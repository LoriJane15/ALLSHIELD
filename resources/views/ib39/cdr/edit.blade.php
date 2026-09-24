@extends('layouts.skydash-v')
@section('title', 'Edit CDR Draft')
@section('heading', 'Edit Custodial Debriefing Report Draft')

@push('styles')
<style>
    .cdr-editor-container {
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 3rem;
    }
    .module-nav-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }
    .module-back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #475569;
        font-size: 0.84rem;
        font-weight: 700;
        text-decoration: none !important;
        transition: all 0.2s ease;
    }
    .module-back-link:hover {
        color: #4338ca;
        transform: translateX(-2px);
    }
    .module-back-link i {
        font-size: 1.15rem;
    }
    .module-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.78rem;
        color: #64748b;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .module-breadcrumb li a {
        color: #64748b;
        text-decoration: none;
        font-weight: 600;
    }
    .module-breadcrumb li a:hover {
        color: #4338ca;
    }
    .module-breadcrumb li.active {
        color: #0f172a;
        font-weight: 700;
    }
    .module-breadcrumb .separator {
        color: #cbd5e1;
        font-size: 0.75rem;
    }

    .editor-hero {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(30, 27, 75, 0.25);
        color: #fff;
        overflow: hidden;
        padding: 1.5rem 1.75rem;
        position: relative;
        margin-bottom: 1.5rem;
    }
    .hero-top-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1.25rem;
        position: relative;
        z-index: 1;
    }
    .hero-main {
        display: flex;
        align-items: center;
        gap: 1.15rem;
        min-width: 0;
    }
    .hero-icon-box {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        flex: 0 0 56px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: #67e8f9;
        font-size: 1.6rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .hero-eyebrow {
        color: #f59e0b;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    .editor-hero h1 {
        color: #fff;
        font-size: 1.48rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 0.25rem;
        line-height: 1.2;
    }
    .editor-hero p {
        color: #c7d2fe;
        font-size: 0.8125rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
    }
    .hero-status-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: #f59e0b;
        color: #1e1b4b;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 750;
    }

    .hero-actions {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
    }
    .hero-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        padding: 0.65rem 1.25rem;
        border-radius: 10px;
        font-size: 0.8125rem;
        font-weight: 750;
        text-decoration: none !important;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .hero-btn-glass {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: #fff;
    }
    .hero-btn-glass:hover {
        background: rgba(255, 255, 255, 0.22);
        color: #fff;
        transform: translateY(-1px);
    }
    .hero-btn-white {
        background: #fff;
        border: 1px solid rgba(255, 255, 255, 0.9);
        color: #1e1b4b;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
    }
    .hero-btn-white:hover {
        background: #eef2ff;
        color: #4338ca;
        transform: translateY(-1px);
    }

    .editor-card {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        background: #fff;
        overflow: hidden;
        margin-bottom: 1.5rem;
        padding: 0;
    }
    .editor-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        background: #fafbfc;
    }
    .editor-card-header h2, .editor-card-header legend {
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 750;
        margin: 0;
        padding: 0;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        border: 0;
        float: none;
        width: auto;
    }
    .editor-card-header h2 i, .editor-card-header legend i {
        color: #4338ca;
        font-size: 1.2rem;
    }
    .editor-card-body {
        padding: 1.5rem;
    }

    .form-group-modern {
        margin-bottom: 1.25rem;
    }
    .form-group-modern label {
        color: #334155;
        font-size: 0.78rem;
        font-weight: 700;
        margin-bottom: 0.4rem;
        display: block;
    }
    .form-control-modern {
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        color: #0f172a;
        font-size: 0.84rem;
        padding: 0.65rem 0.95rem;
        transition: all 0.2s ease;
        width: 100%;
    }
    .form-control-modern:focus {
        border-color: #4338ca;
        box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.12);
        outline: 0;
    }
    select.form-control-modern {
        cursor: pointer;
    }

    .format-toolbar {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 0.45rem;
        background: #f8fafc;
        padding: 0.4rem 0.6rem;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    .format-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.3rem 0.6rem;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .format-btn:hover {
        background: #eef2ff;
        border-color: #4338ca;
        color: #4338ca;
    }

    .photo-upload-container {
        display: flex;
        align-items: center;
        gap: 1.75rem;
        flex-wrap: wrap;
        background: #fafbfc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
    }
    .photo-preview-box {
        width: 150px;
        height: 150px;
        border-radius: 14px;
        border: 2px dashed #cbd5e1;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex: 0 0 150px;
        position: relative;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        transition: border-color 0.2s ease;
    }
    .photo-preview-box.has-photo {
        border: 2px solid #e2e8f0;
    }
    .photo-preview {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .photo-preview-placeholder {
        text-align: center;
        color: #94a3b8;
        padding: 0.5rem;
    }
    .photo-preview-placeholder i {
        font-size: 2.4rem;
        display: block;
        margin-bottom: 0.25rem;
        color: #cbd5e1;
    }
    .photo-badge-overlay {
        position: absolute;
        bottom: 6px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(30, 27, 75, 0.85);
        backdrop-filter: blur(4px);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 750;
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        white-space: nowrap;
        pointer-events: none;
    }
    .photo-upload-controls {
        flex: 1;
        min-width: 260px;
    }
    .photo-upload-title {
        color: #0f172a;
        font-size: 1rem;
        font-weight: 750;
        margin-bottom: 0.35rem;
    }
    .photo-specs-list {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }
    .photo-spec-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 650;
        padding: 0.25rem 0.65rem;
        border-radius: 6px;
    }
    .photo-spec-pill i {
        font-size: 0.9rem;
        color: #64748b;
    }
    .photo-actions-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        flex-wrap: wrap;
    }
    .btn-choose-photo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: #fff;
        border: 1.5px solid #4338ca;
        color: #4338ca;
        font-size: 0.84rem;
        font-weight: 700;
        padding: 0.65rem 1.35rem;
        border-radius: 10px;
        cursor: pointer;
        margin: 0;
        transition: all 0.2s ease;
        box-shadow: 0 2px 6px rgba(67, 56, 202, 0.08);
    }
    .btn-choose-photo:hover {
        background: #eef2ff;
        color: #312e81;
        border-color: #312e81;
    }
    .btn-save-photo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: #4338ca;
        border: 1.5px solid #4338ca;
        color: #fff;
        font-size: 0.84rem;
        font-weight: 700;
        padding: 0.65rem 1.45rem;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(67, 56, 202, 0.25);
    }
    .btn-save-photo:hover {
        background: #312e81;
        border-color: #312e81;
        color: #fff;
        box-shadow: 0 6px 16px rgba(49, 46, 129, 0.3);
    }
    .selected-photo-feedback {
        display: none;
        align-items: center;
        gap: 0.45rem;
        color: #15803d;
        font-size: 0.78rem;
        font-weight: 700;
        margin-top: 0.65rem;
    }
    .selected-photo-feedback i {
        font-size: 1rem;
    }

    .repeat-table-wrapper {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1rem;
    }
    .repeat-table-modern {
        margin: 0;
        width: 100%;
    }
    .repeat-table-modern thead th {
        background: #f8fafc;
        border: 0;
        border-bottom: 1px solid #e2e8f0;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 750;
        letter-spacing: 0.04em;
        padding: 0.85rem 1rem;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .repeat-table-modern tbody td {
        border-color: #f1f5f9;
        padding: 0.75rem 0.85rem;
        vertical-align: middle;
    }
    .repeat-table-modern tbody td textarea {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.82rem;
        padding: 0.45rem 0.65rem;
        resize: vertical;
    }
    .repeat-table-modern tbody td textarea:focus {
        border-color: #4338ca;
        box-shadow: 0 0 0 2px rgba(67, 56, 202, 0.12);
        outline: 0;
    }

    .btn-add-table-row {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: #f8fafc;
        border: 1.5px dashed #4338ca;
        color: #4338ca;
        font-size: 0.8125rem;
        font-weight: 750;
        padding: 0.55rem 1.25rem;
        border-radius: 9px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-add-table-row:hover {
        background: #eef2ff;
    }
    .btn-remove-table-row {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        background: #fff1f2;
        border: 1px solid #fecdd3;
        color: #e11d48;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.4rem 0.75rem;
        border-radius: 7px;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .btn-remove-table-row:hover {
        background: #ffe4e6;
        color: #be123c;
    }

    .autosave-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #fff;
        font-size: 0.78rem;
        font-weight: 650;
        padding: 0.35rem 0.9rem;
        border-radius: 9999px;
        backdrop-filter: blur(4px);
        transition: all 0.2s ease;
    }
    .autosave-status-pill.saving {
        background: rgba(245, 158, 11, 0.25);
        border-color: rgba(245, 158, 11, 0.5);
        color: #fef3c7;
    }
    .autosave-status-pill.unsaved {
        background: rgba(239, 68, 68, 0.2);
        border-color: rgba(239, 68, 68, 0.4);
        color: #fee2e2;
    }
    .autosave-status-pill.saved {
        background: rgba(34, 197, 94, 0.2);
        border-color: rgba(34, 197, 94, 0.4);
        color: #dcfce7;
    }
    .spin-icon {
        display: inline-block;
        animation: autosave-spin 1s linear infinite;
    }
    @keyframes autosave-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .cdr-step-nav-bar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.15rem 1.35rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
    }
    .cdr-step-pills {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 0.5rem;
    }
    .cdr-step-pill {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.65rem 0.75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: left;
        outline: none !important;
        width: 100%;
    }
    .cdr-step-pill:hover {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #4338ca;
        transform: translateY(-1px);
    }
    .cdr-step-pill.active {
        background: linear-gradient(135deg, #312e81 0%, #4338ca 100%);
        border-color: #312e81;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(67, 56, 202, 0.25);
    }
    .cdr-step-pill .step-num {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        flex: 0 0 22px;
        border-radius: 6px;
        background: #e2e8f0;
        color: #475569;
        font-size: 0.7rem;
        font-weight: 800;
        transition: all 0.2s ease;
    }
    .cdr-step-pill.active .step-num {
        background: rgba(255, 255, 255, 0.22);
        color: #ffffff;
    }
    .cdr-step-pill .step-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .editor-action-footer {
        position: sticky;
        bottom: 1.5rem;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        padding: 1.15rem 1.75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        z-index: 10;
        margin-top: 2rem;
    }
    .btn-step-nav {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        font-size: 0.825rem;
        font-weight: 750;
        padding: 0.65rem 1.25rem;
        border-radius: 9px;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .btn-step-prev {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #475569;
    }
    .btn-step-prev:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
    }
    .btn-step-next {
        background: #4338ca;
        border: 1px solid #4338ca;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(67, 56, 202, 0.2);
    }
    .btn-step-next:hover {
        background: #312e81;
        border-color: #312e81;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(49, 46, 129, 0.25);
    }

    .btn-save-draft {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: #10b981;
        border: 1px solid #10b981;
        color: #fff;
        font-size: 0.84rem;
        font-weight: 750;
        padding: 0.65rem 1.5rem;
        border-radius: 9px;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-save-draft:hover {
        background: #059669;
        border-color: #059669;
        color: #fff;
        box-shadow: 0 6px 18px rgba(5, 150, 105, 0.3);
        transform: translateY(-1px);
    }

    @media(max-width:991px){
        .cdr-step-pills {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media(max-width:767px){
        .cdr-step-pills {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .editor-hero {
            padding: 1.25rem;
        }
        .hero-top-row {
            flex-direction: column;
            align-items: flex-start;
        }
        .hero-actions {
            width: 100%;
        }
        .hero-btn {
            flex: 1;
            width: 100%;
        }
        .editor-card-body {
            padding: 1.25rem;
        }
        .editor-action-footer {
            flex-direction: column;
            align-items: stretch;
        }
        .btn-save-draft, .btn-step-nav {
            width: 100%;
        }
    }
</style>
@endpush


@section('content')
<div class="cdr-editor-container">
    {{-- Top Navigation & History --}}
    <div class="module-nav-top">
        <a href="{{ route('ib39.cdr.show', $cdr) }}" class="module-back-link">
            <i class="mdi mdi-arrow-left"></i>
            <span>Back to CDR Workspace</span>
        </a>
        <ol class="module-breadcrumb" aria-label="Breadcrumb">
            <li><a href="{{ route('ib39.dashboard') }}">Dashboard</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li><a href="{{ route('ib39.fr-profiles.index') }}">FR Profiles</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li><a href="{{ route('ib39.fr-profiles.show', $cdr->surfacedFormerRebel) }}">{{ $cdr->surfacedFormerRebel->reference_number }}</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li><a href="{{ route('ib39.cdr.show', $cdr) }}">CDR Workspace</a></li>
            <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li class="active" aria-current="page">Edit Draft</li>
        </ol>
    </div>

    @if(session('status'))
        <div class="alert alert-success d-flex align-items-center mb-4" role="status">
            <i class="mdi mdi-check-circle-outline mr-2 font-weight-bold"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-4" role="alert">
            <div class="d-flex align-items-center mb-1">
                <i class="mdi mdi-alert-circle-outline mr-2 font-weight-bold font-size-lg"></i>
                <span class="font-weight-bold">Please check the highlighted errors:</span>
            </div>
            <ul class="mb-0 pl-4 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Modern Hero Banner --}}
    <header class="editor-hero">
        <div class="hero-top-row">
            <div class="hero-main">
                <div class="hero-icon-box">
                    <i class="mdi mdi-pencil" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="hero-eyebrow mb-1">Custodial Debriefing Report · Draft Editor</div>
                    <h1 class="mb-1">{{ $cdr->surfacedFormerRebel->reference_number }}</h1>
                    <p>
                        <span>Status: <strong class="text-white">{{ $cdr->status->value }}</strong></span>
                        <span>·</span>
                        <span>{{ $cdr->surfacedFormerRebel->display_name }}</span>
                    </p>
                </div>
            </div>
            <div class="hero-actions">
                <div class="autosave-status-pill" id="autoSaveIndicator">
                    <i class="mdi mdi-cloud-check" id="autoSaveIcon"></i>
                    <span id="autoSaveText">All changes saved</span>
                </div>
                <a class="hero-btn hero-btn-glass" href="{{ route('ib39.cdr.preview', $cdr) }}" target="_blank" rel="noopener">
                    <i class="mdi mdi-eye"></i>
                    <span>Preview</span>
                </a>
                <a class="hero-btn hero-btn-white" href="{{ route('ib39.cdr.show', $cdr) }}">
                    <i class="mdi mdi-file-document-box-outline"></i>
                    <span>Workspace</span>
                </a>
            </div>
        </div>
    </header>

    @php
        $draft = old('content', array_replace($defaultContent, $cdr->form?->content ?? []));
        $photoSlot = $cdr->photos->first(fn ($photo) => $photo->photo_type === \App\Enums\Ib39CdrPhotoType::FrPhoto);
        $stepMap = [
            'cover' => 1,
            'fr-photo' => 1,
            'personal' => 1,
            'physical' => 1,
            'parents_family' => 2,
            'siblings' => 2,
            'children' => 2,
            'government_relatives' => 2,
            'ugm_relatives' => 2,
            'neutralization' => 3,
            'entry_background' => 3,
            'party_member_courses' => 3,
            'npa_member_courses' => 3,
            'mass_activist_courses' => 3,
            'violent_activities' => 3,
            'non_violent_activities' => 3,
            'order_of_battle' => 4,
            'composition' => 4,
            'disposition' => 4,
            'strength_firearms' => 4,
            'training' => 4,
            'logistics' => 4,
            'strategy_tactics' => 4,
            'combat_effectiveness' => 4,
            'plans' => 4,
            'personalities' => 5,
            'posting_areas' => 5,
            'mass_contacts' => 5,
            'supply_routes' => 5,
            'npa_active' => 5,
            'other_information' => 5,
            'chronology' => 6,
            'assessment' => 6,
            'recommendation' => 6,
            'signatories' => 6,
        ];
    @endphp

    {{-- Interactive Step-by-Step Wizard Navigator --}}
    <div class="cdr-step-nav-bar" id="cdrStepNav">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge" style="background:#eef2ff; color:#4338ca; font-weight:750; font-size:0.75rem;">
                    <i class="mdi mdi-format-list-checks"></i> GUIDED DRAFT WIZARD
                </span>
                <span class="text-muted small font-weight-bold" id="currentStepLabelDisplay">Step 1 of 6: Cover & Personal Data</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold" id="btnToggleAllSections" style="border-radius:8px; font-size:0.78rem;">
                    <i class="mdi mdi-view-agenda me-1"></i> <span>Show All Sections</span>
                </button>
            </div>
        </div>
        <div class="cdr-step-pills" role="tablist">
            <button type="button" class="cdr-step-pill active" data-step-target="1">
                <span class="step-num">1</span>
                <span class="step-text">Cover & Personal</span>
            </button>
            <button type="button" class="cdr-step-pill" data-step-target="2">
                <span class="step-num">2</span>
                <span class="step-text">Family & Relatives</span>
            </button>
            <button type="button" class="cdr-step-pill" data-step-target="3">
                <span class="step-num">3</span>
                <span class="step-text">Affiliations</span>
            </button>
            <button type="button" class="cdr-step-pill" data-step-target="4">
                <span class="step-num">4</span>
                <span class="step-text">Order of Battle</span>
            </button>
            <button type="button" class="cdr-step-pill" data-step-target="5">
                <span class="step-num">5</span>
                <span class="step-text">Networks & Contacts</span>
            </button>
            <button type="button" class="cdr-step-pill" data-step-target="6">
                <span class="step-num">6</span>
                <span class="step-text">Assessment & Sign</span>
            </button>
        </div>
    </div>

    {{-- FR Photo Separate Form --}}
    <form id="fr-photo-upload" method="POST" enctype="multipart/form-data" action="{{ route('ib39.cdr.photos.store', $cdr) }}">
        @csrf
    </form>

    {{-- Main CDR Structured Draft Form --}}
    <form method="POST" action="{{ route('ib39.cdr.update', $cdr) }}" id="cdrDraftForm">
        @csrf
        @method('PUT')

        @foreach($orderedBlocks as [$kind, $key])
            @if($kind === 'section')
                @php $section = $sections[$key]; @endphp
                <fieldset data-section="{{ $key }}" data-step="{{ $stepMap[$key] ?? 1 }}" class="editor-card">
                    <div class="editor-card-header">
                        <legend>
                            <i class="mdi mdi-file-document-box-outline"></i>
                            <span>{{ $section['label'] }}</span>
                        </legend>
                    </div>
                    <div class="editor-card-body">
                        @if(!$section['fields'])
                            <p class="text-muted mb-0 font-size-sm">Official section heading</p>
                        @else
                            <div class="row">
                                @foreach($section['fields'] as $fieldKey => $field)
                                    <div class="col-md-6 mb-3" @if(in_array($fieldKey, ['significant_information', 'white_area_information', 'projected_enemy_operations'])) data-text-detail="{{ $fieldKey }}" @endif>
                                        <div class="form-group-modern mb-0">
                                            <label for="cdr-{{ $fieldKey }}">{{ $field['label'] }}</label>

                                            @if($field['type'] === 'yes_na_text')
                                                <select id="cdr-{{ $fieldKey }}" class="form-control-modern" name="content[{{ $fieldKey }}]" data-text-status>
                                                    <option value="">Select</option>
                                                    <option value="yes" @selected(data_get($draft, $fieldKey) === 'yes')>Yes</option>
                                                    <option value="na" @selected(data_get($draft, $fieldKey) === 'na')>N/A</option>
                                                </select>
                                            @elseif(($field['type'] ?? '') === 'select' || (!empty($field['options']) && ($field['type'] ?? '') !== 'datalist'))
                                                @php
                                                    $val = data_get($draft, $fieldKey);
                                                    $options = $field['options'] ?? [];
                                                @endphp
                                                <select id="cdr-{{ $fieldKey }}" class="form-control-modern" name="content[{{ $fieldKey }}]">
                                                    <option value="">-- Select {{ $field['label'] }} --</option>
                                                    @if($val && !in_array($val, $options))
                                                        <option value="{{ $val }}" selected>{{ $val }} (Current)</option>
                                                    @endif
                                                    @foreach($options as $option)
                                                        <option value="{{ $option }}" @selected($val === $option)>{{ $option }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif(($field['type'] ?? '') === 'datalist')
                                                @php
                                                    $val = data_get($draft, $fieldKey);
                                                    $options = $field['options'] ?? [];
                                                @endphp
                                                <input id="cdr-{{ $fieldKey }}" list="list-{{ $fieldKey }}" class="form-control-modern" type="text" maxlength="{{ $field['max'] }}" name="content[{{ $fieldKey }}]" value="{{ $val }}" placeholder="Select or type {{ strtolower($field['label']) }}...">
                                                <datalist id="list-{{ $fieldKey }}">
                                                    @foreach($options as $option)
                                                        <option value="{{ $option }}">
                                                    @endforeach
                                                </datalist>
                                            @elseif(in_array($field['type'], ['textarea', 'narrative']))
                                                @if($field['type'] === 'narrative')
                                                    <div class="format-toolbar">
                                                        <button type="button" class="format-btn" data-format="bold">
                                                            <i class="mdi mdi-format-bold"></i> Bold
                                                        </button>
                                                        <button type="button" class="format-btn" data-format="bullet">
                                                            <i class="mdi mdi-format-list-bulleted"></i> Bullet
                                                        </button>
                                                        <small class="text-muted ml-auto font-size-xs">Only **bold**, bullets, and line breaks are rendered.</small>
                                                    </div>
                                                @endif
                                                <textarea id="cdr-{{ $fieldKey }}" class="form-control-modern" rows="4" maxlength="{{ $field['max'] }}" name="content[{{ $fieldKey }}]">{{ data_get($draft, $fieldKey) }}</textarea>
                                            @else
                                                <input id="cdr-{{ $fieldKey }}" class="form-control-modern" type="{{ $field['type'] === 'date' ? 'date' : 'text' }}" maxlength="{{ $field['max'] }}" name="content[{{ $fieldKey }}]" value="{{ data_get($draft, $fieldKey) }}">
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </fieldset>

                @if($key === 'cover')
                    <fieldset data-section="fr-photo" data-step="1" class="editor-card">
                        <div class="editor-card-header">
                            <legend>
                                <i class="mdi mdi-camera"></i>
                                <span>FR Photo</span>
                            </legend>
                        </div>
                        <div class="editor-card-body">
                            <input form="fr-photo-upload" type="hidden" name="photo_type" value="fr_photo">
                            <div class="photo-upload-container">
                                <div class="photo-preview-box {{ $photoSlot?->currentVersion ? 'has-photo' : '' }}" id="frPhotoBox">
                                    @if($photoSlot?->currentVersion)
                                        <img class="photo-preview" id="frPhotoImg" alt="Current FR Photo" src="{{ route('ib39.cdr.photos.show', $photoSlot->currentVersion) }}">
                                        <span class="photo-badge-overlay"><i class="mdi mdi-check"></i> Current Photo</span>
                                    @else
                                        <div class="photo-preview-placeholder" id="frPhotoPlaceholder">
                                            <i class="mdi mdi-account-box"></i>
                                            <span class="font-size-xs font-weight-bold d-block">No photo uploaded</span>
                                        </div>
                                        <img class="photo-preview d-none" id="frPhotoImg" alt="Selected FR Photo">
                                    @endif
                                </div>
                                <div class="photo-upload-controls">
                                    <h3 class="photo-upload-title">Custodial Debriefing Photograph</h3>
                                    <div class="photo-specs-list">
                                        <span class="photo-spec-pill"><i class="mdi mdi-image"></i> JPEG or PNG</span>
                                        <span class="photo-spec-pill"><i class="mdi mdi-file-image"></i> Maximum 5 MB</span>
                                        <span class="photo-spec-pill"><i class="mdi mdi-shield-check"></i> Official Record</span>
                                    </div>

                                    <div class="photo-actions-row">
                                        <input form="fr-photo-upload" id="fr-photo" type="file" name="photo" accept="image/jpeg,image/png" required class="d-none" onchange="previewSelectedPhoto(this)">
                                        <label for="fr-photo" class="btn-choose-photo">
                                            <i class="mdi mdi-image-plus"></i>
                                            <span id="btnChoosePhotoText">{{ $photoSlot?->currentVersion ? 'Choose Replacement Image...' : 'Choose Photo File...' }}</span>
                                        </label>
                                        <button form="fr-photo-upload" class="btn-save-photo" type="submit">
                                            <i class="mdi mdi-cloud-upload"></i>
                                            <span>{{ $photoSlot?->currentVersion ? 'Replace' : 'Save' }} FR Photo</span>
                                        </button>
                                    </div>

                                    <div id="photoSelectionFeedback" class="selected-photo-feedback">
                                        <i class="mdi mdi-check-circle"></i>
                                        <span id="photoSelectionFilename"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                @endif
            @else
                @php
                    $section = $repeatableSections[$key];
                    $rows = data_get($draft, $key, []);
                    $rows = count($rows) ? $rows : [[]];
                    $statusKey = $section['status_key'] ?? null;
                @endphp
                <fieldset data-repeatable="{{ $key }}" data-step="{{ $stepMap[$key] ?? 1 }}" class="editor-card">
                    <div class="editor-card-header">
                        <legend>
                            <i class="mdi mdi-table"></i>
                            <span>{{ $section['label'] }}</span>
                        </legend>
                    </div>
                    <div class="editor-card-body">
                        @if($statusKey)
                            <div class="form-group-modern mb-3" style="max-width: 320px;">
                                <label for="cdr-{{ $statusKey }}">Applicability</label>
                                <select id="cdr-{{ $statusKey }}" class="form-control-modern" name="content[{{ $statusKey }}]" data-applicability>
                                    <option value="">Select</option>
                                    <option value="yes" @selected(data_get($draft, $statusKey) === 'yes')>Yes</option>
                                    <option value="na" @selected(data_get($draft, $statusKey) === 'na')>N/A</option>
                                </select>
                            </div>
                        @endif

                        <div data-table-details>
                            <div class="repeat-table-wrapper table-responsive">
                                <table class="table repeat-table-modern">
                                    <thead>
                                        <tr>
                                            @foreach($section['columns'] as $label)
                                                <th>{{ $label }}</th>
                                            @endforeach
                                            <th style="width: 90px; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rows as $i => $row)
                                            <tr>
                                                @foreach($section['columns'] as $column => $label)
                                                    <td>
                                                        @if($key === 'parents_family' && $column === 'relation')
                                                            @php
                                                                $relVal = data_get($row, $column);
                                                                $relOptions = ['Father', 'Mother', 'Husband', 'Wife', 'Spouse', 'Live-in Partner', 'Son', 'Daughter', 'Brother', 'Sister', 'Guardian', 'Other'];
                                                            @endphp
                                                            <select class="form-control" name="content[{{ $key }}][{{ $i }}][{{ $column }}]" style="font-size: .8rem; padding: .35rem .5rem; border-radius: 7px; border: 1.5px solid #d8e0ea;">
                                                                <option value="">-- Relation --</option>
                                                                @if($relVal && !in_array($relVal, $relOptions))
                                                                    <option value="{{ $relVal }}" selected>{{ $relVal }}</option>
                                                                @endif
                                                                @foreach($relOptions as $opt)
                                                                    <option value="{{ $opt }}" @selected($relVal === $opt)>{{ $opt }}</option>
                                                                @endforeach
                                                            </select>
                                                        @elseif($key === 'mass_contacts' && $column === 'status')
                                                            @php
                                                                $stVal = data_get($row, $column);
                                                                $stOptions = ['Active', 'Inactive', 'Surrendered', 'Neutralized', 'Under Surveillance', 'Deceased'];
                                                            @endphp
                                                            <select class="form-control" name="content[{{ $key }}][{{ $i }}][{{ $column }}]" style="font-size: .8rem; padding: .35rem .5rem; border-radius: 7px; border: 1.5px solid #d8e0ea;">
                                                                <option value="">-- Status --</option>
                                                                @if($stVal && !in_array($stVal, $stOptions))
                                                                    <option value="{{ $stVal }}" selected>{{ $stVal }}</option>
                                                                @endif
                                                                @foreach($stOptions as $opt)
                                                                    <option value="{{ $opt }}" @selected($stVal === $opt)>{{ $opt }}</option>
                                                                @endforeach
                                                            </select>
                                                        @else
                                                            <textarea class="form-control" rows="2" maxlength="10000" name="content[{{ $key }}][{{ $i }}][{{ $column }}]">{{ data_get($row, $column) }}</textarea>
                                                        @endif
                                                    </td>
                                                @endforeach
                                                <td style="text-align: center;">
                                                    <button type="button" class="btn-remove-table-row" data-remove-row>
                                                        <i class="mdi mdi-delete-outline"></i> Remove
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn-add-table-row" data-add-row>
                                <i class="mdi mdi-plus-circle-outline"></i>
                                <span>Add row to {{ $section['label'] }}</span>
                            </button>
                        </div>
                    </div>
                </fieldset>
            @endif
        @endforeach

        {{-- Bottom Floating Action Bar --}}
        <div class="editor-action-footer">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn-step-nav btn-step-prev" id="btnPrevStep" style="display: none;">
                    <i class="mdi mdi-arrow-left"></i> <span>Previous Step</span>
                </button>
                <button type="button" class="btn-step-nav btn-step-next" id="btnNextStep">
                    <span>Next Step</span> <i class="mdi mdi-arrow-right"></i>
                </button>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-muted font-size-xs d-none d-md-flex align-items-center" id="autoSaveBottomIndicator">
                    <i class="mdi mdi-cloud-check text-success mr-1"></i>
                    <span id="autoSaveBottomText">All changes saved</span>
                </div>
                <button class="btn-save-draft" type="submit" id="btnManualSaveDraft">
                    <i class="mdi mdi-content-save"></i>
                    <span>Save draft</span>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
/* ==========================================================================
   Step-by-Step Wizard Navigation Controller
   ========================================================================== */
var currentStep = 1;
var totalSteps = 6;
var isAllSectionsMode = false;
var stepLabels = {
    1: 'Cover & Personal Data',
    2: 'Family & Relatives',
    3: 'Affiliations & Neutralization',
    4: 'Order of Battle & Firepower',
    5: 'Networks & Contacts',
    6: 'Assessment & Signatures'
};

var stepFieldsets = document.querySelectorAll('fieldset[data-step]');
var stepPills = document.querySelectorAll('.cdr-step-pill');
var currentStepLabelDisplay = document.getElementById('currentStepLabelDisplay');
var btnPrevStep = document.getElementById('btnPrevStep');
var btnNextStep = document.getElementById('btnNextStep');
var btnToggleAll = document.getElementById('btnToggleAllSections');

function goToStep(step, smoothScroll) {
    if (step < 1 || step > totalSteps) return;
    
    if (isAllSectionsMode) {
        isAllSectionsMode = false;
        if (btnToggleAll) {
            btnToggleAll.innerHTML = '<i class="mdi mdi-view-agenda me-1"></i> <span>Show All Sections</span>';
            btnToggleAll.classList.remove('btn-secondary');
            btnToggleAll.classList.add('btn-outline-secondary');
        }
    }

    currentStep = step;

    // Show only the fieldsets for this step
    stepFieldsets.forEach(function(fs) {
        var fsStep = parseInt(fs.getAttribute('data-step') || '1', 10);
        if (fsStep === currentStep) {
            fs.style.display = '';
        } else {
            fs.style.display = 'none';
        }
    });

    // Update active state on stepper pills
    stepPills.forEach(function(pill) {
        var target = parseInt(pill.getAttribute('data-step-target') || '1', 10);
        if (target === currentStep) {
            pill.classList.add('active');
        } else {
            pill.classList.remove('active');
        }
    });

    // Update Step label
    if (currentStepLabelDisplay) {
        currentStepLabelDisplay.textContent = 'Step ' + currentStep + ' of ' + totalSteps + ': ' + (stepLabels[currentStep] || '');
    }

    // Update navigation buttons
    if (btnPrevStep) {
        btnPrevStep.style.display = (currentStep === 1) ? 'none' : 'inline-flex';
    }
    if (btnNextStep) {
        if (currentStep === totalSteps) {
            btnNextStep.style.display = 'none';
        } else {
            btnNextStep.style.display = 'inline-flex';
            btnNextStep.innerHTML = '<span>Next Step</span> <i class="mdi mdi-arrow-right"></i>';
        }
    }

    if (smoothScroll) {
        var navBar = document.getElementById('cdrStepNav');
        if (navBar) {
            var navPos = navBar.getBoundingClientRect().top + window.scrollY - 80;
            if (window.scrollY > navPos) {
                window.scrollTo({ top: navPos, behavior: 'smooth' });
            }
        }
    }
}

function toggleAllSections() {
    isAllSectionsMode = !isAllSectionsMode;

    if (isAllSectionsMode) {
        stepFieldsets.forEach(function(fs) {
            fs.style.display = '';
        });
        stepPills.forEach(function(pill) {
            pill.classList.remove('active');
        });
        if (btnToggleAll) {
            btnToggleAll.innerHTML = '<i class="mdi mdi-format-list-checks me-1"></i> <span>Show Step-by-Step</span>';
            btnToggleAll.classList.remove('btn-outline-secondary');
            btnToggleAll.classList.add('btn-secondary');
        }
        if (currentStepLabelDisplay) {
            currentStepLabelDisplay.textContent = 'Showing All 35 Sections (Continuous View)';
        }
        if (btnPrevStep) btnPrevStep.style.display = 'none';
        if (btnNextStep) btnNextStep.style.display = 'none';
    } else {
        if (btnToggleAll) {
            btnToggleAll.innerHTML = '<i class="mdi mdi-view-agenda me-1"></i> <span>Show All Sections</span>';
            btnToggleAll.classList.remove('btn-secondary');
            btnToggleAll.classList.add('btn-outline-secondary');
        }
        goToStep(currentStep, false);
    }
}

// Bind step navigation events
stepPills.forEach(function(pill) {
    pill.addEventListener('click', function() {
        var stepTarget = parseInt(this.getAttribute('data-step-target') || '1', 10);
        goToStep(stepTarget, true);
    });
});

if (btnPrevStep) {
    btnPrevStep.addEventListener('click', function() {
        goToStep(currentStep - 1, true);
    });
}

if (btnNextStep) {
    btnNextStep.addEventListener('click', function() {
        goToStep(currentStep + 1, true);
    });
}

if (btnToggleAll) {
    btnToggleAll.addEventListener('click', function() {
        toggleAllSections();
    });
}

// Initialize on Step 1
goToStep(1, false);

document.querySelectorAll('[data-repeatable]').forEach(function(s){
    function ix(){
        s.querySelectorAll('tbody tr').forEach(function(r,i){
            r.querySelectorAll('textarea, input, select').forEach(function(f){
                f.name=f.name.replace(/\[\d+\]/,'['+i+']');
            });
        });
    }
    var addBtn = s.querySelector('[data-add-row]');
    if (addBtn) {
        addBtn.onclick=function(){
            var b=s.querySelector('tbody');
            if(b.rows.length>={{ \App\Support\Ib39CdrFormSchema::MAX_ROWS }}) return;
            var r=b.rows[0].cloneNode(true);
            r.querySelectorAll('textarea, input, select').forEach(function(f){f.value='';});
            b.appendChild(r);
            ix();
            scheduleAutoSave();
        };
    }
    s.onclick=function(e){
        var removeBtn = e.target.closest('[data-remove-row]');
        if(!removeBtn) return;
        var b=s.querySelector('tbody');
        if(b.rows.length>1){
            removeBtn.closest('tr').remove();
            ix();
            scheduleAutoSave();
        } else {
            b.querySelectorAll('textarea, input, select').forEach(function(f){f.value='';});
            scheduleAutoSave();
        }
    };
    var a=s.querySelector('[data-applicability]');
    if(a){
        function toggle(){
            var off=a.value==='na', d=s.querySelector('[data-table-details]');
            d.hidden=off;
            d.querySelectorAll('textarea, input, select, button').forEach(function(f){f.disabled=off;});
        }
        a.onchange=function(){
            toggle();
            scheduleAutoSave();
        };
        toggle();
    }
});

document.querySelectorAll('[data-text-status]').forEach(function(s){
    var stem=s.id.replace('cdr-','').replace('_status',''), d=document.querySelector('[data-text-detail="'+stem+'"]');
    if(!d) return;
    function toggle(){
        var off=s.value==='na';
        d.hidden=off;
        d.querySelectorAll('textarea').forEach(function(f){f.disabled=off;});
    }
    s.onchange=function(){
        toggle();
        scheduleAutoSave();
    };
    toggle();
});

document.querySelectorAll('[data-format]').forEach(function(b){
    b.onclick=function(){
        var a=b.closest('.form-group-modern, .mb-3').querySelector('textarea');
        if(!a) return;
        var x=a.selectionStart, y=a.selectionEnd, t=a.value.slice(x,y);
        a.setRangeText(b.dataset.format==='bold'?'**'+t+'**':(x&&a.value[x-1]!=='\n'?'\n':'')+'- '+t,x,y,'end');
        a.focus();
        scheduleAutoSave();
    };
});

function previewSelectedPhoto(input) {
    if (input.files && input.files[0]) {
        var file = input.files[0];
        var sizeKb = Math.round(file.size / 1024);
        var feedback = document.getElementById('photoSelectionFeedback');
        var filenameSpan = document.getElementById('photoSelectionFilename');
        var chooseText = document.getElementById('btnChoosePhotoText');
        var placeholder = document.getElementById('frPhotoPlaceholder');
        var img = document.getElementById('frPhotoImg');
        var box = document.getElementById('frPhotoBox');

        if (filenameSpan) filenameSpan.textContent = file.name + ' (' + sizeKb + ' KB) ready to upload';
        if (feedback) feedback.style.display = 'inline-flex';
        if (chooseText) chooseText.textContent = 'Change selected photo...';

        if (img) {
            img.src = URL.createObjectURL(file);
            img.classList.remove('d-none');
            if (placeholder) placeholder.classList.add('d-none');
            if (box) box.classList.add('has-photo');
        }
    }
}

/* ==========================================================================
   Auto-Save Draft Engine (Local Storage + Asynchronous Background Sync)
   ========================================================================== */
var draftForm = document.getElementById('cdrDraftForm');
var autoSaveTimer = null;
var isFormDirty = false;
var isAutoSaving = false;
var cdrStorageKey = 'shield_cdr_backup_{{ $cdr->id }}';

var autoSaveIndicator = document.getElementById('autoSaveIndicator');
var autoSaveIcon = document.getElementById('autoSaveIcon');
var autoSaveText = document.getElementById('autoSaveText');
var autoSaveBottomText = document.getElementById('autoSaveBottomText');

function setAutoSaveStatus(status, message) {
    if (!autoSaveIndicator) return;
    autoSaveIndicator.classList.remove('saving', 'unsaved', 'saved');

    if (status === 'saving') {
        autoSaveIndicator.classList.add('saving');
        if (autoSaveIcon) autoSaveIcon.className = 'mdi mdi-loading spin-icon';
        if (autoSaveText) autoSaveText.textContent = message || 'Saving draft...';
        if (autoSaveBottomText) autoSaveBottomText.textContent = 'Saving draft in background...';
    } else if (status === 'unsaved') {
        autoSaveIndicator.classList.add('unsaved');
        if (autoSaveIcon) autoSaveIcon.className = 'mdi mdi-clock-alert-outline';
        if (autoSaveText) autoSaveText.textContent = message || 'Unsaved changes';
        if (autoSaveBottomText) autoSaveBottomText.textContent = 'Unsaved changes';
    } else if (status === 'saved') {
        autoSaveIndicator.classList.add('saved');
        if (autoSaveIcon) autoSaveIcon.className = 'mdi mdi-cloud-check';
        if (autoSaveText) autoSaveText.textContent = message || 'All changes saved';
        if (autoSaveBottomText) autoSaveBottomText.textContent = message || 'Draft automatically saved';
    } else if (status === 'local') {
        autoSaveIndicator.classList.add('saved');
        if (autoSaveIcon) autoSaveIcon.className = 'mdi mdi-content-save';
        if (autoSaveText) autoSaveText.textContent = message || 'Saved to browser cache';
        if (autoSaveBottomText) autoSaveBottomText.textContent = 'Saved to browser cache';
    }
}

function saveToLocalStorage() {
    try {
        var formData = new FormData(draftForm);
        var obj = {};
        for (var pair of formData.entries()) {
            obj[pair[0]] = pair[1];
        }
        localStorage.setItem(cdrStorageKey, JSON.stringify({
            data: obj,
            timestamp: new Date().toISOString()
        }));
    } catch(e) {}
}

function performAutoSave() {
    if (!isFormDirty || isAutoSaving || !draftForm) return;
    isAutoSaving = true;
    setAutoSaveStatus('saving');
    saveToLocalStorage();

    var formData = new FormData(draftForm);
    var tokenInput = draftForm.querySelector('input[name="_token"]');
    var token = tokenInput ? tokenInput.value : '';

    fetch(draftForm.action, {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token
        }
    })
    .then(function(res) {
        if (!res.ok) throw new Error('Auto-save response status ' + res.status);
        return res.json();
    })
    .then(function(data) {
        isFormDirty = false;
        isAutoSaving = false;
        var timeLabel = data.formatted_time || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        setAutoSaveStatus('saved', 'Auto-saved at ' + timeLabel);
        try {
            localStorage.removeItem(cdrStorageKey);
        } catch(e) {}
    })
    .catch(function() {
        isAutoSaving = false;
        setAutoSaveStatus('local', 'Saved to browser cache');
    });
}

function scheduleAutoSave(delay) {
    isFormDirty = true;
    setAutoSaveStatus('unsaved');
    saveToLocalStorage();
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(performAutoSave, delay || 2000);
}

if (draftForm) {
    draftForm.addEventListener('input', function() {
        scheduleAutoSave(2000);
    });

    draftForm.addEventListener('change', function() {
        scheduleAutoSave(1000);
    });

    draftForm.addEventListener('submit', function() {
        isFormDirty = false;
        clearTimeout(autoSaveTimer);
        try {
            localStorage.removeItem(cdrStorageKey);
        } catch(e) {}
    });
}

// Periodic auto-save every 30 seconds if dirty
setInterval(function() {
    if (isFormDirty && !isAutoSaving) {
        performAutoSave();
    }
}, 30000);

// Warn if accidentally navigating away or refreshing with unsaved changes
window.addEventListener('beforeunload', function(e) {
    if (isFormDirty) {
        saveToLocalStorage();
        e.preventDefault();
        e.returnValue = 'You have unsaved changes in your CDR draft.';
        return 'You have unsaved changes in your CDR draft.';
    }
});
</script>
@endpush
