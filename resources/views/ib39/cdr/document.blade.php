<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $isFinal ? 'CDR Final Copy' : 'CDR Draft Preview' }}</title>
    <style>
@page{size:A4;margin:0}
* {
    box-sizing: border-box;
}
body {
    font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
    color: #000;
    font-size: 11pt;
    line-height: 1.35;
    margin: 0;
    background: #525659;
    padding-bottom: 40px;
}

/* Screen toolbar */
.toolbar {
    background: #1e293b;
    color: #fff;
    padding: 10px 20px;
    position: sticky;
    top: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 10px rgba(0,0,0,.3);
}
.toolbar a {
    color: #e2e8f0;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 6px;
    background: rgba(255,255,255,.1);
    transition: background .15s ease;
}
.toolbar a:hover {
    background: rgba(255,255,255,.2);
    color: #fff;
}
.toolbar button {
    background: #401595;
    border: 1px solid #401595;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    padding: 6px 18px;
    border-radius: 6px;
    cursor: pointer;
    transition: background .15s ease;
}
.toolbar button:hover {
    background: #280274;
}

/* Word Document Multi-page Container */
.word-doc-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 28px;
    padding: 24px 0;
}

/* Individual A4 Word Paper Sheet */
.word-page {
    background: #ffffff;
    width: 210mm;
    min-height: 297mm;
    padding: 1in 1in 1in 0.31in;
    box-shadow: 0 6px 20px rgba(0,0,0,.35), 0 1px 4px rgba(0,0,0,.2);
    border: 1px solid #d1d5db;
    position: relative;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

/* Page Indicator Badge on Screen */
.page-number-tag {
    position: absolute;
    top: -12px;
    right: 14px;
    background: #1e293b;
    color: #94a3b8;
    font-size: 8pt;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    letter-spacing: .05em;
    user-select: none;
}

/* Content wrapper between header and footer */
.page-content {
    flex: 1 0 auto;
    display: flex;
    flex-direction: column;
    padding: 6px 0;
}

/* Draft Watermark / Badge */
.draft-mark {
    border: 2px dashed #dc2626;
    background: #fef2f2;
    color: #dc2626;
    font-size: 11pt;
    font-weight: 800;
    letter-spacing: .12em;
    text-align: center;
    padding: 4px;
    margin-bottom: 10px;
}
.draft-mark.final-banner {
    border: 2px solid #16a34a;
    background: #f0fdf4;
    color: #16a34a;
}

/* Official Header */
.official-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1.5px solid #000;
    padding-bottom: 4px;
    margin-bottom: 10px;
}
.header-logo-left img {
    width: 42px;
    height: 42px;
    object-fit: contain;
}
.header-center {
    text-align: center;
    flex: 1;
}
.confidential-badge {
    display: block;
    font-family: Stencil, "Arial Black", Impact, sans-serif;
    color: #5b9bd5;
    font-size: 14pt;
    font-weight: bold;
    letter-spacing: .22em;
    margin-bottom: 2px;
}
.vision-badge {
    display: block;
    font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
    color: #595959;
    font-size: 7.5pt;
    font-weight: bold;
    font-style: italic;
    letter-spacing: .02em;
}
.header-logo-right .agila-round-mark {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.header-logo-right img {
    width: 42px;
    height: 42px;
    object-fit: contain;
}

/* Official Footer */
.official-footer {
    border-top: 1.5px solid #000;
    padding-top: 4px;
    margin-top: 12px;
}
.footer-grid {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.footer-left {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 6pt;
    color: #595959;
    font-weight: bold;
    line-height: 1.1;
}
.pgs-mark, .igg-mark, .iso-mark {
    border-right: 1px solid #d1d5db;
    padding-right: 6px;
}
.iso-circle span {
    border: 1px solid #595959;
    border-radius: 50%;
    padding: 1px 3px;
}
.footer-center {
    text-align: center;
}
.confidential-badge-footer {
    display: block;
    font-family: Stencil, "Arial Black", Impact, sans-serif;
    color: #5b9bd5;
    font-size: 14pt;
    font-weight: bold;
    letter-spacing: .22em;
}
.motto-text {
    display: block;
    font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
    color: #595959;
    font-size: 8pt;
    font-style: italic;
    font-weight: bold;
}
.footer-right .atr-mark {
    text-align: right;
    line-height: 1.1;
}
.atr-text {
    color: #548235;
    font-size: 11pt;
    font-weight: bold;
    letter-spacing: -.02em;
}
.atr-hashtag {
    display: block;
    color: #548235;
    font-size: 6pt;
    font-weight: bold;
}

/* Unit Header */
.unit-heading {
    text-align: center;
    font-size: 10.5pt;
    line-height: 1.25;
    margin-bottom: 6px;
}
.unit-heading strong {
    display: block;
    font-size: 11pt;
    font-weight: bold;
    letter-spacing: .06em;
}
.unit-heading span {
    font-size: 10pt;
}

.cdr-main-title {
    text-align: center;
    font-size: 11.5pt;
    font-weight: bold;
    letter-spacing: .02em;
    margin: 4px 0 1px;
}
.cdr-doc-date {
    text-align: center;
    font-size: 11pt;
    margin-bottom: 6px;
}

/* FR Photo Frame */
.fr-photo-frame {
    border: 1.5px solid #000;
    width: 140px;
    height: 168px;
    margin: 0 auto 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: #fff;
    overflow: hidden;
}
.fr-photo-frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.fr-photo-placeholder {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 10pt;
    font-weight: bold;
}

.cover-sub-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11pt;
    font-weight: normal;
    margin: 8px 0 4px;
    padding: 0 2px;
}
.cover-sub-row > div:first-child {
    padding-left: 28px;
}

/* Section Headings */
.document-section {
    margin-bottom: 6px;
}
.document-section h2.section-title {
    font-size: 11pt;
    font-weight: bold;
    margin: 6px 0 3px;
    letter-spacing: .01em;
    border: 0;
    background: transparent;
    padding: 0;
}
.document-section.heading-only h2.section-title {
    text-align: left;
    margin: 8px 0 3px;
}

/* Tables */
table.cdr-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    margin-bottom: 6px;
    font-size: 11pt;
    line-height: 1.25;
}
table.cdr-table th, table.cdr-table td {
    border: 1px solid #000;
    padding: 2px 5px;
    vertical-align: top;
    font-size: 11pt;
    overflow-wrap: anywhere;
}
table.cdr-table th {
    font-weight: bold;
    background: #fff;
    text-align: left;
    font-size: 11pt;
}
table.cdr-table thead th {
    text-align: center;
    font-size: 11pt;
    font-weight: bold;
    letter-spacing: .02em;
}
table.cdr-table td.cell-label {
    width: 38%;
    font-weight: normal;
}
table.cdr-table td.cell-end {
    text-align: center;
    font-weight: bold;
    letter-spacing: .2em;
    padding: 3px;
}

.section-na-text {
    font-weight: bold;
    font-size: 11pt;
    margin: 4px 0 8px;
}

.formatted p {
    margin: 0 0 .4em;
    font-size: 11pt;
}
.formatted ul {
    margin: .2em 0;
    padding-left: 1.5em;
    font-size: 11pt;
}

/* Signatures Section */
.signatures {
    margin-top: 28px;
    page-break-inside: avoid;
}
.signature-grid {
    display: flex;
    align-items: flex-start;
    justify-content: space-around;
    text-align: center;
}
.signature-item {
    min-width: 200px;
}
.signature-name {
    font-size: 11pt;
    font-weight: bold;
    text-decoration: underline;
    text-transform: uppercase;
    letter-spacing: .01em;
    display: block;
    margin-bottom: 2px;
}
.signature-role {
    font-size: 11pt;
    font-weight: normal;
    display: block;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 0;
    }
    .toolbar, .page-number-tag {
        display: none !important;
    }
    body {
        background: transparent !important;
        padding: 0 !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
    .word-doc-container {
        display: block !important;
        padding: 0 !important;
        gap: 0 !important;
    }
    .word-page {
        box-shadow: none !important;
        border: none !important;
        margin: 0 !important;
        padding: 1in 1in 1in 0.31in !important;
        width: 100% !important;
        min-height: 297mm !important;
        page-break-after: always !important;
        break-after: page !important;
    }
    .word-page:last-child {
        page-break-after: auto !important;
        break-after: auto !important;
    }
}
    </style>
</head>
<body>

<nav class="toolbar">
    <div>
        @unless($isFinal)
            <a href="{{ route('ib39.cdr.edit', $cdr) }}">&larr; Edit draft</a>
        @endunless
    </div>
    <div style="font-size: 13px; font-weight: bold; color: #cbd5e1;">
        Microsoft Word Print Layout (A4 &bull; Arial 11)
    </div>
    <div>
        <button onclick="window.print()">Print Document</button>
    </div>
</nav>

@php
$docPages = [
    1 => [
        'blocks' => [['section', 'cover'], ['section', 'personal']],
    ],
    2 => [
        'blocks' => [['section', 'physical'], ['repeatable', 'parents_family'], ['repeatable', 'siblings']],
    ],
    3 => [
        'blocks' => [['repeatable', 'children'], ['repeatable', 'government_relatives'], ['repeatable', 'ugm_relatives']],
    ],
    4 => [
        'blocks' => [['section', 'neutralization'], ['section', 'entry_background'], ['repeatable', 'party_member_courses'], ['repeatable', 'npa_member_courses'], ['repeatable', 'mass_activist_courses']],
    ],
    5 => [
        'blocks' => [['repeatable', 'violent_activities'], ['repeatable', 'non_violent_activities']],
    ],
    6 => [
        'blocks' => [['section', 'order_of_battle'], ['section', 'composition'], ['section', 'disposition'], ['section', 'strength_firearms'], ['section', 'training'], ['section', 'logistics']],
    ],
    7 => [
        'blocks' => [['section', 'strategy_tactics'], ['section', 'combat_effectiveness'], ['section', 'plans'], ['repeatable', 'personalities']],
    ],
    8 => [
        'blocks' => [['repeatable', 'posting_areas'], ['repeatable', 'mass_contacts'], ['repeatable', 'supply_routes']],
    ],
    9 => [
        'blocks' => [['repeatable', 'npa_active'], ['section', 'other_information'], ['repeatable', 'chronology']],
    ],
    10 => [
        'blocks' => [['section', 'assessment'], ['section', 'recommendation'], ['section', 'signatories']],
    ],
];
@endphp

<div class="word-doc-container">
    @foreach($docPages as $pageNum => $pageData)
        <main class="word-page">
            <div class="page-number-tag">Page {{ $pageNum }} of {{ count($docPages) }}</div>
            @include('ib39.cdr.partials.official-header')

            <div class="page-content">
                @foreach($pageData['blocks'] as [$kind, $key])
                    @if($key === 'cover')
                        <div class="draft-mark {{ $isFinal ? 'final-banner' : '' }}">
                            {{ $isFinal ? 'FINAL COPY' : 'DRAFT — NOT FINAL' }}
                        </div>

                        <section class="cover">
                            <div class="unit-heading">
                                <strong>H E A D Q U A R T E R S</strong>
                                <strong>39TH INFANTRY (SMASH’EM) BATTALION, 10ID, PA</strong>
                                <span>Brgy Poblacion, Makilala, Cotabato</span>
                            </div>

                            <h1 class="cdr-main-title">CUSTODIAL DEBRIEFING REPORT (CDR)</h1>
                            <div class="cdr-doc-date">
                                ({{ data_get($content, 'report_date') ? \Carbon\Carbon::parse(data_get($content, 'report_date'))->format('d F Y') : '18 January 2024' }})
                            </div>

                            <div class="fr-photo-frame" data-cover-fr-photo>
                                @if($photoVersion)
                                    <img src="{{ route('ib39.cdr.photos.show', $photoVersion) }}" alt="FR Photo">
                                @else
                                    <span class="fr-photo-placeholder">FR Photo</span>
                                @endif
                            </div>

                            <div class="cover-sub-row">
                                <div>39IB/C</div>
                                <div>({{ data_get($content, 'report_date') ? \Carbon\Carbon::parse(data_get($content, 'report_date'))->format('d F Y') : now()->format('d F Y') }})</div>
                            </div>
                        </section>
                    @elseif($key === 'signatories')
                        <section class="signatures">
                            <div class="signature-grid">
                                <div class="signature-item">
                                    <span class="signature-name">{{ data_get($content, 'debriefer_name') ?: '—' }}</span>
                                    <span class="signature-role">Debriefer</span>
                                </div>
                                <div class="signature-item">
                                    <span class="signature-name">{{ data_get($content, 'approving_officer_name') ?: '—' }}</span>
                                    <span class="signature-role">Commanding Officer</span>
                                </div>
                            </div>
                        </section>
                    @elseif($kind === 'section')
                        @php $section = $sections[$key]; @endphp
                        <section class="document-section {{ !$section['fields'] ? 'heading-only' : '' }}" data-section-block="{{ $key }}">
                            <h2 class="section-title">{{ $section['label'] }}</h2>
                            @if($section['fields'])
                                <table class="cdr-table">
                                    <tbody>
                                        @if($key === 'personal')
                                            <tr>
                                                <td class="cell-label">Name:</td>
                                                <td>{{ data_get($content, 'subject_name') ?: $cdr->surfacedFormerRebel->display_name }}</td>
                                            </tr>
                                            <tr>
                                                <td class="cell-label">Alias:</td>
                                                <td>{{ data_get($content, 'alias') ?: '—' }}</td>
                                            </tr>
                                        @endif
                                        @foreach($section['fields'] as $fieldKey => $field)
                                            @continue(in_array($fieldKey, ['significant_information', 'white_area_information', 'projected_enemy_operations']))
                                            @php
                                                $detailFields = [
                                                    'significant_information_status' => 'significant_information',
                                                    'white_area_status' => 'white_area_information',
                                                    'projected_enemy_status' => 'projected_enemy_operations'
                                                ];
                                                $detail = $detailFields[$fieldKey] ?? null;
                                            @endphp
                                            <tr>
                                                <td class="cell-label">{{ $field['label'] }}:</td>
                                                <td>
                                                    @if($field['type'] === 'yes_na_text' && data_get($content, $fieldKey) === 'na')
                                                        N/A
                                                    @elseif($field['type'] === 'yes_na_text')
                                                        {{ data_get($content, $detail) ?: '—' }}
                                                    @elseif($field['type'] === 'narrative')
                                                        <div class="formatted">{!! \App\Support\Ib39CdrNarrativeFormatter::render(data_get($content, $fieldKey)) !!}</div>
                                                    @else
                                                        <span style="white-space:pre-wrap">{{ data_get($content, $fieldKey) ?: '—' }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </section>
                    @else
                        @php
                            $section = $repeatableSections[$key];
                            $rows = data_get($content, $key, []);
                            $na = isset($section['status_key']) && data_get($content, $section['status_key']) === 'na';
                        @endphp
                        <section class="document-section" data-repeatable-block="{{ $key }}">
                            <h2 class="section-title">{{ $section['label'] }}</h2>
                            @if($na)
                                <p class="section-na-text">N/A</p>
                            @else
                                <table class="cdr-table">
                                    <thead>
                                        <tr>
                                            @foreach($section['columns'] as $label)
                                                <th>{{ $label }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($rows as $row)
                                            <tr>
                                                @foreach($section['columns'] as $column => $label)
                                                    <td style="white-space:pre-wrap">{{ data_get($row, $column) ?: '—' }}</td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ count($section['columns']) }}" style="text-align: center;">—</td>
                                            </tr>
                                        @endforelse
                                        @if($key === 'chronology')
                                            <tr>
                                                <td colspan="{{ count($section['columns']) }}" class="cell-end">END</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            @endif
                        </section>
                    @endif
                @endforeach
            </div>

            @include('ib39.cdr.partials.official-footer')
        </main>
    @endforeach
</div>

@if($autoPrint)
    <script>
        window.addEventListener('load', function(){ window.print(); });
    </script>
@endif

</body>
</html>
