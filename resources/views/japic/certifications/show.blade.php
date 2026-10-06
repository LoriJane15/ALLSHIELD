@extends('layouts.skydash-v')
@section('title', 'JAPIC Certification Profile')
@section('heading', 'JAPIC Certification')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
@endpush

@section('content')
@php($fr = $processing->surfacedFormerRebel)
<div class="fr-profile-container">
    <div class="module-nav-top">
        <a href="{{ route('japic.certifications.index') }}" class="module-back-link">
            <i class="mdi mdi-arrow-left"></i>
            <span>Back to FRs for Certification</span>
        </a>
        <ol class="module-breadcrumb" aria-label="Breadcrumb">
            <li><a href="{{ route('japic.dashboard') }}">Dashboard</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li><a href="{{ route('japic.certifications.index') }}">Certifications</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li class="active">{{ $fr->reference_number }}</li>
        </ol>
    </div>

    <x-surfaced-fr-progress-timeline :phases="$progressTimeline" />

    <x-surfaced-fr-profile :record="$fr" information-heading="FR Profile Information" />


    <x-surfaced-fr-documents-records :summaries="$documentSummaries" :links="$documentLinks" />

    <section class="profile-card mb-4" aria-labelledby="certification-information-heading">
        <div class="profile-card-header">
            <h2 id="certification-information-heading"><i class="mdi mdi-certificate" style="color: #312e81;"></i> Certification Information</h2>
        </div>
        <div class="profile-card-body">
            @if($fr->cancellation)
                <div class="alert alert-warning" role="status">Certification processing stopped because the FR was cancelled.</div>
            @endif
            <dl class="row mb-3">
                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8">{{ $processing->status->value }}</dd>
                <dt class="col-sm-4">Certification received</dt>
                <dd class="col-sm-8">{{ $processing->received_at?->format('M d, Y h:i A') ?? 'Not recorded' }}</dd>
                <dt class="col-sm-4">Final Certification</dt>
                <dd class="col-sm-8">{{ $processing->currentFinalVersion ? 'Available' : 'Not yet available' }}</dd>
            </dl>
            <a class="btn-action-primary" href="{{ route('japic.certifications.workspace', $processing) }}" style="background: #312e81; color: #ffffff; border-color: #312e81;">
                <i class="mdi mdi-arrow-right mr-1"></i> Open Certification Workspace
            </a>
        </div>
    </section>
</div>
@endsection
