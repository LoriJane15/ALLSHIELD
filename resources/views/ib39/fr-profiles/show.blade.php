@extends('layouts.skydash-v')
@section('title', 'View FR Profile')
@section('heading', 'View FR Profile')

@section('content')
<div class="fr-profile-container">
    {{-- Top Navigation & History Breadcrumb --}}
    <div class="module-nav-top">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge" style="background:#eef2ff; color:#4338ca; font-weight:750; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em;">
                <i class="mdi mdi-account-details"></i> FR Profile
            </span>
            <nav aria-label="Breadcrumb">
                <ol class="module-breadcrumb">
                    <li><a href="{{ route('ib39.dashboard') }}"><i class="mdi mdi-view-dashboard-outline me-1"></i>Dashboard</a></li>
                    <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
                    <li><a href="{{ route('ib39.fr-profiles.index') }}">FR Profiles</a></li>
                    <li class="separator"><i class="mdi mdi-chevron-right"></i></li>
                    <li class="active" aria-current="page">{{ $record->reference_number }}</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('ib39.fr-profiles.index') }}" class="module-back-link">
            <i class="mdi mdi-arrow-left"></i>
            <span>Back to FR Profiles</span>
        </a>
    </div>

    <x-surfaced-fr-progress-timeline :phases="$progressTimeline" />

    <x-surfaced-fr-profile :record="$record" />

    <x-surfaced-fr-documents-records :summaries="$documentSummaries" :links="$documentLinks" />

    <section class="profile-card mb-4" aria-labelledby="initial-history-heading">
        <div class="profile-card-header">
            <h2 id="initial-history-heading">
                <i class="mdi mdi-history text-primary"></i>
                <span>Initial Status History</span>
            </h2>
        </div>
        <div class="profile-card-body">
            <div class="history-card-item">
                <div class="history-icon">
                    <i class="mdi mdi-flag-checkered"></i>
                </div>
                <div>
                    <strong class="d-block" style="color: #0f172a; font-size: 0.9rem; font-weight: 750;">{{ $record->overall_case_status }}</strong>
                    <div class="text-muted small mt-1">
                        <i class="mdi mdi-clock-outline me-1"></i>{{ $record->created_at->format('F d, Y · h:i A') }} &middot; Recorded by <strong>{{ $recordedBy }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="profile-footer-actions">
        @if($record->cdrProcessing)
            <a href="{{ route('ib39.cdr.show', $record->cdrProcessing) }}" class="btn-action-primary">
                <i class="mdi mdi-file-document-edit-outline"></i>
                <span>Open CDR Workspace</span>
            </a>
        @endif
        @if($record->feaProcessing)
            <a href="{{ route('ib39.fea.show', $record->feaProcessing) }}" class="btn-action-secondary">
                <i class="mdi mdi-file-document-outline"></i>
                <span>View FEA Record</span>
            </a>
        @endif
        <a href="{{ route('ib39.fr-profiles.index') }}" class="btn-action-secondary">
            <i class="mdi mdi-arrow-left"></i>
            <span>Back to FR Profiles</span>
        </a>
        @can('cancel', $record)
            <form method="POST" action="{{ route('ib39.fr-profiles.cancel', $record) }}" class="card card-body mt-3 w-100 border-danger-subtle shadow-sm" style="border-radius: 14px; background: #fffafb; border: 1px solid #fecdd3; padding: 1.35rem;">
                @csrf
                <div class="d-flex align-items-center gap-2 mb-2 text-danger">
                    <i class="mdi mdi-alert-circle-outline" style="font-size: 1.25rem;"></i>
                    <label for="cancellation-reason" class="form-label font-weight-bold mb-0" style="color: #9f1239; font-size: 0.9rem;">Cancellation reason</label>
                </div>
                <textarea id="cancellation-reason" name="reason" class="form-control" rows="3" maxlength="2000" placeholder="State the official reason for cancelling this record..." required style="border-radius: 10px; border-color: #fca5a5; background: #ffffff;">{{ old('reason') }}</textarea>
                <div class="d-flex align-items-center gap-2 mt-3 p-2 px-3 rounded" style="background: rgba(254, 226, 226, 0.6); border: 1px solid #fca5a5;">
                    <input id="cancellation-confirmed" name="confirmed" value="1" class="form-check-input mt-0 position-static" type="checkbox" required style="width: 18px; height: 18px; cursor: pointer; accent-color: #dc2626; flex-shrink: 0;">
                    <label class="form-check-label font-weight-bold mb-0" for="cancellation-confirmed" style="font-size: 0.8125rem; color: #7f1d1d; cursor: pointer; user-select: none;">
                        I confirm that I am cancelling the correct surfaced FR.
                    </label>
                </div>
                <button type="submit" class="btn btn-danger mt-3 align-self-start font-weight-bold px-4 py-2" style="border-radius: 8px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);">
                    <i class="mdi mdi-close-circle-outline me-1"></i> Cancel FR
                </button>
            </form>
        @endcan
    </div>
</div>
@endsection

