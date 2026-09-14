@extends('layouts.skydash-h')
@section('title', 'Clusters')
@section('heading', 'SHIELD Clusters')

@php
    $clustersConfig = config('shield_clusters') ?? [];

    // 12 Clusters in orbital sequence (--i 0..11)
    $wheel = [
        'basic-services' => [
            'name'     => 'Basic Services',
            'fullName' => 'Basic Services Cluster',
            'img'      => 'basic services.png',
            'desc'     => 'Ensures delivery of fundamental health, education, utilities, and social welfare services to beneficiary communities.',
        ],
        'livelihood' => [
            'name'     => 'Livelihood & Employment',
            'fullName' => 'Poverty Reduction, Livelihood and Employment Cluster',
            'img'      => 'livelihood.png',
            'desc'     => 'Provides sustainable livelihood assistance, entrepreneurial training, and employment facilitation for former rebels.',
        ],
        'comprehensive' => [
            'name'     => 'E-CLIP & Amnesty',
            'fullName' => 'E-CLIP and Amnesty Program Cluster',
            'img'      => 'comprehensive.png',
            'desc'     => 'Oversees reintegration, financial packages, housing, and official amnesty facilitation across government agencies.',
        ],
        'cooperation' => [
            'name'     => 'Legal Cooperation',
            'fullName' => 'Legal Cooperation Cluster',
            'img'      => 'cooperation.png',
            'desc'     => 'Handles legal assistance, court clearances, and judicial coordination for surfaced beneficiaries.',
        ],
        'empowerment' => [
            'name'     => 'LGU Empowerment',
            'fullName' => 'Local Government Empowerment Cluster',
            'img'      => 'empowerment.png',
            'desc'     => 'Strengthens municipal and provincial local government units in grassroots peace, governance, and development.',
        ],
        'enforcement' => [
            'name'     => 'Peace & Enforcement',
            'fullName' => 'Peace, Law Enforcement, and Development Support Cluster',
            'img'      => 'enforcement.png',
            'desc'     => 'Maintains territorial stability, rule of law, and supportive law enforcement in cleared priority areas.',
        ],
        'infrastructure' => [
            'name'     => 'Infrastructure & Resource',
            'fullName' => 'Infrastructure & Resource Management Cluster',
            'img'      => 'infrastructure.png',
            'desc'     => 'Implements farm-to-market roads, bridges, rural electrification, and critical water systems.',
        ],
        'international' => [
            'name'     => 'International Engagement',
            'fullName' => 'International Engagement Cluster',
            'img'      => 'international.png',
            'desc'     => 'Coordinates diplomatic outreach, overseas community engagement, and international peace alignment.',
        ],
        'local-peace' => [
            'name'     => 'Local Peace Engagement',
            'fullName' => 'Localized Peace Engagement Cluster',
            'img'      => 'local peace.png',
            'desc'     => 'Facilitates community dialogues, traditional leader mediation, and local peace council initiatives.',
        ],
        'sectoral' => [
            'name'     => 'Sectoral Unification',
            'fullName' => 'Sectoral Unification, Capacity-Building, Empowerment, and Mobilization Cluster',
            'img'      => 'sectoral.png',
            'desc'     => 'Mobilizes youth, indigenous peoples, labor, women, and civil society sectors toward lasting unity.',
        ],
        'situational' => [
            'name'     => 'Situational Awareness',
            'fullName' => 'Situational Awareness and Knowledge Management Cluster',
            'img'      => 'situational.png',
            'desc'     => 'Consolidates real-time field data, research analytics, and operational intelligence for decision-makers.',
        ],
        'strategic' => [
            'name'     => 'Strategic Communication',
            'fullName' => 'Strategic Communication Cluster',
            'img'      => 'strategic.png',
            'desc'     => 'Disseminates verified information, public advocacy campaigns, and peace narratives nationwide.',
        ],
    ];
@endphp

@section('content')
<div class="clusters-overview-wrapper">
    {{-- Top Metric Summary Cards --}}
    <div class="row mb-4">
        @php
            $metrics = [
                ['label' => 'Total IMPLANs', 'val' => $rollup['total'], 'icon' => 'mdi-file-document-box-multiple', 'class' => 'stat-primary', 'iconClass' => 'icon-primary'],
                ['label' => 'Verified IMPLANs', 'val' => $rollup['verified'], 'icon' => 'mdi-shield-check', 'class' => 'stat-indigo', 'iconClass' => 'icon-indigo'],
                ['label' => 'Ongoing Execution', 'val' => $rollup['ongoing'], 'icon' => 'mdi-progress-clock', 'class' => 'stat-sky', 'iconClass' => 'icon-sky'],
                ['label' => 'For Verification', 'val' => $rollup['for_verification'], 'icon' => 'mdi-alert-circle-outline', 'class' => 'stat-amber', 'iconClass' => 'icon-amber'],
            ];
        @endphp

        @foreach ($metrics as $m)
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
                <div class="cluster-stat-card {{ $m['class'] }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">{{ number_format($m['val']) }}</div>
                            <div class="stat-label">{{ $m['label'] }}</div>
                        </div>
                        <div class="stat-icon-wrap {{ $m['iconClass'] }}">
                            <i class="mdi {{ $m['icon'] }}"></i>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Executive Header --}}
    <div class="clusters-hero-header">
        <div class="cluster-eyebrow-badge">
            <i class="mdi mdi-view-dashboard-outline"></i> Whole-of-Nation Approach
        </div>
        <h1 class="cluster-hero-title">
            12 Clusters of <span class="highlight-gold">Katuparan Center</span>
        </h1>
        <p class="cluster-hero-subtitle">
            The Katuparan Center harmonizes 12 dedicated inter-agency clusters, orchestrating national and local government agencies into a unified front for peace, community development, and sustainable reintegration.
        </p>

        {{-- Section Quick Navigation --}}
        <div class="view-mode-nav">
            <a href="#orbitalView" class="view-mode-btn active" id="btnOrbitalView">
                <i class="mdi mdi-radar"></i> Orbital Ecosystem View
            </a>
            <a href="#directoryView" class="view-mode-btn" id="btnDirectoryView">
                <i class="mdi mdi-view-grid"></i> Cluster Directory (12)
            </a>
        </div>
    </div>

    {{-- Interactive Orbital Wheel Canvas --}}
    <div class="orbital-stage-card" id="orbitalView">
        <div class="orbital-stage-bg" style="background-image: url('{{ asset('assets/img/circlebg.png') }}');"></div>
        <div class="orbital-stage-overlay"></div>

        {{-- Futuristic Orbital Guide Rings --}}
        <div class="orbital-radar-ring ring-outer"></div>
        <div class="orbital-radar-ring ring-track"></div>
        <div class="orbital-radar-ring ring-inner"></div>

        {{-- Central Hub --}}
        <div class="central-hub" title="Katuparan Center">
            <img src="{{ asset('assets/img/SHEILD.png') }}" alt="Katuparan Center Logo">
        </div>

        {{-- 12 Orbital Cluster Nodes --}}
        <div class="orbit-nodes-container">
            @foreach ($wheel as $slug => $data)
                @php
                    $agencyCount = isset($clustersConfig[$slug]['agencies']) ? count($clustersConfig[$slug]['agencies']) : 0;
                @endphp
                <div class="orbit-node" style="--i: {{ $loop->index }};" title="{{ $data['fullName'] }}">
                    <a href="{{ route('admin.clusters.show', $slug) }}">
                        <img src="{{ asset('assets/img/cluster/'.$data['img']) }}" alt="{{ $data['name'] }}">
                        <div class="orbit-node-tooltip">
                            {{ $data['name'] }}
                            @if ($agencyCount > 0)
                                &bull; {{ $agencyCount }} Agencies
                            @endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Cluster Directory Section --}}
    <div class="cluster-directory-section" id="directoryView">
        <div class="directory-header-bar">
            <div>
                <h3 class="directory-title">
                    <i class="mdi mdi-domain" style="color: #2563eb;"></i>
                    Directory of Clusters &amp; Inter-Agency Mandates
                </h3>
                <p class="text-muted small mb-0">Browse cluster objectives, member agencies, and dedicated implementation plans.</p>
            </div>
            <div class="directory-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="text" id="clusterFilterInput" placeholder="Filter clusters by name or keywords..." autocomplete="off">
            </div>
        </div>

        <div class="row" id="clusterGridContainer">
            @foreach ($wheel as $slug => $data)
                @php
                    $agencies = $clustersConfig[$slug]['agencies'] ?? [];
                    $agencyCount = count($agencies);
                @endphp
                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-4 cluster-grid-item" data-cluster-name="{{ strtolower($data['fullName'] . ' ' . $data['name'] . ' ' . $data['desc']) }}">
                    <a href="{{ route('admin.clusters.show', $slug) }}" class="cluster-directory-card">
                        <div class="cluster-card-top">
                            <div class="cluster-card-emblem">
                                <img src="{{ asset('assets/img/cluster/'.$data['img']) }}" alt="{{ $data['name'] }}">
                            </div>
                            <div class="cluster-card-title-wrap">
                                <h4 class="cluster-card-name">{{ $data['name'] }}</h4>
                                <span class="cluster-agency-pill">
                                    <i class="mdi mdi-bank"></i> {{ $agencyCount }} Member {{ Str::plural('Agency', $agencyCount) }}
                                </span>
                            </div>
                        </div>
                        <p class="cluster-card-desc">
                            {{ $data['desc'] }}
                        </p>
                        <div class="cluster-card-footer">
                            <span>Explore Cluster Profile</span>
                            <i class="mdi mdi-arrow-right"></i>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/clusters-overview.css') }}">
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Quick interactive search filter for cluster cards
        const filterInput = document.getElementById('clusterFilterInput');
        const clusterItems = document.querySelectorAll('.cluster-grid-item');

        if (filterInput) {
            filterInput.addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase().trim();
                clusterItems.forEach(function(item) {
                    const text = item.getAttribute('data-cluster-name') || '';
                    if (!term || text.includes(term)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }

        // Smooth scroll and active button toggle
        const btnOrbital = document.getElementById('btnOrbitalView');
        const btnDirectory = document.getElementById('btnDirectoryView');

        if (btnOrbital && btnDirectory) {
            btnOrbital.addEventListener('click', function(e) {
                btnOrbital.classList.add('active');
                btnDirectory.classList.remove('active');
            });

            btnDirectory.addEventListener('click', function(e) {
                btnDirectory.classList.add('active');
                btnOrbital.classList.remove('active');
            });
        }
    });
</script>
@endpush
