<?php

namespace App\Services;

use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspImplementationTimeline;
use App\Models\RcspPhase;
use App\Models\RcspPhaseSchedule;
use App\Models\User;
use App\Support\RcspActivityCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RcspTimelineService
{
    private const RETURNED_STATUSES = ['disapproved', 'to be complied', 'to be conducted'];

    public const PHASE_DETAILS = [
        0 => ['name' => 'Pre-Planning', 'objective' => 'Pre-planning stage', 'suggested_timeline' => 'Week 0 to Immersion'],
        1 => ['name' => 'Shape', 'objective' => 'Organization of RCSP Team and Core Team', 'suggested_timeline' => 'Weeks 1–2'],
        2 => ['name' => 'Access', 'objective' => 'Identification of governance gaps and needed development interventions', 'suggested_timeline' => 'Weeks 3–4'],
        3 => ['name' => 'Transform', 'objective' => 'Implementation of priority programs and projects identified by the community based on the analysis of issues and gaps', 'suggested_timeline' => 'Weeks 4–5'],
        4 => ['name' => 'Sustain', 'objective' => 'Empowerment of communities, strengthening of barangay-based institutions, and capacity of the barangay in crafting the Barangay Development Plan', 'suggested_timeline' => 'Week 5 onward'],
        5 => ['name' => 'Monitor', 'objective' => 'Mobilization, handover, and sustainability', 'suggested_timeline' => 'Week 8 onward'],
    ];

    public function build(RcspBarangay $barangay, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today('Asia/Manila');
        $barangay->loadMissing([
            'barangay', 'municipality', 'phaseStatus', 'phaseTransitions',
            'implementationTimeline.schedules.phase',
            'implementationTimeline.schedules.adjustments.actor',
        ]);

        $phases = RcspPhase::query()
            ->where('catalog_key', $barangay->catalog_key)
            ->whereBetween('number', [0, 5])
            ->orderBy('number')
            ->get();
        $schedules = $barangay->implementationTimeline?->schedules->keyBy('rcsp_phase_id') ?? collect();
        $transitions = $barangay->phaseTransitions->keyBy('from_phase');
        $forms = RcspForm::query()
            ->with('reviewer')
            ->where('rcsp_barangay_id', $barangay->id)
            ->orderByDesc('submission_version')
            ->orderByDesc('id')
            ->get();
        $latestForms = $forms->unique('rcsp_activity_id');
        $firstForms = $forms
            ->sortBy(fn (RcspForm $form): int => $this->submissionDate($form)?->timestamp ?? PHP_INT_MAX)
            ->unique('rcsp_activity_id');

        $rows = $phases->map(function (RcspPhase $phase) use (
            $barangay, $schedules, $transitions, $latestForms, $firstForms, $today
        ): array {
            $number = (int) $phase->number;
            $schedule = $schedules->get($phase->id);
            $transition = $transitions->get($number);
            $verifiedCompletion = $transition?->advanced_at
                ? CarbonImmutable::instance($transition->advanced_at)->startOfDay()
                : null;
            $activities = RcspActivity::query()
                ->forBarangayPhase($barangay, $phase)
                ->orderBy('catalog_order')
                ->orderBy('id')
                ->get();
            $phaseForms = $latestForms->whereIn('rcsp_activity_id', $activities->pluck('id'));
            $phaseFirstForms = $firstForms->whereIn('rcsp_activity_id', $activities->pluck('id'));
            $submitted = $phaseForms->count();
            $approved = $phaseForms->where('status', 'approved')->count();
            $returned = $phaseForms->whereIn('status', self::RETURNED_STATUSES)->count();
            $allEvidenceSubmitted = null;
            if ($activities->isNotEmpty() && $phaseFirstForms->count() === $activities->count()) {
                $allEvidenceSubmitted = $phaseFirstForms
                    ->map(fn (RcspForm $form): ?CarbonImmutable => $this->submissionDate($form))
                    ->filter()
                    ->sortByDesc(fn (CarbonImmutable $date): int => $date->timestamp)
                    ->first()?->startOfDay();
            }
            $duration = $schedule
                ? $this->duration($schedule->planned_start_date, $schedule->planned_end_date)
                : null;

            return [
                'phase' => $phase,
                'number' => $number,
                'name' => self::PHASE_DETAILS[$number]['name'],
                'objective' => self::PHASE_DETAILS[$number]['objective'],
                'effective_objective' => $schedule?->strategic_objective ?: self::PHASE_DETAILS[$number]['objective'],
                'suggested_timeline' => self::PHASE_DETAILS[$number]['suggested_timeline'],
                'schedule' => $schedule,
                'duration_days' => $duration['days'] ?? null,
                'duration_weeks' => $duration['weeks'] ?? null,
                'all_evidence_submitted_date' => $allEvidenceSubmitted,
                'verified_completion_date' => $verifiedCompletion,
                'status' => $this->statusFor(
                    $schedule,
                    $verifiedCompletion,
                    $allEvidenceSubmitted,
                    $submitted,
                    $activities->count(),
                    $returned > 0,
                    $today
                ),
                'activity_total' => $activities->count(),
                'activity_submitted' => $submitted,
                'activity_approved' => $approved,
                'activity_remaining' => max(0, $activities->count() - $submitted),
                'activities' => $activities->map(fn (RcspActivity $activity): array => $this->activityDetails(
                    $activity,
                    $phaseForms->firstWhere('rcsp_activity_id', $activity->id),
                    $number
                ))->values(),
                'next_action' => $this->nextAction(
                    $schedule !== null,
                    $verifiedCompletion !== null,
                    $returned > 0,
                    $submitted,
                    $activities->count()
                ),
            ];
        })->values();

        $chart = $this->chartScale($rows);
        $rows = $rows->map(function (array $row) use ($chart): array {
            $row['week_start'] = null;
            $row['week_span'] = null;
            if ($row['schedule'] && $chart) {
                $startOffset = (int) $chart['start']->diffInDays(
                    CarbonImmutable::instance($row['schedule']->planned_start_date)
                );
                $endOffset = (int) $chart['start']->diffInDays(
                    CarbonImmutable::instance($row['schedule']->planned_end_date)
                );
                $row['week_start'] = intdiv($startOffset, 7) + 1;
                $row['week_span'] = intdiv($endOffset, 7) - intdiv($startOffset, 7) + 1;
            }

            return $row;
        });

        $current = $rows->firstWhere('number', (int) $barangay->current_phase) ?? $rows->last();
        $nextDeadline = $rows
            ->filter(fn (array $row) => $row['schedule'] && ! $row['verified_completion_date'])
            ->sortBy(fn (array $row) => $row['schedule']->planned_end_date->format('Y-m-d'))
            ->first();
        $completedCount = $rows->whereNotNull('verified_completion_date')->count();

        return [
            'timeline' => $barangay->implementationTimeline,
            'rows' => $rows,
            'chart' => $chart,
            'current' => $current,
            'next_deadline' => $nextDeadline,
            'completed_count' => $completedCount,
            'progress_percent' => (int) round(($completedCount / 6) * 100),
            'adjustments' => $schedules->flatMap(function (RcspPhaseSchedule $schedule): Collection {
                return $schedule->adjustments->map(fn ($adjustment): array => [
                    'phase_number' => (int) $schedule->phase->number,
                    'previous_start' => $adjustment->previous_planned_start_date,
                    'previous_end' => $adjustment->previous_planned_end_date,
                    'updated_start' => $adjustment->updated_planned_start_date,
                    'updated_end' => $adjustment->updated_planned_end_date,
                    'previous_objective' => $adjustment->previous_strategic_objective,
                    'updated_objective' => $adjustment->updated_strategic_objective,
                    'reason' => $adjustment->reason,
                    'actor_name' => $adjustment->actor?->name ?? 'Account unavailable',
                    'adjusted_at' => $adjustment->adjusted_at,
                ]);
            })->sortByDesc('adjusted_at')->values(),
        ];
    }

    public function save(RcspBarangay $barangay, User $user, array $data): RcspImplementationTimeline
    {
        return DB::transaction(function () use ($barangay, $user, $data): RcspImplementationTimeline {
            RcspBarangay::query()->whereKey($barangay->id)->lockForUpdate()->firstOrFail();
            $timeline = RcspImplementationTimeline::query()
                ->where('rcsp_barangay_id', $barangay->id)
                ->lockForUpdate()
                ->first();

            if (! $timeline) {
                $timeline = RcspImplementationTimeline::create([
                    'rcsp_barangay_id' => $barangay->id,
                    'implementation_start_date' => $data['schedule']['planned_start_date'],
                    'created_by_user_id' => $user->id,
                    'updated_by_user_id' => $user->id,
                ]);
            } else {
                $timeline->update(['updated_by_user_id' => $user->id]);
            }

            $phases = RcspPhase::query()
                ->where('catalog_key', $barangay->catalog_key)
                ->whereBetween('number', [0, 5])
                ->get()
                ->keyBy('number');
            $number = (int) $data['phase_number'];
            $values = $data['schedule'];
            $phase = $phases->get($number);
            abort_unless($phase, 422, 'The RCSP phase catalog is incomplete.');

            $schedule = RcspPhaseSchedule::query()
                ->where('rcsp_implementation_timeline_id', $timeline->id)
                ->where('rcsp_phase_id', $phase->id)
                ->lockForUpdate()
                ->first();
            $officialObjective = self::PHASE_DETAILS[$number]['objective'];
            $storedObjective = $values['strategic_objective'] === $officialObjective
                ? null
                : $values['strategic_objective'];
            $previousObjective = $schedule?->strategic_objective ?: $officialObjective;
            $updatedObjective = $storedObjective ?: $officialObjective;
            $objectiveChanged = $schedule && $previousObjective !== $updatedObjective;
            $datesChanged = $schedule && $this->datesChanged($schedule, $values);

            if ($schedule && ($datesChanged || $objectiveChanged)) {
                $schedule->adjustments()->create([
                    'previous_planned_start_date' => $schedule->planned_start_date,
                    'previous_planned_end_date' => $schedule->planned_end_date,
                    'updated_planned_start_date' => $values['planned_start_date'],
                    'updated_planned_end_date' => $values['planned_end_date'],
                    'previous_strategic_objective' => $objectiveChanged ? $previousObjective : null,
                    'updated_strategic_objective' => $objectiveChanged ? $updatedObjective : null,
                    'reason' => $data['adjustment_reason'],
                    'changed_by_user_id' => $user->id,
                    'adjusted_at' => now(),
                ]);
            }

            $duration = $this->duration($values['planned_start_date'], $values['planned_end_date']);
            RcspPhaseSchedule::updateOrCreate([
                'rcsp_implementation_timeline_id' => $timeline->id,
                'rcsp_phase_id' => $phase->id,
            ], [
                'planned_start_date' => $values['planned_start_date'],
                'planned_end_date' => $values['planned_end_date'],
                'estimated_duration_weeks' => $duration['weeks'],
                'strategic_objective' => $storedObjective,
                'implementation_note' => $values['implementation_note'] ?? null,
            ]);

            // Retained only for compatibility with the applied schema; it is not an actual-start date.
            $timeline->update([
                'implementation_start_date' => $timeline->schedules()->min('planned_start_date'),
            ]);

            return $timeline->fresh(['schedules']);
        });
    }

    private function statusFor(
        ?RcspPhaseSchedule $schedule,
        ?CarbonImmutable $verifiedCompletion,
        ?CarbonImmutable $allEvidenceSubmitted,
        int $submittedCount,
        int $activityCount,
        bool $revisionRequired,
        CarbonImmutable $today
    ): array {
        if (! $schedule) {
            return ['key' => 'not_scheduled', 'label' => 'Not scheduled'];
        }

        $plannedStart = CarbonImmutable::instance($schedule->planned_start_date)->startOfDay();
        $plannedEnd = CarbonImmutable::instance($schedule->planned_end_date)->startOfDay();
        if ($verifiedCompletion) {
            $submittedOnTime = ! $allEvidenceSubmitted || $allEvidenceSubmitted->lte($plannedEnd);

            return $submittedOnTime
                ? ['key' => 'completed_verified_on_time', 'label' => 'Completed and verified — submitted on time']
                : ['key' => 'completed_verified_late', 'label' => 'Completed and verified — submitted late'];
        }
        if ($revisionRequired) {
            return ['key' => 'revision_required', 'label' => 'Revision required'];
        }
        if ($activityCount > 0 && $submittedCount >= $activityCount && $allEvidenceSubmitted) {
            return $allEvidenceSubmitted->lte($plannedEnd)
                ? ['key' => 'submitted_on_time_pending_review', 'label' => 'Submitted on time — pending review']
                : ['key' => 'submitted_late_pending_review', 'label' => 'Submitted late — pending review'];
        }
        if ($submittedCount === 0) {
            return $today->lte($plannedStart)
                ? ['key' => 'scheduled', 'label' => 'Scheduled']
                : ['key' => 'delayed_no_evidence', 'label' => 'Delayed — no evidence submitted'];
        }

        return $today->gt($plannedEnd)
            ? ['key' => 'delayed_incomplete_evidence', 'label' => 'Delayed — incomplete evidence submission']
            : ['key' => 'in_progress', 'label' => 'In progress'];
    }

    private function chartScale(Collection $rows): ?array
    {
        $scheduled = $rows->filter(fn (array $row): bool => $row['schedule'] !== null);
        if ($scheduled->isEmpty()) {
            return null;
        }

        $start = CarbonImmutable::instance($scheduled
            ->min(fn (array $row) => $row['schedule']->planned_start_date))->startOfDay();
        $end = CarbonImmutable::instance($scheduled
            ->max(fn (array $row) => $row['schedule']->planned_end_date))->startOfDay();
        $days = max(1, (int) $start->diffInDays($end) + 1);
        $weekCount = (int) ceil($days / 7);
        $weeks = collect(range(0, $weekCount - 1))->map(function (int $offset) use ($start, $end): array {
            $weekStart = $start->addDays($offset * 7);
            $weekEnd = $weekStart->addDays(6);

            return [
                'number' => $offset + 1,
                'start' => $weekStart,
                'end' => $weekEnd->gt($end) ? $end : $weekEnd,
            ];
        });

        return compact('start', 'end', 'days', 'weekCount', 'weeks');
    }

    /** @return array{days: int, weeks: int} */
    private function duration(mixed $start, mixed $end): array
    {
        $plannedStart = $start instanceof \DateTimeInterface
            ? CarbonImmutable::instance($start)
            : CarbonImmutable::parse($start, 'Asia/Manila');
        $plannedEnd = $end instanceof \DateTimeInterface
            ? CarbonImmutable::instance($end)
            : CarbonImmutable::parse($end, 'Asia/Manila');
        $days = (int) $plannedStart->startOfDay()->diffInDays($plannedEnd->startOfDay()) + 1;

        return ['days' => $days, 'weeks' => (int) ceil($days / 7)];
    }

    private function activityDetails(RcspActivity $activity, ?RcspForm $form, int $phaseNumber): array
    {
        $status = match (true) {
            ! $form => ['key' => 'no_evidence', 'label' => 'No evidence'],
            in_array($form->status, self::RETURNED_STATUSES, true) => ['key' => 'revision_required', 'label' => 'Revision required'],
            $form->status === 'approved' => ['key' => 'approved', 'label' => 'Verified completed'],
            default => ['key' => 'pending_review', 'label' => 'Conducted · Pending verification'],
        };
        $filename = null;
        if ($form?->original_filename) {
            $filename = basename(str_replace('\\', '/', $form->original_filename));
        } elseif ($form?->file) {
            $filename = basename(str_replace('\\', '/', str_replace('private:', '', $form->file)));
        }

        return [
            'activity' => $activity,
            'name' => $activity->description,
            'expected_result' => $activity->expected_result,
            'responsible_office_person' => RcspActivityCatalog::responsibilityFor($phaseNumber, $activity->description),
            'status' => $status,
            'form' => $form,
            'filename' => $filename,
            'submitted_at' => $form ? $this->submissionDate($form) : null,
            'review_status' => $form?->status,
            'reviewed_at' => $form?->reviewed_at,
            'reviewer_remarks' => $form?->remarks,
            'reviewer_name' => $form?->reviewer?->name,
            'version' => $form?->submission_version,
            'file_available' => $form ? $this->evidenceExists($form) : false,
        ];
    }

    private function evidenceExists(RcspForm $form): bool
    {
        if (! $form->file || str_contains($form->file, '..') || str_contains($form->file, '\\')
            || str_starts_with($form->file, '/') || preg_match('/^[A-Za-z]:/', $form->file)) {
            return false;
        }
        $private = str_starts_with($form->file, 'private:');
        $path = $private ? substr($form->file, 8) : $form->file;
        if (! str_starts_with($path, "rcsp/{$form->rcsp_barangay_id}/")) {
            return false;
        }

        return Storage::disk($private ? 'local' : 'public')->exists($path);
    }

    private function submissionDate(RcspForm $form): ?CarbonImmutable
    {
        $date = $form->submitted_at ?? $form->created_at;

        return $date ? CarbonImmutable::instance($date) : null;
    }

    private function nextAction(
        bool $scheduled,
        bool $verified,
        bool $revisionRequired,
        int $submittedCount,
        int $activityCount
    ): string {
        if (! $scheduled) {
            return 'Set planned dates';
        }
        if ($verified) {
            return 'Phase completed';
        }
        if ($revisionRequired) {
            return 'Revise returned evidence';
        }
        if ($activityCount > 0 && $submittedCount >= $activityCount) {
            return 'Await reviewer action';
        }
        if ($submittedCount > 0) {
            return 'Submit remaining evidence';
        }

        return 'Upload activity evidence';
    }

    private function datesChanged(RcspPhaseSchedule $schedule, array $values): bool
    {
        return $schedule->planned_start_date->format('Y-m-d') !== $values['planned_start_date']
            || $schedule->planned_end_date->format('Y-m-d') !== $values['planned_end_date'];
    }
}
