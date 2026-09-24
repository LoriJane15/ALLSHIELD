@extends('layouts.skydash-v')
@section('title', 'Monitoring Form')
@section('heading', 'RCSP Implementation Monitoring Form')

@php
    $phaseDesc = [0 => 'Pre-Shaping', 1 => 'Shape', 2 => 'Access', 3 => 'Transform', 4 => 'Sustain', 5 => 'Monitor'];
    $statusBadge = fn ($s) => match ($s) {
        'submitted', 'updated' => 'bg-primary',
        'approved' => 'bg-success',
        'disapproved' => 'bg-danger',
        'to be conducted' => 'bg-primary',
        'to be complied' => 'bg-orange',
        default => 'bg-secondary',
    };
    $cur = $rcspBarangay->current_phase;
    $allApproved = $activities->isNotEmpty() && $activities->every(fn ($a) => optional($forms->get($a->id))->status === 'approved');
@endphp

@push('styles')
<style>
    .progress-tracker { display: flex; align-items: center; justify-content: space-evenly; width: 100%; margin: 0 auto; }
    .phase { display: flex; align-items: center; text-align: center; flex: 1 1 auto; }
    .circle { width: 24px; height: 24px; background-color: #403e92; border-radius: 50%; margin-right: 10px; display: flex; align-items: center; justify-content: center; }
    .checkmark { font-size: 20px; color: #fff; font-weight: 600; }
    .phase-text { display: flex; align-items: center; }
    .number { font-weight: bold; font-size: 1.7em; color: #333; margin-right: 5px; }
    .label-container { display: flex; flex-direction: column; text-align: left; }
    .phase-label { font-weight: bold; color: #333; }
    .phase-desc { color: #777; font-size: .85em; }
    .separator { height: 2px; background-color: #403e92; margin: 0 10px; flex-grow: 1; }
    .phase.current .circle { animation: pulse 2s infinite; background-color: #403e92; }
    .phase.disabled { opacity: .5; }
    .phase.completed .circle { background-color: #28a745; }
    @keyframes pulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgb(64, 62, 146); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(255, 127, 65, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 127, 65, 0); }
    }
    .conduct-options { display: flex; align-items: center; gap: 1.25rem; }
    .conduct-opt { display: inline-flex; align-items: center; gap: .35rem; margin: 0; cursor: pointer; }
    .conduct-opt input { margin: 0; position: static; }
    .conduct-opt i { font-size: 1rem; line-height: 1; }
    .bg-orange { background-color: #fd7e14 !important; color: #fff !important; }
    .table td { vertical-align: middle; }

    /* Styled evidence-file upload */
    .file-drop {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 2px; width: 100%; padding: 14px 10px; cursor: pointer;
        border: 1.5px dashed #c7c9e6; border-radius: 10px; background: #f8f8fd;
        transition: border-color .2s, background .2s; text-align: center; margin: 0;
    }
    .file-drop:hover { border-color: #403e92; background: #f2f2fb; }
    .file-drop.has-file { border-style: solid; border-color: #28a745; background: #f2fbf5; }
    .file-drop-icon { font-size: 1.5rem; color: #403e92; line-height: 1; }
    .file-drop.has-file .file-drop-icon { color: #28a745; }
    .file-drop-text { font-size: .8rem; color: #555; font-weight: 500; }
    .file-name { font-size: .75rem; color: #28a745; word-break: break-all; }
</style>
@endpush

@section('content')
    <div class="row mb-3">
        <div class="col-9 col-xl-8 mb-3 mb-xl-0">
            <h3 class="font-weight-bold">
                RCSP Implementation Monitoring Form
                <span class="fs-4" style="color: #403e92; font-weight: bold;">| {{ $rcspBarangay->barangay?->name }} - {{ $rcspBarangay->municipality?->name }}</span>
            </h3>
            <h6 class="mb-0 text-muted font-weight-light">
                <span style="color: #403e92; font-weight: bold;">This Report is pursuant to Unnumbered Memoranda</span>
                dated September 10, 2019 and September 30, 2019; Memorandum Circular No. 2019-169 dated October 11, 2019
            </h6>
        </div>
        <div class="col-3 col-xl-4">
            <div class="justify-content-end d-flex gap-2">
                <button data-bs-toggle="modal" data-bs-target="#phasesModal" class="btn btn-sm btn-primary">View Phases</button>
                <a href="{{ route('lgu.rcsp.index') }}" class="btn btn-sm btn-light bg-white">Back</a>
            </div>
        </div>
    </div>

    {{-- Progress tracker --}}
    <div class="row mb-3">
        <div class="card">
            <div class="card-body">
                <div class="progress-tracker">
                    @php $allDone = $rcspBarangay->status === 'Completed'; @endphp
                    @foreach ($phases as $p)
                        @php $state = ($allDone || $p->number < $cur) ? 'completed' : ($p->number === $cur ? 'current' : 'disabled'); @endphp
                        <div class="phase {{ $state }}">
                            <div class="circle">@if ($state === 'completed')<span class="checkmark">✓</span>@endif</div>
                            <div class="phase-text">
                                <span class="number">{{ str_pad($p->number, 2, '0', STR_PAD_LEFT) }}</span>
                                <div class="label-container">
                                    <span class="phase-label">Phase {{ $p->number }}</span>
                                    <span class="phase-desc">{{ $phaseDesc[$p->number] ?? '' }}</span>
                                </div>
                            </div>
                        </div>
                        @if (! $loop->last)<div class="separator"></div>@endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <h4 class="font-weight-bold" style="color: #403e92;">Phase {{ $cur }}: {{ $currentPhase->name }}</h4>
        </div>
    </div>

    @if ($rcspBarangay->status === 'Completed')
        <div class="card"><div class="card-body text-center">
            <i class="mdi mdi-check-decagram text-success" style="font-size:2.5rem"></i>
            <p class="mt-2 font-weight-bold mb-0">RCSP monitoring completed for this barangay.</p>
        </div></div>
    @else
        <form method="POST" action="{{ route('lgu.monitoring.submit', $rcspBarangay) }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="phase_id" value="{{ $currentPhase->id }}">
            <div class="card">
                <div class="card-body pb-2">
                    <div class="table-responsive">
                        <table class="table table-fixed">
                            <thead>
                                <tr>
                                    <th style="width:30%">Activities</th>
                                    <th style="width:18%">Conduct of the Activity</th>
                                    <th style="width:24%">Mode of Verification</th>
                                    <th style="width:14%">Status</th>
                                    <th style="width:14%">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($activities as $activity)
                                    @php $form = $forms->get($activity->id); $locked = $form && $form->status === 'approved'; @endphp
                                    <tr class="{{ $locked ? 'bg-light' : '' }}">
                                        <td class="text-wrap">{{ $activity->description }}</td>
                                        <td>
                                            <div class="conduct-options">
                                                @foreach (['yes' => '<i class="ti-check text-success"></i>', 'no' => '<i class="ti-close text-danger"></i>', 'n/a' => '<span class="text-dark small">N/A</span>'] as $val => $icon)
                                                    <label class="conduct-opt">
                                                        <input type="radio" name="conduct_{{ $activity->id }}" value="{{ $val }}"
                                                               class="form-check-input" @checked($form?->conduct === $val) @disabled($locked)>
                                                        {!! $icon !!}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td>
                                            @unless ($locked)
                                                <label class="file-drop">
                                                    <input type="file" name="file_{{ $activity->id }}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                                           class="file-input" hidden>
                                                    <i class="mdi mdi-cloud-upload-outline file-drop-icon"></i>
                                                    <span class="file-drop-text">Click to upload evidence</span>
                                                    <span class="file-name"></span>
                                                </label>
                                                <small class="text-muted d-block mt-1">PDF, image or Word · max 25MB</small>
                                            @else
                                                <span class="badge bg-success"><i class="ti-check me-1"></i>Approved — locked</span>
                                            @endunless
                                        </td>
                                        <td>
                                            @if ($form)
                                                <span class="badge {{ $statusBadge($form->status) }}">{{ ucfirst($form->status) }}</span>
                                            @else
                                                <span class="badge bg-secondary">Pending</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($form)
                                                @if ($form->remarks)
                                                    <p class="small text-danger mb-1"><strong>Admin:</strong> {{ $form->remarks }}</p>
                                                @endif
                                                <a href="{{ route('lgu.monitoring.file', $form->id) }}" class="btn btn-sm btn-outline-secondary" title="View file & discussion">
                                                    <i class="fa fa-comments"></i>
                                                    @if ($form->fileComments->count())<span class="badge bg-primary">{{ $form->fileComments->count() }}</span>@endif
                                                </a>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">No activities in this phase.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @unless ($allApproved)
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                    @endunless
                </div>
            </div>
        </form>

        @if ($allApproved)
            <div class="d-flex justify-content-end mt-2 mb-3">
                <form method="POST" action="{{ route('lgu.monitoring.proceed', $rcspBarangay) }}">
                    @csrf
                    <button type="submit" class="btn btn-success px-4">
                        {{ $cur >= 5 ? 'Complete' : 'Proceed to Phase '.($cur + 1) }}
                    </button>
                </form>
            </div>
        @endif
    @endif

    {{-- View Phases modal (approved compliance report) --}}
    <div class="modal fade" id="phasesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h3 class="modal-title"><i class="mdi mdi-format-list-checks me-2"></i>RCSP Compliance Report</h3>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @forelse ($approvedForms as $phaseId => $group)
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="mb-0" style="color:#ff7f41;">
                                    {{ $group->first()->phase?->name }} <span class="badge bg-success ms-2">Approved</span>
                                </h4>
                                <span><i class="mdi mdi-check-circle text-success fs-4"></i></span>
                            </div>
                            <div class="card-body">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr><th style="width:55%">Activities</th><th style="width:25%">Conduct</th><th style="width:20%">Status</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($group as $f)
                                            <tr>
                                                <td>{{ $f->activity?->description }}</td>
                                                <td>
                                                    @if ($f->conduct === 'yes')<span class="badge bg-success"><i class="ti-check me-1"></i>Yes</span>
                                                    @elseif ($f->conduct === 'no')<span class="badge bg-danger"><i class="ti-close me-1"></i>No</span>
                                                    @else<span class="badge bg-secondary">N/A</span>@endif
                                                </td>
                                                <td><span class="badge bg-success"><i class="ti-check me-1"></i>Approved</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-info"><i class="mdi mdi-information-outline me-2"></i>No approved phases found yet.</div>
                    @endforelse
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    document.querySelectorAll('.file-drop .file-input').forEach((input) => {
        input.addEventListener('change', function () {
            const drop = this.closest('.file-drop');
            const nameEl = drop.querySelector('.file-name');
            const textEl = drop.querySelector('.file-drop-text');
            if (this.files.length) {
                drop.classList.add('has-file');
                textEl.textContent = 'Selected file';
                nameEl.textContent = this.files[0].name;
            } else {
                drop.classList.remove('has-file');
                textEl.textContent = 'Click to upload evidence';
                nameEl.textContent = '';
            }
        });
    });
</script>
@endpush
