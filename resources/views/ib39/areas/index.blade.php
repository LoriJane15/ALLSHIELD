@extends('layouts.skydash-v')
@section('title', 'Add Area')
@section('heading', 'RCSP Areas')

@php
    $statusClass = fn ($s) => 'status-'.strtolower($s ?: 'unclassified');
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/ib39-areas.css') }}">
<style>
    .status-badge {
        padding: .4rem .9rem; border-radius: 9999px; font-size: .72rem; font-weight: 600;
        text-transform: uppercase; letter-spacing: .05em; display: inline-flex; align-items: center; gap: .375rem;
        box-shadow: 0 2px 4px rgba(0,0,0,.05);
    }
    .status-konsolidado { background: linear-gradient(135deg,#fecaca,#fee2e2); color: #dc2626; border: 1px solid #fecaca; }
    .status-rekonsilida { background: linear-gradient(135deg,#fed7aa,#ffedd5); color: #ea580c; border: 1px solid #fed7aa; }
    .status-expansion   { background: linear-gradient(135deg,#fef08a,#fef9c3); color: #ca8a04; border: 1px solid #fef08a; }
    .status-recovery    { background: linear-gradient(135deg,#86efac,#dcfce7); color: #16a34a; border: 1px solid #86efac; }
    .status-unclassified { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
</style>
@endpush

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h3 class="font-weight-bold mb-0">RCSP Barangays</h3>
                    <p class="text-muted mb-0">Set former-rebel counts to classify each barangay.</p>
                </div>
                <button type="button" class="btn btn-primary" id="addRCSPButton"
                        data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="mdi mdi-plus"></i> Add RCSP
                </button>
            </div>

            {{-- Filters: search, municipality, status, FR range --}}
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="input-group" style="width:16rem;">
                    <span class="input-group-text bg-white"><i class="mdi mdi-magnify"></i></span>
                    <input name="search" id="searchInput" value="{{ request('search') }}"
                           placeholder="Search barangays, municipalities…" class="form-control">
                </div>
                <select name="municipality" id="municipalityFilter" class="form-select" style="width:12rem;" onchange="this.form.submit()">
                    <option value="">All Municipalities</option>
                    @foreach ($municipalities as $m)
                        <option value="{{ $m }}" @selected(request('municipality') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
                <select name="status" id="statusFilter" class="form-select" style="width:11rem;" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    @foreach (['Konsolidado', 'Rekonsilida', 'Expansion', 'Recovery'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
                <select name="fr_range" id="frRangeFilter" class="form-select" style="width:10rem;" onchange="this.form.submit()">
                    <option value="">All FR's</option>
                    @foreach (['0-9', '10-14', '15-19', '20+'] as $r)
                        <option value="{{ $r }}" @selected(request('fr_range') === $r)>{{ $r }} FR's</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-secondary"><i class="mdi mdi-filter-variant"></i> Filter</button>
                @if (request()->hasAny(['search', 'municipality', 'status', 'fr_range']))
                    <a href="{{ route('ib39.areas.index') }}" id="clearFilters" class="btn btn-light">
                        <i class="mdi mdi-close"></i> Clear
                    </a>
                @endif
            </form>

            <p class="text-muted small" id="resultsCount">
                Showing {{ $areas->count() }} of {{ $areas->total() }} RCSP barangays
                <span class="ms-2">Thresholds: ≥20 Konsolidado · ≥15 Rekonsilida · ≥10 Expansion · &lt;10 Recovery</span>
            </p>

            <div class="table-responsive">
                <table class="table table-hover ib39-areas-table">
                    <thead>
                        <tr>
                            <th>Province</th>
                            <th>Municipality/City</th>
                            <th>Barangay</th>
                            <th>Status</th>
                            <th>FR's</th>
                            <th style="width:11rem">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($areas as $area)
                            <tr>
                                <td>Davao del Sur</td>
                                <td>{{ $area->municipality }}</td>
                                <td class="font-weight-medium">{{ $area->barangay }}</td>
                                <td><span class="status-badge {{ $statusClass($area->status) }}">{{ $area->status ?: 'Unclassified' }}</span></td>
                                <td>{{ $area->frs }}</td>
                                <td>
                                    <div class="ib39-action-buttons">
                                        {{-- Opened declaratively by Bootstrap; the JS only fills in the fields. --}}
                                        <button type="button" class="ib39-edit-btn js-edit-area"
                                                title="Edit RCSP" aria-label="Edit {{ $area->barangay }}"
                                                data-bs-toggle="modal" data-bs-target="#editModal"
                                                data-id="{{ $area->id }}"
                                                data-municipality="{{ $area->municipality }}"
                                                data-barangay="{{ $area->barangay }}"
                                                data-frs="{{ $area->frs }}"
                                                data-action="{{ route('ib39.areas.update', $area) }}">
                                            <i class="fa fa-pencil-square-o"></i>
                                        </button>
                                        <form method="POST" action="{{ route('ib39.areas.destroy', $area) }}"
                                              data-confirm="Remove RCSP data for {{ $area->barangay }}? The barangay stays on the map but is reset to unclassified."
                                              data-confirm-title="Confirm remove" data-confirm-action="Remove">
                                            @csrf @method('DELETE')
                                            <button class="ib39-delete-btn" title="Delete RCSP"
                                                    aria-label="Delete {{ $area->barangay }}">
                                                <i class="fa fa-trash-o"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="ib39-no-results">
                                    <i class="fa fa-search" style="font-size:2rem;color:#cbd5e1;margin-bottom:1rem;display:block;"></i>
                                    <div>No RCSP Barangays found</div>
                                    <small>Click "Add RCSP" to add your first RCSP entry</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $areas->links() }}</div>
        </div>
    </div>

    {{-- Add New RCSP — municipality drives the barangay dropdown, as in the legacy page. --}}
    <div class="modal fade ib39-modal" id="addModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <button type="button" class="ib39-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                <h2 class="ib39-modal-title is-add">Add New RCSP</h2>

                <form method="POST" action="{{ route('ib39.areas.store') }}">
                    @csrf
                    <div class="ib39-form-group">
                        <label>Province</label>
                        <input type="text" value="Davao del Sur" disabled>
                    </div>
                    <div class="ib39-form-group">
                        <label for="municipality-select">Municipality</label>
                        <select name="municipality" id="municipality-select" required
                                data-barangays="{{ route('ib39.barangays') }}">
                            <option value="">Select Municipality</option>
                            @foreach ($municipalities as $m)
                                <option value="{{ $m }}">{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ib39-form-group">
                        <label for="barangay-select">Barangay</label>
                        <select name="barangay" id="barangay-select" required disabled>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                    <div class="ib39-form-group">
                        <label for="add-frs">Number of FR's</label>
                        <input type="number" name="frs" id="add-frs" min="0" required>
                        <div class="ib39-form-info">
                            This will determine the colour on the map:
                            <ul>
                                <li>20+ FR's: Red (Konsolidado)</li>
                                <li>15–19 FR's: Orange (Rekonsilida)</li>
                                <li>10–14 FR's: Yellow (Expansion)</li>
                                <li>0–9 FR's: Green (Recovery)</li>
                            </ul>
                        </div>
                    </div>

                    <button type="submit" class="ib39-submit-btn">Add Area History</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Update RCSP Data --}}
    <div class="modal fade ib39-modal" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <button type="button" class="ib39-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                <h2 class="ib39-modal-title is-edit">Update RCSP Data</h2>

                <form method="POST" id="editForm">
                    @csrf @method('PUT')
                    <div class="ib39-form-group">
                        <label>Municipality</label>
                        <input type="text" id="edit_municipality" disabled>
                    </div>
                    <div class="ib39-form-group">
                        <label>Barangay</label>
                        <input type="text" id="edit_barangay" disabled>
                    </div>
                    <div class="ib39-form-group">
                        <label for="edit_frs">Number of FR's</label>
                        <input type="number" name="frs" id="edit_frs" min="0" required>
                        <div class="ib39-form-info">Status and colour are recalculated automatically.</div>
                    </div>

                    <button type="submit" class="ib39-submit-btn">Update</button>
                </form>
            </div>
        </div>
    </div>
@endsection
