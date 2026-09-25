@php
    $monitoring = $monitoring ?? false;
    $responses = $monitoring
        ? $implan->responses
        : collect([$currentResponse ?? null])->filter();
    $responseByAgency = $implan->responses->keyBy('gov_agency_id');
@endphp

<div class="table-responsive">
    <table class="table table-bordered align-top implan-official-table">
        <thead class="table-light">
            <tr>
                <th>Issues and Concerns to be Addressed</th>
                <th>Program/Project/Activity</th>
                <th>Target Area</th>
                <th>Target Beneficiaries</th>
                <th>Expected Results/Outcome</th>
                <th>Responsible Agency</th>
                <th>Resources Needed (Funding)</th>
                <th>Support Needed</th>
                <th>Duration</th>
                <th>Action Taken</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <tr data-implementation-row="{{ $implan->id }}">
                <td data-lgu-original="issues">{{ filled($implan->issues) ? $implan->issues : '—' }}</td>
                <td data-lgu-original="program">{{ filled($implan->program) ? $implan->program : '—' }}</td>
                <td data-lgu-original="target_areas">{{ $areaNames->join(', ') ?: '—' }}</td>
                <td data-lgu-original="beneficiaries">{{ filled($implan->beneficiaries) ? $implan->beneficiaries : '—' }}</td>
                <td data-lgu-original="outcome">{{ filled($implan->outcome) ? $implan->outcome : '—' }}</td>
                <td data-lgu-original="agencies">
                    @forelse (($implan->agencies ?? []) as $agencyId)
                        @php
                            $assignedAgency = $agenciesById[$agencyId] ?? null;
                            $assignedResponse = $responseByAgency[$agencyId] ?? null;
                        @endphp
                        @if ($assignedAgency)
                            <div class="mb-2" data-responsible-agency="{{ $agencyId }}">
                                <strong>{{ $assignedAgency->acronym }}</strong>
                                @if ($assignedResponse)
                                    <span class="badge {{ $assignedResponse->response_status === 'accepted' ? 'badge-success' : ($assignedResponse->response_status === 'rejected' ? 'badge-danger' : 'badge-secondary') }}">
                                        {{ ucfirst($assignedResponse->response_status) }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    @empty
                        —
                    @endforelse
                </td>
                <td data-lgu-original="resources">{{ filled($implan->resources) ? $implan->resources : '—' }}</td>
                <td data-lgu-original="support">{{ filled($implan->support) ? $implan->support : '—' }}</td>
                <td data-lgu-original="duration">{{ filled($implan->duration) ? $implan->duration : '—' }}</td>
                <td>@include('implan._response_value', ['field' => 'action_taken', 'original' => null])</td>
                <td>@include('implan._response_value', ['field' => 'remarks', 'original' => null])</td>
            </tr>
        </tbody>
    </table>
</div>
