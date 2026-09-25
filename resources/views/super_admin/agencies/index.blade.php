@extends('layouts.skydash-v')
@section('title', 'Government Agencies')
@section('heading', 'Government Agencies')

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

.sa-agency-logo-box {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}

.sa-agency-logo-img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.sa-logo-picker {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
    gap: 0.65rem;
    max-height: 248px;
    overflow-y: auto;
    padding: 0.65rem;
    border: 1.5px solid var(--sa-slate-200);
    border-radius: 12px;
    background: var(--sa-slate-50);
}

.sa-logo-option {
    position: relative;
    min-width: 0;
    padding: 0.55rem;
    border: 2px solid transparent;
    border-radius: 10px;
    background: #ffffff;
    color: var(--sa-slate-700);
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.sa-logo-option:hover,
.sa-logo-option:focus-visible {
    border-color: #a5b4fc;
    box-shadow: 0 3px 10px rgba(79, 70, 229, 0.12);
    outline: none;
    transform: translateY(-1px);
}

.sa-logo-option.is-selected {
    border-color: var(--sa-primary);
    background: #eef2ff;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.12);
}

.sa-logo-option-image {
    height: 62px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sa-logo-option-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.sa-logo-option-fallback {
    color: var(--sa-slate-500);
    font-size: 1.5rem;
}

.sa-logo-option-name {
    display: block;
    margin-top: 0.4rem;
    overflow: hidden;
    color: var(--sa-slate-500);
    font-size: 0.68rem;
    line-height: 1.2;
    text-align: center;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sa-logo-option-check {
    position: absolute;
    top: 0.25rem;
    right: 0.3rem;
    color: var(--sa-primary);
    font-size: 1rem;
    opacity: 0;
}

.sa-logo-option.is-selected .sa-logo-option-check {
    opacity: 1;
}

.sa-acronym-pill {
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: #2563eb;
    background: #eff6ff;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    border: 1px solid #bfdbfe;
    display: inline-block;
}

.sa-icon-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    border: 1px solid transparent !important;
    outline: none !important;
    cursor: pointer;
    text-decoration: none !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 0 !important;
}

.sa-icon-btn-edit {
    background-color: #eff6ff !important;
    color: #2563eb !important;
    border-color: #bfdbfe !important;
}

.sa-icon-btn-edit:hover {
    background-color: #2563eb !important;
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

.sa-icon-btn-delete {
    background-color: #fff1f2 !important;
    color: #e11d48 !important;
    border-color: #fecdd3 !important;
}

.sa-icon-btn-delete:hover {
    background-color: #e11d48 !important;
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25);
}

/* Clean Modal Styles */
.clean-modal-dialog {
    max-width: 580px;
    margin: 1.75rem auto;
}

.clean-modal-content {
    border-radius: 16px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.15) !important;
    overflow: hidden;
    background: #ffffff;
}

.clean-modal-header {
    background: #ffffff !important;
    border-bottom: 1px solid #f1f5f9 !important;
    padding: 1.5rem 1.75rem 1.25rem 1.75rem !important;
}

.clean-modal-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #eef2ff;
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.clean-modal-title {
    font-size: 1.15rem !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    margin-bottom: 0.15rem !important;
    letter-spacing: -0.01em;
}

.clean-modal-subtitle {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 0;
}

.clean-modal-body {
    padding: 1.5rem 1.75rem !important;
    background: #ffffff;
}

.clean-modal-footer {
    padding: 1.2rem 1.75rem !important;
    background: #f8fafc !important;
    border-top: 1px solid #f1f5f9 !important;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
}

.modern-input {
    border-radius: 10px !important;
    border: 1.5px solid #e2e8f0 !important;
    padding: 0.65rem 0.95rem !important;
    font-size: 0.88rem !important;
    color: #1e293b !important;
    background-color: #ffffff !important;
    transition: all 0.2s ease !important;
    height: auto !important;
}

.modern-input:focus {
    border-color: #4f46e5 !important;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12) !important;
    background-color: #ffffff !important;
    outline: none !important;
}
</style>
@endpush

@section('content')
<div class="sa-dashboard-wrapper">
    @if ($errors->has('agency_deletion'))
        <div class="alert alert-danger" role="alert">
            {{ $errors->first('agency_deletion') }}
        </div>
    @endif

    {{-- Executive Header Banner --}}
    <div class="sa-hero-banner mb-4">
        <div class="sa-hero-content">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="sa-hero-badge">
                        <i class="mdi mdi-bank"></i>
                        <span>Inter-Agency Stakeholder Directory</span>
                    </div>
                    <h1 class="sa-hero-title mb-1">Government Agencies</h1>
                    <p class="sa-hero-subtitle mb-0">
                        Add, configure, and maintain partner government agencies, acronyms, and organizational profiles.
                    </p>
                </div>
                <div>
                    <button type="button" class="sa-hero-btn sa-hero-btn-primary" data-bs-toggle="modal" data-bs-target="#addAgencyModal">
                        <i class="mdi mdi-plus-circle-outline"></i>
                        <span>Add Government Agency</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Top Stats (2-Grid) --}}
    <div class="sa-stat-grid mb-4" style="grid-template-columns: repeat(2, 1fr);">
        <div class="sa-stat-card">
            <div class="sa-stat-header">
                <div class="sa-stat-icon-wrap sa-icon-primary">
                    <i class="mdi mdi-bank"></i>
                </div>
                <span class="sa-stat-badge sa-badge-primary">Directory</span>
            </div>
            <div class="sa-stat-title">Registered Agencies</div>
            <div class="sa-stat-number">{{ $stats['total'] ?? $agencies->total() }}</div>
            <div class="sa-stat-subtitle">Official Partner Agencies &amp; Units</div>
            <i class="mdi mdi-bank sa-stat-watermark"></i>
        </div>

        <div class="sa-stat-card">
            <div class="sa-stat-header">
                <div class="sa-stat-icon-wrap sa-icon-amber">
                    <i class="mdi mdi-account-group"></i>
                </div>
                <span class="sa-stat-badge sa-badge-amber">Active Focal</span>
            </div>
            <div class="sa-stat-title">Active Agency Users</div>
            <div class="sa-stat-number" style="color: var(--sa-amber);">{{ $stats['active_users'] ?? 0 }}</div>
            <div class="sa-stat-subtitle">Active Focal Person &amp; Operator Accounts</div>
            <i class="mdi mdi-account-group sa-stat-watermark"></i>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="sa-card">
        <div class="sa-card-header">
            <div class="sa-card-header-left">
                <div class="sa-card-header-icon">
                    <i class="mdi mdi-domain"></i>
                </div>
                <div>
                    <h3 class="sa-card-title">Agency Registry</h3>
                    <p class="sa-card-subtitle">Showing {{ $agencies->firstItem() ?? 0 }}–{{ $agencies->lastItem() ?? 0 }} of {{ $agencies->total() }} registered agencies</p>
                </div>
            </div>
        </div>

        <div class="sa-card-body">
            {{-- Toolbar: Compact Search Form --}}
            <div class="d-flex justify-content-start mb-4">
                <form method="GET" action="{{ route('super_admin.agencies.index') }}" class="d-flex align-items-center gap-2" style="width: 100%; max-width: 440px;">
                    <div class="input-group" style="border-radius: 10px; overflow: hidden;">
                        <span class="input-group-text bg-white border-end-0" style="border: 1.5px solid #e2e8f0; border-right: none; color: #94a3b8; padding-left: 1rem;">
                            <i class="mdi mdi-magnify fs-5"></i>
                        </span>
                        <input name="search" value="{{ request('search') }}" placeholder="Search acronym or agency name..." class="form-control modern-input border-start-0 border-end-0" style="border-radius: 0 !important; border-left: none !important; border-right: none !important;">
                        <button type="submit" class="btn btn-primary px-3" style="background: #4f46e5; border: none; font-weight: 600; font-size: 0.85rem; border-radius: 0 !important;">
                            Search
                        </button>
                    </div>
                    @if(request('search'))
                        <a href="{{ route('super_admin.agencies.index') }}" class="btn btn-light px-3" style="border-radius: 10px; border: 1.5px solid #e2e8f0; font-weight: 600; font-size: 0.85rem; color: #64748b;" title="Clear Search">
                            <i class="mdi mdi-close"></i>
                        </a>
                    @endif
                </form>
            </div>

            {{-- Table --}}
            <div class="table-responsive">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th style="min-width: 320px;">Agency Profile</th>
                            <th>Acronym</th>
                            <th>Assigned Personnel</th>
                            <th class="text-end" style="width: 280px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($agencies as $a)
                            <tr>
                                {{-- Agency Profile (Logo + Official Name + Subtitle) --}}
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="sa-agency-logo-box">
                                            @if ($a->profile)
                                                <img src="{{ asset('assets/logoAgency/'.$a->profile) }}"
                                                     onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'mdi mdi-bank text-muted\'></i>';"
                                                     alt="{{ $a->acronym }}" class="sa-agency-logo-img">
                                            @else
                                                <i class="mdi mdi-bank text-muted" style="font-size: 1.35rem;"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark" style="font-size: 0.92rem; line-height: 1.25;">{{ $a->name }}</div>
                                            <small class="text-muted" style="font-size: 0.76rem;">Partner Department &amp; Bureau</small>
                                        </div>
                                    </div>
                                </td>

                                {{-- Acronym Badge --}}
                                <td>
                                    <span class="sa-acronym-pill">{{ $a->acronym }}</span>
                                </td>

                                {{-- Active Personnel --}}
                                <td>
                                    <span class="d-inline-flex align-items-center gap-1.5 px-2.5 py-1 rounded-pill" style="background: #f1f5f9; color: #334155; font-size: 0.8rem; font-weight: 600; border: 1px solid #e2e8f0;">
                                        <i class="mdi mdi-account-multiple text-primary me-1"></i>
                                        <span>{{ $a->active_users_count }} Active {{ Str::plural('user', $a->active_users_count) }}</span>
                                    </span>
                                </td>

                                {{-- Action Buttons --}}
                                <td>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <button type="button" class="sa-icon-btn sa-icon-btn-edit" title="Edit Agency"
                                                data-edit-agency
                                                data-name="{{ $a->name }}"
                                                data-acronym="{{ $a->acronym }}"
                                                data-profile="{{ $a->profile }}"
                                                data-action="{{ route('super_admin.agencies.update', $a) }}">
                                            <i class="mdi mdi-pencil"></i>
                                        </button>

                                        @if (! $a->users_count && ! $a->responses_count && ! $a->implementation_taggings_count)
                                            <form method="POST" action="{{ route('super_admin.agencies.destroy', $a) }}"
                                                  data-confirm="Are you sure you want to delete agency '{{ $a->acronym }}'?"
                                                  data-confirm-title="Confirm Agency Deletion"
                                                  data-confirm-action="Delete Agency"
                                                  style="display:inline;">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="sa-icon-btn sa-icon-btn-delete" title="Delete Agency">
                                                    <i class="mdi mdi-delete"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="small text-muted text-end" style="max-width: 210px; line-height: 1.25;">
                                                Agency cannot be deleted while linked accounts or implementation records exist.
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="sa-empty-state py-5">
                                        <div class="sa-empty-icon">
                                            <i class="mdi mdi-domain"></i>
                                        </div>
                                        <div class="sa-empty-title">No Government Agencies Found</div>
                                        <p class="sa-empty-subtitle">No agency records match your current search query. Try typing another keyword or create a new agency.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($agencies->hasPages())
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 pt-3 border-top">
                    <div class="text-muted small">
                        Showing {{ $agencies->firstItem() }} to {{ $agencies->lastItem() }} of {{ $agencies->total() }} agencies
                    </div>
                    <div>
                        {{ $agencies->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Add Agency Modal --}}
    <div class="modal fade" id="addAgencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered clean-modal-dialog">
            <div class="modal-content clean-modal-content">
                <div class="clean-modal-header d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center gap-3">
                        <div class="clean-modal-icon">
                            <i class="mdi mdi-plus-circle-outline"></i>
                        </div>
                        <div>
                            <h5 class="clean-modal-title">Add Government Agency</h5>
                            <p class="clean-modal-subtitle">Register a partner government department or agency</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('super_admin.agencies.store') }}" enctype="multipart/form-data" data-agency-form>
                    @csrf
                    <div class="clean-modal-body">
                        @include('super_admin.agencies._fields')
                    </div>
                    <div class="clean-modal-footer">
                        <button type="button" data-bs-dismiss="modal" class="btn btn-light px-4 py-2" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; border: 1.5px solid #e2e8f0; color: #475569;">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; background: #4f46e5; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);">
                            <i class="mdi mdi-check me-1"></i> Add Agency
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Agency Modal --}}
    <div class="modal fade" id="editAgencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered clean-modal-dialog">
            <div class="modal-content clean-modal-content">
                <div class="clean-modal-header d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center gap-3">
                        <div class="clean-modal-icon" style="background: #eff6ff; color: #2563eb;">
                            <i class="mdi mdi-pencil-box-outline"></i>
                        </div>
                        <div>
                            <h5 class="clean-modal-title">Edit Government Agency</h5>
                            <p class="clean-modal-subtitle">Update agency acronym, name, or profile logo</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" data-agency-form data-edit-agency-form>
                    @csrf @method('PUT')
                    <div class="clean-modal-body">
                        @include('super_admin.agencies._fields')
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
        const agencyLogoBaseUrl = @json(rtrim(asset('assets/logoAgency'), '/'));

        function showAgencyLogoPreview(form, source, alt = 'Selected agency logo') {
            const preview = form.querySelector('[data-agency-logo-preview]');
            preview.replaceChildren();

            if (source) {
                const image = document.createElement('img');
                image.src = source;
                image.alt = alt;
                image.className = 'sa-agency-logo-img';
                image.addEventListener('error', () => {
                    preview.innerHTML = '<i class="mdi mdi-bank text-muted" style="font-size: 1.35rem;"></i>';
                }, { once: true });
                preview.appendChild(image);
                return;
            }

            preview.innerHTML = '<i class="mdi mdi-bank text-muted" style="font-size: 1.35rem;"></i>';
        }

        function existingAgencyLogoUrl(filename) {
            return filename ? `${agencyLogoBaseUrl}/${encodeURIComponent(filename)}` : '';
        }

        function selectExistingAgencyLogo(form, filename, previewSource) {
            const value = form.querySelector('[data-agency-logo-value]');
            const upload = form.querySelector('[data-agency-logo-upload]');
            let selectedOption = null;

            form.querySelectorAll('[data-agency-logo-option]').forEach((option) => {
                const isSelected = option.dataset.logo === filename;
                option.classList.toggle('is-selected', isSelected);
                option.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                if (isSelected) selectedOption = option;
            });

            value.value = selectedOption ? selectedOption.dataset.logo : '';
            if (selectedOption) upload.value = '';

            const source = previewSource === undefined
                ? existingAgencyLogoUrl(value.value)
                : previewSource;
            showAgencyLogoPreview(form, source);
        }

        document.querySelectorAll('[data-agency-form]').forEach((form) => {
            const value = form.querySelector('[data-agency-logo-value]');
            const upload = form.querySelector('[data-agency-logo-upload]');

            form.querySelectorAll('[data-agency-logo-option]').forEach((option) => {
                option.addEventListener('click', () => {
                    selectExistingAgencyLogo(form, option.dataset.logo);
                });
            });

            upload.addEventListener('change', () => {
                const file = upload.files[0];

                if (! file) {
                    showAgencyLogoPreview(form, existingAgencyLogoUrl(value.value));
                    return;
                }

                selectExistingAgencyLogo(form, '');
                const reader = new FileReader();
                reader.addEventListener('load', () => showAgencyLogoPreview(form, reader.result));
                reader.readAsDataURL(file);
            });
        });

        document.querySelectorAll('[data-edit-agency]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const f = document.querySelector('[data-edit-agency-form]');
                f.action = btn.dataset.action;
                f.querySelector('[name=acronym]').value = btn.dataset.acronym;
                f.querySelector('[name=name]').value = btn.dataset.name;
                f.querySelector('[data-agency-logo-upload]').value = '';
                selectExistingAgencyLogo(f, btn.dataset.profile || '', existingAgencyLogoUrl(btn.dataset.profile));
                new bootstrap.Modal(document.getElementById('editAgencyModal')).show();
            });
        });
        @if ($errors->any() && ! $errors->has('agency_deletion'))
            document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('addAgencyModal')).show());
        @endif
    </script>
@endpush
