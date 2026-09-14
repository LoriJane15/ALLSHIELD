@extends('layouts.skydash-v')
@section('title', '39th IB Dashboard')
@section('heading', '39th Infantry Battalion')

@php
    $totalMapped = max((int) ($stats['mapped'] ?? 0), 1);
    $recoveryCount = (int) ($statusCounts['Recovery'] ?? 0);
    $konsolidadoCount = (int) ($statusCounts['Konsolidado'] ?? 0);
    $rekonsilidaCount = (int) ($statusCounts['Rekonsilida'] ?? 0);
    $expansionCount = (int) ($statusCounts['Expansion'] ?? 0);
    $totalFRs = (int) ($stats['total_frs'] ?? 0);

    $areaCards = [
        [
            'title' => 'Konsolidado',
            'count' => $konsolidadoCount,
            'subtitle' => 'Organized NPA Influenced',
            'badge' => 'High Threat',
            'badge_cls' => 'badge-danger-soft',
            'card_cls' => 'card-theme-konsolidado',
            'icon' => 'mdi-flag-variant',
            'color' => '#dc2626',
            'pct' => round(($konsolidadoCount / $totalMapped) * 100),
            'threshold' => '20+ FRs',
        ],
        [
            'title' => 'Rekonsilida',
            'count' => $rekonsilidaCount,
            'subtitle' => 'Less Influenced Areas',
            'badge' => 'Monitored',
            'badge_cls' => 'badge-warning-soft',
            'card_cls' => 'card-theme-rekonsilida',
            'icon' => 'mdi-alert-circle',
            'color' => '#ea580c',
            'pct' => round(($rekonsilidaCount / $totalMapped) * 100),
            'threshold' => '15–19 FRs',
        ],
        [
            'title' => 'Expansion',
            'count' => $expansionCount,
            'subtitle' => 'Potential Threat Areas',
            'badge' => 'Watchlist',
            'badge_cls' => 'badge-amber-soft',
            'card_cls' => 'card-theme-expansion',
            'icon' => 'mdi-radar',
            'color' => '#ca8a04',
            'pct' => round(($expansionCount / $totalMapped) * 100),
            'threshold' => '10–14 FRs',
        ],
        [
            'title' => 'Recovery',
            'count' => $recoveryCount,
            'subtitle' => 'Cleared & Stabilized',
            'badge' => 'Cleared',
            'badge_cls' => 'badge-success-soft',
            'card_cls' => 'card-theme-recovery',
            'icon' => 'mdi-shield-check',
            'color' => '#16a34a',
            'pct' => round(($recoveryCount / $totalMapped) * 100),
            'threshold' => '<10 FRs',
        ],
    ];
@endphp

@push('styles')
<style>
    /* Container Constraint for Wide Monitors */
    .ib39-dashboard-container {
        max-width: 1560px;
        margin: 0 auto;
    }

    /* 1. Standard Unified Executive Military Header */
    .hero-banner {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%) !important;
        border-radius: 14px !important;
        padding: 1.25rem 1.75rem !important;
        color: #ffffff !important;
        margin-bottom: 1.25rem !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        box-shadow: 0 8px 24px -4px rgba(30, 27, 75, 0.25) !important;
        position: relative !important;
        overflow: hidden !important;
    }
    .hero-banner::after {
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
    .hero-main {
        display: flex;
        align-items: center;
        gap: 1.15rem;
        min-width: 0;
        position: relative;
        z-index: 1;
    }
    .hero-icon-box {
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
    .hero-eyebrow {
        color: #f59e0b;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        margin-bottom: 0.2rem;
    }
    .hero-title {
        font-size: 1.35rem !important;
        font-weight: 800 !important;
        color: #ffffff !important;
        letter-spacing: -0.01em !important;
        margin-bottom: 0.2rem !important;
        line-height: 1.25 !important;
    }
    .hero-subtitle {
        font-size: 0.85rem !important;
        color: #c7d2fe !important;
        margin-bottom: 0 !important;
        font-weight: 500 !important;
    }
    .hero-timestamp {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #a5b4fc;
        font-size: 0.72rem;
        font-weight: 600;
        margin-top: 0.35rem;
    }
    .hero-btn-primary {
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
    .hero-btn-primary i {
        color: #4338ca !important;
        font-size: 1.15rem !important;
    }
    .hero-btn-primary:hover {
        background: #f8fafc !important;
        color: #1e1b4b !important;
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2) !important;
    }
    .hero-btn-glass {
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
    .hero-btn-glass:hover {
        background: rgba(255, 255, 255, 0.25) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.6) !important;
        transform: translateY(-1px);
    }

    /* 2. Unified 5 KPI Metric Cards */
    .kpi-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        padding: 1.15rem 1.2rem;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .kpi-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3.5px;
    }

    /* Consistent KPI Themes */
    .card-theme-standout::before   { background: #4338ca; }
    .card-theme-konsolidado::before { background: #dc2626; }
    .card-theme-rekonsilida::before { background: #ea580c; }
    .card-theme-expansion::before   { background: #ca8a04; }
    .card-theme-recovery::before    { background: #16a34a; }

    .kpi-icon-wrapper {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .card-theme-standout .kpi-icon-wrapper    { background: #eef2ff; color: #4338ca; }
    .card-theme-konsolidado .kpi-icon-wrapper { background: #fef2f2; color: #dc2626; }
    .card-theme-rekonsilida .kpi-icon-wrapper { background: #fff7ed; color: #ea580c; }
    .card-theme-expansion .kpi-icon-wrapper   { background: #fefce8; color: #ca8a04; }
    .card-theme-recovery .kpi-icon-wrapper    { background: #f0fdf4; color: #16a34a; }

    /* Consistent KPI Numbers & Typography */
    .kpi-count {
        font-size: 2.25rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
        margin: 0.35rem 0 0.2rem 0;
        letter-spacing: -0.02em;
    }
    .kpi-title {
        font-size: 0.95rem;
        font-weight: 750;
        color: #1e293b;
        margin: 0;
    }
    .kpi-desc {
        font-size: 0.78rem;
        color: #64748b;
        font-weight: 500;
        margin: 0;
    }

    /* Standardized Soft Badges */
    .badge-soft {
        font-size: 0.72rem;
        font-weight: 750;
        padding: 0.25rem 0.6rem;
        border-radius: 8px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }
    .badge-primary-soft { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
    .badge-danger-soft  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .badge-warning-soft { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
    .badge-amber-soft   { background: #fefce8; color: #854d0e; border: 1px solid #fef08a; }
    .badge-success-soft { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .badge-neutral-soft { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    /* Progress Indicators */
    .progress-bar-custom {
        height: 6px;
        border-radius: 9999px;
        background: #f1f5f9;
        overflow: hidden;
        margin-top: 0.45rem;
    }
    .progress-fill {
        height: 100%;
        border-radius: 9999px;
        transition: width 0.6s ease;
    }

    /* 3. Universal Module / Title Card Styling */
    .dashboard-module {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        height: 100%;
    }
    .module-header {
        padding: 1.15rem 1.35rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .module-body {
        padding: 1.25rem 1.35rem;
    }
    .module-title {
        font-size: 1.05rem;
        font-weight: 750;
        color: #0f172a;
        margin-bottom: 0.15rem;
        line-height: 1.25;
    }
    .module-subtitle {
        font-size: 0.82rem;
        color: #64748b;
        margin-bottom: 0;
        font-weight: 500;
    }
    .btn-module-action {
        min-height: 34px;
        padding: 0.35rem 0.85rem;
        font-size: 0.78rem;
        font-weight: 700;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #334155;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none !important;
        transition: all 0.15s ease;
    }
    .btn-module-action:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #4338ca;
    }

    /* Action Center Internal Cards */
    .action-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.95rem 1.1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
        transition: all 0.18s ease;
        height: 100%;
    }
    .action-card:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
    }
    .action-card-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .btn-shield-action {
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #c7d2fe;
        font-size: 0.78rem;
        font-weight: 750;
        border-radius: 8px;
        min-height: 34px;
        padding: 0.35rem 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none !important;
        transition: all 0.15s ease;
    }
    .btn-shield-action:hover {
        background: #4338ca;
        color: #ffffff;
        border-color: #4338ca;
    }

    /* View Switcher Pills */
    .chart-view-pills {
        background: #f1f5f9;
        padding: 3px;
        border-radius: 8px;
        display: inline-flex;
        gap: 3px;
    }
    .chart-pill-btn {
        border: none;
        background: transparent;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0.35rem 0.8rem;
        border-radius: 6px;
        transition: all 0.15s ease;
        cursor: pointer;
    }
    .chart-pill-btn.active {
        background: #ffffff;
        color: #4338ca;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    .chart-container-box {
        position: relative;
        height: 290px;
        width: 100%;
        margin-top: 0.5rem;
    }

    /* Threat Status Breakdown Items */
    .status-breakdown-row {
        padding: 0.7rem 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .status-breakdown-row:last-child {
        border-bottom: none;
    }

    /* Quick Action Matrix */
    .quick-tile {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.8rem 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
        text-decoration: none !important;
        color: #0f172a;
        min-height: 56px;
        transition: all 0.15s ease;
    }
    .quick-tile:hover {
        background: #ffffff;
        border-color: #c7d2fe;
        color: #4338ca;
        box-shadow: 0 2px 8px rgba(67, 56, 202, 0.08);
    }
    .quick-tile-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #eef2ff;
        color: #4338ca;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .quick-tile:hover .quick-tile-icon {
        background: #4338ca;
        color: #ffffff;
    }

    /* Accessible High-Contrast Tables */
    .custom-table {
        margin: 0;
        width: 100%;
    }
    .custom-table th {
        font-size: 0.75rem;
        font-weight: 750;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #475569;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.75rem 1.15rem;
    }
    .custom-table td {
        padding: 0.85rem 1.15rem;
        vertical-align: middle;
        font-size: 0.88rem;
        color: #1e293b;
        border-bottom: 1px solid #f1f5f9;
    }
    .custom-table tr:hover td {
        background: #f8fafc;
    }
    .table-action-btn {
        min-height: 34px;
        min-width: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-weight: 600;
        color: #4338ca;
        border: 1px solid #c7d2fe;
        background: #ffffff;
        transition: all 0.15s ease;
    }
    .table-action-btn:hover {
        background: #4338ca;
        color: #ffffff;
        border-color: #4338ca;
    }
</style>
@endpush

@section('content')
<div class="content-wrapper p-0">
    <div class="ib39-dashboard-container">
        {{-- 1. Executive Military Header Banner --}}
        <header class="hero-banner">
            <div class="row align-items-center">
                <div class="col-lg-8 mb-3 mb-lg-0">
                    <div class="hero-main">
                        <div class="hero-icon-box">
                            <i class="mdi mdi-shield-account" aria-hidden="true"></i>
                        </div>
                        <div>
                            <div class="hero-eyebrow">Battalion Registry & Operations · 39th Infantry Battalion</div>
                            <h1 class="hero-title">39th Infantry Battalion (39th IB)</h1>
                            <p class="hero-subtitle">
                                Former Rebel Profiling & Conflict Area Monitoring
                            </p>
                            <div class="hero-timestamp">
                                <i class="mdi mdi-clock-outline"></i>
                                <span>Last updated: {{ now()->format('d M Y, h:i A') }} · Active Battalion Registry</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end hero-actions">
                    <div class="d-inline-flex flex-wrap gap-2">
                        <a href="{{ route('ib39.fr-profiles.create') }}" class="hero-btn-primary">
                            <i class="mdi mdi-account-plus"></i>
                            <span>Record Surfaced FR</span>
                        </a>
                        <a href="{{ route('ib39.map') }}" class="hero-btn-glass">
                            <i class="mdi mdi-map-marker-radius"></i>
                            <span>Area Map</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        {{-- 2. Unified 5 KPI Metric Cards --}}
        <section class="mb-3" aria-label="Key Performance Indicators">
            <div class="row g-3">
                {{-- Primary Metric: Total FRs (People KPI) --}}
                <div class="col-xl col-md-4 col-sm-6 col-12 mb-3 mb-xl-0">
                    <div class="kpi-card card-theme-standout">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge-soft badge-primary-soft">Primary Metric</span>
                                <div class="kpi-icon-wrapper">
                                    <i class="mdi mdi-account-group"></i>
                                </div>
                            </div>
                            <h2 class="kpi-title">Total FRs</h2>
                            <div class="kpi-count">{{ number_format($totalFRs) }}</div>
                            <p class="kpi-desc">Overall Profiled Rebels</p>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between" style="font-size: 0.75rem; font-weight: 600; color: #64748b;">
                                <span>Active Profiling Base</span>
                                <span class="text-primary font-weight-bold">100%</span>
                            </div>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: 100%; background-color: #4338ca;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4 Area Threat Classification Cards --}}
                @foreach ($areaCards as $c)
                    <div class="col-xl col-md-4 col-sm-6 col-12 mb-3 mb-xl-0">
                        <div class="kpi-card {{ $c['card_cls'] }}">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge-soft {{ $c['badge_cls'] }}">{{ $c['badge'] }}</span>
                                    <div class="kpi-icon-wrapper">
                                        <i class="mdi {{ $c['icon'] }}"></i>
                                    </div>
                                </div>
                                <h2 class="kpi-title">{{ $c['title'] }}</h2>
                                <div class="kpi-count">{{ number_format($c['count']) }}</div>
                                <p class="kpi-desc">{{ $c['subtitle'] }}</p>
                            </div>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between" style="font-size: 0.75rem; font-weight: 600; color: #64748b;">
                                    <span>Share of Areas</span>
                                    <span class="font-weight-bold" style="color: {{ $c['color'] }};">{{ $c['pct'] }}%</span>
                                </div>
                                <div class="progress-bar-custom">
                                    <div class="progress-fill" style="width: {{ $c['pct'] }}%; background-color: {{ $c['color'] }};"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 3. Operational Action Center (Unified Module Styling) --}}
        <section class="dashboard-module mb-3" aria-label="Pending Operations">
            <div class="module-header">
                <div>
                    <h2 class="module-title">Pending Operations</h2>
                    <p class="module-subtitle">Critical battalion action items requiring operational response</p>
                </div>
                <span class="badge badge-neutral-soft font-weight-bold">
                    Live Queue
                </span>
            </div>
            <div class="module-body">
                <div class="row g-3">
                    {{-- Action 1: CDR Debriefings --}}
                    <div class="col-lg-4 col-md-6 col-12">
                        <div class="action-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="action-card-icon" style="background: #eef2ff; color: #4338ca;">
                                    <i class="mdi mdi-file-document-edit"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="font-weight-bold text-dark" style="font-size: 0.92rem;">
                                            {{ number_format($needsAttention['pending_cdrs'] ?? 0) }} CDR Reports
                                        </span>
                                        <span class="badge badge-primary-soft py-0 px-1" style="font-size: 0.65rem;">Active</span>
                                    </div>
                                    <small class="text-muted font-weight-medium">Awaiting debriefing completion</small>
                                </div>
                            </div>
                            <a href="{{ route('ib39.fr-profiles.index') }}" class="btn-shield-action">
                                <span>Review</span> <i class="mdi mdi-chevron-right"></i>
                            </a>
                        </div>
                    </div>

                    {{-- Action 2: FEA Firearms Records --}}
                    <div class="col-lg-4 col-md-6 col-12">
                        <div class="action-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="action-card-icon" style="background: #fff7ed; color: #c2410c;">
                                    <i class="mdi mdi-file-document-box"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="font-weight-bold text-dark" style="font-size: 0.92rem;">
                                            {{ number_format($needsAttention['pending_feas'] ?? 0) }} FEA Records
                                        </span>
                                        <span class="badge badge-warning-soft py-0 px-1" style="font-size: 0.65rem;">Medium Priority</span>
                                    </div>
                                    <small class="text-muted font-weight-medium">Preliminary review in progress</small>
                                </div>
                            </div>
                            <a href="{{ route('ib39.fea.index') }}" class="btn-shield-action">
                                <span>Queue</span> <i class="mdi mdi-chevron-right"></i>
                            </a>
                        </div>
                    </div>

                    {{-- Action 3: Active Threat Conflict Areas --}}
                    <div class="col-lg-4 col-md-12 col-12">
                        <div class="action-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="action-card-icon" style="background: #fef2f2; color: #b91c1c;">
                                    <i class="mdi mdi-shield-alert"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="font-weight-bold text-dark" style="font-size: 0.92rem;">
                                            {{ number_format($stats['threat_areas'] ?? 0) }} Threat Areas
                                        </span>
                                        <span class="badge badge-danger-soft py-0 px-1" style="font-size: 0.65rem;">High Priority</span>
                                    </div>
                                    <small class="text-muted font-weight-medium">Monitored conflict sectors</small>
                                </div>
                            </div>
                            <a href="{{ route('ib39.areas.index') }}" class="btn-shield-action">
                                <span>Manage</span> <i class="mdi mdi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- 4. Analytics Hub & Threat Level Breakdown (Split 7 Col + 5 Col) --}}
        <div class="row g-3 mb-3">
            {{-- Left: Interactive Chart --}}
            <div class="col-lg-7 col-12 mb-3 mb-lg-0">
                <div class="dashboard-module d-flex flex-column justify-content-between">
                    <div>
                        <div class="module-header">
                            <div>
                                <h2 class="module-title">Threat & Area Distribution Analytics</h2>
                                <p class="module-subtitle">Classified barangays and former rebel concentration</p>
                            </div>
                            <div class="chart-view-pills">
                                <button type="button" class="chart-pill-btn active" data-chart-type="status">
                                    <i class="mdi mdi-chart-bar me-1"></i> Threat Status
                                </button>
                                <button type="button" class="chart-pill-btn" data-chart-type="municipality">
                                    <i class="mdi mdi-city me-1"></i> Municipality
                                </button>
                                <button type="button" class="chart-pill-btn" data-chart-type="donut">
                                    <i class="mdi mdi-chart-donut me-1"></i> Distribution
                                </button>
                            </div>
                        </div>

                        <div class="module-body pb-0">
                            <div class="chart-container-box">
                                <canvas id="ib39AnalyticsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    {{-- Mini Summary Footer inside Chart Box --}}
                    <div class="px-4 py-3 border-top bg-light text-center" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="text-muted font-weight-bold" style="font-size: 0.72rem; text-transform: uppercase;">Mapped Barangays</div>
                                <div class="h5 font-weight-bold text-dark mb-0 mt-1">{{ number_format($stats['mapped'] ?? 0) }}</div>
                            </div>
                            <div class="col-4 border-start border-end">
                                <div class="text-muted font-weight-bold" style="font-size: 0.72rem; text-transform: uppercase;">Active Threat Sectors</div>
                                <div class="h5 font-weight-bold text-danger mb-0 mt-1">{{ number_format($stats['threat_areas'] ?? 0) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted font-weight-bold" style="font-size: 0.72rem; text-transform: uppercase;">Cleared Recovery Rate</div>
                                <div class="h5 font-weight-bold text-success mb-0 mt-1">
                                    {{ round(($recoveryCount / $totalMapped) * 100) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: Status Breakdown & Operations Quick Hub --}}
            <div class="col-lg-5 col-12">
                <div class="dashboard-module d-flex flex-column justify-content-between">
                    <div>
                        <div class="module-header">
                            <div>
                                <h2 class="module-title">Threat Level Breakdown</h2>
                                <p class="module-subtitle">Area concentration & monitored sectors</p>
                            </div>
                            <a href="{{ route('ib39.areas.index') }}" class="btn-module-action">
                                <span>Manage Areas</span> <i class="mdi mdi-chevron-right"></i>
                            </a>
                        </div>

                        <div class="module-body py-2">
                            {{-- Breakdown Items with Count, Percentage, and Monitored Areas list --}}
                            @foreach ($areaCards as $card)
                                @php
                                    $cardPct = round(($card['count'] / $totalMapped) * 100);
                                    $statusBarangays = $threatBreakdown[$card['title']] ?? collect();
                                @endphp
                                <div class="status-breakdown-row">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="width: 9px; height: 9px; border-radius: 50%; background: {{ $card['color'] }}; display: inline-block;"></span>
                                            <span class="font-weight-bold text-dark" style="font-size: 0.88rem;">{{ $card['title'] }}</span>
                                            <small class="text-muted" style="font-size: 0.75rem;">({{ $card['threshold'] }})</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="font-weight-bold" style="color: {{ $card['color'] }}; font-size: 0.88rem;">
                                                {{ $card['count'] }} {{ Str::plural('Area', $card['count']) }} · {{ $cardPct }}%
                                            </span>
                                        </div>
                                    </div>
                                    <div class="progress-bar-custom">
                                        <div class="progress-fill" style="width: {{ $cardPct }}%; background: {{ $card['color'] }};"></div>
                                    </div>
                                    <div class="mt-1 text-truncate text-muted" style="font-size: 0.72rem;">
                                        @if ($statusBarangays->isNotEmpty())
                                            <span class="font-weight-medium text-dark">Monitored:</span>
                                            {{ $statusBarangays->take(3)->pluck('barangay')->implode(', ') }}
                                            @if ($statusBarangays->count() > 3)
                                                <span class="text-primary font-weight-bold">+{{ $statusBarangays->count() - 3 }} more</span>
                                            @endif
                                        @else
                                            <span class="text-muted font-italic">No barangays currently classified as {{ $card['title'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Operations Quick Launch Hub --}}
                    <div class="px-4 py-3 border-top bg-light" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                        <h3 class="font-weight-bold text-dark mb-2" style="font-size: 0.88rem;">Quick Action Matrix</h3>
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="{{ route('ib39.fr-profiles.create') }}" class="quick-tile">
                                    <div class="quick-tile-icon"><i class="mdi mdi-account-plus"></i></div>
                                    <div>
                                        <div class="font-weight-bold" style="font-size: 0.84rem;">Record FR</div>
                                        <small class="text-muted font-weight-medium">New profile</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="{{ route('ib39.fea.index') }}" class="quick-tile">
                                    <div class="quick-tile-icon"><i class="mdi mdi-file-document-box"></i></div>
                                    <div>
                                        <div class="font-weight-bold" style="font-size: 0.84rem;">FEA Processing</div>
                                        <small class="text-muted font-weight-medium">Firearms review</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="{{ route('ib39.map') }}" class="quick-tile">
                                    <div class="quick-tile-icon"><i class="mdi mdi-crosshairs-gps"></i></div>
                                    <div>
                                        <div class="font-weight-bold" style="font-size: 0.84rem;">Spatial Map</div>
                                        <small class="text-muted font-weight-medium">Choropleth map</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="{{ route('ib39.areas.index') }}" class="quick-tile">
                                    <div class="quick-tile-icon"><i class="mdi mdi-map-marker-plus"></i></div>
                                    <div>
                                        <div class="font-weight-bold" style="font-size: 0.84rem;">Area Management</div>
                                        <small class="text-muted font-weight-medium">RCSP counts</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 5. Priority Conflict Areas & Recent Surfaced Former Rebels --}}
        <div class="row g-3">
            {{-- Priority Monitored Barangays --}}
            <div class="col-lg-6 col-12 mb-3 mb-lg-0">
                <div class="dashboard-module overflow-hidden">
                    <div class="module-header bg-white">
                        <div>
                            <h2 class="module-title">Priority Monitored Barangays</h2>
                            <p class="module-subtitle">Areas with highest former rebel presence</p>
                        </div>
                        <a href="{{ route('ib39.areas.index') }}" class="btn-module-action">
                            <span>View All Areas</span> <i class="mdi mdi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table custom-table mb-0">
                            <thead>
                                <tr>
                                    <th>Barangay & Municipality</th>
                                    <th>FR Count</th>
                                    <th>Threat Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($priorityAreas as $area)
                                    @php
                                        $statusBadge = match($area->status) {
                                            'Konsolidado' => 'badge-danger-soft',
                                            'Rekonsilida' => 'badge-warning-soft',
                                            'Expansion' => 'badge-amber-soft',
                                            default => 'badge-success-soft',
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold text-dark">{{ $area->barangay }}</div>
                                            <small class="text-muted font-weight-medium">{{ $area->municipality }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark font-weight-bold px-2 py-1 border" style="border-radius: 6px;">
                                                {{ $area->frs }} FRs
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-soft {{ $statusBadge }}">{{ $area->status ?: 'Unclassified' }}</span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('ib39.areas.index', ['search' => $area->barangay]) }}" class="table-action-btn" title="View details">
                                                <i class="mdi mdi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="mdi mdi-map-marker-off d-block mb-1 fs-3"></i>
                                            No mapped areas found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Recently Recorded Former Rebels --}}
            <div class="col-lg-6 col-12">
                <div class="dashboard-module overflow-hidden">
                    <div class="module-header bg-white">
                        <div>
                            <h2 class="module-title">Recently Profiled Former Rebels</h2>
                            <p class="module-subtitle">Latest surfaced rebels recorded in the system</p>
                        </div>
                        <a href="{{ route('ib39.fr-profiles.index') }}" class="btn-module-action">
                            <span>View All FRs</span> <i class="mdi mdi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table custom-table mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Surfaced Date</th>
                                    <th>Firearms</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentSurfaced as $fr)
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold text-dark">{{ $fr->displayName }}</div>
                                            <small class="text-muted font-weight-medium">{{ $fr->category?->value ?? 'Former Rebel' }}</small>
                                        </td>
                                        <td>
                                            <div class="text-dark">{{ $fr->municipality?->name ?? $fr->province }}</div>
                                            <small class="text-muted">{{ $fr->barangay?->name ?? '—' }}</small>
                                        </td>
                                        <td>
                                            {{ $fr->surfaced_at ? $fr->surfaced_at->format('M d, Y') : '—' }}
                                        </td>
                                        <td>
                                            @if ($fr->possessed_firearms)
                                                <span class="badge badge-danger-soft px-2 py-1 font-weight-bold">
                                                    <i class="mdi mdi-pistol me-1"></i> Yes
                                                </span>
                                            @else
                                                <span class="badge bg-light text-muted px-2 py-1 border font-weight-medium" style="border-radius: 6px;">
                                                    No
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('ib39.fr-profiles.show', $fr) }}" class="table-action-btn" title="View FR Profile">
                                                <i class="mdi mdi-account-card-details"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="mdi mdi-account-off d-block mb-1 fs-3"></i>
                                            No recent surfaced former rebels recorded.
                                            <div class="mt-2">
                                                <a href="{{ route('ib39.fr-profiles.create') }}" class="btn btn-sm btn-primary" style="border-radius: 8px;">
                                                    <i class="mdi mdi-plus"></i> Record Surfaced FR
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Success Modal for Record Surfaced FR --}}
@php
    $modalData = session('surfaced_fr_modal');
@endphp
@if ($modalData || (session('success') && str_contains(session('success'), 'was recorded successfully')))
    <div class="modal fade" id="recordSuccessModal" tabindex="-1" aria-labelledby="recordSuccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 mx-auto d-flex align-items-center justify-content-center"
                         style="width: 68px; height: 68px; border-radius: 50%; background: #ecfdf5; color: #10b981; font-size: 2.2rem; box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.1);">
                        <i class="mdi mdi-check-circle-outline"></i>
                    </div>
                    <h3 class="h4 font-weight-bold text-dark mb-1" id="recordSuccessModalLabel">Surfaced FR Recorded!</h3>
                    <p class="text-muted small mb-3">
                        The former rebel profile has been successfully registered and assigned a unique reference code.
                    </p>

                    @if ($modalData)
                        <div class="p-3 mb-3 text-start" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <span class="text-muted small font-weight-bold text-uppercase">Reference Code</span>
                                <span class="badge font-monospace" style="background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; font-size: 0.9rem; padding: 0.35rem 0.65rem; border-radius: 8px;">
                                    {{ $modalData['reference_number'] }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small">Name</span>
                                <span class="font-weight-bold text-dark small">{{ $modalData['name'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small">Category</span>
                                <span class="text-dark small font-weight-medium">{{ $modalData['category'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small">Area</span>
                                <span class="text-dark small text-truncate" style="max-width: 220px;">{{ $modalData['area'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Firearms</span>
                                <div>
                                    @if ($modalData['firearms'])
                                        <span class="badge bg-danger text-white small px-2 py-0" style="border-radius: 6px;">Yes</span>
                                    @else
                                        <span class="badge bg-secondary text-white small px-2 py-0" style="border-radius: 6px;">No</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="d-flex gap-2 justify-content-center">
                        @if ($modalData && !empty($modalData['id']))
                            <a href="{{ route('ib39.fr-profiles.show', $modalData['id']) }}" class="btn btn-primary font-weight-bold px-3" style="border-radius: 8px;">
                                <i class="mdi mdi-eye me-1"></i> View Profile
                            </a>
                        @endif
                        <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal" style="border-radius: 8px;">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Data Bridge for Chart.js --}}
<div id="ib39Data"
     data-status='@json($statusCounts)'
     data-muni-labels='@json($perMunicipality->pluck('municipality'))'
     data-muni-values='@json($perMunicipality->pluck('frs'))'
     style="display:none;"></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show success modal if session flag present
    const modalEl = document.getElementById('recordSuccessModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    const el = document.getElementById('ib39Data');
    if (!el) return;

    const status = JSON.parse(el.dataset.status || '{}');
    const muniLabels = JSON.parse(el.dataset.muniLabels || '[]');
    const muniValues = JSON.parse(el.dataset.muniValues || '[]');
    const statusLabels = ['Konsolidado', 'Rekonsilida', 'Expansion', 'Recovery'];
    const statusColors = ['#dc2626', '#ea580c', '#ca8a04', '#16a34a'];
    const statusHoverColors = ['#b91c1c', '#c2410c', '#a16207', '#15803d'];

    const ctx = document.getElementById('ib39AnalyticsChart');
    if (!ctx) return;

    let currentChart = null;

    function renderChart(type) {
        if (currentChart) {
            currentChart.destroy();
        }

        const chartCtx = ctx.getContext('2d');

        if (type === 'status') {
            const dataVals = statusLabels.map(l => status[l] || 0);

            currentChart = new Chart(chartCtx, {
                type: 'horizontalBar',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        label: 'Barangays',
                        data: dataVals,
                        backgroundColor: statusColors,
                        hoverBackgroundColor: statusHoverColors,
                        barThickness: 24,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    tooltips: {
                        backgroundColor: '#0f172a',
                        titleFontColor: '#ffffff',
                        bodyFontColor: '#cbd5e1',
                        xPadding: 12,
                        yPadding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(item, data) {
                                const val = item.xLabel;
                                return val === 0 ? ' 0 Areas (No active presence)' : ` ${data.datasets[item.datasetIndex].label}: ${val} Area(s)`;
                            }
                        }
                    },
                    scales: {
                        xAxes: [{
                            ticks: {
                                beginAtZero: true,
                                precision: 0,
                                stepSize: 1,
                                fontColor: '#475569',
                                fontSize: 12,
                            },
                            gridLines: {
                                color: 'rgba(226, 232, 240, 0.6)',
                                zeroLineColor: '#cbd5e1',
                            }
                        }],
                        yAxes: [{
                            ticks: {
                                fontColor: '#0f172a',
                                fontStyle: 'bold',
                                fontSize: 13,
                            },
                            gridLines: {
                                display: false,
                            }
                        }]
                    }
                }
            });
        } else if (type === 'municipality') {
            currentChart = new Chart(chartCtx, {
                type: 'bar',
                data: {
                    labels: muniLabels.length > 0 ? muniLabels : ['No Data'],
                    datasets: [{
                        label: 'Former Rebels',
                        data: muniValues.length > 0 ? muniValues : [0],
                        backgroundColor: '#4338ca',
                        hoverBackgroundColor: '#312e81',
                        maxBarThickness: 40,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    tooltips: {
                        backgroundColor: '#0f172a',
                        titleFontColor: '#ffffff',
                        bodyFontColor: '#cbd5e1',
                        xPadding: 12,
                        yPadding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(item, data) {
                                return ` Total FRs: ${item.yLabel}`;
                            }
                        }
                    },
                    scales: {
                        xAxes: [{
                            ticks: {
                                fontColor: '#475569',
                                fontStyle: 'bold',
                                fontSize: 12,
                            },
                            gridLines: { display: false }
                        }],
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                precision: 0,
                                fontColor: '#475569',
                                fontSize: 12,
                            },
                            gridLines: {
                                color: 'rgba(226, 232, 240, 0.6)',
                                zeroLineColor: '#cbd5e1',
                            }
                        }]
                    }
                }
            });
        } else if (type === 'donut') {
            currentChart = new Chart(chartCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusLabels.map(l => status[l] || 0),
                        backgroundColor: statusColors,
                        hoverBackgroundColor: statusHoverColors,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'right',
                        labels: {
                            fontColor: '#1e293b',
                            fontSize: 12,
                            fontStyle: 'bold',
                            padding: 12,
                            usePointStyle: true,
                        }
                    },
                    tooltips: {
                        backgroundColor: '#0f172a',
                        titleFontColor: '#ffffff',
                        bodyFontColor: '#cbd5e1',
                        xPadding: 12,
                        yPadding: 10,
                        cornerRadius: 8,
                    },
                    cutoutPercentage: 68,
                }
            });
        }
    }

    // Initialize Default Chart
    renderChart('status');

    // Chart Switcher Tab Handling
    const pillButtons = document.querySelectorAll('.chart-pill-btn');
    pillButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            pillButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const type = this.getAttribute('data-chart-type');
            renderChart(type);
        });
    });
});
</script>
@endpush
