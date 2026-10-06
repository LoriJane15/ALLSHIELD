<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Initial Interview Form</title>
    <style>
        @page { size: A4 portrait; margin: 25.4mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 12pt; line-height: 1.08; }
        .toolbar { width: 159.2mm; margin: 10px auto; text-align: right; }
        .toolbar a, .toolbar button { display: inline-block; margin-left: 6px; padding: 7px 12px; border: 1px solid #777; background: #fff; color: #111; text-decoration: none; font: inherit; cursor: pointer; }
        .page { position: relative; background: #fff; page-break-after: always; overflow: hidden; }
        .page:last-child { page-break-after: auto; }
        .preview-output .page { width: 210mm; height: 297mm; margin: 12px auto; padding: 25.4mm; box-shadow: 0 1px 8px rgba(15, 23, 42, .18); }
        .pdf-output .page { width: 159.2mm; height: 240mm; margin: 0; padding: 0; }
        h1, h2 { text-align: center; font-size: 12pt; line-height: 1.05; font-weight: 700; }
        h1 { margin: 0; }
        h2 { margin: 1.8mm 0 1.2mm; }
        p { margin: 0 0 1mm; text-align: justify; }
        .subtitle { margin: .4mm 0 0; text-align: center; font-weight: 700; }
        .as-of { margin: .4mm 0 2.5mm; text-align: center; }
        .line { display: inline-block; min-height: 4.2mm; padding: 0 .6mm .1mm; border-bottom: .65pt solid #000; vertical-align: bottom; text-align: center; }
        .line-xs { width: 20mm; }
        .line-sm { width: 32mm; }
        .line-md { width: 48mm; }
        .line-lg { width: 72mm; }
        .line-xl { width: 105mm; }
        .notation { font-size: 12pt; text-align: center; line-height: 1; }
        .meta { width: 100%; margin-bottom: 2.5mm; border-collapse: collapse; }
        .meta td { padding: .35mm 0; vertical-align: bottom; font-weight: 700; }
        .meta .value { border-bottom: .65pt solid #000; font-weight: 400; text-align: center; }
        .intro-box, .verification-box { border: .75pt solid #000; padding: 2mm; }
        .intro-box p { margin-bottom: 1mm; text-indent: 8mm; line-height: 1.12; }
        .intro-box .thanks { margin-bottom: .6mm; }
        .consent { margin: 1mm -2mm -2mm; padding: 1.8mm 2mm 1.5mm; border-top: .75pt solid #000; }
        .signature-row { width: 100%; margin-top: 2.2mm; border-collapse: collapse; }
        .signature-row td { width: 50%; padding: 0 7mm; text-align: center; vertical-align: top; }
        .signature-line { border-top: .65pt solid #000; padding-top: .7mm; }
        .encoder { margin-top: 2.2mm; }
        .form-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .form-table td, .form-table th { border: .65pt solid #000; padding: .4mm .55mm; vertical-align: top; }
        .form-table th { text-align: center; font-weight: 700; }
        .question { font-weight: 700; }
        .answer-line { min-height: 4.2mm; margin-top: .2mm; border-bottom: .55pt solid #000; font-weight: 400; }
        .answer-lines .answer-line { margin-top: .55mm; }
        .box { display: inline-block; width: 3.5mm; height: 3.5mm; margin: 0 .35mm 0 .8mm; border: .65pt solid #000; font-size: 9pt; line-height: 3.1mm; text-align: center; vertical-align: -0.35mm; }
        .box:first-child { margin-left: 0; }
        .choice-line { margin: .15mm 0; }
        .two-col { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .two-col > tbody > tr > td { width: 50%; vertical-align: top; }
        .section-gap { margin-top: 1.2mm; }
        .tight td, .tight th { padding: .3mm .45mm; }
        .compact td, .compact th { padding: .2mm .35mm; }
        .small { font-size: 12pt; }
        .emphasis { font-weight: 700; font-style: italic; }
        .frequency th { font-size: 12pt; vertical-align: middle; }
        .frequency td { vertical-align: middle; }
        .frequency .symptom { width: 34%; }
        .frequency .freq { width: 9.5%; text-align: center; }
        .frequency .freq-wide { width: 12%; }
        .continuation-note { width: 16%; font-style: italic; font-weight: 700; }
        .verification-box { margin: 5mm 4mm 0; padding: 2.5mm 2mm 2mm; }
        .verification-box p { line-height: 1.12; text-indent: 8mm; }
        .page-1 { line-height: 1.06; }
        .page-1 .intro-box { margin-top: 1mm; }
        .page-2, .page-3 { line-height: 1.02; }
        .page-2 h2, .page-4 h2 { margin-top: 1.2mm; margin-bottom: .8mm; }
        .page-2 .answer-lines .answer-line { min-height: 4mm; margin-top: .35mm; }
        .page-3 .form-table td { padding-top: .25mm; padding-bottom: .25mm; }
        .page-4 { line-height: .82; letter-spacing: -.35pt; }
        .page-4 h2 { margin-top: .45mm; margin-bottom: .25mm; }
        .page-4 .form-table td, .page-4 .form-table th { padding: 0 .18mm; }
        .page-4 .section-gap { margin-top: .15mm; }
        .page-4 .choice-line { margin: 0; }
        .page-4 .line { min-height: 3.3mm; }
        .page-4 .box { width: 2.8mm; height: 2.8mm; margin-right: .1mm; margin-left: .2mm; line-height: 2.5mm; }
        .page-4 .frequency { line-height: .82; letter-spacing: -.35pt; }
        .page-4 .frequency .box, .page-5 .frequency .box { margin-left: 0; }
        .page-5 { line-height: 1.04; }
        .page-5 .frequency { margin-top: 5mm !important; }
        .page-5 .form-table td, .page-5 .form-table th { padding: .25mm .4mm; }
        .page-5 .verification-box { margin-top: 6mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { width: auto; height: 240mm; margin: 0; padding: 0; box-shadow: none; }
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
    $value = fn(string $key, mixed $default = '') => $payload[$key] ?? $default;
    $values = fn(string $key) => (array) ($payload[$key] ?? []);
    $marked = fn(string $key, string $option) => $value($key) === $option ? 'X' : '';
    $checked = fn(string $key, string $option) => in_array($option, $values($key), true) ? 'X' : '';
    $date = function (string $key, string $format = 'm/d/Y') use ($value): string {
        return filled($value($key)) ? \Carbon\Carbon::parse($value($key))->format($format) : '';
    };
    $birthdate = filled($value('birthdate')) ? \Carbon\Carbon::parse($value('birthdate')) : null;
    $frequencyOptions = ['never', 'rarely', 'sometimes', 'often', 'always'];
@endphp

<section class="page page-1">
    <h1>INITIAL INTERVIEW FORM (35 minutes)</h1>
    <div class="subtitle">(May be conducted together with Needs Assessment tool)</div>
    <div class="as-of">As of <span class="line line-sm">{{ $date('as_of_date') }}</span></div>

    <table class="meta">
        <tr><td style="width:25%;">Name of Interviewer:</td><td class="value" colspan="3">{{ $value('interviewer_name') }}</td></tr>
        <tr><td></td><td class="notation" colspan="3">(Last Name, First Name, Middle Name)</td></tr>
        <tr><td>Office and Designation:</td><td class="value" colspan="3">{{ $value('interviewer_office_designation') }}</td></tr>
        <tr><td style="width:22%;">Date of Interview:</td><td class="value" style="width:28%;">{{ $date('interview_date', 'm/d/y') }}</td><td style="width:22%;padding-left:4mm;">Date of Submission:</td><td class="value" style="width:28%;">{{ $date('submission_date', 'm/d/y') }}</td></tr>
        <tr><td></td><td class="notation">(MM/DD/YY)</td><td></td><td class="notation">(MM/DD/YY)</td></tr>
    </table>

    <div class="intro-box">
        <p>This initial interview is being conducted by the <span class="line line-lg">{{ $value('conducting_entity') }}</span> to determine the urgent needs of Former Rebels (FRs) and Former Violent Extremists (FVEs) as requirement for ECLIP enrollment and immediate assistance.</p>
        <p>You will be asked to answer several questions. These will include details about yourself and your family. Your participation in this interview is voluntary. Should you decide to participate in this interview, please know that you have the right to withdraw at any time during its conduct. Please feel free to ask questions before and during the interview. At the end of this interview, you will be asked to validate and confirm your responses to the questions. All information obtained will be kept strictly confidential.</p>
        <p class="thanks">Thank you.</p>
        <div class="consent">
            <p>I, the undersigned, have been briefed properly by the interviewer as regards the nature and purpose of this initial interview. I give my full consent to participate in this activity.</p>
            <table class="signature-row"><tr><td><div class="signature-line">Signature over Printed Name</div></td><td><div class="signature-line">Date</div></td></tr></table>
        </div>
    </div>

    <table class="meta encoder">
        <tr><td style="width:22%;">Name of Encoder:</td><td class="value">{{ $value('encoder_name') }}</td></tr>
        <tr><td></td><td class="notation">(Last Name, First Name, Middle Name)</td></tr>
        <tr><td>Office and<br>Designation:</td><td class="value">{{ $value('encoder_office_designation') }}</td></tr>
        <tr><td>Date Encoded:</td><td class="value" style="width:50%;">{{ $date('date_encoded', 'm/d/y') }}</td></tr>
        <tr><td></td><td class="notation">(MM/DD/YY)</td></tr>
    </table>
</section>

<section class="page page-2">
    <h2 style="margin-top:0;">PART I: PROFILE OF RESPONDENT (3 minutes)</h2>
    <table class="form-table tight">
        <tr>
            <td colspan="3" style="width:50%;"><span class="question">1. Full Name:</span></td>
            <td rowspan="4" style="width:50%;">
                <div class="question">6. Civil Status:</div>
                @foreach(['single' => 'Single', 'married' => 'Married', 'widow_widower' => 'Widow/Widower', 'separated' => 'Separated', 'common_law_partner' => 'Common Law/Partner', 'others' => 'Others'] as $option => $label)
                    <span class="box">{{ $marked('civil_status', $option) }}</span>{{ $label }}
                @endforeach
                <span class="line line-md">{{ $value('civil_status_other') }}</span>
            </td>
        </tr>
        <tr><td>Last Name:<div class="answer-line">{{ $value('last_name') }}</div></td><td>First Name:<div class="answer-line">{{ $value('first_name') }}</div></td><td>Middle Name:<div class="answer-line">{{ $value('middle_name') }}</div></td></tr>
        <tr><td colspan="3"><span class="question">2. Alias:</span> <span class="line line-lg">{{ $value('alias') }}</span></td></tr>
        <tr><td colspan="3"><span class="question">3. Sex:</span> <span class="box">{{ $marked('sex', 'male') }}</span>Male <span class="box">{{ $marked('sex', 'female') }}</span>Female</td></tr>
        <tr>
            <td colspan="3"><span class="question">4. Birthdate:</span> <span class="line line-xs">{{ $birthdate?->format('m') }}</span> / <span class="line line-xs">{{ $birthdate?->format('d') }}</span> / <span class="line line-xs">{{ $birthdate?->format('Y') }}</span><div class="notation">Month&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Date&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Year</div></td>
            <td rowspan="2"><div class="question">7. Do you belong to a tribal group? <em>(Katubo/Tribo)</em></div><span class="box">{{ $marked('tribal_group', 'yes') }}</span>Yes, please specify (main group): <span class="line line-md">{{ $value('tribal_group_name') }}</span><br><span class="box">{{ $marked('tribal_group', 'no') }}</span>No<br><span class="question">8. Religion:</span> <span class="line line-lg">{{ $value('religion') }}</span></td>
        </tr>
        <tr><td colspan="3"><span class="question">5. Place of birth:</span><div class="answer-line">{{ $value('birthplace') }}</div></td></tr>
    </table>

    <h2>PART II: HISTORY IN THE ARMED MOVEMENT (12 minutes)</h2>
    <table class="form-table tight">
        <tr>
            <td style="width:50%;">
                <div class="question">9. Total number of years in the movement:</div><div class="answer-line">{{ $value('movement_years') }}</div>
                <div class="question section-gap">10. Age at entry in the movement:</div><div class="answer-line">{{ $value('entry_age') }}</div>
                <div class="question section-gap">11. What are your reasons for joining the movement?</div><div class="answer-lines"><div class="answer-line">{{ $value('reasons_joining') }}</div><div class="answer-line"></div><div class="answer-line"></div></div>
                <div class="question section-gap">12. What are your reasons for staying in the movement?</div><div class="answer-lines"><div class="answer-line">{{ $value('reasons_staying') }}</div><div class="answer-line"></div><div class="answer-line"></div></div>
                <div class="question section-gap">13. What was your position in the movement before you left?</div><div class="answer-line">{{ $value('position_before_leaving') }}</div>
            </td>
            <td style="width:50%;">
                <div class="question">14. What was your unit in the movement before you left?</div><div class="answer-line">{{ $value('unit_before_leaving') }}</div>
                <div class="question section-gap">15. What were the covered geographical areas of operation of your unit?</div><div class="answer-lines"><div class="answer-line">{{ $value('areas_of_operation') }}</div><div class="answer-line"></div></div>
                <div class="question section-gap">16. Did you experience any unfair, unequal, or inhumane treatment while you were in the movement?</div><div class="choice-line"><span class="box">{{ $marked('unfair_treatment', 'yes') }}</span>Yes <span class="box">{{ $marked('unfair_treatment', 'no') }}</span>No</div>
                <div class="question">If yes, detail experience/s:</div><div class="answer-lines"><div class="answer-line">{{ $value('unfair_treatment_details') }}</div><div class="answer-line"></div><div class="answer-line"></div></div>
                <div class="question section-gap">17. What are your reasons for leaving the movement?</div><div class="answer-lines"><div class="answer-line">{{ $value('reasons_leaving') }}</div><div class="answer-line"></div><div class="answer-line"></div></div>
            </td>
        </tr>
    </table>

    <h2>PART III: SECURITY ASSESSMENT (10 minutes)</h2>
    <table class="form-table compact">
        <tr>
            <td style="width:30%;" class="question">18. Did you have firearms when you were in the movement?</td>
            <td><span class="box">{{ $marked('firearms_had', 'no') }}</span>No <span class="box">{{ $marked('firearms_had', 'yes') }}</span>Yes (please accomplish Firearms Inventory Form)<br>If Yes, did you bring it/them when you left the movement? <span class="box">{{ $marked('firearms_brought', 'yes') }}</span>Yes <span class="box">{{ $marked('firearms_brought', 'no') }}</span>No<br><span style="margin-left:8mm;">If Yes, have you turned it/them in?</span> <span class="box">{{ $marked('firearms_turned_in', 'yes') }}</span>Yes <span class="box">{{ $marked('firearms_turned_in', 'no') }}</span>No<br>If yes, to who? <span class="line line-lg">{{ $value('firearms_turned_in_to') }}</span><br>If no, why? <span class="line line-lg">{{ $value('firearms_not_turned_in_reason') }}</span></td>
        </tr>
    </table>
</section>

<section class="page page-3">
    <table class="form-table compact">
        <tr><td style="width:30%;" class="question">19. Did you have explosives when you were in the movement?</td><td><span class="box">{{ $marked('explosives_had', 'no') }}</span>No <span class="box">{{ $marked('explosives_had', 'yes') }}</span>Yes (please accomplish Explosives Inventory Form)<br>If Yes, did you bring it/them when you left the movement? <span class="box">{{ $marked('explosives_brought', 'yes') }}</span>Yes <span class="box">{{ $marked('explosives_brought', 'no') }}</span>No<br>If Yes, have you turned it/them in? <span class="box">{{ $marked('explosives_turned_in', 'yes') }}</span>Yes <span class="box">{{ $marked('explosives_turned_in', 'no') }}</span>No<br>If yes, to who? <span class="line line-lg">{{ $value('explosives_turned_in_to') }}</span><br>If no, why? <span class="line line-lg">{{ $value('explosives_not_turned_in_reason') }}</span></td></tr>
        <tr><td rowspan="4" class="question">20. Where are you currently staying?</td><td>No. &amp; Street: <span class="line line-xl">{{ $value('current_street') }}</span></td></tr>
        <tr><td>Sitio: <span class="line line-md">{{ $value('current_sitio') }}</span> Barangay: <span class="line line-md">{{ $value('current_barangay') }}</span></td></tr>
        <tr><td>Municipality/City: <span class="line line-md">{{ $value('current_municipality_city') }}</span> Province: <span class="line line-md">{{ $value('current_province') }}</span></td></tr>
        <tr><td>Philippine Standard Geographic Code (PSGC) - <strong>Barangay Code:</strong> <span class="line line-md">{{ $value('current_psgc_barangay_code') }}</span></td></tr>
        <tr><td colspan="2"><span class="question">21. How long have you been staying in this location?</span> <span class="line line-xs">{{ $value('stay_years') }}</span> years <span class="line line-xs">{{ $value('stay_months') }}</span> months.</td></tr>
        <tr><td rowspan="4" class="question">22. Where does your family reside?</td><td>No. &amp; Street: <span class="line line-xl">{{ $value('family_street') }}</span></td></tr>
        <tr><td>Sitio: <span class="line line-md">{{ $value('family_sitio') }}</span> Barangay: <span class="line line-md">{{ $value('family_barangay') }}</span></td></tr>
        <tr><td>Municipality/City: <span class="line line-md">{{ $value('family_municipality_city') }}</span> Province: <span class="line line-md">{{ $value('family_province') }}</span></td></tr>
        <tr><td>Contact Information: <span class="line line-xl">{{ $value('family_contact_information') }}</span></td></tr>
        <tr><td class="question">23. Contact person:<br><span class="small">(in case of emergency)</span></td><td>Name: <span class="line line-lg">{{ $value('emergency_contact_name') }}</span><br>Relationship: <span class="line line-sm">{{ $value('emergency_contact_relationship') }}</span> Contact Information: <span class="line line-sm">{{ $value('emergency_contact_information') }}</span><br>Address: <span class="line line-xl">{{ $value('emergency_contact_address') }}</span></td></tr>
        <tr><td colspan="2"><div class="question">24. If you are not currently living with your family, to what extent are the following reasons true:</div>@foreach(['unsafe_living_there' => 'I do not feel safe living there.', 'endanger_family' => 'My presence at home might endanger my family.', 'lack_basic_needs' => 'We do not have access to basic needs there.', 'no_transport' => 'I have no means of traveling back to my primary address.', 'family_conflict' => 'I am not on good terms with my family.', 'others' => 'Others'] as $option => $label)<span class="box">{{ $checked('current_separation_reasons', $option) }}</span>{{ $label }} @endforeach <span class="line line-lg">{{ $value('current_separation_other') }}</span></td></tr>
        <tr><td colspan="2"><div class="question">25. Do you have plans of relocating in the future?</div><span class="box">{{ $marked('relocate_plans', 'yes') }}</span>Yes<br><span style="margin-left:14mm;">a. Within the same municipality?</span> <span class="box">{{ $marked('relocate_same_municipality', 'yes') }}</span>Yes <span class="box">{{ $marked('relocate_same_municipality', 'no') }}</span>No<br><span style="margin-left:28mm;">If No, Where?</span> <span class="line line-lg">{{ $value('relocate_where') }}</span><br><span style="margin-left:14mm;">b. Please check all the reasons that apply why you intend to relocate in the future:</span><br><span style="margin-left:28mm;">@foreach(['lack_security' => 'Lack of security', 'no_livelihood' => 'No livelihood opportunity', 'hazardous_location' => 'Hazardous location (i.e., flood-prone, landslide risk, etc.)', 'poor_living_conditions' => 'Poor living conditions', 'others' => 'Others (please specify)'] as $option => $label)<span class="box">{{ $checked('relocation_reasons', $option) }}</span>{{ $label }} @endforeach <span class="line line-lg">{{ $value('relocation_other') }}</span></span><br><span class="box">{{ $marked('relocate_plans', 'no') }}</span>No</td></tr>
    </table>
</section>

<section class="page page-4">
    <table class="form-table compact">
        <tr>
            <td style="width:50%;"><div class="question">26. How safe do you feel at your present address?</div>@foreach(['high_threat' => 'A high threat, fear for life', 'considerable_threat' => 'A considerable threat, limited movement in the community', 'threat_avoid_areas' => 'With threat, avoid certain areas', 'little_threat' => 'Little threat, but can freely move around', 'no_threat' => 'No threat and can freely move around'] as $option => $label)<div class="choice-line"><span class="box">{{ $marked('respondent_safety', $option) }}</span>{{ $label }}</div>@endforeach<div class="question section-gap">27. If there is a threat to your life, what is/are the source/s of threat?</div>@foreach(['former_comrades' => 'Former Comrades', 'mass_base_members' => 'Mass Base members', 'private_armed_groups' => 'Private Armed Groups', 'criminal_groups' => 'Criminal Groups', 'neighbors' => 'Neighbors', 'adjacent_communities' => 'Adjacent Communities', 'others' => 'Others'] as $option => $label)<span class="box">{{ $checked('respondent_threat_sources', $option) }}</span>{{ $label }} @endforeach <span class="line line-md">{{ $value('respondent_threat_other') }}</span></td>
            <td style="width:50%;"><div class="question">28. How safe is your family in their present address?</div>@foreach(['high_threat' => 'A high threat, fear for their lives', 'considerable_threat' => 'A considerable threat, limited movement in the community', 'threat_avoid_areas' => 'With threat, avoid certain areas', 'little_threat' => 'Little threat, but can freely move around', 'no_threat' => 'No threat and can freely move around'] as $option => $label)<div class="choice-line"><span class="box">{{ $marked('family_safety', $option) }}</span>{{ $label }}</div>@endforeach<div class="question section-gap">29. If there is threat to your family's life, what is/are the source/s of threat?</div>@foreach(['former_comrades' => 'Former Comrades', 'mass_base_members' => 'Mass Base members', 'private_armed_groups' => 'Private Armed Groups', 'criminal_groups' => 'Criminal Groups', 'neighbors' => 'Neighbors', 'adjacent_communities' => 'Adjacent Communities', 'others' => 'Others'] as $option => $label)<span class="box">{{ $checked('family_threat_sources', $option) }}</span>{{ $label }} @endforeach <span class="line line-md">{{ $value('family_threat_other') }}</span></td>
        </tr>
    </table>

    <h2>PART IV: IMMEDIATE NEEDS ASSESSMENT (10 minutes)</h2>
    <table class="form-table compact">
        <tr><td rowspan="5" style="width:16%;" class="question">30. Do you have any of the following disabilities?</td><td style="width:28%;">Visual Impairment (Partial or Full) or both eyes</td><td><span class="box">{{ $marked('visual_impairment', 'no') }}</span>No <span class="box">{{ $marked('visual_impairment', 'yes') }}</span>Yes &nbsp; If yes, please specify: <span class="line line-md">{{ $value('visual_impairment_details') }}</span></td></tr>
        <tr><td>Hearing Impairment (Slight or Full) or both ears</td><td><span class="box">{{ $marked('hearing_impairment', 'no') }}</span>No <span class="box">{{ $marked('hearing_impairment', 'yes') }}</span>Yes &nbsp; If yes, please specify: <span class="line line-md">{{ $value('hearing_impairment_details') }}</span></td></tr>
        <tr><td>Speech Impairment (Slight or Full)</td><td><span class="box">{{ $marked('speech_impairment', 'no') }}</span>No <span class="box">{{ $marked('speech_impairment', 'yes') }}</span>Yes &nbsp; If yes, please specify: <span class="line line-md">{{ $value('speech_impairment_details') }}</span></td></tr>
        <tr><td>Physical Disabilities</td><td><span class="box">{{ $marked('physical_disabilities', 'no') }}</span>No <span class="box">{{ $marked('physical_disabilities', 'yes') }}</span>Yes &nbsp; If yes, please specify: <span class="line line-md">{{ $value('physical_disabilities_details') }}</span></td></tr>
        <tr><td>Other Disabilities</td><td><span class="box">{{ $marked('other_disabilities', 'no') }}</span>No <span class="box">{{ $marked('other_disabilities', 'yes') }}</span>Yes &nbsp; If yes, please specify: <span class="line line-md">{{ $value('other_disabilities_details') }}</span></td></tr>
        <tr><td rowspan="6" class="question">31. Have you received any of the following assistance?</td><td>Medical Care</td><td><span class="box">{{ $marked('medical_received', 'no') }}</span>No <span class="box">{{ $marked('medical_received', 'yes') }}</span>Yes<br>If yes, please specify how many times in past 3 months: <span class="line line-sm">{{ $value('medical_times') }}</span><br>For what condition/s: <span class="line line-lg">{{ $value('medical_conditions') }}</span></td></tr>
        @foreach(['board_lodging' => 'Board and Lodging', 'food' => 'Food', 'transport' => 'Transportation', 'psychosocial' => 'Psychosocial Debriefing'] as $prefix => $label)
            <tr><td>{{ $label }}</td><td><span class="box">{{ $marked($prefix.'_received', 'no') }}</span>No <span class="box">{{ $marked($prefix.'_received', 'yes') }}</span>Yes<br>If yes, please specify: <span class="box">{{ $checked($prefix.'_sources', 'lgu') }}</span>LGU <span class="box">{{ $checked($prefix.'_sources', 'afp_pnp') }}</span>AFP/PNP <span class="box">{{ $checked($prefix.'_sources', 'others') }}</span>Others: <span class="line line-sm">{{ $value($prefix.'_other') }}</span></td></tr>
        @endforeach
        <tr><td>Others:</td><td>{{ $value('assistance_other') }}</td></tr>
    </table>

    <table class="form-table compact frequency section-gap">
        <tr><td rowspan="5" style="width:16%;" class="question">32. Have you been experiencing the following in the past 3 months?<br><em>(If yes, please put a check below the frequency of the indicated response)</em></td><th class="symptom">Symptoms</th>@foreach(['Never', 'Rarely', 'Sometimes', 'Often', 'Always'] as $label)<th class="freq {{ $label === 'Sometimes' ? 'freq-wide' : '' }}">{{ $label }}</th>@endforeach</tr>
        @foreach(['difficulty_sleeping' => 'Difficulty Sleeping / Bad dreams', 'anxiety' => 'Anxiety', 'addictive_substances' => 'Consumption of addictive substances, alcoholic beverages, or cigarettes', 'difficulty_concentrating' => 'Difficulty concentrating and/or'] as $field => $label)
            <tr><td>{{ $label }}</td>@foreach($frequencyOptions as $option)<td class="freq"><span class="box">{{ $marked($field, $option) }}</span></td>@endforeach</tr>
        @endforeach
    </table>
</section>

<section class="page page-5">
    <table class="form-table compact frequency" style="margin-top:2mm;">
        <tr><td rowspan="9" class="continuation-note">frequency of the indicated response)</td><td class="symptom">absent-mindedness</td>@foreach($frequencyOptions as $option)<td class="freq"><span class="box">{{ $marked('difficulty_concentrating', $option) }}</span></td>@endforeach</tr>
        @foreach([
            'disengaged_environment' => 'Disengaged from environment',
            'panic_attacks' => 'Panic Attacks',
            'avoidance_people_places' => 'Avoidance of certain people or places; Who '.$value('avoidance_who'),
            'trusting_others' => 'Difficulty trusting others: Who '.$value('trusting_who'),
            'remembering_violent_incidents' => 'Remembering violent incidents',
            'violent_thoughts' => 'Violent thoughts',
            'irritability_anger' => 'Constant Irritability or Anger',
            'feelings_guilt' => 'Feelings of guilt',
        ] as $field => $label)
            <tr><td>{{ $label }}</td>@foreach($frequencyOptions as $option)<td class="freq"><span class="box">{{ $marked($field, $option) }}</span></td>@endforeach</tr>
        @endforeach
    </table>

    <div class="verification-box">
        <p>I, the undersigned, have verified and confirmed the contents of this initial interview. Any information provided in this interview is true and correct. Any misrepresentation on my part shall be sufficient ground for denial of enrolment under ECLIP and provision of immediate assistance.</p>
        <table class="signature-row"><tr><td><div class="signature-line">Signature over Printed Name</div></td><td><div class="signature-line">Date</div></td></tr></table>
    </div>
</section>
</body>
</html>
