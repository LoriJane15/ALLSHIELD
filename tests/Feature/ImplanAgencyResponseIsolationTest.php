<?php

namespace Tests\Feature;

use App\Models\AgencyImplanResponse;
use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\ImplementationFile;
use App\Models\ImplementationPlan;
use App\Models\Municipality;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImplanAgencyResponseIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $municipality;

    private User $lgu;

    /** @var array<string, GovAgency> */
    private array $agencies;

    /** @var array<string, User> */
    private array $agencyUsers;

    private Implementation $implan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->municipality = Municipality::create(['name' => 'Sulop']);
        $this->lgu = User::factory()->lgu($this->municipality->id)->create();

        foreach ([
            'A' => ['Department of the Interior and Local Government', 'DILG'],
            'B' => ['Department of Social Welfare and Development', 'DSWD'],
            'C' => ['Department of Health', 'DOH'],
        ] as $key => [$name, $acronym]) {
            $this->agencies[$key] = GovAgency::create(['name' => $name, 'acronym' => $acronym]);
            $this->agencyUsers[$key] = User::factory()->govAgency($this->agencies[$key]->id)->create();
        }

        $this->implan = Implementation::create([
            'lgu_user_id' => $this->lgu->id,
            'uploaded_at' => now()->toDateString(),
            'issues' => 'LGU original issue',
            'program' => 'LGU original program',
            'target_areas' => [],
            'agencies' => collect($this->agencies)->pluck('id')->values()->all(),
            'beneficiaries' => 'LGU original beneficiaries',
            'outcome' => 'LGU original outcome',
            'resources' => 'LGU original resources',
            'support' => 'LGU original support',
            'duration' => 'LGU original duration',
            'type_gov' => 'NGA',
            'sources' => 'LGU original source',
            'remarks' => 'Historical remarks',
            'status' => 'submitted',
        ]);
    }

    public function test_original_lgu_create_flow_creates_one_record_without_add_row_or_lgu_remarks(): void
    {
        $before = Implementation::count();

        $this->actingAs($this->lgu)->post(route('lgu.implan.store'), [
            'issues' => 'New direct LGU IMPLAN',
            'agencies' => [$this->agencies['A']->id],
            'remarks' => 'Crafted LGU remarks must be ignored',
        ])->assertRedirect();

        $created = Implementation::where('issues', 'New direct LGU IMPLAN')->firstOrFail();
        $this->assertSame($before + 1, Implementation::count());
        $this->assertNull($created->implementation_plan_id);
        $this->assertNull($created->remarks);
        $this->assertSame('not yet started', $created->status);
        $this->assertSame(0, ImplementationPlan::count());

        $this->actingAs($this->lgu)->get(route('lgu.implan.index'))
            ->assertOk()
            ->assertDontSee('Add Row')
            ->assertDontSee('name="remarks"', false);

        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('lgu.implan.rows.store'));
    }

    public function test_crafted_lgu_updates_cannot_change_historical_remarks(): void
    {
        $this->actingAs($this->lgu)->put(route('lgu.implan.implementation', $this->implan), [
            'program' => 'LGU updated program',
            'remarks' => 'Crafted replacement',
        ])->assertRedirect();

        $this->implan->refresh();
        $this->assertSame('LGU updated program', $this->implan->program);
        $this->assertSame('Historical remarks', $this->implan->remarks);
    }

    public function test_submission_routes_directly_to_assigned_agencies_without_katuparan_verification(): void
    {
        $draft = Implementation::create([
            'lgu_user_id' => $this->lgu->id,
            'issues' => 'Direct routing draft',
            'agencies' => [$this->agencies['A']->id, $this->agencies['B']->id],
            'status' => 'not yet started',
        ]);

        $this->actingAs($this->agencyUsers['A'])->get(route('gov_agency.implan.show', $draft))->assertForbidden();
        $this->actingAs($this->lgu)->post(route('lgu.implan.submit', $draft))->assertRedirect();

        $this->assertSame('submitted', $draft->fresh()->status);
        $this->assertNotSame('for verification', $draft->fresh()->status);
        $this->assertNotSame('verified', $draft->fresh()->status);
        $this->actingAs($this->agencyUsers['A'])->get(route('gov_agency.implan.show', $draft))->assertOk();
        $this->actingAs($this->agencyUsers['B'])->get(route('gov_agency.implan.show', $draft))->assertOk();
        $this->actingAs($this->agencyUsers['C'])->get(route('gov_agency.implan.show', $draft))->assertForbidden();
    }

    public function test_agency_form_and_update_allow_only_action_taken_and_remarks(): void
    {
        $this->actingAs($this->agencyUsers['A'])->get(route('gov_agency.implan.show', $this->implan))
            ->assertOk()
            ->assertSee('name="action_taken"', false)
            ->assertSee('name="remarks"', false)
            ->assertDontSee('name="program"', false)
            ->assertDontSee('name="beneficiaries"', false)
            ->assertDontSee('name="outcome"', false)
            ->assertDontSee('name="resources"', false)
            ->assertDontSee('name="support"', false)
            ->assertDontSee('name="duration"', false)
            ->assertDontSee('name="type_gov"', false)
            ->assertDontSee('name="sources"', false);

        $response = AgencyImplanResponse::create([
            'implementation_id' => $this->implan->id,
            'gov_agency_id' => $this->agencies['A']->id,
            'program' => 'Historical agency program',
            'beneficiaries' => 'Historical agency beneficiaries',
            'outcome' => 'Historical agency outcome',
            'resources' => 'Historical agency resources',
            'support' => 'Historical agency support',
            'duration' => 'Historical agency duration',
            'type_gov' => 'PGO',
            'sources' => 'Historical agency source',
        ]);

        $baseline = $this->implan->only([
            'issues', 'program', 'target_areas', 'beneficiaries', 'outcome', 'agencies',
            'resources', 'support', 'duration', 'type_gov', 'sources',
        ]);

        $this->actingAs($this->agencyUsers['A'])->put(route('gov_agency.implan.update', $this->implan), [
            'issues' => 'Crafted issue',
            'program' => 'Crafted program',
            'target_areas' => [999],
            'beneficiaries' => 'Crafted beneficiaries',
            'outcome' => 'Crafted outcome',
            'agencies' => [$this->agencies['A']->id],
            'resources' => 'Crafted resources',
            'support' => 'Crafted support',
            'duration' => 'Crafted duration',
            'type_gov' => 'Development Partner',
            'sources' => 'Crafted source',
            'action_taken' => 'DILG demo action',
            'remarks' => 'DILG demo remarks',
        ])->assertRedirect();

        $response->refresh();
        $this->assertSame('DILG demo action', $response->action_taken);
        $this->assertSame('DILG demo remarks', $response->remarks);
        $this->assertSame('Historical agency program', $response->program);
        $this->assertSame('Historical agency beneficiaries', $response->beneficiaries);
        $this->assertSame('Historical agency outcome', $response->outcome);
        $this->assertSame('Historical agency resources', $response->resources);
        $this->assertSame('Historical agency support', $response->support);
        $this->assertSame('Historical agency duration', $response->duration);
        $this->assertSame('PGO', $response->type_gov);
        $this->assertSame('Historical agency source', $response->sources);
        $this->assertSame($baseline, $this->implan->fresh()->only(array_keys($baseline)));
    }

    public function test_action_taken_and_remarks_remain_independent_for_multiple_agencies(): void
    {
        foreach (['A', 'B', 'C'] as $key) {
            $acronym = $this->agencies[$key]->acronym;
            $this->actingAs($this->agencyUsers[$key])->put(route('gov_agency.implan.update', $this->implan), [
                'action_taken' => "{$acronym} demo action",
                'remarks' => "{$acronym} demo remarks",
            ])->assertRedirect();
        }

        $this->assertSame(3, AgencyImplanResponse::where('implementation_id', $this->implan->id)->count());
        foreach (['A', 'B', 'C'] as $key) {
            $acronym = $this->agencies[$key]->acronym;
            $this->assertSame("{$acronym} demo action", $this->response($key)->action_taken);
            $this->assertSame("{$acronym} demo remarks", $this->response($key)->remarks);
        }

        $this->actingAs($this->agencyUsers['B'])->put(route('gov_agency.implan.update', $this->implan), [
            'action_taken' => 'DSWD updated action',
            'remarks' => 'DSWD updated remarks',
        ])->assertRedirect();

        $this->assertSame('DILG demo action', $this->response('A')->action_taken);
        $this->assertSame('DILG demo remarks', $this->response('A')->remarks);
        $this->assertSame('DSWD updated action', $this->response('B')->action_taken);
        $this->assertSame('DSWD updated remarks', $this->response('B')->remarks);
        $this->assertSame('DOH demo action', $this->response('C')->action_taken);
        $this->assertSame('DOH demo remarks', $this->response('C')->remarks);
        $this->assertSame('LGU original program', $this->implan->fresh()->program);
        $this->assertSame('Historical remarks', $this->implan->fresh()->remarks);
    }

    public function test_hidden_identifier_tampering_cannot_overwrite_another_agency_response(): void
    {
        $responseB = AgencyImplanResponse::create([
            'implementation_id' => $this->implan->id,
            'gov_agency_id' => $this->agencies['B']->id,
            'action_taken' => 'B protected action',
            'remarks' => 'B protected remarks',
        ]);

        $this->actingAs($this->agencyUsers['A'])->put(route('gov_agency.implan.update', $this->implan), [
            'agency_id' => $this->agencies['B']->id,
            'gov_agency_id' => $this->agencies['B']->id,
            'response_id' => $responseB->id,
            'implementation_id' => $this->implan->id,
            'action_taken' => 'Tampered action',
            'remarks' => 'Tampered remarks',
        ])->assertRedirect();

        $this->assertSame('B protected action', $responseB->fresh()->action_taken);
        $this->assertSame('Tampered action', $this->response('A')->action_taken);

        $other = Implementation::create([
            'lgu_user_id' => $this->lgu->id,
            'issues' => 'Assigned only to B',
            'agencies' => [$this->agencies['B']->id],
            'status' => 'submitted',
        ]);
        $this->actingAs($this->agencyUsers['A'])
            ->put(route('gov_agency.implan.update', $other), ['action_taken' => 'Forbidden'])
            ->assertForbidden();
    }

    public function test_original_accept_reject_document_and_photo_actions_still_work_per_agency(): void
    {
        Storage::fake('public');

        $this->actingAs($this->agencyUsers['A'])->post(route('gov_agency.implan.respond', $this->implan), [
            'response_status' => 'accepted',
        ])->assertRedirect();
        $this->actingAs($this->agencyUsers['B'])->post(route('gov_agency.implan.respond', $this->implan), [
            'response_status' => 'rejected',
            'rejection_reason' => 'B reason',
        ])->assertRedirect();

        foreach (['A', 'B'] as $key) {
            $this->actingAs($this->agencyUsers[$key])->post(route('gov_agency.implan.agenda', $this->implan), [
                'file_name' => "{$key} supporting document",
                'description' => "{$key} description",
                'files' => [UploadedFile::fake()->create("{$key}.pdf", 10, 'application/pdf')],
            ])->assertRedirect();
            $this->actingAs($this->agencyUsers[$key])->post(route('gov_agency.implan.photos', $this->implan), [
                'photos' => [UploadedFile::fake()->image("{$key}.jpg")],
            ])->assertRedirect();
        }

        $this->assertSame('accepted', $this->response('A')->response_status);
        $this->assertSame('rejected', $this->response('B')->response_status);
        $this->assertSame('B reason', $this->response('B')->rejection_reason);
        $this->assertSame('A supporting document', $this->response('A')->files()->sole()->file_name);
        $this->assertSame('B supporting document', $this->response('B')->files()->sole()->file_name);
        $this->assertSame(1, $this->response('A')->photos()->count());
        $this->assertSame(1, $this->response('B')->photos()->count());
    }

    public function test_all_roles_see_attributed_responses_while_only_the_current_agency_response_is_editable(): void
    {
        foreach (['A', 'B', 'C'] as $key) {
            $acronym = $this->agencies[$key]->acronym;
            AgencyImplanResponse::create([
                'implementation_id' => $this->implan->id,
                'gov_agency_id' => $this->agencies[$key]->id,
                'program' => "{$acronym} forbidden program override",
                'action_taken' => "{$acronym} demo action",
                'remarks' => "{$acronym} demo remarks",
            ]);
        }

        $agencyView = $this->actingAs($this->agencyUsers['A'])->get(route('gov_agency.implan.show', $this->implan))
            ->assertOk()
            ->assertSee('LGU original program')
            ->assertSee('DILG — DILG demo action')
            ->assertSee('DSWD — DSWD demo action')
            ->assertSee('DOH — DOH demo action')
            ->assertSee('DILG — DILG demo remarks')
            ->assertSee('DSWD — DSWD demo remarks')
            ->assertSee('DOH — DOH demo remarks')
            ->assertSee('name="action_taken"', false)
            ->assertSee('name="remarks"', false)
            ->assertDontSee('name="program"', false)
            ->assertDontSee('forbidden program override');

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($agencyView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//textarea[@name="action_taken"]')->count());
        $this->assertSame(1, $xpath->query('//textarea[@name="remarks"]')->count());
        $this->assertSame('DILG demo action', trim($xpath->evaluate('string(//textarea[@name="action_taken"])')));
        $this->assertSame('DILG demo remarks', trim($xpath->evaluate('string(//textarea[@name="remarks"])')));

        $this->actingAs($this->lgu)->get(route('lgu.implan.show', $this->implan))
            ->assertOk()
            ->assertSee('LGU original program')
            ->assertSee('DILG — DILG demo action')->assertSee('DSWD — DSWD demo action')->assertSee('DOH — DOH demo action')
            ->assertSee('DILG — DILG demo remarks')->assertSee('DSWD — DSWD demo remarks')->assertSee('DOH — DOH demo remarks')
            ->assertDontSee('forbidden program override')
            ->assertDontSee('name="action_taken"', false)->assertDontSee('name="remarks"', false);

        $katuparan = User::factory()->role('admin')->create();
        $this->actingAs($katuparan)->get(route('admin.implan.municipality', $this->municipality))
            ->assertOk()
            ->assertSee('LGU original program')
            ->assertSee('DILG — DILG demo action')->assertSee('DSWD — DSWD demo action')->assertSee('DOH — DOH demo action')
            ->assertSee('DILG — DILG demo remarks')->assertSee('DSWD — DSWD demo remarks')->assertSee('DOH — DOH demo remarks')
            ->assertDontSee('forbidden program override')
            ->assertSee('@media print', false)
            ->assertDontSee('name="action_taken"', false)->assertDontSee('Verify')->assertDontSee('Reassign');
        $this->actingAs($katuparan)->post(route('admin.implan.verify', $this->implan))->assertForbidden();
        $this->actingAs($katuparan)->post(route('admin.implan.reassign', $this->implan))->assertForbidden();
    }

    public function test_lgu_original_and_agency_owned_attachments_remain_separate(): void
    {
        Storage::fake('public');
        ImplementationFile::create([
            'implementation_id' => $this->implan->id,
            'file_name' => 'LGU original agenda',
            'pdf' => 'implan/agenda/lgu.pdf',
        ]);

        $this->actingAs($this->agencyUsers['A'])->post(route('gov_agency.implan.agenda', $this->implan), [
            'file_name' => 'Agency A agenda',
            'files' => [UploadedFile::fake()->create('agency-a.pdf', 10, 'application/pdf')],
        ])->assertRedirect();

        $this->assertSame(1, $this->implan->originalFiles()->count());
        $this->assertSame(1, $this->response('A')->files()->count());
        $this->assertNull($this->implan->originalFiles()->sole()->agency_implan_response_id);
        $this->assertSame($this->response('A')->id, $this->response('A')->files()->sole()->agency_implan_response_id);
    }

    public function test_official_status_is_separate_from_existing_documentation_ownership_ui(): void
    {
        Storage::fake('public');

        $this->actingAs($this->agencyUsers['A'])->post(route('gov_agency.implan.respond', $this->implan), [
            'response_status' => 'accepted',
        ])->assertRedirect();
        $this->actingAs($this->agencyUsers['C'])->post(route('gov_agency.implan.respond', $this->implan), [
            'response_status' => 'rejected',
            'rejection_reason' => 'DOH rejection reason',
        ])->assertRedirect();
        $this->actingAs($this->agencyUsers['A'])->post(route('gov_agency.implan.photos', $this->implan), [
            'photos' => [UploadedFile::fake()->image('dilg.jpg')],
        ])->assertRedirect();
        $this->actingAs($this->agencyUsers['B'])->post(route('gov_agency.implan.agenda', $this->implan), [
            'file_name' => 'DSWD supporting document',
            'files' => [UploadedFile::fake()->create('dswd.pdf', 10, 'application/pdf')],
        ])->assertRedirect();

        foreach ($this->officialRoleViews() as $view) {
            $view->assertOk()
                ->assertSee('DILG (Accepted)')
                ->assertSee('DSWD (Pending)')
                ->assertSee('DOH (Rejected)')
                ->assertDontSee('Documentation Uploaded')
                ->assertDontSee('No Documentation Uploaded')
                ->assertSee('@media print', false);
        }

        $this->assertSame(1, $this->response('A')->photos()->count());
        $this->assertSame(1, $this->response('B')->files()->count());

        $lguView = $this->actingAs($this->lgu)->get(route('lgu.implan.show', $this->implan))->assertOk();
        $lguDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $lguDocument->loadHTML($lguView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $lguXPath = new DOMXPath($lguDocument);
        $this->assertSame('DILG', trim($lguXPath->evaluate('string(//div[@id="monitoring"]//div[contains(@class,"small")])')));
        $this->assertSame('DSWD', trim($lguXPath->evaluate('string(//div[contains(@class,"agenda-file-item")][.//a[contains(normalize-space(.),"DSWD supporting document")]]//span)')));

        $katuparan = User::factory()->role('admin')->create();
        $katuparanView = $this->actingAs($katuparan)
            ->get(route('admin.implan.municipality', $this->municipality))
            ->assertOk()
            ->assertDontSee('Documentation Uploaded');
        $katuparanDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $katuparanDocument->loadHTML($katuparanView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $katuparanXPath = new DOMXPath($katuparanDocument);
        $this->assertSame('DILG', trim($katuparanXPath->evaluate('string(//*[@data-agency-photo="'.$this->agencies['A']->id.'"]//div)')));
        $this->assertStringContainsString('DSWD', trim($katuparanXPath->evaluate('string(//*[@data-agency-attachment="'.$this->agencies['B']->id.'"]//div)')));
    }

    public function test_assigned_agencies_share_documentation_read_only_while_unassigned_agency_is_forbidden(): void
    {
        Storage::fake('public');

        $this->actingAs($this->agencyUsers['A'])->post(route('gov_agency.implan.agenda', $this->implan), [
            'file_name' => 'DILG shared PDF',
            'files' => [UploadedFile::fake()->create('dilg-shared.pdf', 10, 'application/pdf')],
        ])->assertRedirect();
        $this->actingAs($this->agencyUsers['A'])->post(route('gov_agency.implan.photos', $this->implan), [
            'photos' => [UploadedFile::fake()->image('dilg-shared.jpg')],
        ])->assertRedirect();
        $this->actingAs($this->agencyUsers['B'])->post(route('gov_agency.implan.agenda', $this->implan), [
            'file_name' => 'DSWD shared PDF',
            'files' => [UploadedFile::fake()->create('dswd-shared.pdf', 10, 'application/pdf')],
        ])->assertRedirect();
        $this->actingAs($this->agencyUsers['C'])->post(route('gov_agency.implan.photos', $this->implan), [
            'photos' => [UploadedFile::fake()->image('doh-shared.jpg')],
        ])->assertRedirect();

        $dilgFile = $this->response('A')->files()->sole();
        $dilgPhoto = $this->response('A')->photos()->sole();

        foreach (['A', 'B', 'C'] as $viewer) {
            $this->actingAs($this->agencyUsers[$viewer])
                ->get(route('gov_agency.implan.show', $this->implan))
                ->assertOk()
                ->assertSee('DILG shared PDF')
                ->assertSee('DSWD shared PDF')
                ->assertSee('data-agency-attachment="'.$this->agencies['A']->id.'"', false)
                ->assertSee('data-agency-attachment="'.$this->agencies['B']->id.'"', false)
                ->assertSee('data-agency-photo="'.$this->agencies['A']->id.'"', false)
                ->assertSee('data-agency-photo="'.$this->agencies['C']->id.'"', false)
                ->assertSee('DILG')
                ->assertSee('DSWD')
                ->assertSee('DOH');

            $this->actingAs($this->agencyUsers[$viewer])
                ->get(route('gov_agency.implan.files.show', [$this->implan, $dilgFile]))
                ->assertOk();
            $this->actingAs($this->agencyUsers[$viewer])
                ->get(route('gov_agency.implan.photos.show', [$this->implan, $dilgPhoto]))
                ->assertOk();
        }

        $lguView = $this->actingAs($this->lgu)
            ->get(route('lgu.implan.show', $this->implan))
            ->assertOk()
            ->assertSee('DILG shared PDF')
            ->assertSee('DSWD shared PDF');
        $lguDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $lguDocument->loadHTML($lguView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $lguXPath = new DOMXPath($lguDocument);
        $photoOwners = [];
        foreach ($lguXPath->query('//div[@id="monitoring"]//div[contains(@class,"small")]') as $owner) {
            $photoOwners[] = trim($owner->textContent);
        }
        $this->assertContains('DILG', $photoOwners);
        $this->assertContains('DOH', $photoOwners);

        $katuparan = User::factory()->role('admin')->create();
        $this->actingAs($katuparan)
            ->get(route('admin.implan.municipality', $this->municipality))
            ->assertOk()
            ->assertSee('data-agency-attachment="'.$this->agencies['A']->id.'"', false)
            ->assertSee('data-agency-attachment="'.$this->agencies['B']->id.'"', false)
            ->assertSee('data-agency-photo="'.$this->agencies['A']->id.'"', false)
            ->assertSee('data-agency-photo="'.$this->agencies['C']->id.'"', false)
            ->assertSee('DILG shared PDF')
            ->assertSee('DSWD shared PDF');

        $dilgFileOwner = $dilgFile->agency_implan_response_id;
        $this->actingAs($this->agencyUsers['B'])->put(route('gov_agency.implan.update', $this->implan), [
            'agency_implan_response_id' => $this->response('B')->id,
            'file_id' => $dilgFile->id,
            'action_taken' => 'DSWD action remains isolated',
        ])->assertRedirect();
        $this->assertSame($dilgFileOwner, $dilgFile->fresh()->agency_implan_response_id);
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('gov_agency.implan.files.destroy'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('gov_agency.implan.photos.destroy'));

        $denr = GovAgency::create(['name' => 'Department of Environment and Natural Resources', 'acronym' => 'DENR']);
        $denrUser = User::factory()->govAgency($denr->id)->create();
        $this->actingAs($denrUser)->get(route('gov_agency.implan.show', $this->implan))->assertForbidden();
        $this->actingAs($denrUser)
            ->get(route('gov_agency.implan.files.show', [$this->implan, $dilgFile]))
            ->assertForbidden();
        $this->actingAs($denrUser)
            ->get(route('gov_agency.implan.photos.show', [$this->implan, $dilgPhoto]))
            ->assertForbidden();
    }

    /** @return array<int, \Illuminate\Testing\TestResponse> */
    private function officialRoleViews(): array
    {
        $katuparan = User::factory()->role('admin')->create();

        return [
            $this->actingAs($this->lgu)->get(route('lgu.implan.show', $this->implan)),
            $this->actingAs($this->agencyUsers['A'])->get(route('gov_agency.implan.show', $this->implan)),
            $this->actingAs($katuparan)->get(route('admin.implan.municipality', $this->municipality)),
        ];
    }

    private function response(string $key): AgencyImplanResponse
    {
        return AgencyImplanResponse::where([
            'implementation_id' => $this->implan->id,
            'gov_agency_id' => $this->agencies[$key]->id,
        ])->firstOrFail();
    }
}
