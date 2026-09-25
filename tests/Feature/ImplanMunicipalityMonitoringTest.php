<?php

namespace Tests\Feature;

use App\Models\AgencyImplanResponse;
use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\Municipality;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImplanMunicipalityMonitoringTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, GovAgency> */
    private array $agencies;

    /** @var array<string, User> */
    private array $agencyUsers;

    /** @var array<string, Municipality> */
    private array $municipalities;

    /** @var array<string, User> */
    private array $lguUsers;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'DILG' => 'Department of the Interior and Local Government',
            'DSWD' => 'Department of Social Welfare and Development',
            'DOH' => 'Department of Health',
            'DENR' => 'Department of Environment and Natural Resources',
        ] as $acronym => $name) {
            $this->agencies[$acronym] = GovAgency::create(compact('name', 'acronym'));
            $this->agencyUsers[$acronym] = User::factory()->govAgency($this->agencies[$acronym]->id)->create();
        }

        foreach (['Sulop', 'Padada', 'Matanao'] as $name) {
            $this->municipalities[$name] = Municipality::create(compact('name'));
            $this->lguUsers[$name] = User::factory()->lgu($this->municipalities[$name]->id)->create();
        }
    }

    public function test_monitoring_list_shows_only_eligible_municipalities_with_full_official_counts(): void
    {
        $this->implan('Sulop', 'Sulop DILG row', ['DILG']);
        $this->implan('Sulop', 'Sulop DSWD row', ['DSWD']);
        $this->implan('Sulop', 'Sulop DOH row', ['DOH']);
        $this->implan('Sulop', 'Sulop shared row', ['DILG', 'DSWD']);
        $this->implan('Sulop', 'Sulop draft row', ['DILG'], 'not yet started');

        $this->implan('Padada', 'Padada DILG row', ['DILG']);
        $this->implan('Padada', 'Padada DOH row', ['DOH'], 'ongoing');
        $this->implan('Matanao', 'Matanao DSWD row', ['DSWD']);

        $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.implan.index'))
            ->assertOk()
            ->assertSee('Municipality IMPLAN Monitoring')
            ->assertSee('Sulop')
            ->assertSee('4 IMPLANs')
            ->assertSee('Padada')
            ->assertSee('2 IMPLANs')
            ->assertDontSee('Matanao')
            ->assertDontSee('Sulop draft row');
    }

    public function test_monitoring_displays_every_official_municipality_row_with_attribution_and_no_controls(): void
    {
        $rowA = $this->implan('Sulop', 'A - DILG issue', ['DILG']);
        $rowB = $this->implan('Sulop', 'B - DSWD issue', ['DSWD']);
        $rowC = $this->implan('Sulop', 'C - DOH issue', ['DOH']);
        $rowD = $this->implan('Sulop', 'D - DILG and DSWD issue', ['DILG', 'DSWD']);
        $draft = $this->implan('Sulop', 'Draft must stay private', ['DILG'], 'not yet started');
        $this->implan('Matanao', 'Other municipality row', ['DILG']);

        AgencyImplanResponse::create([
            'implementation_id' => $rowA->id,
            'gov_agency_id' => $this->agencies['DILG']->id,
            'response_status' => 'accepted',
            'action_taken' => 'Conducted coordination',
            'remarks' => 'Awaiting confirmation',
        ]);
        AgencyImplanResponse::create([
            'implementation_id' => $rowB->id,
            'gov_agency_id' => $this->agencies['DSWD']->id,
            'response_status' => 'accepted',
            'action_taken' => 'Validated beneficiaries',
        ]);
        AgencyImplanResponse::create([
            'implementation_id' => $rowC->id,
            'gov_agency_id' => $this->agencies['DOH']->id,
            'response_status' => 'rejected',
            'remarks' => 'Medical team unavailable',
        ]);

        $response = $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.implan.municipality', $this->municipalities['Sulop']))
            ->assertOk()
            ->assertSee('Total IMPLANs: 4')
            ->assertSee('A - DILG issue')
            ->assertSee('B - DSWD issue')
            ->assertSee('C - DOH issue')
            ->assertSee('D - DILG and DSWD issue')
            ->assertDontSee($draft->issues)
            ->assertDontSee('Other municipality row')
            ->assertSee('DILG (Accepted)')
            ->assertSee('DSWD (Accepted)')
            ->assertSee('DOH (Rejected)')
            ->assertSee('DILG — Conducted coordination')
            ->assertSee('DSWD — Validated beneficiaries')
            ->assertSee('DILG — Awaiting confirmation')
            ->assertSee('DOH — Medical team unavailable')
            ->assertSee('data-official-implan', false)
            ->assertSee('data-print-implan', false)
            ->assertDontSee('<textarea', false)
            ->assertDontSee(route('gov_agency.implan.respond', $rowB), false)
            ->assertDontSee(route('gov_agency.implan.agenda', $rowB), false)
            ->assertDontSee(route('gov_agency.implan.photos', $rowB), false);

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        $this->assertSame(4, $xpath->query('//*[@data-official-implan]//*[@data-implementation-row]')->count());
        $this->assertSame(0, $xpath->query('//*[@data-official-implan]//form')->count());
        $this->assertSame(0, $xpath->query('//*[@data-official-implan]//button')->count());
    }

    public function test_monitoring_eligibility_does_not_weaken_assigned_record_authorization(): void
    {
        $assigned = $this->implan('Sulop', 'DILG assigned row', ['DILG']);
        $notAssigned = $this->implan('Sulop', 'DSWD only row', ['DSWD']);
        $matanao = $this->implan('Matanao', 'Matanao DSWD only row', ['DSWD']);

        $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.implan.municipality', $this->municipalities['Sulop']))
            ->assertOk()
            ->assertSee($assigned->issues)
            ->assertSee($notAssigned->issues);

        $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.implan.show', $notAssigned))
            ->assertForbidden();
        $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.implan.show', $assigned))
            ->assertOk();
        $this->actingAs($this->agencyUsers['DILG'])
            ->put(route('gov_agency.implan.update', $notAssigned), [
                'action_taken' => 'Forbidden update',
                'remarks' => 'Forbidden update',
            ])
            ->assertForbidden();
        $this->actingAs($this->agencyUsers['DILG'])
            ->post(route('gov_agency.implan.respond', $notAssigned), [
                'response_status' => 'accepted',
            ])
            ->assertForbidden();
        $this->actingAs($this->agencyUsers['DILG'])
            ->post(route('gov_agency.implan.agenda', $notAssigned))
            ->assertForbidden();
        $this->actingAs($this->agencyUsers['DILG'])
            ->post(route('gov_agency.implan.photos', $notAssigned))
            ->assertForbidden();
        $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.implan.municipality', $this->municipalities['Matanao']))
            ->assertForbidden();

        $this->assertDatabaseMissing('agency_implan_responses', [
            'implementation_id' => $notAssigned->id,
            'gov_agency_id' => $this->agencies['DILG']->id,
        ]);
        $this->assertSame('Matanao DSWD only row', $matanao->issues);
    }

    /** @param array<int, string> $agencyAcronyms */
    private function implan(
        string $municipality,
        string $issues,
        array $agencyAcronyms,
        string $status = 'submitted'
    ): Implementation {
        return Implementation::create([
            'lgu_user_id' => $this->lguUsers[$municipality]->id,
            'uploaded_at' => now()->toDateString(),
            'issues' => $issues,
            'program' => "{$issues} program",
            'target_areas' => [],
            'agencies' => array_map(fn (string $acronym) => $this->agencies[$acronym]->id, $agencyAcronyms),
            'beneficiaries' => "{$issues} beneficiaries",
            'outcome' => "{$issues} outcome",
            'resources' => "{$issues} resources",
            'support' => "{$issues} support",
            'duration' => '2026',
            'status' => $status,
        ]);
    }
}
