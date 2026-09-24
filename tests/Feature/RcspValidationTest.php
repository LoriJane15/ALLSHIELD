<?php

namespace Tests\Feature;

use App\Http\Requests\Rcsp\SubmitRcspPhaseRequest;
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

class RcspValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $lgu;

    private User $admin;

    private RcspBarangay $record;

    private RcspPhase $phase;

    private RcspActivity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $m = Municipality::create(['name' => 'DEMO Validation Municipality']);
        $b = Barangay::create(['municipality_id' => $m->id, 'name' => 'DEMO Validation Barangay']);
        $this->lgu = User::factory()->lgu($m->id)->create();
        $this->admin = User::factory()->role('admin')->create();
        $this->phase = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->where('number', 0)->firstOrFail();
        $this->record = RcspBarangay::create(['barangay_id' => $b->id, 'municipality_id' => $m->id,
            'catalog_key' => RcspPhase::CONFIGURABLE_CATALOG_KEY]);
        $this->activity = RcspActivity::create(['rcsp_phase_id' => $this->phase->id,
            'rcsp_barangay_id' => $this->record->id, 'created_by_user_id' => $this->lgu->id,
            'description' => 'DEMO: Validate', 'normalized_title' => 'demo: validate']);
    }

    public function test_phase_tampering_invalid_conduct_and_blank_submission_are_rejected(): void
    {
        $route = route('lgu.monitoring.submit', [$this->record, $this->activity]);
        $this->actingAs($this->lgu)->post($route, ['conduct' => 'maybe'])->assertSessionHasErrors('conduct');
        $this->actingAs($this->lgu)->post($route, ['conduct' => 'yes'])->assertSessionHasErrors('evidence');
        $this->actingAs($this->lgu)->post($route, [
            'conduct' => 'yes',
            'phase_id' => $this->phase->id,
            'evidence' => UploadedFile::fake()->image('valid.png'),
        ])->assertSessionHasErrors('request');

        $futurePhase = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->where('number', 1)->firstOrFail();
        $future = RcspActivity::create(['rcsp_phase_id' => $futurePhase->id,
            'rcsp_barangay_id' => $this->record->id, 'created_by_user_id' => $this->lgu->id,
            'description' => 'Future', 'normalized_title' => 'future']);
        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.submit', [$this->record, $future]), ['conduct' => 'yes'])
            ->assertForbidden();
    }

    public function test_activity_mismatch_and_duplicate_barangay_are_rejected(): void
    {
        $otherMunicipality = Municipality::create(['name' => 'DEMO Other Scope']);
        $otherBarangay = Barangay::create(['municipality_id' => $otherMunicipality->id, 'name' => 'DEMO Other Scope Barangay']);
        $otherRecord = RcspBarangay::create(['barangay_id' => $otherBarangay->id,
            'municipality_id' => $otherMunicipality->id, 'catalog_key' => RcspPhase::CONFIGURABLE_CATALOG_KEY]);
        $otherActivity = RcspActivity::create(['rcsp_phase_id' => $this->phase->id,
            'rcsp_barangay_id' => $otherRecord->id, 'created_by_user_id' => $this->lgu->id,
            'description' => 'DEMO: Other', 'normalized_title' => 'demo: other']);
        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.submit', [$this->record, $otherActivity]), ['conduct' => 'yes'])
            ->assertForbidden();
        $this->actingAs($this->lgu)->post(route('lgu.rcsp.store'), ['barangay_id' => $this->record->barangay_id])->assertSessionHasErrors('barangay_id');
    }

    public function test_cross_barangay_review_and_missing_return_remark_are_rejected(): void
    {
        Storage::disk('local')->put("rcsp/{$this->record->id}/review.png", 'review evidence');
        $form = RcspForm::create(['lgu_user_id' => $this->lgu->id, 'rcsp_barangay_id' => $this->record->id,
            'rcsp_phase_id' => $this->phase->id, 'rcsp_activity_id' => $this->activity->id,
            'submission_version' => 1, 'conduct' => 'yes', 'file' => "private:rcsp/{$this->record->id}/review.png"]);
        $m = Municipality::create(['name' => 'DEMO Cross Municipality']);
        $b = Barangay::create(['municipality_id' => $m->id, 'name' => 'DEMO Cross Barangay']);
        $other = RcspBarangay::create(['barangay_id' => $b->id, 'municipality_id' => $m->id, 'catalog_key' => 'validation']);
        $this->actingAs($this->admin)->post(route('admin.rcsp.review', $other), ['statuses' => [$form->id => 'approved']])->assertSessionHasErrors('statuses');
        $this->actingAs($this->admin)->post(route('admin.rcsp.review', $this->record), [
            'statuses' => [$form->id => 'to be complied'], 'remarks' => [$form->id => '   '],
        ])->assertSessionHasErrors("remarks.{$form->id}");
    }

    public function test_review_audit_is_saved_and_resubmission_preserves_the_reviewed_version(): void
    {
        Storage::disk('local')->put("rcsp/{$this->record->id}/audit.png", 'audit evidence');
        $form = RcspForm::create(['lgu_user_id' => $this->lgu->id, 'rcsp_barangay_id' => $this->record->id,
            'rcsp_phase_id' => $this->phase->id, 'rcsp_activity_id' => $this->activity->id,
            'submission_version' => 1, 'conduct' => 'yes', 'file' => "private:rcsp/{$this->record->id}/audit.png"]);
        $this->actingAs($this->admin)->post(route('admin.rcsp.review', $this->record), [
            'statuses' => [$form->id => 'to be complied'], 'remarks' => [$form->id => '  DEMO: Correct this.  '],
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->admin->id, $form->fresh()->reviewed_by_user_id);
        $this->assertNotNull($form->fresh()->reviewed_at);
        $this->assertSame('DEMO: Correct this.', $form->fresh()->remarks);
        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->record, $this->activity]), [
            'conduct' => 'yes',
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->admin->id, $form->fresh()->reviewed_by_user_id);
        $this->assertNotNull($form->fresh()->reviewed_at);
        $this->assertDatabaseHas('rcsp_forms', [
            'rcsp_activity_id' => $this->activity->id, 'submission_version' => 2,
            'status' => 'submitted', 'reviewed_by_user_id' => null,
        ]);
        $this->assertDatabaseHas('rcsp_form_reviews', [
            'rcsp_form_id' => $form->id, 'remarks' => 'DEMO: Correct this.',
        ]);
    }

    public function test_resubmission_omits_locked_approved_activities(): void
    {
        Storage::disk('local')->put("rcsp/{$this->record->id}/locked.png", 'locked evidence');
        RcspForm::create(['lgu_user_id' => $this->lgu->id, 'rcsp_barangay_id' => $this->record->id,
            'rcsp_phase_id' => $this->phase->id, 'rcsp_activity_id' => $this->activity->id,
            'submission_version' => 1, 'conduct' => 'yes', 'file' => "private:rcsp/{$this->record->id}/locked.png",
            'status' => 'approved']);
        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.submit', [$this->record, $this->activity]), ['conduct' => 'yes'])
            ->assertSessionHasErrors('activity');
        $this->assertSame(1, RcspForm::where('rcsp_activity_id', $this->activity->id)->count());
    }

    public function test_activity_titles_are_scoped_normalized_and_immutable_after_submission(): void
    {
        $this->actingAs($this->lgu)->post(route('lgu.activities.store', $this->record), [
            'title' => '  Community   Dialogue  ',
        ])->assertSessionHasNoErrors();
        $created = RcspActivity::where('description', 'Community   Dialogue')->firstOrFail();
        $this->assertSame('community dialogue', $created->normalized_title);

        $this->actingAs($this->lgu)->post(route('lgu.activities.store', $this->record), [
            'title' => 'community dialogue',
        ])->assertSessionHasErrors('title');
        $this->actingAs($this->lgu)->post(route('lgu.activities.store', $this->record), [
            'title' => 'Allowed title', 'created_by_user_id' => $this->admin->id,
        ])->assertSessionHasErrors('request');

        $otherCreator = User::factory()->lgu($this->lgu->municipality_id)->create();
        $this->actingAs($otherCreator)
            ->patch(route('lgu.activities.update', [$this->record, $created]), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->record, $created]), [
            'conduct' => 'n/a', 'evidence' => UploadedFile::fake()->image('required-for-na.png'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->lgu)
            ->delete(route('lgu.activities.destroy', [$this->record, $created]))
            ->assertForbidden();
    }

    public function test_disguised_upload_and_effective_runtime_limit_are_enforced(): void
    {
        $this->assertSame(20, SubmitRcspPhaseRequest::maxEvidenceMegabytes());
        $disguised = UploadedFile::fake()->createWithContent('not-an-image.png', "%PDF-1.4\n");
        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->record, $this->activity]), [
            'conduct' => 'yes', 'evidence' => $disguised,
        ])->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('rcsp_forms', 0);
    }

    public function test_completed_record_cannot_be_mutated(): void
    {
        $this->record->update(['status' => 'Completed', 'current_phase' => 5]);
        $this->actingAs($this->lgu)
            ->post(route('lgu.monitoring.submit', [$this->record, $this->activity]), ['conduct' => 'yes'])
            ->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.rcsp.review', $this->record), [
            'statuses' => [1 => 'approved'],
        ])->assertForbidden();
    }
}
