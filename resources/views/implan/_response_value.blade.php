@php
    $responseItems = ($responses ?? collect())->filter(
        fn ($agencyResponse) => $agencyResponse->{$field} !== null && $agencyResponse->{$field} !== ''
    );
@endphp

@if ($responseItems->isEmpty())
    <span>—</span>
@else
    @foreach ($responseItems as $agencyResponse)
        <div class="implan-agency-response implan-response-block"
             data-agency-response="{{ $agencyResponse->gov_agency_id }}"
             data-response-field="{{ $field }}">
            {{ $agencyResponse->govAgency?->acronym ?? 'Agency' }} — {{ $agencyResponse->{$field} }}
        </div>
    @endforeach
@endif
