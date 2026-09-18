@extends('layouts.skydash-v')
@section('title', 'Boundary Editor')
@section('heading', 'Boundary Editor')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/ib39-map.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet-geoman.css') }}">
    <style>
        .editor-wrap { position: relative; height: calc(100vh - 70px); min-height: 520px; }
        #boundaryMap { position: absolute; inset: 0; }

        .editor-hint {
            position: absolute; top: 12px; left: 60px; z-index: 1000;
            background: rgba(255,255,255,.95); border-radius: 8px; padding: .5rem .85rem;
            font-size: .8rem; color: #334155; box-shadow: 0 3px 12px rgba(0,0,0,.18); max-width: 22rem;
        }
        .editor-hint strong { color: #35127d; }
        .editor-status {
            position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); z-index: 1000;
            background: #0f172a; color: #fff; border-radius: 9999px; padding: .4rem 1rem;
            font-size: .8rem; box-shadow: 0 4px 14px rgba(0,0,0,.25); opacity: 0; transition: opacity .2s;
        }
        .editor-status.show { opacity: 1; }
    </style>
@endpush

@section('content')
    <div class="editor-wrap"
         data-boundaries="{{ route('ib39.boundaries') }}"
         data-store="{{ route('ib39.boundaries.store') }}"
         data-update-template="{{ route('ib39.boundaries.update', ['area' => '__ID__']) }}"
         data-destroy-template="{{ route('ib39.boundaries.destroy', ['area' => '__ID__']) }}"
         data-municipalities='@json($municipalities)'>
        <div id="boundaryMap"></div>
        <div class="editor-hint">
            <strong>Edit boundaries.</strong> Use the toolbar (top-left) to draw a new area, drag
            vertices to reshape, cut a hole, or delete. Changes save automatically.
        </div>

        {{-- Import / export toolbar --}}
        <div class="editor-io">
            <a href="{{ route('ib39.boundaries.export') }}" class="btn btn-sm btn-light">
                <i class="mdi mdi-download"></i> Export GeoJSON
            </a>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="mdi mdi-upload"></i> Import GeoJSON
            </button>
        </div>

        @if (session('success'))
            <div class="editor-flash editor-flash-ok">{{ session('success') }}</div>
        @elseif (session('error'))
            <div class="editor-flash editor-flash-err">{{ session('error') }}</div>
        @endif

        <div class="editor-status" id="editorStatus"></div>
    </div>

    {{-- Import GeoJSON dialog --}}
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('ib39.boundaries.import') }}"
                  enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" style="color:#35127d;font-weight:bold;">Import boundaries</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="importFile">GeoJSON file</label>
                        <input type="file" name="file" id="importFile" class="form-control"
                               accept=".geojson,.json,application/geo+json,application/json" required>
                        <small class="text-muted">A FeatureCollection whose features carry
                            <code>municipality</code> + <code>barangay</code> (or <code>NAME_2</code>/<code>NAME_3</code>).</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">How to apply</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="modeMerge" value="merge" checked>
                            <label class="form-check-label" for="modeMerge">
                                <strong>Merge</strong> — update matching barangays, leave the rest as-is
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="modeReplace" value="replace">
                            <label class="form-check-label" for="modeReplace">
                                <strong>Replace</strong> — clear all geometry first, so dropped areas disappear
                            </label>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="create" id="createNew" value="1">
                        <label class="form-check-label" for="createNew">
                            Create new barangays for features with no match
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Naming dialog shown after drawing a brand-new polygon --}}
    <div class="modal fade" id="newAreaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="newAreaForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="color:#35127d;font-weight:bold;">Name the new area</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="newMunicipality">Municipality</label>
                        <input list="municipalityList" id="newMunicipality" class="form-control" required
                               placeholder="e.g. Digos City">
                        <datalist id="municipalityList"></datalist>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="newBarangay">Barangay</label>
                        <input id="newBarangay" class="form-control" required placeholder="e.g. Aplaya">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save area</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .editor-io {
        position: absolute; top: 12px; right: 12px; z-index: 1000;
        display: flex; gap: .5rem;
    }
    .editor-io .btn { box-shadow: 0 3px 12px rgba(0,0,0,.2); }
    .editor-flash {
        position: absolute; top: 60px; right: 12px; z-index: 1000;
        padding: .5rem .85rem; border-radius: 8px; font-size: .82rem; max-width: 22rem;
        box-shadow: 0 3px 12px rgba(0,0,0,.2);
    }
    .editor-flash-ok  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .editor-flash-err { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
</style>
@endpush
