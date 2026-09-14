@extends('layouts.skydash-h')
@section('title', 'Users')
@section('heading', 'System Users & Stakeholders')

@php
    $totalUsers = $users->flatten()->count();
    $totalRoles = $users->count();
    $lguCount = $users->get('lgu', collect())->count();
    $adminCount = $users->get('admin', collect())->count() + $users->get('super_admin', collect())->count();
    $partnerCount = $totalUsers - $lguCount - $adminCount;

    $roleIcons = [
        'super_admin' => 'mdi-account-settings',
        'admin'       => 'mdi-shield-check',
        'lgu'         => 'mdi-city',
        'gov_agency'  => 'mdi-bank',
        'mblrc'       => 'mdi-home-map-marker',
        '39th_ib'     => 'mdi-shield-account',
        'afp'         => 'mdi-shield',
        'japic'       => 'mdi-certificate',
        'pswdo'       => 'mdi-account-heart',
    ];
@endphp

@push('styles')
<style>
    .users-page-wrapper { width: 100%; padding: 0 0.15rem 2.5rem 0.15rem; }
    
    /* Stat Cards */
    .user-stat-card {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04);
        transition: all 0.25s ease;
        height: 100%;
        position: relative;
        overflow: hidden;
    }
    .user-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.09);
        border-color: rgba(37, 99, 235, 0.35);
    }
    .user-stat-card::before {
        content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 16px 0 0 16px;
    }
    .user-stat-card.stat-blue::before { background: #2563eb; }
    .user-stat-card.stat-indigo::before { background: #4f46e5; }
    .user-stat-card.stat-sky::before { background: #0284c7; }
    .user-stat-card.stat-amber::before { background: #d97706; }

    .user-icon-wrap {
        width: 48px; height: 48px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.45rem; flex-shrink: 0;
    }
    .user-icon-wrap.icon-blue { background: rgba(37, 99, 235, 0.09); color: #2563eb; border: 1px solid rgba(37, 99, 235, 0.18); }
    .user-icon-wrap.icon-indigo { background: rgba(79, 70, 229, 0.09); color: #4f46e5; border: 1px solid rgba(79, 70, 229, 0.18); }
    .user-icon-wrap.icon-sky { background: rgba(2, 132, 199, 0.09); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.18); }
    .user-icon-wrap.icon-amber { background: rgba(217, 119, 6, 0.09); color: #d97706; border: 1px solid rgba(217, 119, 6, 0.18); }

    .user-stat-value {
        font-size: 1.85rem; font-weight: 800; line-height: 1.15; color: #0f172a; margin-bottom: 0.15rem; letter-spacing: -0.02em;
    }
    .user-stat-label {
        font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; margin: 0;
    }

    /* Header & Filter Search */
    .users-directory-header {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 18px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04);
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .users-header-title h3 {
        font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 0.15rem 0;
        display: flex; align-items: center; gap: 0.5rem;
    }
    .users-header-title p { font-size: 0.82rem; color: #64748b; margin: 0; }

    .users-search-box { position: relative; min-width: 300px; }
    .users-search-box input {
        width: 100%; padding: 0.55rem 1rem 0.55rem 2.4rem;
        background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px;
        font-size: 0.85rem; color: #1e293b; outline: none; transition: all 0.2s ease;
    }
    .users-search-box input:focus {
        background: #ffffff; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .users-search-box i {
        position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: 1.1rem;
    }

    /* Role Filter Buttons */
    .role-pills-bar {
        display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem; margin-bottom: 1.25rem;
    }
    .role-pill-btn {
        padding: 0.4rem 0.9rem; border-radius: 20px; font-size: 0.78rem; font-weight: 700;
        background: #ffffff; border: 1px solid #e2e8f0; color: #475569; cursor: pointer;
        transition: all 0.2s ease; white-space: nowrap; text-decoration: none !important;
    }
    .role-pill-btn:hover, .role-pill-btn.active {
        background: #2563eb; color: #ffffff; border-color: #2563eb; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
    }

    /* Role Group Card */
    .role-group-card {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 18px;
        box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04);
        margin-bottom: 1.5rem;
        overflow: hidden;
        transition: all 0.25s ease;
    }
    .role-group-card:hover {
        box-shadow: 0 10px 28px -4px rgba(15, 23, 42, 0.08);
        border-color: rgba(37, 99, 235, 0.28);
    }
    .role-group-header {
        padding: 1.15rem 1.5rem;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .role-title-wrap { display: flex; align-items: center; gap: 0.75rem; }
    .role-icon-seal {
        width: 42px; height: 42px; border-radius: 12px;
        background: rgba(37, 99, 235, 0.08); color: #2563eb;
        border: 1px solid rgba(37, 99, 235, 0.15);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; flex-shrink: 0;
    }
    .role-title-text {
        font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;
        text-transform: uppercase; letter-spacing: 0.03em;
    }
    .role-badge-count {
        font-size: 0.76rem; font-weight: 700; padding: 0.3rem 0.85rem;
        border-radius: 20px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;
    }

    /* User Items Grid */
    .role-users-grid { padding: 1.25rem 1.5rem; }
    .user-item-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1rem 1.15rem;
        transition: all 0.22s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.85rem;
        height: 100%;
    }
    .user-item-card:hover {
        background: #ffffff;
        border-color: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px -3px rgba(37, 99, 235, 0.12);
    }
    .user-main-info { display: flex; align-items: center; gap: 0.85rem; min-width: 0; flex: 1; }
    .user-avatar-circle {
        width: 44px; height: 44px; border-radius: 50%;
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        color: #ffffff; display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 0.95rem; flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25); border: 2px solid #ffffff;
    }
    .user-details-wrap { min-width: 0; flex: 1; }
    .user-display-name {
        font-size: 0.92rem; font-weight: 700; color: #0f172a; margin: 0 0 0.15rem 0;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .user-username-handle { font-size: 0.78rem; color: #2563eb; font-weight: 600; margin: 0; }
    .user-meta-right { display: flex; align-items: center; gap: 0.6rem; flex-shrink: 0; }
    .user-station-badge {
        display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.74rem; font-weight: 600;
        padding: 0.28rem 0.65rem; border-radius: 8px; background: #ffffff; color: #334155;
        border: 1px solid #cbd5e1; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .btn-chat-icon {
        width: 36px; height: 36px; border-radius: 8px; background: #ffffff; color: #2563eb;
        border: 1px solid #e2e8f0; display: inline-flex; align-items: center; justify-content: center;
        font-size: 1.15rem; transition: all 0.2s ease; text-decoration: none !important;
    }
    .btn-chat-icon:hover {
        background: #2563eb; color: #ffffff; border-color: #2563eb; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
    }
</style>
@endpush

@section('content')
<div class="users-page-wrapper">
    {{-- Top Metrics Overview --}}
    <div class="row mb-4">
        <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
            <div class="user-stat-card stat-blue">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="user-stat-value">{{ number_format($totalUsers) }}</div>
                        <div class="user-stat-label">Total System Users</div>
                    </div>
                    <div class="user-icon-wrap icon-blue">
                        <i class="mdi mdi-account-group"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
            <div class="user-stat-card stat-indigo">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="user-stat-value">{{ number_format($totalRoles) }}</div>
                        <div class="user-stat-label">Stakeholder Roles</div>
                    </div>
                    <div class="user-icon-wrap icon-indigo">
                        <i class="mdi mdi-shield-account"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
            <div class="user-stat-card stat-sky">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="user-stat-value">{{ number_format($lguCount) }}</div>
                        <div class="user-stat-label">Municipal / LGU Users</div>
                    </div>
                    <div class="user-icon-wrap icon-sky">
                        <i class="mdi mdi-city"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3 mb-3 mb-lg-0">
            <div class="user-stat-card stat-amber">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="user-stat-value">{{ number_format($partnerCount) }}</div>
                        <div class="user-stat-label">Partner Agencies &amp; Units</div>
                    </div>
                    <div class="user-icon-wrap icon-amber">
                        <i class="mdi mdi-domain"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Search and Filter Header --}}
    <div class="users-directory-header">
        <div class="users-header-title">
            <h3>
                <i class="mdi mdi-account-multiple-check" style="color: #2563eb;"></i>
                System Stakeholder Directory
            </h3>
            <p>Direct roster of active administrative, military, municipal, and partner agency focal persons.</p>
        </div>
        <div class="users-search-box">
            <i class="mdi mdi-magnify"></i>
            <input type="text" id="userFilterInput" placeholder="Search by name, handle, or agency..." autocomplete="off">
        </div>
    </div>

    {{-- Role Pills Filter Bar --}}
    <div class="role-pills-bar">
        <button type="button" class="role-pill-btn active" data-filter-role="all">All Stakeholders ({{ $totalUsers }})</button>
        @foreach ($users as $role => $group)
            @php
                $roleLabel = config("shield.roles.$role.label", ucwords(str_replace('_', ' ', $role)));
            @endphp
            <button type="button" class="role-pill-btn" data-filter-role="{{ $role }}">{{ $roleLabel }} ({{ $group->count() }})</button>
        @endforeach
    </div>

    {{-- Role Groups --}}
    <div id="usersDirectoryContainer">
        @foreach ($users as $role => $group)
            @php
                $roleLabel = config("shield.roles.$role.label", ucwords(str_replace('_', ' ', $role)));
                $icon = $roleIcons[$role] ?? 'mdi-account-outline';
            @endphp
            <div class="role-group-card role-search-group" data-role-key="{{ $role }}" data-role-name="{{ strtolower($roleLabel) }}">
                <div class="role-group-header">
                    <div class="role-title-wrap">
                        <div class="role-icon-seal">
                            <i class="mdi {{ $icon }}"></i>
                        </div>
                        <div>
                            <h4 class="role-title-text">{{ $roleLabel }}</h4>
                            <span class="text-muted small">SHIELD Operational Stakeholder Group</span>
                        </div>
                    </div>
                    <span class="role-badge-count">{{ $group->count() }} {{ Str::plural('User', $group->count()) }}</span>
                </div>

                <div class="role-users-grid">
                    <div class="row">
                        @foreach ($group as $u)
                            @php
                                $station = $u->municipality?->name ?? $u->govAgency?->name ?? $u->govAgency?->acronym ?? '';
                                $initials = collect(explode(' ', $u->name))->map(fn($s) => mb_substr($s, 0, 1))->take(2)->implode('');
                            @endphp
                            <div class="col-xl-4 col-lg-6 col-12 mb-3 user-item-wrapper" data-user-text="{{ strtolower($u->name . ' ' . $u->username . ' ' . $station . ' ' . $roleLabel) }}">
                                <div class="user-item-card">
                                    <div class="user-main-info">
                                        <div class="user-avatar-circle" title="{{ $u->name }}">
                                            {{ strtoupper($initials ?: 'U') }}
                                        </div>
                                        <div class="user-details-wrap">
                                            <h5 class="user-display-name" title="{{ $u->name }}">{{ $u->name }}</h5>
                                            <p class="user-username-handle">{{ '@'.$u->username }}</p>
                                        </div>
                                    </div>

                                    <div class="user-meta-right">
                                        @if ($station)
                                            <span class="user-station-badge" title="{{ $station }}">
                                                <i class="mdi mdi-map-marker"></i>
                                                {{ $station }}
                                            </span>
                                        @endif
                                        <a href="{{ route('chat.index') }}" class="btn-chat-icon" title="Message {{ $u->name }}">
                                            <i class="mdi mdi-comment-text-outline"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterInput = document.getElementById('userFilterInput');
        const userItems = document.querySelectorAll('.user-item-wrapper');
        const roleGroups = document.querySelectorAll('.role-search-group');
        const rolePills = document.querySelectorAll('.role-pill-btn');

        let activeRoleFilter = 'all';

        function applyFilters() {
            const term = (filterInput ? filterInput.value : '').toLowerCase().trim();

            roleGroups.forEach(function(group) {
                const groupRole = group.getAttribute('data-role-key');
                const matchesRole = (activeRoleFilter === 'all' || activeRoleFilter === groupRole);

                if (!matchesRole) {
                    group.style.display = 'none';
                    return;
                }

                let visibleCount = 0;
                const itemsInGroup = group.querySelectorAll('.user-item-wrapper');

                itemsInGroup.forEach(function(item) {
                    const text = item.getAttribute('data-user-text') || '';
                    if (!term || text.includes(term)) {
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (visibleCount > 0) {
                    group.style.display = '';
                } else {
                    group.style.display = 'none';
                }
            });
        }

        if (filterInput) {
            filterInput.addEventListener('input', applyFilters);
        }

        rolePills.forEach(function(pill) {
            pill.addEventListener('click', function() {
                rolePills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                activeRoleFilter = pill.getAttribute('data-filter-role');
                applyFilters();
            });
        });
    });
</script>
@endpush


