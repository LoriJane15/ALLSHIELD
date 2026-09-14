@extends('layouts.skydash-h')
@section('title', 'Locations')
@section('heading', 'RCSP Locations & GIS Coverage')

@php
    $totalMunicipalities = count($municipalities);
    $totalBarangays = $municipalities->sum(fn ($m) => $m['barangays']->count());
    $totalBeneficiaries = count($frPoints);

    $statusCounts = [
        'Active' => 0,
        'Reintegrated' => 0,
        'Under Review' => 0,
        'Inactive' => 0,
    ];

    foreach ($frPoints as $p) {
        $st = $p['status'] ?? 'Active';
        if (isset($statusCounts[$st])) {
            $statusCounts[$st]++;
        } else {
            $statusCounts['Active']++;
        }
    }
@endphp

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .locations-page-wrapper { width: 100%; padding: 0 0.15rem 2.5rem 0.15rem; }

    /* Stat Cards */
    .loc-stat-card {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04);
        transition: all 0.25s ease;
        height: 100%;
        position: relative;
        overflow: hidden;
    }
    .loc-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.09);
        border-color: rgba(37, 99, 235, 0.35);
    }
    .loc-stat-card::before {
        content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 16px 0 0 16px;
    }
    .loc-stat-card.stat-blue::before { background: #2563eb; }
    .loc-stat-card.stat-indigo::before { background: #4f46e5; }
    .loc-stat-card.stat-sky::before { background: #0284c7; }
    .loc-stat-card.stat-amber::before { background: #d97706; }

    .loc-icon-wrap {
        width: 48px; height: 48px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.45rem; flex-shrink: 0;
    }
    .loc-icon-wrap.icon-blue { background: rgba(37, 99, 235, 0.09); color: #2563eb; border: 1px solid rgba(37, 99, 235, 0.18); }
    .loc-icon-wrap.icon-indigo { background: rgba(79, 70, 229, 0.09); color: #4f46e5; border: 1px solid rgba(79, 70, 229, 0.18); }
    .loc-icon-wrap.icon-sky { background: rgba(2, 132, 199, 0.09); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.18); }
    .loc-icon-wrap.icon-amber { background: rgba(217, 119, 6, 0.09); color: #d97706; border: 1px solid rgba(217, 119, 6, 0.18); }

    .loc-stat-value {
        font-size: 1.85rem; font-weight: 800; line-height: 1.15; color: #0f172a; margin-bottom: 0.15rem; letter-spacing: -0.02em;
    }
    .loc-stat-label {
        font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; margin: 0;
    }

    /* GIS Map Command Card */
    .gis-map-card {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 20px;
        box-shadow: 0 6px 24px -4px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .gis-map-header {
        padding: 1.2rem 1.5rem;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .gis-header-left { display: flex; align-items: center; gap: 0.85rem; }
    .gis-header-icon {
        width: 44px; height: 44px; border-radius: 12px;
        background: rgba(37, 99, 235, 0.08); color: #2563eb;
        border: 1px solid rgba(37, 99, 235, 0.15);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.35rem; flex-shrink: 0;
    }
    .gis-map-title { font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.15rem 0; }
    .gis-map-subtitle { font-size: 0.82rem; color: #64748b; margin: 0; }

    .gis-map-canvas-wrap { position: relative; height: 520px; width: 100%; background: #f1f5f9; }
    #locationsMap { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 1; }

    /* Map Legend */
    .map-floating-legend {
        position: absolute; bottom: 20px; left: 20px; z-index: 1000;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 12px; padding: 0.65rem 1rem;
        box-shadow: 0 10px 25px -4px rgba(15, 23, 42, 0.15);
        display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
    }
    .legend-item { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; font-weight: 600; color: #1e293b; }
    .legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .legend-dot.dot-active { background: #2563eb; box-shadow: 0 0 8px rgba(37, 99, 235, 0.5); }
    .legend-dot.dot-reintegrated { background: #4f46e5; box-shadow: 0 0 8px rgba(79, 70, 229, 0.5); }
    .legend-dot.dot-review { background: #d97706; box-shadow: 0 0 8px rgba(217, 119, 6, 0.5); }
    .legend-dot.dot-inactive { background: #64748b; }

    /* Leaflet Popups */
    .leaflet-popup-content-wrapper {
        background: #ffffff !important; border-radius: 14px !important;
        box-shadow: 0 12px 30px -4px rgba(15, 23, 42, 0.22) !important;
        border: 1px solid rgba(226, 232, 240, 0.9) !important; padding: 0 !important; overflow: hidden;
    }
    .leaflet-popup-content { margin: 0 !important; line-height: 1.4 !important; }
    .gis-popup-card { padding: 1rem 1.15rem; min-width: 220px; }
    .gis-popup-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; gap: 0.5rem; }
    .gis-popup-name { font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0; }
    .gis-popup-badge { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; padding: 2px 8px; border-radius: 9999px; letter-spacing: 0.04em; white-space: nowrap; }
    .gis-popup-badge.badge-active { background: rgba(37, 99, 235, 0.1); color: #1e40af; }
    .gis-popup-badge.badge-reintegrated { background: rgba(79, 70, 229, 0.1); color: #4338ca; }
    .gis-popup-badge.badge-review { background: rgba(217, 119, 6, 0.1); color: #b45309; }
    .gis-popup-badge.badge-inactive { background: rgba(100, 116, 139, 0.1); color: #475569; }
    .gis-popup-address { font-size: 0.8rem; color: #64748b; display: flex; align-items: flex-start; gap: 0.35rem; margin: 0; }

    /* Municipality Directory */
    .muni-directory-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;
    }
    .muni-directory-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0; }
    .muni-search-box { position: relative; min-width: 280px; }
    .muni-search-box input {
        width: 100%; padding: 0.55rem 1rem 0.55rem 2.4rem;
        background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px;
        font-size: 0.85rem; color: #1e293b; outline: none; transition: all 0.2s ease;
    }
    .muni-search-box input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }
    .muni-search-box i { position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1.1rem; }

    .muni-card {
        background: #ffffff; border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 18px; box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04);
        margin-bottom: 1.5rem; overflow: hidden; transition: all 0.25s ease;
    }
    .muni-card:hover { box-shadow: 0 10px 28px -4px rgba(15, 23, 42, 0.08); border-color: rgba(37, 99, 235, 0.28); }
    .muni-card-header {
        padding: 1.25rem 1.5rem; background: #ffffff; border-bottom: 1px solid #f1f5f9;
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
    }
    .muni-name-wrap { display: flex; align-items: center; gap: 0.85rem; }
    .muni-icon-seal {
        width: 44px; height: 44px; border-radius: 12px;
        background: rgba(37, 99, 235, 0.08); color: #2563eb;
        border: 1px solid rgba(37, 99, 235, 0.15);
        display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;
    }
    .muni-title { font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.15rem 0; }
    .muni-badge-count { font-size: 0.76rem; font-weight: 700; padding: 0.3rem 0.85rem; border-radius: 20px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    .muni-barangays-grid { padding: 1.25rem 1.5rem; }
    .barangay-node-card {
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px;
        padding: 1.1rem 1.25rem; transition: all 0.22s ease;
        text-decoration: none !important; display: flex; flex-direction: column;
        justify-content: space-between; height: 100%; color: inherit !important;
    }
    .barangay-node-card:hover {
        background: #ffffff; border-color: #2563eb; transform: translateY(-3px);
        box-shadow: 0 10px 22px -3px rgba(37, 99, 235, 0.14); text-decoration: none !important; color: inherit !important;
    }
    .barangay-node-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.75rem; }
    .barangay-node-name { font-size: 0.92rem; font-weight: 700; color: #0f172a !important; margin: 0; line-height: 1.35; text-decoration: none !important; transition: color 0.2s ease; }
    .barangay-node-card:hover .barangay-node-name { color: #2563eb !important; }

    .barangay-phase-badge {
        font-size: 0.7rem; font-weight: 700; padding: 3px 8px; border-radius: 6px;
        white-space: nowrap; text-transform: uppercase; letter-spacing: 0.02em; text-decoration: none !important;
    }
    .phase-p0 { background: #e2e8f0; color: #475569; }
    .phase-p1 { background: rgba(2, 132, 199, 0.12); color: #0369a1; }
    .phase-p2 { background: rgba(79, 70, 229, 0.12); color: #4338ca; }
    .phase-p3 { background: rgba(37, 99, 235, 0.12); color: #1d4ed8; }
    .phase-p4 { background: rgba(217, 119, 6, 0.12); color: #b45309; }
    .phase-p5 { background: rgba(30, 58, 138, 0.15); color: #1e3a8a; }

    .barangay-progress-bar-wrap { margin-top: 0.5rem; text-decoration: none !important; }
    .barangay-progress-track { height: 6px; background: #e2e8f0; border-radius: 9999px; overflow: hidden; }
    .barangay-progress-fill { height: 100%; border-radius: 9999px; background: linear-gradient(90deg, #2563eb, #4f46e5); transition: width 0.3s ease; }
    .barangay-progress-label { display: flex; justify-content: space-between; align-items: center; font-size: 0.72rem; color: #64748b; margin-top: 0.4rem; font-weight: 600; text-decoration: none !important; }
</style>
@endpush

@section('content')
<div class="locations-page-wrapper">
    {{-- Top Operational Metrics --}}
    <div class="row mb-4">
        <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
            <div class="loc-stat-card stat-blue">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="loc-stat-value">{{ number_format($totalMunicipalities) }}</div>
                        <div class="loc-stat-label">Municipalities</div>
                    </div>
                    <div class="loc-icon-wrap icon-blue">
                        <i class="mdi mdi-city"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
            <div class="loc-stat-card stat-indigo">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="loc-stat-value">{{ number_format($totalBarangays) }}</div>
                        <div class="loc-stat-label">RCSP Priority Barangays</div>
                    </div>
                    <div class="loc-icon-wrap icon-indigo">
                        <i class="mdi mdi-map-marker-multiple"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
            <div class="loc-stat-card stat-sky">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="loc-stat-value">{{ number_format($totalBeneficiaries) }}</div>
                        <div class="loc-stat-label">Mapped Beneficiaries</div>
                    </div>
                    <div class="loc-icon-wrap icon-sky">
                        <i class="mdi mdi-account-group"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
            <div class="loc-stat-card stat-amber">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="loc-stat-value">{{ number_format($statusCounts['Active'] + $statusCounts['Reintegrated']) }}</div>
                        <div class="loc-stat-label">Active &bull; Reintegrated</div>
                    </div>
                    <div class="loc-icon-wrap icon-amber">
                        <i class="mdi mdi-shield-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- GIS Operational Map Command Card --}}
    <div class="gis-map-card">
        <div class="gis-map-header">
            <div class="gis-header-left">
                <div class="gis-header-icon">
                    <i class="mdi mdi-crosshairs-gps"></i>
                </div>
                <div>
                    <h4 class="gis-map-title">Geographic Information System (GIS) Monitoring</h4>
                    <p class="gis-map-subtitle">Live spatial distribution of geotagged Former Rebels and RCSP priority operational areas.</p>
                </div>
            </div>
            <div class="gis-map-controls">
                <button type="button" class="btn btn-sm btn-outline-secondary bg-white px-3" id="btnResetMap" style="border-radius: 8px; font-weight: 600;">
                    <i class="mdi mdi-refresh mr-1"></i> Reset View
                </button>
            </div>
        </div>

        <div class="gis-map-canvas-wrap">
            <div id="locationsMap"></div>

            {{-- Floating Glassmorphic Map Legend --}}
            <div class="map-floating-legend">
                <div class="legend-item">
                    <span class="legend-dot dot-active"></span>
                    <span>Active ({{ $statusCounts['Active'] }})</span>
                </div>
                <div class="legend-item">
                    <span class="legend-dot dot-reintegrated"></span>
                    <span>Reintegrated ({{ $statusCounts['Reintegrated'] }})</span>
                </div>
                <div class="legend-item">
                    <span class="legend-dot dot-review"></span>
                    <span>Under Review ({{ $statusCounts['Under Review'] }})</span>
                </div>
                <div class="legend-item">
                    <span class="legend-dot dot-inactive"></span>
                    <span>Inactive ({{ $statusCounts['Inactive'] }})</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Municipality & RCSP Barangay Directory --}}
    <div class="muni-directory-section">
        <div class="muni-directory-header">
            <div>
                <h3 class="muni-directory-title">
                    <i class="mdi mdi-home-map-marker" style="color: #2563eb;"></i>
                    Municipality &amp; Priority Barangay Coverage
                </h3>
                <p class="text-muted small mb-0">Operational implementation phase progress across covered local government units.</p>
            </div>
            <div class="muni-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="text" id="muniFilterInput" placeholder="Filter municipality or barangay..." autocomplete="off">
            </div>
        </div>

        <div id="muniCardsContainer">
            @forelse ($municipalities as $m)
                @php
                    $bCount = $m['barangays']->count();
                    $avgProgress = $bCount > 0 ? round($m['barangays']->avg('progress')) : 0;
                @endphp
                <div class="muni-card muni-search-item" data-search-text="{{ strtolower($m['name'] . ' ' . $m['barangays']->pluck('barangay.name')->implode(' ')) }}">
                    <div class="muni-card-header">
                        <div class="muni-name-wrap">
                            <div class="muni-icon-seal">
                                <i class="mdi mdi-city-variant-outline"></i>
                            </div>
                            <div>
                                <h4 class="muni-title">{{ $m['name'] }}</h4>
                                <span class="text-muted small">Davao del Sur &bull; {{ $bCount }} Priority {{ Str::plural('Barangay', $bCount) }}</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center" style="gap: 0.75rem;">
                            <div class="text-right d-none d-sm-block">
                                <div class="small font-weight-bold text-dark">{{ $avgProgress }}% Average RCSP Completion</div>
                                <div class="progress" style="height: 6px; width: 140px; border-radius: 9999px; background: #e2e8f0;">
                                    <div class="progress-bar" style="width: {{ $avgProgress }}%; background: linear-gradient(90deg, #2563eb, #4f46e5); border-radius: 9999px;"></div>
                                </div>
                            </div>
                            <span class="muni-badge-count">{{ $bCount }} {{ Str::plural('Barangay', $bCount) }}</span>
                        </div>
                    </div>

                    <div class="muni-barangays-grid">
                        <div class="row">
                            @foreach ($m['barangays'] as $b)
                                @php
                                    $bName = $b->barangay?->name ?? 'Barangay #'.$b->barangay_id;
                                    $phase = $b->current_phase ?? 0;
                                    $progress = $b->progress ?? 0;
                                    $phaseLabel = match((int)$phase) {
                                        0 => 'P0 · Immersion',
                                        1 => 'P1 · Needs Assessment',
                                        2 => 'P2 · Formulation',
                                        3 => 'P3 · Implementation',
                                        4 => 'P4 · Monitoring',
                                        5 => 'P5 · Completed',
                                        default => 'P'.$phase,
                                    };
                                    $phaseClass = 'phase-p' . min(5, max(0, (int)$phase));
                                @endphp
                                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                                    <a href="{{ route('admin.rcsp.show', $b->id) }}" class="barangay-node-card">
                                        <div>
                                            <div class="barangay-node-top">
                                                <h5 class="barangay-node-name">{{ $bName }}</h5>
                                                <span class="barangay-phase-badge {{ $phaseClass }}">{{ $phaseLabel }}</span>
                                            </div>
                                        </div>
                                        <div class="barangay-progress-bar-wrap">
                                            <div class="barangay-progress-track">
                                                <div class="barangay-progress-fill" style="width: {{ $progress }}%;"></div>
                                            </div>
                                            <div class="barangay-progress-label">
                                                <span>Phase {{ $phase }}/5</span>
                                                <span class="font-weight-bold">{{ $progress }}% Complete</span>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="card p-5 text-center">
                    <p class="text-muted mb-0">No RCSP locations currently assigned.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const map = L.map('locationsMap', { zoomControl: false }).setView([6.7497, 125.3572], 10);
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    setTimeout(() => map.invalidateSize(), 250);

    const points = @json($frPoints);
    const colors = {
        'Active': '#2563eb',
        'Reintegrated': '#4f46e5',
        'Under Review': '#d97706',
        'Inactive': '#64748b'
    };

    const badgeClasses = {
        'Active': 'badge-active',
        'Reintegrated': 'badge-reintegrated',
        'Under Review': 'badge-review',
        'Inactive': 'badge-inactive'
    };

    const bounds = [];

    points.forEach(p => {
        const st = p.status || 'Active';
        const c = colors[st] || '#2563eb';
        const badgeCls = badgeClasses[st] || 'badge-active';

        const marker = L.circleMarker([p.lat, p.lng], {
            radius: 7,
            color: '#ffffff',
            fillColor: c,
            fillOpacity: 0.9,
            weight: 2
        });

        const popupContent = `
            <div class="gis-popup-card">
                <div class="gis-popup-header">
                    <span class="gis-popup-name">${p.name}</span>
                    <span class="gis-popup-badge ${badgeCls}">${st}</span>
                </div>
                <p class="gis-popup-address">
                    <i class="mdi mdi-map-marker" style="color: #2563eb; font-size: 1rem;"></i>
                    ${p.address || 'Location Coordinates Logged'}
                </p>
                <div class="mt-2 pt-2 border-top text-muted" style="font-size: 0.7rem;">
                    Lat: ${p.lat.toFixed(5)} &bull; Lng: ${p.lng.toFixed(5)}
                </div>
            </div>
        `;

        marker.bindPopup(popupContent).addTo(map);
        bounds.push([p.lat, p.lng]);
    });

    if (bounds.length) {
        map.fitBounds(bounds, { padding: [50, 50] });
    }

    // Reset Map View Button
    const btnReset = document.getElementById('btnResetMap');
    if (btnReset && bounds.length) {
        btnReset.addEventListener('click', () => {
            map.fitBounds(bounds, { padding: [50, 50] });
        });
    }

    // Client-side search filter for municipalities and barangays
    const filterInput = document.getElementById('muniFilterInput');
    const searchItems = document.querySelectorAll('.muni-search-item');

    if (filterInput) {
        filterInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase().trim();
            searchItems.forEach(function(item) {
                const text = item.getAttribute('data-search-text') || '';
                if (!term || text.includes(term)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
})();
</script>
@endpush
