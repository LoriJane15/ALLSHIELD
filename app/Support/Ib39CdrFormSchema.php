<?php

namespace App\Support;

class Ib39CdrFormSchema
{
    public const VERSION = 2;

    public const MAX_ROWS = 50;

    public const APPLICABILITY = ['yes', 'na'];

    public static function sections(): array
    {
        return [
            'cover' => self::s('Cover/control information', [
                'report_date' => self::f('Report date', 'date', 20),
                'cdr_reference' => self::f('CDR reference', 'text', 100),
                'subject_name' => self::f('Name', 'text', 255),
                'alias' => self::f('Alias', 'text', 255),
            ]),
            'personal' => self::s('Personal Data', [
                'gender' => self::f('Gender', 'select', 50, ['Male', 'Female']),
                'individual_affiliation' => self::f('Individual affiliation', 'text', 1000),
                'position_held' => self::f('Position held', 'text', 1000),
                'classification' => self::f('Classification', 'text', 255),
                'last_contact' => self::f('Last contact with the group', 'text', 500),
                'duration_with_group' => self::f('Duration/stay with the group', 'text', 500),
                'documents_taken' => self::f('Documents taken from subject', 'textarea', 5000),
                'occupation_before_joining' => self::f('Occupation before joining', 'text', 500),
                'reason_for_surrender' => self::f('Reason for surrender', 'textarea'),
                'date_of_birth' => self::f('Date of birth', 'date', 20),
                'place_of_birth' => self::f('Place of birth', 'text', 1000),
                'age_at_entry' => self::f('Age at entry'),
                'present_address' => self::f('Present address', 'textarea', 2000),
                'permanent_address' => self::f('Permanent address', 'textarea', 2000),
                'civil_status' => self::f('Civil status', 'select', 100, [
                    'Single',
                    'Married',
                    'Widowed',
                    'Separated',
                    'Divorced',
                    'Live-in / Common-law',
                ]),
                'ethnic_group' => self::f('Ethnic group', 'datalist', 255, [
                    "B'laan",
                    'Manobo',
                    'Tagabawa',
                    'Bagobo',
                    'Mandaya',
                    'Mansaka',
                    "T'boli",
                    'Bisaya / Cebuano',
                    'Ilonggo / Hiligaynon',
                    'Ilocano',
                    'Tagalog',
                ]),
                'religion' => self::f('Religion', 'datalist', 255, [
                    'Roman Catholic',
                    'Islam',
                    'Evangelical / Born Again',
                    'Iglesia ni Cristo',
                    'Seventh-day Adventist',
                    'Baptist',
                    "Jehovah's Witness",
                ]),
                'educational_attainment' => self::f('Educational attainment', 'select', 500, [
                    'No Formal Education',
                    'Elementary Level / Undergraduate',
                    'Elementary Graduate',
                    'High School Level / Undergraduate',
                    'High School Graduate',
                    'Senior High School Graduate',
                    'Vocational',
                    'College Level / Undergraduate',
                    'College Graduate',
                    'Post Graduate',
                ]),
            ]),
            'physical' => self::s('Physical Description', [
                'height' => self::f('Height'),
                'weight' => self::f('Weight'),
                'build' => self::f('Build', 'select', 100, [
                    'Small',
                    'Medium',
                    'Large',
                    'Slender',
                    'Muscular',
                    'Heavy',
                    'Stout',
                ]),
                'complexion' => self::f('Complexion', 'select', 100, [
                    'Fair',
                    'Light',
                    'Medium',
                    'Tan',
                    'Dark',
                    'Brown',
                    'Fair/White',
                ]),
                'eye_color' => self::f('Eye color', 'select', 100, [
                    'Black',
                    'Brown',
                    'Dark Brown',
                    'Hazel',
                    'Blue',
                    'Green',
                    'Gray',
                ]),
                'hair_color' => self::f('Hair color', 'select', 100, [
                    'Black',
                    'Dark Brown',
                    'Light Brown',
                    'Gray',
                    'White',
                    'Blonde',
                    'Bald',
                ]),
                'identification_marks' => self::f('Identification marks', 'textarea', 3000),
            ]),
            'neutralization' => self::s('II. Circumstances of Neutralization', [
                'neutralization_classification' => self::f('Status of classification', 'select', 500, [
                    'Surrendered',
                    'Captured / Apprehended',
                    'Recovered',
                    'Apprehended',
                ]),
                'arrest_details' => self::f('Details of arrest/neutralization', 'textarea'),
                'reason_for_leaving' => self::f('Reason for leaving the group', 'textarea'),
            ]),
            'entry_background' => self::s('III. Background of Entry in the CTM', [
                'recruiters' => self::f('Recruiter/s', 'text', 2000), 'recruitment_date' => self::f('Date/period of recruitment', 'text', 255), 'recruitment_place' => self::f('Place of recruitment', 'text', 2000),
                'propaganda_used' => self::f('Propaganda used', 'textarea'), 'reason_for_joining' => self::f('Reason for joining', 'textarea'), 'latest_position' => self::f('Latest position in the organization', 'text', 1000),
                'promotion_circumstances' => self::f('Promotion circumstances', 'textarea'), 'disciplinary_circumstances' => self::f('Demotion or disciplinary circumstances', 'textarea'), 'organization_affiliation' => self::f('Individual affiliation in the organization', 'text', 2000),
            ]),
            'order_of_battle' => self::s('IV. Order of Battle', []),
            'composition' => self::s('Composition', ['composition' => self::f('Composition', 'textarea', 15000)]),
            'disposition' => self::s('Disposition', ['disposition' => self::f('Disposition', 'textarea', 15000)]),
            'strength_firearms' => self::s('Strength and Firearms', ['manpower' => self::f('Manpower', 'narrative'), 'firepower' => self::f('Firepower', 'narrative')]),
            'training' => self::s('Training', ['training' => self::f('Training', 'narrative')]),
            'logistics' => self::s('Logistics', ['logistics' => self::f('Logistics', 'narrative', 15000)]),
            'strategy_tactics' => self::s('Strategy and Tactics', ['strategy_tactics' => self::f('Strategy and Tactics', 'narrative')]),
            'combat_effectiveness' => self::s('Combat Effectiveness', ['combat_effectiveness' => self::f('Combat Effectiveness', 'narrative')]),
            'plans' => self::s('Plans', ['plans' => self::f('Plans', 'narrative')]),
            'other_information' => self::s('Other Significant Information', [
                'significant_information_status' => self::f('Significant information/changes, plans and programs', 'yes_na_text'), 'significant_information' => self::f('Details', 'textarea', 15000),
                'white_area_status' => self::f('Information related to white area', 'yes_na_text'), 'white_area_information' => self::f('Details', 'textarea'),
                'projected_enemy_status' => self::f('Projected operation of the enemy', 'yes_na_text'), 'projected_enemy_operations' => self::f('Details', 'textarea'),
            ]),
            'assessment' => self::s('Assessment', ['assessment' => self::f('Assessment', 'textarea', 20000)]),
            'recommendation' => self::s('Recommendation', ['recommendation' => self::f('Recommendation', 'textarea', 20000)]),
            'signatories' => self::s('Signatories', ['debriefer_name' => self::f('Debriefer — Full Name', 'text', 255), 'approving_officer_name' => self::f('Approving signatory — Full Name', 'text', 255)]),
        ];
    }

    public static function repeatableSections(): array
    {
        return [
            'parents_family' => self::t('Parents/family', ['relation' => 'Relation', 'name' => 'Name', 'address' => 'Address']),
            'siblings' => self::t('Brothers/Sisters', ['name' => 'Name', 'address' => 'Address']), 'children' => self::t('Children', ['name' => 'Name', 'address' => 'Address']),
            'government_relatives' => self::t('Relatives Working in Government', ['name' => 'Name', 'job_description' => 'Job description', 'address' => 'Address']),
            'ugm_relatives' => self::t('Relatives Working in UGM', ['name' => 'Name', 'job_description' => 'Job description', 'address' => 'Address']),
            'party_member_courses' => self::ct('Party Member', 'party_member_status', ['course' => 'Course', 'attendees' => 'Attendees', 'instructor' => 'Instructor']),
            'npa_member_courses' => self::ct('NPA Member', 'npa_member_status', ['course' => 'Course', 'attendees' => 'Attendees', 'instructor' => 'Instructor']),
            'mass_activist_courses' => self::ct('Subversive Mass Activist', 'mass_activist_status', ['course' => 'Course', 'attendees' => 'Attendees', 'instructor' => 'Instructor']),
            'violent_activities' => self::ct('Significant violent involvement', 'violent_activities_status', ['date_period' => 'Date/period', 'activity' => 'Activity']),
            'non_violent_activities' => self::ct('Significant non-violent involvement', 'non_violent_activities_status', ['date_period' => 'Date/period', 'activity' => 'Activity']),
            'personalities' => self::t('NPA Personalities', ['team_platoon' => 'Team/Platoon', 'name_alias' => 'Name/Alias', 'fas' => 'FAs', 'position' => 'Position']),
            'posting_areas' => self::t('Posting Areas', ['place' => 'Place', 'description' => 'Description']),
            'mass_contacts' => self::t('Mass Contacts', ['name' => 'Name', 'address' => 'Address', 'status' => 'Status', 'remarks' => 'Remarks']),
            'supply_routes' => self::t('Supply Routes', ['address' => 'Address', 'area_description' => 'Area Description']),
            'npa_active' => self::t('NPA Active', ['full_name' => 'Full Name', 'address' => 'Address']),
            'chronology' => self::ct('Chronology', 'chronology_status', ['date_period' => 'Date/period', 'activity' => 'Activity', 'remarks' => 'Remarks']),
        ];
    }

    public static function orderedBlocks(): array
    {
        return [['section', 'cover'], ['section', 'personal'], ['section', 'physical'], ['repeatable', 'parents_family'], ['repeatable', 'siblings'], ['repeatable', 'children'], ['repeatable', 'government_relatives'], ['repeatable', 'ugm_relatives'], ['section', 'neutralization'], ['section', 'entry_background'], ['repeatable', 'party_member_courses'], ['repeatable', 'npa_member_courses'], ['repeatable', 'mass_activist_courses'], ['repeatable', 'violent_activities'], ['repeatable', 'non_violent_activities'], ['section', 'order_of_battle'], ['section', 'composition'], ['section', 'disposition'], ['section', 'strength_firearms'], ['section', 'training'], ['section', 'logistics'], ['section', 'strategy_tactics'], ['section', 'combat_effectiveness'], ['section', 'plans'], ['repeatable', 'personalities'], ['repeatable', 'posting_areas'], ['repeatable', 'mass_contacts'], ['repeatable', 'supply_routes'], ['repeatable', 'npa_active'], ['section', 'other_information'], ['repeatable', 'chronology'], ['section', 'assessment'], ['section', 'recommendation'], ['section', 'signatories']];
    }

    public static function allowedKeys(): array
    {
        return [...collect(self::sections())->flatMap(fn ($s) => array_keys($s['fields']))->all(), ...collect(self::repeatableSections())->flatMap(fn ($s, $key) => array_filter([$key, $s['status_key'] ?? null]))->all()];
    }

    public static function defaultContent(): array
    {
        return [];
    }

    private static function f(string $label, string $type = 'text', int $max = 10000, array $options = []): array
    {
        return compact('label', 'type', 'max', 'options');
    }

    private static function s(string $label, array $fields): array
    {
        return compact('label', 'fields');
    }

    private static function t(string $label, array $columns): array
    {
        return compact('label', 'columns');
    }

    private static function ct(string $label, string $statusKey, array $columns): array
    {
        return ['label' => $label, 'status_key' => $statusKey, 'columns' => $columns];
    }
}
