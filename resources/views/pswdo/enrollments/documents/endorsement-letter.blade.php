<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Endorsement Letter</title>
    <style>
        @page { size: A4 portrait; margin: 25.4mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { color: #000; background: {{ $printMode ? '#fff' : '#e5e7eb' }}; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 8.2pt; line-height: 1.16; }
        .toolbar { display: flex; gap: 10px; justify-content: flex-end; padding: 14px; position: sticky; top: 0; z-index: 5; background: rgba(229,231,235,.96); }
        .toolbar a { border: 1px solid #334155; border-radius: 6px; color: #0f172a; padding: 8px 12px; text-decoration: none; background: #fff; font-weight: 700; }
        .document-page {
            @if($printMode)
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
            @else
                width: 210mm;
                min-height: 297mm;
                margin: 14px auto 28px;
                padding: 25.4mm;
            @endif
            background: #fff;
            page-break-after: always;
        }
        .document-page:last-child { page-break-after: auto; }
        .form-name { font-size: 8pt; line-height: 1.1; }
        .fy { margin-top: 2px; }
        h1 { margin: 7mm 0 4mm; text-align: center; font-size: 12pt; line-height: 1.15; letter-spacing: .04em; }
        .date-row { width: 48mm; margin: 0 0 4mm auto; text-align: center; }
        .write-line { min-height: 4.5mm; border-bottom: .7pt solid #000; overflow-wrap: anywhere; }
        .date-label { margin-top: 1mm; font-size: 7.5pt; }
        .addressee { width: 82mm; margin-bottom: 3mm; }
        .addressee .write-line { min-height: 4mm; }
        .salutation { margin: 0 0 3mm; }
        .salutation-value { display: inline-block; min-width: 50mm; border-bottom: .7pt solid #000; text-align: center; }
        p { margin: 0 0 3mm; text-align: justify; }
        .indent { text-indent: 10mm; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; break-inside: avoid; }
        th, td { border: .65pt solid #000; padding: 1mm .75mm; vertical-align: middle; overflow-wrap: anywhere; }
        th { text-align: center; font-weight: 700; }
        .beneficiary-table { font-size: 6.25pt; line-height: 1.08; margin-bottom: 1.5mm; }
        .beneficiary-table tbody td { min-height: 10mm; height: 10mm; }
        .beneficiary-table .number { width: 5%; text-align: center; }
        .beneficiary-table .name { width: 15%; }
        .beneficiary-table .address { width: 14%; }
        .beneficiary-table .group { width: 6%; }
        .beneficiary-table .assistance { width: 13%; }
        .beneficiary-table .amount { width: 10%; }
        .beneficiary-table .cases { width: 25%; }
        .grand-total td { height: 6mm; font-weight: 700; }
        .grand-total .label { text-align: right; }
        .firearm-note { font-size: 7pt; margin: 0 0 2mm 3mm; }
        .summary-title { margin: 0 0 1.5mm; }
        .summary-table { font-size: 6.25pt; line-height: 1.05; margin-bottom: 2mm; }
        .summary-table th { height: 10mm; }
        .summary-table td { height: 6mm; text-align: center; }
        .group-note { margin: 0 0 2.5mm; padding-left: 3mm; font-size: 7pt; font-style: italic; }
        .certification { margin-bottom: 3mm; text-indent: 10mm; }
        .closing { margin: 0 0 7mm 10mm; }
        .signature-block { width: 68mm; margin: 0 0 4mm auto; text-align: center; page-break-inside: avoid; break-inside: avoid; }
        .signature-line { height: 7mm; border-bottom: .7pt solid #000; }
        .designation { font-weight: 700; margin-top: 1mm; }
        .attachments-title { margin-bottom: 1mm; font-weight: 700; }
        .attachments { page-break-inside: avoid; break-inside: avoid; }
        .attachments th { font-size: 6.25pt; line-height: 1.08; }
        .attachments td { height: 12mm; font-size: 6.4pt; line-height: 1.12; vertical-align: top; }
        .attachments ul { margin: 0; padding-left: 4mm; }
        .continuation-header { margin-bottom: 5mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .document-page { box-shadow: none; }
        }
        @media screen {
            .document-page { border: 1px solid #cbd5e1; box-shadow: 0 3px 16px rgba(15, 23, 42, .18); }
        }
    </style>
</head>
<body>
@unless($printMode)
    <nav class="toolbar" aria-label="Draft actions">
        <a href="{{ $backUrl }}">Back to workspace</a>
        <a href="{{ $downloadUrl }}">Download PDF</a>
    </nav>
@endunless
@php
    $displayDate = filled(data_get($payload, 'letter_date'))
        ? \Illuminate\Support\Carbon::parse(data_get($payload, 'letter_date'))->format('F j, Y')
        : '';
    $beneficiaries = array_values(array_pad(data_get($payload, 'beneficiaries', []), 3, []));
    $numberedBeneficiaries = collect($beneficiaries)->map(
        fn (array $beneficiary, int $index): array => ['number' => $index + 1, 'data' => $beneficiary],
    );
    $assistanceSummary = collect(array_values(array_pad(data_get($payload, 'assistance_summary', []), 1, [])));
    $initialAssistanceRows = $assistanceSummary->take(4)->values();
    $remainingAssistanceRows = $assistanceSummary->skip(4)->values();
    $pages = collect();
    $beneficiaryPages = $numberedBeneficiaries->chunk(3)->values();
    foreach ($beneficiaryPages as $index => $rows) {
        $isLastBeneficiaryPage = $index === $beneficiaryPages->count() - 1;
        $pages->push([
            'intro' => $index === 0,
            'beneficiaries' => $rows,
            'show_grand_total' => $isLastBeneficiaryPage,
            'assistance' => $isLastBeneficiaryPage ? $initialAssistanceRows : collect(),
            'footer' => $isLastBeneficiaryPage && $remainingAssistanceRows->isEmpty(),
        ]);
    }
    $assistancePages = $remainingAssistanceRows->chunk(10)->values();
    foreach ($assistancePages as $index => $rows) {
        $pages->push([
            'intro' => false,
            'beneficiaries' => collect(),
            'show_grand_total' => false,
            'assistance' => $rows,
            'footer' => $index === $assistancePages->count() - 1,
        ]);
    }
@endphp

@foreach($pages as $page)
    <main class="document-page"
          data-endorsement-page="{{ $loop->iteration }}"
          data-beneficiary-count="{{ $page['beneficiaries']->count() }}"
          data-assistance-count="{{ $page['assistance']->count() }}">
        <div class="form-name">ECLIP IS Form</div>
        <div class="fy">(FY <span class="write-line" style="display:inline-block;min-width:24mm;text-align:center;">{{ data_get($payload, 'fiscal_year') }}</span>)</div>

        <h1>ENDORSEMENT LETTER</h1>

        @if($page['intro'])
            <div class="date-row">
                <div class="write-line">{{ $displayDate }}</div>
                <div class="date-label">Date</div>
            </div>

            <div class="addressee">
                @foreach(data_get($payload, 'addressee_lines', ['', '', '']) as $line)
                    <div class="write-line">{{ $line }}</div>
                @endforeach
            </div>

            <div class="salutation">Dear <span class="salutation-value">{{ data_get($payload, 'salutation') }}</span> :</div>
            <p class="indent">Respectfully endorsing the enrolment of the following FR and/or FVE beneficiaries for financial assistance:</p>
        @endif

        @if($page['beneficiaries']->isNotEmpty())
        <table class="beneficiary-table">
            <thead>
            <tr>
                <th class="number" rowspan="2">No.</th>
                <th class="name" rowspan="2">Name of FR/s and/or FVE/s</th>
                <th class="address" rowspan="2">Address</th>
                <th colspan="2">Group type</th>
                <th class="assistance" rowspan="2">Type of Assistance</th>
                <th class="amount" rowspan="2">Amount</th>
                <th class="cases" rowspan="2">With Pending Cases<br><em>(If yes, please specify the venue, docket no., case title)</em></th>
            </tr>
            <tr><th class="group">FR</th><th class="group">FVE</th></tr>
            </thead>
            <tbody>
            @foreach($page['beneficiaries'] as $beneficiary)
                <tr>
                    <td class="number">{{ $beneficiary['number'] }}</td>
                    <td>{{ data_get($beneficiary, 'data.name') }}</td>
                    <td>{{ data_get($beneficiary, 'data.address') }}</td>
                    <td>{{ data_get($beneficiary, 'data.fr_group_type') }}</td>
                    <td>{{ data_get($beneficiary, 'data.fve_group_type') }}</td>
                    <td>{{ data_get($beneficiary, 'data.assistance_type') }}</td>
                    <td>{{ data_get($beneficiary, 'data.amount') }}</td>
                    <td>{{ data_get($beneficiary, 'data.pending_cases') }}</td>
                </tr>
            @endforeach
            @if($page['show_grand_total'])
                <tr class="grand-total"><td class="label" colspan="6">GRAND TOTAL:</td><td colspan="2">{{ data_get($payload, 'grand_total') }}</td></tr>
            @endif
            </tbody>
        </table>
        @endif

        @if($page['show_grand_total'])
            <div class="firearm-note">*(Indicate number of firearms, applicable to firearms only)</div>
        @endif

        @if($page['assistance']->isNotEmpty())
            <div class="summary-title">Summary of the number of partner-stakeholder/s (beneficiary/ies) and financial assistance requested:</div>
            <table class="summary-table">
                <thead><tr>
                    <th>Immediate Assistance</th>
                    <th>Livelihood Assistance</th>
                    <th>Firearms Remuneration</th>
                    <th>Reintegration Assistance</th>
                    <th>FR</th>
                    <th>FVE</th>
                </tr></thead>
                <tbody>
                @foreach($page['assistance'] as $assistance)
                    <tr>
                        <td>{{ data_get($assistance, 'immediate_assistance') }}</td>
                        <td>{{ data_get($assistance, 'livelihood_assistance') }}</td>
                        <td>{{ data_get($assistance, 'firearms_remuneration') }}</td>
                        <td>{{ data_get($assistance, 'reintegration_assistance') }}</td>
                        <td>{{ data_get($assistance, 'fr') }}</td>
                        <td>{{ data_get($assistance, 'fve') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if($page['footer'])
            <p class="group-note">Specify the group type of the FR if NPA, MB, and others. If FVE, specify the group type if Turaife, BIFF, and others; (e.g. FR-NPA; FVE-Turaife)</p>
            <p class="certification">I hereby certify that the list is a full, true, and correct statement of beneficiaries and this is in support of the liquidation of financial assistance to be granted to the FR/s and/or FVE/s.</p>
            <div class="closing">Very truly yours,</div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="designation">GOVERNOR OR HUC/ICC MAYOR</div>
            </div>

            <div class="attachments-title">Attachments:</div>
            <table class="attachments">
                <thead><tr>
                    <th>Reintegration Assistance, Immediate Assistance, and Livelihood Assistance</th>
                    <th>Firearms Remuneration</th>
                </tr></thead>
                <tbody><tr>
                    <td>JAPIC Certification and ECLIP Enrolment Form</td>
                    <td><ul><li>Property Turn-in Slip (PTIS)</li><li>Technical Inspection Report (TIR)</li><li>Cost Valuation Form</li></ul></td>
                </tr></tbody>
            </table>
        @endif
    </main>
@endforeach
</body>
</html>
