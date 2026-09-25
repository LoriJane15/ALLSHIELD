<?php

namespace Tests\Feature;

use App\Models\AgencyImplanResponse;
use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\ImplementationFile;
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
        foreach (['Sulop A', 'Sulop B', 'Sulop C'] as $issue) {
            $this->createImplan($this->sulopLgu, $issue);
        }
        $this->createImplan($this->sulopLgu, 'Sulop draft', 'not yet started');
        $this->createImplan($this->matanaoLgu, 'Matanao D');
        $this->createImplan($this->matanaoLgu, 'Matanao E');

        $response = $this->actingAs($this->sulopLgu)->get(route('lgu.implan.official'))
            ->assertOk()
            ->assertSee('Sulop A')->assertSee('Sulop B')->assertSee('Sulop C')
            ->assertDontSee('Sulop draft')->assertDontSee('Matanao D')
            ->assertSee('data-print-implan', false)
            ->assertSee('assets/IMPLANs/ELCAC.png', false)
            ->assertSee('data-download-implan', false);

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

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
        $this->assertCount(3, $xpath->query('//*[@data-official-implan]//tbody/tr[@data-implementation-row]'));
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

        ImplementationFile::create([
            'implementation_id' => $implanA->id,
            'file_name' => 'LGU Agenda A.pdf',
            'description' => 'Road agenda',
            'pdf' => 'implan/agenda/agenda-a.pdf',
        ]);
        ImplementationFile::create([
            'implementation_id' => $implanB->id,
            'file_name' => 'LGU Agenda B.pdf',
            'description' => 'Health agenda',
            'pdf' => 'implan/agenda/agenda-b.pdf',
        ]);
        ImplementationFile::create([
            'implementation_id' => $matanaoImplan->id,
            'file_name' => 'Matanao hidden agenda.pdf',
            'pdf' => 'implan/agenda/matanao.pdf',
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
        ImplementationFile::create([
            'implementation_id' => $implanB->id,
            'agency_implan_response_id' => $dilgResponse->id,
            'file_name' => 'Inconsistent ownership must not render.pdf',
            'pdf' => 'implan/agenda/inconsistent.pdf',
        ]);

        $admin = User::factory()->role('admin')->create();
        $response = $this->actingAs($admin)
            ->get(route('admin.implan.municipality', $this->sulop))
            ->assertOk()
            ->assertSee('IMPLAN Supporting Records')
            ->assertDontSee('Matanao hidden agenda.pdf')
            ->assertDontSee('Inconsistent ownership must not render.pdf');

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
        $this->assertStringContainsString('LGU Agenda A.pdf', $implanAGroup->textContent);
        $this->assertSame(1, $xpath->query('.//*[@data-agency-photo="'.$this->agency->id.'"]', $implanAGroup)->count());
        $this->assertStringNotContainsString('LGU Agenda B.pdf', $implanAGroup->textContent);
        $this->assertStringNotContainsString('DOH', $implanAGroup->textContent);

        $this->assertStringContainsString('Health Services', $implanBGroup->textContent);
        $this->assertStringContainsString('LGU Agenda B.pdf', $implanBGroup->textContent);
        $this->assertSame(1, $xpath->query('.//*[@data-agency-photo="'.$doh->id.'"]', $implanBGroup)->count());
        $this->assertStringNotContainsString('LGU Agenda A.pdf', $implanBGroup->textContent);
        $this->assertStringNotContainsString('DILG', $implanBGroup->textContent);

        $this->assertStringContainsString('Water Supply', $emptyGroup->textContent);
        $this->assertStringContainsString('No LGU agenda/supporting file uploaded.', $emptyGroup->textContent);
        $this->assertStringContainsString('No documentation uploaded.', $emptyGroup->textContent);
        $this->assertSame(0, $xpath->query('.//form', $supportingArea)->count());
        $this->assertSame(0, $xpath->query('.//input', $supportingArea)->count());
        $this->assertSame(0, $xpath->query('.//button', $supportingArea)->count());
    }

    public function test_katuparan_separates_lgu_originals_and_groups_agency_documentation_with_attribution(): void
    {
        $implan = $this->createImplan($this->sulopLgu, 'Multi-agency response');
        $dswd = GovAgency::create(['name' => 'Department of Social Welfare and Development', 'acronym' => 'DSWD']);
        $doh = GovAgency::create(['name' => 'Department of Health', 'acronym' => 'DOH']);
        $implan->update(['agencies' => [$this->agency->id, $dswd->id, $doh->id]]);

        ImplementationFile::create([
            'implementation_id' => $implan->id,
            'file_name' => 'Municipal Agenda.pdf',
            'description' => 'LGU meeting agenda',
            'pdf' => 'implan/agenda/municipal.pdf',
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
        ImplementationFile::create([
            'implementation_id' => $implan->id,
            'agency_implan_response_id' => $responses['DSWD']->id,
            'file_name' => 'DSWD Documentation.pdf',
            'description' => 'Beneficiary validation report',
            'pdf' => 'implan/agenda/dswd.pdf',
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
        $this->assertSame(1, $xpath->query('.//*[@data-implan-agenda]//*[@data-lgu-attachment][contains(., "Municipal Agenda.pdf")]', $implanGroup)->count());
        $this->assertStringContainsString('LGU Original', $xpath->query('.//*[@data-implan-agenda]', $implanGroup)->item(0)->textContent);
        $this->assertSame(1, $xpath->query('.//*[@data-implan-documentation]//*[@data-agency-photo="'.$this->agency->id.'"]', $implanGroup)->count());
        $this->assertSame(1, $xpath->query('.//*[@data-implan-documentation]//*[@data-agency-attachment="'.$dswd->id.'"]', $implanGroup)->count());
        $this->assertSame(1, $xpath->query('.//*[@data-implan-documentation]//*[@data-agency-photo="'.$doh->id.'"]', $implanGroup)->count());
        $this->assertStringContainsString('DILG', $xpath->query('.//*[@data-agency-documentation="'.$this->agency->id.'"]', $implanGroup)->item(0)->textContent);
        $this->assertStringContainsString('DSWD', $xpath->query('.//*[@data-agency-documentation="'.$dswd->id.'"]', $implanGroup)->item(0)->textContent);
        $this->assertStringContainsString('DOH', $xpath->query('.//*[@data-agency-documentation="'.$doh->id.'"]', $implanGroup)->item(0)->textContent);
    }

    public function test_xlsx_download_uses_template_keeps_source_unchanged_and_inserts_action_taken_before_remarks(): void
    {
        $template = public_path('assets/IMPLANs/ELCAC-IMPLAN_BSC.xlsx');
        $before = hash_file('sha256', $template);

        $sulop = $this->createImplan($this->sulopLgu, 'Sulop export issue');
        $this->createImplan($this->sulopLgu, 'Sulop second export issue');
        $this->createImplan($this->matanaoLgu, 'Matanao excluded issue');
        $dswd = GovAgency::create(['name' => 'Department of Social Welfare and Development', 'acronym' => 'DSWD']);
        $doh = GovAgency::create(['name' => 'Department of Health', 'acronym' => 'DOH']);
        $sulop->update(['agencies' => [$this->agency->id, $dswd->id, $doh->id]]);

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
        ImplementationFile::create([
            'implementation_id' => $sulop->id,
            'agency_implan_response_id' => $agencyResponses['DILG']->id,
            'file_name' => 'DILG supporting document',
            'pdf' => 'implan/agenda/dilg.pdf',
        ]);
        ImplementationPhoto::create([
            'implementation_id' => $sulop->id,
            'agency_implan_response_id' => $agencyResponses['DOH']->id,
            'image' => 'implan/photos/doh.jpg',
        ]);

        $response = $this->actingAs($this->sulopLgu)->get(route('lgu.implan.download'))
            ->assertOk()
            ->assertDownload('IMPLAN-Sulop.xlsx')
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $temporary = tempnam(sys_get_temp_dir(), 'implan-test-').'.xlsx';
        file_put_contents($temporary, $response->streamedContent());
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
        $this->assertSame("DILG (Accepted)\nDSWD (Pending)\nDOH (Rejected)", $responsibleAgencies);
        $this->assertStringNotContainsString('Documentation Uploaded', $responsibleAgencies);
        $this->assertStringNotContainsString('No Documentation Uploaded', $responsibleAgencies);
        $this->assertSame(
            "DILG — DILG demo action\nDSWD — DSWD demo action\nDOH — DOH demo action",
            $sheet->getCell('J5')->getValue()
        );
        $this->assertSame(
            "DILG — DILG demo remarks\nDSWD — DSWD demo remarks\nDOH — DOH demo remarks",
            $sheet->getCell('K5')->getValue()
        );
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
