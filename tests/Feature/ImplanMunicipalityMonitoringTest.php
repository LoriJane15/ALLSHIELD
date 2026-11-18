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

        $index = $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.implan.index'))
            ->assertOk()
            ->assertSee('Municipality IMPLAN Monitoring')
            ->assertSee('Open Monitoring')
            ->assertSee(route('gov_agency.implan.monitoring'), false)
            ->assertDontSee('data-municipality-monitoring-list', false)
            ->assertDontSee('data-monitoring-municipality', false)
            ->assertDontSee('2 IMPLANs')
            ->assertDontSee('1 IMPLAN')
            ->assertDontSee('Sulop DSWD row')
            ->assertDontSee('Sulop DOH row')
            ->assertDontSee('Sulop draft row');

        $this->assertSame(1, substr_count($index->getContent(), 'data-open-monitoring'));

        $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.implan.monitoring'))
            ->assertOk()
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
            ->get(route('gov_agency.implan.monitoring'))
            ->assertOk()
            ->assertSee('No municipality IMPLANs are currently assigned to your agency.')
            ->assertSee('data-monitoring-empty', false)
            ->assertDontSee('DILG private action');
    }

    public function test_monitoring_index_paginates_unique_eligible_municipalities(): void
    {
        for ($number = 1; $number <= 11; $number++) {
            $name = sprintf('Agency Municipality %02d', $number);
            $municipality = Municipality::create(compact('name'));
            $lgu = User::factory()->lgu($municipality->id)->create();
            foreach (['submitted', 'ongoing'] as $status) {
                Implementation::create([
                    'lgu_user_id' => $lgu->id,
                    'uploaded_at' => now()->subDays($number)->toDateString(),
                    'issues' => "{$name} {$status} action",
                    'target_areas' => [],
                    'agencies' => [$this->agencies['DILG']->id],
                    'status' => $status,
                ]);
            }
        }

        $firstPage = $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.implan.monitoring'))
            ->assertOk()
            ->assertViewHas('municipalities', fn ($municipalities) => $municipalities->perPage() === 10
                && $municipalities->total() === 11
                && $municipalities->count() === 10)
            ->assertSee('Agency Municipality 01')
            ->assertSee('Agency Municipality 10')
            ->assertDontSee('Agency Municipality 11');

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($firstPage->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame(10, $xpath->query('//*[@data-monitoring-municipality]')->count());
        $this->assertSame(10, $xpath->query('//*[@data-monitoring-count="2"]')->count());

        $this->actingAs($this->agencyUsers['DILG'])
            ->get(route('gov_agency.implan.monitoring', ['page' => 2]))
            ->assertOk()
            ->assertSee('Agency Municipality 11')
            ->assertDontSee('Agency Municipality 01');
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
            ->assertSee('DILG, DSWD')
            ->assertDontSee('DILG (Accepted)')
            ->assertSee('Conducted coordination')
            ->assertSee('Awaiting confirmation')
            ->assertDontSee('DILG — Conducted coordination')
            ->assertDontSee('DILG — Awaiting confirmation')
            ->assertDontSee('Validated beneficiaries')
            ->assertDontSee('Medical team unavailable')
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
        $this->assertSame(1, $xpath->query('//*[@data-official-implan]//table')->count());
        $this->assertSame(1, $xpath->query('//*[@data-monitoring-detail]//*[@class="implan-official-scroll"]')->count());
        $this->assertSame(0, $xpath->query('//*[@data-official-implan]//form')->count());
        $this->assertSame(0, $xpath->query('//*[@data-official-implan]//button')->count());
        $headings = [];
        foreach ($xpath->query('//*[@data-official-implan]//thead/tr/th') as $heading) {
            $headings[] = trim(preg_replace('/\s+/', ' ', $heading->textContent));
        }
        $this->assertSame([
            'Issues and Concerns to be Addressed',
            'Program/Project/Activity',
            'Target Area',
            'Target Beneficiaries',
            'Expected Results/Outcome',
            'Responsible Agency',
            'Resources Needed (Funding)',
            'Support Needed',
            'Duration',
            'Action Taken',
            'Remarks',
        ], $headings);

        $sharedAgencyCell = $xpath->query('//*[@data-implementation-row="'.$rowD->id.'"]//*[@data-responsible-agencies]')->item(0);
        $this->assertNotNull($sharedAgencyCell);
        $this->assertSame('DILG, DSWD', trim($sharedAgencyCell->textContent));
        $this->assertSame(0, $xpath->query('.//strong|.//b|.//*[contains(concat(" ", normalize-space(@class), " "), " badge ")]', $sharedAgencyCell)->count());

        $source = file_get_contents(resource_path('views/gov_agency/implan/municipality.blade.php'));
        $this->assertIsString($source);
        $printStart = strrpos($source, '@media print');
        $this->assertNotFalse($printStart);
        $screenCss = substr($source, 0, $printStart);
        $printCss = substr($source, $printStart);

        $this->assertStringContainsString('overflow-x:auto', $screenCss);
        $this->assertStringContainsString('width:2100px', $screenCss);
        $this->assertStringContainsString('min-width:2100px', $screenCss);
        $this->assertStringContainsString('@page { size:A4 landscape; margin:8mm; }', $printCss);
        $this->assertStringContainsString('width:100% !important', $printCss);
        $this->assertStringContainsString('min-width:0 !important', $printCss);
        $this->assertStringContainsString('max-width:100% !important', $printCss);
        $this->assertStringContainsString('table-layout:fixed !important', $printCss);
        $this->assertStringContainsString('border-collapse:collapse !important', $printCss);
        $this->assertStringContainsString('overflow:visible !important', $printCss);
        $this->assertStringContainsString('scrollbar-width:none', $printCss);
        $this->assertStringContainsString('::-webkit-scrollbar', $printCss);
        $this->assertStringContainsString('white-space:normal !important', $printCss);
        $this->assertStringContainsString('overflow-wrap:anywhere', $printCss);
        $this->assertStringContainsString('word-break:normal', $printCss);
        $this->assertStringContainsString('height:auto !important', $printCss);
        $this->assertStringContainsString('display:table-header-group', $printCss);
        $this->assertStringContainsString('break-inside:avoid', $printCss);
        $this->assertStringContainsString('page-break-inside:avoid', $printCss);
        $this->assertStringContainsString('-webkit-print-color-adjust:exact', $printCss);
        $this->assertStringContainsString('print-color-adjust:exact', $printCss);
        $this->assertStringContainsString('.content-wrapper > :not(.gov-agency-monitoring-detail)', $printCss);
        $this->assertStringContainsString('.gov-agency-monitoring-detail > .no-print', $printCss);

        preg_match_all('/th:nth-child\((\d+)\),\s*\R\s*\.gov-agency-monitoring-detail \.implan-official-table td:nth-child\(\1\) \{ width:(\d+)% !important; \}/', $printCss, $printWidths, PREG_SET_ORDER);
        $this->assertCount(11, $printWidths);
        $this->assertSame(100, array_sum(array_map(fn (array $match) => (int) $match[2], $printWidths)));
        $this->assertStringContainsString('onclick="window.print()"', $source);
        $this->assertStringContainsString('scrollContainer.scrollLeft = 0', $source);
        $this->assertStringNotContainsString('window.open', $source);
        $this->assertStringNotContainsString('<iframe', $source);
        $this->assertStringNotContainsString('target="_blank"', $source);
        $this->assertStringNotContainsString('window.location', $source);
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
            ->assertSee('0 of 2 responsible agencies submitted MOV');
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
