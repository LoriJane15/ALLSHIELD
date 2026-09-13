@extends('layouts.skydash-v')
@section('title', 'Assistance Records')
@section('heading', $heading ?? 'JAPIC Certification')

@section('content')
<a href="{{ $backUrl }}" class="d-inline-block mb-3">&larr; Back to surfaced FR profile</a>
<section class="card"><div class="card-header"><h2 class="h5 mb-0">Assistance Records</h2></div><div class="card-body"><p class="text-muted mb-0">No documents available</p></div></section>
@endsection
