<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\PswdoEnrollment;
use App\Models\PswdoEnrollmentDocument;
use App\Models\PswdoEnrollmentDocumentDraft;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\PswdoEnrollmentIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\TestCase;

class PswdoFinalDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $pswdo;

    private PswdoEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->pswdo = User::factory()->role('pswdo')->create();
        $this->enrollment = $this->eligibleEnrollment();
    }

    public function test_endorsement_is_locked_and_four_distinct_finals_derive_completion(): void
    {
        $this->upload(PswdoEnrollmentDocumentType::EndorsementLetter, 'early')->assertSessionHasErrors('document');
        $this->assertDatabaseCount('pswdo_enrollment_documents', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');

        foreach (PswdoEnrollmentDocumentType::prerequisites() as $position => $type) {
            $this->upload($type, 'required-'.$position)->assertRedirect(route('pswdo.enrollments.workspace', [
                'pswdoEnrollment' => $this->enrollment,
                'document_type' => $type->value,
            ]));
        }
        $this->assertFalse($this->enrollment->fresh()->isCompleted());
        $this->upload(PswdoEnrollmentDocumentType::EndorsementLetter, 'endorsement')->assertSessionHasNoErrors();
        $fresh = $this->enrollment->fresh('documents');
        $this->assertTrue($fresh->isCompleted());
        $this->assertSame(4, $fresh->completedDocumentCount());
        $this->assertTrue($fresh->documents->every(fn ($document) => str_starts_with($document->getRawOriginal('storage_path'), "pswdo/enrollments/{$fresh->id}/")));
        $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.workspace', $fresh))
            ->assertOk()
            ->assertSee('Enrollment Created')
            ->assertSee('Final File Uploaded')
            ->assertSee('Endorsement Letter')
            ->assertSee($this->pswdo->name)
            ->assertSee('PSWDO')
            ->assertSee('datetime=', false);
        $this->actingAs($this->pswdo)->get(route('pswdo.dashboard'))->assertOk()->assertSee('Completed');
        $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.index'))->assertOk()->assertSee('4 / 4 completed');
    }

    public function test_duplicate_type_cross_type_hash_and_stale_lock_are_rejected_with_file_cleanup(): void
    {
        $this->upload(PswdoEnrollmentDocumentType::EclipEnrollmentForm, 'same')->assertSessionHasNoErrors();
        $count = Storage::disk('local')->allFiles();

        $this->upload(PswdoEnrollmentDocumentType::EclipEnrollmentForm, 'different')->assertSessionHasErrors('document');
        $this->upload(PswdoEnrollmentDocumentType::InitialInterviewForm, 'same')->assertSessionHasErrors('document');
        $this->postUpload(PswdoEnrollmentDocumentType::InitialInterviewForm, 'stale', 0)->assertStatus(409);
        $this->assertCount(count($count), Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('pswdo_enrollment_documents', 1);
    }

    public function test_pdf_validation_confirmations_and_filename_sanitization_are_enforced(): void
    {
        $this->actingAs($this->pswdo)->post(route('pswdo.enrollments.documents.store', [$this->enrollment, PswdoEnrollmentDocumentType::EclipEnrollmentForm->value]), [
            'lock_version' => 0,
            'document' => UploadedFile::fake()->createWithContent('disguised.pdf', 'not a pdf'),
            'correct_document_type_confirmed' => '1', 'belongs_to_fr_confirmed' => '1', 'final_signed_confirmed' => '1',
        ])->assertSessionHasErrors('document');
        $this->actingAs($this->pswdo)->post(route('pswdo.enrollments.documents.store', [$this->enrollment, PswdoEnrollmentDocumentType::EclipEnrollmentForm->value]), [
            'lock_version' => 0, 'document' => $this->pdf('../unsafe name', 'valid'),
        ])->assertSessionHasErrors(['correct_document_type_confirmed', 'belongs_to_fr_confirmed', 'final_signed_confirmed']);
        $this->upload(PswdoEnrollmentDocumentType::EclipEnrollmentForm, 'valid', '../unsafe name')->assertSessionHasNoErrors();
        $document = PswdoEnrollmentDocument::query()->sole();
        $this->assertSame('unsafe-name.pdf', $document->original_filename);
        $this->assertSame(hash('sha256', "%PDF-1.4\nvalid\n%%EOF"), $document->getRawOriginal('sha256'));
    }

    public function test_preview_download_headers_parent_substitution_and_immutability(): void
    {
        $this->upload(PswdoEnrollmentDocumentType::EclipEnrollmentForm, 'secure');
        $document = PswdoEnrollmentDocument::query()->sole();
        foreach (['preview', 'download'] as $action) {
            $response = $this->actingAs($this->pswdo)->get(route("pswdo.enrollments.documents.{$action}", [$this->enrollment, $document]))->assertOk();
            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        }
        $other = $this->eligibleEnrollment();
        $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.documents.preview', [$other, $document]))->assertNotFound();
        $outsider = User::factory()->role('japic')->create();
        $this->actingAs($outsider)->get(route('pswdo.enrollments.documents.preview', [$this->enrollment, $document]))->assertForbidden();

        $this->expectException(LogicException::class);
        $document->update(['original_filename' => 'changed.pdf']);
    }

    public function test_processor_selected_final_stays_in_the_enrollment_workspace(): void
    {
        $payload = $this->eclipPayload();
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.eclip-draft.update', $this->enrollment), [
            'revision' => 0,
            'draft' => $payload,
        ])->assertOk()->assertJsonPath('revision', 1);
        $this->upload(PswdoEnrollmentDocumentType::EclipEnrollmentForm, 'workspace-final');
        $document = PswdoEnrollmentDocument::query()->sole();

        $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.workspace', [$this->enrollment, $document]))
            ->assertOk()
            ->assertSee('Uploaded Final E-CLIP Enrollment Form')
            ->assertSee('View Uploaded File')
            ->assertSee('Completed / Read-only')
            ->assertSee('Preview Draft')
            ->assertSee('Saved Address')
            ->assertSee('fieldset disabled', false)
            ->assertDontSee('Save Draft')
            ->assertDontSee('type="file" name="document"', false)
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History')
            ->assertSee('Enrollment Created')
            ->assertSee('Final File Uploaded')
            ->assertSee($this->pswdo->name)
            ->assertSee('PSWDO')
            ->assertSee('datetime=', false)
            ->assertDontSee('<iframe title="E-CLIP Enrollment Form"', false);
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.eclip-draft.update', $this->enrollment), [
            'revision' => 1,
            'draft' => array_replace($payload, ['address' => 'Forbidden replacement']),
        ])->assertForbidden();
    }

    public function test_eclip_selection_persists_revisioned_encrypted_draft_and_uses_one_official_preview(): void
    {
        $workspaceUrl = route('pswdo.enrollments.workspace', [
            'pswdoEnrollment' => $this->enrollment,
            'document_type' => PswdoEnrollmentDocumentType::EclipEnrollmentForm->value,
        ]);
        $this->actingAs($this->pswdo)->get($workspaceUrl)->assertOk()
            ->assertSee('Upload Final E-CLIP Enrollment Form')
            ->assertSee('All changes saved')
            ->assertSee('Preview Draft')
            ->assertDontSee('Mandatory Verification Checklist')
            ->assertSee('name="correct_document_type_confirmed"', false)
            ->assertSee('name="belongs_to_fr_confirmed"', false)
            ->assertSee('name="final_signed_confirmed"', false)
            ->assertSee('name="draft[last_name]"', false)
            ->assertSee('name="draft[reintegration_monitoring_number]"', false)
            ->assertSee('name="draft[firearm_type]"', false)
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History')
            ->assertSee('Final')
            ->assertSee('value="'.$this->enrollment->surfacedFormerRebel->last_name.'"', false);
        $this->assertDatabaseCount('pswdo_enrollment_document_drafts', 0);

        $payload = $this->eclipPayload();
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.eclip-draft.update', $this->enrollment), [
            'revision' => 0,
            'draft' => $payload,
        ])->assertOk()->assertJsonPath('saved', true)->assertJsonPath('revision', 1);

        $draft = PswdoEnrollmentDocumentDraft::query()->sole();
        $this->assertSame(PswdoEnrollmentDocumentType::EclipEnrollmentForm, $draft->document_type);
        $this->assertSame($payload, $draft->payload);
        $this->assertSame($this->pswdo->id, $draft->saved_by);
        $this->assertNotNull($draft->saved_at);
        $rawPayload = DB::table('pswdo_enrollment_document_drafts')->value('payload');
        $this->assertStringNotContainsString('Saved Address', $rawPayload);

        $this->actingAs($this->pswdo)->get($workspaceUrl)->assertOk()
            ->assertSee('value="Saved Last"', false)
            ->assertSee('value="RM-2026-001"', false)
            ->assertSee('Saved general remarks')
            ->assertSee('value="Rifle"', false);

        $preview = $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.eclip-draft.preview', $this->enrollment))
            ->assertOk()
            ->assertSee('ECLIP ENROLMENT FORM')
            ->assertSee('Saved Last, Saved First Saved Middle')
            ->assertDontSee('Saved Last, Saved First, Saved Middle')
            ->assertSee('Saved Address')
            ->assertSee('RM-2026-001')
            ->assertSee('10/01/2026')
            ->assertSee('Saved CSO')
            ->assertSee('Saved government agency')
            ->assertSee('Saved general remarks')
            ->assertSee('Rifle')
            ->assertSee('5.56')
            ->assertSee('Saved Make')
            ->assertSee('SERIAL-001')
            ->assertSee('Saved firearm remarks')
            ->assertSee('Enhanced Comprehensive Local Integration Program (ECLIP)')
            ->assertSee('Governor')
            ->assertSee('ECLIP Committee Chairperson')
            ->assertSee('AFP Brigade Commander')
            ->assertSee('ECLIP Committee Co-Chairperson')
            ->assertSee('DILG Provincial Director')
            ->assertSee('PNP Provincial Director')
            ->assertSee('LSWDO, Member')
            ->assertSee('CSO, Member')
            ->assertSee('Date of Issuance')
            ->assertSee('Place of Issuance')
            ->assertSee('>Print</button>', false)
            ->assertSee('>Download</a>', false);
        $this->assertStringNotContainsString('SHIELD', $preview->getContent());
        $download = $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.eclip-draft.download', $this->enrollment))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page(?!s)/', $download->getContent()));

        $updated = array_replace($payload, ['middle_name' => '', 'remarks' => 'Newer saved remarks']);
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.eclip-draft.update', $this->enrollment), [
            'revision' => 1,
            'draft' => $updated,
        ])->assertOk()->assertJsonPath('revision', 2);
        $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.eclip-draft.preview', $this->enrollment))
            ->assertOk()
            ->assertSee('Saved Last, Saved First')
            ->assertDontSee('Saved Last, Saved First,');
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.eclip-draft.update', $this->enrollment), [
            'revision' => 1,
            'draft' => array_replace($payload, ['remarks' => 'Stale overwrite']),
        ])->assertStatus(409);
        $this->assertSame('Newer saved remarks', PswdoEnrollmentDocumentDraft::query()->sole()->payload['remarks']);

        $otherTypeUrl = route('pswdo.enrollments.workspace', [
            'pswdoEnrollment' => $this->enrollment,
            'document_type' => PswdoEnrollmentDocumentType::InitialInterviewForm->value,
        ]);
        $this->actingAs($this->pswdo)->get($otherTypeUrl)->assertOk()
            ->assertSee('Upload Final Initial Interview Form')
            ->assertSee('E-CLIP Enrollment Form')
            ->assertSee('Initial Interview Form drafting area', false)
            ->assertSee('id="pswdoIifDraftForm"', false)
            ->assertDontSee('id="pswdoEclipDraftForm"', false);
    }

    public function test_initial_interview_uses_an_isolated_revisioned_five_page_draft_and_locks_after_final_upload(): void
    {
        $workspaceUrl = route('pswdo.enrollments.workspace', [
            'pswdoEnrollment' => $this->enrollment,
            'document_type' => PswdoEnrollmentDocumentType::InitialInterviewForm->value,
        ]);
        $this->actingAs($this->pswdo)->get($workspaceUrl)->assertOk()
            ->assertSee('Upload Final Initial Interview Form')
            ->assertSee('Initial Interview Form drafting area', false)
            ->assertSee('All changes saved')
            ->assertSee('Preview Draft')
            ->assertDontSee('Mandatory Verification Checklist')
            ->assertSee('name="correct_document_type_confirmed"', false)
            ->assertSee('name="belongs_to_fr_confirmed"', false)
            ->assertSee('name="final_signed_confirmed"', false)
            ->assertSee('name="draft[interviewer_name]"', false)
            ->assertSee('name="draft[current_separation_reasons][]"', false)
            ->assertSee('name="draft[difficulty_sleeping]"', false)
            ->assertSee('class="btn btn-outline-primary btn-sm disabled"', false)
            ->assertSee("form.addEventListener('submit', async event =>", false)
            ->assertSee('event.preventDefault();', false)
            ->assertSee('revision.value = String(data.revision);', false)
            ->assertSee('if (activeSave)', false)
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History')
            ->assertSee('value="'.$this->enrollment->surfacedFormerRebel->last_name.'"', false);
        $this->assertDatabaseCount('pswdo_enrollment_document_drafts', 0);

        $iifPayload = $this->initialInterviewPayload();
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.initial-interview-draft.update', $this->enrollment), [
            'revision' => 0,
            'draft' => $iifPayload,
        ])->assertOk()->assertJsonPath('saved', true)->assertJsonPath('revision', 1);
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.eclip-draft.update', $this->enrollment), [
            'revision' => 0,
            'draft' => $this->eclipPayload(),
        ])->assertOk()->assertJsonPath('revision', 1);

        $drafts = PswdoEnrollmentDocumentDraft::query()->orderBy('document_type')->get()->keyBy(fn ($draft) => $draft->document_type->value);
        $this->assertCount(2, $drafts);
        $this->assertSame($iifPayload, $drafts[PswdoEnrollmentDocumentType::InitialInterviewForm->value]->payload);
        $this->assertSame($this->eclipPayload(), $drafts[PswdoEnrollmentDocumentType::EclipEnrollmentForm->value]->payload);
        $this->assertSame(1, $drafts[PswdoEnrollmentDocumentType::InitialInterviewForm->value]->revision);
        $this->assertSame($this->pswdo->id, $drafts[PswdoEnrollmentDocumentType::InitialInterviewForm->value]->saved_by);
        $this->assertNotNull($drafts[PswdoEnrollmentDocumentType::InitialInterviewForm->value]->saved_at);
        $this->assertStringNotContainsString('Saved Interviewer', DB::table('pswdo_enrollment_document_drafts')
            ->where('document_type', PswdoEnrollmentDocumentType::InitialInterviewForm->value)->value('payload'));

        $savedWorkspace = $this->actingAs($this->pswdo)->get($workspaceUrl)->assertOk()
            ->assertSee('value="Saved Interviewer"', false)
            ->assertSee('Saved reasons for leaving')
            ->assertSee('Saved emergency address')
            ->assertSee('value="often" checked', false);
        $this->assertStringNotContainsString('class="btn btn-outline-primary btn-sm disabled"', $savedWorkspace->getContent());

        $preview = $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.initial-interview-draft.preview', $this->enrollment))
            ->assertOk()
            ->assertSee('INITIAL INTERVIEW FORM (35 minutes)')
            ->assertSee('(May be conducted together with Needs Assessment tool)')
            ->assertSee('Name of Interviewer')
            ->assertSee('Saved Interviewer')
            ->assertSee('Signature over Printed Name')
            ->assertSee('Name of Encoder')
            ->assertSee('PART I: PROFILE OF RESPONDENT (3 minutes)')
            ->assertSee('PART II: HISTORY IN THE ARMED MOVEMENT (12 minutes)')
            ->assertSee('PART III: SECURITY ASSESSMENT (10 minutes)')
            ->assertSee('PART IV: IMMEDIATE NEEDS ASSESSMENT (10 minutes)')
            ->assertSee('Saved reasons for joining')
            ->assertSee('Saved emergency contact')
            ->assertSee('Visual Impairment')
            ->assertSee('Medical Care')
            ->assertSee('Difficulty Sleeping / Bad dreams')
            ->assertSee('Never')
            ->assertSee('Rarely')
            ->assertSee('Sometimes')
            ->assertSee('Often')
            ->assertSee('Always')
            ->assertSee('Saved avoidance person')
            ->assertSee('I, the undersigned, have verified and confirmed the contents of this initial interview.')
            ->assertSee('>Print</button>', false)
            ->assertSee('>Download</a>', false);
        $this->assertStringNotContainsString('SHIELD', $preview->getContent());

        $download = $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.initial-interview-draft.download', $this->enrollment))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(5, preg_match_all('/\/Type\s*\/Page(?!s)/', $download->getContent()));

        $updated = array_replace($iifPayload, [
            'interviewer_name' => 'Latest Version',
            'reasons_leaving' => 'Updated reasons after interview',
        ]);
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.initial-interview-draft.update', $this->enrollment), [
            'revision' => 1,
            'draft' => $updated,
        ])->assertOk()->assertJsonPath('revision', 2);
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.initial-interview-draft.update', $this->enrollment), [
            'revision' => 1,
            'draft' => array_replace($iifPayload, ['reasons_leaving' => 'Stale overwrite']),
        ])->assertStatus(409);
        $this->assertSame('Updated reasons after interview', PswdoEnrollmentDocumentDraft::query()
            ->where('document_type', PswdoEnrollmentDocumentType::InitialInterviewForm)->sole()->payload['reasons_leaving']);
        $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.initial-interview-draft.preview', $this->enrollment))
            ->assertOk()
            ->assertSee('Latest Version')
            ->assertDontSee('Saved Interviewer');

        $this->upload(PswdoEnrollmentDocumentType::InitialInterviewForm, 'initial-interview-final')->assertSessionHasNoErrors();
        $this->actingAs($this->pswdo)->get($workspaceUrl)->assertOk()
            ->assertSee('Uploaded Final Initial Interview Form')
            ->assertSee('View Uploaded File')
            ->assertSee('Completed / Read-only')
            ->assertSee('Preview Draft')
            ->assertSee('Updated reasons after interview')
            ->assertSee('fieldset disabled', false)
            ->assertDontSee('type="file" name="document"', false)
            ->assertDontSee('Save Draft');
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.initial-interview-draft.update', $this->enrollment), [
            'revision' => 2,
            'draft' => array_replace($updated, ['reasons_leaving' => 'Forbidden replacement']),
        ])->assertForbidden();
    }

    private function upload(PswdoEnrollmentDocumentType $type, string $contents, string $name = 'final'): TestResponse
    {
        return $this->postUpload($type, $contents, $this->enrollment->fresh()->lock_version, $name);
    }

    private function postUpload(PswdoEnrollmentDocumentType $type, string $contents, int $lock, string $name = 'final'): TestResponse
    {
        return $this->actingAs($this->pswdo)->post(route('pswdo.enrollments.documents.store', [$this->enrollment, $type->value]), [
            'lock_version' => $lock, 'document' => $this->pdf($name, $contents),
            'correct_document_type_confirmed' => '1', 'belongs_to_fr_confirmed' => '1', 'final_signed_confirmed' => '1',
        ]);
    }

    private function pdf(string $name, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name.'.pdf', "%PDF-1.4\n{$contents}\n%%EOF");
    }

    private function eclipPayload(): array
    {
        return [
            'last_name' => 'Saved Last',
            'first_name' => 'Saved First',
            'middle_name' => 'Saved Middle',
            'address' => 'Saved Address',
            'reintegration_monitoring_number' => 'RM-2026-001',
            'japic_validation_date' => '2026-10-01',
            'civil_society_organization' => 'Saved CSO',
            'other_government_agency' => 'Saved government agency',
            'remarks' => 'Saved general remarks',
            'firearm_type' => 'Rifle',
            'caliber' => '5.56',
            'make' => 'Saved Make',
            'serial_number' => 'SERIAL-001',
            'firearm_remarks' => 'Saved firearm remarks',
            'date_of_issuance' => '2026-10-02',
            'place_of_issuance' => 'Saved Province',
        ];
    }

    private function initialInterviewPayload(): array
    {
        return [
            'as_of_date' => '2026-10-01',
            'interviewer_name' => 'Saved Interviewer',
            'interviewer_office_designation' => 'Saved Interview Office',
            'interview_date' => '2026-10-01',
            'submission_date' => '2026-10-02',
            'conducting_entity' => 'Saved Conducting Office',
            'encoder_name' => 'Saved Encoder',
            'encoder_office_designation' => 'Saved Encoder Office',
            'date_encoded' => '2026-10-02',
            'last_name' => 'Saved Last',
            'first_name' => 'Saved First',
            'middle_name' => 'Saved Middle',
            'alias' => 'Saved Alias',
            'sex' => 'male',
            'birthdate' => '1990-05-06',
            'birthplace' => 'Saved Birthplace',
            'civil_status' => 'others',
            'civil_status_other' => 'Saved civil status',
            'tribal_group' => 'yes',
            'tribal_group_name' => 'Saved tribal group',
            'religion' => 'Saved Religion',
            'movement_years' => 8,
            'entry_age' => 19,
            'reasons_joining' => 'Saved reasons for joining',
            'reasons_staying' => 'Saved reasons for staying',
            'position_before_leaving' => 'Saved position',
            'unit_before_leaving' => 'Saved unit',
            'areas_of_operation' => 'Saved areas of operation',
            'unfair_treatment' => 'yes',
            'unfair_treatment_details' => 'Saved treatment details',
            'reasons_leaving' => 'Saved reasons for leaving',
            'firearms_had' => 'yes',
            'firearms_brought' => 'yes',
            'firearms_turned_in' => 'no',
            'firearms_turned_in_to' => null,
            'firearms_not_turned_in_reason' => 'Saved firearm reason',
            'explosives_had' => 'yes',
            'explosives_brought' => 'no',
            'explosives_turned_in' => 'no',
            'explosives_turned_in_to' => null,
            'explosives_not_turned_in_reason' => 'Saved explosive reason',
            'current_street' => 'Saved current street',
            'current_sitio' => 'Saved current sitio',
            'current_barangay' => 'Saved current barangay',
            'current_municipality_city' => 'Saved municipality',
            'current_province' => 'Saved province',
            'current_psgc_barangay_code' => '0123456789',
            'stay_years' => 2,
            'stay_months' => 5,
            'family_street' => 'Saved family street',
            'family_sitio' => 'Saved family sitio',
            'family_barangay' => 'Saved family barangay',
            'family_municipality_city' => 'Saved family municipality',
            'family_province' => 'Saved family province',
            'family_contact_information' => 'Saved family contact',
            'emergency_contact_name' => 'Saved emergency contact',
            'emergency_contact_relationship' => 'Saved relationship',
            'emergency_contact_information' => 'Saved emergency number',
            'emergency_contact_address' => 'Saved emergency address',
            'current_separation_reasons' => ['unsafe_living_there', 'others'],
            'current_separation_other' => 'Saved separation reason',
            'relocate_plans' => 'yes',
            'relocate_same_municipality' => 'no',
            'relocate_where' => 'Saved relocation place',
            'relocation_reasons' => ['lack_security', 'hazardous_location', 'others'],
            'relocation_other' => 'Saved relocation reason',
            'respondent_safety' => 'threat_avoid_areas',
            'respondent_threat_sources' => ['former_comrades', 'neighbors', 'others'],
            'respondent_threat_other' => 'Saved respondent threat',
            'family_safety' => 'little_threat',
            'family_threat_sources' => ['mass_base_members', 'adjacent_communities', 'others'],
            'family_threat_other' => 'Saved family threat',
            'visual_impairment' => 'yes',
            'visual_impairment_details' => 'Saved visual details',
            'hearing_impairment' => 'no',
            'hearing_impairment_details' => null,
            'speech_impairment' => 'no',
            'speech_impairment_details' => null,
            'physical_disabilities' => 'yes',
            'physical_disabilities_details' => 'Saved physical details',
            'other_disabilities' => 'yes',
            'other_disabilities_details' => 'Saved other disability',
            'medical_received' => 'yes',
            'medical_times' => 2,
            'medical_conditions' => 'Saved medical condition',
            'board_lodging_received' => 'yes',
            'board_lodging_sources' => ['lgu', 'others'],
            'board_lodging_other' => 'Saved lodging source',
            'food_received' => 'yes',
            'food_sources' => ['afp_pnp'],
            'food_other' => null,
            'transport_received' => 'yes',
            'transport_sources' => ['lgu'],
            'transport_other' => null,
            'psychosocial_received' => 'yes',
            'psychosocial_sources' => ['others'],
            'psychosocial_other' => 'Saved psychosocial source',
            'assistance_other' => 'Saved other assistance',
            'difficulty_sleeping' => 'often',
            'anxiety' => 'sometimes',
            'addictive_substances' => 'rarely',
            'difficulty_concentrating' => 'often',
            'disengaged_environment' => 'never',
            'panic_attacks' => 'rarely',
            'avoidance_people_places' => 'sometimes',
            'avoidance_who' => 'Saved avoidance person',
            'trusting_others' => 'often',
            'trusting_who' => 'Saved trusted person',
            'remembering_violent_incidents' => 'always',
            'violent_thoughts' => 'rarely',
            'irritability_anger' => 'sometimes',
            'feelings_guilt' => 'often',
        ];
    }

    private function eligibleEnrollment(): PswdoEnrollment
    {
        $ib39 = User::factory()->role('39th_ib')->create();
        $japic = User::factory()->role('japic')->create();
        $municipality = Municipality::query()->create(['name' => 'Document '.uniqid()]);
        $record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Final', 'last_name' => uniqid(), 'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id, 'surfaced_at' => '2026-09-01', 'possessed_firearms' => true,
        ], $ib39)->load('cdrProcessing');
        $cdr = $record->cdrProcessing;
        $cdrVersion = $cdr->finalDocument()->create(['source_type' => 'uploaded', 'storage_path' => "ib39/cdr/{$cdr->id}/final.pdf", 'original_filename' => 'cdr.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => hash('sha256', uniqid()), 'created_by' => $ib39->id, 'finalized_at' => now()]);
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $ib39->id]);
        $processing = JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id, 'triggering_cdr_final_document_id' => $cdrVersion->id, 'status' => 'Completed', 'received_at' => now(), 'due_at' => now()->addDays(14), 'completed_at' => now(), 'completed_by' => $japic->id, 'lock_version' => 1]);
        $japicVersion = $processing->documentVersions()->create(['version_number' => 1, 'storage_path' => "japic/certifications/{$processing->id}/final-documents/final.pdf", 'original_filename' => 'japic.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => hash('sha256', uniqid()), 'uploaded_by' => $japic->id, 'all_signatories_confirmed' => true, 'correct_final_confirmed' => true, 'uploaded_at' => now()]);
        $processing->forceFill(['current_final_version_id' => $japicVersion->id])->save();

        return DB::transaction(fn () => app(PswdoEnrollmentIntakeService::class)->receiveEligible($record->id));
    }
}
