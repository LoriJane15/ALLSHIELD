@extends('layouts.skydash-v')
@section('title', 'PSWDO Enrollment Workspace')
@section('heading', 'PSWDO Enrollment')

@push('styles')
<style>
.pswdo-workspace-container {
    max-width: 1280px;
    margin: 0 auto;
    padding-bottom: 2.5rem;
}

/* Top Navigation Bar */
.pswdo-nav-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.65rem 1.15rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.pswdo-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #475569;
    font-size: 0.8125rem;
    font-weight: 700;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    text-decoration: none !important;
    transition: all 0.2s ease;
}
.pswdo-back-btn:hover {
    background: #eef2ff;
    border-color: #c7d2fe;
    color: #4338ca;
    transform: translateX(-2px);
}
.pswdo-breadcrumb-trail {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.8125rem;
    color: #64748b;
    margin: 0;
    padding: 0;
    list-style: none;
}
.pswdo-breadcrumb-trail li a {
    color: #64748b;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.15s ease;
}
.pswdo-breadcrumb-trail li a:hover {
    color: #4338ca;
}
.pswdo-breadcrumb-trail li.active {
    color: #0f172a;
    font-weight: 700;
}
.pswdo-breadcrumb-trail .separator {
    color: #cbd5e1;
    font-size: 0.75rem;
}

/* Modern Hero Card */
.pswdo-hero-card {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%);
    border-radius: 16px;
    padding: 1.4rem 1.75rem;
    box-shadow: 0 4px 20px -2px rgba(67, 56, 202, 0.25);
    position: relative;
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.pswdo-hero-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.22);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a5f3fc;
    font-size: 1.6rem;
    flex-shrink: 0;
}
.pswdo-hero-eyebrow {
    color: #fbbf24;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 0.2rem;
}
.pswdo-hero-title {
    font-size: 1.45rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.02em;
    margin-bottom: 0.2rem;
    line-height: 1.3;
}
.pswdo-hero-sub {
    font-size: 0.825rem;
    color: rgba(255, 255, 255, 0.75);
    margin: 0;
}

/* Stepper Component */
.pswdo-stepper-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
}
.pswdo-stepper-list {
    display: flex;
    align-items: flex-start;
    list-style: none;
    margin: 0;
    padding: 0.25rem 0;
}
.pswdo-step-item {
    flex: 1;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 0 0.5rem;
}
.pswdo-step-item:not(:last-child)::after {
    content: "";
    position: absolute;
    top: 19px;
    left: 55%;
    right: -45%;
    height: 3px;
    background: #e2e8f0;
    z-index: 0;
    border-radius: 9999px;
}
.pswdo-step-item.is-done:not(:last-child)::after {
    background: #10b981;
}
.pswdo-step-bubble {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid #cbd5e1;
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
    transition: all 0.25s ease;
}
.pswdo-step-item.is-done .pswdo-step-bubble {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
}
.pswdo-step-item.is-active .pswdo-step-bubble {
    background: #eef2ff;
    border-color: #4338ca;
    color: #4338ca;
    box-shadow: 0 0 0 4px rgba(67, 56, 202, 0.15);
}
.pswdo-step-label {
    font-size: 0.8125rem;
    font-weight: 750;
    color: #0f172a;
    line-height: 1.25;
    margin-bottom: 0.2rem;
}
.pswdo-step-tag {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
}
.pswdo-step-item.is-done .pswdo-step-tag {
    color: #059669;
    font-weight: 750;
}
.pswdo-step-item.is-active .pswdo-step-tag {
    color: #4338ca;
    font-weight: 750;
}

/* Document Cards */
.pswdo-doc-section-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    margin-bottom: 1.35rem;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
    overflow: hidden;
    transition: all 0.2s ease;
}
.pswdo-doc-section-card:hover {
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
    border-color: #cbd5e1;
}
.pswdo-doc-head {
    padding: 1.15rem 1.5rem;
    background: #fafbfc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.pswdo-doc-head-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
}
.pswdo-doc-head-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.pswdo-doc-body {
    padding: 1.5rem;
}

/* Upload Dropzone & Forms */
.pswdo-upload-zone {
    background: #f8fafc;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 1.25rem;
    position: relative;
    cursor: pointer;
    transition: all 0.2s ease;
}
.pswdo-upload-zone:hover {
    border-color: #4338ca;
    background: #fdfdfe;
}
.pswdo-file-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}

/* Checkbox Verification Cards */
.pswdo-verify-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.85rem 1.1rem;
    margin-bottom: 0.65rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    transition: all 0.15s ease;
    cursor: pointer;
}
.pswdo-verify-card:hover {
    border-color: #94a3b8;
    background: #f8fafc;
}
.pswdo-verify-card.checked {
    border-color: #c7d2fe;
    background: #f5f3ff;
}
.pswdo-verify-card .form-check-input {
    margin-top: 0.2rem;
    margin-left: 0;
    width: 1.1rem;
    height: 1.1rem;
    cursor: pointer;
}
.pswdo-verify-label {
    font-size: 0.835rem;
    font-weight: 600;
    color: #334155;
    line-height: 1.45;
    margin: 0;
    cursor: pointer;
}

/* Locked Card Notice */
.pswdo-lock-banner {
    background: #fafafa;
    border: 1px dashed #d1d5db;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    color: #4b5563;
    font-size: 0.875rem;
}
.pswdo-lock-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #fef3c7;
    color: #d97706;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

/* File Info Box */
.pswdo-file-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.15rem 1.35rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}
.pswdo-final-upload-card .pswdo-doc-head {
    padding: 0.72rem 1rem;
}
.pswdo-final-upload-card .pswdo-doc-head-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    font-size: 1rem;
}
.pswdo-final-upload-card .pswdo-doc-body {
    padding: 0.85rem 1rem;
}
.pswdo-final-upload-card .pswdo-file-box {
    padding: 0.7rem 0.85rem;
    border-radius: 9px;
}
.pswdo-upload-zone-compact {
    padding: 0.62rem 0.75rem;
    margin-bottom: 0.65rem;
    border-width: 1px;
    border-style: solid;
    border-radius: 9px;
    text-align: left;
}
.pswdo-upload-zone-compact .pswdo-upload-zone-content {
    flex-direction: row !important;
    justify-content: flex-start !important;
    gap: 0.65rem;
}
.pswdo-upload-zone-compact .pswdo-upload-icon {
    width: 34px !important;
    height: 34px !important;
    margin-bottom: 0 !important;
    border-radius: 8px !important;
    font-size: 1.05rem !important;
    flex-shrink: 0;
}
.pswdo-upload-zone-compact .pswdo-upload-title {
    margin-bottom: 0 !important;
    font-size: 0.86rem !important;
}
.pswdo-upload-actions-compact {
    display: flex;
    align-items: flex-end;
    gap: 0.8rem;
}
.pswdo-upload-actions-compact .pswdo-upload-confirmations {
    flex: 1 1 auto;
    min-width: 0;
    margin-bottom: 0;
}
.pswdo-upload-actions-compact .pswdo-verify-card {
    margin-bottom: 0.18rem;
    padding: 0.22rem 0.35rem;
    border-color: transparent;
    border-radius: 5px;
    background: transparent;
    gap: 0.45rem;
}
.pswdo-upload-actions-compact .pswdo-verify-card:last-child {
    margin-bottom: 0;
}
.pswdo-upload-actions-compact .pswdo-verify-card .form-check-input {
    width: 0.95rem;
    height: 0.95rem;
    margin-top: 0.12rem;
}
.pswdo-upload-actions-compact .pswdo-verify-label {
    font-size: 0.78rem;
    line-height: 1.3;
}
.pswdo-upload-actions-compact .pswdo-upload-submit {
    flex: 0 0 auto;
    padding: 0.48rem 0.9rem !important;
    white-space: nowrap;
}
.pswdo-step-link {
    color: inherit;
    text-decoration: none !important;
}
.pswdo-step-item.is-selected {
    border-radius: 12px;
    background: #f5f3ff;
}
.pswdo-eclip-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
}
.pswdo-save-state {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: #047857;
    font-size: 0.84rem;
    font-weight: 750;
}
.pswdo-save-state.saving { color: #4338ca; }
.pswdo-save-state.unsaved { color: #b45309; }
.pswdo-eclip-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}
.pswdo-eclip-field-full { grid-column: 1 / -1; }
.pswdo-eclip-label {
    display: block;
    margin-bottom: 0.35rem;
    color: #334155;
    font-size: 0.8rem;
    font-weight: 750;
}
.pswdo-eclip-group {
    margin-bottom: 1.4rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid #e2e8f0;
}
.pswdo-eclip-group:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: 0;
}
.pswdo-eclip-group h3 {
    margin: 0 0 1rem;
    color: #0f172a;
    font-size: 0.95rem;
    font-weight: 800;
}
.pswdo-iif-options {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem 1rem;
    padding: 0.55rem 0.7rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
}
.pswdo-iif-option {
    display: inline-flex;
    align-items: flex-start;
    gap: 0.35rem;
    margin: 0;
    color: #334155;
    font-size: 0.8rem;
    line-height: 1.35;
}
.pswdo-iif-option input { margin-top: 0.15rem; }
.pswdo-iif-question {
    margin-bottom: 1rem;
    padding: 0.9rem;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    background: #fff;
}
.pswdo-iif-question:last-child { margin-bottom: 0; }
.pswdo-iif-question-title {
    display: block;
    margin-bottom: 0.55rem;
    color: #1e293b;
    font-size: 0.82rem;
    font-weight: 800;
}
.pswdo-iif-grid-three {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

@media(max-width: 991px) {
    .pswdo-stepper-list {
        flex-direction: column;
        gap: 1.25rem;
    }
    .pswdo-step-item {
        flex-direction: row;
        text-align: left;
        gap: 1rem;
    }
    .pswdo-step-item:not(:last-child)::after {
        display: none;
    }
    .pswdo-eclip-grid,
    .pswdo-iif-grid-three {
        grid-template-columns: 1fr;
    }
    .pswdo-eclip-field-full {
        grid-column: auto;
    }
    .pswdo-upload-actions-compact {
        align-items: stretch;
        flex-direction: column;
    }
    .pswdo-upload-actions-compact .pswdo-upload-submit {
        align-self: flex-end;
    }
}
</style>
@include('components.processing-workspace.styles')
@endpush

@section('content')
@php
    $fr = $enrollment->surfacedFormerRebel;
    $completedCount = $enrollment->completedDocumentCount();
@endphp
<div class="pswdo-workspace-container">
    {{-- Header Navigation Bar --}}
    <div class="pswdo-nav-bar">
        <a href="{{ route('pswdo.enrollments.show', $enrollment) }}" class="pswdo-back-btn">
            <i class="mdi mdi-arrow-left"></i>
            <span>Back to Surfaced FR Profile</span>
        </a>
        <ul class="pswdo-breadcrumb-trail">
            <li><a href="{{ route('pswdo.dashboard') }}">PSWDO</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li><a href="{{ route('pswdo.enrollments.index') }}">FRs for Enrollment</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li><a href="{{ route('pswdo.enrollments.show', $enrollment) }}">{{ $fr->reference_number }}</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li class="active">Workspace</li>
        </ul>
    </div>

    {{-- Modern Hero Banner --}}
    <div class="pswdo-hero-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="position: relative; z-index: 2; gap: 1rem;">
            <div class="d-flex align-items-center" style="gap: 1.15rem;">
                <div class="pswdo-hero-icon">
                    <i class="mdi mdi-folder-upload"></i>
                </div>
                <div>
                    <div class="pswdo-hero-eyebrow">PSWDO Enrollment Workspace</div>
                    <h1 class="pswdo-hero-title">{{ $fr->reference_number }} &mdash; {{ $fr->display_name }}</h1>
                    <p class="pswdo-hero-sub">Only final completed and signed PDFs prepared outside SHIELD are accepted.</p>
                </div>
            </div>
            <div class="d-flex align-items-center flex-wrap" style="gap: 0.65rem;">
                <div class="badge" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); color: #ffffff; font-size: 0.78rem; font-weight: 750; padding: 0.5rem 0.95rem; border-radius: 9999px;">
                    <i class="mdi {{ $enrollment->isCompleted() ? 'mdi-check-circle' : 'mdi-progress-clock' }} mr-1"></i>
                    <span>{{ $enrollment->isCompleted() ? 'Completed' : 'Intake In Progress' }}</span>
                </div>
                <div class="badge" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; font-size: 0.78rem; font-weight: 750; padding: 0.5rem 0.95rem; border-radius: 9999px;">
                    <i class="mdi mdi-file-document-box-check-outline mr-1"></i>
                    {{ $completedCount }} / 4 Documents
                </div>
            </div>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success d-flex align-items-center mb-4" style="border-radius: 12px; font-weight: 600; gap: 0.65rem; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.12);">
            <i class="mdi mdi-check-circle-outline font-size-20"></i>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-4" style="border-radius: 12px; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.12);">
            <div class="font-weight-bold mb-1 d-flex align-items-center" style="gap: 0.4rem;">
                <i class="mdi mdi-alert-circle-outline font-size-18"></i> Please address the following issues:
            </div>
            <ul class="mb-0 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="process-workspace-grid">
    <main class="process-workspace-main">
    @if($selectedDocument)
        <section class="pswdo-doc-section-card" aria-label="Selected PSWDO document">
            <div class="pswdo-doc-head">
                <div class="pswdo-doc-head-title">
                    <div class="pswdo-doc-head-icon" style="background:#ecfdf5;color:#059669;"><i class="mdi mdi-file-check-outline"></i></div>
                    <div>
                        <span style="font-size:.72rem;font-weight:800;text-transform:uppercase;color:#94a3b8;letter-spacing:.05em;">Confirmed Final</span>
                        <div style="font-size:1rem;font-weight:800;color:#0f172a;">{{ $selectedDocument->document_type->label() }}</div>
                    </div>
                </div>
            </div>
            <div class="pswdo-doc-body">
                <iframe title="{{ $selectedDocument->document_type->label() }}" src="{{ $documentPreviewUrl }}" style="width:100%;min-height:72vh;border:1px solid #e2e8f0;border-radius:10px;background:#fff;"></iframe>
            </div>
        </section>
    @else
    {{-- Visual Flow Stepper --}}
    <div class="pswdo-stepper-box">
        <ol class="pswdo-stepper-list" aria-label="PSWDO enrollment progress">
            @foreach($types as $position => $type)
                @php
                    $done = $enrollment->hasDocument($type);
                    $isActive = ! $done && ($position === 0 || $enrollment->hasDocument($types[$position - 1]));
                @endphp
                <li class="pswdo-step-item {{ $done ? 'is-done' : ($isActive ? 'is-active' : '') }} {{ $activeType === $type ? 'is-selected' : '' }}">
                    <span class="pswdo-step-bubble">
                        {!! $done ? '<i class="mdi mdi-check"></i>' : $position + 1 !!}
                    </span>
                    <a class="pswdo-step-link" href="{{ route('pswdo.enrollments.workspace', ['pswdoEnrollment' => $enrollment, 'document_type' => $type->value]) }}">
                        <strong class="pswdo-step-label">{{ $type->label() }}</strong>
                        <div class="pswdo-step-tag">{{ $done ? 'Completed' : ($isActive ? 'Action Required' : 'Pending') }}</div>
                    </a>
                </li>
            @endforeach
            <li class="pswdo-step-item {{ $enrollment->isCompleted() ? 'is-done' : '' }}">
                <span class="pswdo-step-bubble">
                    {!! $enrollment->isCompleted() ? '<i class="mdi mdi-check"></i>' : '5' !!}
                </span>
                <div>
                    <strong class="pswdo-step-label">Completed</strong>
                    <div class="pswdo-step-tag">{{ $enrollment->isCompleted() ? 'Completed / Complete' : 'Pending' }}</div>
                </div>
            </li>
        </ol>
    </div>

    {{-- Document Cards --}}
    @foreach($types as $index => $type)
        @continue($type !== $activeType)
        @php
            $document = $activeDocument;
            $locked = $type->isEndorsementLetter()
                && ! collect(\App\Enums\PswdoEnrollmentDocumentType::prerequisites())
                    ->every(fn ($required) => $enrollment->hasDocument($required));
            $structuredDraftType = in_array($type, [
                \App\Enums\PswdoEnrollmentDocumentType::EclipEnrollmentForm,
                \App\Enums\PswdoEnrollmentDocumentType::InitialInterviewForm,
            ], true);
        @endphp
        <section id="{{ $type->value }}" class="pswdo-doc-section-card {{ $structuredDraftType ? 'pswdo-final-upload-card' : '' }}">
            <div class="pswdo-doc-head">
                <div class="pswdo-doc-head-title">
                    <div class="pswdo-doc-head-icon" style="background: {{ $document ? '#ecfdf5' : ($locked ? '#f1f5f9' : '#eef2ff') }}; color: {{ $document ? '#059669' : ($locked ? '#94a3b8' : '#4338ca') }};">
                        <i class="mdi {{ $document ? 'mdi-file-check-outline' : ($locked ? 'mdi-lock-outline' : 'mdi-file-upload-outline') }}"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em;">Final-file processing</span>
                        <div style="font-size: 1rem; font-weight: 800; color: #0f172a;">{{ $document ? 'Uploaded Final '.$type->label() : 'Upload Final '.$type->label() }}</div>
                    </div>
                </div>
                <div>
                    @if($document)
                        <span class="pswdo-badge-soft badge-green" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 9999px;">
                            <i class="mdi mdi-check-circle-outline"></i> Completed
                        </span>
                    @elseif($locked)
                        <span class="pswdo-badge-soft badge-gray" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 9999px;">
                            <i class="mdi mdi-lock-outline"></i> Locked
                        </span>
                    @else
                        <span class="pswdo-badge-soft badge-amber" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 9999px;">
                            <i class="mdi mdi-clock-alert-outline"></i> Action Required
                        </span>
                    @endif
                </div>
            </div>

            <div class="pswdo-doc-body">
                @if($document)
                    <div class="pswdo-file-box">
                        <div class="d-flex align-items-center" style="gap: 0.9rem; min-width: 0;">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.45rem; flex-shrink: 0; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.15);">
                                <i class="mdi mdi-file-pdf-box"></i>
                            </div>
                            <div style="min-width: 0;">
                                <div class="font-weight-bold text-truncate" style="color: #0f172a; font-size: 0.925rem;">
                                    {{ $document->original_filename }}
                                </div>
                                <div class="text-muted small" style="margin-top: 2px;">
                                    Uploaded {{ $document->uploaded_at->format('M d, Y h:i A') }} by <span class="font-weight-bold" style="color: #475569;">{{ $document->uploader?->name ?? 'User unavailable' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center flex-shrink-0" style="gap: 0.6rem;">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('pswdo.enrollments.documents.preview', [$enrollment, $document]) }}" style="font-weight: 750; border-radius: 8px; padding: 0.45rem 1rem;">
                                <i class="mdi mdi-eye-outline mr-1"></i> View Uploaded File
                            </a>
                        </div>
                    </div>
                @elseif($locked)
                    <div class="pswdo-lock-banner">
                        <div class="pswdo-lock-icon-wrap">
                            <i class="mdi mdi-lock-outline"></i>
                        </div>
                        <div>
                            <strong style="color: #1f2937; font-size: 0.9rem; display: block; margin-bottom: 0.2rem;">Document Locked</strong>
                            <span>The Endorsement Letter is locked until the E-CLIP Enrollment Form, Initial Interview Form, and Profiling Interview Form are completed.</span>
                        </div>
                    </div>
                @else
                    <form method="POST" action="{{ route('pswdo.enrollments.documents.store', [$enrollment, $type->value]) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="lock_version" value="{{ $enrollment->lock_version }}">
                        
                        {{-- Custom Upload Zone --}}
                        <div class="pswdo-upload-zone {{ $structuredDraftType ? 'pswdo-upload-zone-compact' : '' }}" id="dropzone-{{ $type->value }}">
                            <input id="document-{{ $type->value }}" class="pswdo-file-input" type="file" name="document" accept="application/pdf,.pdf" required onchange="handleFileSelected(this, '{{ $type->value }}')">
                            <div class="pswdo-upload-zone-content d-flex flex-column align-items-center justify-content-center">
                                <div class="pswdo-upload-icon" style="width: 48px; height: 48px; border-radius: 50%; background: #eef2ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 0.65rem;">
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                </div>
                                <div>
                                    <div class="pswdo-upload-title font-weight-bold" style="color: #0f172a; font-size: 0.95rem; margin-bottom: 0.2rem;" id="filename-display-{{ $type->value }}">
                                        Choose or drag final signed PDF here
                                    </div>
                                    <div class="text-muted small">PDF only, maximum 20 MB</div>
                                </div>
                            </div>
                        </div>

                        <div class="{{ $structuredDraftType ? 'pswdo-upload-actions-compact' : '' }}">
                            <div class="pswdo-upload-confirmations mb-3">
                                @unless($structuredDraftType)
                                    <div class="small font-weight-bold text-uppercase text-muted mb-2" style="letter-spacing: 0.05em; font-size: 0.72rem;">
                                        Mandatory Verification Checklist
                                    </div>
                                @endunless

                                <label class="pswdo-verify-card" for="type-{{ $type->value }}">
                                    <input id="type-{{ $type->value }}" class="form-check-input" type="checkbox" name="correct_document_type_confirmed" value="1" required onchange="toggleVerifyCard(this)">
                                    <span class="pswdo-verify-label">I confirm this is the correct <strong>{{ $type->label() }}</strong>.</span>
                                </label>

                                <label class="pswdo-verify-card" for="fr-{{ $type->value }}">
                                    <input id="fr-{{ $type->value }}" class="form-check-input" type="checkbox" name="belongs_to_fr_confirmed" value="1" required onchange="toggleVerifyCard(this)">
                                    <span class="pswdo-verify-label">I confirm this document belongs to <strong>{{ $fr->reference_number }} &mdash; {{ $fr->display_name }}</strong>.</span>
                                </label>

                                <label class="pswdo-verify-card" for="final-{{ $type->value }}">
                                    <input id="final-{{ $type->value }}" class="form-check-input" type="checkbox" name="final_signed_confirmed" value="1" required onchange="toggleVerifyCard(this)">
                                    <span class="pswdo-verify-label">I confirm this is the final completed and signed document. SHIELD does not validate handwritten signatures.</span>
                                </label>
                            </div>

                            <button class="pswdo-upload-submit btn btn-action-primary" type="submit" style="background: #059669; border: none; font-weight: 750; padding: 0.6rem 1.4rem; border-radius: 9px; box-shadow: 0 2px 8px rgba(5, 150, 105, 0.25);">
                                <i class="mdi mdi-upload mr-1"></i> Upload Final
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </section>

        @if($type === \App\Enums\PswdoEnrollmentDocumentType::EclipEnrollmentForm)
            <section class="pswdo-eclip-toolbar" aria-label="E-CLIP drafting controls">
                <div class="pswdo-save-state {{ $eclipReadOnly ? '' : 'saved' }}" id="pswdoEclipSaveState" aria-live="polite">
                    <i class="mdi {{ $eclipReadOnly ? 'mdi-lock-outline' : 'mdi-cloud-check' }}" id="pswdoEclipSaveIcon"></i>
                    <span id="pswdoEclipSaveText">{{ $eclipReadOnly ? 'Completed / Read-only' : 'All changes saved' }}</span>
                </div>
                <a id="pswdoEclipPreview"
                   class="btn btn-outline-primary btn-sm {{ $eclipDraft ? '' : 'disabled' }}"
                   href="{{ route('pswdo.enrollments.eclip-draft.preview', $enrollment) }}"
                   target="_blank" rel="noopener"
                   @unless($eclipDraft) aria-disabled="true" tabindex="-1" @endunless>
                    <i class="mdi mdi-eye-outline mr-1"></i> Preview Draft
                </a>
            </section>

            <section class="pswdo-doc-section-card" aria-label="E-CLIP Enrollment Form drafting area">
                <div class="pswdo-doc-head">
                    <div class="pswdo-doc-head-title">
                        <div class="pswdo-doc-head-icon" style="background:#eef2ff;color:#4338ca;"><i class="mdi mdi-file-document-edit-outline"></i></div>
                        <div>
                            <span style="font-size:.72rem;font-weight:800;text-transform:uppercase;color:#94a3b8;letter-spacing:.05em;">Structured official draft</span>
                            <div style="font-size:1rem;font-weight:800;color:#0f172a;">E-CLIP Enrollment Form</div>
                        </div>
                    </div>
                </div>
                <div class="pswdo-doc-body">
                    <form id="pswdoEclipDraftForm"
                          data-editable="{{ $canEditEclipDraft ? 'true' : 'false' }}"
                          @if($canEditEclipDraft) method="POST" action="{{ route('pswdo.enrollments.eclip-draft.update', $enrollment) }}" @endif>
                        @if($canEditEclipDraft)
                            @csrf
                            @method('PUT')
                            <input id="pswdoEclipRevision" type="hidden" name="revision" value="{{ $eclipDraft?->revision ?? 0 }}">
                        @else
                            <fieldset disabled aria-label="Completed E-CLIP Enrollment Form draft">
                        @endif

                        <div class="pswdo-eclip-group">
                            <h3>Person and Certification Information</h3>
                            <div class="pswdo-eclip-grid">
                                @foreach([
                                    'last_name' => ['Last Name', 'text', 100],
                                    'first_name' => ['First Name', 'text', 100],
                                    'middle_name' => ['Middle Name', 'text', 100],
                                    'address' => ['Address', 'text', 1000],
                                    'reintegration_monitoring_number' => ['Reintegration Monitoring (RM) No.', 'text', 100],
                                    'japic_validation_date' => ['JAPIC validation/authentication date', 'date', null],
                                ] as $field => [$label, $inputType, $max])
                                    <div class="{{ $field === 'address' ? 'pswdo-eclip-field-full' : '' }}">
                                        <label class="pswdo-eclip-label" for="eclip-{{ $field }}">{{ $label }}</label>
                                        <input class="form-control" id="eclip-{{ $field }}" type="{{ $inputType }}" name="draft[{{ $field }}]"
                                               value="{{ old('draft.'.$field, $eclipPayload[$field]) }}"
                                               @if($max) maxlength="{{ $max }}" @endif
                                               @if(in_array($field, ['last_name', 'first_name', 'address'], true)) required @endif>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="pswdo-eclip-group">
                            <h3>Other Agencies Involved</h3>
                            <div class="pswdo-eclip-grid">
                                <div>
                                    <label class="pswdo-eclip-label" for="eclip-cso">Civil Society Organization; please specify</label>
                                    <input class="form-control" id="eclip-cso" name="draft[civil_society_organization]" maxlength="500" value="{{ old('draft.civil_society_organization', $eclipPayload['civil_society_organization']) }}">
                                </div>
                                <div>
                                    <label class="pswdo-eclip-label" for="eclip-government-agency">Other government agency; please specify</label>
                                    <input class="form-control" id="eclip-government-agency" name="draft[other_government_agency]" maxlength="500" value="{{ old('draft.other_government_agency', $eclipPayload['other_government_agency']) }}">
                                </div>
                                <div class="pswdo-eclip-field-full">
                                    <label class="pswdo-eclip-label" for="eclip-remarks">Remarks, if any</label>
                                    <textarea class="form-control" id="eclip-remarks" name="draft[remarks]" rows="3" maxlength="4000">{{ old('draft.remarks', $eclipPayload['remarks']) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="pswdo-eclip-group">
                            <h3>Firearm Details</h3>
                            <div class="pswdo-eclip-grid">
                                @foreach([
                                    'firearm_type' => ['Type of Firearm/s', 500],
                                    'caliber' => ['Caliber', 255],
                                    'make' => ['Make', 255],
                                    'serial_number' => ['Serial Number', 255],
                                ] as $field => [$label, $max])
                                    <div>
                                        <label class="pswdo-eclip-label" for="eclip-{{ $field }}">{{ $label }}</label>
                                        <input class="form-control" id="eclip-{{ $field }}" name="draft[{{ $field }}]" maxlength="{{ $max }}" value="{{ old('draft.'.$field, $eclipPayload[$field]) }}">
                                    </div>
                                @endforeach
                                <div class="pswdo-eclip-field-full">
                                    <label class="pswdo-eclip-label" for="eclip-firearm-remarks">Remarks</label>
                                    <textarea class="form-control" id="eclip-firearm-remarks" name="draft[firearm_remarks]" rows="2" maxlength="1000">{{ old('draft.firearm_remarks', $eclipPayload['firearm_remarks']) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="pswdo-eclip-group">
                            <h3>Issuance Information</h3>
                            <div class="pswdo-eclip-grid">
                                <div>
                                    <label class="pswdo-eclip-label" for="eclip-date-of-issuance">Date of Issuance</label>
                                    <input class="form-control" id="eclip-date-of-issuance" type="date" name="draft[date_of_issuance]" value="{{ old('draft.date_of_issuance', $eclipPayload['date_of_issuance']) }}">
                                </div>
                                <div>
                                    <label class="pswdo-eclip-label" for="eclip-place-of-issuance">Place of Issuance</label>
                                    <input class="form-control" id="eclip-place-of-issuance" name="draft[place_of_issuance]" maxlength="500" value="{{ old('draft.place_of_issuance', $eclipPayload['place_of_issuance']) }}">
                                </div>
                            </div>
                        </div>

                        @unless($canEditEclipDraft)
                            </fieldset>
                        @else
                            <div class="text-right">
                                <button class="btn btn-primary" type="submit"><i class="mdi mdi-content-save mr-1"></i> Save Draft</button>
                            </div>
                        @endunless
                    </form>
                </div>
            </section>
        @elseif($type === \App\Enums\PswdoEnrollmentDocumentType::InitialInterviewForm)
            @php
                $iifValue = fn(string $field, mixed $default = '') => old('draft.'.$field, $initialInterviewPayload[$field] ?? $default);
                $iifArray = fn(string $field) => (array) old('draft.'.$field, $initialInterviewPayload[$field] ?? []);
                $yesNoOptions = ['yes' => 'Yes', 'no' => 'No'];
                $frequencyOptions = ['never' => 'Never', 'rarely' => 'Rarely', 'sometimes' => 'Sometimes', 'often' => 'Often', 'always' => 'Always'];
            @endphp
            <section class="pswdo-eclip-toolbar" aria-label="Initial Interview Form drafting controls">
                <div class="pswdo-save-state {{ $initialInterviewReadOnly ? '' : 'saved' }}" id="pswdoIifSaveState" aria-live="polite">
                    <i class="mdi {{ $initialInterviewReadOnly ? 'mdi-lock-outline' : 'mdi-cloud-check' }}" id="pswdoIifSaveIcon"></i>
                    <span id="pswdoIifSaveText">{{ $initialInterviewReadOnly ? 'Completed / Read-only' : 'All changes saved' }}</span>
                </div>
                <a id="pswdoIifPreview"
                   class="btn btn-outline-primary btn-sm {{ $initialInterviewDraft ? '' : 'disabled' }}"
                   href="{{ route('pswdo.enrollments.initial-interview-draft.preview', $enrollment) }}"
                   target="_blank" rel="noopener"
                   @unless($initialInterviewDraft) aria-disabled="true" tabindex="-1" @endunless>
                    <i class="mdi mdi-eye-outline mr-1"></i> Preview Draft
                </a>
            </section>

            <section class="pswdo-doc-section-card" aria-label="Initial Interview Form drafting area">
                <div class="pswdo-doc-head">
                    <div class="pswdo-doc-head-title">
                        <div class="pswdo-doc-head-icon" style="background:#eef2ff;color:#4338ca;"><i class="mdi mdi-file-document-edit-outline"></i></div>
                        <div>
                            <span style="font-size:.72rem;font-weight:800;text-transform:uppercase;color:#94a3b8;letter-spacing:.05em;">Structured official draft</span>
                            <div style="font-size:1rem;font-weight:800;color:#0f172a;">Initial Interview Form (35 minutes)</div>
                        </div>
                    </div>
                </div>
                <div class="pswdo-doc-body">
                    <form id="pswdoIifDraftForm"
                          data-editable="{{ $canEditInitialInterviewDraft ? 'true' : 'false' }}"
                          @if($canEditInitialInterviewDraft) method="POST" action="{{ route('pswdo.enrollments.initial-interview-draft.update', $enrollment) }}" @endif>
                        @if($canEditInitialInterviewDraft)
                            @csrf
                            @method('PUT')
                            <input id="pswdoIifRevision" type="hidden" name="revision" value="{{ $initialInterviewDraft?->revision ?? 0 }}">
                        @else
                            <fieldset disabled aria-label="Completed Initial Interview Form draft">
                        @endif

                        <div class="pswdo-eclip-group">
                            <h3>Header / Interview Information</h3>
                            <div class="pswdo-eclip-grid">
                                @foreach([
                                    'as_of_date' => ['As of date', 'date', 0],
                                    'interviewer_name' => ['Name of Interviewer', 'text', 500],
                                    'interviewer_office_designation' => ['Office and Designation', 'text', 500],
                                    'interview_date' => ['Date of Interview', 'date', 0],
                                    'submission_date' => ['Date of Submission', 'date', 0],
                                    'conducting_entity' => ['Conducting office/entity', 'text', 500],
                                    'encoder_name' => ['Name of Encoder', 'text', 500],
                                    'encoder_office_designation' => ['Encoder Office and Designation', 'text', 500],
                                    'date_encoded' => ['Date Encoded', 'date', 0],
                                ] as $field => [$label, $inputType, $max])
                                    <div class="{{ in_array($field, ['interviewer_office_designation', 'conducting_entity', 'encoder_office_designation'], true) ? 'pswdo-eclip-field-full' : '' }}">
                                        <label class="pswdo-eclip-label" for="iif-{{ $field }}">{{ $label }}</label>
                                        <input class="form-control" id="iif-{{ $field }}" type="{{ $inputType }}" name="draft[{{ $field }}]" value="{{ $iifValue($field) }}" @if($max) maxlength="{{ $max }}" @endif>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="pswdo-eclip-group">
                            <h3>PART I: PROFILE OF RESPONDENT (3 minutes)</h3>
                            <div class="pswdo-iif-grid-three mb-3">
                                @foreach(['last_name' => 'Last Name', 'first_name' => 'First Name', 'middle_name' => 'Middle Name'] as $field => $label)
                                    <div>
                                        <label class="pswdo-eclip-label" for="iif-{{ $field }}">1. {{ $label }}</label>
                                        <input class="form-control" id="iif-{{ $field }}" name="draft[{{ $field }}]" maxlength="100" value="{{ $iifValue($field) }}">
                                    </div>
                                @endforeach
                            </div>
                            <div class="pswdo-eclip-grid">
                                <div>
                                    <label class="pswdo-eclip-label" for="iif-alias">2. Alias</label>
                                    <input class="form-control" id="iif-alias" name="draft[alias]" maxlength="500" value="{{ $iifValue('alias') }}">
                                </div>
                                <div>
                                    <span class="pswdo-eclip-label">3. Sex</span>
                                    <div class="pswdo-iif-options">
                                        @foreach(['male' => 'Male', 'female' => 'Female'] as $value => $label)
                                            <label class="pswdo-iif-option"><input type="radio" name="draft[sex]" value="{{ $value }}" @checked($iifValue('sex') === $value)> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="pswdo-eclip-label" for="iif-birthdate">4. Birthdate</label>
                                    <input class="form-control" id="iif-birthdate" type="date" name="draft[birthdate]" value="{{ $iifValue('birthdate') }}">
                                </div>
                                <div>
                                    <label class="pswdo-eclip-label" for="iif-birthplace">5. Place of birth</label>
                                    <input class="form-control" id="iif-birthplace" name="draft[birthplace]" maxlength="1000" value="{{ $iifValue('birthplace') }}">
                                </div>
                                <div class="pswdo-eclip-field-full">
                                    <span class="pswdo-eclip-label">6. Civil Status</span>
                                    <div class="pswdo-iif-options">
                                        @foreach(['single' => 'Single', 'married' => 'Married', 'widow_widower' => 'Widow/Widower', 'separated' => 'Separated', 'common_law_partner' => 'Common Law/Partner', 'others' => 'Others'] as $value => $label)
                                            <label class="pswdo-iif-option"><input type="radio" name="draft[civil_status]" value="{{ $value }}" @checked($iifValue('civil_status') === $value)> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <input class="form-control mt-2" name="draft[civil_status_other]" maxlength="500" value="{{ $iifValue('civil_status_other') }}" placeholder="If Others, please specify">
                                </div>
                                <div>
                                    <span class="pswdo-eclip-label">7. Do you belong to a tribal group? (Katubo/Tribo)</span>
                                    <div class="pswdo-iif-options">
                                        @foreach($yesNoOptions as $value => $label)
                                            <label class="pswdo-iif-option"><input type="radio" name="draft[tribal_group]" value="{{ $value }}" @checked($iifValue('tribal_group') === $value)> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <input class="form-control mt-2" name="draft[tribal_group_name]" maxlength="500" value="{{ $iifValue('tribal_group_name') }}" placeholder="If Yes, main group/specification">
                                </div>
                                <div>
                                    <label class="pswdo-eclip-label" for="iif-religion">8. Religion</label>
                                    <input class="form-control" id="iif-religion" name="draft[religion]" maxlength="500" value="{{ $iifValue('religion') }}">
                                </div>
                            </div>
                        </div>

                        <div class="pswdo-eclip-group">
                            <h3>PART II: HISTORY IN THE ARMED MOVEMENT (12 minutes)</h3>
                            <div class="pswdo-eclip-grid">
                                <div>
                                    <label class="pswdo-eclip-label" for="iif-movement-years">9. Total number of years in the movement</label>
                                    <input class="form-control" id="iif-movement-years" type="number" min="0" max="100" name="draft[movement_years]" value="{{ $iifValue('movement_years') }}">
                                </div>
                                <div>
                                    <label class="pswdo-eclip-label" for="iif-entry-age">10. Age at entry in the movement</label>
                                    <input class="form-control" id="iif-entry-age" type="number" min="0" max="120" name="draft[entry_age]" value="{{ $iifValue('entry_age') }}">
                                </div>
                                @foreach([
                                    'reasons_joining' => '11. What are your reasons for joining the movement?',
                                    'reasons_staying' => '12. What are your reasons for staying in the movement?',
                                    'position_before_leaving' => '13. What was your position in the movement before you left?',
                                    'unit_before_leaving' => '14. What was your unit in the movement before you left?',
                                    'areas_of_operation' => '15. What were the covered geographical areas of operation of your unit?',
                                ] as $field => $label)
                                    <div class="{{ in_array($field, ['reasons_joining', 'reasons_staying', 'areas_of_operation'], true) ? 'pswdo-eclip-field-full' : '' }}">
                                        <label class="pswdo-eclip-label" for="iif-{{ $field }}">{{ $label }}</label>
                                        @if(in_array($field, ['reasons_joining', 'reasons_staying', 'areas_of_operation'], true))
                                            <textarea class="form-control" id="iif-{{ $field }}" name="draft[{{ $field }}]" rows="3" maxlength="4000">{{ $iifValue($field) }}</textarea>
                                        @else
                                            <input class="form-control" id="iif-{{ $field }}" name="draft[{{ $field }}]" maxlength="500" value="{{ $iifValue($field) }}">
                                        @endif
                                    </div>
                                @endforeach
                                <div class="pswdo-eclip-field-full pswdo-iif-question">
                                    <span class="pswdo-iif-question-title">16. Did you experience any unfair, unequal, or inhumane treatment while you were in the movement?</span>
                                    <div class="pswdo-iif-options mb-2">
                                        @foreach($yesNoOptions as $value => $label)
                                            <label class="pswdo-iif-option"><input type="radio" name="draft[unfair_treatment]" value="{{ $value }}" @checked($iifValue('unfair_treatment') === $value)> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <textarea class="form-control" name="draft[unfair_treatment_details]" rows="3" maxlength="4000" placeholder="If Yes, detail experience/s">{{ $iifValue('unfair_treatment_details') }}</textarea>
                                </div>
                                <div class="pswdo-eclip-field-full">
                                    <label class="pswdo-eclip-label" for="iif-reasons-leaving">17. What are your reasons for leaving the movement?</label>
                                    <textarea class="form-control" id="iif-reasons-leaving" name="draft[reasons_leaving]" rows="3" maxlength="4000">{{ $iifValue('reasons_leaving') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="pswdo-eclip-group">
                            <h3>PART III: SECURITY ASSESSMENT (10 minutes)</h3>
                            @foreach([
                                'firearms' => ['18. Did you have firearms when you were in the movement?', '(please accomplish Firearms Inventory Form)'],
                                'explosives' => ['19. Did you have explosives when you were in the movement?', '(please accomplish Explosives Inventory Form)'],
                            ] as $prefix => [$question, $note])
                                <div class="pswdo-iif-question">
                                    <span class="pswdo-iif-question-title">{{ $question }} <em>{{ $note }}</em></span>
                                    <div class="pswdo-iif-options mb-2">
                                        @foreach($yesNoOptions as $value => $label)
                                            <label class="pswdo-iif-option"><input type="radio" name="draft[{{ $prefix }}_had]" value="{{ $value }}" @checked($iifValue($prefix.'_had') === $value)> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <span class="pswdo-eclip-label">If Yes, did you bring it/them when you left the movement?</span>
                                    <div class="pswdo-iif-options mb-2">
                                        @foreach($yesNoOptions as $value => $label)
                                            <label class="pswdo-iif-option"><input type="radio" name="draft[{{ $prefix }}_brought]" value="{{ $value }}" @checked($iifValue($prefix.'_brought') === $value)> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <span class="pswdo-eclip-label">Have you turned it/them in?</span>
                                    <div class="pswdo-iif-options mb-2">
                                        @foreach($yesNoOptions as $value => $label)
                                            <label class="pswdo-iif-option"><input type="radio" name="draft[{{ $prefix }}_turned_in]" value="{{ $value }}" @checked($iifValue($prefix.'_turned_in') === $value)> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <div class="pswdo-eclip-grid">
                                        <div><label class="pswdo-eclip-label">If yes, to who?</label><input class="form-control" name="draft[{{ $prefix }}_turned_in_to]" maxlength="500" value="{{ $iifValue($prefix.'_turned_in_to') }}"></div>
                                        <div><label class="pswdo-eclip-label">If no, why?</label><input class="form-control" name="draft[{{ $prefix }}_not_turned_in_reason]" maxlength="4000" value="{{ $iifValue($prefix.'_not_turned_in_reason') }}"></div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="pswdo-iif-question">
                                <span class="pswdo-iif-question-title">20. Where are you currently staying?</span>
                                <div class="pswdo-eclip-grid">
                                    @foreach([
                                        'current_street' => 'No. & Street', 'current_sitio' => 'Sitio', 'current_barangay' => 'Barangay',
                                        'current_municipality_city' => 'Municipality/City', 'current_province' => 'Province',
                                        'current_psgc_barangay_code' => 'Philippine Standard Geographic Code (PSGC) - Barangay Code',
                                    ] as $field => $label)
                                        <div class="{{ $field === 'current_psgc_barangay_code' ? 'pswdo-eclip-field-full' : '' }}"><label class="pswdo-eclip-label">{{ $label }}</label><input class="form-control" name="draft[{{ $field }}]" maxlength="500" value="{{ $iifValue($field) }}"></div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="pswdo-iif-question">
                                <span class="pswdo-iif-question-title">21. How long have you been staying in this location?</span>
                                <div class="pswdo-eclip-grid"><div><label class="pswdo-eclip-label">Years</label><input class="form-control" type="number" min="0" max="100" name="draft[stay_years]" value="{{ $iifValue('stay_years') }}"></div><div><label class="pswdo-eclip-label">Months</label><input class="form-control" type="number" min="0" max="11" name="draft[stay_months]" value="{{ $iifValue('stay_months') }}"></div></div>
                            </div>
                            <div class="pswdo-iif-question">
                                <span class="pswdo-iif-question-title">22. Where does your family reside?</span>
                                <div class="pswdo-eclip-grid">
                                    @foreach(['family_street' => 'No. & Street', 'family_sitio' => 'Sitio', 'family_barangay' => 'Barangay', 'family_municipality_city' => 'Municipality/City', 'family_province' => 'Province', 'family_contact_information' => 'Contact Information'] as $field => $label)
                                        <div><label class="pswdo-eclip-label">{{ $label }}</label><input class="form-control" name="draft[{{ $field }}]" maxlength="500" value="{{ $iifValue($field) }}"></div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="pswdo-iif-question">
                                <span class="pswdo-iif-question-title">23. Contact person (in case of emergency)</span>
                                <div class="pswdo-eclip-grid">
                                    @foreach(['emergency_contact_name' => 'Name', 'emergency_contact_relationship' => 'Relationship', 'emergency_contact_information' => 'Contact Information', 'emergency_contact_address' => 'Address'] as $field => $label)
                                        <div class="{{ $field === 'emergency_contact_address' ? 'pswdo-eclip-field-full' : '' }}"><label class="pswdo-eclip-label">{{ $label }}</label><input class="form-control" name="draft[{{ $field }}]" maxlength="1000" value="{{ $iifValue($field) }}"></div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="pswdo-iif-question">
                                <span class="pswdo-iif-question-title">24. If you are not currently living with your family, to what extent are the following reasons true?</span>
                                <div class="pswdo-iif-options">
                                    @foreach(['unsafe_living_there' => 'I do not feel safe living there.', 'endanger_family' => 'My presence at home might endanger my family.', 'lack_basic_needs' => 'We do not have access to basic needs there.', 'no_transport' => 'I have no means of traveling back to my primary address.', 'family_conflict' => 'I am not on good terms with my family.', 'others' => 'Others'] as $value => $label)
                                        <label class="pswdo-iif-option"><input type="checkbox" name="draft[current_separation_reasons][]" value="{{ $value }}" @checked(in_array($value, $iifArray('current_separation_reasons'), true))> {{ $label }}</label>
                                    @endforeach
                                </div>
                                <input class="form-control mt-2" name="draft[current_separation_other]" maxlength="500" value="{{ $iifValue('current_separation_other') }}" placeholder="Others specification">
                            </div>
                            <div class="pswdo-iif-question">
                                <span class="pswdo-iif-question-title">25. Do you have plans of relocating in the future?</span>
                                <div class="pswdo-iif-options mb-2">@foreach($yesNoOptions as $value => $label)<label class="pswdo-iif-option"><input type="radio" name="draft[relocate_plans]" value="{{ $value }}" @checked($iifValue('relocate_plans') === $value)> {{ $label }}</label>@endforeach</div>
                                <span class="pswdo-eclip-label">a. Within the same municipality?</span>
                                <div class="pswdo-iif-options mb-2">@foreach($yesNoOptions as $value => $label)<label class="pswdo-iif-option"><input type="radio" name="draft[relocate_same_municipality]" value="{{ $value }}" @checked($iifValue('relocate_same_municipality') === $value)> {{ $label }}</label>@endforeach</div>
                                <input class="form-control mb-2" name="draft[relocate_where]" maxlength="500" value="{{ $iifValue('relocate_where') }}" placeholder="If No, where?">
                                <span class="pswdo-eclip-label">b. Reasons for intending to relocate</span>
                                <div class="pswdo-iif-options">
                                    @foreach(['lack_security' => 'Lack of security', 'no_livelihood' => 'No livelihood opportunity', 'hazardous_location' => 'Hazardous location (i.e., flood-prone, landslide risk, etc.)', 'poor_living_conditions' => 'Poor living conditions', 'others' => 'Others'] as $value => $label)
                                        <label class="pswdo-iif-option"><input type="checkbox" name="draft[relocation_reasons][]" value="{{ $value }}" @checked(in_array($value, $iifArray('relocation_reasons'), true))> {{ $label }}</label>
                                    @endforeach
                                </div>
                                <input class="form-control mt-2" name="draft[relocation_other]" maxlength="500" value="{{ $iifValue('relocation_other') }}" placeholder="Others specification">
                            </div>

                            @foreach([
                                ['number' => 26, 'field' => 'respondent_safety', 'question' => 'How safe do you feel at your present address?', 'family' => false],
                                ['number' => 28, 'field' => 'family_safety', 'question' => 'How safe is your family in their present address?', 'family' => true],
                            ] as $safety)
                                <div class="pswdo-iif-question">
                                    <span class="pswdo-iif-question-title">{{ $safety['number'] }}. {{ $safety['question'] }}</span>
                                    <div class="pswdo-iif-options">
                                        @foreach([
                                            'high_threat' => $safety['family'] ? 'A high threat, fear for their lives' : 'A high threat, fear for life',
                                            'considerable_threat' => 'A considerable threat, limited movement in the community',
                                            'threat_avoid_areas' => 'With threat, avoid certain areas',
                                            'little_threat' => 'Little threat, but can freely move around',
                                            'no_threat' => 'No threat and can freely move around',
                                        ] as $value => $label)
                                            <label class="pswdo-iif-option"><input type="radio" name="draft[{{ $safety['field'] }}]" value="{{ $value }}" @checked($iifValue($safety['field']) === $value)> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            @foreach([
                                ['number' => 27, 'field' => 'respondent_threat_sources', 'other' => 'respondent_threat_other', 'question' => 'If there is a threat to your life, what is/are the source/s of threat?'],
                                ['number' => 29, 'field' => 'family_threat_sources', 'other' => 'family_threat_other', 'question' => "If there is threat to your family's life, what is/are the source/s of threat?"],
                            ] as $threat)
                                <div class="pswdo-iif-question">
                                    <span class="pswdo-iif-question-title">{{ $threat['number'] }}. {{ $threat['question'] }}</span>
                                    <div class="pswdo-iif-options">
                                        @foreach(['former_comrades' => 'Former Comrades', 'mass_base_members' => 'Mass Base members', 'private_armed_groups' => 'Private Armed Groups', 'criminal_groups' => 'Criminal Groups', 'neighbors' => 'Neighbors', 'adjacent_communities' => 'Adjacent Communities', 'others' => 'Others'] as $value => $label)
                                            <label class="pswdo-iif-option"><input type="checkbox" name="draft[{{ $threat['field'] }}][]" value="{{ $value }}" @checked(in_array($value, $iifArray($threat['field']), true))> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <input class="form-control mt-2" name="draft[{{ $threat['other'] }}]" maxlength="500" value="{{ $iifValue($threat['other']) }}" placeholder="Others specification">
                                </div>
                            @endforeach
                        </div>

                        <div class="pswdo-eclip-group">
                            <h3>PART IV: IMMEDIATE NEEDS ASSESSMENT (10 minutes)</h3>
                            <div class="pswdo-iif-question">
                                <span class="pswdo-iif-question-title">30. Do you have any of the following disabilities?</span>
                                @foreach([
                                    'visual_impairment' => 'Visual Impairment (Partial or Full) or both eyes',
                                    'hearing_impairment' => 'Hearing Impairment (Slight or Full) or both ears',
                                    'speech_impairment' => 'Speech Impairment (Slight or Full)',
                                    'physical_disabilities' => 'Physical Disabilities',
                                    'other_disabilities' => 'Other Disabilities',
                                ] as $field => $label)
                                    <div class="pswdo-eclip-grid mb-2">
                                        <div><span class="pswdo-eclip-label">{{ $label }}</span><div class="pswdo-iif-options">@foreach($yesNoOptions as $value => $optionLabel)<label class="pswdo-iif-option"><input type="radio" name="draft[{{ $field }}]" value="{{ $value }}" @checked($iifValue($field) === $value)> {{ $optionLabel }}</label>@endforeach</div></div>
                                        <div><label class="pswdo-eclip-label">If yes, please specify</label><input class="form-control" name="draft[{{ $field }}_details]" maxlength="500" value="{{ $iifValue($field.'_details') }}"></div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="pswdo-iif-question">
                                <span class="pswdo-iif-question-title">31. Have you received any of the following assistance?</span>
                                <div class="mb-3">
                                    <span class="pswdo-eclip-label">Medical Care</span>
                                    <div class="pswdo-iif-options mb-2">@foreach($yesNoOptions as $value => $label)<label class="pswdo-iif-option"><input type="radio" name="draft[medical_received]" value="{{ $value }}" @checked($iifValue('medical_received') === $value)> {{ $label }}</label>@endforeach</div>
                                    <div class="pswdo-eclip-grid"><div><label class="pswdo-eclip-label">If yes, number of times in past 3 months</label><input class="form-control" type="number" min="0" max="999" name="draft[medical_times]" value="{{ $iifValue('medical_times') }}"></div><div><label class="pswdo-eclip-label">For what condition/s</label><input class="form-control" name="draft[medical_conditions]" maxlength="4000" value="{{ $iifValue('medical_conditions') }}"></div></div>
                                </div>
                                @foreach([
                                    'board_lodging' => 'Board and Lodging', 'food' => 'Food', 'transport' => 'Transportation', 'psychosocial' => 'Psychosocial Debriefing',
                                ] as $prefix => $label)
                                    <div class="mb-3">
                                        <span class="pswdo-eclip-label">{{ $label }}</span>
                                        <div class="pswdo-iif-options mb-2">@foreach($yesNoOptions as $value => $optionLabel)<label class="pswdo-iif-option"><input type="radio" name="draft[{{ $prefix }}_received]" value="{{ $value }}" @checked($iifValue($prefix.'_received') === $value)> {{ $optionLabel }}</label>@endforeach</div>
                                        <div class="pswdo-iif-options">@foreach(['lgu' => 'LGU', 'afp_pnp' => 'AFP/PNP', 'others' => 'Others'] as $value => $sourceLabel)<label class="pswdo-iif-option"><input type="checkbox" name="draft[{{ $prefix }}_sources][]" value="{{ $value }}" @checked(in_array($value, $iifArray($prefix.'_sources'), true))> {{ $sourceLabel }}</label>@endforeach</div>
                                        <input class="form-control mt-2" name="draft[{{ $prefix }}_other]" maxlength="500" value="{{ $iifValue($prefix.'_other') }}" placeholder="Others specification">
                                    </div>
                                @endforeach
                                <label class="pswdo-eclip-label" for="iif-assistance-other">Others</label>
                                <textarea class="form-control" id="iif-assistance-other" name="draft[assistance_other]" rows="2" maxlength="4000">{{ $iifValue('assistance_other') }}</textarea>
                            </div>

                            <div class="pswdo-iif-question">
                                <span class="pswdo-iif-question-title">32. Have you been experiencing the following in the past 3 months?</span>
                                @foreach([
                                    'difficulty_sleeping' => 'Difficulty Sleeping / Bad dreams',
                                    'anxiety' => 'Anxiety',
                                    'addictive_substances' => 'Consumption of addictive substances, alcoholic beverages, or cigarettes',
                                    'difficulty_concentrating' => 'Difficulty concentrating and/or absent-mindedness',
                                    'disengaged_environment' => 'Disengaged from environment',
                                    'panic_attacks' => 'Panic Attacks',
                                    'avoidance_people_places' => 'Avoidance of certain people or places',
                                    'trusting_others' => 'Difficulty trusting others',
                                    'remembering_violent_incidents' => 'Remembering violent incidents',
                                    'violent_thoughts' => 'Violent thoughts',
                                    'irritability_anger' => 'Constant Irritability or Anger',
                                    'feelings_guilt' => 'Feelings of guilt',
                                ] as $field => $label)
                                    <div class="mb-3">
                                        <span class="pswdo-eclip-label">{{ $label }}</span>
                                        <div class="pswdo-iif-options">@foreach($frequencyOptions as $value => $optionLabel)<label class="pswdo-iif-option"><input type="radio" name="draft[{{ $field }}]" value="{{ $value }}" @checked($iifValue($field) === $value)> {{ $optionLabel }}</label>@endforeach</div>
                                        @if($field === 'avoidance_people_places')
                                            <input class="form-control mt-2" name="draft[avoidance_who]" maxlength="500" value="{{ $iifValue('avoidance_who') }}" placeholder="Who">
                                        @elseif($field === 'trusting_others')
                                            <input class="form-control mt-2" name="draft[trusting_who]" maxlength="500" value="{{ $iifValue('trusting_who') }}" placeholder="Who">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @unless($canEditInitialInterviewDraft)
                            </fieldset>
                        @else
                            <div class="text-right">
                                <button class="btn btn-primary" type="submit"><i class="mdi mdi-content-save mr-1"></i> Save Draft</button>
                            </div>
                        @endunless
                    </form>
                </div>
            </section>
        @endif
    @endforeach
    @endif
    </main>
    @include('components.processing-workspace.sidebar', ['sidebarId' => 'pswdo', 'comments' => $enrollment->comments, 'events' => $workspaceEvents, 'commentAction' => route('pswdo.enrollments.comments.store', $enrollment), 'canComment' => $canComment])
    </div>
</div>

<script>
function handleFileSelected(input, type) {
    const display = document.getElementById('filename-display-' + type);
    if (input.files && input.files[0]) {
        display.innerHTML = '<span class="text-primary"><i class="mdi mdi-file-pdf-box mr-1"></i> ' + input.files[0].name + '</span>';
        input.parentElement.style.borderColor = '#10b981';
        input.parentElement.style.background = '#ecfdf5';
    }
}

function toggleVerifyCard(checkbox) {
    const card = checkbox.closest('.pswdo-verify-card');
    if (checkbox.checked) {
        card.classList.add('checked');
    } else {
        card.classList.remove('checked');
    }
}

function wirePswdoDraftForm(config) {
    const form = document.getElementById(config.formId);
    if (!form || form.dataset.editable !== 'true') return;

    const revision = document.getElementById(config.revisionId);
    const state = document.getElementById(config.stateId);
    const stateIcon = document.getElementById(config.iconId);
    const stateText = document.getElementById(config.textId);
    const preview = document.getElementById(config.previewId);
    let timer = null;
    let activeSave = null;
    let changeVersion = 0;
    let dirty = @json(session()->hasOldInput('draft'));
    let hasSavedDraft = preview.getAttribute('aria-disabled') !== 'true';

    const setState = (name, text) => {
        state.className = 'pswdo-save-state ' + name;
        stateText.textContent = text;
        stateIcon.className = name === 'saving'
            ? 'mdi mdi-loading mdi-spin'
            : (name === 'unsaved' ? 'mdi mdi-clock-alert-outline' : 'mdi mdi-cloud-check');
    };
    const setPreviewAvailable = available => {
        preview.classList.toggle('disabled', !available);
        if (available) {
            preview.removeAttribute('aria-disabled');
            preview.removeAttribute('tabindex');
        } else {
            preview.setAttribute('aria-disabled', 'true');
            preview.setAttribute('tabindex', '-1');
        }
    };
    const queueSave = () => {
        clearTimeout(timer);
        timer = setTimeout(() => void save(), 1200);
    };
    const persist = async () => {
        const savingVersion = changeVersion;
        setState('saving', 'Saving...');
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                const error = new Error(config.errorMessage);
                error.status = response.status;
                throw error;
            }
            const data = await response.json();
            if (!Number.isInteger(Number(data.revision))) throw new Error(config.errorMessage);
            revision.value = String(data.revision);
            hasSavedDraft = true;
            dirty = changeVersion !== savingVersion;
            setState(dirty ? 'unsaved' : 'saved', dirty ? 'Changes not saved' : 'All changes saved');
            setPreviewAvailable(true);
            return true;
        } catch (error) {
            dirty = true;
            setState('unsaved', error.status === 409
                ? 'Draft changed elsewhere. Reload to continue.'
                : 'Could not save changes');
            setPreviewAvailable(hasSavedDraft);
            return false;
        }
    };
    const save = async () => {
        clearTimeout(timer);
        if (activeSave) {
            const activeSaveSucceeded = await activeSave;
            if (!activeSaveSucceeded) return false;
            return dirty ? save() : true;
        }
        if (!dirty) return true;
        if (!form.reportValidity()) return false;

        activeSave = persist();
        const saved = await activeSave;
        activeSave = null;

        return saved && dirty ? save() : saved;
    };
    const changed = () => {
        changeVersion += 1;
        dirty = true;
        setState('unsaved', 'Changes not saved');
        setPreviewAvailable(hasSavedDraft);
        queueSave();
    };

    if (dirty) {
        setState('unsaved', 'Changes not saved');
        setPreviewAvailable(false);
    }
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        clearTimeout(timer);
        await save();
    });
    preview.addEventListener('click', async event => {
        if (preview.getAttribute('aria-disabled') === 'true') {
            event.preventDefault();
            return;
        }
        if (!dirty && !activeSave) return;

        event.preventDefault();
        const previewWindow = window.open('', preview.target || '_blank');
        if (previewWindow) previewWindow.opener = null;
        const saved = await save();
        if (!saved || dirty) {
            if (previewWindow) previewWindow.close();
            return;
        }
        if (previewWindow) {
            previewWindow.location.replace(preview.href);
        } else {
            window.location.assign(preview.href);
        }
    });
    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}

wirePswdoDraftForm({
    formId: 'pswdoEclipDraftForm',
    revisionId: 'pswdoEclipRevision',
    stateId: 'pswdoEclipSaveState',
    iconId: 'pswdoEclipSaveIcon',
    textId: 'pswdoEclipSaveText',
    previewId: 'pswdoEclipPreview',
    errorMessage: 'E-CLIP draft save failed.',
});
wirePswdoDraftForm({
    formId: 'pswdoIifDraftForm',
    revisionId: 'pswdoIifRevision',
    stateId: 'pswdoIifSaveState',
    iconId: 'pswdoIifSaveIcon',
    textId: 'pswdoIifSaveText',
    previewId: 'pswdoIifPreview',
    errorMessage: 'Initial Interview Form draft save failed.',
});
</script>
@endsection
