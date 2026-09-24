@extends('layouts.skydash-v')
@section('title', 'FEA Processing')
@section('heading', 'FEA Processing')

@push('styles')
<style>
    .fea-index-container {
        max-width: 100%;
        margin: 0 auto;
        padding-bottom: 2rem;
    }



    /* Standard Unified Executive Hero Banner */
    .fea-hero {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%) !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        border-radius: 14px !important;
        color: #ffffff !important;
        padding: 1.25rem 1.75rem !important;
        margin-bottom: 1.25rem !important;
        box-shadow: 0 8px 24px -4px rgba(30, 27, 75, 0.25) !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1.25rem;
        position: relative !important;
        overflow: hidden !important;
    }
    .fea-hero::after {
        content: '';
        position: absolute;
        right: -30px;
        bottom: -30px;
        width: 160px;
        height: 160px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    .hero-main {
        display: flex;
        align-items: center;
        gap: 1.15rem;
        min-width: 0;
        position: relative;
        z-index: 1;
    }
    .hero-icon-box {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 50px;
        height: 50px;
        flex: 0 0 50px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: #67e8f9;
        font-size: 1.45rem;
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
    .fea-hero h2 {
        color: #ffffff !important;
        font-size: 1.35rem !important;
        font-weight: 800 !important;
        letter-spacing: -0.01em !important;
        margin: 0 0 0.2rem 0 !important;
        line-height: 1.25 !important;
    }
    .fea-hero p {
        font-size: 0.85rem !important;
        color: #c7d2fe !important;
        margin: 0 !important;
        font-weight: 500 !important;
        max-width: 700px;
        line-height: 1.45;
    }
    .hero-stats-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    .hero-stat-card {
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        backdrop-filter: blur(8px);
        border-radius: 9px;
        padding: 0.5rem 0.95rem;
        min-height: 40px;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        color: #ffffff;
    }
    .hero-stat-card i {
        font-size: 1.25rem;
        color: #93c5fd;
    }
    .hero-stat-info .stat-val {
        font-size: 1.15rem;
        font-weight: 800;
        line-height: 1;
    }
    .hero-stat-info .stat-lbl {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #c7d2fe;
        margin-top: 0.2rem;
    }

    /* Workflow Prerequisite Banner */
    .fea-info-strip {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #4338ca;
        border-radius: 10px;
        padding: 0.85rem 1.25rem;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .fea-info-left {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        color: #334155;
        font-size: 0.8125rem;
    }
    .fea-info-left i {
        color: #4338ca;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .fea-info-left strong {
        color: #0f172a;
        font-weight: 750;
    }

    /* Modern Table Card */
    .fea-card {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        background: #ffffff;
        margin-bottom: 1.5rem;
    }
    .fea-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        background: #fafbfc;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    .fea-card-title {
        display: flex;
        align-items: center;
        gap: 0.65rem;
    }
    .fea-card-title h3 {
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 800;
        margin: 0;
    }
    .fea-card-badge {
        background: #eef2ff;
        color: #4338ca;
        font-size: 0.72rem;
        font-weight: 800;
        padding: 0.2rem 0.65rem;
        border-radius: 9999px;
        border: 1px solid #c7d2fe;
    }
    .fea-filter-box {
        position: relative;
        min-width: 240px;
    }
    .fea-filter-box i {
        position: absolute;
        left: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1rem;
    }
    .fea-filter-input {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        color: #0f172a;
        font-size: 0.8125rem;
        padding: 0.45rem 0.85rem 0.45rem 2.2rem;
        width: 100%;
        transition: all 0.2s ease;
    }
    .fea-filter-input:focus {
        border-color: #4338ca;
        box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.12);
        outline: 0;
    }

    /* Table Styling */
    .fea-table {
        margin: 0;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .fea-table thead th {
        background: #f8fafc;
        border: 0;
        border-bottom: 1px solid #e2e8f0;
        color: #475569;
        font-size: 0.7rem;
        font-weight: 750;
        letter-spacing: 0.05em;
        padding: 0.85rem 1rem;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .fea-table tbody tr {
        transition: background-color 0.15s ease;
    }
    .fea-table tbody tr:hover {
        background-color: #f8fafc;
    }
    .fea-table tbody td {
        border-top: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.8125rem;
        padding: 0.85rem 1rem;
        vertical-align: middle;
    }

    .fea-reference-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        color: #3730a3;
        font-weight: 800;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.82rem;
        padding: 0.25rem 0.6rem;
        border-radius: 7px;
        white-space: nowrap;
    }
    .fea-reference-badge i {
        color: #4338ca;
        font-size: 0.9rem;
    }

    .fea-category-tag {
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #1e293b;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0.25rem 0.6rem;
        border-radius: 6px;
        white-space: nowrap;
    }

    .fea-date-cell {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: #475569;
        font-weight: 600;
        font-size: 0.8125rem;
        white-space: nowrap;
    }
    .fea-date-cell i {
        color: #64748b;
        font-size: 0.95rem;
    }

    .fea-area-cell {
        display: flex;
        align-items: flex-start;
        gap: 0.4rem;
        color: #475569;
        font-size: 0.8125rem;
        line-height: 1.35;
        word-break: break-word;
    }
    .fea-area-cell i {
        color: #64748b;
        font-size: 1rem;
        flex-shrink: 0;
        margin-top: 0.05rem;
    }

    .fea-status-pill {
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.72rem;
        font-weight: 750;
        padding: 0.25rem 0.7rem;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }
    .fea-status-pill.pending {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #b45309;
    }
    .fea-status-pill.in-progress {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
    }
    .fea-status-pill.completed {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #047857;
    }

    .fea-actions-col {
        min-width: 220px;
    }
    .btn-fea-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        background: #ffffff;
        border: 1.5px solid #4338ca;
        border-radius: 9px;
        color: #4338ca;
        font-size: 0.8125rem;
        font-weight: 750;
        padding: 0.45rem 1rem;
        text-decoration: none !important;
        transition: all 0.2s ease;
        box-shadow: 0 2px 6px rgba(67, 56, 202, 0.06);
    }
    .btn-fea-action:hover {
        background: #4338ca;
        border-color: #4338ca;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(67, 56, 202, 0.25);
        transform: translateY(-1px);
    }
    .btn-fea-action i {
        font-size: 1rem;
    }

    .fea-lock-notice {
        display: flex;
        align-items: flex-start;
        gap: 0.35rem;
        margin-top: 0.45rem;
        background: #fff1f2;
        border: 1px solid #fecdd3;
        border-radius: 7px;
        padding: 0.35rem 0.55rem;
        color: #9f1239;
        font-size: 0.72rem;
        font-weight: 650;
        line-height: 1.35;
    }
    .fea-lock-notice i {
        color: #e11d48;
        font-size: 0.95rem;
        flex-shrink: 0;
        margin-top: 0.05rem;
    }

    /* Empty State */
    .fea-empty-state {
        padding: 4.5rem 1.5rem;
        text-align: center;
    }
    .empty-icon-circle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #f1f5f9;
        border: 2px dashed #cbd5e1;
        color: #94a3b8;
        font-size: 2.5rem;
        margin-bottom: 1.25rem;
    }
    .fea-empty-state h4 {
        color: #0f172a;
        font-size: 1.1rem;
        font-weight: 800;
        margin-bottom: 0.45rem;
    }
    .fea-empty-state p {
        color: #64748b;
        font-size: 0.84rem;
        max-width: 520px;
        margin: 0 auto;
        line-height: 1.5;
    }

    .fea-footer {
        border-top: 1px solid #e2e8f0;
        padding: 1rem 1.5rem;
        background: #fafbfc;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        border-bottom-left-radius: 16px;
        border-bottom-right-radius: 16px;
    }
</style>
@endpush

@section('content')
<div class="fea-index-container">
    {{-- Standard Unified Hero Banner --}}
    <header class="fea-hero">
        <div class="hero-main">
            <div class="hero-icon-box">
                <i class="mdi mdi-shield" aria-hidden="true"></i>
            </div>
            <div>
                <div class="hero-eyebrow">Armament & Munitions Custody · 39th Infantry Battalion</div>
                <h2>Firearms, Explosives, and Ammunition Processing</h2>
                <p>Preliminary 39th IB queue for surfaced FR records with a recorded firearms indicator of Yes.</p>
            </div>
        </div>
        <div class="hero-stats-group">
            <div class="hero-stat-card">
                <i class="mdi mdi-format-list-bulleted"></i>
                <div class="hero-stat-info">
                    <div class="stat-val">{{ $processings->total() }}</div>
                    <div class="stat-lbl">In Queue</div>
                </div>
            </div>
            <div class="hero-stat-card">
                <i class="mdi mdi-clock-outline"></i>
                <div class="hero-stat-info">
                    <div class="stat-val">{{ $processings->count() }}</div>
                    <div class="stat-lbl">Active Page</div>
                </div>
            </div>
        </div>
    </header>

    {{-- Prerequisite Guidance Callout --}}
    <div class="fea-info-strip">
        <div class="fea-info-left">
            <i class="mdi mdi-information-outline"></i>
            <div>
                <strong>Inter-Agency Custody Protocol:</strong> Preliminary FEA document creation remains locked until all four (4) mandatory PSWDO enrollment documents have been finalized.
            </div>
        </div>
        <div>
            <span class="badge" style="background: #eef2ff; color: #4338ca; font-size: 0.72rem; font-weight: 750; padding: 0.35rem 0.65rem; border-radius: 6px;">
                Stage 2 Prerequisite
            </span>
        </div>
    </div>

    {{-- Main Queue Card --}}
    <section class="card fea-card" aria-label="FEA processing queue">
        <div class="fea-card-header">
            <div class="fea-card-title">
                <i class="mdi mdi-file-document-box text-primary" style="font-size: 1.25rem;"></i>
                <h3>Surfaced FR FEA Queue</h3>
                <span class="fea-card-badge">{{ $processings->total() }} {{ \Illuminate\Support\Str::plural('Record', $processings->total()) }}</span>
            </div>
            <div class="fea-filter-box">
                <i class="mdi mdi-magnify"></i>
                <input type="text" id="feaQueueSearch" class="fea-filter-input" placeholder="Search reference, area, category..." onkeyup="filterFeaTable(this.value)">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table fea-table" id="feaTable">
                <thead>
                    <tr>
                        <th style="width: 140px;">FR Reference</th>
                        <th style="width: 140px;">Category</th>
                        <th style="width: 130px;">Surfacing Date</th>
                        <th>Surfacing Area</th>
                        <th style="width: 140px;">Overall FEA Status</th>
                        <th style="width: 260px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($processings as $fea)
                    @php
                        $record = $fea->surfacedFormerRebel;
                        $statusVal = $fea->overallStatus()->value;
                        $statusClass = match(strtolower($statusVal)) {
                            'completed' => 'completed',
                            'in progress', 'ongoing' => 'in-progress',
                            default => 'pending',
                        };
                        $isReady = $readinessByProcessing[$fea->id] ?? false;
                    @endphp
                    <tr class="fea-row">
                        <td>
                            <span class="fea-reference-badge">
                                <i class="mdi mdi-pound"></i>
                                <span class="search-ref">{{ $record->reference_number }}</span>
                            </span>
                        </td>
                        <td>
                            <span class="fea-category-tag search-cat">{{ $record->category->value }}</span>
                        </td>
                        <td>
                            <div class="fea-date-cell">
                                <i class="mdi mdi-calendar"></i>
                                <span>{{ $record->surfaced_at->format('M d, Y') }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="fea-area-cell search-area">
                                <i class="mdi mdi-map-marker"></i>
                                <span>{{ $record->barangay?->name ? $record->barangay->name.', ' : '' }}{{ $record->municipality->name }}, {{ $record->province }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="fea-status-pill {{ $statusClass }}">
                                @if($statusClass === 'completed')
                                    <i class="mdi mdi-check-circle"></i>
                                @elseif($statusClass === 'in-progress')
                                    <i class="mdi mdi-clock"></i>
                                @else
                                    <i class="mdi mdi-clock-outline"></i>
                                @endif
                                <span>{{ $statusVal }}</span>
                            </span>
                        </td>
                        <td class="fea-actions-col">
                            <a class="btn-fea-action" href="{{ route('ib39.fea.show', $fea) }}">
                                <i class="mdi mdi-file-document-box-outline"></i>
                                <span>View FEA Record</span>
                            </a>
                            @unless($isReady)
                                <div class="fea-lock-notice">
                                    <i class="mdi mdi-lock"></i>
                                    <span>{{ $readiness->denialMessage() }}</span>
                                </div>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="fea-empty-state">
                            <div class="empty-icon-circle">
                                <i class="mdi mdi-shield-check"></i>
                            </div>
                            <h4>No Surfaced FR Records in FEA Queue</h4>
                            <p>There are currently no surfaced FR records with a firearms indicator of "Yes" requiring firearms and explosives custody documentation.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($processings->hasPages())
            <div class="fea-footer">
                <div class="text-muted small">
                    Showing {{ $processings->firstItem() }} to {{ $processings->lastItem() }} of {{ $processings->total() }} results
                </div>
                <div>
                    {{ $processings->links() }}
                </div>
            </div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
function filterFeaTable(query) {
    var filter = (query || '').toLowerCase().trim();
    var rows = document.querySelectorAll('#feaTable tbody tr.fea-row');
    
    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        if (!filter || text.indexOf(filter) > -1) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
@endpush
