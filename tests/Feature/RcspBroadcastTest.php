<?php

namespace Tests\Feature;

use App\Events\RcspCommentPosted;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspPhase;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RcspBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private RcspForm $form;

    private User $admin;

    private User $ownLgu;

    protected function setUp(): void
    {
        parent::setUp();
        $municipality = Municipality::create(['name' => 'Broadcast Municipality']);
        $barangay = Barangay::create(['municipality_id' => $municipality->id, 'name' => 'Broadcast Barangay']);
        $this->ownLgu = User::factory()->lgu($municipality->id)->create();
        $this->admin = User::factory()->role('admin')->create();
        $phase = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->where('number', 0)->firstOrFail();
        $record = RcspBarangay::create(['municipality_id' => $municipality->id, 'barangay_id' => $barangay->id,
            'catalog_key' => RcspPhase::CONFIGURABLE_CATALOG_KEY]);
        $activity = RcspActivity::create(['rcsp_phase_id' => $phase->id, 'rcsp_barangay_id' => $record->id,
            'created_by_user_id' => $this->ownLgu->id, 'description' => 'Broadcast activity',
            'normalized_title' => 'broadcast activity']);
        $this->form = RcspForm::create(['lgu_user_id' => $this->ownLgu->id, 'rcsp_barangay_id' => $record->id,
            'rcsp_phase_id' => $phase->id, 'rcsp_activity_id' => $activity->id,
            'submission_version' => 1, 'conduct' => 'yes', 'status' => 'submitted']);
    }

    public function test_posting_a_comment_broadcasts_to_the_form_channel(): void
    {
        Event::fake([RcspCommentPosted::class]);

        $this->actingAs($this->admin)
            ->post("/katuparan/rcsp-form/{$this->form->id}/comment", ['text' => '  Please re-upload page 2.  '])
            ->assertOk();

        Event::assertDispatched(RcspCommentPosted::class, function ($e) {
            $payload = $e->broadcastWith();

            return $e->comment->rcsp_form_id === $this->form->id
                && $e->broadcastOn()->name === "private-rcsp-form.{$this->form->id}"
                && $e->broadcastAs() === 'comment.posted'
                && $payload['text'] === 'Please re-upload page 2.'
                && $payload['user_role'] === 'admin';
        });
    }

    public function test_channel_authorization_matches_the_http_rules(): void
    {
        require base_path('routes/channels.php');
        $otherMunicipality = Municipality::create(['name' => 'Other Broadcast Municipality']);
        $otherLgu = User::factory()->lgu($otherMunicipality->id)->create();
        $mblrc = User::factory()->role('mblrc')->create();
        $afp = User::factory()->role('afp')->create();
        $superAdmin = User::factory()->role('super_admin')->create();

        $this->assertTrue($this->authorizes($this->admin, $this->form->id));
        $this->assertTrue($this->authorizes($this->ownLgu, $this->form->id));
        $this->assertFalse($this->authorizes($otherLgu, $this->form->id));
        $this->assertFalse($this->authorizes($mblrc, $this->form->id));
        $this->assertFalse($this->authorizes($afp, $this->form->id));
        $this->assertFalse($this->authorizes($superAdmin, $this->form->id));

        $this->form->rcspBarangay->update(['status' => 'Completed']);
        $this->assertFalse($this->authorizes($this->admin, $this->form->id));
        $this->assertFalse($this->authorizes($this->ownLgu, $this->form->id));
    }

    private function authorizes(User $user, int $formId): bool
    {
        // Exercise the registered channel closure directly.
        $broadcaster = app(Factory::class)->connection();
        $ref = new \ReflectionProperty($broadcaster, 'channels');
        $ref->setAccessible(true);
        foreach ($ref->getValue($broadcaster) as $pattern => $cb) {
            if ($pattern === 'rcsp-form.{formId}') {
                return (bool) $cb($user, $formId);
            }
        }
        $this->fail('rcsp-form channel not registered');
    }
}
