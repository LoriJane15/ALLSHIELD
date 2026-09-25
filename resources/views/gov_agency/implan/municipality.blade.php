@extends('layouts.skydash-v')
@section('title', 'Municipality IMPLAN Monitoring')
@section('heading', 'Municipality IMPLAN Monitoring')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 no-print">
        <div>
            <h3 class="font-weight-bold mb-1">{{ $municipality->name }} Official IMPLAN</h3>
            <p class="text-muted mb-0" data-monitoring-total="{{ $implans->count() }}">
                Total IMPLANs: {{ $implans->count() }} &middot; Read-only municipality monitoring
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()" data-print-implan>
                <i class="mdi mdi-printer"></i> Print
            </button>
            <a href="{{ route('gov_agency.implan.index') }}" class="btn btn-light">Back</a>
        </div>
    </div>

    @include('implan._official_format')
@endsection
