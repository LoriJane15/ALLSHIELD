@extends('layouts.skydash-h')
@section('title', 'IMPLAN Monitoring')
@section('heading', 'Municipality IMPLAN Monitoring')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 no-print">
        <div>
            <h3 class="font-weight-bold mb-1">{{ $municipality->name }} IMPLAN</h3>
            <p class="text-muted mb-0">Read-only LGU originals, agency responses, Action Taken, Remarks, and documentation.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()" data-print-implan><i class="mdi mdi-printer"></i> Print</button>
            <a href="{{ route('admin.implan.download', $municipality) }}" class="btn btn-primary" data-download-implan><i class="mdi mdi-file-excel"></i> Download XLSX</a>
            <a href="{{ route('admin.implan.index') }}" class="btn btn-light">Back</a>
        </div>
    </div>

    @include('implan._official_format')

    <div class="mt-4 no-print" data-implan-supporting-records>
        <div class="mb-3">
            <h4 class="font-weight-bold mb-1">IMPLAN Supporting Records</h4>
            <p class="text-muted mb-0">Documentation is grouped by the official IMPLAN record that owns it.</p>
        </div>

        @forelse ($implans as $implan)
            @php
                $agencyDocumentation = $implan->responses->map(function ($agencyResponse) use ($implan) {
                    return [
                        'response' => $agencyResponse,
                        'photos' => $agencyResponse->photos->where('implementation_id', $implan->id),
                    ];
                })->filter(fn ($documentation) => $documentation['photos']->isNotEmpty());
                $hasDocumentation = $implan->originalPhotos->isNotEmpty() || $agencyDocumentation->isNotEmpty();
            @endphp

            <div class="card mb-4" data-implan-supporting="{{ $implan->id }}">
                <div class="card-header bg-light border-bottom">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <div class="small text-uppercase text-muted font-weight-bold mb-1">IMPLAN</div>
                            <h5 class="font-weight-bold mb-1">{{ filled($implan->issues) ? $implan->issues : 'No issue specified' }}</h5>
                            @if (filled($implan->program))
                                <div class="text-muted">Program/Project/Activity: {{ $implan->program }}</div>
                            @endif
                        </div>
                        <span class="badge badge-secondary text-capitalize">{{ $implan->status }}</span>
                    </div>
                </div>

                <div class="card-body">
                    <div data-implan-documentation>
                            <h6 class="font-weight-bold mb-3">Documentation</h6>

                            @if ($implan->originalPhotos->isNotEmpty())
                                <div class="border rounded p-3 mb-3" data-lgu-documentation>
                                    <div class="font-weight-bold mb-2">LGU Original</div>
                                    <div class="row g-2">
                                        @foreach ($implan->originalPhotos as $photo)
                                            <div class="col-6 col-md-4" data-lgu-photo>
                                                <a href="{{ Storage::url($photo->image) }}" target="_blank" rel="noopener">
                                                    <img src="{{ Storage::url($photo->image) }}" class="img-fluid rounded" style="height:100px;width:100%;object-fit:cover" alt="LGU documentation">
                                                </a>
                                                <div class="small text-center mt-1">LGU Original</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @foreach ($agencyDocumentation as $documentation)
                                @php
                                    $agencyResponse = $documentation['response'];
                                    $agencyAcronym = $agencyResponse->govAgency?->acronym ?? 'Agency';
                                @endphp
                                <div class="border rounded p-3 mb-3" data-agency-documentation="{{ $agencyResponse->gov_agency_id }}">
                                    <div class="font-weight-bold mb-2">{{ $agencyAcronym }}</div>

                                    @if ($documentation['photos']->isNotEmpty())
                                        <div class="row g-2">
                                            @foreach ($documentation['photos'] as $photo)
                                                <div class="col-6 col-md-4" data-agency-photo="{{ $agencyResponse->gov_agency_id }}">
                                                    <a href="{{ Storage::url($photo->image) }}" target="_blank" rel="noopener">
                                                        <img src="{{ Storage::url($photo->image) }}" class="img-fluid rounded" style="height:100px;width:100%;object-fit:cover" alt="{{ $agencyAcronym }} documentation">
                                                    </a>
                                                    <div class="small text-center mt-1">{{ $agencyAcronym }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            @unless ($hasDocumentation)
                                <p class="small text-muted mb-0" data-empty-documentation>No documentation uploaded.</p>
                            @endunless
                    </div>
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body text-muted">No submitted IMPLANs.</div></div>
        @endforelse
    </div>
@endsection
