@extends('layouts.skydash-v')
@section('title', 'JAPIC Certification Profile')
@section('heading', 'JAPIC Certification')

@section('content')
@php($fr = $processing->surfacedFormerRebel)
<div class="fr-profile-container">
    <div class="module-nav-top">
        <a href="{{ route('japic.certifications.index') }}" class="module-back-link"><i class="mdi mdi-arrow-left"></i><span>Back to FRs for Certification</span></a>
        <ol class="module-breadcrumb" aria-label="Breadcrumb"><li><a href="{{ route('japic.dashboard') }}">Dashboard</a></li><li class="separator"><i class="mdi mdi-chevron-right"></i></li><li><a href="{{ route('japic.certifications.index') }}">Certifications</a></li><li class="separator"><i class="mdi mdi-chevron-right"></i></li><li class="active">{{ $fr->reference_number }}</li></ol>
    </div>

    <x-surfaced-fr-progress-timeline :phases="$progressTimeline" />

    <x-surfaced-fr-profile :record="$fr" information-heading="FR Profile Information" />

    @push('styles')
    <style>
        .certification-timeline{display:flex;align-items:flex-start;list-style:none;margin:0;padding:0}.certification-step{position:relative;display:flex;flex:1;align-items:flex-start;gap:.7rem;min-width:0}.certification-step:not(:last-child)::after{content:"";position:absolute;top:17px;left:42px;right:10px;height:3px;background:#dbe3ec}.certification-step.is-complete:not(:last-child)::after{background:#28a745}.certification-marker{position:relative;z-index:1;display:flex;align-items:center;justify-content:center;width:34px;height:34px;flex:0 0 34px;border:2px solid #cbd5e0;border-radius:50%;background:#fff;color:#64748b;font-weight:800}.certification-step.is-complete .certification-marker{border-color:#28a745;background:#28a745;color:#fff}.certification-step.is-next .certification-marker{border-color:#401595;color:#401595;box-shadow:0 0 0 4px rgba(64,21,149,.1)}.certification-stage{padding-top:.1rem}.certification-stage strong,.certification-stage small{display:block}.certification-stage strong{color:#1e293b}.certification-stage small{color:#64748b}.workspace-actions{display:flex;align-items:center;flex-wrap:wrap;gap:.65rem}.workspace-panel{border-top:1px solid #edf1f6;margin-top:1rem;padding-top:1rem}.workspace-upload{max-width:760px}.workspace-upload .form-check{padding-left:1.6rem}.final-document-summary{background:#f8fafc;border:1px solid #edf1f6;border-radius:10px;padding:1rem}
        @media(max-width:767px){.certification-timeline{flex-direction:column;gap:1rem}.certification-step:not(:last-child)::after{top:34px;bottom:-16px;left:16px;right:auto;width:3px;height:auto}.workspace-actions{align-items:stretch;flex-direction:column}.workspace-actions a,.workspace-actions button{width:100%}}
    </style>
    @endpush


    <section class="profile-card mb-4" aria-labelledby="certification-status-heading"><div class="profile-card-header"><h2 id="certification-status-heading"><i class="mdi mdi-timeline-check-outline"></i>JAPIC Certification Timeline</h2><span class="badge badge-info">{{ $processing->status->value }}</span></div><div class="profile-card-body">
        @php($timelineProgress = $processing->timeline_progress)
        @php($isCancelled = $fr->cancellation !== null)
        @if($isCancelled)
            <div class="alert alert-warning" role="status">Certification processing stopped because the FR was cancelled.</div>
        @endif
        <ol class="certification-timeline" aria-label="Certification progress">
            @foreach(['Drafting', 'For Signature', 'Completed'] as $position => $stage)
                @php($step = $position + 1)
                @php($completed = $timelineProgress >= $step)
                @php($next = ! $isCancelled && ! $completed && $timelineProgress + 1 === $step)
                <li class="certification-step {{ $completed ? 'is-complete' : ($next ? 'is-next' : '') }}">
                    <span class="certification-marker" aria-hidden="true">@if($completed)<i class="mdi mdi-check"></i>@else{{ $step }}@endif</span>
                    <span class="certification-stage"><strong>{{ $stage }}</strong><small>{{ $completed ? 'Completed' : ($next ? 'Next stage' : 'Pending') }}</small></span>
                </li>
            @endforeach
        </ol>
    </div></section>

    <x-surfaced-fr-documents-records :summaries="$documentSummaries" :links="$documentLinks" />

    <section class="profile-card" aria-labelledby="certification-workspace-heading"><div class="profile-card-header"><h2 id="certification-workspace-heading"><i class="mdi mdi-file-certificate-outline"></i>Certification Workspace</h2></div><div class="profile-card-body">
        <p class="text-muted">{{ $processing->draft ? 'Encrypted draft revision '.$processing->draft->revision.' is available.' : 'No certification draft has been saved.' }}</p>
        <div class="workspace-actions">
            @can('editDraft', $processing)<a class="btn-action-primary" href="{{ route('japic.certifications.draft.edit', $processing) }}">{{ $processing->draft ? 'Edit or Continue Draft' : 'Start Draft' }}</a>@endcan
            @can('previewDraft', $processing)
                <a class="btn-action-secondary" href="{{ route('japic.certifications.preview', $processing) }}">Preview Draft</a>
                <a class="btn-action-secondary" href="{{ route('japic.certifications.print', $processing) }}">Print Draft</a>
            @endcan
            @if($processing->status === \App\Enums\JapicCertificationStatus::Drafting && $processing->draft)
                @can('submitForSigning', $processing)
                    <form method="POST" action="{{ route('japic.certifications.submit-for-signing', $processing) }}">@csrf
                        <input type="hidden" name="revision" value="{{ $processing->draft->revision }}">
                        <input type="hidden" name="lock_version" value="{{ $processing->lock_version }}">
                        @if($processing->delayed)<label class="sr-only" for="submit-delay">Delay reason</label><textarea id="submit-delay" name="delay_reason" class="form-control mb-2" maxlength="2000" placeholder="Delay reason" required></textarea>@endif
                        <button class="btn btn-warning" type="submit">Submit / Mark as For Signature</button>
                    </form>
                @endcan
            @endif
            @if(! in_array($processing->status, [\App\Enums\JapicCertificationStatus::Pending, \App\Enums\JapicCertificationStatus::Completed], true))
                <a class="btn-action-secondary" href="{{ route('japic.certifications.history', $processing) }}">View Certification History</a>
            @endif
        </div>

        @can('uploadFinal', $processing)
            <div class="workspace-panel workspace-upload">
                <h3 class="h6 font-weight-bold">Upload Final Signed Certification</h3>
                <p class="text-muted small">Upload the completed PDF only. The system relies on your confirmation and does not use OCR or automated signature recognition.</p>
                <form method="POST" action="{{ route('japic.certifications.final-document.upload', $processing) }}" enctype="multipart/form-data">@csrf
                    <input type="hidden" name="revision" value="{{ $processing->draft?->revision ?? 0 }}">
                    <input type="hidden" name="lock_version" value="{{ $processing->lock_version }}">
                    <div class="form-group"><label for="final-document" class="font-weight-bold">Final signed PDF</label><input id="final-document" class="form-control-file" type="file" name="document" accept="application/pdf,.pdf" required></div>
                    <div class="form-check mb-2"><input id="all-signatories-confirmed" class="form-check-input" type="checkbox" name="all_signatories_confirmed" value="1" required><label class="form-check-label" for="all-signatories-confirmed">I confirm this is the final signed certification and the required Prepared By and Attested By personnel and ranks are present in the uploaded document.</label></div>
                    <div class="form-check mb-3"><input id="correct-final-confirmed" class="form-check-input" type="checkbox" name="correct_final_confirmed" value="1" required><label class="form-check-label" for="correct-final-confirmed">I confirm this PDF is the correct final certification for {{ $fr->reference_number }} &mdash; {{ $fr->display_name }}.</label></div>
                    <button class="btn btn-success" type="submit">Upload Final Signed Certification</button>
                </form>
            </div>
        @endcan

        @if($processing->status === \App\Enums\JapicCertificationStatus::Completed && $processing->currentFinalVersion)
            <div class="workspace-panel final-document-summary">
                <strong>Final certification version {{ $processing->currentFinalVersion->version_number }}</strong>
                <div class="text-muted small mb-2">{{ $processing->currentFinalVersion->original_filename }} &middot; {{ $processing->currentFinalVersion->uploaded_at->format('M d, Y h:i A') }}</div>
                <div class="workspace-actions">
                    @can('preview', $processing->currentFinalVersion)<a class="btn-action-primary" href="{{ route('japic.certifications.document-versions.preview', [$processing, $processing->currentFinalVersion]) }}">View Final Certification</a>@endcan
                    @can('download', $processing->currentFinalVersion)<a class="btn-action-secondary" href="{{ route('japic.certifications.document-versions.download', [$processing, $processing->currentFinalVersion]) }}">Download Final Certification</a>@endcan
                    <a class="btn-action-secondary" href="{{ route('japic.certifications.history', $processing) }}">View Certification History</a>
                </div>
            </div>
        @endif
    </div></section>
</div>
@endsection
