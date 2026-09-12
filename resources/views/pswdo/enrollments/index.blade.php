@extends('layouts.skydash-v')
@section('title', 'FRs for Enrollment')
@section('heading', 'PSWDO Enrollment')

@section('content')
<div class="mb-4"><h2 class="h4 font-weight-bold mb-1">FRs for Enrollment</h2><p class="text-muted mb-0">Surfaced FRs with valid completed CDR and JAPIC prerequisite documents.</p></div>
<section class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Reference / FR</th><th>Category</th><th>Area</th><th>Documents</th><th>Enrollment</th><th></th></tr></thead>
    <tbody>@forelse($enrollments as $enrollment)
        @php($fr = $enrollment->surfacedFormerRebel)
        <tr><td><strong>{{ $fr->reference_number }}</strong><br><span class="text-muted">{{ $fr->display_name }}</span></td><td>{{ $fr->category->value }}</td><td>{{ $fr->barangay?->name ? $fr->barangay->name.', ' : '' }}{{ $fr->municipality->name }}</td><td>{{ $enrollment->completedDocumentCount() }} / 4 completed</td><td><span class="badge {{ $enrollment->isCompleted() ? 'badge-success' : 'badge-info' }}">{{ $enrollment->isCompleted() ? 'Completed' : 'Pending' }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('pswdo.enrollments.show', $enrollment) }}">View Profile</a></td></tr>
    @empty<tr><td colspan="6" class="text-center text-muted py-5">No eligible surfaced FRs are available for enrollment.</td></tr>@endforelse</tbody>
</table></div>@if($enrollments->hasPages())<div class="card-body border-top">{{ $enrollments->links() }}</div>@endif</section>
@endsection
