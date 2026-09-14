@extends('layouts.skydash-v')
@section('title', 'FEA Processing Documents')
@section('heading', $heading ?? 'JAPIC Certification')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
    .record-doc-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.15rem 1.35rem;
        margin-bottom: 0.85rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .record-doc-item:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    }
    .record-doc-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #eef2ff;
        color: #312e81;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .record-security-note {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.825rem;
        color: #64748b;
    }
</style>
@endpush

@section('content')
<div class="mblrc-dashboard-container">
    {{-- Navigation Breadcrumb --}}
    <div class="module-nav-top mb-3">
        <a href="{{ $backUrl }}" class="module-back-link">
            <i class="mdi mdi-arrow-left"></i>
            <span>Back to surfaced FR profile</span>
        </a>
    </div>

    {{-- Modern Hero Banner --}}
    <div class="mblrc-hero mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="position: relative; z-index: 2; gap: 1rem;">
            <div class="d-flex align-items-center" style="gap: 1.25rem;">
                <div class="mblrc-hero-icon-box">
                    <i class="mdi mdi-file-document-box-multiple-outline"></i>
                </div>
                <div>
                    <div class="mblrc-hero-eyebrow">
                        Firearms Remuneration &middot; Technical Processing Archive
                    </div>
                    <h1 class="mblrc-hero-title">FEA Processing Documents</h1>
                    <p class="mblrc-hero-sub">Official firearms evaluation, technical inspection, and cost valuation documentation.</p>
                </div>
            </div>
            <div>
                <span class="badge" style="background: rgba(255,255,255,0.15); color: #ffffff; padding: 0.5rem 1rem; font-size: 0.8125rem; font-weight: 750; border-radius: 8px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.2);">
                    <i class="mdi mdi-shield-check mr-1"></i> Technical Archive
                </span>
            </div>
        </div>
    </div>

    {{-- Main Document Card --}}
    <section class="mblrc-form-card mb-4" aria-labelledby="fea-docs-heading">
        <div class="mblrc-form-card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="mdi mdi-file-document-box-outline" style="font-size: 1.25rem; color: #312e81;"></i>
                <h2 id="fea-docs-heading" class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">
                    Evaluation Documents
                </h2>
            </div>
            @if(count($documents) > 0)
                <span class="badge" style="background: #eef2ff; color: #312e81; font-weight: 800; border-radius: 6px; padding: 0.35rem 0.75rem;">
                    {{ count($documents) }} {{ count($documents) === 1 ? 'Category' : 'Categories' }}
                </span>
            @endif
        </div>
        <div class="mblrc-form-card-body p-3">
            @forelse($documents as $document)
                <div class="record-doc-item">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom" style="border-color: #f1f5f9 !important;">
                        <h3 class="font-weight-bold mb-0" style="color: #0f172a; font-size: 0.95rem;">{{ $document['label'] }}</h3>
                        <span class="badge" style="background: #eef2ff; color: #312e81; font-weight: 750; border-radius: 6px; padding: 0.3rem 0.65rem; border: 1px solid #c7d2fe;">
                            Status: {{ $document['status'] }}
                        </span>
                    </div>

                    @foreach($document['versions'] as $version)
                        <div class="d-flex align-items-center justify-content-between flex-wrap py-2" style="gap: 0.75rem;">
                            <div class="d-flex align-items-center" style="gap: 0.85rem;">
                                <div class="record-doc-icon">
                                    <i class="mdi mdi-file-pdf"></i>
                                </div>
                                <div>
                                    <strong style="color: #334155; font-size: 0.875rem;">{{ $version['label'] }}</strong>
                                    <small class="d-block text-muted">
                                        <i class="mdi mdi-calendar-clock mr-1" style="color: #312e81;"></i>
                                        <strong>{{ $version['date']['label'] ? $version['date']['label'].':' : '' }}</strong>{{ $version['date']['label'] ? ' ' : '' }}{{ $version['date']['value'] }}
                                    </small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <a class="btn btn-sm" href="{{ $version['previewUrl'] }}" style="background: #312e81; color: #ffffff; font-weight: 750; border-radius: 8px; padding: 0.4rem 0.95rem; border: none; box-shadow: 0 2px 6px rgba(49, 46, 129, 0.18);">
                                    <i class="mdi mdi-eye-outline mr-1"></i> Secure preview
                                </a>
                                <a class="btn btn-sm" href="{{ $version['downloadUrl'] }}" style="background: #ffffff; color: #0f172a; font-weight: 750; border-radius: 8px; padding: 0.4rem 0.95rem; border: 1px solid #cbd5e1;">
                                    <i class="mdi mdi-download mr-1"></i> Secure download
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="text-center py-5 text-muted">
                    <div class="mb-3 mx-auto" style="width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center;">
                        <i class="mdi mdi-file-outline" style="font-size: 2rem; color: #94a3b8;"></i>
                    </div>
                    <span class="font-weight-bold d-block mb-1" style="font-size: 1rem; color: #1e1b4b;">No documents available</span>
                    <small class="text-muted">No FEA technical processing documents have been recorded for this case profile.</small>
                </div>
            @endforelse
        </div>
    </section>

    {{-- Security Notice Footer --}}
    <div class="record-security-note">
        <i class="mdi mdi-shield-lock-outline" style="font-size: 1.35rem; color: #312e81; flex-shrink: 0;"></i>
        <div>
            <strong>Technical Compliance &middot; Read-Only Dossier:</strong> All FEA documentation conforms to inter-agency disarmament standards and is cryptographically validated at rest.
        </div>
    </div>
</div>
@endsection

