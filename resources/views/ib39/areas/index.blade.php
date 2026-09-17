@extends('layouts.skydash-v')
@section('title', 'RCSP Areas')
@section('heading', 'RCSP Areas')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/ib39-areas.css') }}">
<style>
    .status-badge { color: #0f172a; border: 1px solid rgba(15,23,42,.12); }
</style>
@endpush

@section('content')
<div class="content-wrapper p-0">
    {{-- Executive Area Hero Header --}}
    <header class="ib39-areas-hero">
        <div class="hero-main">
            <div class="hero-icon-box">
                <i class="mdi mdi-map-marker-radius" aria-hidden="true"></i>
            </div>
            <div>
                <div class="hero-eyebrow">Geographic Threat Monitoring · 39th Infantry Battalion</div>
                <h2 class="mb-1">RCSP Barangays & Threat Areas</h2>
                <p class="mb-0">Manage and classify barangay conflict risk based on former rebel concentration and security monitoring.</p>
            </div>
        </div>
        <div class="d-inline-flex flex-wrap gap-2">
            <button type="button" class="hero-btn-primary" id="addRCSPButton" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="mdi mdi-map-marker-plus"></i>
                <span>Add RCSP Area</span>
            </button>
            <a href="{{ route('ib39.map') }}" class="hero-btn-glass">
                <i class="mdi mdi-crosshairs-gps"></i>
                <span>Tactical Map</span>
            </a>
        </div>
    </header>

    {{-- Filter Toolbar --}}
    <div class="ib39-filter-card">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6 col-12">
                <label for="searchInput" class="form-label text-muted small font-weight-bold text-uppercase mb-1">Search Areas</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="mdi mdi-magnify text-muted"></i></span>
                    <input name="search" id="searchInput" value="{{ request('search') }}"
                           placeholder="Search barangay, municipality..." class="form-control border-start-0 ps-0">
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-6">
                <label for="municipalityFilter" class="form-label text-muted small font-weight-bold text-uppercase mb-1">Municipality</label>
                <select name="municipality" id="municipalityFilter" class="form-select" onchange="this.form.submit()">
                    <option value="">All Municipalities</option>
                    @foreach ($municipalities as $municipality)
                        <option value="{{ $municipality->name }}" @selected(request('municipality') === $municipality->name)>
                            {{ $municipality->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label for="statusFilter" class="form-label text-muted small font-weight-bold text-uppercase mb-1">Threat Status</label>
                <select name="status" id="statusFilter" class="form-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    @foreach ($classifications as $classification)
                        <option value="{{ $classification['status'] }}" @selected(request('status') === $classification['status'])>
                            {{ $classification['status'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label for="frRangeFilter" class="form-label text-muted small font-weight-bold text-uppercase mb-1">FR Range</label>
                <select name="fr_range" id="frRangeFilter" class="form-select" onchange="this.form.submit()">
                    <option value="">All Counts</option>
                    @foreach (['0-9', '10-14', '15-19', '20+'] as $r)
                        <option value="{{ $r }}" @selected(request('fr_range') === $r)>{{ $r }} FR's</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-1 col-md-4 col-6 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100 py-2" title="Apply filter">
                    <i class="mdi mdi-filter-variant"></i>
                </button>
                @if (request()->hasAny(['search', 'municipality', 'status', 'fr_range']))
                    <a href="{{ route('ib39.areas.index') }}" id="clearFilters" class="btn btn-light border py-2" title="Clear filters">
                        <i class="mdi mdi-close"></i>
                    </a>
                @endif
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top flex-wrap gap-2">
            <span class="text-muted small" id="resultsCount">
                Showing <strong class="text-dark">{{ $areas->count() }}</strong> of <strong class="text-dark">{{ $areas->total() }}</strong> RCSP barangays
            </span>
            <div class="d-flex align-items-center gap-3 small text-muted flex-wrap">
                @foreach ($classifications as $classification)
                    <span>
                        <span class="badge border px-2 py-0" style="background-color: {{ $classification['color'] }}; color: #0f172a;">
                            {{ $classification['label'] }}
                        </span>
                        {{ $classification['status'] }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Main Area Table --}}
    <div class="ib39-table-card">
        <div class="table-responsive">
            <table class="table ib39-areas-table">
                <thead>
                    <tr>
                        <th>Province</th>
                        <th>Municipality / City</th>
                        <th>Barangay</th>
                        <th>Threat Status</th>
                        <th>Former Rebels</th>
                        <th class="text-end" style="width: 8rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($areas as $area)
                        @php
                            $current = $area->latestColorHistory;
                            $canonicalBarangay = $area->barangayRecord;
                            $canonicalMunicipality = $canonicalBarangay?->municipality;
                        @endphp
                        <tr>
                            <td><span class="text-muted">{{ $area->province }}</span></td>
                            <td><span class="font-weight-medium text-dark">{{ $canonicalMunicipality?->name ?? $area->municipality }}</span></td>
                            <td><span class="font-weight-bold text-dark">{{ $canonicalBarangay?->name ?? $area->barangay }}</span></td>
                            <td>
                                <span class="status-badge status-{{ strtolower($current?->status ?? 'unclassified') }}"
                                      style="background-color: {{ $current?->color }}">
                                    {{ $current?->status ?? 'Unclassified' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark font-weight-bold px-2 py-1 border">
                                    {{ $current?->frs ?? 0 }} FR{{ ($current?->frs ?? 0) === 1 ? '' : 's' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="ib39-action-buttons justify-content-end">
                                    @if ($canonicalBarangay)
                                        <button type="button" class="ib39-edit-btn js-edit-area"
                                                title="Edit RCSP Data" aria-label="Edit {{ $area->barangay }}"
                                                data-bs-toggle="modal" data-bs-target="#editModal"
                                                data-id="{{ $area->id }}"
                                                data-barangay-id="{{ $canonicalBarangay->id }}"
                                                data-municipality="{{ $canonicalMunicipality?->name ?? $area->municipality }}"
                                                data-barangay="{{ $canonicalBarangay->name }}"
                                                data-frs="{{ $current?->frs ?? 0 }}"
                                                data-effective-date="{{ now()->toDateString() }}"
                                                data-action="{{ route('ib39.areas.update', $area) }}">
                                            <i class="fa fa-pencil-square-o"></i>
                                        </button>
                                    @endif
                                    <form method="POST" action="{{ route('ib39.areas.destroy', $area) }}"
                                          data-confirm="Remove RCSP data for {{ $area->barangay }}? The barangay stays on the map but is reset to unclassified."
                                          data-confirm-title="Confirm remove" data-confirm-action="Remove" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="ib39-delete-btn" title="Delete RCSP Entry"
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
                                <i class="fa fa-search" style="font-size: 2.2rem; color: #cbd5e1; margin-bottom: 0.75rem; display: block;"></i>
                                <div class="font-weight-bold text-dark">No RCSP Barangays found</div>
                                <small class="text-muted">Click "Add RCSP Area" to record your first area entry</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($areas->hasPages())
            <div class="p-3 border-top bg-light d-flex justify-content-end">
                {{ $areas->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Add New RCSP Modal --}}
<div class="modal fade ib39-modal" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="ib39-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            <h2 class="ib39-modal-title is-add">Add New RCSP Area</h2>

            <form method="POST" action="{{ route('ib39.areas.store') }}">
                @csrf
                <div class="ib39-form-group">
                    <label>Province</label>
                    <input type="text" value="Davao del Sur" disabled>
                </div>
                <div class="ib39-form-group">
                    <label for="municipality-select">Municipality</label>
                    <select id="municipality-select" required
                            data-barangays="{{ route('ib39.barangays') }}">
                        <option value="">Select Municipality</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality->id }}">{{ $municipality->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ib39-form-group">
                    <label for="barangay-select">Barangay</label>
                    <select name="barangay_id" id="barangay-select" required disabled>
                        <option value="">Select Barangay</option>
                    </select>
                </div>
                <div class="ib39-form-group">
                    <label for="add-frs">Number of Former Rebels (FR's)</label>
                    <input type="number" name="frs" id="add-frs" min="0" placeholder="e.g. 12" required>
                    <div class="ib39-form-info">
                        This count automatically determines the threat classification:
                        <ul>
                            @foreach ($classifications as $classification)
                                <li>
                                    <span style="display:inline-block;width:.75rem;height:.75rem;background:{{ $classification['color'] }}"></span>
                                    {{ $classification['label'] }}: {{ $classification['status'] }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="ib39-form-group">
                    <label for="add-effective-date">Effective Date</label>
                    <input type="date" name="effective_date" id="add-effective-date"
                           value="{{ old('effective_date', now()->toDateString()) }}" required>
                </div>

                <button type="submit" class="ib39-submit-btn">Add Area History</button>
            </form>
        </div>
    </div>
</div>

{{-- Update RCSP Data Modal --}}
<div class="modal fade ib39-modal" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="ib39-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            <h2 class="ib39-modal-title is-edit">Update RCSP Area</h2>

            <form method="POST" id="editForm">
                @csrf @method('PUT')
                <input type="hidden" name="barangay_id" id="edit_barangay_id">
                <div class="ib39-form-group">
                    <label>Municipality</label>
                    <input type="text" id="edit_municipality" disabled>
                </div>
                <div class="ib39-form-group">
                    <label>Barangay</label>
                    <input type="text" id="edit_barangay" disabled>
                </div>
                <div class="ib39-form-group">
                    <label for="edit_frs">Number of Former Rebels (FR's)</label>
                    <input type="number" name="frs" id="edit_frs" min="0" required>
                    <div class="ib39-form-info">
                        Threat status and choropleth color are recalculated automatically upon saving.
                    </div>
                </div>
                <div class="ib39-form-group">
                    <label for="edit_effective_date">Effective Date</label>
                    <input type="date" name="effective_date" id="edit_effective_date" required>
                </div>

                <button type="submit" class="ib39-submit-btn">Update Area Data</button>
            </form>
        </div>
    </div>
</div>
@endsection
