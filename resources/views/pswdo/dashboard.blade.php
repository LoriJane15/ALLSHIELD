@extends('layouts.skydash-v')
@section('title', 'PSWDO Dashboard')
@section('heading', 'PSWDO Enrollment')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
    <div><h2 class="h4 font-weight-bold mb-1">Enrollment Dashboard</h2><p class="text-muted mb-0">Eligible surfaced FR enrollment overview.</p></div>
    <a class="btn btn-primary" href="{{ route('pswdo.enrollments.index') }}">FRs for Enrollment</a>
</div>
<div class="row">
    @foreach([['Eligible FRs', $total, 'icon-layers'], ['Pending', $pending, 'icon-clock'], ['Completed', $completed, 'icon-check']] as [$label, $count, $icon])
        <div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between"><span class="text-muted small">{{ $label }}</span><i class="{{ $icon }} text-primary"></i></div><div class="h3 mt-3 mb-0">{{ number_format($count) }}</div></div></div></div>
    @endforeach
</div>
@if($total === 0)<div class="card mt-2"><div class="card-body text-muted">No surfaced FR currently has both a completed final CDR and a completed final JAPIC certification.</div></div>@endif
@endsection
