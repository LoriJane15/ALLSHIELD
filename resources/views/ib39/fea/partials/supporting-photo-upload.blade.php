@php
    $current = $slot === \App\Enums\Ib39FeaUploadSlot::JustificationSurrendered ? $document->currentSurrenderedPhotoVersion : $document->currentSupportingPhotoVersion;
    $versions = $document->versions->where('slot', $slot);
    $routeName = $slot === \App\Enums\Ib39FeaUploadSlot::JustificationSurrendered ? 'ib39.fea.documents.surrendered-versions.store' : 'ib39.fea.documents.comparison-versions.store';
    $section = $slot === \App\Enums\Ib39FeaUploadSlot::JustificationSurrendered ? '3' : '4';
    $formId = 'support-photo-form-'.$section;
@endphp
@if($render === 'form')
    @can('uploadDraft', [$document, $fea])
        <form id="{{ $formId }}" method="POST" enctype="multipart/form-data" action="{{ route($routeName, [$fea, $document]) }}" data-supporting-photo-form>@csrf
            @if($current)<input type="hidden" name="expected_current_version_id" value="{{ $current->id }}">@endif
        </form>
    @endcan
@else
    <div class="compact-upload" data-photo-slot="{{ $slot->value }}">
        @if($current)<div class="current-upload"><strong>Current version {{ $current->version_number }}</strong> — {{ $current->original_filename }} · {{ $current->created_at->format('F d, Y · h:i A') }} · {{ $current->uploader?->name ?? 'User unavailable' }} <a href="{{ route('ib39.fea.documents.versions.preview', [$fea, $document, $current]) }}" target="_blank" rel="noopener">Preview photo</a> <a href="{{ route('ib39.fea.documents.versions.download', [$fea, $document, $current]) }}">Download photo</a></div>@endif
        @can('uploadDraft', [$document, $fea])
            <div class="compact-upload-row d-flex flex-wrap align-items-center mt-2">
                <label class="sr-only" for="support-photo-{{ $section }}">{{ $current ? 'Replacement photo' : 'Photo file' }}</label><input id="support-photo-{{ $section }}" class="form-control-file" type="file" name="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required form="{{ $formId }}">
                @if($current)<div class="replacement-fields w-100 mt-2"><label for="support-reason-{{ $section }}">Reason for replacement</label><textarea id="support-reason-{{ $section }}" name="replacement_reason" maxlength="2000" class="form-control" required form="{{ $formId }}"></textarea></div>@endif
                <button class="btn btn-sm btn-primary" type="submit" form="{{ $formId }}">Upload Photo</button>
            </div>
        @endcan
        <details class="version-history"><summary>View Upload History{{ $versions->isNotEmpty() ? ' ('.$versions->count().')' : '' }}</summary>@forelse($versions as $version)<div><strong>Version {{ $version->version_number }} — DRAFT — NOT FINAL</strong><br>{{ $version->original_filename }} · {{ $version->created_at->format('F d, Y · h:i A') }} · {{ $version->uploader?->name ?? 'User unavailable' }}@if($version->replacement_reason)<br>Replacement reason: {{ $version->replacement_reason }}@endif <a href="{{ route('ib39.fea.documents.versions.preview', [$fea, $document, $version]) }}" target="_blank" rel="noopener">Preview</a> <a href="{{ route('ib39.fea.documents.versions.download', [$fea, $document, $version]) }}">Download</a></div>@empty<div>No uploads recorded.</div>@endforelse</details>
    </div>
@endif
