@props(['summaries', 'links'])

<section class="profile-card mb-4" aria-labelledby="documents-records-heading">
    <div class="profile-card-header"><h2 id="documents-records-heading"><i class="mdi mdi-folder-multiple-outline" aria-hidden="true"></i>Documents/Records</h2></div>
    <div class="profile-card-body"><div class="workflow-tile-list">
        @foreach($summaries as $key => $summary)
            <a class="workflow-tile text-decoration-none" href="{{ $links[$key] }}" aria-label="Open {{ $summary['label'] }} records">
                <strong>{{ $summary['label'] }}</strong>
                <span>{{ $summary['status'] }}</span>
                <span class="mt-1">Open records <i class="mdi mdi-chevron-right" aria-hidden="true"></i></span>
            </a>
        @endforeach
    </div></div>
</section>
