@extends('layouts.skydash-v')
@section('title', 'JAPIC Certification Profile')
@section('heading', 'JAPIC Certification')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
    .certification-timeline {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        list-style: none;
        margin: 0;
        padding: 0.75rem 0;
        position: relative;
    }
    .certification-step {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        flex: 1;
        min-width: 0;
        padding: 0 0.5rem;
        z-index: 1;
    }
    .certification-step:not(:last-child)::after {
        content: "";
        position: absolute;
        top: 19px;
        left: 50%;
        width: 100%;
        height: 3px;
        background: #e2e8f0;
        z-index: 1;
    }
    .certification-step.is-complete:not(:last-child)::after {
        background: #312e81;
    }
    .certification-marker {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        border: 2px solid #cbd5e1;
        border-radius: 50%;
        background: #ffffff;
        color: #64748b;
        font-weight: 800;
        font-size: 0.875rem;
        box-shadow: 0 2px 4px rgba(15, 23, 42, 0.04);
        margin-bottom: 0.65rem;
        transition: all 0.2s ease;
    }
    .certification-step.is-complete .certification-marker {
        border-color: #312e81;
        background: #312e81;
        color: #ffffff;
        box-shadow: 0 0 0 4px rgba(49, 46, 129, 0.15);
    }
    .certification-step.is-next .certification-marker {
        border-color: #2563eb;
        background: #ffffff;
        color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
        transform: scale(1.05);
    }
    .certification-stage {
        position: relative;
        z-index: 2;
        min-width: 0;
    }
    .certification-stage strong {
        display: block;
        color: #0f172a;
        font-size: 0.9375rem;
        font-weight: 750;
        line-height: 1.3;
    }
    .certification-stage small {
        display: block;
        color: #64748b;
        font-size: 0.775rem;
        font-weight: 600;
        line-height: 1.3;
        margin-top: 0.2rem;
    }
    .workspace-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.65rem;
    }
    .workspace-panel {
        border-top: 1px solid #e2e8f0;
        margin-top: 1.25rem;
        padding-top: 1.25rem;
    }
    .workspace-upload {
        max-width: 760px;
    }
    .workspace-upload .form-check {
        padding-left: 1.6rem;
    }
    .final-document-summary {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem;
    }
    @media(max-width: 767px) {
        .certification-timeline {
            flex-direction: column;
            gap: 1.25rem;
            align-items: flex-start;
        }
        .certification-step {
            flex-direction: row;
            align-items: center;
            text-align: left;
            gap: 1rem;
            width: 100%;
            padding: 0;
        }
        .certification-step:not(:last-child)::after {
            top: 38px;
            bottom: -20px;
            left: 18px;
            width: 3px;
            height: auto;
            right: auto;
        }
        .certification-marker {
            margin-bottom: 0;
        }
        .workspace-actions {
            align-items: stretch;
            flex-direction: column;
        }
        .workspace-actions a,
        .workspace-actions button {
            width: 100%;
        }
    }
</style>
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

    {{-- Timeline Card --}}
    <section class="profile-card mb-4" aria-labelledby="certification-status-heading">
        <div class="profile-card-header d-flex justify-content-between align-items-center">
            <h2 id="certification-status-heading">
                <i class="mdi mdi-history" style="color: #312e81;"></i>
                JAPIC Certification Timeline
            </h2>
            <span class="badge badge-info" style="font-weight: 750; border-radius: 6px; padding: 0.35rem 0.75rem;">
                {{ $processing->status->value }}
            </span>
        </div>
        <div class="profile-card-body">
            @php($timelineProgress = $processing->timeline_progress)
            @php($isCancelled = $fr->cancellation !== null)
            @if($isCancelled)
                <div class="alert alert-warning" role="status">
                    <i class="mdi mdi-alert mr-1"></i> Certification processing stopped because the FR was cancelled.
                </div>
            @endif
            <ol class="certification-timeline" aria-label="Certification progress">
                @foreach(['Drafting', 'For Signature', 'Completed'] as $position => $stage)
                    @php($step = $position + 1)
                    @php($completed = $timelineProgress >= $step)
                    @php($next = ! $isCancelled && ! $completed && $timelineProgress + 1 === $step)
                    <li class="certification-step {{ $completed ? 'is-complete' : ($next ? 'is-next' : '') }}">
                        <span class="certification-marker" aria-hidden="true">
                            @if($completed)<i class="mdi mdi-check"></i>@else{{ $step }}@endif
                        </span>
                        <span class="certification-stage">
                            <strong>{{ $stage }}</strong>
                            <small>{{ $completed ? 'Completed' : ($next ? 'Next stage' : 'Pending') }}</small>
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <x-surfaced-fr-documents-records :summaries="$documentSummaries" :links="$documentLinks" />

    {{-- Workspace Card --}}
    <section class="profile-card" aria-labelledby="certification-workspace-heading">
        <div class="profile-card-header">
            <h2 id="certification-workspace-heading">
                <i class="mdi mdi-certificate" style="color: #312e81;"></i>
                Certification Workspace
            </h2>
        </div>
        <div class="profile-card-body">
            <p class="text-muted font-weight-bold mb-3">
                {{ $processing->draft ? 'Encrypted draft revision '.$processing->draft->revision.' is available.' : 'No certification draft has been saved.' }}
            </p>
            <div class="workspace-actions">
                @can('editDraft', $processing)
                    <a class="btn-action-primary" href="{{ route('japic.certifications.draft.edit', $processing) }}" style="background: #312e81; color: #ffffff; border-color: #312e81;">
                        <i class="mdi mdi-pencil mr-1"></i> {{ $processing->draft ? 'Edit or Continue Draft' : 'Start Draft' }}
                    </a>
                @endcan
                @can('previewDraft', $processing)
                    <a class="btn-action-secondary" href="{{ route('japic.certifications.preview', $processing) }}">
                        <i class="mdi mdi-eye-outline mr-1"></i> Preview Draft
                    </a>
                    <a class="btn-action-secondary" href="{{ route('japic.certifications.print', $processing) }}">
                        <i class="mdi mdi-printer mr-1"></i> Print Draft
                    </a>
                @endcan
                @if($processing->status === \App\Enums\JapicCertificationStatus::Drafting && $processing->draft)
                    @can('submitForSigning', $processing)
                        <form method="POST" action="{{ route('japic.certifications.submit-for-signing', $processing) }}">@csrf
                            <input type="hidden" name="revision" value="{{ $processing->draft->revision }}">
                            <input type="hidden" name="lock_version" value="{{ $processing->lock_version }}">
                            @if($processing->delayed)
                                <label class="sr-only" for="submit-delay">Delay reason</label>
                                <textarea id="submit-delay" name="delay_reason" class="form-control mb-2" maxlength="2000" placeholder="Delay reason" required></textarea>
                            @endif
                            <button class="btn btn-warning font-weight-bold" type="submit" style="border-radius: 8px; padding: 0.55rem 1.15rem;">
                                <i class="mdi mdi-send mr-1"></i> Submit / Mark as For Signature
                            </button>
                        </form>
                    @endcan
                @endif
                @if(! in_array($processing->status, [\App\Enums\JapicCertificationStatus::Pending, \App\Enums\JapicCertificationStatus::Completed], true))
                    <a class="btn-action-secondary" href="{{ route('japic.certifications.history', $processing) }}">
                        <i class="mdi mdi-history mr-1"></i> View Certification History
                    </a>
                @endif
            </div>

            @can('uploadFinal', $processing)
                <div class="workspace-panel workspace-upload">
                    <h3 class="h6 font-weight-bold" style="color: #0f172a;">Upload Final Signed Certification</h3>
                    <p class="text-muted small">Upload the completed PDF only. The system relies on your confirmation and does not use OCR or automated signature recognition.</p>
                    <form method="POST" action="{{ route('japic.certifications.final-document.upload', $processing) }}" enctype="multipart/form-data">@csrf
                        <input type="hidden" name="revision" value="{{ $processing->draft?->revision ?? 0 }}">
                        <input type="hidden" name="lock_version" value="{{ $processing->lock_version }}">
                        <div class="form-group">
                            <label for="final-document" class="mblrc-label">Final signed PDF</label>
                            <input id="final-document" class="form-control mblrc-input" type="file" name="document" accept="application/pdf,.pdf" required>
                        </div>
                        <div class="form-check mb-2">
                            <input id="all-signatories-confirmed" class="form-check-input" type="checkbox" name="all_signatories_confirmed" value="1" required>
                            <label class="form-check-label small" for="all-signatories-confirmed">
                                I confirm this is the final signed certification and the required Prepared By and Attested By personnel and ranks are present in the uploaded document.
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input id="correct-final-confirmed" class="form-check-input" type="checkbox" name="correct_final_confirmed" value="1" required>
                            <label class="form-check-label small" for="correct-final-confirmed">
                                I confirm this PDF is the correct final certification for {{ $fr->reference_number }} &mdash; {{ $fr->display_name }}.
                            </label>
                        </div>
                        <button class="btn font-weight-bold" type="submit" style="background: #312e81; color: #ffffff; border-radius: 10px; padding: 0.65rem 1.35rem;">
                            <i class="mdi mdi-upload mr-1"></i> Upload Final Signed Certification
                        </button>
                    </form>
                </div>
            @endcan

            @if($processing->status === \App\Enums\JapicCertificationStatus::Completed && $processing->currentFinalVersion)
                <div class="workspace-panel final-document-summary">
                    <strong style="color: #1e1b4b; font-size: 0.95rem;">Final certification version {{ $processing->currentFinalVersion->version_number }}</strong>
                    <div class="text-muted small mb-3">
                        <i class="mdi mdi-file-pdf mr-1"></i> {{ $processing->currentFinalVersion->original_filename }} &middot; {{ $processing->currentFinalVersion->uploaded_at->format('M d, Y h:i A') }}
                    </div>
                    <div class="workspace-actions">
                        @can('preview', $processing->currentFinalVersion)
                            <a class="btn-action-primary" href="{{ route('japic.certifications.document-versions.preview', [$processing, $processing->currentFinalVersion]) }}" style="background: #312e81; color: #ffffff; border-color: #312e81;">
                                <i class="mdi mdi-eye-outline mr-1"></i> View Final Certification
                            </a>
                        @endcan
                        @can('download', $processing->currentFinalVersion)
                            <a class="btn-action-secondary" href="{{ route('japic.certifications.document-versions.download', [$processing, $processing->currentFinalVersion]) }}">
                                <i class="mdi mdi-download mr-1"></i> Download Final Certification
                            </a>
                        @endcan
                        <a class="btn-action-secondary" href="{{ route('japic.certifications.history', $processing) }}">
                            <i class="mdi mdi-history mr-1"></i> View Certification History
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection

