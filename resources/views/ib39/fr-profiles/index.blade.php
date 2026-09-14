@extends('layouts.skydash-v')
@section('title', 'FR Profiles')
@section('heading', 'Surfaced FR Profiles')

@push('styles')
<style>
    .fr-list-page {
        --navy: #1e1b4b;
        --border: #e2e8f0;
    }
    .fr-list-hero {
        align-items: center;
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%) !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        border-radius: 14px !important;
        color: #fff !important;
        display: flex;
        justify-content: space-between;
        overflow: hidden !important;
        padding: 1.25rem 1.75rem !important;
        position: relative !important;
        margin-bottom: 1.25rem !important;
        box-shadow: 0 8px 24px -4px rgba(30, 27, 75, 0.25) !important;
    }
    .fr-list-hero::after {
        content: '';
        position: absolute;
        right: -30px;
        bottom: -30px;
        width: 160px;
        height: 160px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    .hero-title-main {
        align-items: center;
        display: flex;
        gap: 1.15rem;
        min-width: 0;
        position: relative;
        z-index: 1;
    }
    .module-title-icon {
        align-items: center;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 12px;
        color: #67e8f9;
        display: flex;
        flex: 0 0 50px;
        font-size: 1.45rem;
        height: 50px;
        justify-content: center;
        width: 50px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .hero-content {
        position: relative;
        z-index: 1;
    }
    .hero-eyebrow {
        color: #f59e0b;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        margin-bottom: 0.2rem;
    }
    .fr-list-hero h2 {
        color: #ffffff !important;
        font-size: 1.35rem !important;
        font-weight: 800 !important;
        letter-spacing: -0.01em !important;
        margin-bottom: 0.2rem !important;
        line-height: 1.25 !important;
    }
    .fr-list-hero p {
        color: #c7d2fe !important;
        font-size: 0.85rem !important;
        margin-bottom: 0 !important;
        font-weight: 500 !important;
    }
    .register-button {
        align-items: center;
        background: #ffffff !important;
        border: none !important;
        border-radius: 9px !important;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15) !important;
        color: #1e1b4b !important;
        display: inline-flex;
        font-size: 0.84rem !important;
        font-weight: 700 !important;
        min-height: 40px !important;
        padding: 0.5rem 1.15rem !important;
        gap: 0.45rem !important;
        position: relative;
        text-decoration: none !important;
        transition: all 0.2s ease !important;
        z-index: 1;
    }
    .register-button i {
        color: #4338ca !important;
        font-size: 1.15rem !important;
    }
    .register-button:hover {
        background: #f8fafc !important;
        color: #1e1b4b !important;
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2) !important;
    }
    .list-card {
        background: #ffffff;
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    .filter-bar {
        align-items: flex-end;
        background: #fafbfc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        flex-wrap: wrap;
        gap: 0.85rem;
        padding: 1.25rem 1.5rem;
    }
    .filter-field {
        min-width: 160px;
    }
    .filter-search {
        flex: 1 1 260px;
    }
    .filter-field label {
        color: #64748b;
        display: block;
        font-size: 0.6875rem;
        font-weight: 700;
        margin-bottom: 0.35rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .filter-actions {
        display: flex;
        gap: 0.5rem;
    }
    .form-control {
        border-color: #cbd5e1;
        border-radius: 10px;
        font-size: 0.8125rem;
        padding: 0.55rem 0.85rem;
        height: auto;
    }
    .form-control:focus {
        border-color: #4338ca;
        box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.12);
    }
    .records-table {
        margin: 0;
    }
    .records-table thead th {
        background: #f8fafc;
        border: 0;
        border-bottom: 1px solid #e2e8f0;
        color: #475569;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        padding: 0.95rem 1rem;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .records-table tbody tr {
        transition: background-color 0.15s ease;
    }
    .records-table tbody tr:hover {
        background-color: #f8fafc;
    }
    .records-table tbody td {
        border-color: #f1f5f9;
        color: #334155;
        font-size: 0.8125rem;
        padding: 0.95rem 1rem;
        vertical-align: middle;
    }
    .reference {
        color: #4338ca;
        font-weight: 700;
        font-family: monospace;
        font-size: 0.85rem;
    }
    .record-name {
        color: #0f172a;
        font-weight: 700;
    }
    .area-text {
        min-width: 190px;
        color: #64748b;
    }
    .badge-readonly {
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        font-size: 0.6875rem;
        font-weight: 700;
        padding: 0.25rem 0.65rem;
        letter-spacing: 0.03em;
    }
    .badge-yes {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    .badge-no {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }
    .badge-new {
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #c7d2fe;
    }
    .list-footer {
        align-items: center;
        border-top: 1px solid #e2e8f0;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: space-between;
        padding: 1rem 1.5rem;
        background: #fafbfc;
    }
    .pagination-summary {
        color: #64748b;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .empty-state {
        color: #64748b;
        padding: 4rem 1.5rem;
        text-align: center;
    }
    .empty-state i {
        color: #94a3b8;
        display: block;
        font-size: 2.75rem;
        margin-bottom: 0.75rem;
    }
    .empty-state strong {
        color: #0f172a;
        display: block;
        font-size: 0.95rem;
        margin-bottom: 0.35rem;
    }
    @media(max-width:767px){
        .fr-list-hero {
            align-items: flex-start;
            flex-direction: column;
            gap: 1.25rem;
            padding: 1.25rem;
        }
        .filter-field, .filter-search {
            flex: 1 1 100%;
            min-width: 100%;
        }
        .filter-actions {
            width: 100%;
        }
        .filter-actions .btn {
            flex: 1;
        }
        .list-footer {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>
@endpush

@section('content')
<div class="fr-list-page">
    <header class="fr-list-hero mb-4">
        <div class="hero-title-main">
            <span class="module-title-icon"><i class="mdi mdi-account-group-outline" aria-hidden="true"></i></span>
            <div class="hero-content">
                <div class="hero-eyebrow">Battalion Registry · 39th Infantry Battalion</div>
                <h2 class="mb-1">Surfaced FR Profiles</h2>
                <p class="mb-0">Authorized register of standalone surfaced former rebel records.</p>
            </div>
        </div>
        <a href="{{ route('ib39.fr-profiles.create') }}" class="register-button"><i class="mdi mdi-account-plus"></i>Record Surfaced FR</a>
    </header>

    <section class="card list-card" aria-label="Surfaced FR profiles">
        <form method="GET" action="{{ route('ib39.fr-profiles.index') }}" class="filter-bar">
            <div class="filter-field filter-search">
                <label for="search">Reference or name</label>
                <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" class="form-control" placeholder="Search reference, first name, or last name" autocomplete="off">
            </div>
            <div class="filter-field">
                <label for="category">FR category</label>
                <select id="category" name="category" class="form-control">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected(($filters['category'] ?? null) === $category->value)>{{ $category->value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="municipality_id">Municipality</label>
                <select id="municipality_id" name="municipality_id" class="form-control">
                    <option value="">All municipalities</option>
                    @foreach ($municipalities as $municipality)
                        <option value="{{ $municipality->id }}" @selected((string) ($filters['municipality_id'] ?? '') === (string) $municipality->id)>{{ $municipality->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="possessed_firearms">Firearms</label>
                <select id="possessed_firearms" name="possessed_firearms" class="form-control">
                    <option value="">All records</option>
                    <option value="1" @selected(($filters['possessed_firearms'] ?? null) === true)>Yes</option>
                    <option value="0" @selected(array_key_exists('possessed_firearms', $filters) && $filters['possessed_firearms'] === false)>No</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary font-weight-bold px-3 py-2 rounded-lg" style="background-color: #4338ca; border-color: #4338ca;">Apply Filters</button>
                <a href="{{ route('ib39.fr-profiles.index') }}" class="btn btn-light font-weight-bold px-3 py-2 rounded-lg border">Reset Filters</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table records-table">
                <caption class="sr-only">Authorized surfaced former rebel profiles</caption>
                <thead>
                    <tr>
                        <th>FR Reference Number</th>
                        <th>Name</th>
                        <th>FR Category</th>
                        <th>Area of Surfacing</th>
                        <th>Date of Surfacing</th>
                        <th>Firearms Indicator</th>
                        <th>Overall Case Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td><span class="reference">{{ $record->reference_number }}</span></td>
                        <td><span class="record-name">{{ $record->display_name }}</span></td>
                        <td><span class="font-weight-medium text-dark">{{ $record->category->value }}</span></td>
                        <td class="area-text">{{ collect([$record->barangay?->name, $record->municipality->name, $record->province])->filter()->join(', ') }}</td>
                        <td><span class="text-secondary">{{ $record->surfaced_at->format('M d, Y') }}</span></td>
                        <td><span class="badge-readonly {{ $record->possessed_firearms ? 'badge-yes' : 'badge-no' }}">{{ $record->possessed_firearms ? 'Yes' : 'No' }}</span></td>
                        <td><span class="badge-readonly badge-new">{{ $record->overall_case_status }}</span></td>
                        <td><a href="{{ route('ib39.fr-profiles.show', $record) }}" class="btn btn-sm btn-outline-primary rounded-pill font-weight-bold px-3">View Profile</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="mdi mdi-account-search" aria-hidden="true"></i>
                                <strong>{{ $hasActiveFilters ? 'No matching FR profiles found' : 'No surfaced FR profiles recorded' }}</strong>
                                <span>{{ $hasActiveFilters ? 'Adjust or reset the filters to view other records.' : 'Use Record Surfaced FR to create the first authorized record.' }}</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->isNotEmpty())
            <footer class="list-footer">
                <div class="pagination-summary">Showing {{ number_format($records->firstItem()) }}–{{ number_format($records->lastItem()) }} of {{ number_format($records->total()) }} records</div>
                @if ($records->hasPages())<div>{{ $records->onEachSide(1)->links() }}</div>@endif
            </footer>
        @endif
    </section>

    {{-- Success Modal for Record Surfaced FR --}}
    @php
        $modalData = session('surfaced_fr_modal');
    @endphp
    @if ($modalData || (session('success') && str_contains(session('success'), 'was recorded successfully')))
        <div class="modal fade" id="recordSuccessModal" tabindex="-1" aria-labelledby="recordSuccessModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
                    <div class="modal-body p-4 text-center">
                        <div class="mb-3 mx-auto d-flex align-items-center justify-content-center"
                             style="width: 72px; height: 72px; border-radius: 50%; background: #ecfdf5; color: #10b981; font-size: 2.5rem; box-shadow: 0 0 0 8px rgba(16, 185, 129, 0.1);">
                            <i class="mdi mdi-check-circle-outline"></i>
                        </div>
                        <h3 class="h4 font-weight-bold text-dark mb-1" id="recordSuccessModalLabel">Surfaced FR Recorded!</h3>
                        <p class="text-muted small mb-3">
                            The former rebel profile has been successfully registered and assigned a unique reference code.
                        </p>

                        @if ($modalData)
                            <div class="p-3 mb-3 text-start" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px;">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                    <span class="text-muted small font-weight-bold text-uppercase">Reference Code</span>
                                    <span class="badge font-monospace" style="background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; font-size: 0.9rem; padding: 0.35rem 0.65rem;">
                                        {{ $modalData['reference_number'] }}
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small">Name</span>
                                    <span class="font-weight-bold text-dark small">{{ $modalData['name'] }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small">Category</span>
                                    <span class="text-dark small font-weight-medium">{{ $modalData['category'] }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small">Area</span>
                                    <span class="text-dark small text-truncate" style="max-width: 220px;">{{ $modalData['area'] }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small">Firearms</span>
                                    <span class="badge {{ $modalData['possessed_firearms'] ? 'bg-warning text-dark' : 'bg-light text-secondary border' }}" style="font-size: 0.72rem;">
                                        {{ $modalData['possessed_firearms'] ? 'Yes, Possessed' : 'No Firearms' }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-success py-2 px-3 small mb-3">
                                {{ session('success') }}
                            </div>
                        @endif

                        <div class="d-grid gap-2">
                            @if ($modalData && !empty($modalData['show_url']))
                                <a href="{{ $modalData['show_url'] }}" class="btn btn-primary font-weight-bold py-2" style="background: #4338ca; border-color: #4338ca; border-radius: 10px;">
                                    <i class="mdi mdi-account-card-details-outline me-1"></i> View FR Profile
                                </a>
                            @endif
                            <button type="button" class="btn btn-light border font-weight-bold py-2 text-muted" data-bs-dismiss="modal" style="border-radius: 10px;">
                                Dismiss & View List
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    @if ($modalData || (session('success') && str_contains(session('success'), 'was recorded successfully')))
        var successModalEl = document.getElementById('recordSuccessModal');
        if (successModalEl) {
            if (window.bootstrap && bootstrap.Modal) {
                var successModal = new bootstrap.Modal(successModalEl);
                successModal.show();
            } else if (window.$ && $.fn.modal) {
                $('#recordSuccessModal').modal('show');
            }
        }
    @endif
})();
</script>
@endpush

