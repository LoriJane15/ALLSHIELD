@extends('layouts.skydash-v')
@section('title', 'IMPLAN')
@section('heading', 'Implementation Plan')

@php
    $statusBadge = match ($implan->status) {
        'verified' => 'badge-success', 'ongoing' => 'badge-primary',
        'submitted' => 'badge-info', 'for verification' => 'badge-info',
        'not yet started' => 'badge-danger', default => 'badge-secondary',
    };
    $assignedAgencyIds = collect($implan->agencies ?? [])->map(fn ($agencyId) => (int) $agencyId)->unique()->values();
    $responseByAgency = $implan->responses->keyBy('gov_agency_id');
    $firstAgencyId = $assignedAgencyIds->first();
@endphp

@push('styles')
<style>
    .table td { word-wrap: break-word; white-space: normal; max-width: 220px; padding: 15px; }
    .lgu-agency-implan-modal .modal-dialog { width: 96vw; max-width: 1700px; }
    .lgu-agency-implan-modal .modal-body { max-height: 76vh; overflow-y: auto; }
    .lgu-agency-implan-modal .nav-tabs { flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; }
    .lgu-agency-implan-modal .nav-link { white-space: nowrap; }
    .lgu-agency-implan-modal .agency-mov { display: flex; align-items: flex-start; justify-content: center; }
    .lgu-agency-implan-modal .agency-mov-preview { display: inline-flex; align-items: flex-start; justify-content: center; max-width: 100%; padding: .5rem; border: 1px solid #dbe2ea; border-radius: .5rem; background: #f8fafc; }
    .lgu-agency-implan-modal .agency-mov img { display: block; width: auto; height: auto; max-width: 100%; max-height: 520px; object-fit: contain; object-position: center; }
    @media print {
        .content-wrapper > :not(#agencyImplanResponsesModal) { display: none !important; }
        .content-wrapper { padding: 0 !important; }
        #agencyImplanResponsesModal { display: block !important; position: static !important; width: auto !important; height: auto !important; overflow: visible !important; padding: 0 !important; opacity: 1 !important; background: transparent !important; }
        #agencyImplanResponsesModal .modal-dialog { width: 100% !important; max-width: none !important; height: auto !important; min-height: 0 !important; margin: 0 !important; transform: none !important; }
        #agencyImplanResponsesModal .modal-content { border: 0 !important; box-shadow: none !important; overflow: visible !important; }
        #agencyImplanResponsesModal .modal-body { max-height: none !important; overflow: visible !important; padding: 0 !important; }
        #agencyImplanResponsesModal .modal-header,
        #agencyImplanResponsesModal .modal-footer,
        #agencyImplanResponsesModal .nav-tabs { display: none !important; }
        #agencyImplanResponsesModal .tab-pane { display: none !important; visibility: hidden !important; opacity: 0 !important; }
        #agencyImplanResponsesModal .tab-pane.active { display: block !important; visibility: visible !important; opacity: 1 !important; }
        #agencyImplanResponsesModal .tab-pane.active,
        #agencyImplanResponsesModal .tab-pane.active * { visibility: visible !important; }
        #agencyImplanResponsesModal .tab-pane.active > :not(.implan-official-document) { display: none !important; }
        #agencyImplanResponsesModal .agency-response-heading,
        #agencyImplanResponsesModal [data-agency-mov-gallery] { display: none !important; }
        #agencyImplanResponsesModal .implan-official-document { position: static !important; inset: auto !important; width: 100% !important; }
        #agencyImplanResponsesModal .implan-official-scroll { overflow: visible !important; }
        #agencyImplanResponsesModal .implan-official-table { width: 100% !important; min-width: 0 !important; }
        #agencyImplanResponsesModal .implan-official-table tr { break-inside: avoid; page-break-inside: avoid; }
        #agencyImplanResponsesModal .implan-official-banner,
        #agencyImplanResponsesModal .implan-official-table th,
        #agencyImplanResponsesModal .implan-official-table td { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>
@endpush

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $e)<p class="mb-0">{{ $e }}</p>@endforeach</div>
    @endif

    <div class="row mb-4">
        <div class="col-8 col-xl-8 mb-3 mb-xl-0">
            <h3 class="font-weight-bold mb-0">Implementation Plan</h3>
        </div>
        <div class="col-4 col-xl-4">
            <div class="justify-content-end d-flex gap-2">
                @if ($canManage && $implan->status === 'not yet started')
                    <form method="POST" action="{{ route('lgu.implan.submit', $implan) }}"
                          data-confirm="Submit this IMPLAN directly to its responsible agencies?" data-confirm-title="Submit IMPLAN" data-confirm-action="Submit" data-confirm-danger="false">
                        @csrf
                        <button class="btn btn-sm btn-success"><i class="mdi mdi-send"></i> Submit to Responsible Agencies</button>
                    </form>
                @endif
                <a href="{{ route('lgu.implan.index') }}" class="btn btn-sm btn-light bg-white">Back</a>
            </div>
        </div>
    </div>

    <div class="card mb-4 no-print" data-agency-response-summary>
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="mb-2">{{ filled($implan->program) ? $implan->program : $implan->issues }}</h5>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small">Responsible agencies</span>
                    @forelse ($assignedAgencyIds as $agencyId)
                        <span class="badge badge-secondary">{{ ($agenciesById[$agencyId] ?? null)?->acronym ?? "Agency #{$agencyId}" }}</span>
                    @empty
                        <span class="text-muted small">Not assigned</span>
                    @endforelse
                </div>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#agencyImplanResponsesModal" @disabled($assignedAgencyIds->isEmpty())>
                View Agency IMPLANs
            </button>
        </div>
    </div>

    <div class="row">
        {{-- LEFT: implementation information --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Information</h5>
                        @if ($canManage)
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editImplanModal"><i class="mdi mdi-pencil"></i> Edit</button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr><th>Expected Results/Outcome</th><th>Resources Needed<br>(Funding)</th><th>Support Needed</th><th>Duration</th></tr>
                            </thead>
                            <tbody>
                                <tr class="wrap">
                                    <td>{{ $implan->outcome ?: '—' }}</td>
                                    <td>{{ $implan->resources ?: '—' }}</td>
                                    <td>{{ $implan->support ?: '—' }}</td>
                                    <td>{{ $implan->duration ?: '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: summary --}}
        <div class="col-md-4 grid-margin">
            <div class="card">
                <div class="card-body p-3">
                    <div class="mb-3">
                        <span class="badge {{ $statusBadge }} me-2 px-3 py-2" style="font-size:.8rem;">{{ $implan->status }}</span>
                        <h4 class="mt-2" style="font-weight:500;font-size:1.2rem;">{{ $implan->issues }}</h4>
                    </div>

                    <div class="card mb-3" style="background-color:#ffebe6;border:none;border-radius:12px;">
                        <div class="card-body p-3">
                            <p class="text-muted mb-1" style="font-size:.85rem;">Target Beneficiary</p>
                            <h1 style="color:#d63300;font-weight:600;font-size:1.8rem;">{{ $implan->beneficiaries ?: '—' }}</h1>
                        </div>
                    </div>
                    <div class="card mb-3" style="background-color:#e6ffe6;border:none;border-radius:12px;">
                        <div class="card-body p-3">
                            <p class="text-muted mb-1" style="font-size:.85rem;">Resources Needed</p>
                            <h1 style="color:#008000;font-weight:600;font-size:1.8rem;">{{ $implan->resources ?: '—' }}</h1>
                        </div>
                    </div>
                    <div class="card mb-4" style="background-color:#e6e6ff;border:none;border-radius:12px;">
                        <div class="card-body p-3">
                            <p class="text-muted mb-1" style="font-size:.85rem;">Target Area</p>
                            <h4 style="color:#000080;font-weight:500;font-size:1.4rem;">{{ $areaNames->join(', ') ?: 'No Areas Defined' }}</h4>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Agency-specific official IMPLAN responses --}}
    <div class="modal fade lgu-agency-implan-modal" id="agencyImplanResponsesModal" tabindex="-1" aria-labelledby="agencyImplanResponsesTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h4 class="modal-title" id="agencyImplanResponsesTitle">Agency IMPLAN Responses</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if ($assignedAgencyIds->isNotEmpty())
                        <ul class="nav nav-tabs mb-3" role="tablist" aria-label="Responsible agencies">
                            @foreach ($assignedAgencyIds as $agencyId)
                                @php
                                    $agency = $agenciesById[$agencyId] ?? null;
                                    $agencyAcronym = $agency?->acronym ?? "Agency #{$agencyId}";
                                @endphp
                                <li class="nav-item" role="presentation">
                                    <button
                                        class="nav-link {{ $loop->first ? 'active' : '' }}"
                                        id="agency-implan-tab-{{ $agencyId }}"
                                        data-bs-toggle="tab"
                                        data-bs-target="#agency-implan-pane-{{ $agencyId }}"
                                        data-agency-implan-tab
                                        type="button"
                                        role="tab"
                                        aria-controls="agency-implan-pane-{{ $agencyId }}"
                                        aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                        {{ $agencyAcronym }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="tab-content">
                            @foreach ($assignedAgencyIds as $agencyId)
                                @php
                                    $agency = $agenciesById[$agencyId] ?? null;
                                    $agencyAcronym = $agency?->acronym ?? "Agency #{$agencyId}";
                                    $agencyResponse = $responseByAgency->get($agencyId);
                                    $responseStatus = ucfirst($agencyResponse?->response_status ?? 'pending');
                                    $responseBadge = match ($agencyResponse?->response_status) {
                                        'accepted' => 'badge-success',
                                        'rejected' => 'badge-danger',
                                        default => 'badge-secondary',
                                    };
                                    $agencyMovs = $agencyResponse?->photos ?? collect();
                                @endphp
                                <section
                                    class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                                    id="agency-implan-pane-{{ $agencyId }}"
                                    role="tabpanel"
                                    aria-labelledby="agency-implan-tab-{{ $agencyId }}"
                                    data-agency-implan-pane>
                                    <div class="agency-response-heading d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <h5 class="mb-0">{{ $agencyAcronym }}</h5>
                                        <span class="badge {{ $responseBadge }}">{{ $responseStatus }}</span>
                                    </div>

                                    @include('implan._official_format', [
                                        'implans' => collect([$implan]),
                                        'municipality' => $implan->lguUser?->municipality,
                                        'areaNamesByImplan' => collect([$implan->id => $areaNames]),
                                        'agenciesById' => $agenciesById,
                                        'agencyScopeId' => $agencyId,
                                        'showAgencyResponseAttribution' => false,
                                        'showAllResponsibleAgencies' => true,
                                        'emptyResponseValue' => 'Not yet submitted',
                                    ])

                                    <div class="mt-4" data-agency-mov-gallery>
                                        <h6 class="font-weight-bold mb-3">Mode of Verification</h6>
                                        @if ($agencyMovs->isNotEmpty())
                                            <div class="row g-2">
                                                @foreach ($agencyMovs as $photo)
                                                    <div class="col-6 col-sm-4 col-lg-3 agency-mov">
                                                        <a href="{{ route('lgu.implan.photos.show', [$implan, $photo]) }}" class="agency-mov-preview" target="_blank" rel="noopener" aria-label="Preview mode of verification">
                                                            <img src="{{ route('lgu.implan.photos.show', [$implan, $photo]) }}" class="img-fluid rounded" alt="Mode of verification">
                                                        </a>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-muted mb-0">No mode of verification available.</p>
                                        @endif
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted mb-0">No responsible agencies are assigned.</p>
                    @endif
                </div>
                <div class="modal-footer">
                    @if ($firstAgencyId)
                        <button
                            type="button"
                            class="btn btn-primary"
                            id="printActiveAgencyImplan">
                            <i class="mdi mdi-printer"></i> Print
                        </button>
                    @endif
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @if ($canManage)
    {{-- Edit modal --}}
    <div class="modal fade" id="editImplanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius:15px;overflow:hidden;">
                <div class="modal-header" style="background-color:#35127d;height:8px;padding:.5rem;border:none;"></div>
                <div class="modal-body">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="float:right;"></button>
                    <h3 style="color:#35127d;font-weight:bold;">Edit Implementation Target</h3>
                    <p class="text-muted">Update implementation plan details</p>
                    <form method="POST" action="{{ route('lgu.implan.implementation', $implan) }}">
                        @csrf @method('PUT')
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Issues and Concerns to be Addressed</label>
                                <input type="text" class="form-control" value="{{ $implan->issues }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Program/Project/Activity</label>
                                <input type="text" name="program" class="form-control" value="{{ old('program', $implan->program) }}">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Target Beneficiaries</label>
                                <input type="text" name="beneficiaries" class="form-control" value="{{ old('beneficiaries', $implan->beneficiaries) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Resources Needed (Funding)</label>
                                <input type="text" name="resources" class="form-control" value="{{ old('resources', $implan->resources) }}" placeholder="Php 0.00">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Support Needed</label>
                                <input type="text" name="support" class="form-control" value="{{ old('support', $implan->support) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Duration</label>
                                <input type="text" name="duration" class="form-control" value="{{ old('duration', $implan->duration) }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expected Results/Outcome</label>
                            <textarea name="outcome" class="form-control" rows="3">{{ old('outcome', $implan->outcome) }}</textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-info">Save changes</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @endif
@endsection

@push('scripts')
<script>
    (function () {
        const modal = document.getElementById('agencyImplanResponsesModal');
        const printButton = document.getElementById('printActiveAgencyImplan');

        if (!modal || !printButton || modal.dataset.printBinding === 'ready') {
            return;
        }

        modal.dataset.printBinding = 'ready';

        printButton.addEventListener('click', function () {
            window.print();
        });
    })();
</script>
@endpush
