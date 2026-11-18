<?php

namespace Tests\Feature;

use App\Models\AgencyImplanResponse;
use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\ImplementationPlan;
use App\Models\Municipality;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
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

    public function test_original_accept_reject_and_mov_upload_actions_still_work_per_agency(): void
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
            $this->actingAs($this->agencyUsers[$key])->post(route('gov_agency.implan.photos', $this->implan), [
                'photos' => [UploadedFile::fake()->image("{$key}.jpg")],
            ])->assertRedirect();
        }

        $this->assertSame('accepted', $this->response('A')->response_status);
        $this->assertSame('rejected', $this->response('B')->response_status);
        $this->assertSame('B reason', $this->response('B')->rejection_reason);
        $this->assertSame(1, $this->response('A')->photos()->count());
        $this->assertSame(1, $this->response('B')->photos()->count());
    }

    public function test_agency_sees_only_its_raw_response_while_lgu_and_admin_see_all_attributions(): void
    {
        $this->implan->update([
            'agencies' => [
                $this->agencies['A']->id,
                $this->agencies['C']->id,
                $this->agencies['B']->id,
            ],
        ]);

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
            ->assertSee('DILG demo action')
            ->assertSee('DILG demo remarks')
            ->assertDontSee('DILG — DILG demo action')
            ->assertDontSee('DILG — DILG demo remarks')
            ->assertDontSee('DSWD demo action')
            ->assertDontSee('DOH demo action')
            ->assertDontSee('DSWD demo remarks')
            ->assertDontSee('DOH demo remarks')
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
        $responsibleAgencyCell = $xpath->query('//*[@data-official-implan]//*[@data-lgu-original="agencies"]')->item(0);
        $this->assertNotNull($responsibleAgencyCell);
        $this->assertSame('DILG, DOH, DSWD', trim(preg_replace('/\s+/', ' ', $responsibleAgencyCell->textContent)));
        $this->assertSame(1, $xpath->query('.//*[@data-responsible-agencies]', $responsibleAgencyCell)->count());
        $this->assertSame(0, $xpath->query('.//strong|.//b|.//*[contains(concat(" ", normalize-space(@class), " "), " badge ")]', $responsibleAgencyCell)->count());

        $dohView = $this->actingAs($this->agencyUsers['C'])
            ->get(route('gov_agency.implan.show', $this->implan))
            ->assertOk()
            ->assertSee('DILG')
            ->assertSee('DOH')
            ->assertSee('DSWD')
            ->assertDontSee('(Accepted)')
            ->assertDontSee('(Pending)')
            ->assertDontSee('(Rejected)')
            ->assertSee('DOH demo action')
            ->assertSee('DOH demo remarks')
            ->assertDontSee('DOH — DOH demo action')
            ->assertDontSee('DILG demo action')
            ->assertDontSee('DSWD demo action')
            ->assertDontSee('DILG demo remarks')
            ->assertDontSee('DSWD demo remarks');

        $dohDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dohDocument->loadHTML($dohView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $dohXPath = new DOMXPath($dohDocument);
        $this->assertSame('DILG, DOH, DSWD', trim($dohXPath->evaluate('string(//*[@data-official-implan]//*[@data-responsible-agencies])')));

        $lguView = $this->actingAs($this->lgu)->get(route('lgu.implan.show', $this->implan))
            ->assertOk()
            ->assertSee('LGU original program')
            ->assertSee('View Agency IMPLANs')
            ->assertSee('Agency IMPLAN Responses')
            ->assertDontSee('Manage implementation details and mode of verification.')
            ->assertDontSee('LGU original and responsible-agency responses.')
            ->assertDontSee('data-print-implan', false)
            ->assertSee('object-fit: contain', false)
            ->assertDontSee('window.open', false)
            ->assertDontSee('window.location', false)
            ->assertDontSee('<iframe', false)
            ->assertSee("printButton.addEventListener('click', function () {\n            window.print();", false)
            ->assertDontSee('forbidden program override')
            ->assertDontSee('name="action_taken"', false)->assertDontSee('name="remarks"', false);

        $lguDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $lguDocument->loadHTML($lguView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $lguXPath = new DOMXPath($lguDocument);
        $this->assertSame(1, $lguXPath->query('//*[@id="agencyImplanResponsesModal"]')->count());
        $this->assertSame(3, $lguXPath->query('//*[@id="agencyImplanResponsesModal"]//*[@data-agency-implan-tab]')->count());
        $this->assertSame(1, $lguXPath->query('//*[@id="agencyImplanResponsesModal"]//*[@data-agency-implan-tab and contains(concat(" ", normalize-space(@class), " "), " active ")]')->count());
        $this->assertSame(1, $lguXPath->query('//*[@id="agencyImplanResponsesModal"]//*[@data-agency-implan-pane and contains(concat(" ", normalize-space(@class), " "), " active ")]')->count());
        $this->assertSame(1, $lguXPath->query('//*[@id="agencyImplanResponsesModal"]//*[@id="printActiveAgencyImplan"]')->count());
        $this->assertSame('button', strtolower($lguXPath->query('//*[@id="printActiveAgencyImplan"]')->item(0)?->nodeName ?? ''));
        $this->assertSame('', $lguXPath->query('//*[@id="printActiveAgencyImplan"]')->item(0)?->getAttribute('target'));
        $this->assertSame('', $lguXPath->query('//*[@id="printActiveAgencyImplan"]')->item(0)?->getAttribute('href'));
        $this->assertSame(0, $lguXPath->query('//iframe')->count());
        $this->assertSame(0, $lguXPath->query('//*[@data-official-implan][not(ancestor::*[@id="agencyImplanResponsesModal"])]')->count());

        $lguViewSource = file_get_contents(resource_path('views/lgu/implan/show.blade.php'));
        $this->assertIsString($lguViewSource);
        $this->assertDoesNotMatchRegularExpression('/\.lgu-agency-implan-modal\s+\.agency-mov\s+img\s*\{[^}]*object-fit\s*:\s*cover/is', $lguViewSource);
        $this->assertDoesNotMatchRegularExpression('/\.lgu-agency-implan-modal\s+\.agency-mov\s+img\s*\{[^}]*(?:(?<!-)width\s*:\s*100%|(?<!-)height\s*:\s*\d+px)/is', $lguViewSource);
        $this->assertStringContainsString('#agencyImplanResponsesModal .tab-pane { display: none !important;', $lguViewSource);
        $this->assertStringContainsString('#agencyImplanResponsesModal .tab-pane.active { display: block !important;', $lguViewSource);
        $this->assertStringContainsString('#agencyImplanResponsesModal .tab-pane.active > :not(.implan-official-document) { display: none !important;', $lguViewSource);
        $this->assertStringContainsString('#agencyImplanResponsesModal .agency-response-heading,', $lguViewSource);
        $this->assertStringContainsString('#agencyImplanResponsesModal [data-agency-mov-gallery] { display: none !important;', $lguViewSource);
        $this->assertStringContainsString('#agencyImplanResponsesModal .modal-header,', $lguViewSource);
        $this->assertStringContainsString('#agencyImplanResponsesModal .nav-tabs { display: none !important;', $lguViewSource);
        $this->assertStringContainsString('print-color-adjust: exact !important;', $lguViewSource);

        foreach (['A' => 'DILG', 'B' => 'DSWD', 'C' => 'DOH'] as $key => $acronym) {
            $agencyId = $this->agencies[$key]->id;
            $pane = $lguXPath->query('//*[@id="agency-implan-pane-'.$agencyId.'"]')->item(0);
            $this->assertNotNull($pane);
            $paneText = trim(preg_replace('/\s+/', ' ', $pane->textContent));
            $this->assertStringContainsString("{$acronym} demo action", $paneText);
            $this->assertStringContainsString("{$acronym} demo remarks", $paneText);
            $this->assertStringNotContainsString("{$acronym} — {$acronym} demo action", $paneText);
            $this->assertStringNotContainsString("{$acronym} — {$acronym} demo remarks", $paneText);
            $responsibleAgencyCell = $lguXPath->query('.//*[@data-lgu-original="agencies"]', $pane)->item(0);
            $this->assertNotNull($responsibleAgencyCell);
            $this->assertSame('DILG, DOH, DSWD', trim(preg_replace('/\s+/', ' ', $responsibleAgencyCell->textContent)));
            $this->assertSame(0, $lguXPath->query('.//strong|.//b|.//*[contains(concat(" ", normalize-space(@class), " "), " badge ")]', $responsibleAgencyCell)->count());
            $this->assertSame(1, $lguXPath->query('.//*[@data-official-implan]//*[@data-implementation-row]', $pane)->count());
            $this->assertSame(11, $lguXPath->query('.//*[@data-official-implan]//thead/tr/th', $pane)->count());
            $this->assertSame(1, $lguXPath->query('.//*[@data-official-implan]//*[@data-official-logo]', $pane)->count());
            $this->assertStringContainsString('BASIC SERVICES CLUSTER IMPLEMENTATION PLAN', $paneText);

            foreach (['A' => 'DILG', 'B' => 'DSWD', 'C' => 'DOH'] as $otherKey => $otherAcronym) {
                if ($otherKey !== $key) {
                    $this->assertStringNotContainsString("{$otherAcronym} demo action", $paneText);
                    $this->assertStringNotContainsString("{$otherAcronym} demo remarks", $paneText);
                }
            }
        }

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

    public function test_official_status_is_separate_from_mov_ownership_ui(): void
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
        $this->actingAs($this->agencyUsers['B'])->post(route('gov_agency.implan.photos', $this->implan), [
            'photos' => [UploadedFile::fake()->image('dswd.jpg')],
        ])->assertRedirect();

        $this->actingAs($this->agencyUsers['A'])->put(route('gov_agency.implan.update', $this->implan), [
            'action_taken' => 'Demo Action taken by DILG',
            'remarks' => 'Demo Remarks',
        ])->assertRedirect();
        $this->response('B')->update([
            'action_taken' => 'Private DSWD action',
            'remarks' => 'Private DSWD remarks',
        ]);

        $agencyView = $this->actingAs($this->agencyUsers['A'])
            ->get(route('gov_agency.implan.show', $this->implan))
            ->assertOk()
            ->assertSee('Implementation Plan')
            ->assertSee('Official IMPLAN Format')
            ->assertDontSee('Manage implementation details and mode of verification.')
            ->assertDontSee('LGU baseline with attributed agency responses.')
            ->assertSee('DILG')
            ->assertDontSee('DILG (Accepted)')
            ->assertDontSee('DSWD (Pending)')
            ->assertDontSee('DOH (Rejected)')
            ->assertSee('Demo Action taken by DILG')
            ->assertSee('Demo Remarks')
            ->assertDontSee('DILG — Demo Action taken by DILG')
            ->assertDontSee('DILG — Demo Remarks')
            ->assertDontSee('Private DSWD action')
            ->assertDontSee('Private DSWD remarks');

        $agencyDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $agencyDocument->loadHTML($agencyView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $agencyXPath = new DOMXPath($agencyDocument);
        $this->assertSame('DILG, DSWD, DOH', trim($agencyXPath->evaluate('string(//*[@data-official-implan]//*[@data-responsible-agencies])')));
        $this->assertSame('Demo Action taken by DILG', trim($agencyXPath->evaluate('string(//*[@data-official-implan]//*[@data-response-field="action_taken"])')));
        $this->assertSame('Demo Remarks', trim($agencyXPath->evaluate('string(//*[@data-official-implan]//*[@data-response-field="remarks"])')));

        $this->response('A')->update(['action_taken' => null, 'remarks' => null]);
        $emptyAgencyView = $this->actingAs($this->agencyUsers['A'])
            ->get(route('gov_agency.implan.show', $this->implan))
            ->assertOk()
            ->assertDontSee('DILG —');
        $emptyAgencyDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $emptyAgencyDocument->loadHTML($emptyAgencyView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $emptyAgencyXPath = new DOMXPath($emptyAgencyDocument);
        $this->assertSame(0, $emptyAgencyXPath->query('//*[@data-official-implan]//*[@data-response-field]')->count());

        $this->actingAs($this->lgu)
            ->get(route('lgu.implan.show', $this->implan))
            ->assertOk()
            ->assertDontSee('DILG (Accepted)')
            ->assertDontSee('DSWD (Pending)')
            ->assertDontSee('DOH (Rejected)')
            ->assertSee('Accepted')
            ->assertSee('Pending')
            ->assertSee('Rejected')
            ->assertDontSee('MOV Uploaded')
            ->assertDontSee('No MOV Uploaded')
            ->assertSee('@media print', false);

        $this->actingAs(User::factory()->role('admin')->create())
            ->get(route('admin.implan.municipality', $this->municipality))
            ->assertOk()
            ->assertSee('DILG (Accepted)')
            ->assertSee('DSWD (Pending)')
            ->assertSee('DOH (Rejected)')
            ->assertDontSee('MOV Uploaded')
            ->assertDontSee('No MOV Uploaded')
            ->assertSee('@media print', false);

        $this->assertSame(1, $this->response('A')->photos()->count());
        $this->assertSame(1, $this->response('B')->photos()->count());

        $lguView = $this->actingAs($this->lgu)->get(route('lgu.implan.show', $this->implan))->assertOk();
        $lguDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $lguDocument->loadHTML($lguView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $lguXPath = new DOMXPath($lguDocument);
        $dilgPane = $lguXPath->query('//*[@id="agency-implan-pane-'.$this->agencies['A']->id.'"]')->item(0);
        $dswdPane = $lguXPath->query('//*[@id="agency-implan-pane-'.$this->agencies['B']->id.'"]')->item(0);
        $dohPane = $lguXPath->query('//*[@id="agency-implan-pane-'.$this->agencies['C']->id.'"]')->item(0);
        $this->assertNotNull($dilgPane);
        $this->assertNotNull($dswdPane);
        $this->assertNotNull($dohPane);
        $this->assertSame(1, $lguXPath->query('.//*[@data-agency-mov-gallery]//img[@alt="Mode of verification"]', $dilgPane)->count());
        $this->assertSame(1, $lguXPath->query('.//*[@data-agency-mov-gallery]//img[@alt="Mode of verification"]', $dswdPane)->count());
        $this->assertSame(0, $lguXPath->query('.//*[@data-agency-mov-gallery]//img[@alt="Mode of verification"]', $dohPane)->count());

        $katuparan = User::factory()->role('admin')->create();
        $katuparanView = $this->actingAs($katuparan)
            ->get(route('admin.implan.municipality', $this->municipality))
            ->assertOk()
            ->assertDontSee('MOV Uploaded');
        $katuparanDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $katuparanDocument->loadHTML($katuparanView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $katuparanXPath = new DOMXPath($katuparanDocument);
        $this->assertSame(2, $katuparanXPath->query('//*[@data-implan-mov]//*[@data-mov-item]')->count());
        $this->assertSame(0, $katuparanXPath->query('//*[@data-implan-mov]//*[@data-agency-photo or @data-agency-documentation]')->count());
        $this->assertStringNotContainsString('DILG', $katuparanXPath->evaluate('string(//*[@data-implan-mov])'));
        $this->assertStringNotContainsString('DSWD', $katuparanXPath->evaluate('string(//*[@data-implan-mov])'));
    }

    public function test_shared_action_is_visible_but_each_agency_documentation_is_isolated(): void
    {
        Storage::fake('public');

        $this->actingAs($this->agencyUsers['A'])->post(route('gov_agency.implan.photos', $this->implan), [
            'photos' => [
                UploadedFile::fake()->image('dilg-shared-1.jpg'),
                UploadedFile::fake()->image('dilg-shared-2.jpg'),
            ],
        ])->assertRedirect();
        $this->actingAs($this->agencyUsers['B'])->post(route('gov_agency.implan.photos', $this->implan), [
            'photos' => [UploadedFile::fake()->image('dswd-shared.jpg')],
        ])->assertRedirect();
        $this->actingAs($this->agencyUsers['C'])->post(route('gov_agency.implan.photos', $this->implan), [
            'photos' => [UploadedFile::fake()->image('doh-shared.jpg')],
        ])->assertRedirect();

        $dilgPhotos = $this->response('A')->photos()->orderBy('id')->get();
        $this->assertCount(2, $dilgPhotos);
        $dilgPhoto = $dilgPhotos->first();
        $dilgSecondPhoto = $dilgPhotos->last();
        $dswdPhoto = $this->response('B')->photos()->sole();
        $dohPhoto = $this->response('C')->photos()->sole();

        $this->actingAs($this->agencyUsers['A'])
            ->get(route('gov_agency.implan.show', $this->implan))
            ->assertOk()
            ->assertSee(route('gov_agency.implan.photos.show', [$this->implan, $dilgPhoto]), false)
            ->assertSee(route('gov_agency.implan.photos.show', [$this->implan, $dilgSecondPhoto]), false)
            ->assertDontSee(route('gov_agency.implan.photos.show', [$this->implan, $dswdPhoto]), false)
            ->assertDontSee('data-agency-photo=', false);
        $this->actingAs($this->agencyUsers['B'])
            ->get(route('gov_agency.implan.show', $this->implan))
            ->assertOk()
            ->assertSee(route('gov_agency.implan.photos.show', [$this->implan, $dswdPhoto]), false)
            ->assertDontSee(route('gov_agency.implan.photos.show', [$this->implan, $dilgPhoto]), false)
            ->assertDontSee(route('gov_agency.implan.photos.show', [$this->implan, $dilgSecondPhoto]), false)
            ->assertDontSee('data-agency-photo=', false);
        $this->actingAs($this->agencyUsers['C'])
            ->get(route('gov_agency.implan.show', $this->implan))
            ->assertOk()
            ->assertSee(route('gov_agency.implan.photos.show', [$this->implan, $dohPhoto]), false)
            ->assertDontSee(route('gov_agency.implan.photos.show', [$this->implan, $dilgPhoto]), false)
            ->assertDontSee(route('gov_agency.implan.photos.show', [$this->implan, $dilgSecondPhoto]), false)
            ->assertDontSee('data-agency-photo=', false);

        $this->actingAs($this->agencyUsers['A'])
            ->get(route('gov_agency.implan.photos.show', [$this->implan, $dilgPhoto]))->assertOk();
        foreach (['B', 'C'] as $viewer) {
            $this->actingAs($this->agencyUsers[$viewer])
                ->get(route('gov_agency.implan.photos.show', [$this->implan, $dilgPhoto]))->assertNotFound();
        }

        $lguView = $this->actingAs($this->lgu)
            ->get(route('lgu.implan.show', $this->implan))
            ->assertOk();
        $lguDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $lguDocument->loadHTML($lguView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $lguXPath = new DOMXPath($lguDocument);
        foreach (['A' => $dilgPhoto, 'B' => $dswdPhoto, 'C' => $dohPhoto] as $agencyKey => $ownPhoto) {
            $pane = $lguXPath->query('//*[@id="agency-implan-pane-'.$this->agencies[$agencyKey]->id.'"]')->item(0);
            $this->assertNotNull($pane);
            $paneHtml = $lguDocument->saveHTML($pane);
            $this->assertStringContainsString(route('lgu.implan.photos.show', [$this->implan, $ownPhoto]), $paneHtml);

            foreach (['A' => $dilgPhoto, 'B' => $dswdPhoto, 'C' => $dohPhoto] as $otherKey => $otherPhoto) {
                if ($otherKey !== $agencyKey) {
                    $this->assertStringNotContainsString(route('lgu.implan.photos.show', [$this->implan, $otherPhoto]), $paneHtml);
                }
            }
        }
        $dilgPane = $lguXPath->query('//*[@id="agency-implan-pane-'.$this->agencies['A']->id.'"]')->item(0);
        $this->assertNotNull($dilgPane);
        $this->assertSame(2, $lguXPath->query('.//*[@data-agency-mov-gallery]//img[@alt="Mode of verification"]', $dilgPane)->count());
        $this->assertStringContainsString(route('lgu.implan.photos.show', [$this->implan, $dilgSecondPhoto]), $lguDocument->saveHTML($dilgPane));
        $this->actingAs($this->lgu)
            ->get(route('lgu.implan.photos.show', [$this->implan, $dilgPhoto]))->assertOk();

        $katuparan = User::factory()->role('admin')->create();
        $katuparanView = $this->actingAs($katuparan)
            ->get(route('admin.implan.municipality', $this->municipality))
            ->assertOk()
            ->assertSee('Mode of Verification')
            ->assertSee('data-mov-item', false)
            ->assertDontSee('data-agency-photo=', false)
            ->assertDontSee('data-agency-documentation=', false)
            ->assertDontSee('Agenda File');
        $katuparanDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $katuparanDocument->loadHTML($katuparanView->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $katuparanXPath = new DOMXPath($katuparanDocument);
        $this->assertSame(4, $katuparanXPath->query('//*[@data-implan-mov]//*[@data-mov-item]')->count());
        $this->assertStringNotContainsString('DILG', $katuparanXPath->evaluate('string(//*[@data-implan-mov])'));
        $this->assertStringNotContainsString('DSWD', $katuparanXPath->evaluate('string(//*[@data-implan-mov])'));
        $this->assertStringNotContainsString('DOH', $katuparanXPath->evaluate('string(//*[@data-implan-mov])'));

        $this->actingAs($this->agencyUsers['B'])->put(route('gov_agency.implan.update', $this->implan), [
            'agency_implan_response_id' => $this->response('B')->id,
            'action_taken' => 'DSWD action remains isolated',
        ])->assertRedirect();
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('gov_agency.implan.photos.destroy'));

        $denr = GovAgency::create(['name' => 'Department of Environment and Natural Resources', 'acronym' => 'DENR']);
        $denrUser = User::factory()->govAgency($denr->id)->create();
        $this->actingAs($denrUser)->get(route('gov_agency.implan.show', $this->implan))->assertForbidden();
        $this->actingAs($denrUser)
            ->get(route('gov_agency.implan.photos.show', [$this->implan, $dilgPhoto]))
            ->assertForbidden();
    }

    public function test_agenda_file_interface_is_removed_while_mode_of_verification_remains(): void
    {
        $this->assertFalse(Schema::hasTable('implementation_files'));

        $agencyImplanPage = $this->actingAs($this->agencyUsers['A'])
            ->get(route('gov_agency.implan.show', $this->implan))
            ->assertOk()
            ->assertDontSee('Agenda File')
            ->assertDontSee('Add File')
            ->assertDontSee('addFileModal')
            ->assertSee('Mode of Verification')
            ->assertSee('Add MOV')
            ->assertSee('Add Mode of Verification')
            ->assertSee('Select file(s)')
            ->assertSee('Accepted formats: JPG, PNG, GIF')
            ->assertSee('Upload')
            ->assertSee('Cancel')
            ->assertDontSee('Upload mode of verification')
            ->assertDontSee('Upload MOV')
            ->assertDontSee('Documentation')
            ->assertDontSee('Add Photo')
            ->assertDontSee('Upload Photo');
        $this->assertSame(1, substr_count($agencyImplanPage->getContent(), 'Add Mode of Verification'));

        $this->actingAs($this->lgu)
            ->get(route('lgu.implan.show', $this->implan))
            ->assertOk()
            ->assertDontSee('Agenda File')
            ->assertDontSee('Add File')
            ->assertDontSee('addFileModal')
            ->assertSee('Information')
            ->assertSee('Mode of Verification')
            ->assertDontSee('Documentation');

        $katuparan = User::factory()->role('admin')->create();
        $this->actingAs($katuparan)
            ->get(route('admin.implan.show', $this->implan))
            ->assertOk()
            ->assertSee('Mode of Verification')
            ->assertDontSee('Documentation');

        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('gov_agency.implan.agenda'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('gov_agency.implan.files.show'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('lgu.implan.agenda'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('lgu.implan.files.show'));

        $this->actingAs($this->agencyUsers['A'])->post("/agency/implan/{$this->implan->id}/agenda")->assertNotFound();
        $this->actingAs($this->agencyUsers['A'])->get("/agency/implan/{$this->implan->id}/files/1")->assertNotFound();
        $this->actingAs($this->lgu)->post("/lgu/implan/{$this->implan->id}/agenda")->assertNotFound();
        $this->actingAs($this->lgu)->get("/lgu/implan/{$this->implan->id}/files/1")->assertNotFound();
    }

    public function test_lgu_agency_tabs_and_selected_print_are_scoped_without_creating_pending_responses(): void
    {
        AgencyImplanResponse::create([
            'implementation_id' => $this->implan->id,
            'gov_agency_id' => $this->agencies['A']->id,
            'response_status' => 'accepted',
            'action_taken' => 'DILG selected print action',
            'remarks' => 'DILG selected print remarks',
        ]);
        AgencyImplanResponse::create([
            'implementation_id' => $this->implan->id,
            'gov_agency_id' => $this->agencies['B']->id,
            'response_status' => 'accepted',
            'action_taken' => 'DSWD private print action',
            'remarks' => 'DSWD private print remarks',
        ]);

        $responseCount = AgencyImplanResponse::count();
        $implanCount = Implementation::count();
        $page = $this->actingAs($this->lgu)
            ->get(route('lgu.implan.show', $this->implan))
            ->assertOk()
            ->assertSee('View Agency IMPLANs')
            ->assertDontSee('LGU original and responsible-agency responses.');

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($page->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $pendingPane = $xpath->query('//*[@id="agency-implan-pane-'.$this->agencies['C']->id.'"]')->item(0);
        $this->assertNotNull($pendingPane);
        $this->assertSame(2, substr_count($pendingPane->textContent, 'Not yet submitted'));
        $this->assertStringContainsString('No mode of verification available', $pendingPane->textContent);
        $this->assertSame($responseCount, AgencyImplanResponse::count());
        $this->assertSame($implanCount, Implementation::count());

        $printUrl = route('lgu.implan.official', [
            'implan' => $this->implan->id,
            'agency' => $this->agencies['A']->id,
            'print' => 1,
        ]);
        $this->actingAs($this->lgu)
            ->get($printUrl)
            ->assertOk()
            ->assertSee('DILG selected print action')
            ->assertSee('DILG selected print remarks')
            ->assertDontSee('DILG — DILG selected print action')
            ->assertDontSee('DILG (Accepted)')
            ->assertDontSee('DSWD private print action')
            ->assertDontSee('DSWD private print remarks')
            ->assertSee('window.print()', false);

        $unassigned = GovAgency::create([
            'name' => 'Department of Environment and Natural Resources',
            'acronym' => 'DENR',
        ]);
        $this->actingAs($this->lgu)
            ->get(route('lgu.implan.official', [
                'implan' => $this->implan->id,
                'agency' => $unassigned->id,
                'print' => 1,
            ]))
            ->assertForbidden();

        $otherMunicipality = Municipality::create(['name' => 'Matanao']);
        $otherLgu = User::factory()->lgu($otherMunicipality->id)->create();
        $this->actingAs($otherLgu)
            ->get($printUrl)
            ->assertForbidden();
    }

    private function response(string $key): AgencyImplanResponse
    {
        return AgencyImplanResponse::where([
            'implementation_id' => $this->implan->id,
            'gov_agency_id' => $this->agencies[$key]->id,
        ])->firstOrFail();
    }
}
