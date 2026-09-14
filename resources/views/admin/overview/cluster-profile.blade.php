@extends('layouts.skydash-h')
@section('title', $cluster['name'])
@section('heading', $cluster['name'].' Cluster')

@section('content')
<div class="clusters-overview-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('admin.clusters.index') }}" class="btn btn-sm btn-outline-secondary bg-white px-3 py-2" style="border-radius: 8px; font-weight: 600;">
            <i class="mdi mdi-arrow-left mr-1"></i> Back to 12 Clusters
        </a>
        <span class="badge px-3 py-2" style="background: rgba(37, 99, 235, 0.08); color: #1e40af; border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 20px; font-size: 0.78rem; font-weight: 700;">
            <i class="mdi mdi-shield-check mr-1"></i> NATIONAL PLAN ELCAC
        </span>
    </div>

    {{-- Main Profile Card --}}
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 20px; overflow: hidden; border: 1px solid rgba(226, 232, 240, 0.85) !important;">
        {{-- Cover Banner --}}
        <div style="height: 180px; background: linear-gradient(135deg, #1e3a8a 0%, #172554 100%); position: relative;">
            <div style="position: absolute; inset: 0; background-image: url('{{ asset('assets/img/circlebg.png') }}'); background-size: cover; background-position: center; opacity: 0.25;"></div>
            <div style="position: absolute; inset: 0; background: radial-gradient(circle at top right, rgba(217, 119, 6, 0.2) 0%, transparent 60%);"></div>
        </div>

        <div class="card-body px-4 pb-4 pt-0">
            {{-- Profile Header Row --}}
            <div class="d-flex flex-column flex-md-row align-items-center align-items-md-end mb-4" style="margin-top: -65px; gap: 1.5rem;">
                <div style="width: 130px; height: 130px; border-radius: 50%; background: #ffffff; border: 4px solid #ffffff; box-shadow: 0 10px 25px rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0; position: relative; z-index: 10;">
                    <img src="{{ asset('assets/img/cluster/'.$cluster['logo']) }}" alt="{{ $cluster['name'] }}" style="width: 108px; height: 108px; object-fit: contain; border-radius: 50%;">
                </div>
                <div class="text-center text-md-left flex-grow-1">
                    <h2 class="h3 font-weight-bold text-dark mb-1">{{ $cluster['name'] }}</h2>
                    <p class="text-muted small mb-0 font-weight-medium">
                        <i class="mdi mdi-bank-outline mr-1" style="color: #2563eb;"></i> Inter-Agency Executive Cluster &bull; {{ count($cluster['agencies']) }} Member {{ Str::plural('Agency', count($cluster['agencies'])) }}
                    </p>
                </div>
            </div>

            <div class="row">
                {{-- Left: Mandate, Objective & Other Clusters --}}
                <div class="col-lg-4 col-xl-3 mb-4 mb-lg-0">
                    <div class="p-3 mb-3" style="background: #f8fafc; border-radius: 14px; border: 1px solid #e2e8f0;">
                        <h6 class="font-weight-bold text-dark mb-2" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em;">
                            <i class="mdi mdi-bullseye-arrow mr-1" style="color: #2563eb;"></i> Objective
                        </h6>
                        <p class="small text-muted mb-0" style="line-height: 1.6;">
                            Coordinate and deliver interventions of the {{ $cluster['name'] }} to sustain the comprehensive reintegration and socio-economic wellbeing of Former Rebels.
                        </p>
                    </div>

                    <div class="p-3 mb-3" style="background: #f8fafc; border-radius: 14px; border: 1px solid #e2e8f0;">
                        <h6 class="font-weight-bold text-dark mb-2" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em;">
                            <i class="mdi mdi-flag-outline mr-1" style="color: #d97706;"></i> Mission &amp; Approach
                        </h6>
                        <p class="small text-muted mb-0" style="line-height: 1.6;">
                            As part of the Whole-of-Nation Approach, this cluster mobilizes its member agencies to serve harmoniously within its specialized operating principle.
                        </p>
                    </div>

                    <div class="p-3" style="background: #f8fafc; border-radius: 14px; border: 1px solid #e2e8f0;">
                        <h6 class="font-weight-bold text-dark mb-2" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em;">
                            <i class="mdi mdi-view-grid mr-1" style="color: #4f46e5;"></i> Inter-Cluster Network
                        </h6>
                        <div class="d-flex flex-wrap" style="gap: 6px;">
                            @foreach ($allClusters as $slugKey => $c)
                                <a href="{{ route('admin.clusters.show', $slugKey) }}" title="{{ $c['name'] }}" class="d-inline-block" style="transition: transform 0.2s ease;">
                                    <img src="{{ asset('assets/img/cluster/'.$c['logo']) }}" alt="{{ $c['name'] }}" style="width: 34px; height: 34px; border-radius: 50%; border: 1.5px solid #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,0.1); background: #fff;">
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Right: Member Agencies Grid --}}
                <div class="col-lg-8 col-xl-9">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h5 class="font-weight-bold text-dark mb-0" style="font-size: 1.05rem;">
                            <i class="mdi mdi-account-group mr-1.5" style="color: #2563eb;"></i>
                            Member Agencies ({{ count($cluster['agencies']) }})
                        </h5>
                        <span class="text-muted small">Collaborative Government Roster</span>
                    </div>

                    <div class="row">
                        @forelse ($cluster['agencies'] as $agency)
                            <div class="col-xl-4 col-md-6 col-12 mb-3">
                                <div class="card h-100 border" style="border-radius: 12px; border-color: #e2e8f0 !important; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);">
                                    <div class="card-body p-3 d-flex align-items-center" style="gap: 0.85rem;">
                                        <div style="width: 50px; height: 50px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <img src="{{ asset('assets/'.$agency['img']) }}" alt="{{ $agency['acro'] }}" style="max-width: 40px; max-height: 40px; object-fit: contain;" onerror="this.src='{{ asset('assets/img/kc-logo.svg') }}'">
                                        </div>
                                        <div style="min-width: 0;">
                                            <div class="badge badge-light text-primary font-weight-bold mb-1" style="font-size: 0.72rem; border-radius: 4px;">
                                                {{ $agency['acro'] }}
                                            </div>
                                            <div class="font-weight-bold text-dark text-truncate" style="font-size: 0.82rem;" title="{{ $agency['name'] }}">
                                                {{ $agency['name'] }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 py-4 text-center">
                                <p class="text-muted">No agencies currently assigned to this cluster.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/clusters-overview.css') }}">
@endpush
