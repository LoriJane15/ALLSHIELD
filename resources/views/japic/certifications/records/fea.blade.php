@extends('layouts.skydash-v')
@section('title', 'FEA Processing Documents')
@section('heading', $heading ?? 'JAPIC Certification')

@section('content')
<a href="{{ $backUrl }}" class="d-inline-block mb-3">&larr; Back to surfaced FR profile</a>
<section class="card"><div class="card-header"><h2 class="h5 mb-0">FEA Processing Documents</h2></div><div class="card-body">
    @forelse($documents as $document)
        <section class="border-bottom pb-3 mb-3"><h3 class="h6 font-weight-bold">{{ $document['label'] }}</h3>
            @foreach($document['versions'] as $version)
                <div class="d-flex align-items-center justify-content-between flex-wrap py-2"><span>{{ $version['label'] }}</span><span><a class="btn btn-sm btn-outline-primary" href="{{ $version['previewUrl'] }}">Secure preview</a> <a class="btn btn-sm btn-outline-secondary" href="{{ $version['downloadUrl'] }}">Secure download</a></span></div>
            @endforeach
        </section>
    @empty
        <p class="text-muted mb-0">No FEA processing documents are available yet.</p>
    @endforelse
</div></section>
@endsection
