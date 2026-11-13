@extends($layout)
@section('title', 'Implementation Timeline')

@php
    $rows = $timelineData['rows'];
    $current = $timelineData['current'];
    $statusClasses = [
        'not_scheduled' => 'timeline-status-muted',
        'scheduled' => 'timeline-status-scheduled',
        'delayed_no_evidence' => 'timeline-status-red',
        'in_progress' => 'timeline-status-amber',
        'delayed_incomplete_evidence' => 'timeline-status-red',
        'submitted_on_time_pending_review' => 'timeline-status-blue',
        'submitted_late_pending_review' => 'timeline-status-red',
        'revision_required' => 'timeline-status-orange',
        'completed_verified_on_time' => 'timeline-status-green',
        'completed_verified_late' => 'timeline-status-green',
    ];
@endphp

@push('styles')
<style>
    .rcsp-timeline .timeline-status-muted { --timeline-color: #858796; --timeline-soft: #f2f3f5; }
    .rcsp-timeline .timeline-status-scheduled { --timeline-color: #6985a7; --timeline-soft: #edf3f8; }
    .rcsp-timeline .timeline-status-blue { --timeline-color: #3569c8; --timeline-soft: #edf3ff; }
    .rcsp-timeline .timeline-status-amber { --timeline-color: #a86f00; --timeline-soft: #fff5dd; }
    .rcsp-timeline .timeline-status-green { --timeline-color: #18845d; --timeline-soft: #e9f8f1; }
    .rcsp-timeline .timeline-status-orange { --timeline-color: #b85516; --timeline-soft: #fff1e7; }
    .rcsp-timeline .timeline-status-red { --timeline-color: #c53c30; --timeline-soft: #fff0ee; }
    .rcsp-timeline .status-pill { display: inline-block; padding: .25rem .55rem; border: 1px solid var(--timeline-color); border-radius: 999px; color: var(--timeline-color); font-size: .75rem; font-weight: 700; line-height: 1.35; }
    .rcsp-timeline .timeline-legend { display: flex; flex-wrap: wrap; gap: .55rem 1rem; }
    .rcsp-timeline .legend-item { display: inline-flex; align-items: center; gap: .35rem; color: #55576b; font-size: .78rem; }
    .rcsp-timeline .legend-swatch { width: .75rem; height: .75rem; border-radius: .2rem; background: var(--timeline-color); }
    .rcsp-timeline .gantt-scroll { overflow-x: auto; overscroll-behavior-inline: contain; }
    .rcsp-timeline .gantt-board { min-width: max(58rem, calc(22rem + (var(--week-count) * 7rem))); border: 1px solid #e4e4ee; border-radius: .7rem; background: #fff; }
    .rcsp-timeline .gantt-axis,
    .rcsp-timeline .gantt-phase-main { display: grid; grid-template-columns: 22rem minmax(calc(var(--week-count) * 7rem), 1fr); }
    .rcsp-timeline .phase-axis-heading,
    .rcsp-timeline .gantt-phase-summary { position: sticky; left: 0; z-index: 3; border-right: 1px solid #dedee8; background: #fff; }
    .rcsp-timeline .phase-axis-heading { display: flex; align-items: center; padding: .75rem 1rem; color: #56586b; font-size: .76rem; font-weight: 700; text-transform: uppercase; }
    .rcsp-timeline .week-grid { display: grid; grid-template-columns: repeat(var(--week-count), minmax(7rem, 1fr)); }
    .rcsp-timeline .week-heading { border-left: 1px solid #e5e5ee; padding: .55rem .35rem; color: #34345e; text-align: center; }
    .rcsp-timeline .week-heading strong { display: block; font-size: .76rem; }
    .rcsp-timeline .week-heading small { display: block; color: #77798d; font-size: .68rem; white-space: nowrap; }
    .rcsp-timeline .gantt-phase { border-top: 1px solid #e7e7ef; }
    .rcsp-timeline .gantt-phase-summary { padding: .7rem .8rem; }
    .rcsp-timeline .phase-heading { display: flex; align-items: flex-start; gap: .55rem; }
    .rcsp-timeline .phase-heading-text { min-width: 0; flex: 1; }
    .rcsp-timeline .phase-name { color: #29275f; font-size: .9rem; font-weight: 700; line-height: 1.3; }
    .rcsp-timeline .phase-objective-brief { display: -webkit-box; margin-top: .18rem; overflow: hidden; color: #66687a; font-size: .73rem; line-height: 1.35; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
    .rcsp-timeline .phase-plan { margin-top: .35rem; color: #56586b; font-size: .73rem; line-height: 1.4; }
    .rcsp-timeline .phase-status-line { display: flex; align-items: center; gap: .45rem; margin-top: .42rem; }
    .rcsp-timeline .view-activities-btn { flex: 0 0 auto; width: 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; padding: 0; }
    .rcsp-timeline .view-activities-btn i { font-size: 1rem; line-height: 1; }
    .rcsp-timeline .week-lane { display: grid; grid-template-columns: repeat(var(--week-count), minmax(7rem, 1fr)); align-items: stretch; background: #fbfbfd; }
    .rcsp-timeline .week-cell { grid-row: 1; min-height: 5.25rem; border-left: 1px solid #ebebf2; }
    .rcsp-timeline .planned-bar { grid-row: 1; z-index: 2; align-self: center; min-height: 2.25rem; margin: .55rem .2rem; border-radius: .45rem; background: var(--timeline-color); color: #fff; display: flex; align-items: center; justify-content: center; padding: .3rem .5rem; font-size: .73rem; font-weight: 700; text-align: center; overflow-wrap: anywhere; }
    .rcsp-timeline .unscheduled-lane { grid-column: 1 / -1; grid-row: 1; z-index: 2; align-self: center; justify-self: start; margin-left: 1rem; color: #77798d; font-size: .8rem; }
    .rcsp-timeline .activities-modal-dialog { width: 96vw; max-width: 1600px; }
    .rcsp-timeline .activities-modal-dialog .modal-content { max-height: 88vh; }
    .rcsp-timeline .activities-modal-header { display: grid; grid-template-columns: 2.25rem minmax(0, 1fr) 2.25rem; align-items: center; min-height: 4.25rem; padding: .7rem 1rem; background: #4e73df; color: #fff; }
    .rcsp-timeline .activities-modal-heading { grid-column: 2; min-width: 0; text-align: center; }
    .rcsp-timeline .activities-modal-heading .modal-title { color: #fff !important; line-height: 1.3; overflow-wrap: anywhere; }
    .rcsp-timeline .activities-modal-heading small { color: rgba(255, 255, 255, .82) !important; }
    .rcsp-timeline .activities-modal-header .btn-close { grid-column: 3; justify-self: end; margin: 0; }
    .rcsp-timeline .activities-table-wrap { width: 100%; overflow-x: auto; overscroll-behavior-inline: contain; }
    .rcsp-timeline .activities-modal-table { width: 100%; min-width: 1350px; table-layout: fixed; }
    .rcsp-timeline .activities-modal-table th,
    .rcsp-timeline .activities-modal-table td { box-sizing: border-box; padding: .7rem .65rem; line-height: 1.45; overflow-wrap: anywhere; white-space: normal !important; word-break: normal; vertical-align: top; }
    .rcsp-timeline .activities-modal-table td > * { max-width: 100%; min-width: 0; }
    .rcsp-timeline .activities-modal-table th { position: sticky; top: 0; z-index: 2; border-top: 0; border-bottom: 2px solid #dfe1eb; background: #f8f9fc; color: #45475b; font-size: .7rem; letter-spacing: .02em; text-transform: uppercase; }
    .rcsp-timeline .activities-modal-table td { border-bottom: 1px solid #e7e8ef; color: #4c4e61; font-size: .78rem; }
    .rcsp-timeline .activities-modal-table tbody tr:nth-child(even) td { background: #fbfbfd; }
    .rcsp-timeline .activities-modal-table tbody tr:last-child td { border-bottom: 0; }
    .rcsp-timeline .activity-text,
    .rcsp-timeline .activity-result,
    .rcsp-timeline .activity-responsibility,
    .rcsp-timeline .activity-remarks { display: block; min-width: 0; overflow-wrap: anywhere; white-space: normal !important; }
    .rcsp-timeline .activity-evidence-filename { display: block; color: #29275f; font-weight: 600; overflow-wrap: anywhere; }
    .rcsp-timeline .activity-state,
    .rcsp-timeline .activity-version { display: block; margin-top: .2rem; color: #6c6e80; font-size: .72rem; line-height: 1.35; }
    .rcsp-timeline .activity-date { display: block; white-space: normal !important; }
    .rcsp-timeline .activity-time { display: block; margin-top: .15rem; color: #6c6e80; font-size: .72rem; white-space: normal !important; }
    .rcsp-timeline .activity-review-badge { display: inline-block; max-width: 100%; padding: .3rem .5rem; border: 1px solid #d5d7e2; border-radius: 999px; background: #f4f5f8; color: #45475b; font-size: .72rem; font-weight: 700; line-height: 1.25; white-space: normal !important; }
    .rcsp-timeline .activity-review-approved { border-color: #b7dfcf; background: #e9f8f1; color: #14704f; }
    .rcsp-timeline .activity-review-pending_review { border-color: #bfd0f2; background: #edf3ff; color: #2e5cae; }
    .rcsp-timeline .activity-review-revision_required { border-color: #efc5a9; background: #fff1e7; color: #9c4813; }
    .rcsp-timeline .activity-review-no_evidence { border-color: #d5d7e2; background: #f4f5f8; color: #66687a; }
    .rcsp-timeline .activity-actions { display: flex; min-width: 0; flex-direction: column; align-items: stretch; gap: .35rem; }
    .rcsp-timeline .activity-actions .btn { width: 100%; margin: 0; padding-right: .35rem; padding-left: .35rem; white-space: normal !important; }
    .rcsp-timeline .file-unavailable { display: block; color: #77798d; font-size: .76rem; line-height: 1.35; }
    .rcsp-timeline .history-toggle { cursor: pointer; color: #29275f; font-size: 1.05rem; font-weight: 700; }
    .rcsp-timeline .form-label { color: #34345e; font-size: .82rem; font-weight: 600; }
    .rcsp-timeline .timeline-phase-modal-header { display: grid; grid-template-columns: 2.25rem minmax(0, 1fr) 2.25rem; align-items: center; min-height: 4.25rem; padding: .75rem 1rem; background: #4e73df; color: #fff; }
    .rcsp-timeline .timeline-phase-modal-title { grid-column: 2; min-width: 0; text-align: center; }
    .rcsp-timeline .timeline-phase-modal-title .modal-title { line-height: 1.3; overflow-wrap: anywhere; }
    .rcsp-timeline .timeline-phase-modal-header .btn-close { grid-column: 3; justify-self: end; margin: 0; }
    .rcsp-timeline .timeline-phase-modal-dialog { width: calc(100% - 2rem); max-width: 900px; }
    .rcsp-timeline .timeline-phase-modal-dialog .modal-content { max-height: 88vh; }
    .rcsp-timeline .timeline-phase-modal-body { padding: 1rem 1.25rem; }
    .rcsp-timeline .timeline-phase-modal-body textarea { resize: vertical; }
    .rcsp-timeline .timeline-phase-modal-footer { padding: .75rem 1.25rem; }
    .rcsp-timeline .calculated-duration { min-height: 2.4rem; border: 1px solid #e0e0e8; border-radius: .25rem; background: #f7f7fa; padding: .55rem .75rem; color: #34345e; }
    @media (max-width: 767.98px) {
        .rcsp-timeline .gantt-scroll { overflow: visible; }
        .rcsp-timeline .gantt-board { min-width: 0; border: 0; background: transparent; }
        .rcsp-timeline .gantt-axis { display: none; }
        .rcsp-timeline .gantt-phase { margin-bottom: .75rem; border: 1px solid #e4e4ee; border-radius: .65rem; overflow: hidden; background: #fff; }
        .rcsp-timeline .gantt-phase-main { display: block; }
        .rcsp-timeline .gantt-phase-summary { position: static; border-right: 0; }
        .rcsp-timeline .week-lane { display: none; }
        .rcsp-timeline .phase-objective-brief { -webkit-line-clamp: 3; }
    }
    @media (max-width: 575.98px) {
        .rcsp-timeline .activities-modal-dialog,
        .rcsp-timeline .timeline-phase-modal-dialog { width: calc(100% - 1rem); margin: .5rem auto; }
        .rcsp-timeline .activities-modal-header,
        .rcsp-timeline .timeline-phase-modal-header { grid-template-columns: 2rem minmax(0, 1fr) 2rem; padding: .65rem .75rem; }
        .rcsp-timeline .activities-modal-heading .modal-title,
        .rcsp-timeline .timeline-phase-modal-title .modal-title { font-size: 1.05rem; }
        .rcsp-timeline .timeline-phase-modal-body { padding: .9rem; }
        .rcsp-timeline .timeline-phase-modal-footer { padding: .65rem .9rem; }
    }
</style>
@endpush

@section('content')
<div class="rcsp-timeline">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="font-weight-bold mb-1">Implementation Timeline</h3>
            <p class="text-muted mb-0">
                {{ $rcspBarangay->barangay?->name }} · {{ $rcspBarangay->municipality?->name }}
            </p>
            @if ($timelineData['chart'])
                <p class="text-muted small mb-0 mt-1" data-timeline-range>
                    {{ $timelineData['chart']['start']->format('M j, Y') }} – {{ $timelineData['chart']['end']->format('M j, Y') }} · {{ $timelineData['chart']['weekCount'] }} {{ Str::plural('week', $timelineData['chart']['weekCount']) }}
                </p>
            @endif
        </div>
        <a href="{{ $backRoute }}" class="btn btn-sm btn-light bg-white"><i class="mdi mdi-arrow-left"></i> Back to RCSP Activities</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card mb-4"><div class="card-body">
        <div class="timeline-legend mb-3" aria-label="Timeline status legend">
            @foreach ([
                ['timeline-status-muted', 'Not scheduled'], ['timeline-status-scheduled', 'Scheduled'],
                ['timeline-status-amber', 'In progress'], ['timeline-status-blue', 'Pending review'],
                ['timeline-status-red', 'Delayed'], ['timeline-status-orange', 'Revision required'],
                ['timeline-status-green', 'Completed and verified'],
            ] as [$class, $label])
                <span class="legend-item {{ $class }}"><span class="legend-swatch"></span>{{ $label }}</span>
            @endforeach
        </div>
        <div class="gantt-scroll" data-week-gantt-scroll>
            <div class="gantt-board" style="--week-count:{{ max(1, $timelineData['chart']['weekCount'] ?? 1) }}" data-week-gantt>
                @if ($timelineData['chart'])
                    <div class="gantt-axis">
                        <div class="phase-axis-heading">RCSP phase</div>
                        <div class="week-grid" aria-label="Timeline weeks">
                            @foreach ($timelineData['chart']['weeks'] as $week)
                                <div class="week-heading" data-week-number="{{ $week['number'] }}">
                                    <strong>Week {{ $week['number'] }}</strong>
                                    <small>{{ $week['start']->format('M j') }}–{{ $week['end']->format('M j') }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @foreach ($rows as $row)
                    <section class="gantt-phase {{ $statusClasses[$row['status']['key']] ?? 'timeline-status-muted' }}" data-phase-row="{{ $row['number'] }}">
                        <div class="gantt-phase-main">
                            <div class="gantt-phase-summary">
                                <div class="phase-heading">
                                    <div class="phase-heading-text">
                                        <div class="phase-name">Phase {{ $row['number'] }} – {{ $row['name'] }}</div>
                                        <div class="phase-objective-brief" title="{{ $row['effective_objective'] }}">{{ $row['effective_objective'] }}</div>
                                    </div>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary view-activities-btn"
                                            data-timeline-activities-view
                                            data-phase-number="{{ $row['number'] }}"
                                            title="View Phase {{ $row['number'] }} activities"
                                            aria-label="View Phase {{ $row['number'] }} activities">
                                        <i class="mdi mdi-eye-outline" aria-hidden="true"></i>
                                    </button>
                                    @if ($canEdit)
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary view-activities-btn"
                                                data-timeline-phase-edit
                                                data-phase-number="{{ $row['number'] }}"
                                                title="Edit Phase {{ $row['number'] }} schedule"
                                                aria-label="Edit Phase {{ $row['number'] }} – {{ $row['name'] }} schedule">
                                            <i class="mdi mdi-pencil-outline" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </div>
                                <div class="phase-plan">
                                    @if ($row['schedule'])
                                        {{ $row['schedule']->planned_start_date->format('M j, Y') }}–{{ $row['schedule']->planned_end_date->format('M j, Y') }}<br>
                                        {{ $row['duration_days'] }} {{ Str::plural('day', $row['duration_days']) }} · approximately {{ $row['duration_weeks'] }} {{ Str::plural('week', $row['duration_weeks']) }}
                                    @else
                                        Not scheduled
                                    @endif
                                </div>
                                <div class="phase-status-line">
                                    <span class="status-pill">{{ $row['status']['label'] }}</span>
                                </div>
                            </div>
                            @if ($timelineData['chart'])
                                <div class="week-lane" aria-label="Phase {{ $row['number'] }} planned schedule">
                                    @foreach ($timelineData['chart']['weeks'] as $week)
                                        <span class="week-cell" style="grid-column:{{ $week['number'] }}" aria-hidden="true"></span>
                                    @endforeach
                                    @if ($row['week_start'] !== null)
                                        <span class="planned-bar" style="grid-column:{{ $row['week_start'] }} / span {{ $row['week_span'] }}" title="{{ $row['status']['label'] }}" aria-label="{{ $row['status']['label'] }}"></span>
                                    @else
                                        <span class="unscheduled-lane">Not scheduled</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div></div>

    @php
        $activitiesModalData = $rows->mapWithKeys(fn (array $row): array => [
            (string) $row['number'] => [
                'number' => $row['number'],
                'name' => $row['name'],
                'status' => $row['status']['label'],
                'manageUrl' => $canEdit && (int) $row['number'] === (int) $rcspBarangay->current_phase
                    ? route('lgu.monitoring.show', ['rcspBarangay' => $rcspBarangay, 'phase' => $row['number']])
                    : null,
                'activities' => $row['activities']->map(fn (array $activity): array => [
                    'name' => $activity['name'],
                    'expectedResult' => $activity['expected_result'],
                    'responsibleOfficePerson' => $activity['responsible_office_person'],
                    'evidence' => $activity['filename'] ?: 'No evidence submitted',
                    'evidenceState' => $activity['status']['label'],
                    'evidenceStatusKey' => $activity['status']['key'],
                    'submittedDate' => $activity['submitted_at']?->format('M j, Y') ?? 'Not submitted',
                    'submittedTime' => $activity['submitted_at']?->format('g:i A'),
                    'reviewStatus' => $activity['review_status'] ? Str::headline($activity['review_status']) : 'Not reviewed',
                    'reviewedDate' => $activity['reviewed_at']?->format('M j, Y') ?? 'Not yet reviewed',
                    'reviewedTime' => $activity['reviewed_at']?->format('g:i A'),
                    'remarks' => $activity['reviewer_remarks'] ?: 'No reviewer remarks',
                    'version' => $activity['version'],
                    'fileAvailable' => $activity['file_available'],
                    'previewUrl' => $activity['file_available'] ? route('rcsp.evidence', $activity['form']) : null,
                    'downloadUrl' => $activity['file_available'] ? route('rcsp.evidence', $activity['form']) : null,
                ])->values(),
            ],
        ]);
    @endphp
    <script type="application/json" id="timelineActivitiesData">{!! json_encode($activitiesModalData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <div class="modal fade" id="timelineActivitiesModal" tabindex="-1" aria-labelledby="timelineActivitiesTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable activities-modal-dialog">
            <div class="modal-content">
                <div class="modal-header activities-modal-header">
                    <div class="activities-modal-heading">
                        <h4 class="modal-title mb-0" id="timelineActivitiesTitle">Phase Activities</h4>
                        <small class="text-white-50" id="timelineActivitiesStatus"></small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close phase activities"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="activities-table-wrap" tabindex="0" role="region" aria-label="Phase activities table; scroll horizontally on smaller screens">
                        <table class="table table-hover mb-0 activities-modal-table">
                            <colgroup>
                                <col style="width: 16%">
                                <col style="width: 15%">
                                <col style="width: 12%">
                                <col style="width: 13%">
                                <col style="width: 9%">
                                <col style="width: 9%">
                                <col style="width: 9%">
                                <col style="width: 11%">
                                <col style="width: 6%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">Activity</th>
                                    <th scope="col">Expected Result/Output</th>
                                    <th scope="col">Office/Person Responsible</th>
                                    <th scope="col">Evidence</th>
                                    <th scope="col">Submitted</th>
                                    <th scope="col">Review Status</th>
                                    <th scope="col">Reviewed</th>
                                    <th scope="col">Reviewer Remarks</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody id="timelineActivitiesBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer justify-content-end">
                    <a class="btn btn-primary d-none" id="timelineManageEvidence" href="#">Manage activity evidence</a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @if ($canEdit)
        @php
            $editorSchedule = $editPhase['schedule'] ?? null;
            $editorPhaseNumber = old('phase_number', $editPhase['number'] ?? '');
            $phaseEditorData = $rows->mapWithKeys(fn (array $row): array => [
                (string) $row['number'] => [
                    'number' => $row['number'], 'name' => $row['name'],
                    'suggestedTimeline' => $row['suggested_timeline'],
                    'officialObjective' => $row['objective'], 'objective' => $row['effective_objective'],
                    'plannedStart' => $row['schedule']?->planned_start_date?->format('Y-m-d'),
                    'plannedEnd' => $row['schedule']?->planned_end_date?->format('Y-m-d'),
                    'implementationNote' => $row['schedule']?->implementation_note,
                    'hasSchedule' => $row['schedule'] !== null,
                ],
            ]);
        @endphp
        <script type="application/json" id="timelinePhaseEditorData">{!! json_encode($phaseEditorData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        <div class="modal fade" id="timelinePhaseEditorModal" tabindex="-1" aria-labelledby="timelinePhaseEditorTitle" aria-hidden="true" data-validation-reopen="{{ $errors->any() && $editPhase ? 'true' : 'false' }}">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable timeline-phase-modal-dialog">
                <div class="modal-content">
                    <div class="modal-header timeline-phase-modal-header">
                        <div class="timeline-phase-modal-title">
                            <h4 class="modal-title mb-0" id="timelinePhaseEditorTitle">{{ $editPhase ? 'Edit Phase '.$editPhase['number'].' – '.$editPhase['name'] : 'Edit RCSP Phase' }}</h4>
                            <small class="text-white-50" id="timelinePhaseEditorGuidance">@if ($editPhase) Official suggested timeline: {{ $editPhase['suggested_timeline'] }} @endif</small>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close timeline phase editor"></button>
                    </div>
                    <form method="POST" action="{{ $updateRoute }}" data-timeline-phase-editor>
                        @csrf
                        @method('PUT')
                        <input id="timeline_phase_number" type="hidden" name="phase_number" value="{{ $editorPhaseNumber }}">
                        <div class="modal-body timeline-phase-modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="strategic_objective">Strategic objective</label>
                                    <textarea id="strategic_objective" name="schedule[strategic_objective]" maxlength="1000" rows="3" required class="form-control @error('schedule.strategic_objective') is-invalid @enderror" @error('schedule.strategic_objective') aria-describedby="strategic_objective_error" @enderror>{{ old('schedule.strategic_objective', $editPhase['effective_objective'] ?? '') }}</textarea>
                                    <small class="text-muted" id="timelinePhaseOfficialObjective">@if ($editPhase) Official default: {{ $editPhase['objective'] }} @endif</small>
                                    @error('schedule.strategic_objective')<div class="invalid-feedback" id="strategic_objective_error">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="planned_start_date">Planned start date</label>
                                    <input id="planned_start_date" type="date" required class="form-control @error('schedule.planned_start_date') is-invalid @enderror" name="schedule[planned_start_date]" value="{{ old('schedule.planned_start_date', $editorSchedule?->planned_start_date?->format('Y-m-d')) }}" @error('schedule.planned_start_date') aria-describedby="planned_start_date_error" @enderror>
                                    @error('schedule.planned_start_date')<div class="invalid-feedback" id="planned_start_date_error">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="planned_end_date">Planned end date</label>
                                    <input id="planned_end_date" type="date" required class="form-control @error('schedule.planned_end_date') is-invalid @enderror" name="schedule[planned_end_date]" value="{{ old('schedule.planned_end_date', $editorSchedule?->planned_end_date?->format('Y-m-d')) }}" @error('schedule.planned_end_date') aria-describedby="planned_end_date_error" @enderror>
                                    @error('schedule.planned_end_date')<div class="invalid-feedback" id="planned_end_date_error">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <span class="form-label d-block">Calculated duration</span>
                                    <div class="calculated-duration" id="timelineCalculatedDuration" aria-live="polite">Select planned dates.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="implementation_note">Optional implementation note</label>
                                    <textarea id="implementation_note" maxlength="2000" rows="2" class="form-control @error('schedule.implementation_note') is-invalid @enderror" name="schedule[implementation_note]" @error('schedule.implementation_note') aria-describedby="implementation_note_error" @enderror>{{ old('schedule.implementation_note', $editorSchedule?->implementation_note) }}</textarea>
                                    @error('schedule.implementation_note')<div class="invalid-feedback" id="implementation_note_error">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 {{ $editorSchedule ? '' : 'd-none' }}" id="timelineAdjustmentReasonGroup">
                                    <label class="form-label" for="adjustment_reason">Adjustment reason</label>
                                    <textarea id="adjustment_reason" name="adjustment_reason" maxlength="2000" rows="2" class="form-control @error('adjustment_reason') is-invalid @enderror" aria-describedby="adjustment-help @error('adjustment_reason') adjustment_reason_error @enderror">{{ old('adjustment_reason') }}</textarea>
                                    <small id="adjustment-help" class="text-muted">Required when changing a saved planned date or strategic objective.</small>
                                    @error('adjustment_reason')<div class="invalid-feedback" id="adjustment_reason_error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer timeline-phase-modal-footer justify-content-end">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="card"><div class="card-body">
        <details>
            <summary class="history-toggle">Timeline adjustment history ({{ $timelineData['adjustments']->count() }})</summary>
            <div class="table-responsive mt-3"><table class="table">
                <thead><tr><th>Phase</th><th>Date change</th><th>Strategic objective change</th><th>Reason</th><th>Changed by</th><th>Adjusted</th></tr></thead>
                <tbody>
                @forelse ($timelineData['adjustments'] as $adjustment)
                    <tr>
                        <td>Phase {{ $adjustment['phase_number'] }}</td>
                        <td>{{ $adjustment['previous_start']->format('M j, Y') }} – {{ $adjustment['previous_end']->format('M j, Y') }}<br><span class="text-muted">to</span><br>{{ $adjustment['updated_start']->format('M j, Y') }} – {{ $adjustment['updated_end']->format('M j, Y') }}</td>
                        <td>@if ($adjustment['previous_objective'] !== null)<span class="text-muted">Previous:</span> {{ $adjustment['previous_objective'] }}<br><span class="text-muted">Updated:</span> {{ $adjustment['updated_objective'] }}@else<span class="text-muted">No objective change</span>@endif</td>
                        <td>{{ $adjustment['reason'] }}</td><td>{{ $adjustment['actor_name'] }}</td><td>{{ $adjustment['adjusted_at']->format('M j, Y g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No schedule adjustments recorded.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </details>
    </div></div>
</div>
@endsection

@push('scripts')
    <script>
        (() => {
            const initializeTimelineModals = () => {
                const root = document.querySelector('.rcsp-timeline');
                const activitiesModal = document.getElementById('timelineActivitiesModal');
                const activitiesDataElement = document.getElementById('timelineActivitiesData');
                if (!root || !activitiesModal || !activitiesDataElement || root.dataset.timelineModalsInitialized === 'true') return;

                let activitiesData;
                try { activitiesData = JSON.parse(activitiesDataElement.textContent); }
                catch (error) { console.error('[RCSP timeline] Activity data could not be parsed.'); return; }

                const editorModal = document.getElementById('timelinePhaseEditorModal');
                const editorDataElement = document.getElementById('timelinePhaseEditorData');
                let editorData = null;
                if (editorModal && editorDataElement) {
                    try { editorData = JSON.parse(editorDataElement.textContent); }
                    catch (error) { console.error('[RCSP timeline] Phase editor data could not be parsed.'); return; }
                }

                root.dataset.timelineModalsInitialized = 'true';
                const activitiesTitle = document.getElementById('timelineActivitiesTitle');
                const activitiesStatus = document.getElementById('timelineActivitiesStatus');
                const activitiesBody = document.getElementById('timelineActivitiesBody');
                const manageEvidence = document.getElementById('timelineManageEvidence');
                let activitiesOpeningButton = null;
                let editorOpeningButton = null;

                const showModal = (modal, button = null, reportUnavailable = true) => {
                    if (window.bootstrap?.Modal) {
                        const instance = window.bootstrap.Modal.getInstance(modal) ?? new window.bootstrap.Modal(modal);
                        instance.show(button);
                        return true;
                    }
                    if (window.jQuery?.fn?.modal) { window.jQuery(modal).modal('show'); return true; }
                    if (reportUnavailable) console.error('[RCSP timeline] The Bootstrap modal component is unavailable.');
                    return false;
                };
                const appendTextCell = (row, value, className = '') => {
                    const cell = document.createElement('td');
                    const text = document.createElement('span');
                    text.className = className;
                    text.textContent = value;
                    cell.appendChild(text);
                    row.appendChild(cell);
                    return cell;
                };
                const appendDateCell = (row, date, time) => {
                    const cell = document.createElement('td');
                    const dateText = document.createElement('span');
                    dateText.className = 'activity-date';
                    dateText.textContent = date;
                    cell.appendChild(dateText);
                    if (time) {
                        const timeText = document.createElement('span');
                        timeText.className = 'activity-time';
                        timeText.textContent = time;
                        cell.appendChild(timeText);
                    }
                    row.appendChild(cell);
                    return cell;
                };
                const populateActivities = (phase) => {
                    activitiesTitle.textContent = `Phase ${phase.number} – ${phase.name} Activities`;
                    activitiesStatus.textContent = `Current status: ${phase.status}`;
                    activitiesBody.replaceChildren();

                    if (!phase.activities.length) {
                        const emptyRow = document.createElement('tr');
                        const emptyCell = document.createElement('td');
                        emptyCell.colSpan = 9;
                        emptyCell.className = 'text-center text-muted py-4';
                        emptyCell.textContent = 'No activities are configured for this phase.';
                        emptyRow.appendChild(emptyCell);
                        activitiesBody.appendChild(emptyRow);
                    }

                    phase.activities.forEach((activity) => {
                        const row = document.createElement('tr');
                        appendTextCell(row, activity.name, 'activity-text');
                        appendTextCell(row, activity.expectedResult, 'activity-result');
                        appendTextCell(row, activity.responsibleOfficePerson, 'activity-responsibility');

                        const evidenceCell = document.createElement('td');
                        const evidenceName = document.createElement('span');
                        evidenceName.className = 'activity-evidence-filename';
                        evidenceName.textContent = activity.evidence;
                        evidenceName.title = activity.evidence;
                        evidenceCell.appendChild(evidenceName);
                        const state = document.createElement('span');
                        state.className = 'activity-state';
                        state.textContent = activity.evidenceState;
                        evidenceCell.appendChild(state);
                        if (activity.version) {
                            const version = document.createElement('span');
                            version.className = 'activity-version';
                            version.textContent = `Version ${activity.version}`;
                            evidenceCell.appendChild(version);
                        }
                        row.appendChild(evidenceCell);

                        appendDateCell(row, activity.submittedDate, activity.submittedTime);
                        const reviewCell = document.createElement('td');
                        const reviewBadge = document.createElement('span');
                        reviewBadge.className = `activity-review-badge activity-review-${activity.evidenceStatusKey}`;
                        reviewBadge.textContent = activity.evidenceState;
                        reviewBadge.setAttribute('aria-label', `Review status: ${activity.evidenceState}`);
                        reviewCell.appendChild(reviewBadge);
                        row.appendChild(reviewCell);
                        appendDateCell(row, activity.reviewedDate, activity.reviewedTime);
                        appendTextCell(row, activity.remarks, 'activity-remarks');

                        const actionCell = document.createElement('td');
                        const actionControls = document.createElement('div');
                        actionControls.className = 'activity-actions';
                        if (activity.fileAvailable) {
                            const preview = document.createElement('a');
                            preview.className = 'btn btn-sm btn-outline-primary';
                            preview.href = activity.previewUrl;
                            preview.target = '_blank';
                            preview.rel = 'noopener';
                            preview.textContent = 'Preview';
                            preview.title = `Preview ${activity.evidence}`;
                            preview.setAttribute('aria-label', `Preview evidence: ${activity.evidence}`);
                            const download = document.createElement('a');
                            download.className = 'btn btn-sm btn-outline-secondary';
                            download.href = activity.downloadUrl;
                            download.download = '';
                            download.textContent = 'Download';
                            download.title = `Download ${activity.evidence}`;
                            download.setAttribute('aria-label', `Download evidence: ${activity.evidence}`);
                            actionControls.append(preview, download);
                        } else {
                            const unavailable = document.createElement('span');
                            unavailable.className = 'file-unavailable';
                            unavailable.textContent = activity.evidence === 'No evidence submitted' ? 'No evidence submitted' : 'File unavailable';
                            actionControls.appendChild(unavailable);
                        }
                        actionCell.appendChild(actionControls);
                        row.appendChild(actionCell);
                        activitiesBody.appendChild(row);
                    });

                    if (phase.manageUrl) {
                        manageEvidence.href = phase.manageUrl;
                        manageEvidence.classList.remove('d-none');
                    } else {
                        manageEvidence.removeAttribute('href');
                        manageEvidence.classList.add('d-none');
                    }
                };
                const openActivities = (button) => {
                    const phaseNumber = String(button.dataset.phaseNumber ?? '');
                    const phase = activitiesData[phaseNumber];
                    if (!phase) { console.error(`RCSP timeline activity data was not found for phase ${phaseNumber}.`); return; }
                    activitiesOpeningButton = button;
                    populateActivities(phase);
                    showModal(activitiesModal, button);
                };

                const editorTitle = document.getElementById('timelinePhaseEditorTitle');
                const editorGuidance = document.getElementById('timelinePhaseEditorGuidance');
                const officialObjective = document.getElementById('timelinePhaseOfficialObjective');
                const adjustmentGroup = document.getElementById('timelineAdjustmentReasonGroup');
                const durationOutput = document.getElementById('timelineCalculatedDuration');
                const plannedStart = document.getElementById('planned_start_date');
                const plannedEnd = document.getElementById('planned_end_date');
                const setValue = (id, value = '') => { const field = document.getElementById(id); if (field) field.value = value ?? ''; };
                const updateCalculatedDuration = () => {
                    if (!durationOutput) return;
                    if (!plannedStart?.value || !plannedEnd?.value) { durationOutput.textContent = 'Select planned dates.'; return; }
                    const start = Date.parse(`${plannedStart.value}T00:00:00Z`);
                    const end = Date.parse(`${plannedEnd.value}T00:00:00Z`);
                    if (!Number.isFinite(start) || !Number.isFinite(end) || end < start) { durationOutput.textContent = 'Enter a valid date range.'; return; }
                    const days = Math.floor((end - start) / 86400000) + 1;
                    const weeks = Math.ceil(days / 7);
                    durationOutput.textContent = `${days} ${days === 1 ? 'day' : 'days'} · approximately ${weeks} ${weeks === 1 ? 'week' : 'weeks'}`;
                };
                const clearValidationState = () => {
                    editorModal?.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
                    editorModal?.querySelectorAll('.invalid-feedback').forEach((feedback) => feedback.classList.add('d-none'));
                };
                const populatePhaseFields = (phase) => {
                    editorTitle.textContent = `Edit Phase ${phase.number} – ${phase.name}`;
                    editorGuidance.textContent = `Official suggested timeline: ${phase.suggestedTimeline}`;
                    officialObjective.textContent = `Official default: ${phase.officialObjective}`;
                    setValue('timeline_phase_number', phase.number);
                    setValue('strategic_objective', phase.objective);
                    setValue('planned_start_date', phase.plannedStart);
                    setValue('planned_end_date', phase.plannedEnd);
                    setValue('implementation_note', phase.implementationNote);
                    setValue('adjustment_reason');
                    adjustmentGroup?.classList.toggle('d-none', !phase.hasSchedule);
                    updateCalculatedDuration();
                };
                const openPhaseEditor = (button) => {
                    const phaseNumber = String(button.dataset.phaseNumber ?? '');
                    const phase = editorData?.[phaseNumber];
                    if (!phase || !editorModal) { console.error(`RCSP timeline phase data was not found for phase ${phaseNumber}.`); return; }
                    editorOpeningButton = button;
                    clearValidationState();
                    populatePhaseFields(phase);
                    showModal(editorModal, button);
                };

                document.addEventListener('click', (event) => {
                    const activitiesButton = event.target?.closest?.('[data-timeline-activities-view]');
                    const editButton = event.target?.closest?.('[data-timeline-phase-edit]');
                    if (!activitiesButton && !editButton) return;
                    event.preventDefault();
                    if (activitiesButton) openActivities(activitiesButton);
                    else openPhaseEditor(editButton);
                });
                activitiesModal.addEventListener('hidden.bs.modal', () => activitiesOpeningButton?.focus());
                editorModal?.addEventListener('hidden.bs.modal', () => editorOpeningButton?.focus());
                plannedStart?.addEventListener('change', updateCalculatedDuration);
                plannedEnd?.addEventListener('change', updateCalculatedDuration);
                updateCalculatedDuration();
                if (editorModal?.dataset.validationReopen === 'true') {
                    if (!showModal(editorModal, null, false)) window.addEventListener('load', () => showModal(editorModal), { once: true });
                }
            };

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeTimelineModals, { once: true });
            else initializeTimelineModals();
        })();
    </script>
@endpush
