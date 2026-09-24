@extends('layouts.skydash-v')
@section('title', 'User Management')
@section('heading', 'User Management')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-dashboard.css') }}">
<style>
/* Embedded styles for instant rendering */
:root {
    --sa-primary: #4f46e5;
    --sa-royal: #2563eb;
    --sa-royal-light: #eff6ff;
    --sa-sky: #0284c7;
    --sa-sky-light: #f0f9ff;
    --sa-amber: #d97706;
    --sa-amber-light: #fffbeb;
    --sa-rose: #e11d48;
    --sa-rose-light: #fff1f2;
    --sa-slate-900: #0f172a;
    --sa-slate-800: #1e293b;
    --sa-slate-700: #334155;
    --sa-slate-500: #64748b;
    --sa-slate-200: #e2e8f0;
    --sa-slate-100: #f1f5f9;
    --sa-slate-50: #f8fafc;
    --sa-card-radius: 18px;
}

.sa-user-table-wrapper {
    background: #ffffff;
    border-radius: var(--sa-card-radius);
    border: 1px solid var(--sa-slate-200);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}

.sa-avatar-initials {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    font-weight: 700;
    color: #ffffff;
    flex-shrink: 0;
    text-transform: uppercase;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.sa-avatar-img {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    object-fit: cover;
    border: 1.5px solid var(--sa-slate-200);
    flex-shrink: 0;
}

.sa-role-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.sa-scope-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    background: var(--sa-slate-100);
    color: var(--sa-slate-700);
    border: 1px solid var(--sa-slate-200);
}

.sa-btn-action {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    border: none;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none !important;
}

.sa-btn-edit {
    background: var(--sa-royal-light);
    color: var(--sa-royal);
    border: 1px solid #bfdbfe;
}

.sa-btn-edit:hover {
    background: var(--sa-royal);
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
}

</style>
@endpush

@section('content')
<div class="sa-dashboard-wrapper">
    {{-- Executive Header Banner --}}
    <div class="sa-hero-banner mb-4">
        <div class="sa-hero-content">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="sa-hero-badge">
                        <i class="mdi mdi-account-group"></i>
                        <span>System Access &amp; User Directory</span>
                    </div>
                    <h1 class="sa-hero-title mb-1">User Management</h1>
                    <p class="sa-hero-subtitle mb-0">
                        Add, configure, and manage system accounts across stakeholder roles, government agencies, and municipal LGUs.
                    </p>
                </div>
                <div>
                    <button type="button" class="sa-hero-btn sa-hero-btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="mdi mdi-account-plus"></i>
                        <span>Add New User</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->has('account_lifecycle') || $errors->has('is_active'))
        <div class="alert alert-danger" role="alert">
            {{ $errors->first('account_lifecycle') ?: $errors->first('is_active') }}
        </div>
    @endif

    {{-- Top Quick Metric Cards (4 Cards) --}}
    <div class="sa-stat-grid mb-4">
        <div class="sa-stat-card">
            <div class="sa-stat-header">
                <div class="sa-stat-icon-wrap sa-icon-primary">
                    <i class="mdi mdi-account-multiple"></i>
                </div>
                <span class="sa-stat-badge sa-badge-primary">Total Accounts</span>
            </div>
            <div class="sa-stat-title">Total System Users</div>
            <div class="sa-stat-number">{{ $stats['total'] ?? $users->total() }}</div>
            <div class="sa-stat-subtitle">Registered System Profiles</div>
            <i class="mdi mdi-account-multiple sa-stat-watermark"></i>
        </div>

        <div class="sa-stat-card">
            <div class="sa-stat-header">
                <div class="sa-stat-icon-wrap sa-icon-royal">
                    <i class="mdi mdi-shield-account"></i>
                </div>
                <span class="sa-stat-badge sa-badge-royal">Security Matrix</span>
            </div>
            <div class="sa-stat-title">Stakeholder Roles</div>
            <div class="sa-stat-number" style="color: var(--sa-royal);">{{ $stats['roles_count'] ?? count($roles) }}</div>
            <div class="sa-stat-subtitle">Active Defined Access Tiers</div>
            <i class="mdi mdi-shield-account sa-stat-watermark"></i>
        </div>

        <div class="sa-stat-card">
            <div class="sa-stat-header">
                <div class="sa-stat-icon-wrap sa-icon-sky">
                    <i class="mdi mdi-city"></i>
                </div>
                <span class="sa-stat-badge sa-badge-sky">Local Government</span>
            </div>
            <div class="sa-stat-title">Municipal LGU Users</div>
            <div class="sa-stat-number" style="color: var(--sa-sky);">{{ $stats['lgu_count'] ?? 0 }}</div>
            <div class="sa-stat-subtitle">DILG &amp; RCSP Coordinators</div>
            <i class="mdi mdi-city sa-stat-watermark"></i>
        </div>

        <div class="sa-stat-card">
            <div class="sa-stat-header">
                <div class="sa-stat-icon-wrap sa-icon-amber">
                    <i class="mdi mdi-bank"></i>
                </div>
                <span class="sa-stat-badge sa-badge-amber">Partner Units</span>
            </div>
            <div class="sa-stat-title">Government Agency Users</div>
            <div class="sa-stat-number" style="color: var(--sa-amber);">{{ $stats['agency_count'] ?? 0 }}</div>
            <div class="sa-stat-subtitle">Partner Agency Officers</div>
            <i class="mdi mdi-bank sa-stat-watermark"></i>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="sa-card">
        <div class="sa-card-header">
            <div class="sa-card-header-left">
                <div class="sa-card-header-icon">
                    <i class="mdi mdi-account-group"></i>
                </div>
                <div>
                    <h3 class="sa-card-title">User Directory</h3>
                    <p class="sa-card-subtitle">Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }} registered users</p>
                </div>
            </div>
        </div>

        <div class="sa-card-body">
            {{-- Search & Filter Toolbar --}}
            <form method="GET" action="{{ route('super_admin.users.index') }}" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div class="sa-search-box" style="width: 320px;">
                    <i class="mdi mdi-magnify"></i>
                    <input name="search" value="{{ request('search') }}" placeholder="Search by name or username...">
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <select name="role" class="sa-filter-select" onchange="this.form.submit()">
                        <option value="">All Roles ({{ $stats['roles_count'] ?? count($roles) }})</option>
                        @foreach ($roles as $key => $meta)
                            <option value="{{ $key }}" @selected(request('role') === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-primary btn-sm px-3" style="border-radius: 9px; font-weight: 600; padding: 0.55rem 1rem;">
                        <i class="mdi mdi-filter-variant me-1"></i> Filter
                    </button>

                    @if(request('search') || request('role'))
                        <a href="{{ route('super_admin.users.index') }}" class="btn btn-light btn-sm px-3" style="border-radius: 9px; font-weight: 600; padding: 0.55rem 0.85rem;" title="Reset filters">
                            <i class="mdi mdi-refresh me-1"></i> Clear
                        </a>
                    @endif
                </div>
            </form>

            {{-- Table --}}
            <div class="table-responsive">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>User Profile</th>
                            <th>Username</th>
                            <th>Assigned Role</th>
                            <th>Status</th>
                            <th>Operational Scope</th>
                            <th>Created Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $u)
                            @php
                                $roleKey = $u->role->value ?? $u->role;
                                $roleMeta = $roles[$roleKey] ?? null;
                                $roleLabel = $roleMeta['label'] ?? ucfirst(str_replace('_', ' ', $roleKey));

                                // Role style mapping
                                $roleStyle = match($roleKey) {
                                    'super_admin' => ['class' => 'sa-role-super-admin', 'icon' => 'mdi-shield-account'],
                                    'admin' => ['class' => 'sa-role-admin', 'icon' => 'mdi-shield-check'],
                                    '39th_ib', 'afp' => ['class' => 'sa-role-military', 'icon' => 'mdi-shield'],
                                    'lgu' => ['class' => 'sa-role-lgu', 'icon' => 'mdi-city'],
                                    'gov_agency' => ['class' => 'sa-role-agency', 'icon' => 'mdi-bank'],
                                    default => ['class' => 'sa-role-social', 'icon' => 'mdi-account-heart'],
                                };

                                // Initials background gradients
                                $avatarGradients = [
                                    'linear-gradient(135deg, #4f46e5, #3b82f6)',
                                    'linear-gradient(135deg, #2563eb, #0284c7)',
                                    'linear-gradient(135deg, #7c3aed, #9333ea)',
                                    'linear-gradient(135deg, #0284c7, #0d9488)',
                                    'linear-gradient(135deg, #475569, #1e293b)'
                                ];
                                $avatarBg = $avatarGradients[abs(crc32($u->name)) % count($avatarGradients)];
                                $initials = strtoupper(substr($u->name, 0, 2));
                            @endphp
                            <tr>
                                {{-- User Profile --}}
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        @if ($u->logo)
                                            <img src="{{ asset('assets/'.$u->logo) }}"
                                                 onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\'sa-avatar-initials\' style=\'background:{{ $avatarBg }}\'>{{ $initials }}</div>';"
                                                 alt="{{ $u->name }}" class="sa-avatar-img">
                                        @else
                                            <div class="sa-avatar-initials" style="background: {{ $avatarBg }};">
                                                {{ $initials }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="sa-user-name">{{ $u->name }}</div>
                                            <div class="sa-user-subtext">{{ $u->email ?? 'System User' }}</div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Username --}}
                                <td>
                                    <span class="sa-username-badge">{{ '@'.$u->username }}</span>
                                </td>

                                {{-- Role --}}
                                <td>
                                    <span class="sa-role-tag {{ $roleStyle['class'] }}">
                                        <i class="mdi {{ $roleStyle['icon'] }}"></i>
                                        <span>{{ $roleLabel }}</span>
                                    </span>
                                </td>

                                {{-- Status --}}
                                <td>
                                    <span class="badge rounded-pill {{ $u->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $u->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>

                                {{-- Scope --}}
                                <td>
                                    @if ($u->municipality)
                                        <span class="sa-scope-badge">
                                            <i class="mdi mdi-city text-primary"></i>
                                            <span>{{ $u->municipality->name }}</span>
                                        </span>
                                    @elseif ($u->govAgency)
                                        <span class="sa-scope-badge">
                                            <i class="mdi mdi-bank text-warning"></i>
                                            <span>{{ $u->govAgency->acronym }}</span>
                                        </span>
                                    @else
                                        <span class="text-muted small">
                                            <i class="mdi mdi-layers-outline me-1"></i> Regional / Central
                                        </span>
                                    @endif
                                </td>

                                {{-- Created At --}}
                                <td>
                                    <div class="d-flex align-items-center gap-1 text-muted small">
                                        <i class="mdi mdi-calendar-range"></i>
                                        <span>{{ $u->created_at?->format('M d, Y') ?? '—' }}</span>
                                    </div>
                                </td>

                                {{-- Actions --}}
                                <td>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <button type="button" class="sa-btn-action sa-btn-edit" title="Edit User"
                                                data-edit-user
                                                data-id="{{ $u->id }}"
                                                data-username="{{ $u->username }}"
                                                data-name="{{ $u->name }}"
                                                data-role="{{ $roleKey }}"
                                                data-active="{{ $u->is_active ? '1' : '0' }}"
                                                data-municipality="{{ $u->municipality_id }}"
                                                data-agency="{{ $u->gov_agency_id }}"
                                                data-action="{{ route('super_admin.users.update', $u) }}">
                                            <i class="mdi mdi-pencil"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="sa-empty-state py-5">
                                        <div class="sa-empty-icon">
                                            <i class="mdi mdi-account-search"></i>
                                        </div>
                                        <div class="sa-empty-title">No System Users Found</div>
                                        <p class="sa-empty-subtitle">No accounts match your selected filter criteria. Try clearing search terms or selecting another role.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($users->hasPages())
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 pt-3 border-top">
                    <div class="text-muted small">
                        Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} users
                    </div>
                    <div>
                        {{ $users->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Add User Modal --}}
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered clean-modal-dialog">
            <div class="modal-content clean-modal-content">
                <div class="clean-modal-header d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center gap-3">
                        <div class="clean-modal-icon">
                            <i class="mdi mdi-account-plus"></i>
                        </div>
                        <div>
                            <h5 class="clean-modal-title">Create New User</h5>
                            <p class="clean-modal-subtitle">Provision account credentials and access permissions</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('super_admin.users.store') }}" enctype="multipart/form-data" data-user-form>
                    @csrf
                    <div class="clean-modal-body">
                        @include('super_admin.users._fields', ['isEdit' => false])
                    </div>
                    <div class="clean-modal-footer">
                        <button type="button" data-bs-dismiss="modal" class="btn btn-light px-4 py-2" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; border: 1.5px solid #e2e8f0; color: #475569;">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; background: #4f46e5; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);">
                            <i class="mdi mdi-check me-1"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit User Modal --}}
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered clean-modal-dialog">
            <div class="modal-content clean-modal-content">
                <div class="clean-modal-header d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center gap-3">
                        <div class="clean-modal-icon" style="background: #eff6ff; color: #2563eb;">
                            <i class="mdi mdi-account-edit"></i>
                        </div>
                        <div>
                            <h5 class="clean-modal-title">Edit User Profile</h5>
                            <p class="clean-modal-subtitle">Update account details, role assignments, or operational scope</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" data-user-form data-edit-form>
                    @csrf @method('PUT')
                    <div class="clean-modal-body">
                        @include('super_admin.users._fields', ['isEdit' => true])
                    </div>
                    <div class="clean-modal-footer">
                        <button type="button" data-bs-dismiss="modal" class="btn btn-light px-4 py-2" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; border: 1.5px solid #e2e8f0; color: #475569;">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; background: #2563eb; border: none; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">
                            <i class="mdi mdi-content-save-outline me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        // Password visibility toggle
        function togglePasswordVisibility(btn) {
            const input = btn.closest('.position-relative').querySelector('.sa-password-input');
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'mdi mdi-eye-off-outline';
            } else {
                input.type = 'password';
                icon.className = 'mdi mdi-eye-outline';
            }
        }

        // File change preview
        function handleFileChange(input) {
            const container = input.closest('.col-12');
            const display = container.querySelector('.sa-file-name-display');
            const previewBox = container.querySelector('.sa-preview-box');
            if (input.files && input.files[0]) {
                const file = input.files[0];
                display.textContent = 'Selected: ' + file.name;
                display.style.display = 'block';

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewBox.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
                    };
                    reader.readAsDataURL(file);
                }
            } else {
                display.style.display = 'none';
                previewBox.innerHTML = '<i class="mdi mdi-image-outline text-muted fs-4"></i>';
            }
        }

        // Role-conditional field visibility (both modals).
        function syncRoleFields(form) {
            const role = form.querySelector('[name=role]').value;
            form.querySelectorAll('[data-role-field]').forEach((el) => {
                el.classList.toggle('d-none', el.dataset.roleField !== role);
            });
        }
        document.querySelectorAll('[data-user-form]').forEach((form) => {
            const roleSel = form.querySelector('[name=role]');
            roleSel.addEventListener('change', () => syncRoleFields(form));
            syncRoleFields(form);
        });

        // Populate + open the single edit modal.
        function openEditUserModal(user) {
            const f = document.querySelector('[data-edit-form]');
            f.action = user.action;
            f.querySelector('[name=username]').value = user.username;
            f.querySelector('[name=name]').value = user.name;
            f.querySelector('[name=role]').value = user.role;
            f.querySelector('[name=is_active]').value = user.active;
            f.querySelector('[name=municipality_id]').value = user.municipality || '';
            f.querySelector('[name=gov_agency_id]').value = user.agency || '';
            f.querySelector('[name=password]').value = '';
            f.querySelector('[name=password_confirmation]').value = '';

            const fileDisplay = f.querySelector('.sa-file-name-display');
            const previewBox = f.querySelector('.sa-preview-box');
            if (fileDisplay) fileDisplay.style.display = 'none';
            if (previewBox) previewBox.innerHTML = '<i class="mdi mdi-image-outline text-muted fs-4"></i>';

            syncRoleFields(f);
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        document.querySelectorAll('[data-edit-user]').forEach((btn) => {
            btn.addEventListener('click', () => openEditUserModal(btn.dataset));
        });

        @if ($editingUser)
            document.addEventListener('DOMContentLoaded', () => openEditUserModal({
                action: @json(route('super_admin.users.update', $editingUser)),
                username: @json(old('username', $editingUser->username)),
                name: @json(old('name', $editingUser->name)),
                role: @json(old('role', $editingUser->role)),
                active: @json((string) old('is_active', $editingUser->is_active ? '1' : '0')),
                municipality: @json((string) old('municipality_id', $editingUser->municipality_id)),
                agency: @json((string) old('gov_agency_id', $editingUser->gov_agency_id)),
            }));
        @elseif ($errors->any() && ! $errors->has('account_lifecycle') && ! $errors->has('is_active'))
            document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('addUserModal')).show());
        @endif
    </script>
@endpush
