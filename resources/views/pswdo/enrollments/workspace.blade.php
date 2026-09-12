@extends('layouts.skydash-v')
@section('title', 'PSWDO Enrollment Workspace')
@section('heading', 'PSWDO Enrollment')

@push('styles')
<style>.pswdo-flow{display:flex;list-style:none;margin:0 0 1.5rem;padding:0}.pswdo-step{flex:1;text-align:center}.pswdo-marker{align-items:center;background:#fff;border:2px solid #cbd5e0;border-radius:50%;display:inline-flex;height:34px;justify-content:center;width:34px}.pswdo-step.done .pswdo-marker{background:#28a745;border-color:#28a745;color:#fff}.pswdo-document{border:1px solid #e2e8f0;border-radius:12px;margin-bottom:1rem;padding:1rem}.pswdo-actions{display:flex;flex-wrap:wrap;gap:.5rem}.pswdo-locked{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;color:#64748b;padding:.75rem}@media(max-width:767px){.pswdo-flow{flex-direction:column;gap:.75rem}.pswdo-step{text-align:left}}</style>
@endpush

@section('content')
@php($fr = $enrollment->surfacedFormerRebel)
<a href="{{ route('pswdo.enrollments.show', $enrollment) }}" class="d-inline-block mb-3">&larr; Back to surfaced FR profile</a>
<div class="card mb-4"><div class="card-body"><h2 class="h5">{{ $fr->reference_number }} &mdash; {{ $fr->display_name }}</h2><p class="text-muted mb-0">Only final completed and signed PDFs prepared outside SHIELD are accepted.</p></div></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<ol class="pswdo-flow" aria-label="PSWDO enrollment progress">
    @foreach($types as $position => $type)@php($done = $enrollment->hasDocument($type))<li class="pswdo-step {{ $done ? 'done' : '' }}"><span class="pswdo-marker">{!! $done ? '<i class="mdi mdi-check"></i>' : $position + 1 !!}</span><div><strong>{{ $type->label() }}</strong><br><small>{{ $done ? 'Completed' : 'Pending' }}</small></div></li>@endforeach
    <li class="pswdo-step {{ $enrollment->isCompleted() ? 'done' : '' }}"><span class="pswdo-marker">{!! $enrollment->isCompleted() ? '<i class="mdi mdi-check"></i>' : '5' !!}</span><div><strong>Completed</strong><br><small>{{ $enrollment->isCompleted() ? 'Completed / Complete' : 'Pending' }}</small></div></li>
</ol>
@foreach($types as $type)
    @php($document = $enrollment->documents->firstWhere('document_type', $type))
    @php($locked = $type->isEndorsementLetter() && !collect(\App\Enums\PswdoEnrollmentDocumentType::prerequisites())->every(fn($required) => $enrollment->hasDocument($required)))
    <section id="{{ $type->value }}" class="pswdo-document"><div class="d-flex justify-content-between flex-wrap"><h3 class="h6 font-weight-bold">{{ $type->label() }}</h3><span class="badge {{ $document ? 'badge-success' : 'badge-light' }}">{{ $document ? 'Completed' : 'Pending' }}</span></div>
        @if($document)
            <p class="small text-muted">{{ $document->original_filename }} &middot; uploaded {{ $document->uploaded_at->format('M d, Y h:i A') }} by {{ $document->uploader?->name ?? 'User unavailable' }}</p><div class="pswdo-actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('pswdo.enrollments.documents.preview', [$enrollment, $document]) }}">Secure Preview</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('pswdo.enrollments.documents.download', [$enrollment, $document]) }}">Secure Download</a></div>
        @elseif($locked)
            <div class="pswdo-locked">The Endorsement Letter is locked until the E-CLIP Enrollment Form, Initial Interview Form, and Profiling Interview Form are completed.</div>
        @else
            <form method="POST" action="{{ route('pswdo.enrollments.documents.store', [$enrollment, $type->value]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="lock_version" value="{{ $enrollment->lock_version }}"><div class="form-group"><label class="font-weight-bold" for="document-{{ $type->value }}">Final signed PDF</label><input id="document-{{ $type->value }}" class="form-control-file" type="file" name="document" accept="application/pdf,.pdf" required></div><div class="form-check mb-2"><input id="type-{{ $type->value }}" class="form-check-input" type="checkbox" name="correct_document_type_confirmed" value="1" required><label class="form-check-label" for="type-{{ $type->value }}">I confirm this is the correct {{ $type->label() }}.</label></div><div class="form-check mb-2"><input id="fr-{{ $type->value }}" class="form-check-input" type="checkbox" name="belongs_to_fr_confirmed" value="1" required><label class="form-check-label" for="fr-{{ $type->value }}">I confirm this document belongs to {{ $fr->reference_number }} &mdash; {{ $fr->display_name }}.</label></div><div class="form-check mb-3"><input id="final-{{ $type->value }}" class="form-check-input" type="checkbox" name="final_signed_confirmed" value="1" required><label class="form-check-label" for="final-{{ $type->value }}">I confirm this is the final completed and signed document. SHIELD does not validate handwritten signatures.</label></div><button class="btn btn-success" type="submit">Upload Final Signed PDF</button></form>
        @endif
    </section>
@endforeach
@endsection
