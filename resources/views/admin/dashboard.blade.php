@extends('layouts.skydash-h')
@section('title', 'Dashboard')

@push('styles')
    {{-- Lifted verbatim from the legacy accounts/katuparan_center/index.php <style> block. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/katuparan-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
@endpush

@section('content')
    <div class="katuparan-dashboard-wrapper">
        {{-- Hero: interactive barangay map behind a left-aligned gradient overlay --}}
        <div class="hero-section">
            <div class="map-container">
                <div id="heroMap"
                     data-geojson="{{ route('admin.boundaries') }}"
                     data-areas="{{ route('admin.area.data') }}"></div>
            </div>

            <div class="hero-overlay">
                <div class="hero-content">
                    <div class="hero-badge">
                        <i class="mdi mdi-shield-check" style="color: #fbbf24; font-size: 1rem;"></i>
                        <span>SHIELD Program Dashboard</span>
                    </div>

                    <h1 class="hero-title">Strengthening Communities</h1>

                    <p class="hero-subtitle">
                        Empowering localities against discrimination through comprehensive programs
                        for former rebels and sustainable community development.
                    </p>

                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-icon-wrap">
                                <i class="mdi mdi-account-multiple"></i>
                            </div>
                            <div>
                                <span class="hero-stat-number">{{ $stats['former_rebels'] }}</span>
                                <span class="hero-stat-label">Former Rebels</span>
                            </div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-icon-wrap">
                                <i class="mdi mdi-map-marker-radius"></i>
                            </div>
                            <div>
                                <span class="hero-stat-number">{{ $stats['rcsp_barangays'] }}</span>
                                <span class="hero-stat-label">RCSP Barangays</span>
                            </div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-icon-wrap">
                                <i class="mdi mdi-city"></i>
                            </div>
                            <div>
                                <span class="hero-stat-number">{{ $stats['municipalities'] }}</span>
                                <span class="hero-stat-label">Municipalities</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Municipalities Overview Section --}}
        <div class="stats-section">
            <div class="section-header">
                <div class="section-eyebrow">
                    <i class="mdi mdi-shield-check"></i> Regional Monitoring
                </div>
                <h2 class="section-title">Municipality Overview</h2>
                <p class="section-subtitle">
                    Comprehensive breakdown of RCSP implementation across all municipalities in the region
                </p>
            </div>

            <div class="municipality-grid">
                @foreach ($municipalities as $m)
                    <div class="municipality-card">
                        <div class="municipality-header">
                            <div class="municipality-seal-box">
                                <img src="{{ asset('assets/LGUS/'.($m['seal'] ?? 'LGU.png')) }}"
                                     alt="{{ $m['name'] }}" class="municipality-seal"
                                     onerror="this.onerror=null;this.src='{{ asset('assets/LGUS/LGU.png') }}';">
                            </div>
                            <div class="municipality-info">
                                <h3>{{ $m['name'] }}</h3>
                                <span class="municipality-kind-badge">{{ $m['kind'] }}</span>
                            </div>
                        </div>
                        <div class="municipality-stats">
                            <div>
                                <div class="rcsp-count">{{ $m['total'] }}</div>
                                <div class="rcsp-label">RCSP Barangays</div>
                            </div>
                            <div class="municipality-stat-icon">
                                <i class="mdi mdi-home-map-marker"></i>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- RCSP Implementation Progress Chart --}}
        <div class="chart-section">
            <div class="chart-header">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="chart-header-icon">
                            <i class="mdi mdi-chart-bar"></i>
                        </div>
                        <div>
                            <h3 class="chart-title">RCSP Implementation Progress</h3>
                            <p class="chart-subtitle mb-0">Status comparison of recognized vs ongoing RCSP programs per municipality</p>
                        </div>
                    </div>
                </div>
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
    </div>
@endsection

@push('scripts')
@endpush
