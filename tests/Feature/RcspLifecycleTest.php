<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RcspLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $muni;
    private Barangay $barangay;
    private User $lgu;
    private User $admin;
    private RcspBarangay $rb;
    /** @var array<int,RcspPhase> */
    private array $phases = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->muni = Municipality::create(['name' => 'Digos']);
        $this->barangay = Barangay::create(['municipality_id' => $this->muni->id, 'name' => 'Aplaya']);

        $this->lgu = User::factory()->lgu($this->muni->id)->create();
        $this->admin = User::factory()->role('admin')->create();

        // 6 phases (0-5), phase 0 has 2 activities
        foreach (range(0, 5) as $n) {
            $this->phases[$n] = RcspPhase::create(['number' => $n, 'name' => "Phase {$n} name"]);
        }
        RcspActivity::create(['rcsp_phase_id' => $this->phases[0]->id, 'description' => 'Activity A']);
        RcspActivity::create(['rcsp_phase_id' => $this->phases[0]->id, 'description' => 'Activity B']);

        $this->rb = RcspBarangay::create([
            'barangay_id' => $this->barangay->id,
            'municipality_id' => $this->muni->id,
            'status' => 'Pending',
            'current_phase' => 0,
        ]);
    }

    private function submitPhaseZero(): void
    {
        $activities = RcspActivity::where('rcsp_phase_id', $this->phases[0]->id)->get();
        $payload = ['phase_id' => $this->phases[0]->id];
        foreach ($activities as $a) {
            $payload["conduct_{$a->id}"] = 'yes';
            $payload["file_{$a->id}"] = UploadedFile::fake()->create("evidence_{$a->id}.pdf", 50, 'application/pdf');
        }

        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.submit', $this->rb), $payload)
            ->assertRedirect(route('lgu.monitoring.show', $this->rb));
    }

    private function approvePhaseZero(): void
    {
        $forms = RcspForm::where('rcsp_barangay_id', $this->rb->id)
            ->where('rcsp_phase_id', $this->phases[0]->id)->get();

        $statuses = $forms->mapWithKeys(fn ($f) => [$f->id => 'approved'])->all();

        $this->actingAs($this->admin)
            ->post(route('admin.rcsp.review', $this->rb), [
                'phase_id' => $this->phases[0]->id,
                'statuses' => $statuses,
            ])->assertRedirect();
    }

    public function test_lgu_submit_creates_forms_stores_files_and_moves_barangay_to_ongoing(): void
    {
        $this->submitPhaseZero();

        $forms = RcspForm::where('rcsp_barangay_id', $this->rb->id)->get();
        $this->assertCount(2, $forms);
        $this->assertTrue($forms->every(fn ($f) => $f->status === 'submitted'));
        $this->assertTrue($forms->every(fn ($f) => $f->conduct === 'yes'));

        // evidence files landed on the fake public disk
        foreach ($forms as $f) {
            $this->assertNotNull($f->file);
            Storage::disk('public')->assertExists($f->file);
        }

        $this->assertSame('Ongoing', $this->rb->fresh()->status);
    }

    public function test_lgu_cannot_proceed_before_all_activities_approved(): void
    {
        $this->submitPhaseZero();

        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.proceed', $this->rb))
            ->assertSessionHas('error');

        $this->assertSame(0, $this->rb->fresh()->current_phase);
    }

    public function test_admin_approval_lets_lgu_advance_to_next_phase(): void
    {
        $this->submitPhaseZero();
        $this->approvePhaseZero();

        $this->assertSame(
            2,
            RcspForm::where('rcsp_barangay_id', $this->rb->id)->where('status', 'approved')->count()
        );

        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.proceed', $this->rb))
            ->assertSessionHas('success');

        $fresh = $this->rb->fresh();
        $this->assertSame(1, $fresh->current_phase);
        $this->assertTrue((bool) $fresh->phaseStatus->phase0_completed);
    }

    public function test_lgu_can_post_a_comment_on_a_submitted_form(): void
    {
        $this->submitPhaseZero();
        $form = RcspForm::where('rcsp_barangay_id', $this->rb->id)->first();

        $this->actingAs($this->lgu)
            ->postJson(route('lgu.monitoring.comment', $form->id), ['text' => 'Re-uploaded, please check.'])
            ->assertOk()
            ->assertJson(['success' => true, 'comment' => ['role' => 'lgu']]);

        $this->assertDatabaseHas('rcsp_file_comments', [
            'rcsp_form_id' => $form->id,
            'user_id' => $this->lgu->id,
            'text' => 'Re-uploaded, please check.',
        ]);
    }

    public function test_lgu_from_another_municipality_is_forbidden(): void
    {
        $other = User::factory()->lgu(Municipality::create(['name' => 'Bansalan'])->id)->create();

        $this->actingAs($other)
            ->post(route('lgu.monitoring.submit', $this->rb), ['phase_id' => $this->phases[0]->id])
            ->assertForbidden();
    }

    public function test_completing_final_phase_marks_barangay_completed(): void
    {
        // jump straight to phase 5 with one approved activity
        $this->rb->update(['current_phase' => 5, 'status' => 'Ongoing']);
        $activity = RcspActivity::create(['rcsp_phase_id' => $this->phases[5]->id, 'description' => 'Final activity']);
        RcspForm::create([
            'rcsp_barangay_id' => $this->rb->id,
            'rcsp_phase_id' => $this->phases[5]->id,
            'rcsp_activity_id' => $activity->id,
            'lgu_user_id' => $this->lgu->id,
            'conduct' => 'yes',
            'status' => 'approved',
        ]);

        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.proceed', $this->rb))
            ->assertSessionHas('success');

        $this->assertSame('Completed', $this->rb->fresh()->status);
    }
}
