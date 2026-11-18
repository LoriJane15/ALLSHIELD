@php
    $responseItems = ($responses ?? collect())->filter(
        fn ($agencyResponse) => $agencyResponse->{$field} !== null && $agencyResponse->{$field} !== ''
    );
    $showAgencyResponseAttribution = $showAgencyResponseAttribution ?? true;
    $emptyResponseValue = $emptyResponseValue ?? '—';
@endphp

@if ($responseItems->isEmpty())
    <span>{{ $emptyResponseValue }}</span>
@else
    @foreach ($responseItems as $agencyResponse)
        <div class="implan-agency-response implan-response-block"
             data-agency-response="{{ $agencyResponse->gov_agency_id }}"
             data-response-field="{{ $field }}">
            @if ($showAgencyResponseAttribution){{ $agencyResponse->govAgency?->acronym ?? 'Agency' }} — @endif{{ $agencyResponse->{$field} }}
        </div>
    @endforeach
@endif
