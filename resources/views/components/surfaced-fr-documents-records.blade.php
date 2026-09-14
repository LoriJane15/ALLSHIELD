@props(['summaries', 'links'])

<section class="profile-card mb-4" aria-labelledby="documents-records-heading">
    <div class="profile-card-header">
        <h2 id="documents-records-heading">
            <i class="mdi mdi-folder-multiple-outline text-primary" aria-hidden="true"></i>
            <span>Documents/Records</span>
        </h2>
    </div>
    <div class="profile-card-body">
        <div class="workflow-tile-list">
            @foreach($summaries as $key => $summary)
                <a class="workflow-tile text-decoration-none" href="{{ $links[$key] }}" aria-label="Open {{ $summary['label'] }} records">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <strong class="mb-0">{{ $summary['label'] }}</strong>
                        <i class="mdi mdi-file-document-outline text-primary" style="font-size: 1.1rem; opacity: 0.75;"></i>
                    </div>
                    <span>{{ $summary['status'] }}</span>
                    <span class="mt-2 text-primary font-weight-bold d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;">
                        Open records <i class="mdi mdi-chevron-right" aria-hidden="true"></i>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

