@extends('layouts.skydash-v')
@section('title', 'Municipality IMPLAN Monitoring')
@section('heading', 'Municipality IMPLAN Monitoring')

@push('styles')
    <style>
        .gov-agency-monitoring-detail .implan-official-scroll { max-width:100%; overflow-x:auto; }
        .gov-agency-monitoring-detail .implan-official-table { width:2100px; min-width:2100px; table-layout:fixed; }
        .gov-agency-monitoring-detail .implan-official-table th,
        .gov-agency-monitoring-detail .implan-official-table td {
            box-sizing:border-box;
            height:auto;
            white-space:normal !important;
            overflow-wrap:anywhere;
            word-break:normal;
            vertical-align:top;
        }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(1),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(1) { width:220px; }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(2),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(2) { width:300px; }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(3),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(3) { width:180px; }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(4),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(4) { width:190px; }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(5),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(5) { width:220px; }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(6),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(6) { width:170px; }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(7),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(7) { width:170px; }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(8),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(8) { width:190px; }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(9),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(9) { width:130px; }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(10),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(10) { width:170px; }
        .gov-agency-monitoring-detail .implan-official-table th:nth-child(11),
        .gov-agency-monitoring-detail .implan-official-table td:nth-child(11) { width:160px; }
    </style>
@endpush

@section('content')
    <div class="gov-agency-monitoring-detail" data-monitoring-detail>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 no-print">
        <div>
            <h3 class="font-weight-bold mb-1">{{ $municipality->name }} Official IMPLAN</h3>
            <p class="text-muted mb-0" data-monitoring-total="{{ $implans->count() }}">
                Total IMPLANs: {{ $implans->count() }} &middot; Read-only municipality monitoring
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()" data-print-implan>
                <i class="mdi mdi-printer"></i> Print
            </button>
            <a href="{{ route('gov_agency.implan.index') }}" class="btn btn-light">Back</a>
        </div>
    </div>

    @include('implan._official_format', [
        'showAllResponsibleAgencies' => true,
        'showAgencyResponseAttribution' => false,
        'emptyResponseValue' => '—',
    ])
    </div>
@endsection

@push('styles')
    <style>
        @media print {
            @page { size:A4 landscape; margin:8mm; }

            html,
            body {
                width:100% !important;
                height:auto !important;
                margin:0 !important;
                padding:0 !important;
                overflow:visible !important;
                background:#fff !important;
            }

            .container-scroller,
            .page-body-wrapper,
            .main-panel,
            .content-wrapper,
            .gov-agency-monitoring-detail {
                display:block !important;
                position:static !important;
                inset:auto !important;
                width:100% !important;
                max-width:none !important;
                height:auto !important;
                max-height:none !important;
                margin:0 !important;
                padding:0 !important;
                overflow:visible !important;
                transform:none !important;
            }

            .content-wrapper > :not(.gov-agency-monitoring-detail),
            .gov-agency-monitoring-detail > .no-print {
                display:none !important;
            }

            .gov-agency-monitoring-detail .implan-print-area {
                display:block !important;
                visibility:visible !important;
                position:static !important;
                inset:auto !important;
                width:100% !important;
                min-width:0 !important;
                max-width:100% !important;
                height:auto !important;
                max-height:none !important;
                margin:0 !important;
                padding:0 !important;
                overflow:visible !important;
                transform:none !important;
                border:0 !important;
                box-shadow:none !important;
            }

            .gov-agency-monitoring-detail .implan-official-scroll {
                width:100% !important;
                min-width:0 !important;
                max-width:none !important;
                height:auto !important;
                max-height:none !important;
                overflow:visible !important;
                scrollbar-width:none;
                border:0 !important;
            }

            .gov-agency-monitoring-detail .implan-official-scroll::-webkit-scrollbar {
                display:none !important;
            }

            .gov-agency-monitoring-detail .implan-official-table {
                width:100% !important;
                min-width:0 !important;
                max-width:100% !important;
                table-layout:fixed !important;
                border-collapse:collapse !important;
                margin:0 !important;
                font-size:7pt !important;
            }

            .gov-agency-monitoring-detail .implan-official-table thead {
                display:table-header-group;
            }

            .gov-agency-monitoring-detail .implan-official-table tr {
                height:auto !important;
                break-inside:avoid;
                page-break-inside:avoid;
            }

            .gov-agency-monitoring-detail .implan-official-table th,
            .gov-agency-monitoring-detail .implan-official-table td {
                height:auto !important;
                padding:3px 2px !important;
                white-space:normal !important;
                overflow-wrap:anywhere;
                word-break:normal;
                vertical-align:top;
                line-height:1.2;
            }

            .gov-agency-monitoring-detail .implan-official-table th:nth-child(1),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(1) { width:10% !important; }
            .gov-agency-monitoring-detail .implan-official-table th:nth-child(2),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(2) { width:12% !important; }
            .gov-agency-monitoring-detail .implan-official-table th:nth-child(3),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(3) { width:8% !important; }
            .gov-agency-monitoring-detail .implan-official-table th:nth-child(4),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(4) { width:9% !important; }
            .gov-agency-monitoring-detail .implan-official-table th:nth-child(5),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(5) { width:10% !important; }
            .gov-agency-monitoring-detail .implan-official-table th:nth-child(6),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(6) { width:8% !important; }
            .gov-agency-monitoring-detail .implan-official-table th:nth-child(7),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(7) { width:9% !important; }
            .gov-agency-monitoring-detail .implan-official-table th:nth-child(8),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(8) { width:9% !important; }
            .gov-agency-monitoring-detail .implan-official-table th:nth-child(9),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(9) { width:7% !important; }
            .gov-agency-monitoring-detail .implan-official-table th:nth-child(10),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(10) { width:9% !important; }
            .gov-agency-monitoring-detail .implan-official-table th:nth-child(11),
            .gov-agency-monitoring-detail .implan-official-table td:nth-child(11) { width:9% !important; }

            .gov-agency-monitoring-detail .implan-official-banner {
                width:100% !important;
                min-height:60px;
                -webkit-print-color-adjust:exact;
                print-color-adjust:exact;
            }

            .gov-agency-monitoring-detail .implan-official-banner img {
                width:60px !important;
                height:auto !important;
                max-height:60px !important;
                object-fit:contain !important;
                flex:0 0 auto;
            }

            .gov-agency-monitoring-detail .implan-official-title {
                font-size:14pt !important;
                padding:8px 68px 8px 8px !important;
            }

            .gov-agency-monitoring-detail .implan-official-municipality {
                font-size:9pt !important;
            }

            .gov-agency-monitoring-detail .implan-official-table th,
            .gov-agency-monitoring-detail .implan-official-table td {
                -webkit-print-color-adjust:exact;
                print-color-adjust:exact;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const resetMonitoringScroll = () => {
                const scrollContainer = document.querySelector('[data-monitoring-detail] .implan-official-scroll');
                if (scrollContainer) {
                    scrollContainer.scrollLeft = 0;
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', resetMonitoringScroll, { once: true });
            } else {
                resetMonitoringScroll();
            }
        })();
    </script>
@endpush
