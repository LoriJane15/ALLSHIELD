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
    $nav = collect($meta['nav'] ?? [])
        ->concat(config('shield.shared_nav', []))
        ->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i['route']));
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
                    <ul class="navbar-nav navbar-nav-right d-flex align-items-center">
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
                                         onerror="this.onerror=null;this.src='{{ asset('assets/img/kc-logo.svg') }}'" alt="profile" class="user-avatar-img" />
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
