@extends('layouts.skydash-v')
@section('title', 'CDR Record')
@section('heading', $heading ?? 'JAPIC Certification')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
    .record-doc-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 0.85rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .record-doc-item:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
        transform: translateY(-1px);
    }
    .record-doc-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #eef2ff;
        color: #312e81;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
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
                    <i class="mdi mdi-file-document-outline"></i>
                </div>
                <div>
                    <div class="mblrc-hero-eyebrow">
                        39th Infantry Battalion &middot; Authoritative Custody Record
                    </div>
                    <h1 class="mblrc-hero-title">CDR Document Record</h1>
                    <p class="mblrc-hero-sub">Authoritative Custody and Demobilization Record triggering JAPIC certification eligibility.</p>
                </div>
            </div>
            <div>
                <span class="badge" style="background: rgba(255,255,255,0.15); color: #ffffff; padding: 0.5rem 1rem; font-size: 0.8125rem; font-weight: 750; border-radius: 8px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.2);">
                    Status: {{ $cdrStatus }}
                </span>
            </div>
        </div>
    </div>

    {{-- Main Document Card --}}
    <section class="mblrc-form-card mb-4" aria-labelledby="cdr-doc-heading">
        <div class="mblrc-form-card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="mdi mdi-file-check" style="font-size: 1.25rem; color: #312e81;"></i>
                <h2 id="cdr-doc-heading" class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">
                    Authoritative Document
                </h2>
            </div>
            <span class="badge" style="background: #eef2ff; color: #312e81; padding: 0.4rem 0.85rem; font-weight: 750; border-radius: 8px; border: 1px solid #c7d2fe;">
                Status: {{ $cdrStatus }}
            </span>
        </div>
        <div class="mblrc-form-card-body p-3">
            @if($finalCdr)
                <div class="record-doc-item">
                    <div class="d-flex align-items-center" style="gap: 1.15rem; min-width: 0;">
                        <div class="record-doc-icon">
                            <i class="mdi mdi-file-pdf"></i>
                        </div>
                        <div style="min-width: 0;">
                            <h3 class="mb-1 font-weight-bold" style="color: #0f172a; font-size: 1rem;">
                                Authoritative Final CDR &mdash; {{ $referenceNumber }}
                            </h3>
                            <p class="text-muted small mb-0">
                                This is the current authoritative final CDR for {{ $referenceNumber }}.
                                @if($date['label'])
                                    <span class="d-block mt-1 font-weight-bold" style="color: #1e1b4b;">
                                        <i class="mdi mdi-calendar-clock mr-1" style="color: #312e81;"></i>
                                        {{ $date['label'] }}: {{ $date['value'] }}
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a class="btn btn-sm" href="{{ $previewUrl }}" style="background: #312e81; color: #ffffff; font-weight: 750; border-radius: 8px; padding: 0.5rem 1.15rem; border: none; box-shadow: 0 2px 6px rgba(49, 46, 129, 0.18);">
                            <i class="mdi mdi-eye-outline mr-1"></i> Secure preview
                        </a>
                        @if($downloadUrl)
                            <a class="btn btn-sm" href="{{ $downloadUrl }}" style="background: #ffffff; color: #0f172a; font-weight: 750; border-radius: 8px; padding: 0.5rem 1.15rem; border: 1px solid #cbd5e1;">
                                <i class="mdi mdi-download mr-1"></i> Secure download
                            </a>
                        @endif
                    </div>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <div class="mb-3 mx-auto" style="width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center;">
                        <i class="mdi mdi-file-outline" style="font-size: 2rem; color: #94a3b8;"></i>
                    </div>
                    <span class="font-weight-bold d-block mb-1" style="font-size: 1rem; color: #1e1b4b;">No documents available</span>
                    <small class="text-muted">No finalized CDR record is currently available for this subject.</small>
                </div>
            @endif
        </div>
    </section>

    {{-- Security Notice Footer --}}
    <div class="record-security-note">
        <i class="mdi mdi-shield-lock-outline" style="font-size: 1.35rem; color: #312e81; flex-shrink: 0;"></i>
        <div>
            <strong>Authoritative Chain of Custody:</strong> The CDR is verified by 39th IB command authority and serves as the cryptographic foundation for all downstream intake milestones.
        </div>
    </div>
</div>
@endsection

