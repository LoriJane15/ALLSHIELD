@extends('layouts.skydash-v')
@section('title', 'PSWDO Enrollment Profile')
@section('heading', 'PSWDO Enrollment')

@section('content')
@php($fr = $enrollment->surfacedFormerRebel)
<div class="fr-profile-container">
    <div class="module-nav-top">
        <a href="{{ route('pswdo.enrollments.index') }}" class="module-back-link">
            <i class="mdi mdi-arrow-left"></i>
            <span>Back to FRs for Enrollment</span>
        </a>
        <ul class="module-breadcrumb">
            <li><a href="{{ route('pswdo.dashboard') }}">PSWDO</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li><a href="{{ route('pswdo.enrollments.index') }}">FRs for Enrollment</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li class="active">{{ $fr->reference_number }}</li>
        </ul>
    </div>

    <x-surfaced-fr-progress-timeline :phases="$progressTimeline" />
    <x-surfaced-fr-profile :record="$fr" information-heading="FR Profile Information" />
    <x-surfaced-fr-documents-records :summaries="$documentSummaries" :links="$documentLinks" />

    <section class="profile-card">
        <div class="profile-card-header">
            <h2>
                <i class="mdi mdi-file-document-multiple-outline text-primary"></i>
                <span>PSWDO Enrollment Workspace</span>
            </h2>
            <span class="badge {{ $enrollment->isCompleted() ? 'badge-success' : 'badge-info' }}" style="padding: 0.4rem 0.85rem; border-radius: 9999px; font-weight: 750;">
                <i class="mdi {{ $enrollment->isCompleted() ? 'mdi-check-circle-outline' : 'mdi-clock-outline' }} mr-1"></i>
                {{ $enrollment->isCompleted() ? 'Completed' : 'Pending' }}
            </span>
        </div>
        <div class="profile-card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap: 1rem;">
                <div>
                    <h4 class="h6 font-weight-bold mb-1" style="color: #0f172a;">Document Intake & Verification</h4>
                    <p class="text-muted mb-0" style="font-size: 0.85rem;">Upload controls and document management are kept in the dedicated workspace.</p>
                </div>
                <a class="btn-action-primary" href="{{ route('pswdo.enrollments.workspace', $enrollment) }}">
                    <i class="mdi mdi-tray-arrow-up mr-1"></i> Open Enrollment Workspace
                </a>
            </div>
        </div>
    </section>
</div>
@endsection
