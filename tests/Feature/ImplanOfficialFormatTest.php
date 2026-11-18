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
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ImplanOfficialFormatTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $sulop;

    private Municipality $matanao;

    private User $sulopLgu;

    private User $matanaoLgu;

    private GovAgency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sulop = Municipality::create(['name' => 'Sulop']);
        $this->matanao = Municipality::create(['name' => 'Matanao']);
        $this->sulopLgu = User::factory()->lgu($this->sulop->id)->create();
        $this->matanaoLgu = User::factory()->lgu($this->matanao->id)->create();
        $this->agency = GovAgency::create([
            'name' => 'Department of the Interior and Local Government',
            'acronym' => 'DILG',
        ]);
    }

    public function test_municipality_output_has_exact_official_headings_print_logo_and_one_record_per_row(): void
    {
        $doh = GovAgency::create(['name' => 'Department of Health', 'acronym' => 'DOH']);
        $dswd = GovAgency::create(['name' => 'Department of Social Welfare and Development', 'acronym' => 'DSWD']);
        $denr = GovAgency::create(['name' => 'Department of Environment and Natural Resources', 'acronym' => 'DENR']);
        $sulopA = $this->createImplan($this->sulopLgu, 'Sulop A');
        $sulopA->update(['agencies' => [$this->agency->id, $doh->id]]);
        $sulopB = $this->createImplan($this->sulopLgu, 'Sulop B');
        $sulopB->update(['agencies' => [$this->agency->id, $this->agency->id]]);
        $sulopC = $this->createImplan($this->sulopLgu, 'Sulop C');
        $sulopC->update(['agencies' => [$dswd->id]]);
        $this->createImplan($this->sulopLgu, 'Sulop draft', 'not yet started');
        $matanao = $this->createImplan($this->matanaoLgu, 'Matanao D');
        $matanao->update(['agencies' => [$denr->id]]);
        $this->createImplan($this->matanaoLgu, 'Matanao E');

        foreach ([
            [$sulopA, $this->agency, 'DILG action A', 'DILG remarks A'],
            [$sulopA, $doh, 'DOH action A', 'DOH remarks A'],
            [$sulopB, $this->agency, 'DILG action B', 'DILG remarks B'],
            [$sulopC, $dswd, 'DSWD action C', 'DSWD remarks C'],
        ] as [$implan, $agency, $actionTaken, $remarks]) {
            AgencyImplanResponse::create([
                'implementation_id' => $implan->id,
                'gov_agency_id' => $agency->id,
                'response_status' => 'accepted',
                'action_taken' => $actionTaken,
                'remarks' => $remarks,
            ]);
        }

        $response = $this->actingAs($this->sulopLgu)->get(route('lgu.implan.official'))
            ->assertOk()
            ->assertSee('Sulop A')->assertSee('Sulop B')->assertSee('Sulop C')
            ->assertDontSee('Sulop draft')->assertDontSee('Matanao D')
            ->assertSee('data-print-implan', false)
            ->assertSee('assets/IMPLANs/ELCAC.png', false)
            ->assertSee('data-download-implan', false)
            ->assertSee('Download XLSX')
            ->assertDontSee('Download XLXS')
            ->assertDontSee('DENR');

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        $tabs = [];
        foreach ($xpath->query('//*[@data-official-agency-tab]') as $tab) {
            $tabs[] = trim($tab->textContent);
        }
        $this->assertSame(['DILG', 'DOH', 'DSWD'], $tabs);
        $this->assertSame(3, $xpath->query('//*[@data-official-agency-tab]')->count());
        $this->assertSame(1, $xpath->query('//*[@data-official-agency-tab and contains(concat(" ", normalize-space(@class), " "), " active ")]')->count());
        $this->assertSame(3, $xpath->query('//*[@data-official-agency-pane]')->count());

        $dilgPane = $xpath->query('//*[@data-official-agency-pane="'.$this->agency->id.'"]')->item(0);
        $dohPane = $xpath->query('//*[@data-official-agency-pane="'.$doh->id.'"]')->item(0);
        $dswdPane = $xpath->query('//*[@data-official-agency-pane="'.$dswd->id.'"]')->item(0);
        $this->assertNotNull($dilgPane);
        $this->assertNotNull($dohPane);
        $this->assertNotNull($dswdPane);
        $this->assertSame(2, $xpath->query('.//*[@data-implementation-row]', $dilgPane)->count());
        $this->assertSame(1, $xpath->query('.//*[@data-implementation-row]', $dohPane)->count());
        $this->assertSame(1, $xpath->query('.//*[@data-implementation-row]', $dswdPane)->count());
        $this->assertStringContainsString('Sulop A', $dilgPane->textContent);
        $this->assertStringContainsString('Sulop B', $dilgPane->textContent);
        $this->assertStringNotContainsString('Sulop C', $dilgPane->textContent);
        $this->assertStringContainsString('Sulop A', $dohPane->textContent);
        $this->assertStringNotContainsString('Sulop B', $dohPane->textContent);
        $this->assertStringContainsString('Sulop C', $dswdPane->textContent);

        $this->assertStringContainsString('DILG action A', $dilgPane->textContent);
        $this->assertStringContainsString('DILG remarks A', $dilgPane->textContent);
        $this->assertStringNotContainsString('DOH action A', $dilgPane->textContent);
        $this->assertStringNotContainsString('DOH remarks A', $dilgPane->textContent);
        $this->assertStringContainsString('DOH action A', $dohPane->textContent);
        $this->assertStringContainsString('DOH remarks A', $dohPane->textContent);
        $this->assertStringNotContainsString('DILG action A', $dohPane->textContent);
        $this->assertStringNotContainsString('DILG remarks A', $dohPane->textContent);
        $this->assertStringContainsString('DSWD action C', $dswdPane->textContent);

        $responsibleCell = $xpath->query('.//*[@data-implementation-row="'.$sulopA->id.'"]//*[@data-lgu-original="agencies"]', $dilgPane)->item(0);
        $this->assertNotNull($responsibleCell);
        $this->assertSame('DILG, DOH', trim(preg_replace('/\s+/', ' ', $responsibleCell->textContent)));
        $this->assertSame(0, $xpath->query('.//strong|.//b|.//*[contains(concat(" ", normalize-space(@class), " "), " badge ")]', $responsibleCell)->count());
        $this->assertStringNotContainsString('(Accepted)', $responsibleCell->textContent);

        $headings = [];
        foreach ($xpath->query('.//*[@data-official-implan]//thead/tr/th', $dilgPane) as $heading) {
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
        $this->assertSame(0, $xpath->query('//*[@data-official-agency-pane]//*[@data-agency-mov-gallery or @data-mov-item]')->count());

        $source = file_get_contents(resource_path('views/lgu/implan/official.blade.php'));
        $this->assertIsString($source);
        $this->assertStringContainsString('onclick="window.print()"', $source);
        $this->assertStringNotContainsString('window.open', $source);
        $this->assertStringNotContainsString('<iframe', $source);
        $this->assertStringContainsString('#municipalityAgencyImplans .tab-pane:not(.active)', $source);
        $this->assertStringContainsString('#municipalityAgencyImplans .tab-pane.active', $source);
        $this->assertStringContainsString("tabList.addEventListener('shown.bs.tab'", $source);
    }

    public function test_municipality_output_has_one_empty_state_and_no_tabs_without_relevant_assignments(): void
    {
        $this->createImplan($this->sulopLgu, 'Sulop draft only', 'not yet started');
        $unassigned = $this->createImplan($this->sulopLgu, 'Submitted without agency');
        $unassigned->update(['agencies' => []]);

        $response = $this->actingAs($this->sulopLgu)
            ->get(route('lgu.implan.official'))
            ->assertOk()
            ->assertSee('No submitted municipality IMPLANs have responsible agency assignments.')
            ->assertDontSee('data-official-agency-tab', false)
            ->assertDontSee('data-download-implan', false)
            ->assertDontSee('data-print-implan', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-empty-agency-implan'));
    }

    public function test_katuparan_monitors_each_municipality_read_only_without_draft_or_verification_gate(): void
    {
        $sulop = $this->createImplan($this->sulopLgu, 'Sulop only');
        $this->createImplan($this->matanaoLgu, 'Matanao only');
        $this->createImplan($this->sulopLgu, 'Sulop draft', 'not yet started');
        $admin = User::factory()->role('admin')->create();

        $this->actingAs($admin)->get(route('admin.implan.index'))
            ->assertOk()->assertSee('Sulop')->assertSee('Matanao')->assertDontSee('Verify')->assertDontSee('Reassign');

        $this->actingAs($admin)->get(route('admin.implan.municipality', $this->sulop))
            ->assertOk()->assertSee('Sulop only')->assertDontSee('Matanao only')->assertDontSee('Sulop draft')
            ->assertDontSee('name="remarks"', false)->assertDontSee('name="action_taken"', false);

        $this->actingAs($admin)->get(route('admin.implan.municipality', $this->matanao))
            ->assertOk()->assertSee('Matanao only')->assertDontSee('Sulop only');

        $this->actingAs($admin)->post(route('admin.implan.verify', $sulop))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.implan.reassign', $sulop))->assertForbidden();
    }

    public function test_katuparan_groups_supporting_records_by_their_exact_implan_container(): void
    {
        $implanA = $this->createImplan($this->sulopLgu, 'Road Accessibility');
        $implanB = $this->createImplan($this->sulopLgu, 'Health Services');
        $emptyImplan = $this->createImplan($this->sulopLgu, 'Water Supply');
        $matanaoImplan = $this->createImplan($this->matanaoLgu, 'Matanao excluded supporting records');
        $doh = GovAgency::create(['name' => 'Department of Health', 'acronym' => 'DOH']);

        ImplementationPhoto::create([
            'implementation_id' => $implanA->id,
            'image' => 'implan/photos/lgu-a.jpg',
        ]);
        ImplementationPhoto::create([
            'implementation_id' => $implanB->id,
            'image' => 'implan/photos/lgu-b.jpg',
        ]);
        ImplementationPhoto::create([
            'implementation_id' => $matanaoImplan->id,
            'image' => 'implan/photos/matanao-hidden.jpg',
        ]);

        $dilgResponse = AgencyImplanResponse::create([
            'implementation_id' => $implanA->id,
            'gov_agency_id' => $this->agency->id,
        ]);
        $dohResponse = AgencyImplanResponse::create([
            'implementation_id' => $implanB->id,
            'gov_agency_id' => $doh->id,
        ]);
        ImplementationPhoto::create([
            'implementation_id' => $implanA->id,
            'agency_implan_response_id' => $dilgResponse->id,
            'image' => 'implan/photos/dilg-a.jpg',
        ]);
        ImplementationPhoto::create([
            'implementation_id' => $implanB->id,
            'agency_implan_response_id' => $dohResponse->id,
            'image' => 'implan/photos/doh-b.jpg',
        ]);
        ImplementationPhoto::create([
            'implementation_id' => $implanB->id,
            'agency_implan_response_id' => $dilgResponse->id,
            'image' => 'implan/photos/inconsistent.jpg',
        ]);

        $admin = User::factory()->role('admin')->create();
        $response = $this->actingAs($admin)
            ->get(route('admin.implan.municipality', $this->sulop))
            ->assertOk()
            ->assertSee('IMPLAN Supporting Records')
            ->assertDontSee('matanao-hidden.jpg')
            ->assertDontSee('inconsistent.jpg')
            ->assertDontSee('Agenda File');

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        $supportingArea = $xpath->query('//*[@data-implan-supporting-records]')->item(0);
        $implanAGroup = $xpath->query('//*[@data-implan-supporting="'.$implanA->id.'"]')->item(0);
        $implanBGroup = $xpath->query('//*[@data-implan-supporting="'.$implanB->id.'"]')->item(0);
        $emptyGroup = $xpath->query('//*[@data-implan-supporting="'.$emptyImplan->id.'"]')->item(0);

        $this->assertNotNull($supportingArea);
        $this->assertNotNull($implanAGroup);
        $this->assertNotNull($implanBGroup);
        $this->assertNotNull($emptyGroup);
        $this->assertStringContainsString('Road Accessibility', $implanAGroup->textContent);
        $this->assertStringContainsString('implan/photos/lgu-a.jpg', $document->saveHTML($implanAGroup));
        $this->assertSame(2, $xpath->query('.//*[@data-implan-mov]//*[@data-mov-item]', $implanAGroup)->count());
        $this->assertStringNotContainsString('implan/photos/lgu-b.jpg', $document->saveHTML($implanAGroup));
        $this->assertStringNotContainsString('DOH', $implanAGroup->textContent);

        $this->assertStringContainsString('Health Services', $implanBGroup->textContent);
        $this->assertStringContainsString('implan/photos/lgu-b.jpg', $document->saveHTML($implanBGroup));
        $this->assertSame(2, $xpath->query('.//*[@data-implan-mov]//*[@data-mov-item]', $implanBGroup)->count());
        $this->assertStringNotContainsString('implan/photos/lgu-a.jpg', $document->saveHTML($implanBGroup));
        $this->assertStringNotContainsString('DILG', $implanBGroup->textContent);

        $this->assertStringContainsString('Water Supply', $emptyGroup->textContent);
        $this->assertStringContainsString('No mode of verification available.', $emptyGroup->textContent);
        $this->assertSame(0, $xpath->query('.//form', $supportingArea)->count());
        $this->assertSame(0, $xpath->query('.//input', $supportingArea)->count());
        $this->assertSame(0, $xpath->query('.//button', $supportingArea)->count());
    }

    public function test_katuparan_displays_movs_without_upload_owner_indicators(): void
    {
        $implan = $this->createImplan($this->sulopLgu, 'Multi-agency response');
        $dswd = GovAgency::create(['name' => 'Department of Social Welfare and Development', 'acronym' => 'DSWD']);
        $doh = GovAgency::create(['name' => 'Department of Health', 'acronym' => 'DOH']);
        $implan->update(['agencies' => [$this->agency->id, $dswd->id, $doh->id]]);

        ImplementationPhoto::create([
            'implementation_id' => $implan->id,
            'image' => 'implan/photos/municipal.jpg',
        ]);

        $responses = collect([
            'DILG' => AgencyImplanResponse::create([
                'implementation_id' => $implan->id,
                'gov_agency_id' => $this->agency->id,
            ]),
            'DSWD' => AgencyImplanResponse::create([
                'implementation_id' => $implan->id,
                'gov_agency_id' => $dswd->id,
            ]),
            'DOH' => AgencyImplanResponse::create([
                'implementation_id' => $implan->id,
                'gov_agency_id' => $doh->id,
            ]),
        ]);

        ImplementationPhoto::create([
            'implementation_id' => $implan->id,
            'agency_implan_response_id' => $responses['DILG']->id,
            'image' => 'implan/photos/dilg.jpg',
        ]);
        ImplementationPhoto::create([
            'implementation_id' => $implan->id,
            'agency_implan_response_id' => $responses['DSWD']->id,
            'image' => 'implan/photos/dswd.jpg',
        ]);
        ImplementationPhoto::create([
            'implementation_id' => $implan->id,
            'agency_implan_response_id' => $responses['DOH']->id,
            'image' => 'implan/photos/doh.jpg',
        ]);

        $admin = User::factory()->role('admin')->create();
        $response = $this->actingAs($admin)->get(route('admin.implan.municipality', $this->sulop))->assertOk();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $implanGroup = $xpath->query('//*[@data-implan-supporting="'.$implan->id.'"]')->item(0);

        $this->assertNotNull($implanGroup);
        $this->assertSame(4, $xpath->query('.//*[@data-implan-mov]//*[@data-mov-item]', $implanGroup)->count());
        $this->assertSame(0, $xpath->query('.//*[@data-implan-mov]//*[@data-agency-photo or @data-agency-documentation or @data-lgu-photo]', $implanGroup)->count());
        $movText = $xpath->evaluate('string(.//*[@data-implan-mov])', $implanGroup);
        $this->assertStringNotContainsString('DILG', $movText);
        $this->assertStringNotContainsString('DSWD', $movText);
        $this->assertStringNotContainsString('DOH', $movText);
    }

    public function test_xlsx_download_is_valid_and_contains_only_the_selected_agency_scope(): void
    {
        $template = public_path('assets/IMPLANs/ELCAC-IMPLAN_BSC.xlsx');
        $before = hash_file('sha256', $template);

        $sulop = $this->createImplan($this->sulopLgu, 'Sulop export issue');
        $this->createImplan($this->sulopLgu, 'Sulop second export issue');
        $matanao = $this->createImplan($this->matanaoLgu, 'Matanao excluded issue');
        $dswd = GovAgency::create(['name' => 'Department of Social Welfare and Development', 'acronym' => 'DSWD']);
        $doh = GovAgency::create(['name' => 'Department of Health', 'acronym' => 'DOH']);
        $denr = GovAgency::create(['name' => 'Department of Environment and Natural Resources', 'acronym' => 'DENR']);
        $sulop->update(['agencies' => [$this->agency->id, $dswd->id, $doh->id]]);
        $matanao->update(['agencies' => [$denr->id]]);

        $agencyResponses = collect();
        foreach ([
            [$this->agency, 'accepted', 'DILG demo action', 'DILG demo remarks'],
            [$dswd, 'pending', 'DSWD demo action', 'DSWD demo remarks'],
            [$doh, 'rejected', 'DOH demo action', 'DOH demo remarks'],
        ] as [$agency, $status, $actionTaken, $remarks]) {
            $agencyResponses->put($agency->acronym, AgencyImplanResponse::create([
                'implementation_id' => $sulop->id,
                'gov_agency_id' => $agency->id,
                'response_status' => $status,
                'program' => "{$agency->acronym} forbidden program override",
                'action_taken' => $actionTaken,
                'remarks' => $remarks,
            ]));
        }
        ImplementationPhoto::create([
            'implementation_id' => $sulop->id,
            'agency_implan_response_id' => $agencyResponses['DOH']->id,
            'image' => 'implan/photos/doh.jpg',
        ]);

        $response = $this->actingAs($this->sulopLgu)->get(route('lgu.implan.download', [
            'agency' => $this->agency->id,
        ]))
            ->assertOk()
            ->assertDownload('sulop-implan-dilg.xlsx')
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $binary = $response->streamedContent();
        $this->assertSame('PK', substr($binary, 0, 2));
        $this->assertStringNotContainsString('<html', strtolower(substr($binary, 0, 256)));
        $temporary = tempnam(sys_get_temp_dir(), 'implan-test-').'.xlsx';
        file_put_contents($temporary, $binary);
        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($temporary) === true);
        $this->assertNotFalse($archive->locateName('[Content_Types].xml'));
        $archive->close();
        $workbook = IOFactory::load($temporary);
        $sheet = $workbook->getActiveSheet();

        $this->assertSame([
            'Issues and Concerns to be Addressed',
            'Program/Project/Activity',
            'Target Area',
            'Target Beneficiaries',
            'Expected Results/Outcome',
            'Responsible Agency',
            'Resources Needed',
            'Support Needed',
            'Duration',
            'Action Taken',
            'Remarks',
        ], array_map(fn (string $column) => $sheet->getCell("{$column}3")->getValue(), range('A', 'K')));
        $this->assertSame('(Funding)', $sheet->getCell('G4')->getValue());
        $this->assertSame('Sulop export issue', $sheet->getCell('A5')->getValue());
        $this->assertSame('Sulop second export issue', $sheet->getCell('A6')->getValue());
        $this->assertSame('Sulop export issue program', $sheet->getCell('B5')->getValue());
        $responsibleAgencies = (string) $sheet->getCell('F5')->getValue();
        $this->assertSame('DILG, DSWD, DOH', $responsibleAgencies);
        $this->assertStringNotContainsString('MOV Uploaded', $responsibleAgencies);
        $this->assertStringNotContainsString('No MOV Uploaded', $responsibleAgencies);
        $this->assertSame('DILG demo action', $sheet->getCell('J5')->getValue());
        $this->assertSame('DILG demo remarks', $sheet->getCell('K5')->getValue());
        $this->assertStringNotContainsString('DSWD demo action', json_encode($sheet->toArray()));
        $this->assertStringNotContainsString('DOH demo action', json_encode($sheet->toArray()));
        $this->assertStringNotContainsString('DSWD demo remarks', json_encode($sheet->toArray()));
        $this->assertStringNotContainsString('DOH demo remarks', json_encode($sheet->toArray()));
        $this->assertTrue($sheet->getStyle('J5')->getAlignment()->getWrapText());
        $this->assertTrue($sheet->getStyle('K5')->getAlignment()->getWrapText());
        $this->assertTrue($sheet->getStyle('F5')->getAlignment()->getWrapText());
        $this->assertStringNotContainsString('forbidden program override', json_encode($sheet->toArray()));
        $this->assertStringNotContainsString('Matanao excluded issue', json_encode($sheet->toArray()));
        $this->assertSame('landscape', $sheet->getPageSetup()->getOrientation());
        $this->assertNotEmpty($sheet->getDrawingCollection());

        $workbook->disconnectWorksheets();
        unlink($temporary);
        $this->assertSame($before, hash_file('sha256', $template));

        $this->actingAs($this->sulopLgu)
            ->get(route('lgu.implan.download', ['agency' => $denr->id]))
            ->assertForbidden();
        $this->actingAs($this->sulopLgu)
            ->getJson(route('lgu.implan.download', ['agency' => 'tampered']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('agency');
    }

    private function createImplan(User $lgu, string $issue, string $status = 'submitted'): Implementation
    {
        return Implementation::create([
            'lgu_user_id' => $lgu->id,
            'uploaded_at' => now()->toDateString(),
            'issues' => $issue,
            'program' => "{$issue} program",
            'target_areas' => [],
            'agencies' => [$this->agency->id],
            'beneficiaries' => "{$issue} beneficiaries",
            'outcome' => "{$issue} outcome",
            'resources' => "{$issue} funding",
            'support' => "{$issue} support",
            'duration' => '2026',
            'status' => $status,
        ]);
    }
}
