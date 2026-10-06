<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>ECLIP Enrolment Form</title>
    <style>
        @page { size: A4 portrait; margin: 25.4mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 9.25pt; line-height: 1.15; }
        .toolbar { width: 159.2mm; margin: 10px auto; text-align: right; }
        .toolbar a, .toolbar button { display: inline-block; margin-left: 6px; padding: 7px 12px; border: 1px solid #777; background: #fff; color: #111; text-decoration: none; font: inherit; cursor: pointer; }
        .sheet { margin: 0 auto; padding: 0; background: #fff; }
        .preview-output .sheet { width: 210mm; min-height: 297mm; padding: 25.4mm; }
        .pdf-output .sheet { width: 159.2mm; }
        h1 { margin: 0 0 4mm; text-align: center; font-size: 11.5pt; line-height: 1; font-weight: 700; }
        p { margin: 0 0 2.15mm; }
        .line { display: inline-block; min-height: 3.8mm; padding: 0 .8mm .25mm; border-bottom: .75pt solid #000; vertical-align: bottom; text-align: center; }
        .line-wide { width: 98mm; }
        .line-address { width: 159.2mm; }
        .line-rm { width: 48mm; }
        .line-date { width: 27mm; }
        .line-agency { width: 70mm; text-align: left; }
        .line-person { width: 68mm; }
        .notation { margin: -2mm 0 2mm 32mm; width: 98mm; text-align: center; font-size: 6.5pt; line-height: 1; }
        .notation.address { margin-left: 0; width: 159.2mm; }
        .notation.date { display: inline-block; width: 27mm; margin: 0; text-align: center; }
        .rm-heading { margin: 0 0 1.3mm 82mm; }
        .validation-first { margin-bottom: 6mm; }
        .validation-continuation { margin-bottom: 0; }
        .date-notation { margin: 0 0 4mm 58mm; width: 27mm; text-align: center; font-size: 6.5pt; line-height: 1; }
        .agency-block { margin-top: 2mm; }
        .agency-block p { margin-bottom: 3mm; }
        .agency-block .agency-section { margin-top: 0; }
        .agency-block .remarks-label { margin-bottom: 0; padding-top: 2mm; }
        .remarks-lines { width: 154mm; margin: .5mm 0 3mm; }
        .remarks-line { min-height: 5.5mm; border-bottom: .75pt solid #000; padding: 0 .5mm; }
        .firearm-intro { margin: 0 0 .7mm; }
        .firearm-grid { width: 100%; margin: 0 0 5mm; border-collapse: collapse; table-layout: fixed; }
        .firearm-grid td { width: 50%; padding: .1mm .8mm .35mm 0; vertical-align: bottom; }
        .firearm-value { display: inline-block; min-height: 3.5mm; padding-left: .8mm; border-bottom: .75pt solid #000; }
        .firearm-type { width: 43mm; }
        .firearm-caliber { width: 54mm; }
        .firearm-make { width: 53mm; }
        .firearm-serial { width: 43mm; }
        .firearm-remarks { width: 139mm; }
        .support { margin: 0 0 3mm; }
        .section-label { margin: 0 0 1mm; }
        .approved-label { margin-top: 3mm; }
        .signatures { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .signatures td { width: 50%; padding: 5mm 8mm 1.5mm 0; vertical-align: top; }
        .signature-line { width: 58mm; border-top: .75pt solid #000; padding-top: .7mm; }
        .signatures td:nth-child(2) .signature-line { margin-left: auto; }
        .approved-signatures { margin-bottom: 6mm; }
        .attested-first td { padding-top: 5mm; padding-bottom: 1.5mm; }
        .attested-second td { padding-top: 10mm; padding-bottom: 0; }
        .issuance { margin-top: 3mm; }
        .issuance-row { margin-bottom: .55mm; }
        .issuance-line { display: inline-block; min-height: 3.6mm; margin-left: 2mm; padding: 0 .8mm .25mm; border-bottom: .75pt solid #000; vertical-align: bottom; }
        .issuance-line-date { width: 34mm; }
        .issuance-line-place { width: 50mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; margin: 0; min-height: 0; padding: 0; }
        }
    </style>
</head>
<body class="{{ $printMode ? 'pdf-output' : 'preview-output' }}">
@unless($printMode)
    <div class="toolbar">
        <a href="{{ $backUrl }}">Back to PSWDO Workspace</a>
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ $downloadUrl }}">Download</a>
    </div>
@endunless
@php
    $givenNames = collect([$payload['first_name'], $payload['middle_name']])->filter(fn ($value) => filled($value))->join(' ');
    $name = collect([$payload['last_name'], $givenNames])->filter(fn ($value) => filled($value))->join(', ');
    $displayName = collect([$payload['first_name'], $payload['middle_name'], $payload['last_name']])->filter(fn ($value) => filled($value))->join(' ');
    $japicDate = filled($payload['japic_validation_date']) ? \Carbon\Carbon::parse($payload['japic_validation_date'])->format('m/d/Y') : '';
    $issuanceDate = filled($payload['date_of_issuance']) ? \Carbon\Carbon::parse($payload['date_of_issuance'])->format('m/d/Y') : '';
@endphp
<main class="sheet">
    <h1>ECLIP ENROLMENT FORM</h1>

    <p>This is to certify that <span class="line line-wide">{{ $name }}</span> of</p>
    <div class="notation">(Last Name, First Name, Middle Name)</div>
    <p><span class="line line-address">{{ $payload['address'] }}</span></p>
    <div class="notation address">(Address)</div>
    <p class="rm-heading">With Reintegration Monitoring (RM)</p>
    <p class="validation-first">No. <span class="line line-rm">{{ $payload['reintegration_monitoring_number'] }}</span> has been validated and authenticated by the Joint AFP-</p>
    <p class="validation-continuation">PNP Intelligence Committee (JAPIC) on <span class="line line-date">{{ $japicDate }}</span>.</p>
    <div class="date-notation">(MM/DD/YYYY)</div>

    <div class="agency-block">
        <p class="agency-section">Other agencies involved in the verification process, as applicable:</p>
        <p>Civil Society Organization; please specify: <span class="line line-agency">{{ $payload['civil_society_organization'] }}</span></p>
        <p>Other government agency; please specify: <span class="line line-agency">{{ $payload['other_government_agency'] }}</span></p>
        <p class="remarks-label">Remarks, if any:</p>
        <div class="remarks-lines">
            <div class="remarks-line">{{ $payload['remarks'] }}</div>
            <div class="remarks-line"></div>
            <div class="remarks-line"></div>
        </div>
    </div>

    <p class="firearm-intro">Further, this is also to certify that Mr./Ms. <span class="line line-person">{{ $displayName }}</span> has also turned-in the firearms with the following details:</p>
    <table class="firearm-grid">
        <tr><td>Type of Firearm/s: <span class="firearm-value firearm-type">{{ $payload['firearm_type'] }}</span></td><td>Caliber: <span class="firearm-value firearm-caliber">{{ $payload['caliber'] }}</span></td></tr>
        <tr><td>Make: <span class="firearm-value firearm-make">{{ $payload['make'] }}</span></td><td>Serial Number: <span class="firearm-value firearm-serial">{{ $payload['serial_number'] }}</span></td></tr>
        <tr><td colspan="2">Remarks: <span class="firearm-value firearm-remarks">{{ $payload['firearm_remarks'] }}</span></td></tr>
    </table>

    <p class="support">This certification is issued to support the enrollment of Mr./Ms. <span class="line line-person">{{ $displayName }}</span><br>in the Enhanced Comprehensive Local Integration Program (ECLIP).</p>

    <div class="section-label approved-label">Approved by:</div>
    <table class="signatures approved-signatures">
        <tr class="attested-first">
            <td><div class="signature-line"><div>Governor</div><div>ECLIP Committee Chairperson</div></div></td>
            <td><div class="signature-line"><div>AFP Brigade Commander</div><div>ECLIP Committee Co-Chairperson</div></div></td>
        </tr>
    </table>

    <div class="section-label">Attested by:</div>
    <table class="signatures">
        <tr class="attested-second">
            <td><div class="signature-line"><div>DILG Provincial Director</div><div>Member</div></div></td>
            <td><div class="signature-line"><div>PNP Provincial Director</div><div>Member</div></div></td>
        </tr>
        <tr>
            <td><div class="signature-line"><div>LSWDO, Member</div></div></td>
            <td><div class="signature-line"><div>CSO, Member</div></div></td>
        </tr>
    </table>

    <div class="issuance">
        <div class="issuance-row">Date of Issuance: <span class="issuance-line issuance-line-date">{{ $issuanceDate }}</span></div>
        <div class="issuance-row">Place of Issuance: <span class="issuance-line issuance-line-place">{{ $payload['place_of_issuance'] }}</span></div>
    </div>
</main>
</body>
</html>
