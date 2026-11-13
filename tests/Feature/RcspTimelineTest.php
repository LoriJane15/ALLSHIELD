<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspImplementationTimeline;
use App\Models\RcspPhase;
use App\Models\RcspPhaseSchedule;
use App\Models\RcspPhaseScheduleAdjustment;
use App\Models\RcspPhaseTransition;
use App\Models\User;
use App\Services\RcspTimelineService;
use App\Support\RcspActivityCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RcspTimelineTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $municipality;

    private User $lgu;

    private RcspBarangay $rcspBarangay;

    /** @var array<int,RcspPhase> */
    private array $phases;

    protected function setUp(): void
    {
        parent::setUp();

        $this->municipality = Municipality::create(['name' => 'Digos City']);
        $barangay = Barangay::create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Aplaya',
        ]);
        $this->lgu = User::factory()->lgu($this->municipality->id)->create();
        $this->rcspBarangay = RcspBarangay::create([
            'barangay_id' => $barangay->id,
            'municipality_id' => $this->municipality->id,
            'status' => 'Ongoing',
            'current_phase' => 0,
            'catalog_key' => RcspPhase::CONFIGURABLE_CATALOG_KEY,
        ]);
        $this->rcspBarangay->phaseStatus()->create();
        $this->phases = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->orderBy('number')->get()->keyBy('number')->all();
    }

    public function test_lgu_can_view_and_create_timeline_for_own_barangay_without_opening_page_creating_rows(): void
    {
        $response = $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertSee('Implementation Timeline')
            ->assertSee('Phase 0 – Pre-Planning')
            ->assertSee('Week 0 to Immersion')
            ->assertSee(RcspTimelineService::PHASE_DETAILS[0]['objective'])
            ->assertSee('Not scheduled')
            ->assertSee('Edit Phase 0')
            ->assertSee('Implementation Timeline')
            ->assertSee('Aplaya · Digos City')
            ->assertDontSee('RCSP Implementation Timeline')
            ->assertDontSee('The chart shows each phase’s planned schedule and current evidence-based implementation status.')
            ->assertDontSee('data-timeline-range', false)
            ->assertSee('data-phase-row="0"', false)
            ->assertSee('data-timeline-activities-view', false)
            ->assertSee('id="timelineActivitiesModal"', false)
            ->assertSee('id="timelinePhaseEditorModal"', false)
            ->assertSee('data-timeline-phase-editor', false)
            ->assertDontSee('Overall phase progress')
            ->assertDontSee('Next planned deadline')
            ->assertDontSee('Current schedule status')
            ->assertDontSee('Current phase: Phase')
            ->assertDontSee('summary-card', false)
            ->assertDontSee('summary-label', false)
            ->assertDontSee('summary-value', false);

        $this->assertSame(
            1,
            substr_count($response->getContent(), '<h3 class="font-weight-bold mb-1">Implementation Timeline</h3>')
        );

        $this->assertDatabaseCount('rcsp_implementation_timelines', 0);

        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $this->payload())
            ->assertRedirect(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rcsp_implementation_timelines', [
            'rcsp_barangay_id' => $this->rcspBarangay->id,
            'created_by_user_id' => $this->lgu->id,
        ]);
        $this->assertDatabaseCount('rcsp_phase_schedules', 1);
        $this->assertDatabaseHas('rcsp_phase_schedules', [
            'rcsp_implementation_timeline_id' => RcspImplementationTimeline::firstOrFail()->id,
        ]);
        $this->assertFalse(Schema::hasColumn('rcsp_phase_schedules', 'responsible_party'));
        $this->assertFalse((new RcspPhaseSchedule)->isFillable('responsible_party'));
        $this->assertSame(0, $this->rcspBarangay->fresh()->current_phase);
    }

    public function test_timeline_rejects_forged_phase_responsibility_input(): void
    {
        $payload = $this->payload();
        $payload['schedule']['responsible_party'] = 'Forged phase responsibility';

        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $payload)
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('rcsp_implementation_timelines', 0);
        $this->assertDatabaseCount('rcsp_phase_schedules', 0);
    }

    public function test_existing_schedule_updates_without_obsolete_phase_responsibility_column(): void
    {
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $this->payload())
            ->assertSessionHasNoErrors();

        $schedule = RcspPhaseSchedule::firstOrFail();
        $payload = $this->payload();
        $payload['schedule']['implementation_note'] = 'Updated implementation note.';

        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated implementation note.', $schedule->fresh()->implementation_note);
        $this->assertFalse(Schema::hasColumn('rcsp_phase_schedules', 'responsible_party'));
        $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertDontSee('name="schedule[responsible_party]"', false);
    }

    public function test_other_lgu_cannot_view_or_edit_timeline(): void
    {
        $otherMunicipality = Municipality::create(['name' => 'Bansalan']);
        $other = User::factory()->lgu($otherMunicipality->id)->create();

        $this->actingAs($other)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertForbidden();
        $this->actingAs($other)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('rcsp_implementation_timelines', 0);
    }

    public function test_existing_admin_and_afp_scopes_can_view_but_not_edit(): void
    {
        $admin = User::factory()->role('admin')->create();
        $afp = User::factory()->role('afp')->create();
        $payload = $this->payload();
        $payload['schedule']['strategic_objective'] = 'Visible customized objective for reviewers.';
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('admin.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()->assertSee('Implementation Timeline')
            ->assertSee('Visible customized objective for reviewers.')
            ->assertDontSee('Edit Phase 0')
            ->assertDontSee('id="timelinePhaseEditorModal"', false);
        $this->actingAs($afp)
            ->get(route('afp.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()->assertSee('Implementation Timeline')
            ->assertSee('Visible customized objective for reviewers.')
            ->assertDontSee('Edit Phase 0')
            ->assertDontSee('id="timelinePhaseEditorModal"', false);

        $this->assertFalse(Route::has('admin.rcsp.timeline.update'));
        $this->assertFalse(Route::has('afp.rcsp.timeline.update'));
    }

    public function test_custom_objective_is_scoped_to_one_barangay_and_phase(): void
    {
        $payload = $this->payload();
        $payload['schedule']['strategic_objective'] = '  Coordinate municipal pre-planning and local resource preparation.  ';
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rcsp_phase_schedules', [
            'strategic_objective' => 'Coordinate municipal pre-planning and local resource preparation.',
        ]);
        $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertSee('Coordinate municipal pre-planning and local resource preparation.');

        $otherBarangay = Barangay::create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Zone 1',
        ]);
        $otherRecord = RcspBarangay::create([
            'barangay_id' => $otherBarangay->id,
            'municipality_id' => $this->municipality->id,
            'status' => 'Pending',
            'current_phase' => 0,
            'catalog_key' => RcspPhase::CONFIGURABLE_CATALOG_KEY,
        ]);
        $otherRecord->phaseStatus()->create();

        $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $otherRecord))
            ->assertOk()
            ->assertSee(RcspTimelineService::PHASE_DETAILS[0]['objective'])
            ->assertDontSee('Coordinate municipal pre-planning and local resource preparation.');
        $this->assertDatabaseMissing('rcsp_implementation_timelines', ['rcsp_barangay_id' => $otherRecord->id]);
    }

    public function test_strategic_objective_rejects_html_and_excessive_length(): void
    {
        $unsafe = $this->payload();
        $unsafe['schedule']['strategic_objective'] = '<script>alert("unsafe")</script>';
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $unsafe)
            ->assertSessionHasErrors('schedule.strategic_objective');

        $tooLong = $this->payload();
        $tooLong['schedule']['strategic_objective'] = str_repeat('A', 1001);
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $tooLong)
            ->assertSessionHasErrors('schedule.strategic_objective');

        $this->assertDatabaseCount('rcsp_phase_schedules', 0);

        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $this->payload())
            ->assertSessionHasNoErrors();
        RcspPhaseSchedule::firstOrFail()->update(['strategic_objective' => '<script>alert("unsafe")</script>']);
        $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertDontSee('<script>alert("unsafe")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_existing_objective_change_requires_reason_and_records_objective_history(): void
    {
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $this->payload())
            ->assertSessionHasNoErrors();

        $changed = $this->payload();
        $changed['schedule']['strategic_objective'] = 'Customized strategic objective for the barangay.';
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $changed)
            ->assertSessionHasErrors('adjustment_reason');

        $changed['adjustment_reason'] = 'Aligned the objective with the approved local implementation plan.';
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $changed)
            ->assertSessionHasNoErrors();

        $adjustment = RcspPhaseScheduleAdjustment::firstOrFail();
        $this->assertSame(RcspTimelineService::PHASE_DETAILS[0]['objective'], $adjustment->previous_strategic_objective);
        $this->assertSame('Customized strategic objective for the barangay.', $adjustment->updated_strategic_objective);
        $this->assertSame($this->lgu->id, $adjustment->changed_by_user_id);

        $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertSee(RcspTimelineService::PHASE_DETAILS[0]['objective'])
            ->assertSee('Customized strategic objective for the barangay.')
            ->assertSee('Aligned the objective with the approved local implementation plan.');
    }

    public function test_objective_only_change_preserves_dates_and_date_only_change_preserves_objective(): void
    {
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $this->payload())
            ->assertSessionHasNoErrors();
        $schedule = RcspPhaseSchedule::firstOrFail();
        $originalStart = $schedule->planned_start_date->format('Y-m-d');
        $originalEnd = $schedule->planned_end_date->format('Y-m-d');

        $objectiveChange = $this->payload();
        $objectiveChange['schedule']['strategic_objective'] = 'Barangay-specific implementation objective.';
        $objectiveChange['adjustment_reason'] = 'Localized objective approved by the RCSP team.';
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $objectiveChange)
            ->assertSessionHasNoErrors();

        $schedule->refresh();
        $this->assertSame($originalStart, $schedule->planned_start_date->format('Y-m-d'));
        $this->assertSame($originalEnd, $schedule->planned_end_date->format('Y-m-d'));

        $dateChange = $this->payload();
        $dateChange['schedule']['strategic_objective'] = 'Barangay-specific implementation objective.';
        $dateChange['schedule']['planned_end_date'] = '2027-02-08';
        $dateChange['adjustment_reason'] = 'Extended the implementation period by one day.';
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $dateChange)
            ->assertSessionHasNoErrors();

        $schedule->refresh();
        $this->assertSame('Barangay-specific implementation objective.', $schedule->strategic_objective);
        $this->assertSame('2027-02-08', $schedule->planned_end_date->format('Y-m-d'));
        $dateAdjustment = RcspPhaseScheduleAdjustment::latest('id')->firstOrFail();
        $this->assertNull($dateAdjustment->previous_strategic_objective);
        $this->assertNull($dateAdjustment->updated_strategic_objective);
    }

    public function test_page_renders_one_reusable_modal_with_phase_specific_edit_targets(): void
    {
        $phaseOne = $this->payload(1);
        $phaseOne['schedule']['strategic_objective'] = 'Customized Phase 1 objective for this barangay.';
        $phaseOne['schedule']['implementation_note'] = "Phase 1-specific note.\nSecond line with \"quotes\".";
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $phaseOne)
            ->assertSessionHasNoErrors();

        $response = $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertSee('data-week-gantt', false)
            ->assertSee('gantt-scroll', false)
            ->assertSee('Edit Phase 0')
            ->assertSee('Edit Phase 5')
            ->assertSee('id="timelinePhaseEditorModal"', false)
            ->assertSee('timeline-phase-modal-dialog', false)
            ->assertSee('modal-dialog-scrollable', false)
            ->assertSee('modal-dialog-centered', false)
            ->assertSee('timeline-phase-modal-footer', false)
            ->assertSee('data-phase-number="2"', false)
            ->assertSee('timeline-phase-modal-header', false)
            ->assertSee('timeline-phase-modal-title', false)
            ->assertDontSee('id="phase-editor"', false);

        $phaseData = $this->phaseEditorData($response->getContent());
        $this->assertSame(RcspTimelineService::PHASE_DETAILS[0]['objective'], $phaseData[0]['objective']);
        $this->assertSame('Pre-Planning', $phaseData[0]['name']);
        $this->assertSame(RcspTimelineService::PHASE_DETAILS[1]['objective'], $phaseData[1]['officialObjective']);
        $this->assertSame('Shape', $phaseData[1]['name']);
        $this->assertSame('Customized Phase 1 objective for this barangay.', $phaseData[1]['objective']);
        $this->assertSame("Phase 1-specific note.\nSecond line with \"quotes\".", $phaseData[1]['implementationNote']);
        $this->assertArrayNotHasKey('responsibleParty', $phaseData[1]);
        $this->assertArrayNotHasKey('responsibleOfficePerson', $phaseData[1]);
        $this->assertNotSame($phaseData[0]['objective'], $phaseData[1]['objective']);
        $this->assertNotSame($phaseData[0]['officialObjective'], $phaseData[1]['officialObjective']);
        foreach (range(0, 5) as $phaseNumber) {
            $this->assertSame($phaseNumber, $phaseData[$phaseNumber]['number']);
            $this->assertSame(RcspTimelineService::PHASE_DETAILS[$phaseNumber]['name'], $phaseData[$phaseNumber]['name']);
            $this->assertSame(RcspTimelineService::PHASE_DETAILS[$phaseNumber]['objective'], $phaseData[$phaseNumber]['officialObjective']);
            $this->assertStringContainsString(
                'data-phase-number="'.$phaseNumber.'"',
                $response->getContent()
            );
        }

        $this->assertSame(1, substr_count($response->getContent(), 'data-timeline-phase-editor'));
        $this->assertSame(1, substr_count($response->getContent(), 'id="timelinePhaseEditorModal"'));
        $this->assertSame(1, substr_count($response->getContent(), 'id="timelinePhaseEditorData"'));
        $this->assertSame(1, substr_count($response->getContent(), 'name="schedule[strategic_objective]"'));
        $this->assertSame(6, preg_match_all('/<button type="button"[^>]+data-timeline-phase-edit/s', $response->getContent()));
        $this->assertSame(0, substr_count($response->getContent(), 'data-bs-target="#timelinePhaseEditorModal"'));
        $this->assertSame(0, substr_count($response->getContent(), 'data-bs-toggle="modal"'));
        $this->assertSame(1, substr_count($response->getContent(), "document.addEventListener('click'"));
        $this->assertStringNotContainsString('!window.bootstrap?.Modal', $response->getContent());
        $this->assertStringContainsString('event.preventDefault();', $response->getContent());
        $this->assertStringNotContainsString("addEventListener('show.bs.modal'", $response->getContent());
        $this->assertStringContainsString("const phaseNumber = String(button.dataset.phaseNumber ?? '');", $response->getContent());
        $this->assertStringContainsString('const phase = editorData?.[phaseNumber];', $response->getContent());
        $this->assertStringContainsString('if (window.bootstrap?.Modal)', $response->getContent());
        $this->assertStringContainsString('window.bootstrap.Modal.getInstance(modal)', $response->getContent());
        $this->assertStringContainsString('new window.bootstrap.Modal(modal)', $response->getContent());
        $this->assertStringNotContainsString('Modal.getOrCreateInstance', $response->getContent());
        $this->assertStringContainsString('window.jQuery?.fn?.modal', $response->getContent());
        $this->assertStringContainsString('RCSP timeline phase data was not found for phase ${phaseNumber}.', $response->getContent());
        $this->assertStringNotContainsString('editorData[0]', $response->getContent());
        $this->assertStringContainsString("setValue('strategic_objective', phase.objective);", $response->getContent());
        $this->assertStringContainsString("setValue('planned_start_date', phase.plannedStart);", $response->getContent());
        $this->assertStringNotContainsString("setValue('responsible_party'", $response->getContent());
        $this->assertStringContainsString("setValue('implementation_note', phase.implementationNote);", $response->getContent());
        $this->assertStringContainsString("setValue('adjustment_reason');", $response->getContent());
        $this->assertStringNotContainsString('RCSP implementation start date', $response->getContent());
        $this->assertStringNotContainsString('implementationStart', $response->getContent());
        $this->assertStringNotContainsString('actual-marker', $response->getContent());
        $this->assertStringNotContainsString('name="schedule[estimated_duration_weeks]"', $response->getContent());
        $this->assertStringNotContainsString('name="milestone', $response->getContent());
        $this->assertStringContainsString('id="timelineCalculatedDuration"', $response->getContent());
        $this->assertStringContainsString('instance.show(button);', $response->getContent());
        $this->assertLessThan(
            strpos($response->getContent(), 'showModal(editorModal, button);'),
            strpos($response->getContent(), 'populatePhaseFields(phase);')
        );
        $this->assertStringContainsString('clearValidationState();', $response->getContent());
        $this->assertStringContainsString('grid-template-columns: 2.25rem minmax(0, 1fr) 2.25rem;', $response->getContent());
        $this->assertStringContainsString('text-align: center;', $response->getContent());
        $this->assertStringContainsString('max-width: 900px;', $response->getContent());
        $this->assertStringContainsString('max-height: 88vh;', $response->getContent());
        $this->assertStringContainsString('name="phase_number" value=""', $response->getContent());
        $this->assertStringContainsString('id="strategic_objective"', $response->getContent());
        $this->assertStringNotContainsString('name="schedule[responsible_party]"', $response->getContent());
        $this->assertStringNotContainsString('Responsible office or person', $response->getContent());
        $this->assertStringNotContainsString('name="phase_number" value="0"', $response->getContent());
        $this->assertSame(1, preg_match('/<textarea id="strategic_objective"[^>]*>(.*?)<\/textarea>/s', $response->getContent(), $objectiveField));
        $this->assertSame('', trim($objectiveField[1]));
    }

    public function test_validation_failure_reopens_the_correct_modal_and_preserves_old_input(): void
    {
        $payload = $this->payload(2);
        $payload['schedule']['strategic_objective'] = 'Preserve this submitted strategic objective.';
        $payload['schedule']['planned_end_date'] = '2027-02-14';

        $this->actingAs($this->lgu)
            ->from(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $payload)
            ->assertRedirect(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertSessionHasErrors('schedule.planned_end_date');

        $response = $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertSee('data-validation-reopen="true"', false)
            ->assertSee('Edit Phase 2')
            ->assertSee('name="phase_number" value="2"', false)
            ->assertSee('Preserve this submitted strategic objective.')
            ->assertSee('id="planned_end_date_error"', false)
            ->assertSee('aria-describedby="planned_end_date_error"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-timeline-phase-editor'));
        $this->assertSame(1, substr_count($response->getContent(), 'name="schedule[strategic_objective]"'));
    }

    public function test_planned_end_cannot_precede_planned_start(): void
    {
        $payload = $this->payload(3);
        $payload['schedule']['planned_end_date'] = '2027-02-09';

        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $payload)
            ->assertSessionHasErrors('schedule.planned_end_date');

        $this->assertDatabaseCount('rcsp_implementation_timelines', 0);
    }

    public function test_overlapping_phase_schedules_are_accepted_and_duration_is_calculated(): void
    {
        $phaseTwo = $this->payload(2);
        $phaseTwo['schedule']['planned_start_date'] = '2027-01-15';
        $phaseTwo['schedule']['planned_end_date'] = '2027-01-28';
        $phaseThree = $this->payload(3);
        $phaseThree['schedule']['planned_start_date'] = '2027-01-22';
        $phaseThree['schedule']['planned_end_date'] = '2027-02-05';

        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $phaseTwo)
            ->assertSessionHasNoErrors();
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $phaseThree)
            ->assertSessionHasNoErrors();

        $data = app(RcspTimelineService::class)->build($this->rcspBarangay->fresh());
        $this->assertSame('2027-01-15', $data['chart']['start']->format('Y-m-d'));
        $this->assertSame('2027-02-05', $data['chart']['end']->format('Y-m-d'));
        $this->assertSame(4, $data['chart']['weekCount']);
        $this->assertSame(14, $data['rows'][2]['duration_days']);
        $this->assertSame(2, $data['rows'][2]['duration_weeks']);
        $this->assertSame(1, $data['rows'][2]['week_start']);
        $this->assertSame(2, $data['rows'][2]['week_span']);
        $this->assertSame(2, $data['rows'][3]['week_start']);
        $this->assertSame(3, $data['rows'][3]['week_span']);
        $this->assertDatabaseHas('rcsp_phase_schedules', [
            'rcsp_phase_id' => $this->phases[2]->id,
            'estimated_duration_weeks' => 2,
        ]);
    }

    public function test_user_controlled_estimated_duration_is_rejected(): void
    {
        $payload = $this->payload();
        $payload['schedule']['estimated_duration_weeks'] = 99;

        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $payload)
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseCount('rcsp_phase_schedules', 0);
    }

    public function test_date_adjustment_requires_reason_and_records_immutable_before_and_after_dates(): void
    {
        $this->saveTimeline();
        $changed = $this->payload(2);
        $changed['schedule']['planned_end_date'] = '2027-02-22';

        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $changed)
            ->assertSessionHasErrors('adjustment_reason');
        $this->assertDatabaseCount('rcsp_phase_schedule_adjustments', 0);

        $changed['adjustment_reason'] = 'Community consultation required an additional day.';
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $changed)
            ->assertSessionHasNoErrors();

        $adjustment = RcspPhaseScheduleAdjustment::firstOrFail();
        $this->assertSame('2027-02-21', $adjustment->previous_planned_end_date->format('Y-m-d'));
        $this->assertSame('2027-02-22', $adjustment->updated_planned_end_date->format('Y-m-d'));
        $this->assertSame($this->lgu->id, $adjustment->changed_by_user_id);
        $this->assertSame('Community consultation required an additional day.', $adjustment->reason);

        $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertSee('Community consultation required an additional day.')
            ->assertSee('Feb 21, 2027')
            ->assertSee('Feb 22, 2027');
    }

    public function test_saved_dates_are_not_replaced_when_timeline_is_reopened(): void
    {
        $this->saveTimeline();
        $timeline = RcspImplementationTimeline::with('schedules.phase')->firstOrFail();
        $originalDates = $timeline->schedules->mapWithKeys(fn ($schedule) => [
            $schedule->phase->number => $schedule->planned_end_date->format('Y-m-d'),
        ])->all();

        $response = $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk();
        $this->assertSame('2027-02-21', $this->phaseEditorData($response->getContent())[2]['plannedEnd']);

        $reloadedDates = RcspImplementationTimeline::with('schedules.phase')->firstOrFail()
            ->schedules->mapWithKeys(fn ($schedule) => [
                $schedule->phase->number => $schedule->planned_end_date->format('Y-m-d'),
            ])->all();
        $this->assertSame($originalDates, $reloadedDates);
        $this->assertDatabaseCount('rcsp_phase_schedule_adjustments', 0);
    }

    public function test_week_axis_uses_saved_date_range_and_keeps_unscheduled_phases_visible(): void
    {
        $payload = $this->payload();
        $payload['schedule']['planned_start_date'] = '2027-02-01';
        $payload['schedule']['planned_end_date'] = '2027-02-21';
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $payload)
            ->assertSessionHasNoErrors();

        $response = $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertSee('Feb 1, 2027 – Feb 21, 2027 · 3 weeks')
            ->assertSee('data-timeline-range', false)
            ->assertSee('Week 1')
            ->assertSee('Week 2')
            ->assertSee('Week 3')
            ->assertSee('Feb 1–Feb 7')
            ->assertSee('Feb 15–Feb 21')
            ->assertSee('data-phase-row="5"', false)
            ->assertSee('data-week-gantt-scroll', false)
            ->assertSee('@media (max-width: 767.98px)', false);

        $this->assertSame(3, substr_count($response->getContent(), 'data-week-number='));
        $this->assertStringContainsString('grid-column:1 / span 3', $response->getContent());
    }

    public function test_reusable_activities_modal_receives_current_evidence_and_only_available_file_links(): void
    {
        Storage::fake('local');
        $available = $this->activity('Conduct barangay coordination', 0, 'Coordination record completed');
        $missingActivityName = 'Document the complete community response and partner-agency coordination outcome';
        $missingExpectedResult = 'Community response and partner-agency coordination records documented';
        $missingFilename = 'very-long-demo-evidence-filename-for-community-response-and-coordination.pdf';
        $reviewerRemarks = 'Add the signed attendance record and the complete partner-agency coordination notes.';
        $missing = $this->activity($missingActivityName, 0, $missingExpectedResult);
        foreach (range(1, 5) as $phaseNumber) {
            $entry = RcspActivityCatalog::entriesForPhase($phaseNumber)[0];
            $this->activity($entry['description'], $phaseNumber, $entry['expected_result']);
        }
        $path = "rcsp/{$this->rcspBarangay->id}/coordination.pdf";
        Storage::disk('local')->put($path, 'synthetic evidence');
        $availableForm = RcspForm::create(array_merge(
            $this->formData($available, 'submitted', 2, '2027-02-03 09:30:00'),
            [
                'conduct' => 'yes',
                'file' => 'private:'.$path,
                'original_filename' => 'coordination.pdf',
            ]
        ));
        RcspForm::create(array_merge(
            $this->formData($missing, 'to be complied', 1, '2027-02-04 10:00:00', '2027-02-05 11:00:00'),
            [
                'conduct' => 'yes',
                'file' => "private:rcsp/{$this->rcspBarangay->id}/{$missingFilename}",
                'original_filename' => $missingFilename,
                'remarks' => $reviewerRemarks,
            ]
        ));

        $response = $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertSee('id="timelineActivitiesModal"', false)
            ->assertSee('id="timelineActivitiesBody"', false)
            ->assertSee('activities-modal-dialog', false)
            ->assertSee('class="activities-table-wrap"', false)
            ->assertSee('activities-modal-table', false)
            ->assertSee('<colgroup>', false)
            ->assertSee('Expected Result/Output')
            ->assertSee('Office/Person Responsible')
            ->assertSee('Reviewer Remarks')
            ->assertSee('Manage activity evidence')
            ->assertDontSee('Expand Activities')
            ->assertDontSee('type="file"', false);

        $phaseData = $this->activitiesModalData($response->getContent());
        $this->assertSame('Pre-Planning', $phaseData[0]['name']);
        $this->assertSame('Conduct barangay coordination', $phaseData[0]['activities'][0]['name']);
        $this->assertSame('Coordination record completed', $phaseData[0]['activities'][0]['expectedResult']);
        $this->assertSame(RcspActivityCatalog::UNSPECIFIED_RESPONSIBILITY, $phaseData[0]['activities'][0]['responsibleOfficePerson']);
        $this->assertSame('Conducted · Pending verification', $phaseData[0]['activities'][0]['evidenceState']);
        $this->assertSame('pending_review', $phaseData[0]['activities'][0]['evidenceStatusKey']);
        $this->assertSame('Feb 3, 2027', $phaseData[0]['activities'][0]['submittedDate']);
        $this->assertSame('9:30 AM', $phaseData[0]['activities'][0]['submittedTime']);
        $this->assertTrue($phaseData[0]['activities'][0]['fileAvailable']);
        $this->assertSame(route('rcsp.evidence', $availableForm), $phaseData[0]['activities'][0]['previewUrl']);
        $this->assertSame(route('rcsp.evidence', $availableForm), $phaseData[0]['activities'][0]['downloadUrl']);
        $this->assertSame($missingActivityName, $phaseData[0]['activities'][1]['name']);
        $this->assertSame($missingExpectedResult, $phaseData[0]['activities'][1]['expectedResult']);
        $this->assertSame($missingFilename, $phaseData[0]['activities'][1]['evidence']);
        $this->assertSame('Revision required', $phaseData[0]['activities'][1]['evidenceState']);
        $this->assertSame('revision_required', $phaseData[0]['activities'][1]['evidenceStatusKey']);
        $this->assertSame('Feb 5, 2027', $phaseData[0]['activities'][1]['reviewedDate']);
        $this->assertSame('11:00 AM', $phaseData[0]['activities'][1]['reviewedTime']);
        $this->assertSame($reviewerRemarks, $phaseData[0]['activities'][1]['remarks']);
        $this->assertFalse($phaseData[0]['activities'][1]['fileAvailable']);
        $this->assertNull($phaseData[0]['activities'][1]['previewUrl']);
        $this->assertNull($phaseData[0]['activities'][1]['downloadUrl']);
        $this->assertSame('Shape', $phaseData[1]['name']);
        $this->assertSame(RcspActivityCatalog::ACTIVITIES[1][0], $phaseData[1]['activities'][0]['name']);
        $this->assertSame(RcspActivityCatalog::EXPECTED_RESULTS['orient_local_core_team'], $phaseData[1]['activities'][0]['expectedResult']);
        $this->assertSame('C/MLGOO', $phaseData[1]['activities'][0]['responsibleOfficePerson']);
        $this->assertSame('No evidence submitted', $phaseData[1]['activities'][0]['evidence']);
        $this->assertSame('Not submitted', $phaseData[1]['activities'][0]['submittedDate']);
        $this->assertSame('Not yet reviewed', $phaseData[1]['activities'][0]['reviewedDate']);
        $this->assertSame('No reviewer remarks', $phaseData[1]['activities'][0]['remarks']);
        $this->assertNotSame($phaseData[0]['activities'][0]['name'], $phaseData[1]['activities'][0]['name']);
        foreach (range(1, 5) as $phaseNumber) {
            $entry = RcspActivityCatalog::entriesForPhase($phaseNumber)[0];
            $this->assertSame(
                $entry['responsible_office_person'],
                $phaseData[$phaseNumber]['activities'][0]['responsibleOfficePerson']
            );
        }
        $this->assertSame(1, substr_count($response->getContent(), 'Manage activity evidence'));
        $this->assertSame(1, substr_count($response->getContent(), 'id="timelineActivitiesModal"'));
        $this->assertSame(1, substr_count($response->getContent(), 'class="table table-hover mb-0 activities-modal-table"'));
        $this->assertSame(9, substr_count($response->getContent(), 'scope="col"'));
        preg_match_all('/<col style="width: (\d+)%">/', $response->getContent(), $columnMatches);
        $this->assertSame([16, 15, 12, 13, 9, 9, 9, 11, 6], array_map('intval', $columnMatches[1]));
        $this->assertSame(100, array_sum(array_map('intval', $columnMatches[1])));
        $this->assertStringContainsString('width: 96vw; max-width: 1600px;', $response->getContent());
        $this->assertStringContainsString('width: 100%; min-width: 1350px; table-layout: fixed;', $response->getContent());
        $this->assertStringContainsString('overflow-x: auto;', $response->getContent());
        $this->assertStringContainsString('.activities-modal-table th,', $response->getContent());
        $this->assertStringContainsString('.activities-modal-table td { box-sizing: border-box;', $response->getContent());
        $this->assertStringNotContainsString('activity-card', $response->getContent());
        $this->assertStringNotContainsString("document.createElement('article')", $response->getContent());
        $this->assertStringContainsString("document.createElement('tr')", $response->getContent());
        $this->assertStringContainsString("document.createElement('td')", $response->getContent());
        $this->assertStringContainsString('min-width: 0;', $response->getContent());
        $this->assertStringContainsString('white-space: normal !important;', $response->getContent());
        $this->assertStringContainsString('overflow-wrap: anywhere;', $response->getContent());
        $this->assertStringNotContainsString('.activities-modal-table tr { height:', $response->getContent());
        $this->assertStringContainsString('color: #fff !important;', $response->getContent());
        $this->assertStringContainsString('flex-direction: column;', $response->getContent());
        $this->assertStringContainsString("appendTextCell(row, activity.name, 'activity-text');", $response->getContent());
        $this->assertStringContainsString("appendTextCell(row, activity.expectedResult, 'activity-result');", $response->getContent());
        $this->assertStringContainsString("appendTextCell(row, activity.responsibleOfficePerson, 'activity-responsibility');", $response->getContent());
        $this->assertStringContainsString('appendDateCell(row, activity.submittedDate, activity.submittedTime);', $response->getContent());
        $this->assertStringContainsString('appendDateCell(row, activity.reviewedDate, activity.reviewedTime);', $response->getContent());
        $this->assertStringContainsString("appendTextCell(row, activity.remarks, 'activity-remarks');", $response->getContent());
        $this->assertStringContainsString('row.appendChild(actionCell);', $response->getContent());
        $this->assertStringContainsString('activitiesTitle.textContent = `Phase ${phase.number}', $response->getContent());
        $this->assertStringContainsString('reviewBadge.className = `activity-review-badge activity-review-${activity.evidenceStatusKey}`;', $response->getContent());
        $this->assertStringContainsString("unavailable.className = 'file-unavailable';", $response->getContent());
        $this->assertStringContainsString("preview.setAttribute('aria-label'", $response->getContent());
        $this->assertStringContainsString("download.setAttribute('aria-label'", $response->getContent());
        $this->assertSame(6, preg_match_all('/<button type="button"[^>]+data-timeline-activities-view/s', $response->getContent()));
        foreach (range(0, 5) as $phaseNumber) {
            $this->assertStringContainsString(
                'aria-label="View Phase '.$phaseNumber.' activities"',
                $response->getContent()
            );
            $this->assertSame($phaseNumber, $phaseData[$phaseNumber]['number']);
        }
        $this->assertStringContainsString('const phase = activitiesData[phaseNumber];', $response->getContent());
        $this->assertStringContainsString('populateActivities(phase);', $response->getContent());
        $this->assertLessThan(
            strpos($response->getContent(), 'showModal(activitiesModal, button);'),
            strpos($response->getContent(), 'populateActivities(phase);')
        );
    }

    public function test_evidence_statuses_distinguish_no_evidence_partial_and_late_partial_submission(): void
    {
        $activityA = $this->activity('Evidence status activity A');
        $this->activity('Evidence status activity B');
        $this->savePhaseZeroSchedule('2027-02-01', '2027-02-07');
        $service = app(RcspTimelineService::class);

        $scheduled = $service->build($this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-01'))['rows'][0];
        $this->assertSame('scheduled', $scheduled['status']['key']);
        $this->assertSame('delayed_no_evidence', $service->build(
            $this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-02')
        )['rows'][0]['status']['key']);

        RcspForm::create($this->formData($activityA, 'submitted', 1, '2027-02-03 09:00:00'));
        $this->assertSame('in_progress', $service->build(
            $this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-04')
        )['rows'][0]['status']['key']);
        $this->assertSame('delayed_incomplete_evidence', $service->build(
            $this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-08')
        )['rows'][0]['status']['key']);
    }

    public function test_all_evidence_submission_uses_first_submission_dates_and_excludes_reviewer_time(): void
    {
        $activityA = $this->activity('On-time evidence activity A');
        $activityB = $this->activity('On-time evidence activity B');
        $this->savePhaseZeroSchedule('2027-02-01', '2027-02-07');
        RcspForm::create($this->formData($activityA, 'approved', 1, '2027-02-05 08:00:00', '2027-02-10 08:00:00'));
        RcspForm::create($this->formData($activityB, 'submitted', 1, '2027-02-07 17:00:00'));

        $row = app(RcspTimelineService::class)
            ->build($this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-10'))['rows'][0];

        $this->assertSame('submitted_on_time_pending_review', $row['status']['key']);
        $this->assertSame('2027-02-07', $row['all_evidence_submitted_date']->format('Y-m-d'));
        $this->assertSame(2, $row['activity_submitted']);
        $this->assertSame(1, $row['activity_approved']);
        $this->assertSame(0, $row['activity_remaining']);
    }

    public function test_late_submission_and_returned_current_evidence_have_distinct_statuses(): void
    {
        $activityA = $this->activity('Late evidence activity A');
        $activityB = $this->activity('Late evidence activity B');
        $this->savePhaseZeroSchedule('2027-02-01', '2027-02-07');
        RcspForm::create($this->formData($activityA, 'submitted', 1, '2027-02-06 08:00:00'));
        RcspForm::create($this->formData($activityB, 'submitted', 1, '2027-02-09 08:00:00'));

        $service = app(RcspTimelineService::class);
        $this->assertSame('submitted_late_pending_review', $service->build(
            $this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-10')
        )['rows'][0]['status']['key']);

        RcspForm::create($this->formData($activityA, 'to be complied', 2, '2027-02-11 08:00:00'));
        $this->assertSame('revision_required', $service->build(
            $this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-11')
        )['rows'][0]['status']['key']);
        $this->assertSame('2027-02-09', $service->build(
            $this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-11')
        )['rows'][0]['all_evidence_submitted_date']->format('Y-m-d'));
    }

    public function test_verified_completion_comes_from_transition_and_preserves_submission_timeliness(): void
    {
        $activityA = $this->activity('Completed evidence activity A');
        $activityB = $this->activity('Completed evidence activity B');
        $this->savePhaseZeroSchedule('2027-02-01', '2027-02-07');
        RcspForm::create($this->formData($activityA, 'approved', 1, '2027-02-05 08:00:00', '2027-02-10 08:00:00'));
        RcspForm::create($this->formData($activityB, 'approved', 1, '2027-02-07 17:00:00', '2027-02-10 08:00:00'));
        RcspPhaseTransition::create([
            'rcsp_barangay_id' => $this->rcspBarangay->id,
            'from_phase' => 0,
            'to_phase' => 1,
            'advanced_by_user_id' => $this->lgu->id,
            'advanced_at' => '2027-02-10 10:00:00',
        ]);
        $this->rcspBarangay->update(['current_phase' => 1]);

        $row = app(RcspTimelineService::class)
            ->build($this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-11'))['rows'][0];
        $this->assertSame('completed_verified_on_time', $row['status']['key']);
        $this->assertSame('2027-02-10', $row['verified_completion_date']->format('Y-m-d'));

        RcspForm::where('rcsp_activity_id', $activityB->id)->update(['submitted_at' => '2027-02-09 17:00:00']);
        $late = app(RcspTimelineService::class)
            ->build($this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-11'))['rows'][0];
        $this->assertSame('completed_verified_late', $late['status']['key']);
        $this->assertSame('2027-02-10', $late['verified_completion_date']->format('Y-m-d'));
    }

    public function test_current_phase_progress_uses_latest_approved_activity_versions(): void
    {
        $activityA = $this->activity('Activity A');
        $activityB = $this->activity('Activity B');
        RcspForm::create($this->formData($activityA, 'approved', 1));
        RcspForm::create($this->formData($activityB, 'approved', 1));
        RcspForm::create($this->formData($activityB, 'submitted', 2));

        $current = app(RcspTimelineService::class)
            ->build($this->rcspBarangay->fresh(), CarbonImmutable::parse('2027-02-02'))['current'];

        $this->assertSame(2, $current['activity_total']);
        $this->assertSame(2, $current['activity_submitted']);
        $this->assertSame(1, $current['activity_approved']);
        $this->assertSame(0, $current['activity_remaining']);
    }

    public function test_timeline_has_no_manual_completion_route_and_does_not_unlock_future_phase(): void
    {
        $this->saveTimeline();

        $this->assertSame(0, $this->rcspBarangay->fresh()->current_phase);
        $this->assertFalse(Route::has('lgu.rcsp.timeline.complete'));
        $this->assertDatabaseCount('rcsp_phase_transitions', 0);
    }

    public function test_completed_barangay_timeline_is_viewable_but_final_locked_against_changes(): void
    {
        $this->saveTimeline();
        $this->rcspBarangay->update(['status' => 'Completed', 'current_phase' => 5]);
        $payload = $this->payload();

        $this->actingAs($this->lgu)
            ->get(route('lgu.rcsp.timeline.show', $this->rcspBarangay))
            ->assertOk()
            ->assertDontSee('Edit Phase 0')
            ->assertDontSee('id="timelinePhaseEditorModal"', false);
        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $payload)
            ->assertForbidden();

        $this->assertFalse(Schema::hasColumn('rcsp_phase_schedules', 'responsible_party'));
    }

    public function test_existing_barangay_with_historical_transition_displays_verified_progress(): void
    {
        $this->rcspBarangay->update(['current_phase' => 2]);
        RcspPhaseTransition::create([
            'rcsp_barangay_id' => $this->rcspBarangay->id,
            'from_phase' => 0,
            'to_phase' => 1,
            'advanced_by_user_id' => $this->lgu->id,
            'advanced_at' => '2026-12-10 09:00:00',
        ]);
        RcspPhaseTransition::create([
            'rcsp_barangay_id' => $this->rcspBarangay->id,
            'from_phase' => 1,
            'to_phase' => 2,
            'advanced_by_user_id' => $this->lgu->id,
            'advanced_at' => '2026-12-20 09:00:00',
        ]);

        $data = app(RcspTimelineService::class)->build($this->rcspBarangay->fresh());
        $this->assertSame(2, $data['completed_count']);
        $this->assertSame('2026-12-20', $data['rows'][1]['verified_completion_date']->format('Y-m-d'));
        $this->assertDatabaseCount('rcsp_implementation_timelines', 0);
    }

    public function test_additive_timeline_migration_preserves_existing_rcsp_records(): void
    {
        $activity = $this->activity('Preserved activity');
        $form = RcspForm::create($this->formData($activity, 'approved', 1));
        $transition = RcspPhaseTransition::create([
            'rcsp_barangay_id' => $this->rcspBarangay->id,
            'from_phase' => 0,
            'to_phase' => 1,
            'advanced_by_user_id' => $this->lgu->id,
            'advanced_at' => '2027-02-07 10:00:00',
        ]);

        $migration = require database_path('migrations/2026_11_11_000001_create_rcsp_implementation_timeline_tables.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('rcsp_implementation_timelines'));
        $migration->up();

        $this->assertTrue(Schema::hasTable('rcsp_phase_schedule_adjustments'));
        $this->assertDatabaseHas('rcsp_barangays', ['id' => $this->rcspBarangay->id]);
        $this->assertDatabaseHas('rcsp_activities', ['id' => $activity->id]);
        $this->assertDatabaseHas('rcsp_forms', ['id' => $form->id]);
        $this->assertDatabaseHas('rcsp_phase_transitions', ['id' => $transition->id]);
    }

    public function test_drop_responsibility_migration_removes_only_the_column_and_rollback_restores_nullable_schema(): void
    {
        $timeline = RcspImplementationTimeline::create([
            'rcsp_barangay_id' => $this->rcspBarangay->id,
            'implementation_start_date' => '2027-02-01',
            'created_by_user_id' => $this->lgu->id,
            'updated_by_user_id' => $this->lgu->id,
        ]);
        $schedule = RcspPhaseSchedule::create([
            'rcsp_implementation_timeline_id' => $timeline->id,
            'rcsp_phase_id' => $this->phases[0]->id,
            'planned_start_date' => '2027-02-01',
            'planned_end_date' => '2027-02-07',
            'estimated_duration_weeks' => 1,
            'implementation_note' => 'Preserved schedule data.',
        ]);
        $migration = require database_path(
            'migrations/2026_11_13_000001_drop_responsible_party_from_rcsp_phase_schedules_table.php'
        );

        $migration->down();
        $this->assertTrue(Schema::hasColumn('rcsp_phase_schedules', 'responsible_party'));
        $this->assertNull(DB::table('rcsp_phase_schedules')->where('id', $schedule->id)->value('responsible_party'));
        $migration->up();

        $this->assertFalse(Schema::hasColumn('rcsp_phase_schedules', 'responsible_party'));
        $this->assertDatabaseHas('rcsp_phase_schedules', [
            'id' => $schedule->id,
            'implementation_note' => 'Preserved schedule data.',
        ]);
        $newSchedule = RcspPhaseSchedule::create([
            'rcsp_implementation_timeline_id' => $timeline->id,
            'rcsp_phase_id' => $this->phases[1]->id,
            'planned_start_date' => '2027-02-08',
            'planned_end_date' => '2027-02-14',
            'estimated_duration_weeks' => 1,
        ]);
        $this->assertNotNull($newSchedule->fresh());

        $migration->down();
        $this->assertTrue(Schema::hasColumn('rcsp_phase_schedules', 'responsible_party'));
        $restoredColumn = collect(Schema::getColumns('rcsp_phase_schedules'))
            ->firstWhere('name', 'responsible_party');
        $this->assertTrue((bool) ($restoredColumn['nullable'] ?? false));
        $migration->up();
        $this->assertFalse(Schema::hasColumn('rcsp_phase_schedules', 'responsible_party'));
    }

    public function test_objective_migration_preserves_existing_timeline_and_rcsp_records(): void
    {
        $migration = require database_path('migrations/2026_11_11_000002_add_strategic_objectives_to_rcsp_timelines.php');
        $migration->down();

        $timeline = RcspImplementationTimeline::create([
            'rcsp_barangay_id' => $this->rcspBarangay->id,
            'implementation_start_date' => '2027-02-01',
            'created_by_user_id' => $this->lgu->id,
            'updated_by_user_id' => $this->lgu->id,
        ]);
        $schedule = RcspPhaseSchedule::create([
            'rcsp_implementation_timeline_id' => $timeline->id,
            'rcsp_phase_id' => $this->phases[0]->id,
            'planned_start_date' => '2027-02-01',
            'planned_end_date' => '2027-02-07',
            'estimated_duration_weeks' => 1,
            'implementation_note' => 'Existing schedule note.',
        ]);
        $adjustment = $schedule->adjustments()->create([
            'previous_planned_start_date' => '2027-02-01',
            'previous_planned_end_date' => '2027-02-07',
            'updated_planned_start_date' => '2027-02-01',
            'updated_planned_end_date' => '2027-02-08',
            'reason' => 'Existing adjustment reason.',
            'changed_by_user_id' => $this->lgu->id,
            'adjusted_at' => now(),
        ]);
        $activity = $this->activity('Existing MOV activity');
        $form = RcspForm::create($this->formData($activity, 'submitted', 1));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('rcsp_phase_schedules', 'strategic_objective'));
        $this->assertTrue(Schema::hasColumn('rcsp_phase_schedule_adjustments', 'previous_strategic_objective'));
        $this->assertDatabaseHas('rcsp_phase_schedules', [
            'id' => $schedule->id,
            'planned_end_date' => '2027-02-07 00:00:00',
            'strategic_objective' => null,
        ]);
        $this->assertDatabaseHas('rcsp_phase_schedule_adjustments', [
            'id' => $adjustment->id,
            'reason' => 'Existing adjustment reason.',
            'previous_strategic_objective' => null,
            'updated_strategic_objective' => null,
        ]);
        $this->assertDatabaseHas('rcsp_forms', ['id' => $form->id]);
        $this->assertDatabaseHas('rcsp_activities', ['id' => $activity->id]);
    }

    /** @return array<int, array<string, mixed>> */
    private function phaseEditorData(string $content): array
    {
        $matched = preg_match(
            '/<script type="application\/json" id="timelinePhaseEditorData">(.*?)<\/script>/s',
            $content,
            $matches
        );

        $this->assertSame(1, $matched);

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<int, array<string, mixed>> */
    private function activitiesModalData(string $content): array
    {
        $matched = preg_match(
            '/<script type="application\/json" id="timelineActivitiesData">(.*?)<\/script>/s',
            $content,
            $matches
        );

        $this->assertSame(1, $matched);

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }

    private function saveTimeline(): void
    {
        foreach (range(0, 5) as $phase) {
            $this->actingAs($this->lgu)
                ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $this->payload($phase))
                ->assertSessionHasNoErrors();
        }
    }

    private function payload(int $phase = 0): array
    {
        $start = CarbonImmutable::parse('2027-02-01');
        $phaseStart = $start->addDays($phase * 7);

        return [
            'phase_number' => $phase,
            'schedule' => [
                'planned_start_date' => $phaseStart->format('Y-m-d'),
                'planned_end_date' => $phaseStart->addDays(6)->format('Y-m-d'),
                'strategic_objective' => RcspTimelineService::PHASE_DETAILS[$phase]['objective'],
                'implementation_note' => $phase === 0 ? 'Coordinate pre-planning resources.' : null,
            ],
            'adjustment_reason' => null,
        ];
    }

    private function activity(string $description, int $phase = 0, ?string $expectedResult = null): RcspActivity
    {
        return RcspActivity::create([
            'rcsp_phase_id' => $this->phases[$phase]->id,
            'rcsp_barangay_id' => $this->rcspBarangay->id,
            'created_by_user_id' => $this->lgu->id,
            'description' => $description,
            'normalized_title' => RcspActivity::normalizeTitle($description),
            'expected_result' => $expectedResult ?? $description.' result',
        ]);
    }

    private function formData(
        RcspActivity $activity,
        string $status,
        int $version,
        ?string $submittedAt = null,
        ?string $reviewedAt = null
    ): array {
        return [
            'lgu_user_id' => $this->lgu->id,
            'rcsp_barangay_id' => $this->rcspBarangay->id,
            'rcsp_phase_id' => $this->phases[0]->id,
            'rcsp_activity_id' => $activity->id,
            'submission_version' => $version,
            'conduct' => 'no',
            'status' => $status,
            'submitted_at' => $submittedAt,
            'reviewed_at' => $reviewedAt,
        ];
    }

    private function savePhaseZeroSchedule(string $start, string $end): void
    {
        $payload = $this->payload();
        $payload['schedule']['planned_start_date'] = $start;
        $payload['schedule']['planned_end_date'] = $end;

        $this->actingAs($this->lgu)
            ->put(route('lgu.rcsp.timeline.update', $this->rcspBarangay), $payload)
            ->assertSessionHasNoErrors();
    }
}
