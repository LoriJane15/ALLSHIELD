@extends('layouts.skydash-v')
@section('title', 'FEA Record')
@section('heading', 'FEA Record')

@push('styles')
<style>
    .fea-workspace{--border:#e2e8f0}.fea-workspace-hero{background:linear-gradient(125deg,#173b74,#2f6fed);border-radius:16px;color:#fff;padding:1.5rem}.fea-workspace-hero h2{color:#fff;font-size:1.5rem;font-weight:700}.fea-panel{border:1px solid var(--border);border-radius:14px;box-shadow:0 4px 16px rgba(23,43,77,.05)}.fea-panel .card-body{padding:1.25rem}.fea-title{border-bottom:1px solid #edf1f6;color:#172b4d;font-size:.95rem;font-weight:700;margin-bottom:1rem;padding-bottom:.65rem}.fea-summary{display:grid;gap:.85rem;grid-template-columns:repeat(3,minmax(0,1fr))}.fea-detail small,.metadata-item small{color:#718096;display:block;font-size:.65rem;font-weight:700;text-transform:uppercase}.fea-detail strong,.metadata-item span{color:#334155;font-size:.8rem}.requirement{border:1px solid #e8edf4;border-radius:12px;margin-bottom:1rem;padding:1rem}.requirement-header{align-items:flex-start;display:flex;gap:1rem;justify-content:space-between}.requirement-name{color:#334155;font-size:.82rem;font-weight:700}.requirement-meta{color:#718096;font-size:.68rem}.pending-badge{background:#f1f5f9;border-radius:14px;color:#52616f;font-size:.65rem;font-weight:700;padding:.3rem .55rem}.disabled-actions{background:#f7f9fc;border:1px dashed #cbd5e1;border-radius:10px;color:#64748b;padding:1rem}.overall-badge{background:#fff5df;border-radius:16px;color:#8a6200;font-size:.7rem;font-weight:700;padding:.4rem .65rem}.metadata-grid{display:grid;gap:.75rem;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:1rem}.metadata-item.full{grid-column:1/-1}.preliminary-form{background:#f8fafc;border-radius:10px;margin-top:1rem;padding:1rem}.preliminary-form label{color:#52616f;font-size:.7rem;font-weight:700}.document-history{border-top:1px solid #edf1f6;margin-top:1rem;padding-top:.8rem}.history-row{border-left:3px solid #cbd5e1;margin:.55rem 0;padding-left:.7rem}.history-row strong{color:#334155;display:block;font-size:.7rem}.history-row span{color:#718096;font-size:.65rem}.empty-history{color:#8492a6;font-size:.7rem}.validation-summary{background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;color:#9f1239;font-size:.75rem;padding:1rem}@media(max-width:767px){.fea-summary,.metadata-grid{grid-template-columns:1fr}.metadata-item.full{grid-column:auto}.requirement-header{flex-direction:column}}
    .document-actions{align-items:center;display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}.compact-upload{margin-top:.75rem}.compact-upload label{color:#52616f;font-size:.7rem;font-weight:700}.compact-upload-row{align-items:center;display:flex;flex-wrap:wrap;gap:.5rem}.current-upload{color:#64748b;font-size:.68rem;margin-bottom:.6rem}.current-upload strong{color:#334155;font-size:.72rem}.version-history{display:inline-block;margin-top:.65rem}.version-history summary{color:#334155;cursor:pointer;font-size:.7rem;font-weight:700}.version-history div{color:#64748b;font-size:.68rem;margin:.6rem 0}.replacement-fields{flex:1 0 100%}.replacement-fields label{display:block}.replacement-fields textarea{max-width:34rem}
</style>
@endpush

@section('content')
@php
    $record = $fea->surfacedFormerRebel;
@endphp
<div class="fea-workspace">
    <header class="fea-workspace-hero mb-4"><div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between" style="gap:1rem"><div><h2 class="mb-1">FEA Record {{ $record->reference_number }}</h2><p class="mb-0">Preliminary document workspace</p></div><span class="overall-badge">{{ $fea->overallStatus()->value }}</span></div></header>
    @unless($isReady)<div class="alert alert-warning" role="status">{{ $readiness->denialMessage() }}</div>@endunless
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="validation-summary mb-4"><strong>The preliminary metadata was not saved.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="row">
        <div class="col-lg-5 mb-4"><section class="card fea-panel h-100"><div class="card-body">
            <h3 class="fea-title">FR Summary</h3><div class="fea-summary">
                <div class="fea-detail"><small>Reference</small><strong>{{ $record->reference_number }}</strong></div><div class="fea-detail"><small>Category</small><strong>{{ $record->category->value }}</strong></div><div class="fea-detail"><small>Firearms</small><strong>{{ $record->possessed_firearms ? 'Yes' : 'No' }}</strong></div>
                <div class="fea-detail"><small>Surfacing date</small><strong>{{ $record->surfaced_at->format('F d, Y') }}</strong></div><div class="fea-detail"><small>Municipality</small><strong>{{ $record->municipality->name }}</strong></div><div class="fea-detail"><small>Barangay</small><strong>{{ $record->barangay?->name ?? 'Not provided' }}</strong></div>
            </div>
        </div></section></div>
        <div class="col-lg-7 mb-4"><section class="card fea-panel h-100"><div class="card-body">
            <h3 class="fea-title">Document Requirements</h3>
            @foreach($fea->documents as $document)
                <div class="requirement" id="document-{{ $document->id }}">
                    <div class="requirement-header"><div><div class="requirement-name">{{ $document->document_type->label() }}</div><div class="requirement-meta">{{ $document->is_required ? 'Required' : 'Optional' }} · Compliance: {{ $document->compliance_status->value }}</div></div><span class="pending-badge">{{ $document->status->value }}</span></div>
                    <div class="metadata-grid">
                        <div class="metadata-item"><small>Started</small><span>{{ $document->started_at?->format('F d, Y · h:i A') ?? 'Not started' }}</span></div><div class="metadata-item"><small>Prepared by</small><span>{{ filled($document->preparer?->name) ? $document->preparer->name : 'Not recorded' }}</span></div>
                        <div class="metadata-item"><small>Last updated</small><span>{{ $document->last_updated_by ? $document->updated_at->format('F d, Y · h:i A') : 'Not updated' }}</span></div><div class="metadata-item"><small>Updated by</small><span>{{ filled($document->lastUpdater?->name) ? $document->lastUpdater->name : 'Not recorded' }}</span></div>
                        <div class="metadata-item full"><small>Remarks</small><span>{{ $document->remarks ?: 'No remarks recorded.' }}</span></div><div class="metadata-item full"><small>Compliance reason</small><span>{{ $document->compliance_reason ?: 'No compliance reason recorded.' }}</span></div><div class="metadata-item full"><small>Delay reason</small><span>{{ $document->delay_reason ?: 'No delay recorded.' }}</span></div>
                    </div>
                    @if($document->document_type->hasDraftEditor())
                        @php
                            $finalUploadLabel = match ($document->document_type) {
                                \App\Enums\Ib39FeaDocumentType::Tir => 'Upload Final TIR',
                                \App\Enums\Ib39FeaDocumentType::Cvif => 'Upload Final CVIF',
                                \App\Enums\Ib39FeaDocumentType::Ptis => 'Upload Final PTIS',
                                \App\Enums\Ib39FeaDocumentType::Justification => 'Upload Final Justification Form',
                                default => null,
                            };
                        @endphp
                        <div class="document-actions">
                            @if($isReady)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('ib39.fea.documents.draft.edit', [$fea, $document]) }}">Open Official Form Editor</a>
                                <button class="btn btn-sm btn-secondary" type="button" disabled>{{ $finalUploadLabel }}</button>
                            @endif
                            @can('viewDraft', [$document, $fea])
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('ib39.fea.documents.draft.preview', [$fea, $document]) }}">Preview Saved Draft</a>
                            @endcan
                        </div>
                    @endif
                    @if($isReady)
                        @if($document->status === \App\Enums\Ib39FeaDocumentStatus::Pending)
                            <form method="POST" action="{{ route('ib39.fea.documents.start', [$fea, $document]) }}" class="mt-3">@csrf<button class="btn btn-sm btn-primary" type="submit">Start Preliminary Work</button></form>
                        @else
                            <form method="POST" action="{{ route('ib39.fea.documents.update', [$fea, $document]) }}" class="preliminary-form">
                                @csrf @method('PATCH')<input type="hidden" name="document[status]" value="Processing">
                                <div class="form-group"><label for="compliance-{{ $document->id }}">Compliance status</label><select id="compliance-{{ $document->id }}" name="document[compliance_status]" class="form-control"><option @selected($document->compliance_status->value === 'None')>None</option><option @selected($document->compliance_status->value === 'Returned for Compliance')>Returned for Compliance</option><option @selected($document->compliance_status->value === 'Has Issue')>Has Issue</option></select></div>
                                <div class="form-group"><label for="compliance-reason-{{ $document->id }}">Compliance reason</label><textarea id="compliance-reason-{{ $document->id }}" name="document[compliance_reason]" class="form-control" maxlength="2000">{{ $document->compliance_reason }}</textarea></div>
                                <div class="form-group"><label for="remarks-{{ $document->id }}">Remarks</label><textarea id="remarks-{{ $document->id }}" name="document[remarks]" class="form-control" maxlength="2000">{{ $document->remarks }}</textarea></div>
                                <div class="form-check mb-2"><input type="hidden" name="document[is_delayed]" value="0"><input id="delayed-{{ $document->id }}" name="document[is_delayed]" value="1" type="checkbox" class="form-check-input" @checked($document->is_delayed)><label for="delayed-{{ $document->id }}" class="form-check-label">Document is delayed</label></div>
                                <div class="form-group"><label for="delay-reason-{{ $document->id }}">Reason for delay</label><textarea id="delay-reason-{{ $document->id }}" name="document[delay_reason]" class="form-control" maxlength="2000">{{ $document->delay_reason }}</textarea></div>
                                <button class="btn btn-sm btn-primary" type="submit">Update Preliminary Work</button>
                            </form>
                        @endif
                    @endif
                    @include('ib39.fea.partials.uploads')
                    <div class="document-history"><strong class="requirement-name">History</strong>
                        @forelse($document->histories as $history)<div class="history-row"><strong>{{ $history->event->label() }}</strong><span>{{ $history->created_at->format('F d, Y · h:i A') }} · {{ filled($history->actor?->name) ? $history->actor->name : 'User unavailable' }}</span></div>@empty<div class="empty-history mt-2">No document history recorded.</div>@endforelse
                    </div>
                </div>
            @endforeach
        </div></section></div>
    </div>
    <a class="btn btn-light" href="{{ route('ib39.fea.index') }}">Back to FEA Processing</a>
</div>
@endsection
