@extends('layouts.skydash-v')
@section('title', 'JAPIC Certification')
@section('heading', 'JAPIC Certification')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
    .fixed-narrative {
        font-family: "Georgia", "Times New Roman", serif;
        font-size: 1.05rem;
        line-height: 2.2;
        text-align: justify;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        color: #1e293b;
    }
    .fixed-narrative input {
        display: inline-block;
        border: none;
        border-bottom: 2px solid #312e81;
        border-radius: 0;
        background: #ffffff;
        padding: 0.2rem 0.6rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0.2rem;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .fixed-narrative input:focus {
        border-bottom-color: #2563eb;
        background: #eff6ff;
        outline: none;
    }
    .personnel-row {
        padding: 1rem 1.25rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        margin-bottom: 0.75rem;
        transition: all 0.2s ease;
    }
    .personnel-row:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 2px 6px rgba(15,23,42,0.04);
    }
</style>
@include('components.processing-workspace.styles')
@endpush

@section('content')


<div class="mblrc-dashboard-container">
    {{-- Header / Breadcrumb Bar --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4" style="gap: 1rem;">
        <div>
            <a href="{{ route('japic.certifications.workspace', $processing) }}" class="btn btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; color: #475569; font-weight: 750; border-radius: 8px; padding: 0.45rem 1rem;">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to JAPIC Certification
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success" role="status" style="border-radius: 10px; font-weight: 600;">
            <i class="mdi mdi-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="border-radius: 10px;">
            <ul class="mb-0 font-weight-bold">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <header class="process-workspace-header">
        <div><h1>JAPIC Certification</h1><p>{{ $processing->surfacedFormerRebel->reference_number }} &middot; {{ $processing->surfacedFormerRebel->display_name }}</p></div>
        <span class="process-workspace-status">{{ $processing->status->value }}</span>
    </header>
    <div class="process-workspace-grid">
    <main class="process-workspace-main">

    @include('japic.certifications.partials.draft-editor')
    </main>
    @include('components.processing-workspace.sidebar', ['sidebarId' => 'japic-draft', 'comments' => $processing->comments, 'events' => $workspaceEvents, 'commentAction' => route('japic.certifications.comments.store', $processing), 'canComment' => true])
    </div>
</div>

@endsection
