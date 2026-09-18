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
        <div class="editor-status" id="editorStatus"></div>
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
