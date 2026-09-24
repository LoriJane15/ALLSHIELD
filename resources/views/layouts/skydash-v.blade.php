<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — SHIELD</title>

    <link rel="stylesheet" href="{{ asset('assets/vendors/feather/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/ti-icons/css/themify-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/simple-line-icons/css/simple-line-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/vertical-layout-light/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
    <link rel="shortcut icon" href="{{ asset('assets/img/SHEILD.png') }}">
    @stack('styles')
</head>
<body class="sidebar-fixed">
@php
    $role = auth()->user()->role;
    $meta = config("shield.roles.$role");
    $nav = collect($meta['nav'] ?? [])->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i['route']));
@endphp
<div class="container-scroller">
    {{-- Top navbar --}}
    <nav class="navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
        <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
            <a class="navbar-brand brand-logo" href="{{ route(auth()->user()->homeRoute()) }}">
                <img src="{{ asset('assets/img/SHIELD horizontal.png') }}" alt="logo" style="width:120px;height:auto;" />
            </a>
            <a class="navbar-brand brand-logo-mini" href="{{ route(auth()->user()->homeRoute()) }}">
                <img src="{{ asset('assets/img/SHEILD.png') }}" alt="logo" />
            </a>
        </div>
        <div class="navbar-menu-wrapper d-flex align-items-stretch">
            <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                <span class="icon-menu"></span>
            </button>
            <span class="ms-3 align-self-center h5 mb-0 text-dark d-none d-md-block">@yield('heading', $meta['label'] ?? '')</span>
            <ul class="navbar-nav navbar-nav-right ms-auto">
                <li class="nav-item nav-profile dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" id="profileDropdown">
                        <div class="nav-profile-img">
                            <img src="{{ auth()->user()->logo ? asset('assets/'.auth()->user()->logo) : asset('assets/img/kc-logo.svg') }}"
                                 onerror="this.onerror=null;this.src='{{ asset('assets/img/kc-logo.svg') }}'"
                                 alt="profile" style="width:32px;height:32px;border-radius:50%;object-fit:cover;" />
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
                        <h6 class="dropdown-header mb-0 text-truncate">{{ auth()->user()->name }}</h6>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="ti-settings text-primary"></i> Settings</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown-item" type="submit"><i class="ti-power-off text-primary"></i> Logout</button>
                        </form>
                    </div>
                </li>
            </ul>
            <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
                <span class="icon-menu"></span>
            </button>
        </div>
    </nav>

    <div class="container-fluid page-body-wrapper">
        {{-- Sidebar --}}
        <nav class="sidebar sidebar-offcanvas" id="sidebar">
            <ul class="nav">
                @foreach ($nav as $item)
                    @php
                        $patterns = [$item['route']];
                        // match sibling child routes (e.g. lgu.rcsp.* for lgu.rcsp.index) — only for resource routes, not role.dashboard
                        if (substr_count($item['route'], '.') >= 2) {
                            $patterns[] = preg_replace('/[^.]+$/', '*', $item['route']);
                        }
                        $patterns = array_merge($patterns, (array) ($item['active'] ?? []));
                        $isActive = request()->routeIs(...$patterns);
                    @endphp
                    <li class="nav-item {{ $isActive ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route($item['route']) }}">
                            <i class="{{ $item['skyicon'] ?? 'icon-grid' }} menu-icon"></i>
                            <span class="menu-title">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="main-panel">
            <div class="content-wrapper">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @yield('content')
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/vendors/js/vendor.bundle.base.js') }}"></script>
<script src="{{ asset('assets/vendors/sweetalert/sweetalert.min.js') }}"></script>
    <script src="{{ asset('assets/js/off-canvas.js') }}"></script>
<script src="{{ asset('assets/js/hoverable-collapse.js') }}"></script>
<script src="{{ asset('assets/js/jquery.cookie.js') }}"></script>
<script src="{{ asset('assets/js/template.js') }}"></script>
<script src="{{ asset('assets/js/settings.js') }}"></script>
<script src="{{ asset('assets/vendors/chart.js/Chart.min.js') }}"></script>
{{-- App JS bundle: maps, confirm dialogs, cascades, live comments.
     Only the JS entry — resources/css/app.css is Tailwind and would
     override the SkyDash Bootstrap styling. --}}
@vite(['resources/js/app.js'])
@stack('scripts')
</body>
</html>
