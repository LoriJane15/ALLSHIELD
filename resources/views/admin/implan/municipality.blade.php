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
            <p class="text-muted mb-0">Agenda files and documentation are grouped by the official IMPLAN record that owns them.</p>
        </div>

        @forelse ($implans as $implan)
            @php
                $agencyDocumentation = $implan->responses->map(function ($agencyResponse) use ($implan) {
                    return [
                        'response' => $agencyResponse,
                        'files' => $agencyResponse->files->where('implementation_id', $implan->id),
                        'photos' => $agencyResponse->photos->where('implementation_id', $implan->id),
                    ];
                })->filter(fn ($documentation) => $documentation['files']->isNotEmpty() || $documentation['photos']->isNotEmpty());
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
                    <div class="row">
                        <div class="col-lg-5 mb-4 mb-lg-0" data-implan-agenda>
                            <h6 class="font-weight-bold mb-3">Agenda / Supporting Files</h6>
                            @forelse ($implan->originalFiles as $file)
                                <div class="border rounded p-3 mb-2" data-lgu-attachment>
                                    <a href="{{ Storage::url($file->pdf) }}" target="_blank" rel="noopener" class="font-weight-medium">
                                        {{ $file->file_name }}
                                    </a>
                                    <div class="small text-muted mt-1">
                                        <span class="badge badge-secondary mr-1">LGU Original</span>
                                        @if (filled($file->description))
                                            {{ $file->description }}
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="small text-muted mb-0" data-empty-agenda>No LGU agenda/supporting file uploaded.</p>
                            @endforelse
                        </div>

                        <div class="col-lg-7" data-implan-documentation>
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

                                    @foreach ($documentation['files'] as $file)
                                        <div class="border rounded p-2 mb-2" data-agency-attachment="{{ $agencyResponse->gov_agency_id }}">
                                            <a href="{{ Storage::url($file->pdf) }}" target="_blank" rel="noopener" class="font-weight-medium">
                                                {{ $file->file_name }}
                                            </a>
                                            <div class="small text-muted mt-1">
                                                {{ $agencyAcronym }}@if (filled($file->description)) &middot; {{ $file->description }}@endif
                                            </div>
                                        </div>
                                    @endforeach

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
            </div>
        @empty
            <div class="card"><div class="card-body text-muted">No submitted IMPLANs.</div></div>
        @endforelse
    </div>
@endsection
