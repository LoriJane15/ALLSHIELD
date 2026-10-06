@extends('layouts.skydash-v')

@section('title', 'FEA Draft')
@section('heading', 'FEA Draft')

@push('styles')
<style>
    .draft-container {
        max-width: 1080px;
        margin: 0 auto;
    }
    .draft-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    .draft-card-body {
        padding: 2rem;
    }
    .draft-banner {
        border-radius: 12px;
        border: 1px solid #c7d2fe;
        background: #eef2ff;
        color: #312e81;
        padding: 1rem 1.25rem;
    }
    .draft-table th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
</style>
@endpush

@section('content')
<div class="draft-container py-2">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
        <div>
            <h1 class="h3 font-weight-bold mb-1" style="color: #1e1b4b;">{{ $document->document_type->label() }}</h1>
            <p class="text-muted mb-0 font-13">Encrypted working draft · <span class="badge badge-light border text-primary">Revision {{ $document->draft_revision }}</span></p>
        </div>
        <div>
            <a class="btn btn-outline-secondary rounded-pill font-weight-bold px-3" href="{{ route('ib39.fea.show', $fea) }}">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to FEA Record
            </a>
        </div>
    </div>

    <div class="draft-banner d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
        <div class="d-flex align-items-center gap-2">
            <i class="mdi mdi-information-outline font-18 text-primary"></i>
            <span class="font-13">Preview and print use only the last saved draft. Save your changes first.</span>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary rounded-pill font-weight-bold px-3" href="{{ route('ib39.fea.documents.draft.preview', [$fea, $document]) }}">
                <i class="mdi mdi-eye-outline mr-1"></i> Preview Saved Draft
            </a>
            <a class="btn btn-sm btn-outline-secondary rounded-pill font-weight-bold px-3 bg-white" href="{{ route('ib39.fea.documents.draft.print', [$fea, $document]) }}">
                <i class="mdi mdi-printer mr-1"></i> Print Saved Draft
            </a>
        </div>
    </div>

    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>The draft was not saved.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if($document->document_type === \App\Enums\Ib39FeaDocumentType::Justification)
        @include('ib39.fea.partials.supporting-photo-upload', ['slot' => \App\Enums\Ib39FeaUploadSlot::JustificationSurrendered, 'render' => 'form'])
        @include('ib39.fea.partials.supporting-photo-upload', ['slot' => \App\Enums\Ib39FeaUploadSlot::JustificationComparison, 'render' => 'form'])
    @endif

    <form method="POST" action="{{ route('ib39.fea.documents.draft.update', [$fea, $document]) }}" class="draft-card card" id="draft-form" data-fea-draft-form>
        @csrf @method('PUT')
        <input type="hidden" name="revision" value="{{ $document->draft_revision }}">
        <div class="draft-card-body card-body">

            @if($document->document_type === \App\Enums\Ib39FeaDocumentType::Cvif)
                <div class="text-center mb-4"><h2 class="h4">COST VALUATION OF INVENTORIED FIREARMS</h2></div>
                <div class="border p-3 mb-4">THIS IS TO CERTIFY that [Name of FR or FVE]; with RM No. [RM No.]<br>The owner of the firearm is described below:<br><br>THAT THE ABOVE-DESCRIBED firearms has undergone inventory and technical on [Date of inspection] at [Place of inspection] with the following findings:<br><br>THAT UPON DUE DELIBERATION, The Valuation Committee finds the amount [Amount in words] (Php: [Cost valuation]) as the cost valuation for the said firearm.</div>
            @elseif($document->document_type === \App\Enums\Ib39FeaDocumentType::Justification)
                <div class="text-center mb-4"><small>Form 25</small><h2 class="h4">JUSTIFICATION ON THE TIR AND CVC/CVIF</h2></div>
            @elseif($document->document_type === \App\Enums\Ib39FeaDocumentType::Tir)
                <h2 class="h4 text-center mb-4">TECHNICAL INSPECTION REPORT</h2>
            @else
                <h2 class="h4 text-center mb-4">PROPERTY TURN-IN SLIP</h2>
            @endif

            @include('ib39.fea.partials.draft-inputs')

            @if($document->document_type === \App\Enums\Ib39FeaDocumentType::Justification)
                <div class="border p-3 mb-4">THIS IS TO CERTIFY that the justification provided in the Cost Valuation Certificate(CVC) / Cost Valuation of Inventoried Firearms(CVIF), as prepared by <span data-preparer-name>[Prepared by name]</span>, has been reviewed by the PNP RSAO and found to be correct, accurate and sufficient to support the cost valuation reflected on the CVC/CVIF of the firearms enumerated above.</div>
            @endif
            @if($document->document_type === \App\Enums\Ib39FeaDocumentType::Ptis)
                <div class="border p-3 mb-4"><strong>LEGEND FOR REMARKS</strong><br>FWT — Unserviceable due to wear and tear<br>SER — Serviceable<br>R/C — Unserviceable Statement<br>R/S — Unserviceable Report on survey<br>EXC — In Excess of Authorized Allowance<br><br>I HEREBY CERTIFY that the article/s listed herein are turned-in under the circumstances indicated therein:<br><br>FOR THE COMMANDING OFFICER:<br>(signature above printed name) AFP/ PNP Representative<br><br>CONFIRMED BY:<br>(signature above printed name) (DILG Representative)<br><br>QUANTITIES SHOWN ABOVE IN ACTION HAVE BEEN RECEIVED:<br>(DATE :) For Station Supply or Classification Officer</div>
            @endif
            <button class="btn btn-primary" type="submit">Save Encrypted Draft</button>
        </div>
    </form>

    <section class="card mt-4"><div class="card-body"><h2 class="h5">Draft history</h2>@forelse($document->draftHistories as $history)<div class="border-top py-2">Revision {{ $history->revision }} · {{ $history->created_at->format('F d, Y · h:i A') }} · {{ $history->actor?->name ?: 'User unavailable' }}<br><small>Changed fields: {{ implode(', ', $history->changed_fields) ?: 'Initial blank draft' }}</small></div>@empty<p class="text-muted mb-0">No saved draft history.</p>@endforelse</div></section>
</div>
@include('ib39.fea.partials.draft-interactions')
@endsection
