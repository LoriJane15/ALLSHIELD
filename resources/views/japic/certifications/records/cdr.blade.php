@extends('layouts.skydash-v')
@section('title', 'CDR Record')
@section('heading', $heading ?? 'JAPIC Certification')

@section('content')
<a href="{{ $backUrl }}" class="d-inline-block mb-3">&larr; Back to surfaced FR profile</a>
<section class="card"><div class="card-header"><h2 class="h5 mb-0">CDR</h2></div><div class="card-body">
    <p><strong>Status:</strong> {{ $cdrStatus }}</p>
    @if($finalCdr)
        <p>This is the current authoritative final CDR for {{ $referenceNumber }}.</p>
        <p class="text-muted"><strong>{{ $date['label'] ? $date['label'].':' : '' }}</strong>{{ $date['label'] ? ' ' : '' }}{{ $date['value'] }}</p>
        <a class="btn btn-outline-primary" href="{{ $previewUrl }}">Secure preview</a>
        @if($downloadUrl)
            <a class="btn btn-outline-secondary" href="{{ $downloadUrl }}">Secure download</a>
        @endif
    @else
        <p class="text-muted mb-0">No documents available</p>
    @endif
</div></section>
@endsection
