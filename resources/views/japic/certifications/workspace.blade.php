@extends('layouts.skydash-v')
@section('title', 'JAPIC Certification')
@section('heading', 'JAPIC Certification')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
    .workspace-upload { margin-bottom: 1.25rem; }
    .workspace-upload .form-check { padding-left: 1.6rem; }
    .japic-final-actions { align-items:center; background:#fff; border:1px solid #e2e8f0; border-radius:12px; display:flex; flex-wrap:wrap; gap:.9rem; justify-content:space-between; padding:1rem 1.15rem; }
    .japic-final-upload-form { border-top:1px solid #f1f5f9; flex:0 0 100%; padding-top:1rem; }
    .japic-final-upload-form .form-group { max-width:620px; }
    .personnel-row { padding: 1rem 1.25rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; margin-bottom: 0.75rem; }
    .personnel-row:hover { background: #ffffff; border-color: #cbd5e1; }
    .certification-document-frame { width: 100%; min-height: 680px; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; }
</style>
@include('components.processing-workspace.styles')
@endpush

@section('content')
@php($fr = $processing->surfacedFormerRebel)
<div class="fr-profile-container">
    <div class="module-nav-top">
        <a href="{{ route('japic.certifications.show', $processing) }}" class="module-back-link"><i class="mdi mdi-arrow-left"></i> Back to Certification Profile</a>
    </div>
    <header class="process-workspace-header">
        <div><h1>JAPIC Certification</h1><p>{{ $fr->reference_number }} &middot; {{ $fr->display_name }}</p></div>
        <span class="process-workspace-status">{{ $processing->status->value }}</span>
    </header>

    @if(session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="process-workspace-grid">
        <main class="process-workspace-main">
            @if($responsibleProcessor && $processing->currentFinalVersion)
                <div class="workspace-upload" id="japic-final-upload">
                    <section class="japic-final-actions" aria-label="Final JAPIC certification actions">
                        <div>
                            <strong class="d-block text-dark">Uploaded Final Certification</strong>
                            <small class="text-muted">{{ $processing->currentFinalVersion->original_filename }} &middot; Final file has already been uploaded.</small>
                        </div>
                        <a class="btn btn-primary btn-sm" href="{{ route('japic.certifications.document-versions.preview', [$processing, $processing->currentFinalVersion]) }}" target="_blank" rel="noopener">
                            <i class="mdi mdi-eye-outline mr-1"></i> View Uploaded Certification
                        </a>
                    </section>
                </div>
            @elseif($canUploadFinal)
                <div class="workspace-upload" id="japic-final-upload">
                    <section class="japic-final-actions" aria-label="Upload final JAPIC certification">
                        <div>
                            <strong class="d-block text-dark">Upload Certification</strong>
                            <small class="text-muted">Upload the completed signed PDF when it is ready.</small>
                        </div>
                        <form class="japic-final-upload-form" method="POST" action="{{ route('japic.certifications.final-document.upload', $processing) }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="revision" value="{{ $processing->draft?->revision ?? 0 }}">
                            <input type="hidden" name="lock_version" value="{{ $processing->lock_version }}">
                            <div class="form-group">
                                <label for="final-document" class="mblrc-label">Final signed PDF</label>
                                <input id="final-document" class="form-control mblrc-input" type="file" name="document" accept="application/pdf,.pdf" required>
                            </div>
                            <div class="form-check mb-2">
                                <input id="all-signatories-confirmed" class="form-check-input" type="checkbox" name="all_signatories_confirmed" value="1" required>
                                <label class="form-check-label small" for="all-signatories-confirmed">I confirm this is the final signed certification and the required Prepared By and Attested By personnel and ranks are present in the uploaded document.</label>
                            </div>
                            <div class="form-check mb-3">
                                <input id="correct-final-confirmed" class="form-check-input" type="checkbox" name="correct_final_confirmed" value="1" required>
                                <label class="form-check-label small" for="correct-final-confirmed">I confirm this PDF is the correct final certification for {{ $fr->reference_number }} &mdash; {{ $fr->display_name }}.</label>
                            </div>
                            <button class="btn btn-primary" type="submit"><i class="mdi mdi-upload mr-1"></i> Upload Certification</button>
                        </form>
                    </section>
                </div>
            @endif

            @if($draftReadOnly)
                @include('japic.certifications.partials.draft-editor')
            @elseif($canPreviewFinal)
                <iframe class="certification-document-frame" title="Uploaded final JAPIC certification" src="{{ route('japic.certifications.document-versions.preview', ['japicCertificationProcessing' => $processing, 'version' => $processing->currentFinalVersion, 'file' => 1]) }}" loading="lazy"></iframe>
            @elseif($canEditDraft)
                @include('japic.certifications.partials.draft-editor')
                @if($canSubmitForSigning)
                    <form method="POST" action="{{ route('japic.certifications.submit-for-signing', $processing) }}" class="mb-4">
                        @csrf
                        <input type="hidden" name="revision" value="{{ $processing->draft->revision }}">
                        <input type="hidden" name="lock_version" value="{{ $processing->lock_version }}">
                        @if($processing->delayed)
                            <label for="submit-delay" class="mblrc-label">Delay reason</label>
                            <textarea id="submit-delay" name="delay_reason" class="form-control mb-2" maxlength="2000" required></textarea>
                        @endif
                        <button class="btn btn-warning font-weight-bold" type="submit"><i class="mdi mdi-send mr-1"></i> Submit / Mark as For Signature</button>
                    </form>
                @endif
            @elseif($canPreviewDraft)
                <iframe class="certification-document-frame" title="Official JAPIC certification draft preview" src="{{ route('japic.certifications.preview', ['japicCertificationProcessing' => $processing, 'embedded' => 1]) }}" loading="lazy"></iframe>
            @else
                <section class="profile-card"><div class="profile-card-body">Certification document is not yet available.</div></section>
            @endif
        </main>
        @include('components.processing-workspace.sidebar', ['sidebarId' => 'japic-workspace', 'comments' => $processing->comments, 'events' => $workspaceEvents, 'commentAction' => route('japic.certifications.comments.store', $processing), 'canComment' => $canComment])
    </div>
</div>
@endsection
