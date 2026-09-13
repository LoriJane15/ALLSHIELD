@extends('layouts.skydash-v')
@section('title', 'PSWDO Enrollment Documents')
@section('heading', $heading ?? 'JAPIC Certification')

@section('content')
<a href="{{ $backUrl }}" class="d-inline-block mb-3">&larr; Back to surfaced FR profile</a>
<section class="card"><div class="card-header"><h2 class="h5 mb-0">PSWDO Enrollment Documents</h2></div><div class="card-body">
    @forelse($documents as $document)
        <section class="border-bottom pb-3 mb-3">
            <h3 class="h6 font-weight-bold">{{ $document['label'] }}</h3>
            <p class="small text-muted"><strong>{{ $document['date']['label'] ? $document['date']['label'].':' : '' }}</strong>{{ $document['date']['label'] ? ' ' : '' }}{{ $document['date']['value'] }}</p>
            <a class="btn btn-sm btn-outline-primary" href="{{ $document['previewUrl'] }}">Secure preview</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ $document['downloadUrl'] }}">Secure download</a>
        </section>
    @empty
        <p class="text-muted mb-0">No documents available</p>
    @endforelse
</div></section>
@endsection
