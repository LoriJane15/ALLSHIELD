<?php

namespace App\Support;

use App\Models\RcspActivity;

final class RcspActivityCatalog
{
    /** @var array<int, list<string>> */
    public const ACTIVITIES = [
        0 => [
            'Orient the Local Chief Executive on EO70 and RCSP',
            'Identify and list the LGU RCSP Team Members',
            'Conduct Clearing Operations of Target Barangay',
            'Profile Development Status of Target Barangays',
            'Gather information from existing LGU Plans on Target Barangays',
        ],
        1 => [
            'Orient the Provincial/Municipal/City RCSP Core Team',
            'Secure Support of LGU',
            'Craft Initial Summing-Up Report',
            'Conduct Writeshop of the RCSP Team Action Plan',
            'Orientation of Barangay RCSP Core Team (BPOC and BDC Members)',
            'Prepare for the Immersion Proper',
        ],
        2 => [
            'Conduct the Serbisyo Caravan',
            'Conduct the Immersion Proper',
            'Identify Development Gaps and preferred interventions in the Community',
            'Match development gaps with current city/municipal program to be addressed directly by the LGU',
            'Conduct convergence meeting with Province and NGAs',
            'Support community participatory governance efforts in the barangay',
        ],
        3 => [
            'Assist LGUs in crafting/enhancing their BDP/ELA/AIP',
            'Capacitate Barangay-Based Institutions',
            'Secure funding support for identified PPAs in the Barangay BDP/ELA/AIP',
            'Address immediately resolvable gaps in the target barangays',
        ],
        4 => [
            'Conduct themed meetings on peace and development concerns',
            'Conduct information drive on peace, security and development',
            'Strengthen Community Security Mechanisms and Structures',
            'Monitoring and Evaluation of Peace and Development Situation in Target Barangays',
            'Implement PPAs that address Primary Issues in the Barangay',
        ],
        5 => [
            'Evaluate Program Results for the RCSP implementation',
            'Incentivize Performance of Barangays',
            'Craft periodic peace status report',
            'Conduct Community-Based Satisfaction Surveys',
            'Formally conclude the RCSP Program in the Barangay',
            'Turnover the RCSP Program and resulting interventions to the RCSP Barangay Team',
            'Ensure Program Sustainability',
        ],
    ];

    /** @var array<int, list<string>> */
    public const KEYS = [
        0 => ['orient_lce', 'list_lgu_team', 'clear_target_barangay', 'profile_target_barangays', 'gather_lgu_plans'],
        1 => ['orient_local_core_team', 'secure_lgu_support', 'initial_summing_up', 'team_action_plan', 'orient_barangay_core_team', 'prepare_immersion'],
        2 => ['serbisyo_caravan', 'immersion', 'identify_development_gaps', 'match_lgu_interventions', 'convergence_meeting', 'participatory_governance'],
        3 => ['enhance_local_plans', 'capacitate_bbis', 'secure_ppa_funding', 'address_immediate_gaps'],
        4 => ['themed_peace_meetings', 'peace_information_drive', 'strengthen_security_mechanisms', 'monitor_peace_development', 'implement_primary_issue_ppas'],
        5 => ['evaluate_program', 'incentivize_barangays', 'peace_status_report', 'satisfaction_surveys', 'conclude_program', 'turnover_program', 'ensure_sustainability'],
    ];

    /** @var array<string, string> */
    public const EXPECTED_RESULTS = [
        'orient_lce' => 'LCEs Oriented on RCSP/EO70',
        'list_lgu_team' => 'RCSP Team Members List and Profile',
        'clear_target_barangay' => 'Target Barangays Cleared for RCSP Civilian Operations',
        'profile_target_barangays' => 'Barangay Profile/Status',
        'gather_lgu_plans' => 'Compilation of information collected from existing plans',
        'orient_local_core_team' => 'RCSP Local Core Team Oriented on RCSP/EO70',
        'secure_lgu_support' => 'Resolution expressing support to RCSP',
        'initial_summing_up' => 'Summing-Up Report',
        'team_action_plan' => 'RCSP Team Action Plan',
        'orient_barangay_core_team' => 'RCSP Barangay Core Team Oriented on RCSP/EO70',
        'prepare_immersion' => 'Resources and Team prepared for Immersion',
        'serbisyo_caravan' => 'Local basic services delivered to target barangay',
        'immersion' => 'RCSP Team builds rapport with the community',
        'identify_development_gaps' => 'Development gaps identified',
        'match_lgu_interventions' => 'Matching of Interventions with identified development issues',
        'convergence_meeting' => 'Resources and support secured',
        'participatory_governance' => 'POs formed/registered',
        'enhance_local_plans' => 'Enhanced BDP/ELA/AIP',
        'capacitate_bbis' => 'BBIs capacitated',
        'secure_ppa_funding' => 'Funding commitment from higher LGUs',
        'address_immediate_gaps' => 'Immediately resolvable gaps addressed',
        'themed_peace_meetings' => 'Community engaged further on peace efforts',
        'peace_information_drive' => 'IEC materials and community dialogues',
        'strengthen_security_mechanisms' => 'Retooling for BPATs/Tanods and BNIs',
        'monitor_peace_development' => 'Periodic security reports submitted',
        'implement_primary_issue_ppas' => 'Medium/Longer-term PPAs implemented',
        'evaluate_program' => 'Program Evaluated',
        'incentivize_barangays' => 'Barangay incentives program',
        'peace_status_report' => 'Security reports',
        'satisfaction_surveys' => 'Community Satisfaction Survey',
        'conclude_program' => 'Final Summing-Up Report',
        'turnover_program' => 'Turnover ceremony conducted',
        'ensure_sustainability' => 'LGU continuous support of the program and re-echoing of the program to other barangays',
    ];

    /** @var array<string, string> */
    public const RESPONSIBLE_PARTIES = [
        'orient_lce' => 'C/MLGOO',
        'list_lgu_team' => 'RCSP Team',
        'clear_target_barangay' => 'PLEDS Team',
        'profile_target_barangays' => 'RCSP Team',
        'gather_lgu_plans' => 'C/MPDC',
        'orient_local_core_team' => 'C/MLGOO',
        'secure_lgu_support' => 'Sanggunian Panlungsod/Bayan',
        'initial_summing_up' => 'C/MLGOO, C/MPDC',
        'team_action_plan' => 'RCSP Team',
        'orient_barangay_core_team' => 'C/MLGOO',
        'prepare_immersion' => 'RCSP Core Team',
        'serbisyo_caravan' => 'CCPT',
        'immersion' => 'RCSP Local Team (Headed)',
        'identify_development_gaps' => 'CCPT, RCSP Core Team',
        'match_lgu_interventions' => 'C/MPDC',
        'convergence_meeting' => 'MPDC, NGAs',
        'participatory_governance' => 'CCPT',
        'enhance_local_plans' => 'Sanggunian Barangay',
        'capacitate_bbis' => 'C/MLGOO, C/MPDC, CCPT',
        'secure_ppa_funding' => 'Sanggunian Barangay',
        'address_immediate_gaps' => 'LCE',
        'themed_peace_meetings' => 'CCPT',
        'peace_information_drive' => 'CCPT, PLEDS',
        'strengthen_security_mechanisms' => 'PLEDS Team',
        'monitor_peace_development' => 'PLEDS Team',
        'implement_primary_issue_ppas' => 'C/M LGU, Province, Barangay',
        'evaluate_program' => 'C/MPDC, C/MLGOO',
        'incentivize_barangays' => 'C/M LGU',
        'peace_status_report' => 'PLEDS Team',
        'satisfaction_surveys' => 'CCPT',
        'conclude_program' => 'City/Municipal Mayor, C/MLGOO',
        'turnover_program' => 'City/Municipal and Barangay RCSP Team',
        'ensure_sustainability' => 'City/Municipal Mayor, Barangay RCSP Team',
    ];

    public const UNSPECIFIED_RESPONSIBILITY = 'Not specified in the official activity mapping';

    /** @var array<int, list<string|null>> */
    public const LEGACY_ACTIVITIES = [
        0 => [
            'Orient the Local Chief Executive on EO70 and RCSP',
            'Identify and list the LGU RCSP Team Members',
            'Conduct clearing operations of target barangays',
            'Profile development status of target barangays',
            'Gather information from existing LGU plans on target barangays',
        ],
        1 => [
            'Orient the Local Chief Executive on EO70 and RCSP',
            'Identify and list the LGU RCSP Team Members',
            'Conduct clearing operations of target barangays',
            'Profile development status of target barangays',
            'Gather information from existing LGU plans on target barangays',
            'Prepare for the immersion proper',
        ],
        2 => [
            'Conduct of Serbisyo Caravan',
            'Conduct the immersion proper',
            'Identify development gaps and preferred interventions in the community',
            'Match development gaps with current city/municipal program to be addressed directly by the LGU',
            'Conduct convergence meeting with Province and NGAs',
            'Support community participatory governance efforts in the barangay',
        ],
        3 => [
            'Assist LGUs in crafting/enhancing their BDP/ELA/AIP',
            'Capacitate Barangay-based Institutions (BBIs)',
            'Secure funding support for identified PPAs in the Barangay BDP/ELA/AIP',
            'Address immediately resolvable gaps in the target barangays',
        ],
        4 => [
            'Conduct themed meetings on peace and development concerns',
            'Conduct an information drive on peace, security and development',
            'Strengthen community security mechanisms and structures',
            'Monitor and evaluate the peace and development situation in target barangays',
            'Implement PPAs that address primary issues in the barangay',
        ],
        5 => [
            'Evaluate program results for the RCSP implementation',
            'Incentivize performance of barangays',
            'Craft periodic peace status report',
            null,
            'Formally conclude the RCSP in the barangay',
            'Turn over the RCSP and resulting interventions to the RCSP Barangay Team',
            null,
        ],
    ];

    /** @return list<array{key: string, order: int, description: string, expected_result: string, responsible_office_person: string, accepted_titles: list<string>}> */
    public static function entriesForPhase(int $phaseNumber): array
    {
        $entries = [];
        foreach (self::ACTIVITIES[$phaseNumber] ?? [] as $offset => $description) {
            $key = self::KEYS[$phaseNumber][$offset];
            $accepted = [$description];
            $legacy = self::LEGACY_ACTIVITIES[$phaseNumber][$offset] ?? null;
            if ($legacy !== null) {
                $accepted[] = $legacy;
            }

            $entries[] = [
                'key' => $key,
                'order' => $offset + 1,
                'description' => $description,
                'expected_result' => self::EXPECTED_RESULTS[$key],
                'responsible_office_person' => self::RESPONSIBLE_PARTIES[$key],
                'accepted_titles' => array_values(array_unique(array_map(
                    static fn (string $title): string => RcspActivity::normalizeTitle($title),
                    $accepted,
                ))),
            ];
        }

        return $entries;
    }

    public static function responsibilityFor(int $phaseNumber, string $activityTitle): string
    {
        $normalizedTitle = RcspActivity::normalizeTitle($activityTitle);

        foreach (self::entriesForPhase($phaseNumber) as $entry) {
            if (in_array($normalizedTitle, $entry['accepted_titles'], true)) {
                return $entry['responsible_office_person'];
            }
        }

        return self::UNSPECIFIED_RESPONSIBILITY;
    }

    /** @return array<int, int> */
    public static function phaseCounts(): array
    {
        return array_map(static fn (array $activities): int => count($activities), self::ACTIVITIES);
    }
}
