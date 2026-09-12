@extends('layouts.skydash-v')
@section('title', 'PSWDO Enrollment Profile')
@section('heading', 'PSWDO Enrollment')

@section('content')
@php($fr = $enrollment->surfacedFormerRebel)
<div class="fr-profile-container">
    <div class="module-nav-top"><a href="{{ route('pswdo.enrollments.index') }}" class="module-back-link"><i class="mdi mdi-arrow-left"></i><span>Back to FRs for Enrollment</span></a></div>
    <x-surfaced-fr-profile :record="$fr" information-heading="FR Profile Information" />
    <x-surfaced-fr-documents-records :summaries="$documentSummaries" :links="$documentLinks" />
    <section class="profile-card"><div class="profile-card-header"><h2><i class="mdi mdi-file-document-multiple-outline"></i>PSWDO Enrollment Workspace</h2><span class="badge {{ $enrollment->isCompleted() ? 'badge-success' : 'badge-info' }}">{{ $enrollment->isCompleted() ? 'Completed' : 'Pending' }}</span></div><div class="profile-card-body"><p class="text-muted">Upload controls are kept in the dedicated workspace.</p><a class="btn-action-primary" href="{{ route('pswdo.enrollments.workspace', $enrollment) }}">Open Enrollment Workspace</a></div></section>
</div>
@endsection
