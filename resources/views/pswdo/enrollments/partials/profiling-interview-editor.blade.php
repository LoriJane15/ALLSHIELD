@php
    $pifValue = fn(string $key, mixed $default = '') => old('draft.'.$key, data_get($payload, $key, $default));
    $pifArray = fn(string $key) => (array) old('draft.'.$key, data_get($payload, $key, []));
    $pifRows = fn(string $key) => array_values($pifArray($key)) ?: [[]];
    $pifCode = static fn(mixed $value): string => is_array($value) ? implode(', ', $value) : (string) $value;
    $childrenRows = $pifRows('children');
    $familyRows = $pifRows('other_family_members');
    $yesNo = ['yes' => 'Yes', 'no' => 'No'];
    $yesNoUnknown = ['yes' => 'Yes', 'no' => 'No', 'dont_know' => "Don't Know"];
    $incomeCodes = [
        '1' => 'Stay-home/Housewife Industry', '2' => 'Home-based Business', '3' => 'Agriculture-based',
        '4' => 'Regular Wage Earner', '5' => 'Crafts/Cottage', '6' => 'Trade', '7' => 'Professional', '8' => 'Others',
    ];
    $relationCodes = [
        '1' => 'Grand Parents (including in-laws)', '2' => 'Parent/Parent-in-law (including in-laws)',
        '3' => 'Aunt/Uncle (including in-laws)', '4' => 'Cousin (including in-laws)',
        '5' => 'Brother/Sister', '6' => 'Grand Child', '7' => 'Others',
    ];
    $incomeBands = [
        '500_below' => '500 and below', '501_2500' => '501php - 2,500php', '2500_5000' => '2,500php - 5,000php',
        '5001_10000' => '5,001php - 10,000php', '10001_15000' => '10,001php - 15,000php',
        '15001_20000' => '15,001php - 20,000php', '20001_above' => '20,001php and above', 'dont_know' => "I don't know",
    ];
    $educationChoices = [
        'did_not_enter_school' => 'Did not enter school', 'preschool' => 'Preschool', 'elementary_level' => 'Elementary Level',
        'elementary_graduate' => 'Elementary Graduate', 'high_school_level' => 'High School Level',
        'high_school_graduate' => 'High School Graduate', 'college_level' => 'College Level',
        'college_graduate' => 'College graduate', 'post_graduate' => 'Post Graduate (Masters, Doctorate)',
        'als' => 'ALS', 'vocational' => 'Vocational',
    ];
    $wageSources = [
        'government' => 'Government (Public Sector)', 'private_firm' => 'Private Firm', 'ngo_po' => 'NGO/PO',
        'private_individual' => 'Private Individual', 'seasonal_worker' => 'Seasonal worker', 'others' => 'Others, please specify',
    ];
    $selfEmployedSources = [
        'farmer_agriculture' => 'Farmer/Agriculture-based', 'fisheries' => 'Fisheries', 'home_business' => 'Home-based business',
        'cottage_industry' => 'Cottage Industry', 'trader' => 'Trader', 'local_transport' => 'Local Transport Service',
        'others' => 'Others, please specify',
    ];
    $skillCategories = [
        'agriculture_fishery' => 'Agriculture and Fishery', 'construction' => 'Construction',
        'furniture_fixture' => 'Furniture and Fixture', 'garments' => 'Garments',
        'community_services' => 'Health, Social and other Community Development Services',
        'land_transportation' => 'Land Transportation', 'metals_engineering' => 'Metals and Engineering',
        'machine_operation' => 'Machine Operation', 'culinary' => 'Culinary', 'food_processing' => 'Food Processing',
        'other_technical' => 'Other technical skills',
    ];
    $desiredSkillCategories = array_diff_key($skillCategories, ['machine_operation' => true, 'food_processing' => true]);
    $currentSkills = [
        'agricultural_crops' => 'Agricultural Crops', 'livestock' => 'Livestock', 'aquaculture' => 'Aquaculture',
        'fish_capture' => 'Fish Capture', 'carpentry' => 'Carpentry', 'painting' => 'Painting', 'masonry' => 'Masonry',
        'pipefitting' => 'Pipefitting', 'furniture_making' => 'Furniture Making', 'furniture_fixing' => 'Furniture Fixing',
        'dress_making' => 'Dress Making', 'dress_fixing' => 'Dress Fixing', 'barbering' => 'Barbering', 'hilot' => 'Hilot',
        'health_care_service' => 'Health Care Service', 'massage_training' => 'Massage Training',
        'regular_vehicle' => 'Regular Vehicle', 'bus' => 'Bus', 'truck' => 'Truck', 'motorcycle' => 'Motorcycle',
        'heavy_equipment_operation' => 'Heavy Equipment Operation', 'mechanical_drafting' => 'Mechanical Drafting',
        'mining' => 'Mining', 'food_beverage_services' => 'Food and Beverage Services',
    ];
    $desiredSkills = $currentSkills + ['machine_operation' => 'Machine Operation', 'food_processing' => 'Food Processing'];
    $otherSkillFields = [
        'agriculture_other' => 'Agriculture and Fishery - Others, please specify',
        'construction_other' => 'Construction - Others, please specify',
        'furniture_other' => 'Furniture and Fixture - Others, please specify',
        'garments_other' => 'Garments - Others, please specify',
        'community_services_other' => 'Health, Social and other Community Development Services - Others, please specify',
        'transport_other' => 'Land Transportation - Others, please specify',
    ];
@endphp

<form id="pswdoPifDraftForm" class="pswdo-pif-editor" data-editable="{{ $canEdit ? 'true' : 'false' }}" @if($canEdit) method="POST" action="{{ $updateUrl }}" @endif>
    @if($canEdit)
        @csrf
        @method('PUT')
        <input id="pswdoPifRevision" type="hidden" name="revision" value="{{ $draft?->revision ?? 0 }}">
    @else
        <fieldset disabled>
    @endif

    <div class="pswdo-eclip-group">
        <h3>PROFILING INTERVIEW FORM (45 minutes)</h3>
        <div class="pswdo-eclip-grid">
            @foreach([
                'as_of_date' => ['As of', 'date'], 'interviewer_name' => ['Name of Interviewer', 'text'],
                'interviewer_office_designation' => ['Office and Designation', 'text'],
                'interview_date' => ['Date of Interview', 'date'], 'submission_date' => ['Date of Submission', 'date'],
                'conducting_entity' => ['This profiling interview is being conducted by the', 'text'],
                'encoder_name' => ['Name Encoder', 'text'], 'encoder_office_designation' => ['Office and Designation', 'text'],
                'date_encoded' => ['Date Encoded', 'date'],
            ] as $field => [$label, $type])
                <div><label class="pswdo-eclip-label" for="pif-{{ str_replace('_', '-', $field) }}">{{ $label }}</label><input class="form-control" id="pif-{{ str_replace('_', '-', $field) }}" type="{{ $type }}" name="draft[{{ $field }}]" maxlength="500" value="{{ $pifValue($field) }}"></div>
            @endforeach
        </div>
    </div>

    <div class="pswdo-eclip-group">
        <h3>PART I: PROFILE OF DEPENDENTS (20 minutes)</h3>
        <h4 class="h6 font-weight-bold">1. Full Name:</h4>
        <div class="pswdo-eclip-grid">
            @foreach(['respondent_last_name' => 'Last Name', 'respondent_first_name' => 'First Name', 'respondent_middle_name' => 'Middle Name'] as $field => $label)
                <div><label class="pswdo-eclip-label" for="pif-{{ str_replace('_', '-', $field) }}">{{ $label }}</label><input class="form-control" id="pif-{{ str_replace('_', '-', $field) }}" name="draft[{{ $field }}]" maxlength="100" value="{{ $pifValue($field) }}"></div>
            @endforeach
        </div>
        <h4 class="h6 font-weight-bold mt-3">II.A. To be accomplished by respondents with spouse/partner - 2. PARTNER</h4>
        <div class="pswdo-eclip-grid">
            @foreach(['partner_first_name' => 'First Name', 'partner_middle_name' => 'Middle Name', 'partner_last_name' => 'Last Name'] as $field => $label)<div><label class="pswdo-eclip-label">{{ $label }}</label><input class="form-control" name="draft[{{ $field }}]" maxlength="100" value="{{ $pifValue($field) }}"></div>@endforeach
            <div><label class="pswdo-eclip-label">SEX</label><div class="pswdo-iif-options pswdo-pif-compact-options">@foreach(['male' => 'Male', 'female' => 'Female'] as $value => $label)<label class="pswdo-iif-option"><input type="radio" name="draft[partner_sex]" value="{{ $value }}" @checked($pifValue('partner_sex') === $value)> {{ $label }}</label>@endforeach</div></div>
            <div><label class="pswdo-eclip-label">BIRTHDATE (MD/Y)</label><input class="form-control" type="date" name="draft[partner_birthdate]" value="{{ $pifValue('partner_birthdate') }}"></div>
            <div><label class="pswdo-eclip-label">HIGHEST EDUC. LEVEL COMPLETED</label><input class="form-control" name="draft[partner_education]" maxlength="500" value="{{ $pifValue('partner_education') }}"></div>
            <div><label class="pswdo-eclip-label" for="pif-partner-income-sources">SOURCE/S OF INCOME (See code below)</label><input class="form-control" id="pif-partner-income-sources" name="draft[partner_income_sources]" maxlength="100" pattern="\s*[1-8](?:\s*[,/]\s*[1-8])*\s*" title="Enter codes 1 to 8 separated by commas or slashes." value="{{ $pifCode($pifValue('partner_income_sources')) }}"></div>
        </div>
        @foreach(['children' => ['II.B. To be accomplished by single respondents - CHILDREN', $childrenRows, 'Add Child'], 'other_family_members' => ['3. OTHER MEMBERS OF THE FAMILY', $familyRows, 'Add Family Member']] as $collection => [$heading, $rows, $addLabel])
            <div class="pswdo-pif-table-heading"><h4 class="h6 font-weight-bold mb-0">{{ $heading }}</h4>@if($canEdit)<button class="btn btn-sm btn-outline-primary" type="button" data-pif-add-row="{{ $collection }}">{{ $addLabel }}</button>@endif</div>
            <div class="table-responsive pswdo-pif-table-wrap" data-pif-dynamic-table="{{ $collection }}"><table class="table table-bordered table-sm mb-1"><thead><tr><th>No.</th><th>First name</th><th>Middle Name</th><th>Last name</th><th>SEX</th>@if($collection === 'other_family_members')<th>RELATION (See code below)</th>@endif<th>CIVIL STATUS</th><th>BIRTHDATE (MD/Y)</th><th>IN SCHOOL YES/NO</th><th>HIGHEST EDUC. LEVEL COMPLETED</th><th>SOURCE/S OF INCOME (See code below)</th>@if($canEdit)<th>Action</th>@endif</tr></thead><tbody data-pif-rows>
                @foreach($rows as $row => $rowValues)
                    <tr data-pif-row data-row-index="{{ $row }}"><th scope="row" data-pif-row-number>{{ $row + 1 }}</th>
                        @foreach(['first_name', 'middle_name', 'last_name'] as $field)<td><input class="form-control form-control-sm" data-pif-row-field aria-label="{{ $heading }} row {{ $row + 1 }} {{ str_replace('_', ' ', $field) }}" name="draft[{{ $collection }}][{{ $row }}][{{ $field }}]" maxlength="100" value="{{ data_get($rowValues, $field, '') }}"></td>@endforeach
                        <td><select class="form-control form-control-sm" data-pif-row-field aria-label="{{ $heading }} row {{ $row + 1 }} sex" name="draft[{{ $collection }}][{{ $row }}][sex]"><option value=""></option>@foreach(['male' => 'Male', 'female' => 'Female'] as $value => $label)<option value="{{ $value }}" @selected(data_get($rowValues, 'sex') === $value)>{{ $label }}</option>@endforeach</select></td>
                        @if($collection === 'other_family_members')<td><input class="form-control form-control-sm" data-pif-row-field aria-label="Other family member row {{ $row + 1 }} relationship code" name="draft[{{ $collection }}][{{ $row }}][relation]" maxlength="1" pattern="[1-7]" title="Enter a Relationship Code from 1 to 7." value="{{ data_get($rowValues, 'relation', '') }}"></td>@endif
                        <td><input class="form-control form-control-sm" data-pif-row-field aria-label="{{ $heading }} row {{ $row + 1 }} civil status" name="draft[{{ $collection }}][{{ $row }}][civil_status]" maxlength="500" value="{{ data_get($rowValues, 'civil_status', '') }}"></td>
                        <td><input class="form-control form-control-sm" data-pif-row-field aria-label="{{ $heading }} row {{ $row + 1 }} birthdate" type="date" name="draft[{{ $collection }}][{{ $row }}][birthdate]" value="{{ data_get($rowValues, 'birthdate', '') }}"></td>
                        <td><select class="form-control form-control-sm" data-pif-row-field aria-label="{{ $heading }} row {{ $row + 1 }} in school" name="draft[{{ $collection }}][{{ $row }}][in_school]"><option value=""></option>@foreach($yesNo as $value => $label)<option value="{{ $value }}" @selected(data_get($rowValues, 'in_school') === $value)>{{ $label }}</option>@endforeach</select></td>
                        <td><input class="form-control form-control-sm" data-pif-row-field aria-label="{{ $heading }} row {{ $row + 1 }} highest education level completed" name="draft[{{ $collection }}][{{ $row }}][education]" maxlength="500" value="{{ data_get($rowValues, 'education', '') }}"></td>
                        <td><input class="form-control form-control-sm" data-pif-row-field aria-label="{{ $heading }} row {{ $row + 1 }} source or sources of income codes" name="draft[{{ $collection }}][{{ $row }}][income_sources]" maxlength="100" pattern="\s*[1-8](?:\s*[,/]\s*[1-8])*\s*" title="Enter codes 1 to 8 separated by commas or slashes." value="{{ $pifCode(data_get($rowValues, 'income_sources', '')) }}"></td>
                        @if($canEdit)<td class="pswdo-pif-row-action"><button class="btn btn-sm btn-outline-danger" type="button" data-pif-remove-row aria-label="Remove {{ $heading }} row {{ $row + 1 }}">Remove</button></td>@endif
                    </tr>
                @endforeach
            </tbody></table></div>
            @if($collection === 'other_family_members')
                <div class="pswdo-pif-code-legends" data-pif-family-legends aria-label="Official family information code references">
                    <section aria-labelledby="pif-income-code-legend">
                        <strong id="pif-income-code-legend">Source/s of Income Codes:</strong>
                        <div class="pswdo-pif-code-grid">@foreach($incomeCodes as $code => $label)<div><span class="pswdo-pif-code">{{ $code }}</span> - {{ $label }}</div>@endforeach</div>
                    </section>
                    <section aria-labelledby="pif-relationship-code-legend">
                        <strong id="pif-relationship-code-legend">Relationship Codes for Other Members of the Family:</strong>
                        <div class="pswdo-pif-code-grid">@foreach($relationCodes as $code => $label)<div><span class="pswdo-pif-code">{{ $code }}</span> - {{ $label }}</div>@endforeach</div>
                    </section>
                </div>
            @endif
        @endforeach
        <div class="pswdo-eclip-grid mt-3">
            <div><label class="pswdo-eclip-label">TOTAL NUMBER OF CHILDREN:</label><input class="form-control" type="number" min="0" max="999" name="draft[total_children]" value="{{ $pifValue('total_children') }}"></div>
            <div><label class="pswdo-eclip-label">TOTAL NUMBER OF FAMILY MEMBERS:</label><input class="form-control" type="number" min="0" max="999" name="draft[total_family_members]" value="{{ $pifValue('total_family_members') }}"></div>
            <div><label class="pswdo-eclip-label">II.C Total monthly household income</label><select class="form-control" name="draft[monthly_household_income_band]"><option value=""></option>@foreach($incomeBands as $value => $label)<option value="{{ $value }}" @selected($pifValue('monthly_household_income_band') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="pswdo-eclip-label">4. What is your average total monthly income?</label><input class="form-control" type="number" min="0" max="999999999.99" step="0.01" name="draft[average_monthly_income]" value="{{ $pifValue('average_monthly_income') }}"></div>
        </div>
    </div>

    <div class="pswdo-eclip-group">
        <h3>PART II: NEEDS AND VULNERABILITIES (10 minutes)</h3>
        <h4 class="h6 font-weight-bold">5. Does your family have problems with:</h4>
        @foreach(['eyesight_problem' => 'EyeSight', 'hearing_problem' => 'Hearing', 'speech_problem' => 'Speech', 'physical_disability' => 'Physical Disabilities', 'other_health_concern' => 'Other illness / Health Concern'] as $prefix => $label)
            <div class="pswdo-pif-question-card"><span class="pswdo-eclip-label">{{ $label }}</span><div class="pswdo-iif-options pswdo-pif-compact-options">@foreach($yesNo as $value => $option)<label class="pswdo-iif-option"><input type="radio" name="draft[{{ $prefix }}]" value="{{ $value }}" @checked($pifValue($prefix) === $value)> {{ $option }}</label>@endforeach</div><div class="pswdo-eclip-grid mt-2"><div><label class="pswdo-eclip-label">Yes - if yes please specify:</label><input class="form-control" name="draft[{{ $prefix }}_details]" maxlength="500" value="{{ $pifValue($prefix.'_details') }}"></div><div><label class="pswdo-eclip-label">How many members?</label><input class="form-control" type="number" min="0" max="999" name="draft[{{ $prefix }}_count]" value="{{ $pifValue($prefix.'_count') }}"></div></div></div>
        @endforeach
        <h4 class="h6 font-weight-bold mt-3">6. Are you or your family members a member of any of the following?</h4>
        <div class="small font-weight-bold mb-1">SOCIAL PROTECTION - YES (If yes, who?) - NO - DON'T KNOW</div>
        @foreach(['sss' => 'SSS', 'gsis' => 'GSIS', 'philhealth' => 'PHILHEALTH', 'pagibig' => 'PAG-IBIG', '4ps' => "DSWD 4P's", 'other' => 'Other programs:'] as $program => $label)
            <div class="pswdo-pif-answer-row"><div><span class="pswdo-eclip-label">{{ $label }}</span>@if($program === 'other')<input class="form-control" name="draft[social_protection_other_name]" maxlength="500" value="{{ $pifValue('social_protection_other_name') }}">@endif</div><div class="pswdo-iif-options pswdo-pif-compact-options">@foreach($yesNoUnknown as $value => $option)<label class="pswdo-iif-option"><input type="radio" name="draft[social_protection_{{ $program }}]" value="{{ $value }}" @checked($pifValue('social_protection_'.$program) === $value)> {{ $option }}</label>@endforeach</div><div><label class="pswdo-eclip-label">If yes, who?</label><input class="form-control" name="draft[social_protection_{{ $program }}_who]" maxlength="500" value="{{ $pifValue('social_protection_'.$program.'_who') }}"></div></div>
        @endforeach
        <h4 class="h6 font-weight-bold mt-3">7. Please check if you or any of your family members hold any of the following government identification cards.</h4>
        <div class="pswdo-eclip-grid small font-weight-bold mb-1"><span>Identification Card</span><span>If Yes</span><span>If Yes, is it valid or expired?</span></div>
        @foreach(['prc_license' => 'PRC License', 'philid' => 'PHILID', 'drivers_license' => "Driver's License", 'postal_id' => 'Postal I.D', 'senior_citizen_id' => "Senior Citizen's ID", 'passport' => 'Passport', 'solo_parent_id' => 'Solo Parent ID', 'owwa' => 'OWWA', 'ncwdp' => 'NCWDP', 'sss' => 'SSS', 'gsis' => 'GSIS', 'philhealth' => 'PHILHEALTH', 'others' => 'Others:'] as $id => $label)
            <div class="pswdo-pif-answer-row"><div><span class="pswdo-eclip-label">{{ $label }}</span>@if($id === 'others')<input class="form-control" name="draft[other_government_id_name]" maxlength="500" value="{{ $pifValue('other_government_id_name') }}">@endif</div><div class="pswdo-iif-options pswdo-pif-compact-options">@foreach($yesNo as $value => $option)<label class="pswdo-iif-option"><input type="radio" name="draft[government_ids][{{ $id }}][held]" value="{{ $value }}" @checked($pifValue("government_ids.{$id}.held") === $value)> {{ $option }}</label>@endforeach</div><div class="pswdo-iif-options pswdo-pif-compact-options">@foreach(['valid' => 'Valid', 'expired' => 'Expired'] as $value => $option)<label class="pswdo-iif-option"><input type="radio" name="draft[government_ids][{{ $id }}][validity]" value="{{ $value }}" @checked($pifValue("government_ids.{$id}.validity") === $value)> {{ $option }}</label>@endforeach</div></div>
        @endforeach
        <p class="small"><strong>Valid</strong> - useable or was used recently &nbsp;&nbsp; <strong>Expired</strong> - not useable and needs issuance/replacement</p>
        @foreach(['has_birth_certificate' => ['8. Do you have a birth certificate?', $yesNoUnknown], 'has_legal_cases' => ['9. To your knowledge, do you have any legal cases while you were still in the movement?', $yesNoUnknown + ['not_sure' => 'Not sure']]] as $field => [$label, $options])
            <div class="pswdo-pif-question-card"><span class="pswdo-eclip-label">{{ $label }}</span><div class="pswdo-iif-options pswdo-pif-compact-options">@foreach($options as $value => $option)<label class="pswdo-iif-option"><input type="radio" name="draft[{{ $field }}]" value="{{ $value }}" @checked($pifValue($field) === $value)> {{ $option }}</label>@endforeach</div></div>
        @endforeach
    </div>

    <div class="pswdo-eclip-group">
        <h3>PART III: CAPABILITIES AND RESOURCES (10 minutes)</h3>
        <h4 class="h6 font-weight-bold">11. Highest Educational Attainment</h4>
        <div class="pswdo-iif-options">@foreach($educationChoices as $value => $label)<label class="pswdo-iif-option"><input type="radio" name="draft[highest_education]" value="{{ $value }}" @checked($pifValue('highest_education') === $value)> {{ $label }}</label>@endforeach</div>
        <div class="pswdo-eclip-grid mt-2">@foreach(['elementary_level_year' => 'Elementary Level (Gr. ________)', 'high_school_level_year' => 'High School Level (Yr. ________)', 'college_level_year' => 'College Level (Yr. ________)'] as $field => $label)<div><label class="pswdo-eclip-label">{{ $label }}</label><input class="form-control" name="draft[{{ $field }}]" maxlength="500" value="{{ $pifValue($field) }}"></div>@endforeach</div>
        <h4 class="h6 font-weight-bold mt-3">12. Source of Living or Income</h4>
        @foreach(['prior' => 'a. Prior to joining the movement, what was your main source of income?', 'current' => 'b. What is your current main source of income?'] as $period => $heading)
            <div class="pswdo-pif-question-card">
                <h5 class="h6 font-weight-bold">{{ $heading }}</h5>
                <div class="pswdo-iif-options">@foreach(['wage_earner' => ($period === 'current' ? 'Regular Wage Earner' : 'Wage Earner'), 'self_employed' => 'Self-employed', 'others' => 'Others, please specify:'] as $value => $label)<label class="pswdo-iif-option"><input type="radio" name="draft[{{ $period }}_income_type]" value="{{ $value }}" @checked($pifValue($period.'_income_type') === $value)> {{ $label }}</label>@endforeach</div>
                <div class="pswdo-pif-choice-panel mt-2"><strong>{{ $period === 'current' ? 'Regular Wage Earner' : 'Wage Earner' }}</strong><div class="pswdo-iif-options">@foreach($wageSources as $value => $label)<label class="pswdo-iif-option"><input type="checkbox" name="draft[{{ $period }}_wage_sources][]" value="{{ $value }}" @checked(in_array($value, $pifArray($period.'_wage_sources'), true))> {{ $label }}</label>@endforeach</div><label class="pswdo-eclip-label mt-2">Others, please specify:</label><input class="form-control" name="draft[{{ $period }}_wage_other]" maxlength="500" value="{{ $pifValue($period.'_wage_other') }}"></div>
                <div class="pswdo-pif-choice-panel"><strong>Self-employed</strong><div class="pswdo-iif-options">@foreach($selfEmployedSources as $value => $label)<label class="pswdo-iif-option"><input type="checkbox" name="draft[{{ $period }}_self_employed_sources][]" value="{{ $value }}" @checked(in_array($value, $pifArray($period.'_self_employed_sources'), true))> {{ $label }}</label>@endforeach</div><label class="pswdo-eclip-label mt-2">Others, please specify:</label><input class="form-control" name="draft[{{ $period }}_self_employed_other]" maxlength="500" value="{{ $pifValue($period.'_self_employed_other') }}"></div>
                <label class="pswdo-eclip-label">Others, please specify:</label><input class="form-control" name="draft[{{ $period }}_income_other]" maxlength="500" value="{{ $pifValue($period.'_income_other') }}">
            </div>
        @endforeach
        <h4 class="h6 font-weight-bold">13. What are your current technical skills? (Check all that applies)</h4>
        <div class="pswdo-iif-options">@foreach($skillCategories as $value => $label)<label class="pswdo-iif-option"><input type="checkbox" name="draft[current_skill_categories][]" value="{{ $value }}" @checked(in_array($value, $pifArray('current_skill_categories'), true))> {{ $label }}</label>@endforeach</div>
        <div class="pswdo-iif-options mt-2">@foreach($currentSkills as $value => $label)<label class="pswdo-iif-option"><input type="checkbox" name="draft[current_skills][]" value="{{ $value }}" @checked(in_array($value, $pifArray('current_skills'), true))> {{ $label }}</label>@endforeach</div>
        <div class="pswdo-eclip-grid mt-2">@foreach($otherSkillFields as $suffix => $label)<div><label class="pswdo-eclip-label">{{ $label }}</label><input class="form-control" name="draft[current_{{ $suffix }}]" maxlength="500" value="{{ $pifValue('current_'.$suffix) }}"></div>@endforeach<div><label class="pswdo-eclip-label">Machine Operation - Others, please specify</label><input class="form-control" name="draft[current_machine_operation_other]" maxlength="500" value="{{ $pifValue('current_machine_operation_other') }}"></div><div><label class="pswdo-eclip-label">Food Processing - Others, please specify</label><input class="form-control" name="draft[current_food_processing_other]" maxlength="500" value="{{ $pifValue('current_food_processing_other') }}"></div><div><label class="pswdo-eclip-label">Other technical skills:</label><input class="form-control" name="draft[current_other_technical_skills]" maxlength="500" value="{{ $pifValue('current_other_technical_skills') }}"></div></div>
        <h4 class="h6 font-weight-bold mt-3">13b. What technical skills would you and your family members want to learn? (Check all that applies)</h4>
        <div class="pswdo-iif-options">@foreach($desiredSkillCategories as $value => $label)<label class="pswdo-iif-option"><input type="checkbox" name="draft[desired_skill_categories][]" value="{{ $value }}" @checked(in_array($value, $pifArray('desired_skill_categories'), true))> {{ $label }}</label>@endforeach</div>
        <div class="pswdo-iif-options mt-2">@foreach($desiredSkills as $value => $label)<label class="pswdo-iif-option"><input type="checkbox" name="draft[desired_skills][]" value="{{ $value }}" @checked(in_array($value, $pifArray('desired_skills'), true))> {{ $label }}</label>@endforeach</div>
        <div class="pswdo-eclip-grid mt-2">@foreach($otherSkillFields as $suffix => $label)<div><label class="pswdo-eclip-label">{{ $label }}</label><input class="form-control" name="draft[desired_{{ $suffix }}]" maxlength="500" value="{{ $pifValue('desired_'.$suffix) }}"></div>@endforeach<div><label class="pswdo-eclip-label">Metals and Engineering - Others, please specify</label><input class="form-control" name="draft[desired_engineering_other]" maxlength="500" value="{{ $pifValue('desired_engineering_other') }}"></div><div><label class="pswdo-eclip-label">Culinary - Others, please specify</label><input class="form-control" name="draft[desired_culinary_other]" maxlength="500" value="{{ $pifValue('desired_culinary_other') }}"></div><div><label class="pswdo-eclip-label">Other technical skills:</label><input class="form-control" name="draft[desired_other_technical_skills]" maxlength="500" value="{{ $pifValue('desired_other_technical_skills') }}"></div></div>
        <h4 class="h6 font-weight-bold mt-3">14. What kind of ownership do you hold?</h4>
        @foreach(['house_tenures' => ['House', ['owner_amortized' => 'Owner/being amortized', 'settler' => 'Settler', 'rented_shared' => 'Rented / Shared', 'government_dwelling' => 'Government-issued dwelling', 'rent_free_consent' => 'Rent-free with consent of owner, specify:', 'others' => 'Others, please specify']], 'house_materials' => ['Materials:', ['concrete' => 'Concrete', 'semi_concrete' => 'Semi-concrete', 'light_materials' => 'Light materials', 'typhoon_resistant' => 'Typhoon resistant']], 'residential_lot_tenures' => ['Residential Lot', ['fully_owned' => 'Fully Owned', 'tenanted' => 'Tenanted', 'lease_rented' => 'Lease/Rented', 'ancestral_domain' => 'Ancestral domain claim', 'cadt' => 'CADT', 'rent_free' => 'Rent Free', 'others' => 'Other Forms of Tenure, please specify:']], 'agricultural_land_tenures' => ['Agricultural Land', ['fully_owned' => 'Fully Owned', 'rent_free' => 'Rent Free', 'tenanted' => 'Tenanted', 'ancestral_domain' => 'Ancestral domain claim', 'cadt' => 'CADT', 'lease_rented' => 'Lease/Rented', 'clt_cloa' => 'Held under Certificate of Land Transfer (CLT) or Certificate of Land Ownership (CLOA)', 'others' => 'Other Forms of Tenure, please specify:']]] as $field => [$heading, $options])
            <h5 class="h6 font-weight-bold mt-2">{{ $heading }}</h5><div class="pswdo-iif-options">@foreach($options as $value => $label)<label class="pswdo-iif-option"><input type="checkbox" name="draft[{{ $field }}][]" value="{{ $value }}" @checked(in_array($value, $pifArray($field), true))> {{ $label }}</label>@endforeach</div>
        @endforeach
        <div class="pswdo-eclip-grid mt-2"><div><label class="pswdo-eclip-label">Rent-free with consent of owner, specify:</label><input class="form-control" name="draft[house_rent_free_owner_details]" maxlength="500" value="{{ $pifValue('house_rent_free_owner_details') }}"></div><div><label class="pswdo-eclip-label">House - Others, please specify:</label><input class="form-control" name="draft[house_tenure_other]" maxlength="500" value="{{ $pifValue('house_tenure_other') }}"></div><div><label class="pswdo-eclip-label">Residential Lot - Other Forms of Tenure, please specify:</label><input class="form-control" name="draft[residential_lot_other]" maxlength="500" value="{{ $pifValue('residential_lot_other') }}"></div><div><label class="pswdo-eclip-label">Agricultural Land - Other Forms of Tenure, please specify:</label><input class="form-control" name="draft[agricultural_land_other]" maxlength="500" value="{{ $pifValue('agricultural_land_other') }}"></div></div>
    </div>

    <div class="pswdo-eclip-group">
        <h3>PART IV: SOCIO POLITICAL PARTICIPATION (5 minutes)</h3>
        @foreach(['prior' => '15. Were you a member of any community based organization prior to joining the movement?', 'current' => '16. Are you currently a member of any community based organization?'] as $period => $heading)
            <div class="pswdo-pif-question-card"><h4 class="h6 font-weight-bold">{{ $heading }}</h4><div class="pswdo-iif-options pswdo-pif-compact-options">@foreach($yesNo as $value => $label)<label class="pswdo-iif-option"><input type="radio" name="draft[{{ $period }}_organization_member]" value="{{ $value }}" @checked($pifValue($period.'_organization_member') === $value)> {{ $label }}</label>@endforeach</div>
            @php($organizationOptions = $period === 'prior' ? ['cooperative' => 'Cooperative. Please specify', 'workers_union' => "Worker's Union. Please specify", 'barangay_committee' => 'Barangay Committee/ Task Force Please specify', 'peoples_association' => "People's Association (ex. Women's Association, Farmer's Association, etc.)", 'ngo_po' => 'NGO Please specify', 'others' => 'Others:'] : ['cooperative_microfinance' => 'Cooperative/Microfinance', 'workers_union' => "Worker's Union", 'barangay_committee' => 'Barangay Committee/ Task Force', 'peoples_association' => "People's Association (ex. Women's Association, Farmer's Association, etc.)", 'others' => 'Others:'])
            <div class="pswdo-iif-options mt-2">@foreach($organizationOptions as $value => $label)<label class="pswdo-iif-option"><input type="checkbox" name="draft[{{ $period }}_organization_types][]" value="{{ $value }}" @checked(in_array($value, $pifArray($period.'_organization_types'), true))> {{ $label }}</label>@endforeach</div>
            @if($period === 'prior')<div class="pswdo-eclip-grid mt-2">@foreach(['prior_cooperative_details' => 'Cooperative. Please specify', 'prior_workers_union_details' => "Worker's Union. Please specify", 'prior_barangay_group_details' => 'Barangay Committee/ Task Force Please specify', 'prior_ngo_details' => 'NGO Please specify', 'prior_organization_other' => 'Others:'] as $field => $label)<div><label class="pswdo-eclip-label">{{ $label }}</label><input class="form-control" name="draft[{{ $field }}]" maxlength="500" value="{{ $pifValue($field) }}"></div>@endforeach</div>@else<div class="mt-2"><label class="pswdo-eclip-label">Others:</label><input class="form-control" name="draft[current_organization_other]" maxlength="500" value="{{ $pifValue('current_organization_other') }}"></div>@endif
            </div>
        @endforeach
        <div class="pswdo-pif-question-card"><h4 class="h6 font-weight-bold">No. Do you have plans of joining later?</h4><div class="pswdo-iif-options">@foreach(['yes' => 'Yes. If yes, please specify type of organization', 'no' => 'No. Why?', 'not_sure' => 'Not Sure. Why?'] as $value => $label)<label class="pswdo-iif-option"><input type="radio" name="draft[plans_join_organization]" value="{{ $value }}" @checked($pifValue('plans_join_organization') === $value)> {{ $label }}</label>@endforeach</div>
        <div class="pswdo-eclip-grid mt-2">@foreach(['planned_organization' => 'Yes. If yes, please specify type of organization', 'no_join_reason' => 'No. Why?', 'not_sure_join_reason' => 'Not Sure. Why?'] as $field => $label)<div><label class="pswdo-eclip-label">{{ $label }}</label><input class="form-control" name="draft[{{ $field }}]" maxlength="500" value="{{ $pifValue($field) }}"></div>@endforeach</div></div>
        @foreach(['registered_voter' => '17. Are you a registered voter?', 'plans_to_vote' => '18. Are you going to vote this coming election?', 'plans_to_run_for_office' => '19. Do you plan to run in any elective position?'] as $field => $label)<div class="pswdo-pif-question-card"><span class="pswdo-eclip-label">{{ $label }}</span><div class="pswdo-iif-options pswdo-pif-compact-options">@foreach(['yes' => 'Yes', 'no' => 'No', 'not_sure' => 'Not Sure'] as $value => $option)<label class="pswdo-iif-option"><input type="radio" name="draft[{{ $field }}]" value="{{ $value }}" @checked($pifValue($field) === $value)> {{ $option }}</label>@endforeach</div></div>@endforeach
    </div>

    @unless($canEdit)</fieldset>@else<div class="text-right"><button class="btn btn-primary" type="submit"><i class="mdi mdi-content-save mr-1"></i> Save Draft</button></div>@endunless
</form>
