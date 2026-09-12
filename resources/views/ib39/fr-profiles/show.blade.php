@extends('layouts.skydash-v')
@section('title', 'View FR Profile')
@section('heading', 'View FR Profile')

@section('content')
<div class="fr-profile-container">
    <div class="module-nav-top">
        <a href="{{ route('ib39.fr-profiles.index') }}" class="module-back-link"><i class="mdi mdi-arrow-left"></i><span>Back to FR Profiles</span></a>
        <ol class="module-breadcrumb" aria-label="Breadcrumb">
            <li><a href="{{ route('ib39.dashboard') }}">Dashboard</a></li><li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li><a href="{{ route('ib39.fr-profiles.index') }}">FR Profiles</a></li><li class="separator"><i class="mdi mdi-chevron-right"></i></li>
            <li class="active" aria-current="page">{{ $record->reference_number }}</li>
        </ol>
    </div>

    <x-surfaced-fr-progress-timeline :phases="$progressTimeline" />

    <x-surfaced-fr-profile :record="$record" />

    <x-surfaced-fr-documents-records :summaries="$documentSummaries" :links="$documentLinks" />

    <section class="profile-card" aria-labelledby="initial-history-heading">
        <div class="profile-card-header"><h2 id="initial-history-heading"><i class="mdi mdi-history"></i>Initial Status History</h2></div>
        <div class="profile-card-body"><div class="history-card-item"><div class="history-icon"><i class="mdi mdi-flag-checkered"></i></div><div>
            <strong>{{ $record->overall_case_status }}</strong>
            <div class="text-muted small">{{ $record->created_at->format('F d, Y · h:i A') }} · Recorded by {{ $recordedBy }}</div>
        </div></div></div>
    </section>

    <div class="profile-footer-actions">
        @if($record->cdrProcessing)
            <a href="{{ route('ib39.cdr.show', $record->cdrProcessing) }}" class="btn-action-primary"><i class="mdi mdi-file-document-edit-outline"></i><span>Open CDR Workspace</span></a>
        @endif
        @if($record->feaProcessing)
            <a href="{{ route('ib39.fea.show', $record->feaProcessing) }}" class="btn-action-secondary"><i class="mdi mdi-file-document-outline"></i><span>View FEA Record</span></a>
        @endif
        <a href="{{ route('ib39.fr-profiles.index') }}" class="btn-action-secondary"><i class="mdi mdi-arrow-left"></i><span>Back to FR Profiles</span></a>
        @can('cancel', $record)
            <form method="POST" action="{{ route('ib39.fr-profiles.cancel', $record) }}" class="card card-body mt-3 w-100">
                @csrf
                <label for="cancellation-reason" class="form-label font-weight-bold">Cancellation reason</label>
                <textarea id="cancellation-reason" name="reason" class="form-control" rows="3" maxlength="2000" required>{{ old('reason') }}</textarea>
                <div class="form-check mt-2"><input id="cancellation-confirmed" name="confirmed" value="1" class="form-check-input" type="checkbox" required><label class="form-check-label" for="cancellation-confirmed">I confirm that I am cancelling the correct surfaced FR.</label></div>
                <button type="submit" class="btn btn-danger mt-3 align-self-start">Cancel FR</button>
            </form>
        @endcan
    </div>
</div>
@endsection
