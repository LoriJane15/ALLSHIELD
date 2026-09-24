<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — SHIELD</title>

    {{-- SkyDash template CSS (ported from the original) --}}
    <link rel="stylesheet" href="{{ asset('assets/vendors/feather/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/ti-icons/css/themify-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/simple-line-icons/css/simple-line-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/horizontal-layout-light/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
    <link rel="shortcut icon" href="{{ asset('assets/img/SHEILD.png') }}">
    @stack('styles')
</head>
<body>
@php
    $role = auth()->user()->role;
    $meta = config("shield.roles.$role");
    $nav = collect($meta['nav'] ?? [])->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i['route']));
@endphp
<div class="container-scroller">
    <div class="horizontal-menu">
        {{-- Top navbar --}}
        <nav class="navbar top-navbar col-lg-12 col-12 p-0">
            <div class="container">
                <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                    <a class="navbar-brand brand-logo" href="{{ route(auth()->user()->homeRoute()) }}">
                        <img src="{{ asset('assets/img/SHIELD horizontal.png') }}" alt="logo" style="width:130px;height:auto;" />
                    </a>
                    <a class="navbar-brand brand-logo-mini" href="{{ route(auth()->user()->homeRoute()) }}">
                        <img src="{{ asset('assets/img/SHEILD.png') }}" alt="logo" />
                    </a>
                </div>
                <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
                    <ul class="navbar-nav mr-lg-2">
                        <li class="nav-item nav-search d-none d-lg-block">
                            <div class="input-group">
                                <div class="input-group-prepend hover-cursor"><span class="input-group-text"><i class="icon-search"></i></span></div>
                                <input type="text" class="form-control" placeholder="Search now" />
                            </div>
                        </li>
                    </ul>
                    <ul class="navbar-nav navbar-nav-right">
                        <li class="nav-item nav-profile dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" id="profileDropdown">
                                <img src="{{ auth()->user()->logo ? asset('assets/'.auth()->user()->logo) : asset('assets/img/kc-logo.svg') }}"
                                     onerror="this.onerror=null;this.src='{{ asset('assets/img/kc-logo.svg') }}'" alt="profile" />
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
                    <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="horizontal-menu-toggle">
                        <span class="ti-menu"></span>
                    </button>
                </div>
            </div>
        </nav>

        {{-- Bottom navbar (menu) --}}
        <nav class="bottom-navbar">
            <div class="container">
                <ul class="nav page-navigation">
                    @foreach ($nav as $item)
                        @php
                            $patterns = [$item['route']];
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
            </div>
        </nav>
    </div>

    <div class="container-fluid page-body-wrapper">
        <div class="main-panel">
            <div class="content-wrapper" style="padding-top: 1.5rem;">
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
