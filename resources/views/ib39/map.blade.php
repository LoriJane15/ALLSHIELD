@extends('layouts.skydash-v')
@section('title', 'Tactical Area Conflict Map')
@section('heading', 'Operational Tactical Map')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/ib39-map.css') }}">
    <style>
        .map-hero {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 14px !important;
            color: #ffffff !important;
            padding: 1.25rem 1.75rem !important;
            margin-bottom: 1.25rem !important;
            box-shadow: 0 8px 24px -4px rgba(30, 27, 75, 0.25) !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.25rem;
            position: relative !important;
            overflow: hidden !important;
        }
        .map-hero::after {
            content: '';
            position: absolute;
            right: -30px;
            bottom: -30px;
            width: 160px;
            height: 160px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .map-hero .hero-main {
            display: flex;
            align-items: center;
            gap: 1.15rem;
            min-width: 0;
            position: relative;
            z-index: 1;
        }
        .map-hero .hero-icon-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            flex: 0 0 50px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: #67e8f9;
            font-size: 1.45rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .map-hero .hero-eyebrow {
            color: #f59e0b;
            font-size: 0.6875rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 0.2rem;
        }
        .map-hero h2 {
            color: #ffffff !important;
            font-size: 1.35rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.01em !important;
            margin: 0 0 0.2rem 0 !important;
            line-height: 1.25 !important;
        }
        .map-hero p {
            font-size: 0.85rem !important;
            color: #c7d2fe !important;
            margin: 0 !important;
            font-weight: 500 !important;
        }
        .map-hero .hero-btn-primary {
            background: #ffffff !important;
            color: #1e1b4b !important;
            border: none !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15) !important;
            font-weight: 700 !important;
            font-size: 0.84rem !important;
            border-radius: 9px !important;
            min-height: 40px !important;
            padding: 0.5rem 1.15rem !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.45rem !important;
            text-decoration: none !important;
            transition: all 0.2s ease !important;
        }
        .map-hero .hero-btn-primary i {
            color: #4338ca !important;
            font-size: 1.15rem !important;
        }
        .map-hero .hero-btn-primary:hover {
            background: #f8fafc !important;
            color: #1e1b4b !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2) !important;
        }
        .map-hero .hero-btn-glass {
            background: rgba(255, 255, 255, 0.16) !important;
            color: #ffffff !important;
            border: 1px solid rgba(255, 255, 255, 0.4) !important;
            backdrop-filter: blur(8px) !important;
            font-weight: 600 !important;
            font-size: 0.84rem !important;
            border-radius: 9px !important;
            min-height: 40px !important;
            padding: 0.5rem 1.15rem !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.45rem !important;
            text-decoration: none !important;
            transition: all 0.2s ease !important;
        }
        .map-hero .hero-btn-glass:hover {
            background: rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.6) !important;
            transform: translateY(-1px);
        }
    </style>
@endpush

@section('content')
    {{-- Executive Map Hero Header --}}
    <header class="map-hero">
        <div class="hero-main">
            <div class="hero-icon-box">
                <i class="mdi mdi-crosshairs-gps" aria-hidden="true"></i>
            </div>
            <div>
                <div class="hero-eyebrow">Geographic Intelligence · 39th Infantry Battalion</div>
                <h2 class="mb-1">Area Conflict & RCSP Deployment Map</h2>
                <p class="mb-0">Interactive geographic intelligence, conflict monitoring, and barangay status records.</p>
            </div>
        </div>
        <div class="d-inline-flex flex-wrap gap-2">
            <a href="{{ route('ib39.areas.index') }}" class="hero-btn-glass">
                <i class="mdi mdi-format-list-bulleted"></i>
                <span>View Area List</span>
            </a>
            <a href="{{ route('ib39.fr-profiles.create') }}" class="hero-btn-primary">
                <i class="mdi mdi-account-plus"></i>
                <span>Record Surfaced FR</span>
            </a>
        </div>
    </header>

    {{-- Port of the legacy accounts/39th-IB/final_mapping/ module: Google
         Satellite basemap, the Davao del Sur barangay polygons drawn on top,
         and a slide-in detail panel with the colour history. --}}
    <div class="ib39-map-wrap">
        <div class="ib39-map-topbar">
            <div class="ib39-map-badge">
                <span class="pulse-dot"></span>
                <span>Tactical Operational Grid</span>
            </div>
        </div>

        <div id="ib39FullMap"
             data-geojson="{{ route('ib39.boundaries') }}"
             data-areas="{{ route('ib39.area.data') }}"
             data-detail="{{ route('ib39.barangay.data') }}"
             data-update-template="{{ route('ib39.areas.update', ['area' => '__ID__']) }}"></div>

        <div class="ib39-detail" id="barangayDetail">
            <div class="ib39-detail-content">
                <div class="ib39-detail-header">
                    <h3>History Barangay Details: <span class="ib39-detail-close" onclick="closeIb39Sidebar()">&times;</span></h3>
                </div>

                <div class="detail-label">Status Color Indicator:</div>
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
