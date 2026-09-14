@extends('layouts.skydash-v')
@section('title', 'MBLRC Dashboard')
@section('heading', 'MBLRC Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/MarkerCluster.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/MarkerCluster.Default.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
@endpush

@section('content')
<div class="mblrc-dashboard-container">
    {{-- Modern Hero Banner (SHIELD Brand Design) --}}
    <div class="mblrc-hero mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="position: relative; z-index: 2; gap: 1rem;">
            <div class="d-flex align-items-center" style="gap: 1.15rem;">
                <div class="mblrc-hero-icon-box">
                    <i class="mdi mdi-sprout"></i>
                </div>
                <div>
                    <div class="mblrc-hero-eyebrow">
                        Mindanao Baptist Rural Learning Center
                    </div>
                    <h1 class="mblrc-hero-title">Welcome Mindanao Baptist Rural Learning Center</h1>
                    <p class="mblrc-hero-sub">
                        All systems are running smoothly! You have <span class="font-weight-bold" style="color: #fbbf24;">{{ $stats['not_started'] }} not-started programs!</span>
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center flex-wrap" style="gap: 0.75rem;">
                <div class="dropdown">
                    <button class="btn btn-sm" type="button"
                            id="dropdownMenuDate2" data-bs-toggle="dropdown" aria-expanded="false"
                            style="background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.25); color: #ffffff; font-weight: 700; border-radius: 8px; padding: 0.45rem 0.9rem;">
                        <i class="mdi mdi-calendar mr-1"></i> Today ({{ now()->format('d M Y') }})
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="dropdownMenuDate2" style="border-radius: 10px; border: 1px solid #e2e8f0;">
                        <a class="dropdown-item" href="#">January - March</a>
                        <a class="dropdown-item" href="#">March - June</a>
                        <a class="dropdown-item" href="#">June - August</a>
                        <a class="dropdown-item" href="#">August - November</a>
                    </div>
                </div>
                <a class="btn" href="{{ route('mblrc.fr.index') }}" style="background: #ffffff; color: #312e81; font-weight: 800; font-size: 0.875rem; padding: 0.55rem 1.25rem; border-radius: 10px; border: none; box-shadow: 0 4px 14px rgba(0,0,0,0.18); transition: all 0.2s ease;">
                    <i class="mdi mdi-account-group mr-1"></i> Former Rebels
                </a>
            </div>
        </div>
    </div>

    {{-- Headline Primary 4 Metrics Cards --}}
    <div class="row mb-4">
        @php
            $cards = [
                ['Total Registered Former Rebels', $stats['registered'], 'mblrc-card-registered', 'fa-users', $asOf['registered'], 100, '#312e81'],
                ['Total Enrolled in Program', $stats['active'], 'mblrc-card-enrolled', 'fa-graduation-cap', $asOf['enrolled'],
                    $stats['registered'] ? $stats['active'] / $stats['registered'] * 100 : 0, '#d97706'],
                ['Total Completed 3-Month Program', $stats['completed'], 'mblrc-card-completed', 'fa-trophy', $asOf['completed'],
                    $stats['active'] ? $stats['completed'] / $stats['active'] * 100 : 0, '#2563eb'],
                ['Total Former Rebels Reintegrated', $stats['reintegrated'], 'mblrc-card-reintegrated', 'fa-home', $asOf['reintegrated'],
                    $stats['registered'] ? $stats['reintegrated'] / $stats['registered'] * 100 : 0, '#7c3aed'],
            ];
        @endphp

        @foreach ($cards as [$label, $value, $variant, $icon, $date, $pct, $color])
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card mblrc-stat-card {{ $variant }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <span class="card-title-text">{{ $label }}</span>
                                <div class="fs-30">{{ number_format($value) }}</div>
                            </div>
                            <div class="mblrc-card-icon">
                                <i class="fa {{ $icon }}"></i>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center small text-muted">
                                <span style="font-size: 0.725rem;">As of {{ $date }}</span>
                                <span class="font-weight-bold" style="color: {{ $color }}; font-size: 0.725rem;">{{ round($pct) }}%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: {{ round($pct) }}%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Secondary Status Strip & Quick Actions --}}
    <div class="row mb-4">
        {{-- Not-Started Program --}}
        <div class="col-md-4 mb-3">
            <div class="card mblrc-stat-card mblrc-card-notstarted">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="card-title-text" style="color: #e11d48;">Not-Started Program</span>
                            <div class="fs-30">{{ number_format($stats['not_started']) }}</div>
                            <span class="small text-muted">Awaiting batch schedule</span>
                        </div>
                        <div class="mblrc-card-icon">
                            <i class="fa fa-pause-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- On-going Program --}}
        <div class="col-md-4 mb-3">
            <div class="card mblrc-stat-card mblrc-card-ongoing">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="card-title-text" style="color: #ea580c;">On-going Program</span>
                            <div class="fs-30">{{ number_format($stats['ongoing']) }}</div>
                            <span class="small text-muted">Active training &amp; modular classes</span>
                        </div>
                        <div class="mblrc-card-icon">
                            <i class="fa fa-play-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="col-md-4 mb-3">
            <div class="card mblrc-stat-card mblrc-card-actions">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="card-title-text" style="color: #312e81;">Quick Actions</span>
                            <div class="mt-2">
                                <a href="{{ route('mblrc.fr.index') }}" class="mblrc-quick-btn" style="text-decoration: none !important;">
                                    <i class="fa fa-eye"></i> View All
                                </a>
                            </div>
                        </div>
                        <div class="mblrc-card-icon">
                            <i class="fa fa-bolt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Analytics Charts (Constrained Height Containers) --}}
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="mblrc-modern-card h-100">
                <div class="mblrc-card-header-clean">
                    <div>
                        <h2><i class="mdi mdi-chart-timeline-variant" style="color: #4338ca;"></i> Program Status Analytics</h2>
                        <p>Reintegration progress, last 7 months</p>
                    </div>
                </div>
                <div class="p-3">
                    <div class="mblrc-chart-wrapper">
                        <canvas id="programChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="mblrc-modern-card h-100">
                <div class="mblrc-card-header-clean">
                    <div>
                        <h2><i class="mdi mdi-chart-areaspline" style="color: #2563eb;"></i> Overall Statistics</h2>
                        <p>Registered vs reintegrated, last 7 months</p>
                    </div>
                </div>
                <div class="p-3">
                    <div class="mblrc-chart-wrapper">
                        <canvas id="overallChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Location Map Card --}}
    <div class="row">
        <div class="col-12 mb-4">
            <div class="mblrc-modern-card">
                <div class="mblrc-card-header-clean">
                    <div>
                        <h2><i class="mdi mdi-map-marker-multiple" style="color: #312e81;"></i> Former Rebels Location Map</h2>
                        <p>Interactive geographic placement and cluster mapping</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div style="position: relative; min-width: 220px;">
                            <i class="mdi mdi-magnify" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1rem; pointer-events: none;"></i>
                            <input type="text" id="mapSearch" class="form-control form-control-sm" placeholder="Search rebel name..." style="padding-left: 32px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.8125rem; height: 36px;">
                        </div>
                        <select id="mapStatusFilter" class="form-select form-select-sm" style="border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.8125rem; font-weight: 600; color: #334155; min-width: 150px; height: 36px;">
                            <option value="">All Statuses</option>
                            <option value="Not-Started">Not-Started</option>
                            <option value="On-going">On-going</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="p-3">
                    <div id="frMap" data-locations="{{ route('mblrc.fr.locations') }}"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="mblrcData" data-analytics="{{ route('mblrc.analytics') }}" hidden></div>
</div>
@endsection
