@extends('layouts.skydash-v')
@section('title', 'Former Rebels')
@section('heading', 'Former Rebels Monitoring')

@php
    $badge = fn ($s) => match ($s) {
        'Active' => 'badge-soft-shield',
        'Inactive' => 'badge-soft-danger',
        'On hold' => 'badge-soft-warning',
        'Reintegrated' => 'badge-soft-purple',
        default => 'badge-soft-info',
    };
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
.badge-soft-shield { background: #eef2ff; color: #312e81; border: 1px solid #c7d2fe; padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 750; font-size: 0.75rem; }
.badge-soft-danger { background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 750; font-size: 0.75rem; }
.badge-soft-warning { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 750; font-size: 0.75rem; }
.badge-soft-purple { background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 750; font-size: 0.75rem; }
.badge-soft-info { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 750; font-size: 0.75rem; }

.mblrc-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.mblrc-table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    border-top: none;
}
.mblrc-table tbody td {
    padding: 1.1rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.875rem;
}
.mblrc-table tbody tr:hover {
    background-color: #fafbfc;
}
</style>
@endpush

@section('content')
<div class="mblrc-dashboard-container">
    {{-- Modern Hero Banner (SHIELD Brand Design) --}}
    <div class="mblrc-hero mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="position: relative; z-index: 2; gap: 1rem;">
            <div class="d-flex align-items-center" style="gap: 1.15rem;">
                <div class="mblrc-hero-icon-box">
                    <i class="mdi mdi-account-group"></i>
                </div>
                <div>
                    <div class="mblrc-hero-eyebrow">
                        MBLRC Reintegration Monitoring
                    </div>
                    <h1 class="mblrc-hero-title">Former Rebels Directory</h1>
                    <p class="mblrc-hero-sub">
                        Add, monitor, and track Former Rebels program progression and status.
                    </p>
                </div>
            </div>
            <a href="{{ route('mblrc.fr.create') }}" class="btn" style="background: #ffffff; color: #312e81; font-weight: 800; font-size: 0.875rem; padding: 0.55rem 1.25rem; border-radius: 10px; border: none; box-shadow: 0 4px 14px rgba(0,0,0,0.18); transition: all 0.2s ease;">
                <i class="mdi mdi-plus mr-1"></i> Register Former Rebel
            </a>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="mblrc-table-card">
        <div class="table-responsive">
            <table class="table mblrc-table mb-0">
                <thead>
                    <tr>
                        <th>Profile ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Profile</th>
                        <th class="text-right">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($frs as $fr)
                        <tr>
                            <td>
                                <span class="badge badge-light" style="background: #eef2ff; color: #4338ca; font-weight: 750; padding: 0.35rem 0.65rem; border-radius: 6px;">
                                    {{ $fr->classified_id }}
                                </span>
                            </td>
                            <td>
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; overflow: hidden; background: #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                                    <img src="{{ asset('assets/img/fr-profile.jpg') }}" alt="FR Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            </td>
                            <td>
                                <div class="font-weight-bold" style="color: #0f172a;">
                                    {{ trim($fr->lastname.' '.$fr->firstname.' '.$fr->middlename.' '.$fr->suffix) }}
                                </div>
                                @if($fr->nickname)
                                    <span class="small text-muted">Alias: {{ $fr->nickname }}</span>
                                @endif
                            </td>
                            <td>
                                <div style="color: #475569;">
                                    <i class="mdi mdi-map-marker-outline text-muted"></i>
                                    {{ $fr->barangay?->name ? $fr->barangay->name.', ' : '' }}{{ $fr->municipality?->name ?? '—' }}
                                </div>
                            </td>
                            <td>
                                <span class="small font-weight-bold" style="color: #475569;">
                                    Batch {{ $fr->batch_section ?: '—' }} &middot; {{ $fr->batch_year ?: '—' }}
                                </span>
                            </td>
                            <td>
                                <span class="{{ $badge($fr->status) }}">{{ $fr->status }}</span>
                            </td>
                            <td>
                                <a href="{{ route('mblrc.fr.show', $fr) }}" class="btn btn-sm btn-outline-primary" style="font-weight: 750; border-radius: 8px; padding: 0.35rem 0.85rem;">
                                    Profile
                                </a>
                            </td>
                            <td class="text-right">
                                <div class="d-inline-flex align-items-center" style="gap: 8px;">
                                    <a href="{{ route('mblrc.fr.edit', $fr) }}" class="btn btn-sm btn-light" style="border-radius: 8px; padding: 0.35rem 0.6rem; color: #2563eb;" title="Edit Profile">
                                        <i class="mdi mdi-pencil font-size-16"></i>
                                    </a>
                                    <form method="POST" action="{{ route('mblrc.fr.destroy', $fr) }}"
                                          data-confirm="Are you sure you want to delete this Former Rebel? This action cannot be undone." data-confirm-title="Confirm delete" data-confirm-action="Delete"
                                          style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light" style="border-radius: 8px; padding: 0.35rem 0.6rem; color: #e11d48;" title="Delete Profile">
                                            <i class="mdi mdi-trash-can-outline font-size-16"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="mdi mdi-account-search-outline d-block mb-2" style="font-size: 2.5rem; color: #94a3b8;"></i>
                                <span class="font-weight-bold" style="font-size: 1rem;">No records found.</span>
                                <p class="small text-muted mb-0 mt-1">No former rebels are currently registered in this directory.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($frs->hasPages())
            <div class="p-3 border-top" style="border-color: #f1f5f9 !important;">
                {{ $frs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
