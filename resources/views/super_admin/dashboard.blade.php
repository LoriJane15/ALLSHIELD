@extends('layouts.skydash-v')
@section('title', 'Super Admin Dashboard')
@section('heading', 'Super Admin')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-dashboard.css') }}">
<style>
/* Embedded fallback to ensure immediate styling rendering */
:root {
    --sa-primary: #4f46e5;
    --sa-primary-dark: #3730a3;
    --sa-primary-light: #eef2ff;
    --sa-royal: #2563eb;
    --sa-royal-light: #eff6ff;
    --sa-sky: #0284c7;
    --sa-sky-light: #f0f9ff;
    --sa-amber: #d97706;
    --sa-amber-light: #fffbeb;
    --sa-rose: #e11d48;
    --sa-rose-light: #fff1f2;
    --sa-slate-900: #0f172a;
    --sa-slate-800: #1e293b;
    --sa-slate-700: #334155;
    --sa-slate-600: #475569;
    --sa-slate-500: #64748b;
    --sa-slate-400: #94a3b8;
    --sa-slate-200: #e2e8f0;
    --sa-slate-100: #f1f5f9;
    --sa-slate-50: #f8fafc;
    --sa-card-radius: 18px;
    --sa-shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
    --sa-shadow-md: 0 4px 16px -2px rgba(15, 23, 42, 0.07), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
    --sa-shadow-lg: 0 12px 32px -4px rgba(15, 23, 42, 0.1), 0 4px 12px -2px rgba(15, 23, 42, 0.05);
}

.sa-dashboard-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: var(--sa-slate-800);
}

.sa-hero-banner {
    position: relative;
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #312e81 100%);
    border-radius: var(--sa-card-radius);
    padding: 2rem 2.25rem;
    color: #ffffff;
    box-shadow: var(--sa-shadow-lg);
    overflow: hidden;
    margin-bottom: 1.75rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.sa-hero-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, rgba(99, 102, 241, 0) 70%);
    pointer-events: none;
    border-radius: 50%;
}

.sa-hero-banner::after {
    content: '';
    position: absolute;
    bottom: -40%;
    left: 20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.2) 0%, rgba(37, 99, 235, 0) 70%);
    pointer-events: none;
    border-radius: 50%;
}

.sa-hero-content {
    position: relative;
    z-index: 2;
}

.sa-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.85rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: #e0e7ff;
    margin-bottom: 0.85rem;
}

.sa-hero-badge i {
    color: #fbbf24;
    font-size: 0.95rem;
}

.sa-hero-title {
    font-size: 1.75rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: #ffffff;
    margin-bottom: 0.4rem;
    line-height: 1.25;
}

.sa-hero-subtitle {
    font-size: 0.9rem;
    color: #cbd5e1;
    font-weight: 400;
    max-width: 680px;
    line-height: 1.5;
    margin-bottom: 1.25rem;
}

.sa-hero-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
}

.sa-hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.15rem;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.sa-hero-btn-primary {
    background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
}

.sa-hero-btn-primary:hover {
    background: linear-gradient(135deg, #4338ca 0%, #2563eb 100%);
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.45);
}

.sa-hero-btn-glass {
    background: rgba(255, 255, 255, 0.1);
    color: #f1f5f9 !important;
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
}

.sa-hero-btn-glass:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff !important;
    transform: translateY(-2px);
}

.sa-date-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(8px);
    padding: 0.5rem 1rem;
    border-radius: 12px;
    font-size: 0.82rem;
    color: #e2e8f0;
}

.sa-date-pill i {
    color: #93c5fd;
    font-size: 1rem;
}

.sa-pulse-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #38bdf8;
    box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7);
    animation: sa-pulse 2s infinite;
}

@keyframes sa-pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(56, 189, 248, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
}

.sa-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.75rem;
}

@media (max-width: 1199px) {
    .sa-stat-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 575px) {
    .sa-stat-grid {
        grid-template-columns: 1fr;
    }
}

.sa-stat-card {
    background: #ffffff;
    border-radius: var(--sa-card-radius);
    padding: 1.35rem 1.4rem;
    border: 1px solid var(--sa-slate-200);
    box-shadow: var(--sa-shadow-sm);
    position: relative;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.sa-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--sa-shadow-md);
    border-color: #cbd5e1;
}

.sa-stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.sa-stat-icon-wrap {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: #ffffff;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}

.sa-icon-primary {
    background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

.sa-icon-rose {
    background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25);
}

.sa-icon-amber {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
}

.sa-icon-royal {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

.sa-stat-badge {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 9999px;
    letter-spacing: 0.02em;
}

.sa-badge-primary { background: var(--sa-primary-light); color: var(--sa-primary); }
.sa-badge-rose { background: var(--sa-rose-light); color: var(--sa-rose); }
.sa-badge-amber { background: var(--sa-amber-light); color: var(--sa-amber); }
.sa-badge-royal { background: var(--sa-royal-light); color: var(--sa-royal); }

.sa-stat-title {
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sa-slate-500);
    margin-bottom: 0.35rem;
}

.sa-stat-number {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    color: var(--sa-slate-900);
    letter-spacing: -0.03em;
    margin-bottom: 0.35rem;
}

.sa-stat-subtitle {
    font-size: 0.8rem;
    color: var(--sa-slate-600);
    font-weight: 500;
}

.sa-stat-progress-bar {
    height: 5px;
    width: 100%;
    background-color: var(--sa-slate-100);
    border-radius: 9999px;
    overflow: hidden;
    margin-top: 0.85rem;
}

.sa-stat-progress-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.6s ease;
}

.sa-fill-primary { background: linear-gradient(90deg, #4f46e5, #3b82f6); }
.sa-fill-rose { background: linear-gradient(90deg, #f43f5e, #e11d48); }
.sa-fill-amber { background: linear-gradient(90deg, #f59e0b, #d97706); }
.sa-fill-royal { background: linear-gradient(90deg, #2563eb, #1d4ed8); }

.sa-stat-watermark {
    position: absolute;
    right: -10px;
    bottom: -15px;
    font-size: 5rem;
    color: rgba(15, 23, 42, 0.03);
    pointer-events: none;
    line-height: 1;
}

.sa-card {
    background: #ffffff;
    border-radius: var(--sa-card-radius);
    border: 1px solid var(--sa-slate-200);
    box-shadow: var(--sa-shadow-sm);
    margin-bottom: 1.75rem;
    overflow: hidden;
    transition: box-shadow 0.25s ease;
}

.sa-card:hover {
    box-shadow: var(--sa-shadow-md);
}

.sa-card-header {
    padding: 1.35rem 1.6rem;
    border-bottom: 1px solid var(--sa-slate-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    background: #ffffff;
}

.sa-card-header-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.sa-card-header-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--sa-royal-light);
    color: var(--sa-royal);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.sa-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--sa-slate-900);
    margin-bottom: 0.15rem;
    letter-spacing: -0.01em;
}

.sa-card-subtitle {
    font-size: 0.8rem;
    color: var(--sa-slate-500);
    margin-bottom: 0;
}

.sa-card-body {
    padding: 1.5rem 1.6rem;
}

.sa-chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.sa-chart-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    padding-top: 1.25rem;
    margin-top: 1.25rem;
    border-top: 1px solid var(--sa-slate-100);
}

.sa-chart-meta-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.sa-meta-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--sa-slate-100);
    color: var(--sa-slate-600);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.sa-meta-val {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--sa-slate-800);
    line-height: 1.1;
}

.sa-meta-lbl {
    font-size: 0.72rem;
    color: var(--sa-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.sa-activity-feed {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.sa-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    padding: 0.9rem 1rem;
    border-radius: 12px;
    background: var(--sa-slate-50);
    border: 1px solid var(--sa-slate-200);
    transition: all 0.2s ease;
}

.sa-activity-item:hover {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    transform: translateX(3px);
}

.sa-activity-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.sa-activity-info {
    flex-grow: 1;
    min-width: 0;
}

.sa-activity-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--sa-slate-800);
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sa-activity-meta {
    font-size: 0.76rem;
    color: var(--sa-slate-600);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0.35rem;
}

.sa-activity-time {
    font-size: 0.72rem;
    color: var(--sa-slate-400);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.sa-table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.sa-search-box {
    position: relative;
    width: 280px;
    max-width: 100%;
}

.sa-search-box input {
    width: 100%;
    padding: 0.55rem 1rem 0.55rem 2.4rem;
    font-size: 0.84rem;
    border: 1px solid var(--sa-slate-200);
    border-radius: 10px;
    background: #ffffff;
    color: var(--sa-slate-800);
    outline: none;
    transition: all 0.2s ease;
}

.sa-search-box input:focus {
    border-color: var(--sa-royal);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.sa-search-box i {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.05rem;
    color: var(--sa-slate-400);
}

.sa-filter-tabs {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--sa-slate-100);
    padding: 0.3rem;
    border-radius: 10px;
}

.sa-filter-tab {
    padding: 0.35rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 600;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--sa-slate-600);
    cursor: pointer;
    transition: all 0.2s ease;
}

.sa-filter-tab:hover {
    color: var(--sa-slate-900);
}

.sa-filter-tab.active {
    background: #ffffff;
    color: var(--sa-royal);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
}

.sa-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.sa-table thead th {
    background: var(--sa-slate-50);
    color: var(--sa-slate-500);
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 0.85rem 1rem;
    border-top: 1px solid var(--sa-slate-200);
    border-bottom: 1px solid var(--sa-slate-200);
}

.sa-table thead th:first-child {
    border-top-left-radius: 10px;
    border-bottom-left-radius: 10px;
}

.sa-table thead th:last-child {
    border-top-right-radius: 10px;
    border-bottom-right-radius: 10px;
}

.sa-table tbody tr td {
    padding: 1rem 1rem;
    font-size: 0.85rem;
    color: var(--sa-slate-700);
    border-bottom: 1px solid var(--sa-slate-100);
    vertical-align: middle;
    transition: background 0.15s ease;
}

.sa-table tbody tr:hover td {
    background-color: #f8fafc;
}

.sa-table tbody tr:last-child td {
    border-bottom: none;
}

.sa-doc-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.sa-doc-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--sa-royal-light);
    color: var(--sa-royal);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.sa-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: capitalize;
}

.sa-status-pill.approved {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.sa-status-pill.approved .sa-status-dot {
    background-color: #2563eb;
}

.sa-status-pill.submitted,
.sa-status-pill.updated {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.sa-status-pill.submitted .sa-status-dot,
.sa-status-pill.updated .sa-status-dot {
    background-color: #d97706;
}

.sa-status-pill.disapproved {
    background: #fff1f2;
    color: #be123c;
    border: 1px solid #fecdd3;
}

.sa-status-pill.disapproved .sa-status-dot {
    background-color: #e11d48;
}

.sa-status-pill.pending,
.sa-status-pill.draft {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.sa-status-pill.pending .sa-status-dot,
.sa-status-pill.draft .sa-status-dot {
    background-color: #64748b;
}

.sa-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.sa-empty-state {
    padding: 3rem 1.5rem;
    text-align: center;
}

.sa-empty-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--sa-slate-100);
    color: var(--sa-slate-400);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 1rem;
}

.sa-empty-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--sa-slate-700);
    margin-bottom: 0.35rem;
}

.sa-empty-subtitle {
    font-size: 0.82rem;
    color: var(--sa-slate-500);
    max-width: 360px;
    margin: 0 auto;
}
</style>
@endpush

@section('content')
<div class="sa-dashboard-wrapper">
    {{-- Executive Hero Banner --}}
    <div class="sa-hero-banner">
        <div class="sa-hero-content">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="sa-hero-badge">
                        <i class="mdi mdi-shield-check"></i>
                        <span>Super Administrator Command Center</span>
                    </div>
                    <h1 class="sa-hero-title">Good Day! {{ $userFullname }}</h1>
                    <p class="sa-hero-subtitle">
                        Strengthening Institutions and Empowering Localities Against Discrimination Programs for Former Rebels
                    </p>
                </div>
                <div>
                    <div class="sa-date-pill">
                        <span class="sa-pulse-dot"></span>
                        <i class="mdi mdi-calendar-range"></i>
                        <span>Today is <strong>{{ now()->format('d F Y') }}</strong></span>
                    </div>
                </div>
            </div>

            <div class="sa-hero-actions mt-3">
                <a href="{{ route('super_admin.users.index') }}" class="sa-hero-btn sa-hero-btn-glass">
                    <i class="mdi mdi-account-group"></i>
                    <span>User Management ({{ $systemUsersCount ?? 0 }})</span>
                </a>
                <a href="{{ route('super_admin.agencies.index') }}" class="sa-hero-btn sa-hero-btn-primary">
                    <i class="mdi mdi-bank"></i>
                    <span>Government Agencies ({{ $govAgenciesCount ?? 0 }})</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Top KPI Metric Cards (4 Cards) --}}
    @php
        $totalRcsp = $rcsp['total'] ?? 0;
        $notStartedPercent = $totalRcsp > 0 ? round(($rcsp['not_started'] / $totalRcsp) * 100) : 0;
        $ongoingPercent = $totalRcsp > 0 ? round(($rcsp['ongoing'] / $totalRcsp) * 100) : 0;
        $completedPercent = $totalRcsp > 0 ? round(($rcsp['completed'] / $totalRcsp) * 100) : 0;
    @endphp

    <div class="sa-stat-grid">
        {{-- Card 1: Total RCSP Barangay --}}
        <div class="sa-stat-card">
            <div>
                <div class="sa-stat-header">
                    <div class="sa-stat-icon-wrap sa-icon-primary">
                        <i class="mdi mdi-home-map-marker"></i>
                    </div>
                    <span class="sa-stat-badge sa-badge-primary">Monitored</span>
                </div>
                <div class="sa-stat-title">Total RCSP Barangay</div>
                <div class="sa-stat-number">{{ $totalRcsp }}</div>
                <div class="sa-stat-subtitle">Identified Priority Barangays</div>
            </div>
            <div>
                <div class="sa-stat-progress-bar">
                    <div class="sa-stat-progress-fill sa-fill-primary" style="width: 100%;"></div>
                </div>
            </div>
            <i class="mdi mdi-home-map-marker sa-stat-watermark"></i>
        </div>

        {{-- Card 2: Total Not Yet RCSP Barangay --}}
        <div class="sa-stat-card">
            <div>
                <div class="sa-stat-header">
                    <div class="sa-stat-icon-wrap sa-icon-rose">
                        <i class="mdi mdi-alert-circle-outline"></i>
                    </div>
                    <span class="sa-stat-badge sa-badge-rose">{{ $notStartedPercent }}% of total</span>
                </div>
                <div class="sa-stat-title">Not Yet Started</div>
                <div class="sa-stat-number" style="color: var(--sa-rose);">{{ $rcsp['not_started'] }}</div>
                <div class="sa-stat-subtitle">Pending Phase 0 Initialization</div>
            </div>
            <div>
                <div class="sa-stat-progress-bar">
                    <div class="sa-stat-progress-fill sa-fill-rose" style="width: {{ $notStartedPercent }}%;"></div>
                </div>
            </div>
            <i class="mdi mdi-alert-circle-outline sa-stat-watermark"></i>
        </div>

        {{-- Card 3: Total On-Going RCSP Barangay --}}
        <div class="sa-stat-card">
            <div>
                <div class="sa-stat-header">
                    <div class="sa-stat-icon-wrap sa-icon-amber">
                        <i class="mdi mdi-progress-clock"></i>
                    </div>
                    <span class="sa-stat-badge sa-badge-amber">{{ $ongoingPercent }}% of total</span>
                </div>
                <div class="sa-stat-title">On-Going Implementation</div>
                <div class="sa-stat-number" style="color: var(--sa-amber);">{{ $rcsp['ongoing'] }}</div>
                <div class="sa-stat-subtitle">Active Phase Execution</div>
            </div>
            <div>
                <div class="sa-stat-progress-bar">
                    <div class="sa-stat-progress-fill sa-fill-amber" style="width: {{ $ongoingPercent }}%;"></div>
                </div>
            </div>
            <i class="mdi mdi-progress-clock sa-stat-watermark"></i>
        </div>

        {{-- Card 4: Total Completed RCSP Barangay (Royal Blue, ZERO GREEN) --}}
        <div class="sa-stat-card">
            <div>
                <div class="sa-stat-header">
                    <div class="sa-stat-icon-wrap sa-icon-royal">
                        <i class="mdi mdi-check-circle-outline"></i>
                    </div>
                    <span class="sa-stat-badge sa-badge-royal">{{ $completedPercent }}% completed</span>
                </div>
                <div class="sa-stat-title">Completed RCSP</div>
                <div class="sa-stat-number" style="color: var(--sa-royal);">{{ $rcsp['completed'] }}</div>
                <div class="sa-stat-subtitle">Fully Cleared &amp; Sustained</div>
            </div>
            <div>
                <div class="sa-stat-progress-bar">
                    <div class="sa-stat-progress-fill sa-fill-royal" style="width: {{ $completedPercent }}%;"></div>
                </div>
            </div>
            <i class="mdi mdi-check-circle-outline sa-stat-watermark"></i>
        </div>
    </div>

    {{-- Main Row: RCSP Analytics & Notifications Feed --}}
    <div class="row">
        {{-- Left: RCSP Analytics (col-lg-8) --}}
        <div class="col-lg-8 col-12">
            <div class="sa-card">
                <div class="sa-card-header">
                    <div class="sa-card-header-left">
                        <div class="sa-card-header-icon">
                            <i class="mdi mdi-chart-bar"></i>
                        </div>
                        <div>
                            <h3 class="sa-card-title">RCSP Municipal Analytics</h3>
                            <p class="sa-card-subtitle">RCSP barangays identified per municipality across the region</p>
                        </div>
                    </div>
                    <div>
                        <span class="sa-stat-badge sa-badge-primary">
                            <i class="mdi mdi-city me-1"></i> {{ $totalMunicipalitiesCount ?? $rcspByMunicipality->count() }} Municipalities
                        </span>
                    </div>
                </div>
                <div class="sa-card-body">
                    <div class="sa-chart-container">
                        <canvas id="rcspBarChart"></canvas>
                    </div>

                    <div class="sa-chart-meta-row">
                        <div class="sa-chart-meta-item">
                            <div class="sa-meta-icon">
                                <i class="mdi mdi-bank"></i>
                            </div>
                            <div>
                                <div class="sa-meta-val">{{ $totalMunicipalitiesCount ?? $rcspByMunicipality->count() }}</div>
                                <div class="sa-meta-lbl">Covered Municipalities</div>
                            </div>
                        </div>

                        <div class="sa-chart-meta-item">
                            <div class="sa-meta-icon">
                                <i class="mdi mdi-home-map-marker"></i>
                            </div>
                            <div>
                                <div class="sa-meta-val">{{ $totalRcsp }}</div>
                                <div class="sa-meta-lbl">Total Priority Barangays</div>
                            </div>
                        </div>

                        <div class="sa-chart-meta-item">
                            <div class="sa-meta-icon">
                                <i class="mdi mdi-trending-up"></i>
                            </div>
                            <div>
                                <div class="sa-meta-val">
                                    {{ $rcspByMunicipality->count() > 0 ? round($totalRcsp / $rcspByMunicipality->count(), 1) : 0 }}
                                </div>
                                <div class="sa-meta-lbl">Avg. per Municipality</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Notifications & Activity Feed (col-lg-4) --}}
        <div class="col-lg-4 col-12">
            <div class="sa-card">
                <div class="sa-card-header">
                    <div class="sa-card-header-left">
                        <div class="sa-card-header-icon" style="background: var(--sa-primary-light); color: var(--sa-primary);">
                            <i class="mdi mdi-bell-ring-outline"></i>
                        </div>
                        <div>
                            <h3 class="sa-card-title">Recent Activity</h3>
                            <p class="sa-card-subtitle">Live document review updates</p>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-light text-muted font-weight-bold" style="font-size: 0.75rem;">
                        {{ $recentDocuments->count() }} updates
                    </span>
                </div>
                <div class="sa-card-body p-3">
                    <div class="sa-activity-feed">
                        @forelse ($recentDocuments->take(5) as $doc)
                            @php
                                $statusClass = match(strtolower($doc->status ?? '')) {
                                    'approved' => 'approved',
                                    'disapproved' => 'disapproved',
                                    'submitted', 'updated' => 'submitted',
                                    default => 'pending'
                                };
                                $iconClass = match($statusClass) {
                                    'approved' => 'sa-icon-royal',
                                    'disapproved' => 'sa-icon-rose',
                                    'submitted' => 'sa-icon-amber',
                                    default => 'sa-icon-primary'
                                };
                            @endphp
                            <div class="sa-activity-item">
                                <div class="sa-activity-icon-wrap {{ $iconClass }}">
                                    <i class="mdi mdi-file-document-outline"></i>
                                </div>
                                <div class="sa-activity-info">
                                    <div class="sa-activity-title">
                                        {{ $doc->rcspBarangay?->barangay?->name ?? 'RCSP Barangay' }}
                                    </div>
                                    <div class="sa-activity-meta">
                                        <span class="text-dark font-weight-bold">{{ $doc->phase?->name ?? 'Phase Submission' }}</span>
                                        <span>•</span>
                                        <span class="sa-status-pill {{ $statusClass }}" style="padding: 0.15rem 0.5rem; font-size: 0.7rem;">
                                            <span class="sa-status-dot"></span>
                                            {{ ucfirst($doc->status) }}
                                        </span>
                                    </div>
                                    <div class="sa-activity-time">
                                        <i class="mdi mdi-clock-outline"></i>
                                        <span>{{ $doc->created_at?->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="sa-empty-state py-4">
                                <div class="sa-empty-icon" style="width: 48px; height: 48px; font-size: 1.5rem;">
                                    <i class="mdi mdi-bell-outline"></i>
                                </div>
                                <div class="sa-empty-title" style="font-size: 0.9rem;">No Recent Activity</div>
                                <p class="sa-empty-subtitle" style="font-size: 0.78rem;">Submission updates will automatically appear here in real time.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Document Overview & Verification Log --}}
    <div class="row">
        <div class="col-12">
            <div class="sa-card">
                <div class="sa-card-header">
                    <div class="sa-card-header-left">
                        <div class="sa-card-header-icon">
                            <i class="mdi mdi-file-document-box-outline"></i>
                        </div>
                        <div>
                            <h3 class="sa-card-title">Document Overview &amp; Verification Log</h3>
                            <p class="sa-card-subtitle">Track and manage document submissions, phase progression, and verification status</p>
                        </div>
                    </div>
                </div>

                <div class="sa-card-body">
                    {{-- Table Toolbar --}}
                    <div class="sa-table-toolbar">
                        <div class="sa-search-box">
                            <i class="mdi mdi-magnify"></i>
                            <input type="text" id="documentTableSearch" placeholder="Search documents, barangays...">
                        </div>

                        <div class="sa-filter-tabs">
                            <button type="button" class="sa-filter-tab active" data-filter="all">All ({{ $recentDocuments->count() }})</button>
                            <button type="button" class="sa-filter-tab" data-filter="approved">Approved</button>
                            <button type="button" class="sa-filter-tab" data-filter="submitted">Pending / Submitted</button>
                            <button type="button" class="sa-filter-tab" data-filter="disapproved">Disapproved</button>
                        </div>
                    </div>

                    {{-- Table --}}
                    <div class="table-responsive">
                        <table class="sa-table" id="documentTable">
                            <thead>
                                <tr>
                                    <th>Document &amp; Phase</th>
                                    <th>RCSP Barangay</th>
                                    <th>Municipality</th>
                                    <th>Date &amp; Time</th>
                                    <th>Verification Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentDocuments as $doc)
                                    @php
                                        $statusClass = match(strtolower($doc->status ?? '')) {
                                            'approved' => 'approved',
                                            'disapproved' => 'disapproved',
                                            'submitted', 'updated' => 'submitted',
                                            default => 'pending'
                                        };
                                        $filterCategory = match($statusClass) {
                                            'approved' => 'approved',
                                            'disapproved' => 'disapproved',
                                            'submitted' => 'submitted',
                                            default => 'pending'
                                        };
                                    @endphp
                                    <tr data-status="{{ $filterCategory }}" data-search-text="{{ strtolower(($doc->phase?->name ?? 'Phase').' '.($doc->rcspBarangay?->barangay?->name ?? '').' '.($doc->rcspBarangay?->municipality?->name ?? '').' '.$doc->status) }}">
                                        <td>
                                            <div class="sa-doc-cell">
                                                <div class="sa-doc-icon">
                                                    <i class="mdi mdi-file-document-outline"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $doc->phase?->name ?? 'RCSP Phase' }} Submission</div>
                                                    <small class="text-muted">Document Attachment</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="mdi mdi-map-marker text-primary" style="font-size: 1.05rem;"></i>
                                                <span class="fw-semibold text-dark">{{ $doc->rcspBarangay?->barangay?->name ?? '—' }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="mdi mdi-city text-muted" style="font-size: 1rem;"></i>
                                                <span>{{ $doc->rcspBarangay?->municipality?->name ?? '—' }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 text-muted">
                                                <i class="mdi mdi-calendar-clock" style="font-size: 1rem;"></i>
                                                <span>{{ $doc->created_at?->format('d F Y, g:i A') ?? '—' }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="sa-status-pill {{ $statusClass }}">
                                                <span class="sa-status-dot"></span>
                                                <span>{{ ucfirst($doc->status) }}</span>
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="emptyTableInitial">
                                        <td colspan="5">
                                            <div class="sa-empty-state">
                                                <div class="sa-empty-icon">
                                                    <i class="mdi mdi-file-document-box-outline"></i>
                                                </div>
                                                <div class="sa-empty-title">No Document Records Found</div>
                                                <p class="sa-empty-subtitle">Document submissions, revisions, and status verifications will appear here.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                                <tr id="noFilterResultsRow" style="display: none;">
                                    <td colspan="5">
                                        <div class="sa-empty-state py-4">
                                            <div class="sa-empty-icon" style="width: 48px; height: 48px; font-size: 1.5rem;">
                                                <i class="mdi mdi-magnify"></i>
                                            </div>
                                            <div class="sa-empty-title" style="font-size: 0.95rem;">No Matching Documents</div>
                                            <p class="sa-empty-subtitle" style="font-size: 0.8rem;">Try adjusting your search keywords or filter tab selection.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    // --------------------------------------------------------------------------
    // 1. RCSP Municipal Analytics Chart
    // --------------------------------------------------------------------------
    const labels = @json($rcspByMunicipality->keys());
    const values = @json($rcspByMunicipality->values());
    const chartCanvas = document.getElementById('rcspBarChart');

    if (chartCanvas && labels.length > 0) {
        const ctx = chartCanvas.getContext('2d');
        
        // Gradient fill for bars
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, '#4f46e5');
        gradient.addColorStop(1, '#2563eb');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'RCSP Barangays',
                    data: values,
                    backgroundColor: gradient,
                    hoverBackgroundColor: '#3730a3',
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 56,
                    categoryPercentage: 0.5,
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.parsed.y + ' Identified RCSP Barangay(s)';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: {
                            color: '#64748b',
                            font: { family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto', size: 12, weight: '600' }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(226, 232, 240, 0.7)',
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            color: '#94a3b8',
                            font: { size: 11 }
                        }
                    }
                }
            }
        });
    }

    // --------------------------------------------------------------------------
    // 2. Interactive Document Table Filter & Search
    // --------------------------------------------------------------------------
    const searchInput = document.getElementById('documentTableSearch');
    const filterTabs = document.querySelectorAll('.sa-filter-tab');
    const tableRows = document.querySelectorAll('#documentTable tbody tr:not(#noFilterResultsRow):not(#emptyTableInitial)');
    const noResultsRow = document.getElementById('noFilterResultsRow');

    let currentFilter = 'all';
    let searchQuery = '';

    function applyFilter() {
        let visibleCount = 0;

        tableRows.forEach(row => {
            const rowStatus = row.getAttribute('data-status') || '';
            const rowText = row.getAttribute('data-search-text') || '';

            const matchesStatus = (currentFilter === 'all') || (rowStatus === currentFilter);
            const matchesSearch = !searchQuery || rowText.includes(searchQuery);

            if (matchesStatus && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (noResultsRow) {
            noResultsRow.style.display = (visibleCount === 0 && tableRows.length > 0) ? '' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function (e) {
            searchQuery = e.target.value.toLowerCase().trim();
            applyFilter();
        });
    }

    filterTabs.forEach(tab => {
        tab.addEventListener('click', function () {
            filterTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.getAttribute('data-filter') || 'all';
            applyFilter();
        });
    });
})();
</script>
@endpush
