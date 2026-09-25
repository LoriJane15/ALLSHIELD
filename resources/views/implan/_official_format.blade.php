@php
    $implans = $implans ?? collect([$implan]);
    $areaNamesByImplan = $areaNamesByImplan ?? collect();
@endphp

@once
    @push('styles')
        <style>
            .implan-official-document { background:#fff; border:1px solid #c9d0da; font-family:Arial,sans-serif; }
            .implan-official-banner { min-height:80px; background:#002060; color:#fff; display:flex; align-items:center; padding:0; }
            .implan-official-banner img { width:80px; height:80px; object-fit:contain; flex:0 0 80px; }
            .implan-official-title { flex:1; text-align:center; font-family:"Arial Narrow",Arial,sans-serif; font-size:20px; font-weight:700; letter-spacing:.15px; line-height:1.2; padding:12px 90px 12px 10px; }
            .implan-official-municipality { display:block; font-family:Arial,sans-serif; font-size:12px; margin-top:5px; letter-spacing:.4px; }
            .implan-official-scroll { overflow-x:auto; }
            .implan-official-table { width:100%; min-width:1800px; table-layout:fixed; border-collapse:collapse; margin:0; font-family:Arial,sans-serif; font-size:10pt; }
            .implan-official-table th { background:#1f497d; color:#fff; font-weight:700; text-align:center; vertical-align:middle; white-space:normal; line-height:1.25; padding:10px 7px; border:1px solid #000; }
            .implan-official-table td { background:#e9edf4; color:#000; text-align:center; vertical-align:top; white-space:normal; overflow-wrap:anywhere; line-height:1.35; padding:10px 7px; border:1px solid #000; }
            .implan-official-table th:nth-child(1) { width:14.67%; }
            .implan-official-table th:nth-child(2) { width:24.78%; }
            .implan-official-table th:nth-child(3) { width:13.56%; }
            .implan-official-table th:nth-child(4) { width:14.89%; }
            .implan-official-table th:nth-child(5) { width:15.33%; }
            .implan-official-table th:nth-child(6) { width:11.78%; }
            .implan-official-table th:nth-child(7) { width:13.67%; }
            .implan-official-table th:nth-child(8) { width:16.11%; }
            .implan-official-table th:nth-child(9), .implan-official-table th:nth-child(10) { width:9.56%; }
            .implan-official-table th:nth-child(11) { width:14.78%; }
            .implan-response-label { display:block; color:#1f497d; font-size:.72rem; font-weight:700; margin-bottom:2px; }
            .implan-response-block + .implan-response-block { margin-top:8px; padding-top:8px; border-top:1px solid rgba(0,0,0,.22); }
            @media print {
                @page { size:landscape; margin:8mm; }
                body { background:#fff !important; }
                body * { visibility:hidden; }
                .implan-print-area, .implan-print-area * { visibility:visible; }
                .implan-print-area { position:absolute; inset:0; width:100%; }
                .no-print, nav, .navbar, .sidebar, footer { display:none !important; }
                .implan-official-document { border:0; }
                .implan-official-scroll { overflow:visible; }
                .implan-official-table { min-width:0; width:100%; font-size:7pt; }
                .implan-official-table th, .implan-official-table td { padding:4px 3px; }
                .implan-official-banner, .implan-official-table th, .implan-official-table td { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            }
        </style>
    @endpush
@endonce

<div class="implan-official-document implan-print-area" data-official-implan>
    <div class="implan-official-banner">
        <img src="{{ asset('assets/IMPLANs/ELCAC.png') }}" alt="ELCAC logo" data-official-logo>
        <div class="implan-official-title">
            BASIC SERVICES CLUSTER IMPLEMENTATION PLAN
            <span class="implan-official-municipality">MUNICIPALITY OF {{ strtoupper($municipality?->name ?? 'UNSPECIFIED') }}</span>
        </div>
    </div>
    <div class="implan-official-scroll">
        <table class="implan-official-table">
            <thead>
                <tr>
                    <th>Issues and Concerns to be Addressed</th>
                    <th>Program/Project/Activity</th>
                    <th>Target Area</th>
                    <th>Target Beneficiaries</th>
                    <th>Expected Results/Outcome</th>
                    <th>Responsible Agency</th>
                    <th>Resources Needed<br>
                        (Funding)</th>
                    <th>Support Needed</th>
                    <th>Duration</th>
                    <th>Action Taken</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($implans as $row)
                    @php
                        $responseByAgency = $row->responses->keyBy('gov_agency_id');
                        $responses = collect($row->agencies ?? [])
                            ->map(fn ($agencyId) => $responseByAgency->get((int) $agencyId))
                            ->filter();
                        $rowAreaNames = $areaNamesByImplan[$row->id] ?? collect();
                    @endphp
                    <tr data-implementation-row="{{ $row->id }}">
                        <td data-lgu-original="issues">{{ filled($row->issues) ? $row->issues : '—' }}</td>
                        <td data-lgu-original="program">{{ filled($row->program) ? $row->program : '—' }}</td>
                        <td data-lgu-original="target_areas">{{ $rowAreaNames->join(', ') ?: '—' }}</td>
                        <td data-lgu-original="beneficiaries">{{ filled($row->beneficiaries) ? $row->beneficiaries : '—' }}</td>
                        <td data-lgu-original="outcome">{{ filled($row->outcome) ? $row->outcome : '—' }}</td>
                        <td data-lgu-original="agencies">
                            @forelse (($row->agencies ?? []) as $agencyId)
                                @php
                                    $assignedAgency = $agenciesById[$agencyId] ?? null;
                                    $assignedResponse = $responseByAgency[$agencyId] ?? null;
                                    $assignedStatus = ucfirst($assignedResponse?->response_status ?? 'pending');
                                @endphp
                                <div class="implan-response-block" data-responsible-agency="{{ $agencyId }}">
                                    <strong>{{ $assignedAgency?->acronym ?? "Agency #{$agencyId}" }} ({{ $assignedStatus }})</strong>
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td data-lgu-original="resources">{{ filled($row->resources) ? $row->resources : '—' }}</td>
                        <td data-lgu-original="support">{{ filled($row->support) ? $row->support : '—' }}</td>
                        <td data-lgu-original="duration">{{ filled($row->duration) ? $row->duration : '—' }}</td>
                        <td>@include('implan._response_value', ['field' => 'action_taken', 'original' => null, 'responses' => $responses])</td>
                        <td>@include('implan._response_value', ['field' => 'remarks', 'original' => null, 'responses' => $responses])</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center">No submitted IMPLAN records for this municipality.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
