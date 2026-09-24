@extends('layouts.skydash-v')
@section('title', 'FRs for Certification')
@section('heading', 'JAPIC Certification')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
    .badge-soft-danger { color: #e11d48; background-color: #fff1f2; border: 1px solid #fecdd3; }
    .badge-soft-shield { color: #312e81; background-color: #eef2ff; border: 1px solid #c7d2fe; }
    .badge-soft-info { color: #0284c7; background-color: #f0f9ff; border: 1px solid #bae6fd; }
    .badge-soft-warning { color: #d97706; background-color: #fffbeb; border: 1px solid #fde68a; }
    .table-modern thead th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.9rem 1.15rem;
    }
    .table-modern tbody td {
        padding: 1rem 1.15rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.875rem;
    }
    .table-modern tbody tr:hover {
        background-color: #f8fafc;
    }
</style>
@endpush

@section('content')
<div class="mblrc-dashboard-container">
    {{-- Modern Hero Banner --}}
    <div class="mblrc-hero mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="position: relative; z-index: 2; gap: 1rem;">
            <div class="d-flex align-items-center" style="gap: 1.25rem;">
                <div class="mblrc-hero-icon-box">
                    <i class="mdi mdi-certificate"></i>
                </div>
                <div>
                    <div class="mblrc-hero-eyebrow">
                        JAPIC Certification Workflow
                    </div>
                    <h1 class="mblrc-hero-title">FRs for Certification</h1>
                    <p class="mblrc-hero-sub">Eligible completed CDR records received by JAPIC for intelligence certification.</p>
                </div>
            </div>
            <div>
                <a href="{{ route('japic.dashboard') }}" class="btn" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); color: #ffffff; font-weight: 700; border-radius: 10px; padding: 0.55rem 1.15rem;">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="mblrc-form-card mb-4">
        <div class="mblrc-form-card-header">
            <div class="d-flex align-items-center gap-2">
                <i class="mdi mdi-filter-variant" style="font-size: 1.25rem; color: #312e81;"></i>
                <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Search & Filter Records</h4>
            </div>
        </div>
        <div class="mblrc-form-card-body p-3">
            <form method="GET" action="{{ route('japic.certifications.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-7">
                    <label for="search" class="mblrc-label">Reference or Name</label>
                    <div class="input-group">
                        <span class="input-group-text" style="background: #f8fafc; border-color: #cbd5e1; border-top-left-radius: 10px; border-bottom-left-radius: 10px; color: #64748b;">
                            <i class="mdi mdi-magnify"></i>
                        </span>
                        <input id="search" name="search" maxlength="100" value="{{ $filters['search'] ?? '' }}" placeholder="Search by reference number or name..." class="form-control mblrc-input" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                    </div>
                </div>
                <div class="col-lg-3">
                    <label for="status" class="mblrc-label">JAPIC Status</label>
                    <select id="status" name="status" class="form-select mblrc-select">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button class="btn flex-grow-1" type="submit" style="background: #312e81; color: #ffffff; font-weight: 750; border-radius: 10px; padding: 0.65rem 1rem;">
                        <i class="mdi mdi-filter-variant mr-1"></i> Filter
                    </button>
                    <a class="btn btn-light" href="{{ route('japic.certifications.index') }}" style="border-radius: 10px; font-weight: 700; border: 1px solid #cbd5e1; padding: 0.65rem 1rem;" title="Reset filters">
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Records Table Card --}}
    <div class="mblrc-form-card">
        <div class="mblrc-form-card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="mdi mdi-file-document-box-multiple-outline" style="font-size: 1.25rem; color: #312e81;"></i>
                <h4 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Certification Caseload</h4>
            </div>
            <span class="badge" style="background: #eef2ff; color: #312e81; font-weight: 800; font-size: 0.8125rem; border-radius: 8px; padding: 0.35rem 0.75rem;">
                {{ $records->total() }} Total Cases
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th>Reference / FR</th>
                        <th>Category</th>
                        <th>Surfacing</th>
                        <th>Area</th>
                        <th>Overall FR status</th>
                        <th>JAPIC certification status</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($records as $processing)
                    @php($fr = $processing->surfacedFormerRebel)
                    <tr>
                        <td>
                            <strong style="color: #1e1b4b; font-size: 0.9rem;">{{ $fr->reference_number }}</strong>
                            <div class="text-muted small font-weight-bold">{{ $fr->display_name }}</div>
                        </td>
                        <td>
                            <span class="badge" style="background: #f1f5f9; color: #475569; font-weight: 700; font-size: 0.75rem; border-radius: 6px;">
                                {{ $fr->category->value }}
                            </span>
                        </td>
                        <td>
                            <span class="text-muted"><i class="mdi mdi-calendar mr-1"></i>{{ $fr->surfaced_at->format('M d, Y') }}</span>
                        </td>
                        <td>
                            <span class="text-dark font-weight-bold">{{ $fr->barangay?->name ? $fr->barangay->name.', ' : '' }}{{ $fr->municipality->name }}</span>
                        </td>
                        <td>
                            <span class="badge badge-soft-shield" style="font-weight: 750; font-size: 0.75rem; border-radius: 6px; padding: 0.3rem 0.6rem;">
                                {{ $fr->overall_case_status }}
                            </span>
                        </td>
                        <td>
                            @if($processing->delayed)
                                <span class="badge badge-soft-danger" role="status" aria-label="JAPIC certification status: Delayed" style="font-weight: 800; font-size: 0.75rem; border-radius: 6px; padding: 0.3rem 0.65rem;">
                                    <i class="mdi mdi-alert-circle-outline mr-1"></i> Delayed
                                </span>
                            @else
                                <span class="badge badge-soft-info" style="font-weight: 750; font-size: 0.75rem; border-radius: 6px; padding: 0.3rem 0.65rem;">
                                    {{ $processing->status->value }}
                                </span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm" href="{{ route('japic.certifications.show', $processing) }}" style="background: #eef2ff; color: #312e81; font-weight: 750; border-radius: 8px; border: 1px solid #c7d2fe; padding: 0.4rem 0.85rem;">
                                View Profile <i class="mdi mdi-arrow-right ml-1"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="mdi mdi-folder-outline d-block mb-2" style="font-size: 2.5rem; color: #94a3b8;"></i>
                            <span class="font-weight-bold">No certification tasks match the approved filters.</span>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($records->hasPages())
            <div class="mblrc-form-card-body border-top p-3 d-flex justify-content-center">
                {{ $records->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

