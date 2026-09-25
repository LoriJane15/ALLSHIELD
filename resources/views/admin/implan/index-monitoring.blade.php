@extends('layouts.skydash-h')
@section('title', 'IMPLAN Monitoring')
@section('heading', 'IMPLAN Monitoring')

@section('content')
    <div class="mb-4">
        <h3 class="font-weight-bold mb-1">IMPLAN Monitoring</h3>
        <p class="text-muted mb-0">Read-only official IMPLAN tables grouped by municipality. Drafts are excluded.</p>
    </div>

    <div class="row">
        @forelse ($municipalities as $municipality)
            <div class="col-md-6 col-xl-4 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="font-weight-bold">{{ $municipality->name }}</h5>
                        <p class="text-muted flex-grow-1">{{ (int) ($counts[$municipality->id] ?? 0) }} submitted IMPLAN row(s)</p>
                        <a href="{{ route('admin.implan.municipality', $municipality) }}" class="btn btn-primary align-self-start">Open official IMPLAN</a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="alert alert-light">No municipalities found.</div></div>
        @endforelse
    </div>
@endsection
