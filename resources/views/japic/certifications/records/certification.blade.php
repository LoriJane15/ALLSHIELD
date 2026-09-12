@extends('layouts.skydash-v')
@section('title', 'JAPIC Certification Record')
@section('heading', $heading ?? 'JAPIC Certification')

@section('content')
<a href="{{ $backUrl }}" class="d-inline-block mb-3">&larr; Back to surfaced FR profile</a>
<section class="card"><div class="card-header"><h2 class="h5 mb-0">JAPIC Certification</h2></div><div class="card-body">
    <p><strong>Status:</strong> {{ $certificationStatus }}</p>
    @if($finalCertification)
        <p>The current final signed JAPIC certification is available for secure viewing.</p>
        <a class="btn btn-outline-primary" href="{{ $previewUrl }}">Secure preview</a>
        <a class="btn btn-outline-secondary" href="{{ $downloadUrl }}">Secure download</a>
    @else
        <p class="text-muted mb-0">No final JAPIC certification document is available yet.</p>
    @endif
</div></section>
@endsection
