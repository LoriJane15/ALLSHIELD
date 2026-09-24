@extends('layouts.skydash-v')
@section('title', 'JAPIC Dashboard')
@section('heading', 'JAPIC Certification')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
<style>
    .japic-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1.15rem 1.35rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
    }
    .japic-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -4px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }
    .japic-kpi-card.card-total { border-top: 3px solid #312e81; }
    .japic-kpi-card.card-total .kpi-num { color: #1e1b4b; }
    .japic-kpi-card.card-total .kpi-icon { background: #eef2ff; color: #312e81; }

    .japic-kpi-card.card-duesoon { border-top: 3px solid #d97706; }
    .japic-kpi-card.card-duesoon .kpi-num { color: #78350f; }
    .japic-kpi-card.card-duesoon .kpi-icon { background: #fffbeb; color: #d97706; }

    .japic-kpi-card.card-overdue { border-top: 3px solid #e11d48; }
    .japic-kpi-card.card-overdue .kpi-num { color: #881337; }
    .japic-kpi-card.card-overdue .kpi-icon { background: #fff1f2; color: #e11d48; }

    .japic-kpi-card.card-completed { border-top: 3px solid #2563eb; }
    .japic-kpi-card.card-completed .kpi-num { color: #1e3a8a; }
    .japic-kpi-card.card-completed .kpi-icon { background: #eff6ff; color: #2563eb; }

    .japic-kpi-card.card-cancelled { border-top: 3px solid #64748b; }
    .japic-kpi-card.card-cancelled .kpi-num { color: #334155; }
    .japic-kpi-card.card-cancelled .kpi-icon { background: #f1f5f9; color: #64748b; }

    .kpi-title {
        font-size: 0.75rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .kpi-num {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1.1;
        margin: 0.25rem 0 0 0;
    }
    .kpi-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .japic-list-item {
        padding: 0.85rem 1rem;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        margin-bottom: 0.65rem;
        text-decoration: none !important;
        display: block;
    }
    .japic-list-item:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        transform: translateX(2px);
    }
    .sop-step-card {
        padding: 0.85rem 1rem;
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        transition: all 0.2s ease;
    }
    .sop-step-card:hover {
        border-color: #c7d2fe;
        background: #f8fafc;
    }
    .sop-step-num {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #eef2ff;
        color: #312e81;
        font-weight: 800;
        font-size: 0.8125rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .quick-filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.85rem;
        border-radius: 8px;
        font-size: 0.8125rem;
        font-weight: 700;
        text-decoration: none !important;
        transition: all 0.15s ease;
        border: 1px solid transparent;
    }
    .quick-filter-chip:hover {
        transform: translateY(-1px);
    }
</style>
@endpush

@section('content')
<div class="mblrc-dashboard-container">
    {{-- Modern Hero Banner --}}
    <div class="mblrc-hero mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="position: relative; z-index: 2; gap: 1rem;">
            <div class="d-flex align-items-center" style="gap: 1.25rem;">
                <div class="mblrc-hero-icon-box">
                    <i class="mdi mdi-certificate"></i>
                </div>
                <div>
                    <div class="mblrc-hero-eyebrow">
                        Joint AFP-PNP Intelligence Committee &middot; JAPIC Operations
                    </div>
                    <h1 class="mblrc-hero-title">Certification Dashboard</h1>
                    <p class="mblrc-hero-sub">Read-only overview of surfaced FR certification intake and verification workflows.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a class="btn" href="{{ route('japic.certifications.index') }}" style="background: #ffffff; color: #312e81; font-weight: 800; font-size: 0.875rem; padding: 0.6rem 1.35rem; border-radius: 10px; border: none; box-shadow: 0 4px 14px rgba(0,0,0,0.18); transition: all 0.2s ease;">
                    <i class="mdi mdi-format-list-bulleted mr-1"></i> View All Tasks
                </a>
            </div>
        </div>
    </div>

    @php
        $totalVal = (int) ($stats->total ?? 0);
        $dueSoonVal = (int) ($stats->due_soon ?? 0);
        $overdueVal = (int) ($stats->overdue ?? 0);
        $completedVal = (int) ($stats->completed ?? 0);
        $cancelledVal = (int) ($stats->cancelled ?? 0);

        $cards = [
            ['Total Tasks', $totalVal, 'mdi-layers-outline', 'card-total'],
            ['Due Soon', $dueSoonVal, 'mdi-clock-outline', 'card-duesoon'],
            ['Overdue', $overdueVal, 'mdi-alert-circle-outline', 'card-overdue'],
            ['Completed', $completedVal, 'mdi-check-circle-outline', 'card-completed'],
            ['Cancelled', $cancelledVal, 'mdi-close-circle-outline', 'card-cancelled'],
        ];

        $activePending = max(0, $totalVal - $completedVal - $cancelledVal);
        $onTimeRate = $totalVal > 0 ? round((($totalVal - $overdueVal) / $totalVal) * 100) : 100;
    @endphp

    {{-- 5 KPI Metric Cards --}}
    <div class="row g-3 mb-4">
        @foreach($cards as [$label, $count, $icon, $themeClass])
            <div class="col-6 col-md-4 col-xl mb-2">
                <div class="japic-kpi-card {{ $themeClass }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="kpi-title">{{ $label }}</span>
                        <div class="kpi-icon"><i class="mdi {{ $icon }}"></i></div>
                    </div>
                    <div class="kpi-num">{{ number_format($count) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Row 1: Status Distribution & Operational SLA Insights --}}
    <div class="row g-4 mb-4">
        {{-- Status Distribution --}}
        <div class="col-lg-7 mb-3 mb-lg-0">
            <section class="mblrc-form-card h-100" aria-labelledby="status-heading">
                <div class="mblrc-form-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #eef2ff; color: #312e81;">
                            <i class="mdi mdi-chart-donut"></i>
                        </div>
                        <div>
                            <h2 id="status-heading" class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Current status distribution</h2>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Active caseload breakdown across workflow stages</p>
                        </div>
                    </div>
                    <a href="{{ route('japic.certifications.index') }}" class="btn btn-sm" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #312e81; font-weight: 750; font-size: 0.775rem; border-radius: 8px;">
                        Manage <i class="mdi mdi-arrow-right"></i>
                    </a>
                </div>
                <div class="mblrc-form-card-body">
                    @if($statusCounts->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="mdi mdi-folder-outline font-size-24 d-block mb-2" style="font-size: 2.25rem; color: #94a3b8;"></i>
                            <p class="mb-0 font-weight-bold">No certification tasks are currently available.</p>
                        </div>
                    @else
                        @php($totalCount = max(1, $totalVal))
                        <div class="d-flex flex-column gap-2">
                            @foreach($statusCounts as $status => $count)
                                @php($pct = min(100, round(($count / $totalCount) * 100)))
                                <a href="{{ route('japic.certifications.index', ['status' => $status]) }}" class="japic-list-item">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="font-weight-bold" style="color: #1e293b; font-size: 0.9rem;">{{ $status }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge" style="background: #eef2ff; color: #312e81; font-weight: 800; font-size: 0.8125rem; border-radius: 6px; padding: 0.25rem 0.6rem;">
                                                <strong>{{ $count }}</strong>
                                            </span>
                                            <span class="text-muted small">({{ $pct }}%)</span>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 6px; background: #e2e8f0; border-radius: 9999px;">
                                        <div class="progress-bar" role="progressbar" style="width: {{ $pct }}%; background: #312e81;" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>

        {{-- SLA & Operational Performance Card --}}
        <div class="col-lg-5 mb-3 mb-lg-0">
            <section class="mblrc-form-card h-100" aria-labelledby="sla-heading">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #eff6ff; color: #2563eb;">
                            <i class="mdi mdi-timer-sand"></i>
                        </div>
                        <div>
                            <h2 id="sla-heading" class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Operational SLA Performance</h2>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Intake timeliness and workflow compliance</p>
                        </div>
                    </div>
                </div>
                <div class="mblrc-form-card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="font-weight-bold" style="color: #0f172a; font-size: 0.875rem;">Timeliness Compliance Rate</span>
                            <span class="badge" style="background: #eef2ff; color: #312e81; font-size: 0.875rem; font-weight: 800; border-radius: 6px;">
                                {{ $onTimeRate }}%
                            </span>
                        </div>
                        <div class="progress mb-4" style="height: 8px; background: #e2e8f0; border-radius: 9999px;">
                            <div class="progress-bar" role="progressbar" style="width: {{ $onTimeRate }}%; background: linear-gradient(90deg, #312e81 0%, #2563eb 100%);" aria-valuenow="{{ $onTimeRate }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem;">
                                    <small class="text-muted d-block font-weight-bold" style="font-size: 0.725rem; text-transform: uppercase;">Active Caseload</small>
                                    <div style="font-size: 1.25rem; font-weight: 800; color: #1e1b4b;">{{ $activePending }} <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">tasks</span></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem;">
                                    <small class="text-muted d-block font-weight-bold" style="font-size: 0.725rem; text-transform: uppercase;">Overdue Tasks</small>
                                    <div style="font-size: 1.25rem; font-weight: 800; color: {{ $overdueVal > 0 ? '#e11d48' : '#312e81' }};">{{ $overdueVal }} <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">cases</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="background: #f8fafc; border-left: 3px solid #312e81; border-radius: 6px; padding: 0.65rem 0.85rem; font-size: 0.8rem; color: #475569;">
                        <i class="mdi mdi-information-outline mr-1" style="color: #312e81;"></i>
                        Standard JAPIC turnaround timeline is <strong>14 days</strong> from verified CDR document intake.
                    </div>
                </div>
            </section>
        </div>
    </div>

    {{-- Row 2: Recent Notifications & Standard Protocol Guide --}}
    <div class="row g-4 mb-4">
        {{-- Notifications --}}
        <div class="col-lg-6 mb-3 mb-lg-0">
            <section class="mblrc-form-card h-100" aria-labelledby="notifications-heading">
                <div class="mblrc-form-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #fffbeb; color: #d97706;">
                            <i class="mdi mdi-bell-outline"></i>
                        </div>
                        <div>
                            <h2 id="notifications-heading" class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Recent notifications</h2>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Intake updates and certification milestones</p>
                        </div>
                    </div>
                    @if($notifications->isNotEmpty())
                        <span class="badge" style="background: #fffbeb; color: #d97706; font-weight: 800; font-size: 0.75rem; border-radius: 6px;">
                            {{ $notifications->count() }} alerts
                        </span>
                    @endif
                </div>
                <div class="mblrc-form-card-body">
                    @forelse($notifications as $notification)
                        <a class="japic-list-item" href="{{ route('japic.certifications.show', $notification->data['processing_id']) }}">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong style="color: #312e81; font-size: 0.9rem;">{{ $notification->data['fr_reference'] ?? 'Certification intake' }}</strong>
                                <span class="badge" style="background: #eff6ff; color: #2563eb; font-weight: 750; font-size: 0.725rem; border-radius: 6px;">Intake</span>
                            </div>
                            <span class="d-block small text-muted">
                                <i class="mdi mdi-calendar mr-1"></i> Received {{ $notification->data['received_date'] ?? '—' }} &middot; <i class="mdi mdi-clock-outline mr-1"></i> Due {{ $notification->data['due_date'] ?? '—' }}
                            </span>
                        </a>
                    @empty
                        <div class="text-center py-5 text-muted">
                            <i class="mdi mdi-bell-sleep-outline d-block mb-2" style="font-size: 2.25rem; color: #94a3b8;"></i>
                            <p class="mb-0 font-weight-bold">No intake notifications are available.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- JAPIC Certification SOP / Process Guide --}}
        <div class="col-lg-6 mb-3 mb-lg-0">
            <section class="mblrc-form-card h-100" aria-labelledby="sop-heading">
                <div class="mblrc-form-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mblrc-form-header-icon" style="background: #eef2ff; color: #312e81;">
                            <i class="mdi mdi-book-open-page-variant"></i>
                        </div>
                        <div>
                            <h2 id="sop-heading" class="mb-0" style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">Certification Protocol &amp; Standards</h2>
                            <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Standard 4-stage operational validation workflow</p>
                        </div>
                    </div>
                </div>
                <div class="mblrc-form-card-body">
                    <div class="sop-step-card">
                        <div class="sop-step-num">1</div>
                        <div>
                            <strong class="d-block" style="color: #0f172a; font-size: 0.875rem;">Intake &amp; Document Matching</strong>
                            <span class="text-muted small">Automatic intake triggered upon 39th IB CDR final document completion and verification.</span>
                        </div>
                    </div>
                    <div class="sop-step-card">
                        <div class="sop-step-num">2</div>
                        <div>
                            <strong class="d-block" style="color: #0f172a; font-size: 0.875rem;">Encrypted Narrative Drafting</strong>
                            <span class="text-muted small">Prepare certification control record and record dynamic attestation personnel.</span>
                        </div>
                    </div>
                    <div class="sop-step-card">
                        <div class="sop-step-num">3</div>
                        <div>
                            <strong class="d-block" style="color: #0f172a; font-size: 0.875rem;">Multi-Signatory Endorsement</strong>
                            <span class="text-muted small">Freeze draft version and submit for joint AFP-PNP committee clearance and signatures.</span>
                        </div>
                    </div>
                    <div class="sop-step-card">
                        <div class="sop-step-num">4</div>
                        <div>
                            <strong class="d-block" style="color: #0f172a; font-size: 0.875rem;">Final Signed Upload &amp; Seal</strong>
                            <span class="text-muted small">Upload official signed PDF with cryptographic SHA-256 validation to unlock PSWDO phase.</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    {{-- Row 3: Operational Quick Actions & Security Standards --}}
    <div class="row g-4">
        <div class="col-12">
            <div class="p-3" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);">
                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 1rem;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-muted font-weight-bold small text-uppercase" style="letter-spacing: 0.05em;">Quick Filter:</span>
                        <a href="{{ route('japic.certifications.index', ['status' => 'Pending']) }}" class="quick-filter-chip" style="background: #f8fafc; border-color: #cbd5e1; color: #334155;">
                            <i class="mdi mdi-clock-outline text-muted"></i> Pending Intake
                        </a>
                        <a href="{{ route('japic.certifications.index', ['status' => 'Drafting']) }}" class="quick-filter-chip" style="background: #eef2ff; border-color: #c7d2fe; color: #312e81;">
                            <i class="mdi mdi-pencil-outline" style="color: #312e81;"></i> Drafting
                        </a>
                        <a href="{{ route('japic.certifications.index', ['status' => 'For Signature']) }}" class="quick-filter-chip" style="background: #eff6ff; border-color: #bfdbfe; color: #2563eb;">
                            <i class="mdi mdi-draw" style="color: #2563eb;"></i> For Signature
                        </a>
                        <a href="{{ route('japic.certifications.index', ['status' => 'Completed']) }}" class="quick-filter-chip" style="background: #f1f5f9; border-color: #cbd5e1; color: #1e293b;">
                            <i class="mdi mdi-check-circle-outline" style="color: #312e81;"></i> Completed
                        </a>
                    </div>
                    <div class="d-flex align-items-center gap-2 text-muted small">
                        <i class="mdi mdi-shield-check" style="color: #312e81; font-size: 1.1rem;"></i>
                        <span>End-to-End Cryptographic Encryption (AES-256) &middot; Immutable Audit Logging</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

