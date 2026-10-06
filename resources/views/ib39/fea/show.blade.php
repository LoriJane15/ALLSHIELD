@extends('layouts.skydash-v')
@section('title', 'FEA Record')
@section('heading', 'FEA Record')

@push('styles')
<style>
    .fea-workspace-container {
        max-width: 1320px;
        margin: 0 auto;
        padding-bottom: 2.5rem;
    }

    /* Top Nav & Breadcrumbs */
    .module-nav-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem 1.25rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    }
    .module-back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
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
    .module-back-link:hover {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #4338ca;
        transform: translateX(-2px);
    }
    .module-back-link i {
        font-size: 1.1rem;
    }
    .module-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.8125rem;
        color: #64748b;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .module-breadcrumb li a {
        color: #64748b;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.15s ease;
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

    /* Hero Banner */
    .fea-workspace-hero {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
        border-radius: 16px;
        color: #ffffff;
        padding: 1.25rem 1.75rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 10px 25px -5px rgba(30, 27, 75, 0.25);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
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
        width: 50px;
        height: 50px;
        flex: 0 0 50px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: #67e8f9;
        font-size: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .fea-workspace-hero h2 {
        color: #ffffff;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 0.15rem;
    }
    .fea-workspace-hero p {
        color: #c7d2fe;
        font-size: 0.8125rem;
        margin: 0;
    }
    .overall-badge {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 9999px;
        color: #ffffff;
        font-size: 0.8125rem;
        font-weight: 750;
        padding: 0.4rem 1.15rem;
        letter-spacing: 0.03em;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        backdrop-filter: blur(4px);
    }
    .overall-badge::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #f59e0b;
    }

    /* Lock Alert Banner */
    .fea-lock-notice {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-left: 4px solid #f59e0b;
        border-radius: 12px;
        padding: 0.9rem 1.25rem;
        margin-bottom: 1.25rem;
        color: #92400e;
        font-size: 0.84rem;
        line-height: 1.5;
    }
    .fea-lock-notice i {
        color: #f59e0b;
        font-size: 1.35rem;
        margin-top: 0.05rem;
    }

    /* Document workspace */
    .fea-panel {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        background: #ffffff;
        overflow: hidden;
    }
    .fea-panel .card-header {
        background: #fafbfc;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.95rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .fea-title {
        color: #0f172a;
        font-size: 0.925rem;
        font-weight: 800;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }
    .fea-panel .card-body {
        padding: 1.25rem;
    }

    /* Horizontal document checklist */
    .doc-nav-list {
        display: flex;
        flex-direction: row;
        gap: 0.6rem;
        overflow-x: auto;
        padding-bottom: 0.25rem;
    }
    .doc-nav-btn {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        text-decoration: none !important;
        color: #1e293b;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        text-align: left;
        min-width: 165px;
        max-width: 220px;
        flex: 1 0 165px;
    }
    .doc-nav-btn:hover {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #4338ca;
        transform: translateY(-2px);
    }
    .doc-nav-btn.active {
        background: linear-gradient(135deg, #312e81 0%, #4338ca 100%);
        border-color: #312e81;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(67, 56, 202, 0.28);
    }
    .doc-nav-btn.active .doc-nav-num {
        background: rgba(255, 255, 255, 0.22);
        color: #ffffff;
    }
    .doc-nav-btn.active .doc-nav-title {
        color: #ffffff;
    }
    .doc-nav-btn.active .doc-nav-status {
        background: rgba(255, 255, 255, 0.18);
        border-color: rgba(255, 255, 255, 0.3);
        color: #ffffff;
    }
    .doc-nav-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }
    .doc-nav-num {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        flex: 0 0 28px;
        border-radius: 8px;
        background: #e2e8f0;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 800;
        transition: all 0.2s ease;
    }
    .doc-nav-title {
        font-size: 0.825rem;
        font-weight: 750;
        color: #1e293b;
        margin: 0;
        line-height: 1.35;
        white-space: normal;
    }
    .doc-nav-status {
        font-size: 0.6875rem;
        font-weight: 750;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        color: #64748b;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .fea-checklist { margin-bottom: 1.25rem; }
    .fea-draft-inputs { margin-top: 1rem; }
    .fea-draft-inputs h3 { font-size: 1rem; font-weight: 800; }
    .fea-photo-preview { display: block; max-width: 100%; max-height: 480px; margin: 1rem auto; object-fit: contain; }

    /* Active Document Card Pane */
    .requirement-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1.25rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }
    .requirement-header {
        align-items: flex-start;
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        padding-bottom: 0.85rem;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 1rem;
    }
    .requirement-name {
        color: #0f172a;
        font-size: 1.05rem;
        font-weight: 800;
        margin-bottom: 0.25rem;
    }
    .requirement-meta {
        color: #64748b;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: wrap;
    }
    .pending-badge {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 9999px;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 750;
        padding: 0.3rem 0.85rem;
        letter-spacing: 0.02em;
    }

    /* Document Actions */
    .document-actions {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 1rem;
        padding-top: 0.85rem;
        border-top: 1px solid #f1f5f9;
    }
    .btn-action-primary {
        background: #4338ca;
        border: 1px solid #4338ca;
        color: #ffffff;
        font-weight: 750;
        font-size: 0.8125rem;
        padding: 0.45rem 1rem;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none !important;
        transition: all 0.2s ease;
    }
    .btn-action-primary:hover {
        background: #312e81;
        border-color: #312e81;
        color: #ffffff;
    }
    .btn-action-outline {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #475569;
        font-weight: 700;
        font-size: 0.8125rem;
        padding: 0.45rem 0.95rem;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none !important;
        transition: all 0.2s ease;
    }
    .btn-action-outline:hover {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #4338ca;
    }

    /* Selected-document upload controls */
    .compact-upload {
        margin-top: 1rem;
        padding: 0.85rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }
    .preliminary-form {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-top: 1.25rem;
        padding: 1.25rem;
    }

    @media(max-width:767px){
        .requirement-header {
            flex-direction: column;
        }
    }
</style>
@include('components.processing-workspace.styles')
@endpush

@section('content')
@php
    $record = $fea->surfacedFormerRebel;
@endphp
<div class="fea-workspace-container">
    {{-- Top Navigation & History Breadcrumb --}}
    <div class="module-nav-top">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge" style="background:#eef2ff; color:#4338ca; font-weight:750; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em;">
                <i class="mdi mdi-file-document-box"></i> FEA Workspace
            </span>
            <nav aria-label="Breadcrumb">
                <ol class="module-breadcrumb">
                    <li><a href="{{ route('ib39.dashboard') }}"><i class="mdi mdi-view-dashboard-outline me-1"></i>Dashboard</a></li>
                    <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
                    <li><a href="{{ route('ib39.fea.index') }}">FEA Processing</a></li>
                    <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
                    <li class="active" aria-current="page">{{ $record->reference_number }}</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('ib39.fea.index') }}" class="module-back-link">
            <i class="mdi mdi-arrow-left"></i>
            <span>Back to FEA Processing</span>
        </a>
    </div>

    {{-- Modern Hero Banner --}}
    <header class="fea-workspace-hero">
        <div class="hero-main">
            <div class="hero-icon-box">
                <i class="mdi mdi-shield-account-outline" aria-hidden="true"></i>
            </div>
            <div>
                <h2>FEA Processing</h2>
                <p>FR {{ $record->reference_number }} · Preliminary document workspace</p>
            </div>
        </div>
        <div>
            <span class="overall-badge">{{ $fea->overallStatus()->value }}</span>
        </div>
    </header>

    @if(! $readOnlyViewer && ! $isReady)
        <div class="fea-lock-notice" role="status">
            <i class="mdi mdi-lock-clock" aria-hidden="true"></i>
            <div>
                <strong>Action Required:</strong>
                <div>{{ $readiness->denialMessage() }}</div>
                <div>Final FEA documents and photos can be uploaded after PSWDO enrollment is completed.</div>
            </div>
        </div>
    @endif

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="validation-summary mb-4">
            <strong>The preliminary metadata was not saved.</strong>
            <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @unless($readOnlyViewer)
    <section class="card fea-panel fea-checklist" aria-label="Document Checklist">
                <div class="card-header">
                    <h3 class="fea-title">
                        <i class="mdi mdi-clipboard-text text-primary"></i>
                        <span>Document Checklist</span>
                    </h3>
                    <span class="badge bg-light text-muted border rounded-pill font-weight-bold" style="font-size:0.72rem;">
                        {{ $fea->documents->count() }} Total
                    </span>
                </div>
                <div class="card-body">
                    <div class="doc-nav-list" role="tablist" aria-label="FEA documents">
                        @foreach($fea->documents as $index => $document)
                            <button type="button"
                                    class="doc-nav-btn {{ $index === 0 ? 'active' : '' }}"
                                    data-fea-tab-target="document-pane-{{ $document->id }}"
                                    id="document-tab-{{ $document->id }}"
                                    role="tab"
                                    aria-controls="document-pane-{{ $document->id }}"
                                    aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                                <div class="doc-nav-left">
                                    <span class="doc-nav-num">{{ $index + 1 }}</span>
                                    <div>
                                        <div class="doc-nav-title">{{ $document->document_type->label() }}</div>
                                        <small style="font-size:0.6875rem; opacity:0.85;">
                                             {{ $document->is_required ? 'Required' : 'Optional' }}
                                        </small>
                                    </div>
                                </div>
                                <span class="doc-nav-status">{{ $document->status->value }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
    </section>
    @endunless

    <div class="process-workspace-grid">
    <main class="process-workspace-main">
            <section class="card fea-panel" aria-label="Selected FEA document">
                <div class="card-body">
                    @foreach($fea->documents as $index => $document)
                        <div class="fea-doc-pane {{ $index === 0 ? '' : 'd-none' }}" id="document-pane-{{ $document->id }}" role="tabpanel" aria-labelledby="document-tab-{{ $document->id }}">
                            <div class="requirement-card requirement" id="document-{{ $document->id }}">
                                <div class="requirement-header">
                                    <div>
                                        <div class="requirement-name">{{ $document->document_type->label() }}</div>
                                        <div class="requirement-meta">
                                            <span class="badge {{ $document->is_required ? 'badge-info' : 'badge-light' }} text-dark border px-2 py-0">
                                                {{ $document->is_required ? 'Required' : 'Optional' }}
                                            </span>
                                            <span>Compliance: <strong>{{ $document->compliance_status->value }}</strong></span>
                                        </div>
                                    </div>
                                    <span class="pending-badge">{{ $document->status->value }}</span>
                                </div>

                                @if($readOnlyViewer)
                                    @php($documentPreview = $documentPreviewUrls[$document->id])
                                    @if($documentPreview['image'])
                                        <img class="fea-photo-preview" src="{{ $documentPreview['url'] }}" alt="{{ $document->document_type->label() }}">
                                    @else
                                        <iframe title="{{ $document->document_type->label() }}" src="{{ $documentPreview['url'] }}" style="width:100%;min-height:72vh;border:1px solid #e2e8f0;border-radius:10px;background:#fff;"></iframe>
                                    @endif
                                @elseif($document->document_type->hasDraftEditor())
                                    @php($draftEditable = $draftEditors[$document->id]['editable'])
                                    <section class="fea-draft-inputs" aria-label="{{ $document->document_type->label() }} inputs">
                                        <h3>Draft Information</h3>
                                        <p class="text-muted small">{{ $draftEditable ? 'Save your entries before previewing the official document.' : 'Completed document content is shown read-only.' }}</p>
                                        @if($draftEditable && $document->document_type === \App\Enums\Ib39FeaDocumentType::Justification)
                                            @include('ib39.fea.partials.supporting-photo-upload', ['slot' => \App\Enums\Ib39FeaUploadSlot::JustificationSurrendered, 'render' => 'form', 'showHistory' => false])
                                            @include('ib39.fea.partials.supporting-photo-upload', ['slot' => \App\Enums\Ib39FeaUploadSlot::JustificationComparison, 'render' => 'form', 'showHistory' => false])
                                        @endif
                                        <form @if($draftEditable) method="POST" action="{{ route('ib39.fea.documents.draft.update', [$fea, $document]) }}" data-fea-draft-form @endif>
                                            @if($draftEditable)
                                                @csrf @method('PUT')
                                                <input type="hidden" name="revision" value="{{ $document->draft_revision }}">
                                            @else
                                                <fieldset disabled aria-label="Completed {{ $document->document_type->label() }} content">
                                            @endif
                                            @include('ib39.fea.partials.draft-inputs', ['fields' => $draftEditors[$document->id]['fields'], 'draft' => $draftEditors[$document->id]['draft'], 'showHistory' => false, 'readOnly' => ! $draftEditable])
                                            @unless($draftEditable)</fieldset>@endunless
                                            @if($draftEditable)
                                                <div class="document-actions"><button class="btn btn-primary" type="submit">Save Draft</button></div>
                                            @endif
                                        </form>
                                        @can('viewDraft', [$document, $fea])
                                            <a class="btn btn-action-outline" href="{{ route('ib39.fea.documents.draft.preview', [$fea, $document]) }}">Preview</a>
                                        @endcan
                                    </section>
                                @else
                                    @php($photo = $document->currentFinalVersion ?? $document->currentDraftVersion)
                                    <section aria-label="{{ $document->document_type->label() }} photograph">
                                        @if($photo)
                                            <img class="fea-photo-preview" src="{{ route('ib39.fea.documents.versions.preview', [$fea, $document, $photo]) }}" alt="{{ $document->document_type->label() }}">
                                        @else
                                            <p class="text-muted">No photograph has been uploaded for this requirement.</p>
                                        @endif
                                    </section>
                                @endif

                                @unless($readOnlyViewer)
                                    @include('ib39.fea.partials.uploads', ['showHistory' => false])
                                @endunless

                                @if($canProcess[$document->id])
                                    @if($document->status === \App\Enums\Ib39FeaDocumentStatus::Pending)
                                        <form method="POST" action="{{ route('ib39.fea.documents.start', [$fea, $document]) }}" class="mt-3">
                                            @csrf
                                            <button class="btn btn-sm btn-primary font-weight-bold" type="submit">
                                                <i class="mdi mdi-play me-1"></i> Start Preliminary Work
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('ib39.fea.documents.update', [$fea, $document]) }}" class="preliminary-form">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="document[status]" value="Processing">
                                            <div class="form-group mb-2">
                                                <label for="compliance-{{ $document->id }}">Compliance status</label>
                                                <select id="compliance-{{ $document->id }}" name="document[compliance_status]" class="form-control">
                                                    <option @selected($document->compliance_status->value === 'None')>None</option>
                                                    <option @selected($document->compliance_status->value === 'Returned for Compliance')>Returned for Compliance</option>
                                                    <option @selected($document->compliance_status->value === 'Has Issue')>Has Issue</option>
                                                </select>
                                            </div>
                                            <div class="form-group mb-2">
                                                <label for="compliance-reason-{{ $document->id }}">Compliance reason</label>
                                                <textarea id="compliance-reason-{{ $document->id }}" name="document[compliance_reason]" class="form-control" maxlength="2000">{{ $document->compliance_reason }}</textarea>
                                            </div>
                                            <div class="form-group mb-2">
                                                <label for="remarks-{{ $document->id }}">Remarks</label>
                                                <textarea id="remarks-{{ $document->id }}" name="document[remarks]" class="form-control" maxlength="2000">{{ $document->remarks }}</textarea>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input type="hidden" name="document[is_delayed]" value="0">
                                                <input id="delayed-{{ $document->id }}" name="document[is_delayed]" value="1" type="checkbox" class="form-check-input" @checked($document->is_delayed)>
                                                <label for="delayed-{{ $document->id }}" class="form-check-label">Document is delayed</label>
                                            </div>
                                            <div class="form-group mb-3">
                                                <label for="delay-reason-{{ $document->id }}">Reason for delay</label>
                                                <textarea id="delay-reason-{{ $document->id }}" name="document[delay_reason]" class="form-control" maxlength="2000">{{ $document->delay_reason }}</textarea>
                                            </div>
                                            <button class="btn btn-sm btn-primary font-weight-bold" type="submit">Update Preliminary Work</button>
                                        </form>
                                    @endif
                                @endif

                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
    </main>
    @include('components.processing-workspace.sidebar', ['sidebarId' => 'fea', 'comments' => $fea->comments, 'events' => $workspaceEvents, 'commentAction' => route('fea.comments.store', $fea), 'canComment' => $canComment])
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabButtons = document.querySelectorAll('[data-fea-tab-target]');
        const tabPanes = document.querySelectorAll('.fea-doc-pane');

        function activateDocument(button) {
            const targetId = button.getAttribute('data-fea-tab-target');
            tabButtons.forEach(tab => {
                const active = tab === button;
                tab.classList.toggle('active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            tabPanes.forEach(pane => pane.classList.toggle('d-none', pane.id !== targetId));
            try { sessionStorage.setItem('fea-active-document-{{ $fea->id }}', targetId); } catch (error) { /* Storage may be disabled. */ }
        }

        tabButtons.forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                activateDocument(this);
            });
        });

        let selected = new URLSearchParams(window.location.search).get('document');
        if (!selected) {
            try { selected = sessionStorage.getItem('fea-active-document-{{ $fea->id }}')?.replace('document-pane-', ''); } catch (error) { /* Storage may be disabled. */ }
        }
        const selectedButton = Array.from(tabButtons).find(button => button.getAttribute('data-fea-tab-target') === 'document-pane-' + selected);
        if (selectedButton) activateDocument(selectedButton);
    });
</script>
@include('ib39.fea.partials.draft-interactions')
@endpush
@endsection
