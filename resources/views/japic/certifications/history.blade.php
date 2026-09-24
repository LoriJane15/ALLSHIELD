@extends('layouts.skydash-v')
@section('title', 'Certification Draft History')
@section('heading', 'JAPIC Certification')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
    .revision-item {
        padding: 0.9rem 1.15rem;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        margin-bottom: 0.6rem;
        text-decoration: none !important;
        display: block;
        transition: all 0.2s ease;
    }
    .revision-item:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        transform: translateX(2px);
    }
    .revision-item.active {
        background: #312e81;
        border-color: #312e81;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(49, 46, 129, 0.25);
    }
    .revision-item.active small,
    .revision-item.active div,
    .revision-item.active strong {
        color: #ffffff !important;
    }
    .history-table thead th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.85rem 1rem;
    }
    .history-table tbody td,
    .history-table tbody th {
        padding: 0.9rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.875rem;
    }
    .timeline-event-card {
        padding: 1rem 1.15rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 0.75rem;
    }
</style>
@endpush

@section('content')
<div class="mblrc-dashboard-container">
    {{-- Top Back Nav --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="{{ route('japic.certifications.show', $processing) }}" class="btn btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; color: #475569; font-weight: 750; border-radius: 8px; padding: 0.45rem 1rem;">
            <i class="mdi mdi-arrow-left mr-1"></i> Back to Certification Profile
        </a>
        <span class="badge" style="background: #eef2ff; color: #312e81; font-weight: 750; font-size: 0.8125rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #c7d2fe;">
            <i class="mdi mdi-history mr-1"></i> Audit & Revision Log
        </span>
    </div>

    {{-- Top Row: Revision Selector and Details --}}
    <div class="row g-4 mb-4">
        {{-- Revision List --}}
        <div class="col-lg-4">
            <section class="mblrc-form-card h-100">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="mdi mdi-history" style="font-size: 1.25rem; color: #312e81;"></i>
                        <h2 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Revision History</h2>
                    </div>
                </div>
                <div class="mblrc-form-card-body p-3">
                    @forelse($revisions as $item)
                        <a class="revision-item {{ ($selectedRevision['revision'] ?? null) === $item['revision'] ? 'active' : '' }}" href="{{ route('japic.certifications.history', [$processing, 'revision' => $item['revision']]) }}">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong>Revision {{ $item['revision'] }}</strong>
                                <span class="badge" style="background: rgba(0,0,0,0.06); font-size: 0.7rem; border-radius: 4px;">{{ $item['saved_at']->format('M d, Y') }}</span>
                            </div>
                            <small class="text-muted d-block mb-1">{{ $item['saved_at']->format('h:i A') }} &middot; {{ $item['saved_by'] }}</small>
                            <div class="small font-weight-bold" style="color: #475569;">{{ $item['summary'] }}</div>
                        </a>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="mdi mdi-information-outline d-block mb-1" style="font-size: 1.75rem;"></i>
                            No draft revisions have been saved.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Revision Changes Table --}}
        <div class="col-lg-8">
            <section class="mblrc-form-card h-100">
                <div class="mblrc-form-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="mdi mdi-compare" style="font-size: 1.25rem; color: #312e81;"></i>
                        <h2 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">
                            @if($selectedRevision) Revision {{ $selectedRevision['revision'] }} Changes @else Revision Changes @endif
                        </h2>
                    </div>
                    @if($selectedRevision && $selectedRevision['initial'])
                        <span class="badge" style="background: #eef2ff; color: #312e81; font-weight: 750; border-radius: 6px;">Initial Draft</span>
                    @endif
                </div>
                <div class="mblrc-form-card-body p-0">
                    @if($selectedRevision)
                        <div class="table-responsive">
                            <table class="table history-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Field Name</th>
                                        @unless($selectedRevision['initial'])
                                            <th>Previous value</th>
                                        @endunless
                                        <th>{{ $selectedRevision['initial'] ? 'Initial value' : 'New value' }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($selectedRevision['changes'] as $change)
                                        <tr>
                                            <th style="color: #0f172a; font-weight: 750;">{{ $change['field'] }}</th>
                                            @unless($selectedRevision['initial'])
                                                <td class="text-muted" style="text-decoration: line-through; background: #fff1f2;">{{ $change['previous_value'] }}</td>
                                            @endunless
                                            <td style="color: #1e1b4b; font-weight: 700; background: #eef2ff;">{{ $change['new_value'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted">
                                                No approved certification fields changed.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="mdi mdi-cursor-pointer d-block mb-1" style="font-size: 2rem; color: #94a3b8;"></i>
                            <p class="mb-0 font-weight-bold">Select a revision from the left to view its changes.</p>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    {{-- Bottom Row: Workflow Events and Final Document Versions --}}
    <div class="row g-4">
        {{-- Workflow History --}}
        <div class="col-lg-7">
            <section class="mblrc-form-card h-100">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="mdi mdi-source-branch" style="font-size: 1.25rem; color: #312e81;"></i>
                        <h2 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Certification Workflow History</h2>
                    </div>
                </div>
                <div class="mblrc-form-card-body p-3">
                    @forelse($workflowEvents as $event)
                        <div class="timeline-event-card">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong style="color: #0f172a; font-size: 0.925rem;">{{ $event['event'] }}</strong>
                                <span class="badge" style="background: #eef2ff; color: #312e81; font-weight: 750; font-size: 0.725rem; border-radius: 6px;">
                                    {{ $event['occurred_at']->format('M d, Y h:i A') }}
                                </span>
                            </div>
                            <div class="text-muted small mb-1">
                                <span class="font-weight-bold">{{ $event['from_status'] ?? 'Intake' }}</span> &rarr; <span class="font-weight-bold text-dark">{{ $event['to_status'] }}</span> &middot; {{ $event['actor'] }}
                            </div>
                            @if($event['completion_path'])
                                <div class="small font-weight-bold text-muted mt-1">
                                    <i class="mdi mdi-check-circle-outline text-primary mr-1"></i> {{ $event['completion_path'] }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="mdi mdi-information-outline d-block mb-1" style="font-size: 1.75rem;"></i>
                            No certification workflow events have been recorded.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Final Certification Versions --}}
        <div class="col-lg-5">
            <section class="mblrc-form-card h-100">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="mdi mdi-file-pdf" style="font-size: 1.25rem; color: #e11d48;"></i>
                        <h2 class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Final Certification Versions</h2>
                    </div>
                </div>
                <div class="mblrc-form-card-body p-3">
                    @forelse($documentVersions as $version)
                        <div class="timeline-event-card">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong style="color: #0f172a; font-size: 0.925rem;">Version {{ $version['version_number'] }}</strong>
                                @if($version['replaces_version_number'])
                                    <span class="badge" style="background: #f1f5f9; color: #475569; font-weight: 700; font-size: 0.725rem; border-radius: 6px;">
                                        Replaces v{{ $version['replaces_version_number'] }}
                                    </span>
                                @endif
                            </div>
                            <div class="font-weight-bold text-dark small mb-1">
                                <i class="mdi mdi-file-document mr-1 text-muted"></i> {{ $version['original_filename'] }}
                            </div>
                            <div class="text-muted small mb-1">
                                {{ $version['mime_type'] }} &middot; {{ number_format($version['size_bytes']) }} bytes &middot; {{ $version['uploaded_at']->format('M d, Y h:i A') }}
                            </div>
                            <div class="text-muted small">Uploaded by: {{ $version['uploaded_by'] }}</div>
                            <div class="text-muted small text-break mt-1 font-monospace" style="font-size: 0.725rem;">
                                SHA-256: {{ $version['sha256'] }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="mdi mdi-file-outline d-block mb-1" style="font-size: 1.75rem;"></i>
                            No final certification has been uploaded.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

