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
    $nav = collect($meta['nav'] ?? [])
        ->concat(config('shield.shared_nav', []))
        ->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i['route']));
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
            <ul class="navbar-nav navbar-nav-right ms-auto d-flex align-items-center">
                <li class="nav-item me-2 d-flex align-items-center">
                    <a class="notification-indicator-btn"
                       href="{{ $notificationDestination }}"
                       title="Notifications" aria-label="Notifications">
                        <i class="mdi mdi-bell-outline"></i>
                        @if ($notificationUnreadCount > 0)
                            <span class="badge badge-danger rounded-pill"
                                  data-notification-badge aria-label="{{ $notificationUnreadCount }} unread notifications"
                                  style="position: absolute; top: -4px; right: -4px; font-size: 0.65rem; padding: 0.2em 0.45em; font-weight: 750;">
                                {{ $notificationUnreadCount }}
                            </span>
                        @endif
                    </a>
                </li>
                <li class="nav-item nav-profile dropdown">
                    <a class="user-profile-pill dropdown-toggle" href="#" data-bs-toggle="dropdown" id="profileDropdown" aria-expanded="false">
                        <div class="user-avatar-wrap">
                            <img src="{{ $topbarProfileImage ?: asset('assets/img/kc-logo.svg') }}"
                                 onerror="this.onerror=null;this.src='{{ asset('assets/img/kc-logo.svg') }}'"
                                 alt="profile" class="user-avatar-img" />
                            <span class="user-status-dot"></span>
                        </div>
                        <div class="user-info-wrap d-none d-sm-flex">
                            <span class="user-name">{{ auth()->user()->name }}</span>
                            @if(auth()->user()->role)
                                <span class="user-role-badge">
                                    {{ str_replace('_', ' ', auth()->user()->role->value ?? auth()->user()->role) }}
                                </span>
                            @endif
                        </div>
                        <i class="mdi mdi-chevron-down user-chevron d-none d-sm-inline-block"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right navbar-dropdown user-dropdown-menu" aria-labelledby="profileDropdown">
                        <div class="user-dropdown-header">
                            <img src="{{ $topbarProfileImage ?: asset('assets/img/kc-logo.svg') }}"
                                 onerror="this.onerror=null;this.src='{{ asset('assets/img/kc-logo.svg') }}'"
                                 alt="profile" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1.5px solid #e2e8f0;" />
                            <div class="overflow-hidden">
                                <div class="font-weight-bold text-dark text-truncate" style="font-size: 0.84rem;">{{ auth()->user()->name }}</div>
                                <div class="text-muted small text-truncate" style="font-size: 0.72rem;">{{ auth()->user()->email ?? auth()->user()->username }}</div>
                            </div>
                        </div>
                        <a class="dropdown-item user-dropdown-item" href="{{ route('profile.edit') }}"><i class="ti-settings text-primary"></i> Settings & Account</a>
                        <div class="dropdown-divider m-0"></div>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown-item user-dropdown-item text-danger" type="submit"><i class="ti-power-off text-danger"></i> Logout</button>
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
                @php $currentNavGroup = null; @endphp
                @foreach ($nav as $item)
                    @php
                        $itemGroup = $item['group'] ?? null;
                        $patterns = [$item['route']];
                        // match sibling child routes (e.g. lgu.rcsp.* for lgu.rcsp.index) — only for resource routes, not role.dashboard
                        if (substr_count($item['route'], '.') >= 2) {
                            $patterns[] = preg_replace('/[^.]+$/', '*', $item['route']);
                        }
                        $patterns = array_merge($patterns, (array) ($item['active'] ?? []));
                        $isActive = request()->routeIs(...$patterns);
                    @endphp
                    @if ($itemGroup && $itemGroup !== $currentNavGroup)
                        @php $currentNavGroup = $itemGroup; @endphp
                        <li class="nav-item nav-category text-uppercase px-4 pt-3 pb-1" style="font-size: 0.68rem; font-weight: 800; letter-spacing: 0.08em; color: #94a3b8;">
                            {{ $itemGroup }}
                        </li>
                    @endif
                    <li class="nav-item {{ $isActive ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route($item['route']) }}"
                           @if ($item['route'] === 'chat.index') data-chat-nav data-unread-url="{{ route('chat.unread') }}" @endif>
                            <i class="{{ $item['skyicon'] ?? 'icon-grid' }} menu-icon"></i>
                            <span class="menu-title">{{ $item['label'] }}</span>
                            @if ($item['route'] === 'chat.index')
                                <span class="badge badge-danger ml-2 {{ ($chatUnread['total'] ?? 0) > 0 ? '' : 'd-none' }}"
                                      data-chat-nav-badge aria-label="{{ $chatUnread['total'] ?? 0 }} unread messages">
                                    {{ $chatUnread['total_text'] ?? '0' }}
                                </span>
                            @endif
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
