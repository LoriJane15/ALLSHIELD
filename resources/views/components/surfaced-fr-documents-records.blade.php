@props(['summaries', 'links'])

<section class="profile-card mb-4" aria-labelledby="documents-records-heading">
    <div class="profile-card-header"><h2 id="documents-records-heading"><i class="mdi mdi-folder-multiple-outline"></i>Documents/Records</h2></div>
    <div class="profile-card-body"><div class="workflow-tile-list">
        @foreach($summaries as $key => $summary)
            @if($key === 'pswdo')
                <div class="workflow-tile">
                    <strong>{{ $summary['label'] }}</strong>
                    <span>{{ $summary['status'] }}</span>
                    <span>{{ $summary['availability'] }}</span>

                    <div class="mt-2">
                        @foreach($summary['documents'] as $document)
                            <div class="border-top py-2">
                                <strong class="d-block">{{ $document['label'] }}</strong>
                                <span class="d-block">{{ $document['status'] }}</span>
                                <span class="d-block">{{ $document['availability'] }}</span>
                                @if($document['previewUrl'] && $document['downloadUrl'])
                                    <div class="mt-2 d-flex flex-wrap gap-2">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ $document['previewUrl'] }}">Preview</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ $document['downloadUrl'] }}">Download</a>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <a class="workflow-tile text-decoration-none" href="{{ $links[$key] }}">
                    <strong>{{ $summary['label'] }}</strong>
                    <span>{{ $summary['status'] }}</span>
                    <span>{{ $summary['availability'] }}</span>
                </a>
            @endif
        @endforeach
    </div></div>
</section>
