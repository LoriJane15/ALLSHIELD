@extends('layouts.skydash-v')
@section('title', 'Assistance Records')
@section('heading', $heading ?? 'JAPIC Certification')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
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
                    <i class="mdi mdi-gift-outline"></i>
                </div>
                <div>
                    <div class="mblrc-hero-eyebrow">
                        Government Assistance &middot; Social Integration Support
                    </div>
                    <h1 class="mblrc-hero-title">Assistance Records</h1>
                    <p class="mblrc-hero-sub">Livelihood support, immediate financial assistance, and reintegration benefits record archive.</p>
                </div>
            </div>
            <div>
                <span class="badge" style="background: rgba(255,255,255,0.15); color: #ffffff; padding: 0.5rem 1rem; font-size: 0.8125rem; font-weight: 750; border-radius: 8px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.2);">
                    <i class="mdi mdi-shield-check mr-1"></i> Assistance Registry
                </span>
            </div>
        </div>
    </div>

    {{-- Main Document Card --}}
    <section class="mblrc-form-card mb-4" aria-labelledby="assistance-heading">
        <div class="mblrc-form-card-header">
            <div class="d-flex align-items-center gap-2">
                <i class="mdi mdi-gift-outline" style="font-size: 1.25rem; color: #312e81;"></i>
                <h2 id="assistance-heading" class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">
                    Government Assistance Records
                </h2>
            </div>
        </div>
        <div class="mblrc-form-card-body p-4">
            <div class="text-center py-5 text-muted">
                <div class="mb-3 mx-auto" style="width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center;">
                    <i class="mdi mdi-folder-outline" style="font-size: 2rem; color: #94a3b8;"></i>
                </div>
                <span class="font-weight-bold d-block mb-1" style="font-size: 1rem; color: #1e1b4b;">No documents available</span>
                <small class="text-muted">No government assistance records or disbursement receipts have been registered for this case profile.</small>
            </div>
        </div>
    </section>

    {{-- Security Notice Footer --}}
    <div class="record-security-note">
        <i class="mdi mdi-shield-lock-outline" style="font-size: 1.35rem; color: #312e81; flex-shrink: 0;"></i>
        <div>
            <strong>Reintegration Assistance Protocol:</strong> Assistance disbursements are coordinated through DILG/DSWD and require validated JAPIC certification eligibility before formal release.
        </div>
    </div>
</div>
@endsection

