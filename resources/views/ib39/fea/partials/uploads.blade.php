@php
    $primary = $document->currentDraftVersion;
    $isPhoto = in_array($document->document_type, [\App\Enums\Ib39FeaDocumentType::FirearmPhoto, \App\Enums\Ib39FeaDocumentType::FrWithFirearmPhoto], true);
    $primaryVersions = $document->versions->where('slot', \App\Enums\Ib39FeaUploadSlot::Primary);
    $canUpload = auth()->user()->can('uploadDraft', [$document, $fea]);
    $final = $document->currentFinalVersion;
    $canUploadFinal = auth()->user()->can('uploadFinal', [$document, $fea]);
@endphp
<section class="compact-upload" aria-label="{{ $document->document_type->label() }} upload controls">
    @if($final)
        <div class="current-upload"><strong>Final version {{ $final->version_number }}</strong><span>{{ $final->original_filename }} · {{ $final->created_at->format('F d, Y · h:i A') }} · {{ $final->uploader?->name ?? 'User unavailable' }}</span></div>
        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('ib39.fea.documents.versions.preview', [$fea, $document, $final]) }}">Preview Final</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('ib39.fea.documents.versions.download', [$fea, $document, $final]) }}">Download Final</a>
    @elseif($canUploadFinal)
        <form method="POST" enctype="multipart/form-data" action="{{ route('ib39.fea.documents.final-versions.store', [$fea, $document]) }}" class="compact-upload-row d-flex flex-wrap align-items-center">@csrf
            <label for="final-file-{{ $document->id }}">Upload Final {{ $document->document_type->label() }}</label>
            <input id="final-file-{{ $document->id }}" class="form-control-file" type="file" name="file" accept="{{ $isPhoto ? '.jpg,.jpeg,.png,image/jpeg,image/png' : '.pdf,application/pdf' }}" required>
            <button class="btn btn-sm btn-primary" type="submit">Upload Final {{ $document->document_type->label() }}</button>
        </form>
    @endif
    @if($primary)
        <div class="current-upload"><strong>Current: Version {{ $primary->version_number }}</strong><span>{{ $primary->original_filename }} · {{ $primary->mime_type }} · {{ number_format($primary->size_bytes / 1024, 1) }} KiB · {{ $primary->created_at->format('F d, Y · h:i A') }} · {{ $primary->uploader?->name ?? 'User unavailable' }}</span></div>
        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('ib39.fea.documents.versions.preview', [$fea, $document, $primary]) }}">Preview {{ $isPhoto ? 'Current Photo' : 'Draft' }}</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('ib39.fea.documents.versions.download', [$fea, $document, $primary]) }}">Download {{ $isPhoto ? 'Photo' : 'Draft' }}</a>
    @endif
    @if($isPhoto && $canUpload)
        <form method="POST" enctype="multipart/form-data" action="{{ route('ib39.fea.documents.versions.store', [$fea, $document]) }}" class="compact-upload-row d-flex flex-wrap align-items-center">@csrf
            @if($primary)<input type="hidden" name="expected_current_version_id" value="{{ $primary->id }}">@endif
            <label class="sr-only" for="draft-file-{{ $document->id }}">{{ $primary ? 'Replacement photo' : 'Photo file' }}</label><input id="draft-file-{{ $document->id }}" class="form-control-file" type="file" name="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
            @if($primary)<div class="replacement-fields"><label for="replacement-reason-{{ $document->id }}">Reason for replacement</label><textarea id="replacement-reason-{{ $document->id }}" name="replacement_reason" maxlength="2000" class="form-control" required></textarea></div>@endif
            <button class="btn btn-sm btn-primary" type="submit">Upload Photo — {{ $document->document_type->label() }}</button>
        </form>
    @endif
    <details class="version-history"><summary>View Upload History{{ $primaryVersions->isNotEmpty() ? ' ('.$primaryVersions->count().')' : '' }}</summary>
        @if($primaryVersions->isNotEmpty())
            @foreach($primaryVersions as $version)<div><strong>Version {{ $version->version_number }} — DRAFT — NOT FINAL</strong><span>{{ $version->original_filename }} · {{ $version->mime_type }} · {{ number_format($version->size_bytes / 1024, 1) }} KiB · {{ $version->created_at->format('F d, Y · h:i A') }} · {{ $version->uploader?->name ?? 'User unavailable' }}</span>@if($version->replacement_reason)<span>Replacement reason: {{ $version->replacement_reason }}</span>@endif <a href="{{ route('ib39.fea.documents.versions.preview', [$fea, $document, $version]) }}" target="_blank" rel="noopener">Preview</a> <a href="{{ route('ib39.fea.documents.versions.download', [$fea, $document, $version]) }}">Download</a></div>@endforeach
        @else
            <div>No uploads recorded.</div>
        @endif
    </details>
</section>
