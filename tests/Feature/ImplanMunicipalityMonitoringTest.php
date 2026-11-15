<?php

namespace Tests\Feature;

use App\Models\AgencyImplanResponse;
use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\ImplementationPhoto;
use App\Models\Municipality;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_monitoring_list_counts_only_actions_assigned_to_the_authenticated_agency(): void
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
            ->assertSee('2 IMPLANs')
            ->assertSee('Padada')
            ->assertSee('1 IMPLAN')
            ->assertDontSee('Matanao')
            ->assertDontSee('Sulop DSWD row')
            ->assertDontSee('Sulop DOH row')
            ->assertDontSee('Sulop draft row');
    }

    public function test_unassigned_agency_receives_the_assignment_scoped_empty_state(): void
    {
        $this->implan('Sulop', 'DILG private action', ['DILG']);

        $this->actingAs($this->agencyUsers['DENR'])
            ->get(route('gov_agency.implan.index'))
            ->assertOk()
            ->assertSee('No IMPLAN actions are currently assigned to your agency.')
            ->assertDontSee('DILG private action');
    }

    public function test_monitoring_displays_only_assigned_municipality_rows_and_current_agency_response(): void
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
            ->assertSee('Total IMPLANs: 2')
            ->assertSee('A - DILG issue')
            ->assertSee('D - DILG and DSWD issue')
            ->assertDontSee('B - DSWD issue')
            ->assertDontSee('C - DOH issue')
            ->assertDontSee($draft->issues)
            ->assertDontSee('Other municipality row')
            ->assertSee('DILG (Accepted)')
            ->assertSee('DILG — Conducted coordination')
            ->assertSee('DILG — Awaiting confirmation')
            ->assertDontSee('DSWD — Validated beneficiaries')
            ->assertDontSee('DOH — Medical team unavailable')
            ->assertSee('data-official-implan', false)
            ->assertSee('data-print-implan', false)
            ->assertDontSee('<textarea', false)
            ->assertDontSee(route('gov_agency.implan.respond', $rowB), false)
            ->assertDontSee(route('gov_agency.implan.photos', $rowB), false);

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        $this->assertSame(2, $xpath->query('//*[@data-official-implan]//*[@data-implementation-row]')->count());
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
            ->assertDontSee($notAssigned->issues);

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

    public function test_dashboard_counts_and_accepted_list_are_assignment_scoped(): void
    {
        $this->agencies['DILG']->update(['profile' => 'dilg-dashboard-logo.png']);
        $this->agencies['DSWD']->update(['profile' => 'dswd-dashboard-logo.png']);
        $assignedPending = $this->implan('Sulop', 'DILG pending action', ['DILG']);
        $assignedAccepted = $this->implan('Sulop', 'DILG accepted action', ['DILG', 'DSWD']);
        $this->implan('Sulop', 'DSWD private action', ['DSWD']);

        AgencyImplanResponse::create([
            'implementation_id' => $assignedAccepted->id,
            'gov_agency_id' => $this->agencies['DILG']->id,
            'response_status' => 'accepted',
        ]);
        $privateDswdResponse = AgencyImplanResponse::create([
            'implementation_id' => $assignedAccepted->id,
            'gov_agency_id' => $this->agencies['DSWD']->id,
            'response_status' => 'rejected',
            'rejection_reason' => 'DSWD private rejection reason',
            'action_taken' => 'DSWD private action taken',
            'remarks' => 'DSWD private remarks',
        ]);
        $privateDswdResponse->photos()->create([
            'implementation_id' => $assignedAccepted->id,
            'image' => 'implan/photos/dswd-private-dashboard-photo.jpg',
        ]);

        $response = $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', [
                'assigned' => 2,
                'accepted' => 1,
                'pending' => 1,
                'rejected' => 0,
            ])
            ->assertSee($assignedAccepted->issues)
            ->assertDontSee('DSWD private action')
            ->assertSee('DILG')
            ->assertSee('DSWD')
            ->assertSee('Department of the Interior and Local Government (DILG)', false)
            ->assertSee('Department of Social Welfare and Development (DSWD)', false)
            ->assertSee('Accepted')
            ->assertDontSee('DSWD private rejection reason')
            ->assertDontSee('DSWD private action taken')
            ->assertDontSee('DSWD private remarks')
            ->assertDontSee('dswd-private-dashboard-photo.jpg')
            ->assertViewHas('accepted', fn ($actions) => $actions->every(
                fn (Implementation $action) => ! $action->relationLoaded('responses')
            ));

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//*[@data-dashboard-implan="'.$assignedAccepted->id.'"]')->count());
        $this->assertSame(2, $xpath->query('//*[@data-dashboard-implan="'.$assignedAccepted->id.'"]//*[@data-responsible-agency]')->count());
        $this->assertSame(2, $xpath->query('//*[@data-dashboard-implan="'.$assignedAccepted->id.'"]//*[@data-responsible-agency]/img')->count());
        $this->assertSame('', trim($xpath->evaluate('string(//*[@data-dashboard-implan="'.$assignedAccepted->id.'"]//*[@data-responsible-agency][1])')));
        $this->assertSame('', trim($xpath->evaluate('string(//*[@data-dashboard-implan="'.$assignedAccepted->id.'"]//*[@data-responsible-agency][2])')));

        $this->assertSame('DILG pending action', $assignedPending->issues);
    }

    public function test_lgu_has_consolidated_municipality_visibility_but_cross_municipality_photos_are_forbidden(): void
    {
        Storage::fake('public');
        $otherSulopLgu = User::factory()->lgu($this->municipalities['Sulop']->id)->create();
        $municipalAction = Implementation::create([
            'lgu_user_id' => $otherSulopLgu->id,
            'uploaded_at' => now()->toDateString(),
            'issues' => 'Shared Sulop municipal action',
            'target_areas' => [],
            'agencies' => [$this->agencies['DILG']->id, $this->agencies['DSWD']->id],
            'status' => 'submitted',
        ]);
        $otherMunicipalityAction = $this->implan('Matanao', 'Private Matanao action', ['DILG']);
        Storage::disk('public')->put('implan/photos/matanao.jpg', 'private');
        $otherPhoto = ImplementationPhoto::create([
            'implementation_id' => $otherMunicipalityAction->id,
            'image' => 'implan/photos/matanao.jpg',
        ]);

        $this->actingAs($this->lguUsers['Sulop'])
            ->get(route('lgu.implan.index'))
            ->assertOk()
            ->assertSee('Shared Sulop municipal action')
            ->assertDontSee('Private Matanao action')
            ->assertSee('Responsible Agency/Agencies')
            ->assertSee('0 of 2 responsible agencies submitted documentation');
        $this->actingAs($this->lguUsers['Sulop'])
            ->get(route('lgu.implan.show', $municipalAction))->assertOk()
            ->assertDontSee(route('lgu.implan.destroy', $municipalAction), false);
        $this->actingAs($this->lguUsers['Sulop'])
            ->put(route('lgu.implan.update', $municipalAction), ['issues' => 'Forbidden'])
            ->assertForbidden();
        $this->actingAs($this->lguUsers['Sulop'])
            ->get(route('lgu.implan.show', $otherMunicipalityAction))->assertForbidden();
        $this->actingAs($this->lguUsers['Sulop'])
            ->get(route('lgu.implan.photos.show', [$otherMunicipalityAction, $otherPhoto]))->assertForbidden();
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
