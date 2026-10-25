<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Profiling Interview Form</title>
    <style>
        @page { size: A4 portrait; margin: 25.4mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { background: #e5e7eb; color: #000; font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; font-size: 11pt; line-height: 1.5; }
        .toolbar { width: 210mm; margin: 10px auto; text-align: right; font-size: 11pt; line-height: 1.5; }
        .toolbar a, .toolbar button { display: inline-block; margin-left: 6px; padding: 7px 12px; border: 1px solid #777; background: #fff; color: #111; text-decoration: none; font: inherit; cursor: pointer; }
        .document-pages { font-size: 11pt; line-height: 1.5; }
        .pif-page { width: 210mm; height: 297mm; margin: 12px auto; padding: 25.4mm; border: .35mm solid #cbd5e1; background: #fff; box-shadow: 0 2px 10px rgba(15, 23, 42, .18); page-break-after: always; break-after: page; }
        .pif-page:last-child { page-break-after: auto; break-after: auto; }
        .pif-page, .pif-page * { line-height: 1.5; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 2mm; font-size: 15pt; text-align: center; }
        h2 { margin: 0 0 1.5mm; font-size: 13pt; text-align: center; }
        h3 { margin: 1.2mm 0 .8mm; font-size: 12pt; }
        h1, h2, h3 { page-break-after: avoid; break-after: avoid-page; }
        p { margin-bottom: 1.5mm; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold, .answer, .record-label { font-weight: 700; }
        .line { display: inline-block; min-width: 35mm; min-height: 5mm; padding: 0 .7mm; border-bottom: .5pt solid #000; vertical-align: bottom; font-weight: 700; overflow-wrap: anywhere; }
        .line.long { min-width: 85mm; }
        .line.short { min-width: 22mm; }
        .box { display: inline-block; width: 4.2mm; min-height: 4.2mm; margin: 0 .6mm 0 .8mm; border: .55pt solid #000; font-size: 11pt; text-align: center; vertical-align: middle; font-weight: 700; }
        .options { line-height: 1.5; }
        .option { display: inline-block; margin-right: 2mm; white-space: normal; }
        .section-title { margin: 1mm 0; padding: .6mm 1mm; border: .65pt solid #000; background: #f3f4f6; font-weight: 700; text-align: center; }
        .form-table, .record-table, .legend-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .form-table thead, .record-table thead, .legend-table thead { display: table-header-group; }
        .form-table tr, .record-table tr, .legend-table tr { page-break-inside: avoid; break-inside: avoid-page; }
        .form-table th, .form-table td, .record-table th, .record-table td, .legend-table th, .legend-table td { border: .65pt solid #000; padding: .65mm .9mm; vertical-align: top; overflow-wrap: anywhere; font-size: 11pt; line-height: 1.5; }
        .form-table th, .record-table th, .legend-table th { text-align: center; font-weight: 700; }
        .record-card { margin: 0 0 1.2mm; page-break-inside: avoid; break-inside: avoid-page; }
        .record-card h3 { margin: 0; padding: .45mm .9mm; border: .65pt solid #000; border-bottom: 0; background: #f3f4f6; }
        .legend-block { margin-top: 1.2mm; page-break-inside: avoid; break-inside: avoid-page; }
        .legend-stack .legend-table + .legend-table { margin-top: .8mm; }
        .question { border: .65pt solid #000; padding: .8mm 1mm; margin-top: .8mm; page-break-inside: avoid; break-inside: avoid-page; font-size: 11pt; line-height: 1.5; }
        .question + .question { margin-top: 0; border-top: 0; }
        .choice-grid { width: 100%; border-collapse: collapse; }
        .choice-grid td { padding: .4mm .8mm; vertical-align: top; font-size: 11pt; line-height: 1.5; }
        .signature-table { width: 100%; margin-top: 2mm; border-collapse: collapse; page-break-inside: avoid; break-inside: avoid-page; }
        .signature-table td { width: 50%; padding: 0 7mm; text-align: center; font-size: 11pt; line-height: 1.5; }
        .signature-line { height: 6mm; border-bottom: .65pt solid #000; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .pif-page { width: auto; height: 246.2mm; min-height: 246.2mm; margin: 0; padding: 0; border: 0; box-shadow: none; }
        }
        .pdf-output { background: #fff; }
        .pdf-output .toolbar { display: none; }
        .pdf-output .pif-page { width: auto; height: 246.2mm; min-height: 246.2mm; margin: 0; padding: 0; border: 0; box-shadow: none; }
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
    $value = fn(string $key, mixed $default = '') => data_get($payload, $key, $default);
    $values = fn(string $key) => (array) data_get($payload, $key, []);
    $codeValue = static fn(mixed $raw): string => is_array($raw) ? implode(', ', $raw) : (string) $raw;
    $date = static fn(mixed $raw): string => filled($raw) ? \Carbon\Carbon::parse($raw)->format('m/d/y') : '';
    $selected = fn(string $key, string $option): bool => $value($key) === $option;
    $checked = fn(string $key, string $option): bool => in_array($option, $values($key), true);
    $incomeBands = $choices['income_bands'];
    $educationChoices = $choices['education'];
    $wageSources = $choices['wage_sources'];
    $selfEmployedSources = $choices['self_employed_sources'];
    $childrenRows = array_values((array) $value('children', []));
    $familyRows = array_values((array) $value('other_family_members', []));
    $governmentIds = [
        'prc_license' => 'PRC License', 'philid' => 'PHILID', 'drivers_license' => "Driver's License",
        'postal_id' => 'Postal I.D', 'senior_citizen_id' => "Senior Citizen's ID", 'passport' => 'Passport',
        'solo_parent_id' => 'Solo Parent ID', 'owwa' => 'OWWA', 'ncwdp' => 'NCWDP', 'sss' => 'SSS',
        'gsis' => 'GSIS', 'philhealth' => 'PHILHEALTH', 'others' => 'Others:',
    ];
    $currentGroups = [
        ['agriculture_fishery', 'Agriculture and Fishery', ['agricultural_crops' => 'Agricultural Crops', 'livestock' => 'Livestock', 'aquaculture' => 'Aquaculture', 'fish_capture' => 'Fish Capture'], 'current_agriculture_other'],
        ['construction', 'Construction', ['carpentry' => 'Carpentry', 'painting' => 'Painting', 'masonry' => 'Masonry', 'pipefitting' => 'Pipefitting'], 'current_construction_other'],
        ['furniture_fixture', 'Furniture and Fixture', ['furniture_making' => 'Furniture Making', 'furniture_fixing' => 'Furniture Fixing'], 'current_furniture_other'],
        ['garments', 'Garments', ['dress_making' => 'Dress Making', 'dress_fixing' => 'Dress Fixing'], 'current_garments_other'],
        ['community_services', 'Health, Social and other Community Development Services', ['barbering' => 'Barbering', 'hilot' => 'Hilot', 'health_care_service' => 'Health Care Service', 'massage_training' => 'Massage Training'], 'current_community_services_other'],
        ['land_transportation', 'Land Transportation', ['regular_vehicle' => 'Regular Vehicle', 'bus' => 'Bus', 'truck' => 'Truck', 'motorcycle' => 'Motorcycle'], 'current_transport_other'],
        ['metals_engineering', 'Metals and Engineering', ['heavy_equipment_operation' => 'Heavy Equipment Operation'], null],
        ['machine_operation', 'Machine Operation', ['mechanical_drafting' => 'Mechanical Drafting', 'mining' => 'Mining'], 'current_machine_operation_other'],
        ['culinary', 'Culinary', ['food_beverage_services' => 'Food and Beverage Services'], null],
        ['food_processing', 'Food Processing', [], 'current_food_processing_other'],
        ['other_technical', 'Other technical skills', [], 'current_other_technical_skills'],
    ];
    $desiredGroups = [
        ['agriculture_fishery', 'Agriculture and Fishery', ['agricultural_crops' => 'Agricultural Crops', 'livestock' => 'Livestock', 'aquaculture' => 'Aquaculture', 'fish_capture' => 'Fish Capture'], 'desired_agriculture_other'],
        ['construction', 'Construction', ['carpentry' => 'Carpentry', 'painting' => 'Painting', 'masonry' => 'Masonry', 'pipefitting' => 'Pipefitting'], 'desired_construction_other'],
        ['furniture_fixture', 'Furniture and Fixture', ['furniture_making' => 'Furniture Making', 'furniture_fixing' => 'Furniture Fixing'], 'desired_furniture_other'],
        ['garments', 'Garments', ['dress_making' => 'Dress Making', 'dress_fixing' => 'Dress Fixing'], 'desired_garments_other'],
        ['community_services', 'Health, Social and other Community Development Services', ['barbering' => 'Barbering', 'hilot' => 'Hilot', 'health_care_service' => 'Health Care Service', 'massage_training' => 'Massage Training'], 'desired_community_services_other'],
        ['land_transportation', 'Land Transportation', ['regular_vehicle' => 'Regular Vehicle', 'bus' => 'Bus', 'truck' => 'Truck', 'motorcycle' => 'Motorcycle'], 'desired_transport_other'],
        ['metals_engineering', 'Metals and Engineering', ['heavy_equipment_operation' => 'Heavy Equipment Operation', 'machine_operation' => 'Machine Operation', 'mechanical_drafting' => 'Mechanical Drafting', 'mining' => 'Mining'], 'desired_engineering_other'],
        ['culinary', 'Culinary', ['food_beverage_services' => 'Food and Beverage Services', 'food_processing' => 'Food Processing'], 'desired_culinary_other'],
        ['other_technical', 'Other technical skills', [], 'desired_other_technical_skills'],
    ];

    $dependentRecords = [];
    foreach ($childrenRows ?: [['__blank' => true]] as $index => $row) {
        $dependentRecords[] = ['collection' => 'children', 'number' => $index + 1, 'row' => $row, 'blank' => $childrenRows === []];
    }
    foreach ($familyRows ?: [['__blank' => true]] as $index => $row) {
        $dependentRecords[] = ['collection' => 'family', 'number' => $index + 1, 'row' => $row, 'blank' => $familyRows === []];
    }

    // One deterministic plan drives both preview and Dompdf output.
    $pagePlan = [['type' => 'administrative']];
    $pagePlan[] = ['type' => 'dependents', 'initial' => true, 'records' => array_slice($dependentRecords, 0, 2)];
    $remainingDependentRecords = array_slice($dependentRecords, 2);
    while ($remainingDependentRecords !== []) {
        $records = [];
        while ($remainingDependentRecords !== []) {
            $candidate = $remainingDependentRecords[0];
            $containsFamily = $candidate['collection'] === 'family'
                || collect($records)->contains('collection', 'family');
            $capacity = $containsFamily ? 3 : 4;
            if (count($records) >= $capacity) {
                break;
            }
            $records[] = array_shift($remainingDependentRecords);
        }
        $pagePlan[] = ['type' => 'dependents', 'initial' => false, 'records' => $records];
    }
    $pagePlan[] = ['type' => 'family_health'];
    $pagePlan[] = ['type' => 'identification_legal', 'ids' => $governmentIds];
    $pagePlan[] = ['type' => 'education_income'];
    $pagePlan[] = ['type' => 'current_skills', 'groups' => $currentGroups];
    $pagePlan[] = ['type' => 'desired_skills', 'groups' => $desiredGroups];
    $pagePlan[] = ['type' => 'ownership'];
    $pagePlan[] = ['type' => 'organizations'];
@endphp

<main class="document-pages" data-pif-adaptive-document data-pif-server-pages data-pif-page-count="{{ count($pagePlan) }}">
@foreach($pagePlan as $pageIndex => $page)
    <section class="pif-page{{ $page['type'] === 'dependents' && $page['initial'] ? ' part-one-section' : '' }}" data-pif-page="{{ $pageIndex + 1 }}" data-pif-section="{{ $page['type'] }}" @if($page['type'] === 'dependents') data-pif-dependent-page data-pif-record-count="{{ count($page['records']) }}" @endif @if($page['type'] === 'dependents' && $page['initial']) data-pif-part-one-page @endif>
        @switch($page['type'])
            @case('administrative')
                <h1>PROFILING INTERVIEW FORM (45 minutes)</h1>
                <div class="center">As of <span class="line short">{{ $date($value('as_of_date')) }}</span></div>
                <table class="form-table" style="margin-top:4mm;border:0;">
                    <tr><td style="border:0;width:30%;" class="bold">Name of Interviewer:</td><td style="border:0;border-bottom:.65pt solid #000;">{{ $value('interviewer_name') }}</td></tr>
                    <tr><td style="border:0"></td><td style="border:0" class="center">(Last Name, First Name, Middle Name)</td></tr>
                    <tr><td style="border:0" class="bold">Office and Designation:</td><td style="border:0;border-bottom:.65pt solid #000;">{{ $value('interviewer_office_designation') }}</td></tr>
                </table>
                <table class="form-table" style="margin:2mm 0 3mm;border:0;">
                    <tr><td style="border:0;width:23%;" class="bold">Date of Interview:</td><td style="border:0;border-bottom:.65pt solid #000;width:27%;" class="center">{{ $date($value('interview_date')) }}</td><td style="border:0;width:23%;" class="bold center">Date of Submission:</td><td style="border:0;border-bottom:.65pt solid #000;width:27%;" class="center">{{ $date($value('submission_date')) }}</td></tr>
                    <tr><td style="border:0"></td><td style="border:0" class="center">(MM/DD/YY)</td><td style="border:0"></td><td style="border:0" class="center">(MM/DD/YY)</td></tr>
                </table>
                <div class="question">
                    <p>This profiling interview is being conducted by the <span class="line long">{{ $value('conducting_entity') }}</span> to determine the medium- and long-term needs of Former Rebels (FRs) and Former Violent Extremists (FVEs) as a requirement of reintegration assistance.</p>
                    <p>You will be asked to answer several questions. These will include details about yourself and your family. Your participation in this interview is voluntary. Should you decide to participate in this interview, please know that you have the right to withdraw at any time during its conduct. Please feel free to ask questions before and during the interview. At the end of this interview, you will be asked to validate and confirm your responses to the questions. All information obtained will be kept strictly confidential.</p>
                    <p>Thank you.</p>
                </div>
                <div class="question">
                    <p>I, the undersigned, have been briefed properly by the interviewer as regards to the nature and purpose of this profiling interview. I give my full consent to participate in this activity.</p>
                    <table class="signature-table"><tr><td><div class="signature-line"></div>Signature over Complete Name</td><td><div class="signature-line"></div>Date</td></tr></table>
                </div>
                <table class="form-table" style="margin-top:2mm;border:0;width:75%;">
                    <tr><td style="border:0;width:28%;" class="bold">Name Encoder:</td><td style="border:0;border-bottom:.65pt solid #000;">{{ $value('encoder_name') }}</td></tr>
                    <tr><td style="border:0" class="bold">Office and Designation:</td><td style="border:0;border-bottom:.65pt solid #000;">{{ $value('encoder_office_designation') }}</td></tr>
                    <tr><td style="border:0" class="bold">Date Encoded:</td><td style="border:0;border-bottom:.65pt solid #000;">{{ $date($value('date_encoded')) }}</td></tr>
                </table>
                @break

            @case('dependents')
                <h2>PART I: PROFILE OF DEPENDENTS{{ $page['initial'] ? ' (20 minutes)' : ' (continued)' }}</h2>
                @if($page['initial'])
                    <table class="form-table"><tr><th style="width:20%">1. Full Name:</th><td><span class="record-label">Last Name:</span><br><span class="answer">{{ $value('respondent_last_name') }}</span></td><td><span class="record-label">First Name:</span><br><span class="answer">{{ $value('respondent_first_name') }}</span></td><td><span class="record-label">Middle Name:</span><br><span class="answer">{{ $value('respondent_middle_name') }}</span></td></tr></table>
                    <div class="section-title">II.A. To be accomplished by respondents with spouse/partner &nbsp; 2. PARTNER</div>
                    <article class="record-card">
                        <table class="record-table">
                            <tr><th colspan="3">NAME</th></tr>
                            <tr><td><span class="record-label">First name</span>: {{ $value('partner_first_name') }}</td><td><span class="record-label">Middle Name</span>: {{ $value('partner_middle_name') }}</td><td><span class="record-label">Last name</span>: {{ $value('partner_last_name') }}</td></tr>
                            <tr><td><span class="record-label">SEX</span>: <span class="box">{{ $selected('partner_sex','male') ? 'X' : '' }}</span>Male <span class="box">{{ $selected('partner_sex','female') ? 'X' : '' }}</span>Female</td><td><span class="record-label">BIRTHDATE (MD/Y)</span>: {{ $date($value('partner_birthdate')) }}</td><td><span class="record-label">HIGHEST EDUC. LEVEL COMPLETED</span>: {{ $value('partner_education') }}</td></tr>
                            <tr><td colspan="3"><span class="record-label">SOURCE/S OF INCOME (See code below)</span>: {{ $codeValue($value('partner_income_sources')) }}</td></tr>
                        </table>
                    </article>
                @endif
                @php
                    $lastCollection = null;
                @endphp
                @foreach($page['records'] as $record)
                    @if($record['collection'] !== $lastCollection)
                        <div class="section-title">{{ $record['collection'] === 'children' ? 'II.B. To be accomplished by single respondents — CHILDREN' : '3. OTHER MEMBERS OF THE FAMILY' }}</div>
                        @php
                            $lastCollection = $record['collection'];
                        @endphp
                    @endif
                    @if($record['collection'] === 'children')
                        <article class="record-card" data-pif-child-row="{{ $record['blank'] ? '' : $record['number'] }}" @if($record['blank']) data-pif-empty-child-row @endif>
                            <h3>Child {{ $record['number'] }}</h3>
                            <table class="record-table">
                                <tr><td><span class="record-label">First name</span>: {{ data_get($record['row'], 'first_name') }}</td><td><span class="record-label">Middle Name</span>: {{ data_get($record['row'], 'middle_name') }}</td><td><span class="record-label">Last name</span>: {{ data_get($record['row'], 'last_name') }}</td><td><span class="record-label">SEX</span>: <span class="box">{{ data_get($record['row'], 'sex') === 'male' ? 'X' : '' }}</span>Male <span class="box">{{ data_get($record['row'], 'sex') === 'female' ? 'X' : '' }}</span>Female</td></tr>
                                <tr><td><span class="record-label">CIVIL STATUS</span>: {{ data_get($record['row'], 'civil_status') }}</td><td><span class="record-label">BIRTHDATE (MD/Y)</span>: {{ $date(data_get($record['row'], 'birthdate')) }}</td><td colspan="2"><span class="record-label">IN SCHOOL</span>: <span class="box">{{ data_get($record['row'], 'in_school') === 'yes' ? 'X' : '' }}</span>YES <span class="box">{{ data_get($record['row'], 'in_school') === 'no' ? 'X' : '' }}</span>NO</td></tr>
                                <tr><td colspan="2"><span class="record-label">HIGHEST EDUC. LEVEL COMPLETED</span>: {{ data_get($record['row'], 'education') }}</td><td colspan="2"><span class="record-label">SOURCE/S OF INCOME (See code)</span>: {{ $codeValue(data_get($record['row'], 'income_sources')) }}</td></tr>
                            </table>
                        </article>
                    @else
                        <article class="record-card" data-pif-family-row="{{ $record['blank'] ? '' : $record['number'] }}" @if($record['blank']) data-pif-empty-family-row @endif>
                            <h3>Other Member of the Family {{ $record['number'] }}</h3>
                            <table class="record-table">
                                <tr><td><span class="record-label">First name</span>: {{ data_get($record['row'], 'first_name') }}</td><td><span class="record-label">Middle Name</span>: {{ data_get($record['row'], 'middle_name') }}</td><td><span class="record-label">Last name</span>: {{ data_get($record['row'], 'last_name') }}</td><td><span class="record-label">SEX</span>: <span class="box">{{ data_get($record['row'], 'sex') === 'male' ? 'X' : '' }}</span>Male <span class="box">{{ data_get($record['row'], 'sex') === 'female' ? 'X' : '' }}</span>Female</td></tr>
                                <tr><td><span class="record-label">RELATION (See code below)</span>: {{ data_get($record['row'], 'relation') }}</td><td><span class="record-label">CIVIL STATUS</span>: {{ data_get($record['row'], 'civil_status') }}</td><td><span class="record-label">BIRTHDATE (MD/Y)</span>: {{ $date(data_get($record['row'], 'birthdate')) }}</td><td><span class="record-label">IN SCHOOL</span>: <span class="box">{{ data_get($record['row'], 'in_school') === 'yes' ? 'X' : '' }}</span>YES <span class="box">{{ data_get($record['row'], 'in_school') === 'no' ? 'X' : '' }}</span>NO</td></tr>
                                <tr><td colspan="2"><span class="record-label">HIGHEST EDUC. LEVEL COMPLETED</span>: {{ data_get($record['row'], 'education') }}</td><td colspan="2"><span class="record-label">SOURCE/S OF INCOME (See code below)</span>: {{ $codeValue(data_get($record['row'], 'income_sources')) }}</td></tr>
                            </table>
                        </article>
                    @endif
                @endforeach
                @php
                    $pageHasChildren = collect($page['records'])->contains('collection', 'children');
                    $pageHasFamily = collect($page['records'])->contains('collection', 'family');
                @endphp
                <div class="legend-block">
                    <div class="legend-stack">
                    @if($pageHasChildren || $pageHasFamily)
                        <table class="legend-table"><thead><tr><th colspan="4">Source/s of Income Codes:</th></tr></thead><tbody>@foreach(array_chunk($incomeCodes, 4, true) as $group)<tr>@foreach($group as $code => $label)<td><strong>{{ $code }}</strong> - {{ $label }}</td>@endforeach @for($cell = count($group); $cell < 4; $cell++)<td></td>@endfor</tr>@endforeach</tbody></table>
                    @endif
                    @if($pageHasFamily)
                        <table class="legend-table"><thead><tr><th colspan="4">Relationship Codes for Other Members of the Family:</th></tr></thead><tbody>@foreach(array_chunk($relationCodes, 4, true) as $group)<tr>@foreach($group as $code => $label)<td><strong>{{ $code }}</strong> - {{ $label }}</td>@endforeach @for($cell = count($group); $cell < 4; $cell++)<td></td>@endfor</tr>@endforeach</tbody></table>
                    @endif
                    </div>
                </div>
                @break

            @case('family_health')
                <h2>PART I: PROFILE OF DEPENDENTS (continued)</h2>
                <table class="form-table">
                    <tr><th>TOTAL NUMBER OF CHILDREN:</th><td class="answer">{{ $value('total_children') }}</td></tr>
                    <tr><th>TOTAL NUMBER OF FAMILY MEMBERS:</th><td class="answer">{{ $value('total_family_members') }}</td></tr>
                    <tr><th>II.C Total monthly household income</th><td class="options">@foreach($incomeBands as $key => $label)<span class="option"><span class="box">{{ $selected('monthly_household_income_band',$key) ? 'X' : '' }}</span>{{ $label }}</span>@endforeach</td></tr>
                    <tr><th>4. What is your average total monthly income?</th><td class="answer">{{ $value('average_monthly_income') }}</td></tr>
                </table>
                <h2 style="margin-top:2mm">PART II: NEEDS AND VULNERABILITIES (10 minutes)</h2>
                <table class="form-table"><thead><tr><th style="width:22%">5. Does your family have problems with:</th><th>Answer and details</th><th style="width:22%">How many members?</th></tr></thead><tbody>
                    @foreach(['eyesight_problem' => 'EyeSight','hearing_problem' => 'Hearing','speech_problem' => 'Speech','physical_disability' => 'Physical Disabilities','other_health_concern' => 'Other illness / Health Concern'] as $field => $label)
                        <tr><td>{{ $label }}</td><td><span class="box">{{ $selected($field,'yes') ? 'X' : '' }}</span>Yes &nbsp; if yes please specify: <span class="line short">{{ $value($field.'_details') }}</span><br><span class="box">{{ $selected($field,'no') ? 'X' : '' }}</span>No</td><td>{{ $value($field.'_count') }}</td></tr>
                    @endforeach
                </tbody></table>
                <table class="form-table"><thead><tr><th style="width:31%">6. Are you or your family members a member of any of the following?</th><th>YES<br>If yes, who?</th><th>NO</th><th>DON'T KNOW</th></tr></thead><tbody>
                    @foreach(['sss'=>'SSS','gsis'=>'GSIS','philhealth'=>'PHILHEALTH','pagibig'=>'PAG-IBIG','4ps'=>"DSWD 4P's",'other'=>'Other programs:'] as $program=>$label)
                        <tr><td>{{ $label }} @if($program==='other')<strong>{{ $value('social_protection_other_name') }}</strong>@endif</td><td>{{ $selected('social_protection_'.$program,'yes') ? 'X' : '' }} {{ $value('social_protection_'.$program.'_who') }}</td><td class="center">{{ $selected('social_protection_'.$program,'no') ? 'X' : '' }}</td><td class="center">{{ $selected('social_protection_'.$program,'dont_know') ? 'X' : '' }}</td></tr>
                    @endforeach
                </tbody></table>
                @break

            @case('identification_legal')
                <h2>PART II: NEEDS AND VULNERABILITIES (continued)</h2>
                <h3>7. Please check if you or any of your family members hold any of the following government identification cards.</h3>
                <table class="form-table"><thead><tr><th>Identification Card</th><th>If Yes</th><th>If Yes, is it valid or expired?</th></tr></thead><tbody>
                    @foreach($page['ids'] as $id => $label)
                        <tr><td>{{ $label }} @if($id==='others'){{ $value('other_government_id_name') }}@endif</td><td><span class="box">{{ $value("government_ids.{$id}.held") === 'yes' ? 'X' : '' }}</span>Yes <span class="box">{{ $value("government_ids.{$id}.held") === 'no' ? 'X' : '' }}</span>No</td><td><span class="box">{{ $value("government_ids.{$id}.validity") === 'valid' ? 'X' : '' }}</span>Valid <span class="box">{{ $value("government_ids.{$id}.validity") === 'expired' ? 'X' : '' }}</span>Expired</td></tr>
                    @endforeach
                </tbody></table>
                <p><strong>Valid</strong> - useable or was used recently &nbsp;&nbsp; <strong>Expired</strong> - not useable and needs issuance/replacement</p>
                <div class="question"><strong>8. Do you have a birth certificate?</strong> @foreach($choices['yes_no_unknown'] as $key=>$label)<span class="option"><span class="box">{{ $selected('has_birth_certificate',$key) ? 'X' : '' }}</span>{{ $label }}</span>@endforeach</div>
                <div class="question"><strong>9. To your knowledge, do you have any legal cases while you were still in the movement?</strong><br>@foreach($choices['yes_no_unknown_unsure'] as $key=>$label)<span class="option"><span class="box">{{ $selected('has_legal_cases',$key) ? 'X' : '' }}</span>{{ $label }}</span>@endforeach</div>
                @break

            @case('education_income')
                <h2>PART III: CAPABILITIES AND RESOURCES (10 minutes)</h2>
                <div class="question"><strong>11. Highest Educational Attainment</strong><table class="choice-grid"><tr>@foreach($educationChoices as $key=>$label)<td><span class="box">{{ $selected('highest_education',$key) ? 'X' : '' }}</span>{{ $label }}@if($key==='elementary_level') (Gr. {{ $value('elementary_level_year') }})@elseif($key==='high_school_level') (Yr. {{ $value('high_school_level_year') }})@elseif($key==='college_level') (Yr. {{ $value('college_level_year') }})@endif</td>@if($loop->iteration % 2 === 0)</tr><tr>@endif @endforeach</tr></table></div>
                <div class="question"><strong>12. Source of Living or Income</strong>
                    @foreach(['prior'=>'a. Prior to joining the movement, what was your main source of income?','current'=>'b. What is your current main source of income?'] as $period=>$heading)
                        <h3>{{ $heading }}</h3>
                        <div><span class="box">{{ $selected($period.'_income_type','wage_earner') ? 'X' : '' }}</span>{{ $period==='current' ? 'Regular Wage Earner' : 'Wage Earner' }}</div>
                        <div>@foreach($wageSources as $key=>$label)<span class="option"><span class="box">{{ $checked($period.'_wage_sources',$key) ? 'X' : '' }}</span>{{ $label }}</span>@endforeach <span class="line short">{{ $value($period.'_wage_other') }}</span></div>
                        <div><span class="box">{{ $selected($period.'_income_type','self_employed') ? 'X' : '' }}</span>Self-employed</div>
                        <div>@foreach($selfEmployedSources as $key=>$label)<span class="option"><span class="box">{{ $checked($period.'_self_employed_sources',$key) ? 'X' : '' }}</span>{{ $label }}</span>@endforeach <span class="line short">{{ $value($period.'_self_employed_other') }}</span></div>
                        <div><span class="box">{{ $selected($period.'_income_type','others') ? 'X' : '' }}</span>Others, please specify: <span class="line long">{{ $value($period.'_income_other') }}</span></div>
                    @endforeach
                </div>
                @break

            @case('current_skills')
                <h2>PART III: CAPABILITIES AND RESOURCES (continued)</h2>
                <h3>13. What are your current technical skills? (Check all that applies)</h3>
                @foreach($page['groups'] as [$category, $label, $items, $otherField])
                    <div class="question"><span class="box">{{ $checked('current_skill_categories',$category) ? 'X' : '' }}</span><strong>{{ $label }}</strong><br>@foreach($items as $key=>$item)<span class="option"><span class="box">{{ $checked('current_skills',$key) ? 'X' : '' }}</span>{{ $item }}</span>@endforeach @if($otherField)<span class="option">Others, please specify: <span class="line short">{{ $value($otherField) }}</span></span>@endif</div>
                @endforeach
                @break

            @case('desired_skills')
                <h2>PART III: CAPABILITIES AND RESOURCES (continued)</h2>
                <h3>13b. What technical skills would you and your family members want to learn? (Check all that applies)</h3>
                @foreach($page['groups'] as [$category, $label, $items, $otherField])
                    <div class="question"><span class="box">{{ $checked('desired_skill_categories',$category) ? 'X' : '' }}</span><strong>{{ $label }}</strong><br>@foreach($items as $key=>$item)<span class="option"><span class="box">{{ $checked('desired_skills',$key) ? 'X' : '' }}</span>{{ $item }}</span>@endforeach @if($otherField)<span class="option">Others, please specify: <span class="line short">{{ $value($otherField) }}</span></span>@endif</div>
                @endforeach
                @break

            @case('ownership')
                <h2>PART III: CAPABILITIES AND RESOURCES (continued)</h2>
                <h3>14. What kind of ownership do you hold?</h3>
                <table class="form-table">
                    <tr><th style="width:25%">House</th><td class="options">@foreach($choices['house_tenures'] as $key=>$label)<span class="option"><span class="box">{{ $checked('house_tenures',$key) ? 'X' : '' }}</span>{{ $label }}</span>@endforeach<br>Rent-free with consent of owner, specify: <span class="line long">{{ $value('house_rent_free_owner_details') }}</span><br>Others, please specify: <span class="line long">{{ $value('house_tenure_other') }}</span><br><strong>Materials:</strong> @foreach($choices['house_materials'] as $key=>$label)<span class="option"><span class="box">{{ $checked('house_materials',$key) ? 'X' : '' }}</span>{{ $label }}</span>@endforeach</td></tr>
                    <tr><th>Residential Lot</th><td>@foreach($choices['residential_tenures'] as $key=>$label)<span class="option"><span class="box">{{ $checked('residential_lot_tenures',$key) ? 'X' : '' }}</span>{{ $label }}</span>@endforeach<br>Other Forms of Tenure, please specify: <span class="line long">{{ $value('residential_lot_other') }}</span></td></tr>
                    <tr><th>Agricultural Land</th><td>@foreach($choices['agricultural_tenures'] as $key=>$label)<span class="option"><span class="box">{{ $checked('agricultural_land_tenures',$key) ? 'X' : '' }}</span>{{ $label }}</span>@endforeach<br>Other Forms of Tenure, please specify: <span class="line long">{{ $value('agricultural_land_other') }}</span></td></tr>
                </table>
                @break

            @case('organizations')
                <h2>PART IV: SOCIO POLITICAL PARTICIPATION (5 minutes)</h2>
                <div class="question"><strong>15. Were you a member of any community based organization prior to joining the movement?</strong><br><span class="box">{{ $selected('prior_organization_member','yes') ? 'X' : '' }}</span>Yes. If yes, please specify type of organization<br>
                    @foreach($choices['prior_organizations'] as $key=>$label)<span class="option"><span class="box">{{ $checked('prior_organization_types',$key) ? 'X' : '' }}</span>{{ $label }}@if($key==='cooperative') <span class="line short">{{ $value('prior_cooperative_details') }}</span>@elseif($key==='workers_union') <span class="line short">{{ $value('prior_workers_union_details') }}</span>@elseif($key==='barangay_committee') <span class="line short">{{ $value('prior_barangay_group_details') }}</span>@elseif($key==='ngo_po') <span class="line short">{{ $value('prior_ngo_details') }}</span>@elseif($key==='others') <span class="line short">{{ $value('prior_organization_other') }}</span>@endif</span><br>@endforeach
                    <span class="box">{{ $selected('prior_organization_member','no') ? 'X' : '' }}</span>No
                </div>
                <div class="question"><strong>16. Are you currently a member of any community based organization?</strong><br><span class="box">{{ $selected('current_organization_member','yes') ? 'X' : '' }}</span>Yes. If yes, please specify type of organization<br>
                    @foreach($choices['current_organizations'] as $key=>$label)<span class="option"><span class="box">{{ $checked('current_organization_types',$key) ? 'X' : '' }}</span>{{ $label }}@if($key==='others') <span class="line short">{{ $value('current_organization_other') }}</span>@endif</span><br>@endforeach
                    <span class="box">{{ $selected('current_organization_member','no') ? 'X' : '' }}</span>No. Do you have plans of joining later?<br>
                    <span class="box">{{ $selected('plans_join_organization','yes') ? 'X' : '' }}</span>Yes. If yes, please specify type of organization <span class="line long">{{ $value('planned_organization') }}</span><br>
                    <span class="box">{{ $selected('plans_join_organization','no') ? 'X' : '' }}</span>No. Why? <span class="line long">{{ $value('no_join_reason') }}</span><br>
                    <span class="box">{{ $selected('plans_join_organization','not_sure') ? 'X' : '' }}</span>Not Sure. Why? <span class="line long">{{ $value('not_sure_join_reason') }}</span>
                </div>
                @foreach(['registered_voter'=>'17. Are you a registered voter?','plans_to_vote'=>'18. Are you going to vote this coming election?','plans_to_run_for_office'=>'19. Do you plan to run in any elective position?'] as $field=>$label)<div class="question"><strong>{{ $label }}</strong> @foreach($choices['yes_no_unsure'] as $key=>$option)<span class="option"><span class="box">{{ $selected($field,$key) ? 'X' : '' }}</span>{{ $option }}</span>@endforeach</div>@endforeach
                <div class="question" style="margin-top:3mm;padding:2mm;">
                    <p>I, the undersigned, have verified and confirmed the contents of this profiling interview. Any information provided in this profiling interview is true and correct. Any misrepresentation on my part shall be sufficient ground for denial of provision of DILG-administered Package of Assistance.</p>
                    <table class="signature-table"><tr><td><div class="signature-line"></div>Signature over Complete Name</td><td><div class="signature-line"></div></td></tr></table>
                </div>
                @break
        @endswitch
    </section>
@endforeach
</main>
</body>
</html>
