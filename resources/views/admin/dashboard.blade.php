@extends('layouts.skydash-h')
@section('title', 'Dashboard')

@push('styles')
    {{-- Lifted verbatim from the legacy accounts/katuparan_center/index.php <style> block. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/katuparan-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
@endpush

@section('content')
    {{-- Hero: interactive barangay map behind a left-aligned gradient overlay.
         The legacy page iframed 39th-IB/final_mapping/index.php here; the map is
         now rendered inline so it shares the app's data and styling. --}}
    <div class="hero-section">
        <div class="map-container">
            <div id="heroMap"
                 data-geojson="{{ route('admin.boundaries') }}"
                 data-areas="{{ route('admin.area.data') }}"></div>
        </div>

        <div class="hero-overlay">
            <div class="hero-content">
                <div class="hero-badge">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L13.09 8.26L20 9L13.09 9.74L12 16L10.91 9.74L4 9L10.91 8.26L12 2Z" />
                    </svg>
                    SHIELD Program Dashboard
                </div>

                <h1 class="hero-title">Strengthening Communities</h1>

                <p class="hero-subtitle">
                    Empowering localities against discrimination through comprehensive programs
                    for former rebels and sustainable community development.
                </p>

                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-number">{{ $stats['former_rebels'] }}</span>
                        <span class="hero-stat-label">Former Rebels</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-number">{{ $stats['rcsp_barangays'] }}</span>
                        <span class="hero-stat-label">RCSP Barangays</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-number">{{ $stats['municipalities'] }}</span>
                        <span class="hero-stat-label">Municipalities</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-section">
        <div class="section-header">
            <h2 class="section-title">Municipality Overview</h2>
            <p class="section-subtitle">
                Comprehensive breakdown of RCSP implementation across all municipalities in the region
            </p>
        </div>

        <div class="municipality-grid">
            @foreach ($municipalities as $m)
                <div class="municipality-card">
                    <div class="municipality-header">
                        <img src="{{ asset('assets/LGUS/'.($m['seal'] ?? 'LGU.png')) }}"
                             alt="{{ $m['name'] }}" class="municipality-seal"
                             onerror="this.onerror=null;this.src='{{ asset('assets/LGUS/LGU.png') }}';">
                        <div class="municipality-info">
                            <h3>{{ $m['name'] }}</h3>
                            <p>{{ $m['kind'] }}</p>
                        </div>
                    </div>
                    <div class="municipality-stats">
                        <div class="rcsp-count">{{ $m['total'] }}</div>
                        <div class="rcsp-label">RCSP Barangays</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="chart-section">
        <div class="chart-header">
            <h3 class="chart-title">RCSP Implementation Progress</h3>
            <div class="chart-legend">
                <div class="legend-item">
                    <div class="legend-color series-1"></div>
                    <span>Completed</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color series-2"></div>
                    <span>In Progress</span>
                </div>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="rcspProgressChart"
                    data-labels='@json($municipalities->pluck("name"))'
                    data-completed='@json($municipalities->pluck("recognized"))'
                    data-inprogress='@json($municipalities->pluck("in_progress"))'></canvas>
        </div>
    </div>
@endsection

@push('scripts')
@endpush
