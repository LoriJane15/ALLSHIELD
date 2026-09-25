<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspFormReview;
use App\Models\RcspPhase;
use App\Models\RcspPhaseTransition;
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
        Storage::fake('local');
        Storage::fake('public');

        $this->muni = Municipality::create(['name' => 'Digos']);
        $this->barangay = Barangay::create(['municipality_id' => $this->muni->id, 'name' => 'Aplaya']);

        $this->lgu = User::factory()->lgu($this->muni->id)->create();
        $this->admin = User::factory()->role('admin')->create();

        $this->phases = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->orderBy('number')->get()->keyBy('number')->all();

        $this->rb = RcspBarangay::create([
            'barangay_id' => $this->barangay->id,
            'municipality_id' => $this->muni->id,
            'status' => 'Pending',
            'current_phase' => 0,
            'catalog_key' => RcspPhase::CONFIGURABLE_CATALOG_KEY,
        ]);
        $this->rb->phaseStatus()->create();
        foreach (['Activity A', 'Activity B'] as $title) {
            RcspActivity::create([
                'rcsp_phase_id' => $this->phases[0]->id,
                'rcsp_barangay_id' => $this->rb->id,
                'created_by_user_id' => $this->lgu->id,
                'description' => $title,
                'normalized_title' => RcspActivity::normalizeTitle($title),
            ]);
        }
    }

    private function submitPhaseZero(): void
    {
        $activities = RcspActivity::where('rcsp_phase_id', $this->phases[0]->id)->get();
        foreach ($activities as $a) {
            $this->actingAs($this->lgu)
                ->post(route('lgu.monitoring.submit', [$this->rb, $a]), [
                    'conduct' => 'yes',
                    'evidence' => UploadedFile::fake()->image("evidence_{$a->id}.png"),
                ])
                ->assertRedirect(route('lgu.monitoring.show', $this->rb));
        }
    }

    private function approvePhaseZero(): void
    {
        $forms = RcspForm::where('rcsp_barangay_id', $this->rb->id)
            ->where('rcsp_phase_id', $this->phases[0]->id)->get();

        $statuses = $forms->mapWithKeys(fn ($f) => [$f->id => 'approved'])->all();

        $this->actingAs($this->admin)
            ->post(route('admin.rcsp.review', $this->rb), [
                'statuses' => $statuses, 'remarks' => [],
            ])->assertRedirect();
    }

    public function test_lgu_submit_creates_forms_stores_files_and_moves_barangay_to_ongoing(): void
    {
        $this->submitPhaseZero();

        $forms = RcspForm::where('rcsp_barangay_id', $this->rb->id)->get();
        $this->assertCount(2, $forms);
        $this->assertTrue($forms->every(fn ($f) => $f->status === 'submitted'));
        $this->assertTrue($forms->every(fn ($f) => $f->conduct === 'yes'));
        $this->assertTrue($forms->every(fn ($f) => $f->submission_version === 1));
        $this->assertTrue($forms->every(fn ($f) => $f->original_filename && $f->detected_mime_type === 'image/png'));

        // evidence files landed on the fake public disk
        foreach ($forms as $f) {
            $this->assertNotNull($f->file);
            Storage::disk('local')->assertExists(str_replace('private:', '', $f->file));
        }

        $this->assertSame('Ongoing', $this->rb->fresh()->status);
    }

    public function test_lgu_cannot_proceed_before_all_activities_approved(): void
    {
        $this->submitPhaseZero();

        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.proceed', $this->rb))
            ->assertSessionHasErrors('phase');

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
        $this->assertDatabaseHas('rcsp_phase_transitions', [
            'rcsp_barangay_id' => $this->rb->id,
            'from_phase' => 0,
            'to_phase' => 1,
            'advanced_by_user_id' => $this->lgu->id,
        ]);
    }

    public function test_mixed_conduct_phase_can_be_reviewed_and_advanced(): void
    {
        $activities = RcspActivity::where('rcsp_phase_id', $this->phases[0]->id)
            ->orderBy('id')->get();
        $notApplicableActivity = RcspActivity::create([
            'rcsp_phase_id' => $this->phases[0]->id,
            'rcsp_barangay_id' => $this->rb->id,
            'created_by_user_id' => $this->lgu->id,
            'description' => 'Activity C',
            'normalized_title' => RcspActivity::normalizeTitle('Activity C'),
        ]);

        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->rb, $activities[0]]), [
            'conduct' => 'yes',
            'evidence' => UploadedFile::fake()->image('conducted.png'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->rb, $activities[1]]), [
            'conduct' => 'no',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->rb, $notApplicableActivity]), [
            'conduct' => 'n/a',
        ])->assertSessionHasNoErrors();

        $forms = RcspForm::where('rcsp_barangay_id', $this->rb->id)
            ->where('rcsp_phase_id', $this->phases[0]->id)->get();
        $statuses = $forms->mapWithKeys(fn (RcspForm $form) => [$form->id => 'approved'])->all();
        $remarks = $forms->mapWithKeys(fn (RcspForm $form) => [
            $form->id => "Reviewed {$form->conduct} activity.",
        ])->all();

        $this->actingAs($this->admin)->post(route('admin.rcsp.review', $this->rb), [
            'statuses' => $statuses,
            'remarks' => $remarks,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Review saved.');

        $this->assertSame(3, RcspForm::where('rcsp_barangay_id', $this->rb->id)
            ->where('status', 'approved')->count());
        $this->assertSame(3, RcspFormReview::whereIn('rcsp_form_id', $forms->pluck('id'))->count());
        $this->assertTrue($forms->every(fn (RcspForm $form) => $form->fresh()->remarks === "Reviewed {$form->conduct} activity."));

        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.proceed', $this->rb))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(1, $this->rb->fresh()->current_phase);
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
            ->post(route('lgu.monitoring.submit', [$this->rb, RcspActivity::firstOrFail()]), ['conduct' => 'yes'])
            ->assertForbidden();
    }

    public function test_completing_final_phase_marks_barangay_completed(): void
    {
        // jump straight to phase 5 with one approved activity
        $this->rb->update(['current_phase' => 5, 'status' => 'Ongoing']);
        $activity = RcspActivity::create([
            'rcsp_phase_id' => $this->phases[5]->id,
            'rcsp_barangay_id' => $this->rb->id,
            'created_by_user_id' => $this->lgu->id,
            'description' => 'Final activity',
            'normalized_title' => 'final activity',
        ]);
        Storage::disk('local')->put("rcsp/{$this->rb->id}/final.png", 'final evidence');
        RcspForm::create([
            'rcsp_barangay_id' => $this->rb->id,
            'rcsp_phase_id' => $this->phases[5]->id,
            'rcsp_activity_id' => $activity->id,
            'lgu_user_id' => $this->lgu->id,
            'conduct' => 'yes',
            'submission_version' => 1,
            'file' => "private:rcsp/{$this->rb->id}/final.png",
            'status' => 'approved',
        ]);

        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.proceed', $this->rb))
            ->assertSessionHas('success');

        $this->assertSame('Completed', $this->rb->fresh()->status);
        $this->assertSame(1, RcspPhaseTransition::where('rcsp_barangay_id', $this->rb->id)->count());
    }

    public function test_returned_activity_resubmission_creates_an_immutable_version_and_retains_evidence(): void
    {
        $activity = RcspActivity::where('rcsp_phase_id', $this->phases[0]->id)->firstOrFail();
        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->rb, $activity]), [
            'conduct' => 'no',
            'evidence' => UploadedFile::fake()->image('first.png'),
        ])->assertSessionHasNoErrors();
        $first = RcspForm::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.rcsp.review', $this->rb), [
            'statuses' => [$first->id => 'to be complied'],
            'remarks' => [$first->id => 'Provide clarification.'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->rb, $activity]), [
            'conduct' => 'yes',
        ])->assertSessionHasNoErrors();

        $versions = RcspForm::where('rcsp_activity_id', $activity->id)->orderBy('submission_version')->get();
        $this->assertCount(2, $versions);
        $this->assertSame([1, 2], $versions->pluck('submission_version')->all());
        $this->assertSame($first->file, $versions[1]->file);
        $this->assertSame('to be complied', $versions[0]->status);
        $this->assertSame('submitted', $versions[1]->status);
        $this->assertDatabaseHas('rcsp_form_reviews', [
            'rcsp_form_id' => $first->id,
            'reviewer_user_id' => $this->admin->id,
            'status' => 'to be complied',
        ]);
        $this->assertSame(1, RcspFormReview::count());
    }

    public function test_returned_activity_can_replace_existing_evidence(): void
    {
        $activity = RcspActivity::where('rcsp_phase_id', $this->phases[0]->id)->firstOrFail();
        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->rb, $activity]), [
            'conduct' => 'yes',
            'evidence' => UploadedFile::fake()->image('first.png'),
        ])->assertSessionHasNoErrors();
        $first = RcspForm::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.rcsp.review', $this->rb), [
            'statuses' => [$first->id => 'to be complied'],
            'remarks' => [$first->id => 'Replace the evidence.'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->rb, $activity]), [
            'conduct' => 'yes',
            'evidence' => UploadedFile::fake()->image('replacement.png'),
        ])->assertSessionHasNoErrors();

        $replacement = RcspForm::where('rcsp_activity_id', $activity->id)
            ->where('submission_version', 2)->firstOrFail();
        $this->assertNotSame($first->file, $replacement->file);
        $this->assertSame('replacement.png', $replacement->original_filename);
        Storage::disk('local')->assertExists(str_replace('private:', '', $first->file));
        Storage::disk('local')->assertExists(str_replace('private:', '', $replacement->file));
    }
}
