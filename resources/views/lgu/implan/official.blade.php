@extends('layouts.skydash-v')
@section('title', 'Official Municipality IMPLAN')
@section('heading', 'Official Municipality IMPLAN')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 no-print">
        <div>
            <h3 class="font-weight-bold mb-1">{{ $municipality->name }} Official IMPLAN</h3>
            <p class="text-muted mb-0">One submitted LGU-created IMPLAN appears as one official row.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()" data-print-implan>
                <i class="mdi mdi-printer"></i> Print
            </button>
            <a href="{{ route('lgu.implan.download') }}" class="btn btn-primary" data-download-implan>
                <i class="mdi mdi-file-excel"></i> Download XLSX
            </a>
            <a href="{{ route('lgu.implan.index') }}" class="btn btn-light">Back</a>
        </div>
    </div>

    @include('implan._official_format')
@endsection
