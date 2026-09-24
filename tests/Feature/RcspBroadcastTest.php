<?php

namespace Tests\Feature;

use App\Events\RcspCommentPosted;
use App\Models\{RcspForm, User};
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RcspBroadcastTest extends TestCase
{
    public function test_posting_a_comment_broadcasts_to_the_form_channel(): void
    {
        $this->skipUnlessLegacyDataPresent();
        Event::fake([RcspCommentPosted::class]);

        $form = RcspForm::with('rcspBarangay')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post("/katuparan/rcsp-form/{$form->id}/comment", ['text' => 'Please re-upload page 2.'])
            ->assertOk();

        Event::assertDispatched(RcspCommentPosted::class, function ($e) use ($form) {
            $payload = $e->broadcastWith();

            return $e->comment->rcsp_form_id === $form->id
                && $e->broadcastOn()->name === "private-rcsp-form.{$form->id}"
                && $e->broadcastAs() === 'comment.posted'
                && $payload['text'] === 'Please re-upload page 2.'
                && $payload['user_role'] === 'admin';
        });
    }

    public function test_channel_authorization_matches_the_http_rules(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $form = RcspForm::with('rcspBarangay')->firstOrFail();
        $muni = $form->rcspBarangay->municipality_id;

        $callback = require base_path('routes/channels.php');

        $admin = User::where('role', 'admin')->firstOrFail();
        $ownLgu = User::where('role', 'lgu')->where('municipality_id', $muni)->first();
        $otherLgu = User::where('role', 'lgu')->where('municipality_id', '!=', $muni)->first();
        $mblrc = User::where('role', 'mblrc')->firstOrFail();

        // Katuparan reviews everything; MBLRC has no business on this thread.
        $this->assertTrue($this->authorizes($admin, $form->id));
        $this->assertFalse($this->authorizes($mblrc, $form->id));

        if ($ownLgu) {
            $this->assertTrue($this->authorizes($ownLgu, $form->id), 'owning LGU may listen');
        }
        if ($otherLgu) {
            $this->assertFalse($this->authorizes($otherLgu, $form->id), 'other LGU may not listen');
        }
    }

    private function authorizes(User $user, int $formId): bool
    {
        // Exercise the registered channel closure directly.
        $broadcaster = app(\Illuminate\Contracts\Broadcasting\Factory::class)->connection();
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
