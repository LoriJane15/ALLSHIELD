@props([
    'record',
    'informationHeading' => 'Approved Surfacing Information',
    'showStatusTiles' => true,
])

@once
@push('styles')
<style>
    .fr-profile-container {
        max-width: 1240px;
        margin: 0 auto;
        padding-bottom: 2rem;
    }
    .module-nav-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem 1.25rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    }
    .module-back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        color: #475569;
        font-size: 0.8125rem;
        font-weight: 700;
        padding: 0.4rem 0.85rem;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        text-decoration: none !important;
        transition: all 0.2s ease;
    }
    .module-back-link:hover {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #4338ca;
        transform: translateX(-2px);
    }
    .module-back-link i {
        font-size: 1.1rem;
    }
    .module-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.8125rem;
        color: #64748b;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .module-breadcrumb a {
        color: #64748b;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.15s ease;
    }
    .module-breadcrumb a:hover {
        color: #4338ca;
    }
    .module-breadcrumb .active {
        color: #0f172a;
        font-weight: 700;
    }
    .module-breadcrumb .separator {
        color: #cbd5e1;
        font-size: 0.75rem;
    }

    /* Profile Hero */
    .profile-hero {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
        border-radius: 16px;
        color: #fff;
        padding: 1.35rem 1.75rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 10px 25px -5px rgba(30, 27, 75, 0.25);
    }
    .hero-top-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .hero-main {
        display: flex;
        align-items: center;
        gap: 1.15rem;
        min-width: 0;
    }
    .hero-icon-box {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 52px;
        height: 52px;
        flex: 0 0 52px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: #67e8f9;
        font-size: 1.55rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .hero-eyebrow {
        color: #f59e0b;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        margin-bottom: 0.2rem;
    }
    .profile-hero h1 {
        color: #fff;
        font-size: 1.4rem;
        font-weight: 800;
        margin-bottom: 0.15rem;
        line-height: 1.2;
    }
    .profile-hero p {
        color: #c7d2fe;
        font-size: 0.8125rem;
        margin: 0;
    }
    .hero-badges {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
    }
    .hero-ref-badge, .hero-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 0.45rem 0.95rem;
        color: #fff;
        font-size: 0.8125rem;
        font-weight: 750;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .hero-ref-badge {
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(4px);
    }
    .hero-status-pill {
        background: #4338ca;
        border-color: rgba(255, 255, 255, 0.3);
    }

    /* Cards */
    .profile-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    .profile-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.35rem;
        border-bottom: 1px solid #f1f5f9;
        background: #fafbfc;
    }
    .profile-card-header h2, .profile-card-header h3 {
        color: #0f172a;
        font-size: 0.925rem;
        font-weight: 800;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .profile-card-header i {
        color: #4338ca;
        font-size: 1.2rem;
    }
    .profile-card-body {
        padding: 1.25rem;
    }

    /* Info Tile Grid */
    .info-tile-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
    }
    .info-tile {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.8rem 1rem;
        transition: all 0.2s ease;
    }
    .info-tile:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }
    .info-tile.full-width {
        grid-column: 1 / -1;
    }
    .info-tile-label {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        color: #64748b;
        font-size: 0.65rem;
        font-weight: 750;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }
    .info-tile-value {
        color: #0f172a;
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1.4;
        margin: 0;
        overflow-wrap: anywhere;
    }
    .badge-pill-status {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.72rem;
        font-weight: 750;
    }
    .badge-pill-yes {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }
    .badge-pill-no {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #475569;
    }
    .badge-pill-cdr {
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        color: #4338ca;
    }
    .badge-pill-case {
        background: #e0f2fe;
        border: 1px solid #bae6fd;
        color: #0369a1;
    }

    /* Workflow Tiles */
    .workflow-tile-list {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
    }
    .workflow-tile {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.95rem 1.15rem;
        transition: all 0.2s ease;
        display: block;
    }
    .workflow-tile:hover {
        background: #ffffff;
        border-color: #4338ca;
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(67, 56, 202, 0.08);
    }
    .workflow-tile strong, .workflow-tile span {
        display: block;
    }
    .workflow-tile strong {
        font-size: 0.85rem;
        font-weight: 750;
        color: #0f172a;
        margin-bottom: 0.25rem;
    }
    .workflow-tile span {
        font-size: 0.75rem;
        color: #64748b;
    }
    .profile-footer-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 1.25rem;
        flex-wrap: wrap;
    }
    .btn-action-primary, .btn-action-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        font-size: 0.8125rem;
        font-weight: 750;
        padding: 0.6rem 1.25rem;
        border-radius: 10px;
        text-decoration: none !important;
        transition: all 0.2s ease;
    }
    .btn-action-primary {
        background: #4338ca;
        border: 1px solid #4338ca;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(67, 56, 202, 0.2);
    }
    .btn-action-primary:hover {
        background: #312e81;
        border-color: #312e81;
        color: #ffffff;
        transform: translateY(-1px);
    }
    .btn-action-secondary {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
    }
    .btn-action-secondary:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
    }
    .history-card-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.95rem 1.15rem;
        display: flex;
        gap: 0.85rem;
        align-items: center;
    }
    .history-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        border-radius: 10px;
        background: #eef2ff;
        color: #4338ca;
        font-size: 1.15rem;
    }
    @media(max-width:991px){
        .info-tile-grid, .workflow-tile-list {
            grid-template-columns: 1fr;
        }
    }
    @media(max-width:767px){
        .profile-hero {
            padding: 1.15rem;
        }
        .hero-top-row {
            flex-direction: column;
            align-items: flex-start;
        }
        .profile-card-body {
            padding: 1rem;
        }
        .profile-footer-actions {
            flex-direction: column;
        }
        .btn-action-primary, .btn-action-secondary {
            width: 100%;
        }
    }
</style>
@endpush
@endonce

<header class="profile-hero">
    <div class="hero-top-row">
        <div class="hero-main">
            <div class="hero-icon-box">
                <i class="mdi mdi-account-card-details" aria-hidden="true"></i>
            </div>
            <div>
                <div class="hero-eyebrow">Surfaced FR Profile</div>
                <h1>{{ $record->display_name }}</h1>
                <p>Read-only surfaced former rebel profile</p>
            </div>
        </div>
        <div class="hero-badges">
            <div class="hero-ref-badge">
                <i class="mdi mdi-shield-check"></i>
                <span>{{ $record->reference_number }}</span>
            </div>
            @if($showStatusTiles)
                <div class="hero-status-pill">
                    <span>{{ $record->overall_case_status }}</span>
                </div>
            @endif
        </div>
    </div>
</header>

<section class="profile-card mb-4" aria-labelledby="surfacing-details-heading">
    <div class="profile-card-header">
        <h2 id="surfacing-details-heading">
            <i class="mdi mdi-account-card-details text-primary"></i>
            <span>{{ $informationHeading }}</span>
        </h2>
    </div>
    <div class="profile-card-body">
        <dl class="info-tile-grid mb-0">
            @foreach([
                ['First name', $record->first_name],
                ['Last name', $record->last_name],
                ['FR category', $record->category->value.($record->other_category_specification ? ' — '.$record->other_category_specification : '')],
                ['Date of surfacing', $record->surfaced_at->format('F d, Y')],
                ['Province', $record->province],
                ['Municipality', $record->municipality?->name ?? 'Not provided'],
                ['Barangay', $record->barangay?->name ?? 'Not provided'],
                ['Specific location', $record->specific_location ?: 'Not provided'],
            ] as [$label, $value])
                <div class="info-tile">
                    <dt class="info-tile-label">{{ $label }}</dt>
                    <dd class="info-tile-value">{{ $value }}</dd>
                </div>
            @endforeach
            <div class="info-tile">
                <dt class="info-tile-label">Possessed firearms</dt>
                <dd class="info-tile-value">
                    <span class="badge-pill-status {{ $record->possessed_firearms ? 'badge-pill-yes' : 'badge-pill-no' }}">
                        {{ $record->possessed_firearms ? 'Yes' : 'No' }}
                    </span>
                </dd>
            </div>
            @if($showStatusTiles)
                <div class="info-tile">
                    <dt class="info-tile-label">CDR status</dt>
                    <dd class="info-tile-value">
                        <span class="badge-pill-status badge-pill-cdr">{{ $record->cdr_status }}</span>
                    </dd>
                </div>
                <div class="info-tile">
                    <dt class="info-tile-label">Overall case status</dt>
                    <dd class="info-tile-value">
                        <span class="badge-pill-status badge-pill-case">{{ $record->overall_case_status }}</span>
                    </dd>
                </div>
            @endif
            <div class="info-tile">
                <dt class="info-tile-label">Created date</dt>
                <dd class="info-tile-value">{{ $record->created_at->format('F d, Y · h:i A') }}</dd>
            </div>
            <div class="info-tile full-width">
                <dt class="info-tile-label">Initial remarks</dt>
                <dd class="info-tile-value">{{ $record->initial_remarks ?: 'No initial remarks recorded.' }}</dd>
            </div>
        </dl>
    </div>
</section>

@if($record->cancellation)
    <div class="alert alert-warning" role="status">
        <strong>Cancelled {{ $record->cancellation->cancelled_at->format('F d, Y h:i A') }}</strong>
        <div>Previous status: {{ $record->cancellation->previous_overall_status }}</div>
        <div>Reason: {{ $record->cancellation->reason }}</div>
    </div>
@endif

