@extends('layouts.skydash-h')
@section('title', 'Clusters')
@section('heading', 'SHIELD Clusters')

@php
    // Radial order matches the original Katuparan Center wheel (--i 0..11).
    $wheel = [
        'Basic Services'  => 'basic services.png',
        'Livelihood'      => 'livelihood.png',
        'Comprehensive'   => 'comprehensive.png',
        'Cooperation'     => 'cooperation.png',
        'Empowerment'     => 'empowerment.png',
        'Enforcement'     => 'enforcement.png',
        'Infrastructure'  => 'infrastructure.png',
        'International'    => 'international.png',
        'Local Peace'     => 'local peace.png',
        'Sectoral'        => 'sectoral.png',
        'Situational'     => 'situational.png',
        'Strategic'       => 'strategic.png',
    ];
@endphp

@section('content')
    <div class="row">
        @foreach ([
            ['Total IMPLANs', $rollup['total'], 'text-dark'],
            ['Verified', $rollup['verified'], 'text-success'],
            ['Ongoing', $rollup['ongoing'], 'text-primary'],
            ['For Verification', $rollup['for_verification'], 'text-warning'],
        ] as [$label, $value, $tone])
            <div class="col-sm-6 col-lg-3 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <p class="h3 mb-1 {{ $tone }}">{{ $value }}</p>
                        <p class="text-muted small mb-0">{{ $label }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="cluster-header text-center">
        <h2 class="display-4"><strong>12 Clusters of <span class="text-orange">Katuparan Center</span></strong></h2>
        <p class="lead">The Katuparan Center is composed of 12 clusters. As part of the Whole-of-Nation Approach, each cluster is comprised of relevant government agencies committed to serving within its operating principle.</p>
    </div>

    <div class="circle-container"
         style="background-image: url('{{ asset('assets/img/circlebg.png') }}'); background-size: cover; background-position: center;">
        <div class="central-circle">
            <img src="{{ asset('assets/img/SHEILD.png') }}" alt="Central Logo" />
        </div>
        <div class="circle-sector-container">
            @foreach ($wheel as $name => $img)
                <div class="circle" style="--i:{{ $loop->index }};">
                    <a href="{{ route('admin.clusters.show', Str::slug($name)) }}" title="{{ $name }}">
                        <img src="{{ asset('assets/img/cluster/'.$img) }}" alt="{{ $name }}" class="circle-image" />
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@push('styles')
<style>
    .cluster-header { padding: .5rem 0; margin-bottom: .5rem; }
    .cluster-header h2 { margin: 0 0 .25rem; font-size: 2.5rem; }
    .cluster-header .lead { max-width: 800px; margin: .25rem auto; color: #6c757d; font-size: 1rem; }
    .text-orange { color: #ff6b00; }

    /* Full-bleed sky background like the original — breaks out of the content padding */
    .circle-container {
        width: 100vw;
        height: 100vh;
        position: relative;
        left: 50%;
        margin-left: -50vw;
        margin-right: -50vw;
        margin-top: 2rem;
        margin-bottom: -50px;
    }
    .circle-image { transition: all .3s ease; }
    .circle:hover .circle-image {
        transform: scale(1.1);
        filter: drop-shadow(0 0 10px rgba(0,0,0,.3));
    }
</style>
@endpush
