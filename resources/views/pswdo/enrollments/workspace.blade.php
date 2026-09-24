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
}
</style>
@endpush

@section('content')
@php($fr = $enrollment->surfacedFormerRebel)
@php($completedCount = $enrollment->completedDocumentCount())
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

    {{-- Visual Flow Stepper --}}
    <div class="pswdo-stepper-box">
        <ol class="pswdo-stepper-list" aria-label="PSWDO enrollment progress">
            @foreach($types as $position => $type)
                @php($done = $enrollment->hasDocument($type))
                @php($isActive = !$done && ($position === 0 || $enrollment->hasDocument($types[$position - 1])))
                <li class="pswdo-step-item {{ $done ? 'is-done' : ($isActive ? 'is-active' : '') }}">
                    <span class="pswdo-step-bubble">
                        {!! $done ? '<i class="mdi mdi-check"></i>' : $position + 1 !!}
                    </span>
                    <div>
                        <strong class="pswdo-step-label">{{ $type->label() }}</strong>
                        <div class="pswdo-step-tag">{{ $done ? 'Completed' : ($isActive ? 'Action Required' : 'Pending') }}</div>
                    </div>
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
        @php($document = $enrollment->documents->firstWhere('document_type', $type))
        @php($locked = $type->isEndorsementLetter() && !collect(\App\Enums\PswdoEnrollmentDocumentType::prerequisites())->every(fn($required) => $enrollment->hasDocument($required)))
        <section id="{{ $type->value }}" class="pswdo-doc-section-card">
            <div class="pswdo-doc-head">
                <div class="pswdo-doc-head-title">
                    <div class="pswdo-doc-head-icon" style="background: {{ $document ? '#ecfdf5' : ($locked ? '#f1f5f9' : '#eef2ff') }}; color: {{ $document ? '#059669' : ($locked ? '#94a3b8' : '#4338ca') }};">
                        <i class="mdi {{ $document ? 'mdi-file-check-outline' : ($locked ? 'mdi-lock-outline' : 'mdi-file-upload-outline') }}"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em;">Step {{ $index + 1 }}</span>
                        <div style="font-size: 1rem; font-weight: 800; color: #0f172a;">{{ $type->label() }}</div>
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
                                <i class="mdi mdi-eye-outline mr-1"></i> Secure Preview
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('pswdo.enrollments.documents.download', [$enrollment, $document]) }}" style="font-weight: 750; border-radius: 8px; padding: 0.45rem 1rem;">
                                <i class="mdi mdi-download mr-1"></i> Secure Download
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
                        <div class="pswdo-upload-zone" id="dropzone-{{ $type->value }}">
                            <input id="document-{{ $type->value }}" class="pswdo-file-input" type="file" name="document" accept="application/pdf,.pdf" required onchange="handleFileSelected(this, '{{ $type->value }}')">
                            <div class="d-flex flex-column align-items-center justify-content-center">
                                <div style="width: 48px; height: 48px; border-radius: 50%; background: #eef2ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 0.65rem;">
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                </div>
                                <div class="font-weight-bold" style="color: #0f172a; font-size: 0.95rem; margin-bottom: 0.2rem;" id="filename-display-{{ $type->value }}">
                                    Choose or drag final signed PDF here
                                </div>
                                <div class="text-muted small">
                                    Accepted format: PDF only (max 20MB)
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="small font-weight-bold text-uppercase text-muted mb-2" style="letter-spacing: 0.05em; font-size: 0.72rem;">
                                Mandatory Verification Checklist
                            </div>

                            <label class="pswdo-verify-card" for="type-{{ $type->value }}">
                                <input id="type-{{ $type->value }}" class="form-check-input" type="checkbox" name="correct_document_type_confirmed" value="1" required onchange="toggleVerifyCard(this)">
                                <span class="pswdo-verify-label">
                                    I confirm this is the correct <strong>{{ $type->label() }}</strong>.
                                </span>
                            </label>

                            <label class="pswdo-verify-card" for="fr-{{ $type->value }}">
                                <input id="fr-{{ $type->value }}" class="form-check-input" type="checkbox" name="belongs_to_fr_confirmed" value="1" required onchange="toggleVerifyCard(this)">
                                <span class="pswdo-verify-label">
                                    I confirm this document belongs to <strong>{{ $fr->reference_number }} &mdash; {{ $fr->display_name }}</strong>.
                                </span>
                            </label>

                            <label class="pswdo-verify-card" for="final-{{ $type->value }}">
                                <input id="final-{{ $type->value }}" class="form-check-input" type="checkbox" name="final_signed_confirmed" value="1" required onchange="toggleVerifyCard(this)">
                                <span class="pswdo-verify-label">
                                    I confirm this is the final completed and signed document. SHIELD does not validate handwritten signatures.
                                </span>
                            </label>
                        </div>

                        <button class="btn btn-action-primary" type="submit" style="background: #059669; border: none; font-weight: 750; padding: 0.6rem 1.4rem; border-radius: 9px; box-shadow: 0 2px 8px rgba(5, 150, 105, 0.25);">
                            <i class="mdi mdi-upload mr-1"></i> Upload Final Signed PDF
                        </button>
                    </form>
                @endif
            </div>
        </section>
    @endforeach
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
</script>
@endsection
