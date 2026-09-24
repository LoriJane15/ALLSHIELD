@extends('layouts.skydash-h')
@section('title', $cluster['name'])
@section('heading', $cluster['name'].' Cluster')

@section('content')
<div class="row mb-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-end mb-2">
                    <a href="{{ route('admin.clusters.index') }}" class="btn btn-sm btn-light bg-white">
                        <i class="mdi mdi-arrow-left"></i> Back to clusters
                    </a>
                </div>

                {{-- Profile header --}}
                <div class="profile-container">
                    <div class="cover-photo">
                        <img src="{{ asset('assets/img/cover.png') }}" alt="Cover Photo">
                    </div>
                    <div class="profile-picture">
                        <img src="{{ asset('assets/img/cluster/'.$cluster['logo']) }}" alt="{{ $cluster['name'] }}">
                    </div>
                    <div class="profile-info d-flex justify-content-between align-items-center">
                        <div>
                            <h2>{{ $cluster['name'] }}</h2>
                            <p>NATIONAL PLAN ELCAC</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    {{-- Left: objective / members --}}
                    <div class="col-lg-3">
                        <div class="profile-info border-bottom py-4">
                            <h6>Objective</h6>
                            <p class="justified-text">Coordinate and deliver interventions of the {{ $cluster['name'] }} Cluster to sustain the reintegration of Former Rebels.</p>
                            <br>
                            <h6>Mission</h6>
                            <p class="justified-text">As part of the Whole-of-Nation Approach, this cluster mobilises its member agencies to serve within its operating principle.</p>
                        </div>

                        <div class="profile-info py-4">
                            <div class="mb-4">
                                <h6>Members</h6>
                                <p class="clearfix">
                                    <span class="py-1 float-left">
                                        @foreach ($allClusters as $c)
                                            <img src="{{ asset('assets/img/cluster/'.$c['logo']) }}" alt="{{ $c['name'] }}" title="{{ $c['name'] }}" class="member-img">
                                        @endforeach
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Right: agencies --}}
                    <div class="col-lg-9">
                        <div class="mt-4 py-6 border-bottom">
                            <ul class="nav profile-navbar">
                                <li class="nav-item"><a class="nav-link" href="#"><i class="ti-user"></i> Info</a></li>
                                <li class="nav-item"><a class="nav-link active" href="#"><i class="ti-receipt"></i> Agencies</a></li>
                            </ul>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="row portfolio-grid">
                                    @forelse ($cluster['agencies'] as $agency)
                                        <div class="col-xl-3 col-lg-3 col-md-3 col-sm-6 col-12">
                                            <figure class="effect-text-in">
                                                <img src="{{ asset('assets/'.$agency['img']) }}" alt="{{ $agency['acro'] }}"
                                                     onerror="this.style.display='none'" />
                                                <figcaption>
                                                    <h4>{{ $agency['acro'] }}</h4>
                                                    <p>{{ $agency['name'] }}</p>
                                                </figcaption>
                                            </figure>
                                        </div>
                                    @empty
                                        <div class="col-12"><p class="text-muted">No agencies listed for this cluster.</p></div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
