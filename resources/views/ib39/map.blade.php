@extends('layouts.skydash-v')
@section('title', 'Map')
@section('heading', 'Map')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/ib39-map.css') }}">
@endpush

@section('content')
    {{-- Port of the legacy accounts/39th-IB/final_mapping/ module: Google
         Satellite basemap, the Davao del Sur barangay polygons drawn on top,
         and a slide-in detail panel with the colour history. --}}
    <div class="ib39-map-wrap">
        <div id="ib39FullMap"
             data-geojson="{{ route('ib39.boundaries') }}"
             data-areas="{{ route('ib39.area.data') }}"
             data-detail="{{ route('ib39.barangay.data') }}"
             data-update-template="{{ route('ib39.areas.update', ['area' => '__ID__']) }}"></div>

        <div class="ib39-detail" id="barangayDetail">
            <div class="ib39-detail-content">
                <h3>History Barangay Details: <span class="ib39-detail-close" onclick="closeIb39Sidebar()">&times;</span></h3>

                <div class="detail-label">Status:</div>
                <div class="color-indicator" id="infestation-color"></div>

                <div class="detail-label">Province:</div>
                <div class="detail-value" id="province-value"></div>

                <div class="detail-label">Municipality:</div>
                <div class="detail-value" id="municipality-value"></div>

                <div class="detail-label">Barangay:</div>
                <div class="detail-value" id="barangay-value"></div>

                <div class="detail-label">Status:</div>
                <div class="detail-value" id="status-value"></div>

                <div class="detail-label">Number of FR's:</div>
                <div class="detail-value" id="fr-count-value"></div>

                <div class="ib39-detail-section">
                    <div class="detail-label">Color History:</div>
                    <div class="color-history"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function closeIb39Sidebar() {
            document.getElementById('barangayDetail')?.classList.remove('active');
        }
    </script>
@endpush
