@extends('layouts.skydash-v')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/MarkerCluster.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/MarkerCluster.Default.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/mblrc-dashboard.css') }}">
@endpush

@section('content')
    {{-- Welcome bar, ported from the legacy accounts/mblrc/index.php --}}
    <div class="row">
        <div class="col-md-12 grid-margin">
            <div class="row">
                <div class="col-12 col-xl-8 mb-4 mb-xl-0">
                    <h3 class="font-weight-bold">Welcome Mindanao Baptist Rural Learning Center</h3>
                    <h6 class="font-weight-normal mb-0">
                        All systems are running smoothly! You have
                        <span class="text-primary">{{ $stats['not_started'] }} not-started programs!</span>
                    </h6>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="justify-content-end d-flex">
                        <div class="dropdown flex-md-grow-1 flex-xl-grow-0">
                            <button class="btn btn-sm btn-light bg-white dropdown-toggle" type="button"
                                    id="dropdownMenuDate2" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="mdi mdi-calendar"></i> Today ({{ now()->format('d M Y') }})
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuDate2">
                                <a class="dropdown-item" href="#">January - March</a>
                                <a class="dropdown-item" href="#">March - June</a>
                                <a class="dropdown-item" href="#">June - August</a>
                                <a class="dropdown-item" href="#">August - November</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Headline figures. Gradients + progress bars are the legacy card design. --}}
    <div class="row mb-4">
        @php
            $cards = [
                ['Total Registered Former Rebels', $stats['registered'], 'mblrc-card-registered', 'fa-users', $asOf['registered'], 100],
                ['Total Enrolled in Program', $stats['active'], 'mblrc-card-enrolled', 'fa-graduation-cap', $asOf['enrolled'],
                    $stats['registered'] ? $stats['active'] / $stats['registered'] * 100 : 0],
                ['Total Completed 3-Month Program', $stats['completed'], 'mblrc-card-completed', 'fa-trophy', $asOf['completed'],
                    $stats['active'] ? $stats['completed'] / $stats['active'] * 100 : 0],
                ['Total Former Rebels Reintegrated', $stats['reintegrated'], 'mblrc-card-reintegrated', 'fa-home', $asOf['reintegrated'],
                    $stats['registered'] ? $stats['reintegrated'] / $stats['registered'] * 100 : 0],
            ];
        @endphp

        @foreach ($cards as [$label, $value, $variant, $icon, $date, $pct])
            <div class="col-md-3 stretch-card transparent">
                <div class="card mblrc-stat-card {{ $variant }}">
                    <div class="card-body position-relative">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="mb-3 opacity-90">{{ $label }}</p>
                                <p class="fs-30 mb-2 font-weight-bold">{{ $value }}</p>
                                <p class="mb-0 opacity-75"><small>As of {{ $date }}</small></p>
                            </div>
                            <div class="mblrc-card-icon">
                                <i class="fa {{ $icon }} fa-2x"></i>
                            </div>
                        </div>
                        <div class="progress mt-3">
                            <div class="progress-bar" style="width: {{ round($pct) }}%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Secondary row: programme status + quick actions --}}
    <div class="row mb-4">
        <div class="col-md-4 stretch-card transparent">
            <div class="card mblrc-stat-card mblrc-card-notstarted">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-2 opacity-90">Not-Started Program</p>
                        <p class="fs-30 mb-0 font-weight-bold">{{ $stats['not_started'] }}</p>
                    </div>
                    <i class="fa fa-pause-circle fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 stretch-card transparent">
            <div class="card mblrc-stat-card mblrc-card-ongoing">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-2 opacity-90">On-going Program</p>
                        <p class="fs-30 mb-0 font-weight-bold">{{ $stats['ongoing'] }}</p>
                    </div>
                    <i class="fa fa-play-circle fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 stretch-card transparent">
            <div class="card mblrc-stat-card mblrc-card-actions">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-2 opacity-90">Quick Actions</p>
                        <a href="{{ route('mblrc.fr.index') }}" class="btn btn-sm mblrc-quick-btn">
                            <i class="fa fa-eye"></i> View All
                        </a>
                    </div>
                    <i class="fa fa-bolt fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-0">Program Status Analytics</h4>
                    <p class="text-muted mb-3">Reintegration progress, last 7 months</p>
                    <canvas id="programChart" height="140"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-0">Overall Statistics</h4>
                    <p class="text-muted mb-3">Registered vs reintegrated, last 7 months</p>
                    <canvas id="overallChart" height="140"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Former Rebels Location Map</h4>
                    <div id="frMap" data-locations="{{ route('mblrc.fr.locations') }}"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="mblrcData" data-analytics="{{ route('mblrc.analytics') }}" hidden></div>
@endsection
