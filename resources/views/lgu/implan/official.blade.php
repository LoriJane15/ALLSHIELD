@extends('layouts.skydash-v')
@section('title', 'Official Municipality IMPLAN')
@section('heading', 'Official Municipality IMPLAN')

@if (isset($agencyTabs))
    @push('styles')
        <style>
            .official-agency-tabs { flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; }
            .official-agency-tabs .nav-link { white-space: nowrap; }
            @media print {
                #municipalityAgencyImplans .tab-pane:not(.active),
                #municipalityAgencyImplans .tab-pane:not(.active) .implan-print-area { display: none !important; visibility: hidden !important; }
                #municipalityAgencyImplans .tab-pane.active { display: block !important; visibility: visible !important; opacity: 1 !important; }
                #municipalityAgencyImplans .tab-pane.active .implan-print-area { display: block !important; visibility: visible !important; }
                .official-agency-tabs,
                .official-summary-controls { display: none !important; }
            }
        </style>
    @endpush
@endif

@section('content')
    @if (isset($agencyTabs))
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 no-print">
            <h3 class="font-weight-bold mb-0">{{ $municipality->name }} Official IMPLAN</h3>
            <div class="d-flex gap-2 official-summary-controls">
                @if ($agencyTabs->isNotEmpty())
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()" data-print-implan>
                        <i class="mdi mdi-printer"></i> Print
                    </button>
                    <a
                        id="downloadActiveAgencyImplan"
                        href="{{ route('lgu.implan.download', ['agency' => $agencyTabs->first()['agency']->id]) }}"
                        class="btn btn-primary"
                        data-download-implan>
                        <i class="mdi mdi-file-excel"></i> Download XLSX
                    </a>
                @endif
                <a href="{{ route('lgu.implan.index') }}" class="btn btn-light">Back</a>
            </div>
        </div>

        @if ($agencyTabs->isEmpty())
            <div class="alert alert-light border text-muted" data-empty-agency-implan>
                No submitted municipality IMPLANs have responsible agency assignments.
            </div>
        @else
            <div id="municipalityAgencyImplans" data-municipality-agency-implans>
                <ul class="nav nav-tabs official-agency-tabs mb-3 no-print" role="tablist" aria-label="Responsible agencies">
                    @foreach ($agencyTabs as $tab)
                        @php
                            $agency = $tab['agency'];
                        @endphp
                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link {{ $loop->first ? 'active' : '' }}"
                                id="official-agency-tab-{{ $agency->id }}"
                                data-bs-toggle="tab"
                                data-bs-target="#official-agency-pane-{{ $agency->id }}"
                                data-download-url="{{ route('lgu.implan.download', ['agency' => $agency->id]) }}"
                                data-official-agency-tab="{{ $agency->id }}"
                                type="button"
                                role="tab"
                                aria-controls="official-agency-pane-{{ $agency->id }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                {{ $agency->acronym ?: $agency->name }}
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content">
                    @foreach ($agencyTabs as $tab)
                        @php
                            $agency = $tab['agency'];
                        @endphp
                        <div
                            class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                            id="official-agency-pane-{{ $agency->id }}"
                            role="tabpanel"
                            aria-labelledby="official-agency-tab-{{ $agency->id }}"
                            data-official-agency-pane="{{ $agency->id }}">
                            @include('implan._official_format', [
                                'implans' => $tab['implans'],
                                'municipality' => $municipality,
                                'areaNamesByImplan' => $tab['areaNamesByImplan'],
                                'agenciesById' => $agenciesById,
                                'agencyScopeId' => $agency->id,
                                'showAgencyResponseAttribution' => false,
                                'showAllResponsibleAgencies' => true,
                                'emptyResponseValue' => '—',
                            ])
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        @php
            $selectedAgency = isset($agencyScopeId) ? ($agenciesById[$agencyScopeId] ?? null) : null;
            $selectedImplan = $selectedAgency ? $implans->first() : null;
        @endphp
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 no-print">
            <h3 class="font-weight-bold mb-0">
                {{ $selectedAgency ? $selectedAgency->acronym.' Agency IMPLAN' : $municipality->name.' Official IMPLAN' }}
            </h3>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" onclick="window.print()" data-print-implan>
                    <i class="mdi mdi-printer"></i> Print
                </button>
                @if ($selectedAgency)
                    <a href="{{ route('lgu.implan.show', $selectedImplan) }}" class="btn btn-light">Back</a>
                @else
                    <a href="{{ route('lgu.implan.index') }}" class="btn btn-light">Back</a>
                @endif
            </div>
        </div>

        @include('implan._official_format')
    @endif
@endsection

@if (isset($agencyTabs) && $agencyTabs->isNotEmpty())
    @push('scripts')
        <script>
            (() => {
                const initializeAgencyImplanTabs = () => {
                    const downloadButton = document.getElementById('downloadActiveAgencyImplan');
                    const tabList = document.querySelector('[data-municipality-agency-implans]');
                    if (!downloadButton || !tabList || tabList.dataset.downloadBinding === 'ready') {
                        return;
                    }

                    tabList.dataset.downloadBinding = 'ready';
                    tabList.addEventListener('shown.bs.tab', (event) => {
                        const url = event.target?.dataset?.downloadUrl;
                        if (url) {
                            downloadButton.href = url;
                        }
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initializeAgencyImplanTabs, { once: true });
                } else {
                    initializeAgencyImplanTabs();
                }
            })();
        </script>
    @endpush
@endif
