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
use App\Support\OfficialDocumentPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $blankWorkspace = $this->actingAs($this->pswdo)->get($workspaceUrl)->assertOk()
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

    public function test_profiling_interview_uses_an_encrypted_revisioned_adaptive_draft_and_locks_after_final_upload(): void
    {
        $workspaceUrl = route('pswdo.enrollments.workspace', [
            'pswdoEnrollment' => $this->enrollment,
            'document_type' => PswdoEnrollmentDocumentType::ProfilingInterviewForm->value,
        ]);
        $blankWorkspace = $this->actingAs($this->pswdo)->get($workspaceUrl);
        $blankWorkspace->assertOk()
            ->assertSee('Upload Final Profiling Interview Form')
            ->assertSee('Profiling Interview Form drafting area', false)
            ->assertSee('id="pswdoPifDraftForm"', false)
            ->assertSee('name="draft[children][0][first_name]"', false)
            ->assertDontSee('name="draft[children][1][first_name]"', false)
            ->assertSee('name="draft[other_family_members][0][income_sources]"', false)
            ->assertSee('name="draft[other_family_members][0][relation]"', false)
            ->assertSee('name="draft[partner_income_sources]"', false)
            ->assertDontSee('name="draft[partner_income_sources][]"', false)
            ->assertSee('data-pif-add-row="children"', false)
            ->assertSee('data-pif-add-row="other_family_members"', false)
            ->assertSee('data-pif-remove-row', false)
            ->assertSee('Add Child')
            ->assertSee('Add Family Member')
            ->assertSee('function wirePifDynamicRows()', false)
            ->assertSee('row.remove();', false)
            ->assertSee('reindex(table);', false)
            ->assertSee('5. Does your family have problems with:')
            ->assertSee('EyeSight')
            ->assertSee('Hearing')
            ->assertSee('Source/s of Income Codes:')
            ->assertSee('<span class="pswdo-pif-code">1</span> - Stay-home/Housewife Industry', false)
            ->assertSee('<span class="pswdo-pif-code">8</span> - Others', false)
            ->assertSee('<span class="pswdo-pif-code">1</span> - Grand Parents (including in-laws)', false)
            ->assertSee('<span class="pswdo-pif-code">7</span> - Others', false)
            ->assertSee('Stay-home/Housewife Industry')
            ->assertSee('NCWDP')
            ->assertDontSee('Your participation in this interview is voluntary.')
            ->assertDontSee('The signature over complete name and date remain empty for physical signing.')
            ->assertDontSee('Page 1')
            ->assertSee('9. To your knowledge, do you have any legal cases while you were still in the movement?')
            ->assertSee('13b. What technical skills would you and your family members want to learn?')
            ->assertSee('14. What kind of ownership do you hold?')
            ->assertSee('15. Were you a member of any community based organization prior to joining the movement?')
            ->assertSee('16. Are you currently a member of any community based organization?')
            ->assertSee('name="draft[hearing_problem]"', false)
            ->assertSee('name="draft[speech_problem]"', false)
            ->assertSee('name="draft[current_skills][]"', false)
            ->assertSee('name="draft[agricultural_land_tenures][]"', false)
            ->assertSee('class="pswdo-pif-editor"', false)
            ->assertSee('grid-template-columns: 1.05rem minmax(0, 1fr);', false)
            ->assertSee('class="pswdo-pif-question-card"', false)
            ->assertSee('class="pswdo-pif-answer-row"', false)
            ->assertSee('class="pswdo-pif-choice-panel', false)
            ->assertSee('data-pif-family-legends', false)
            ->assertSeeInOrder([
                'data-pif-dynamic-table="other_family_members"',
                'data-pif-family-legends',
                'Source/s of Income Codes:',
                'Relationship Codes for Other Members of the Family:',
            ], false)
            ->assertSee('<label class="pswdo-iif-option"><input type="radio"', false)
            ->assertSee('<label class="pswdo-iif-option"><input type="checkbox"', false)
            ->assertSee('Preview Draft')
            ->assertDontSee('Mandatory Verification Checklist')
            ->assertSee('value="'.$this->enrollment->surfacedFormerRebel->last_name.'"', false);
        $this->assertSame(1, substr_count($blankWorkspace->getContent(), 'Source/s of Income Codes:'));
        $this->assertSame(1, substr_count($blankWorkspace->getContent(), 'Relationship Codes for Other Members of the Family:'));
        $legendStart = strpos($blankWorkspace->getContent(), 'data-pif-family-legends');
        $legendEnd = strpos($blankWorkspace->getContent(), 'pswdo-eclip-grid mt-3', $legendStart);
        $this->assertIsInt($legendStart);
        $this->assertIsInt($legendEnd);
        $legendMarkup = substr($blankWorkspace->getContent(), $legendStart, $legendEnd - $legendStart);
        $this->assertStringNotContainsString('<input', $legendMarkup);
        $this->assertStringNotContainsString('<select', $legendMarkup);
        $this->assertDatabaseCount('pswdo_enrollment_document_drafts', 0);

        $payload = $this->profilingInterviewPayload();
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.profiling-interview-draft.update', $this->enrollment), [
            'revision' => 0,
            'draft' => array_replace($payload, [
                'interviewer_name' => str_repeat('x', 501),
                'partner_sex' => 'unsupported',
                'partner_income_sources' => '1, invalid',
            ]),
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'draft.interviewer_name',
            'draft.partner_sex',
            'draft.partner_income_sources',
        ]);
        $this->assertDatabaseCount('pswdo_enrollment_document_drafts', 0);

        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.profiling-interview-draft.update', $this->enrollment), [
            'revision' => 0,
            'draft' => $payload,
        ])->assertOk()->assertJsonPath('saved', true)->assertJsonPath('revision', 1);

        $draft = PswdoEnrollmentDocumentDraft::query()->sole();
        $this->assertSame(PswdoEnrollmentDocumentType::ProfilingInterviewForm, $draft->document_type);
        $this->assertSame('Saved Partner', $draft->payload['partner_first_name']);
        $this->assertSame('2/7', $draft->payload['partner_income_sources']);
        $this->assertSame('1, 4', $draft->payload['children'][0]['income_sources']);
        $this->assertSame('3', $draft->payload['other_family_members'][0]['income_sources']);
        $this->assertSame('5', $draft->payload['other_family_members'][0]['relation']);
        $this->assertStringNotContainsString('Saved Partner', DB::table('pswdo_enrollment_document_drafts')->value('payload'));

        $savedWorkspace = $this->actingAs($this->pswdo)->get($workspaceUrl)->assertOk()
            ->assertSee('value="Saved Interviewer"', false)
            ->assertSee('value="Saved Partner"', false)
            ->assertSee('value="agricultural_crops" checked', false);
        $this->assertStringNotContainsString('class="btn btn-outline-primary btn-sm disabled"', $savedWorkspace->getContent());

        $preview = $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.profiling-interview-draft.preview', $this->enrollment))
            ->assertOk()
            ->assertSee('Profiling Interview Form')
            ->assertSee('Saved Interviewer')
            ->assertSee('Saved Partner')
            ->assertSee('Saved Child')
            ->assertSee('Saved Family')
            ->assertSee('Saved Program Person')
            ->assertSee('Saved Income Other')
            ->assertSee('Saved Agriculture Skill')
            ->assertSee('Saved Agricultural Tenure')
            ->assertSee('Saved Cooperative')
            ->assertSee('This profiling interview is being conducted by the')
            ->assertSee('Your participation in this interview is voluntary.')
            ->assertSee('I, the undersigned, have been briefed properly')
            ->assertSee('Saved Conducting Office')
            ->assertSee('2/7')
            ->assertSee('1, 4')
            ->assertSee('Relationship Codes for Other Members of the Family:')
            ->assertSee('data-pif-adaptive-document', false)
            ->assertSee('data-pif-server-pages', false)
            ->assertSee('class="pif-page" data-pif-page="1"', false)
            ->assertSee('class="pif-page part-one-section" data-pif-page="2"', false)
            ->assertSee('width: 210mm;', false)
            ->assertSee('height: 297mm;', false)
            ->assertSee('padding: 25.4mm;', false)
            ->assertSee('.document-pages { font-size: 11pt; line-height: 1.5; }', false)
            ->assertSee('data-pif-part-one-page', false)
            ->assertSee('data-pif-section="organizations"', false)
            ->assertSee('@page { size: A4 portrait; margin: 25.4mm; }', false)
            ->assertSee('display: table-header-group', false)
            ->assertSee('page-break-inside: avoid;', false)
            ->assertDontSee('overflow: hidden', false)
            ->assertDontSee('data-pif-preview-pages', false)
            ->assertDontSee('pif:paginated', false)
            ->assertSeeInOrder([
                'data-pif-page="1" data-pif-section="administrative"',
                'data-pif-page="2" data-pif-section="dependents"',
                'data-pif-part-one-page',
            ], false)
            ->assertSee('>Print</button>', false)
            ->assertSee('>Download</a>', false);
        $style = Str::between($preview->getContent(), '<style>', '</style>');
        $this->assertSame(0, preg_match('/font-size:\s*(?:[0-9]|10(?:\.\d+)?)pt/', $style));
        $this->assertSame(0, preg_match('/line-height:\s*(?:0(?:\.\d+)?|1(?:\.[0-4](?:\d+)?)?)(?:;|\s)/', $style));
        $this->assertSame(1, substr_count($preview->getContent(), 'Saved Interviewer'));
        $this->assertSame(1, substr_count($preview->getContent(), 'Child Middle'));
        $this->assertSame(1, substr_count($preview->getContent(), 'Family Middle'));
        foreach (range(1, 6) as $page) {
            $preview->assertDontSee("assets/forms/PIF{$page}.png", false);
        }

        $download = $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.profiling-interview-draft.download', $this->enrollment))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $previewPageCount = substr_count($preview->getContent(), 'class="pif-page');
        $pdfPageCount = preg_match_all('/\/Type\s*\/Page(?!s)/', $download->getContent());
        $this->assertGreaterThan(0, $pdfPageCount);
        $this->assertSame($previewPageCount, $pdfPageCount);
        $this->assertLessThan(21, $previewPageCount);
        $this->assertSame(1, substr_count($preview->getContent(), 'data-pif-dependent-page'));
        $preview->assertSee('data-pif-record-count="2"', false);

        $updated = array_replace($payload, ['interviewer_name' => 'Latest PIF Interviewer']);
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.profiling-interview-draft.update', $this->enrollment), [
            'revision' => 1,
            'draft' => $updated,
        ])->assertOk()->assertJsonPath('revision', 2);
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.profiling-interview-draft.update', $this->enrollment), [
            'revision' => 1,
            'draft' => array_replace($payload, ['interviewer_name' => 'Stale PIF Interviewer']),
        ])->assertStatus(409);
        $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.profiling-interview-draft.preview', $this->enrollment))
            ->assertOk()->assertSee('Latest PIF Interviewer')->assertDontSee('Saved Interviewer');

        $this->upload(PswdoEnrollmentDocumentType::ProfilingInterviewForm, 'profiling-interview-final')->assertSessionHasNoErrors();
        $this->actingAs($this->pswdo)->get($workspaceUrl)->assertOk()
            ->assertSee('Uploaded Final Profiling Interview Form')
            ->assertSee('View Uploaded File')
            ->assertSee('Completed / Read-only')
            ->assertSee('Preview Draft')
            ->assertSee('fieldset disabled', false)
            ->assertDontSee('Save Draft');
        $this->actingAs($this->pswdo)->putJson(route('pswdo.enrollments.profiling-interview-draft.update', $this->enrollment), [
            'revision' => 2,
            'draft' => $updated,
        ])->assertForbidden();
    }

    public function test_profiling_interview_dynamic_family_rows_are_normalized_and_flow_without_truncation(): void
    {
        $saveUrl = route('pswdo.enrollments.profiling-interview-draft.update', $this->enrollment);
        $previewUrl = route('pswdo.enrollments.profiling-interview-draft.preview', $this->enrollment);
        $downloadUrl = route('pswdo.enrollments.profiling-interview-draft.download', $this->enrollment);
        $base = $this->profilingInterviewPayload();

        $this->actingAs($this->pswdo)->putJson($saveUrl, [
            'revision' => 0,
            'draft' => [
                'children' => [['first_name' => '  ', 'income_sources' => '']],
                'other_family_members' => [[]],
            ],
        ])->assertOk()->assertJsonPath('revision', 1);
        $draft = PswdoEnrollmentDocumentDraft::query()->sole();
        $this->assertSame([], $draft->payload['children']);
        $this->assertSame([], $draft->payload['other_family_members']);
        $otherEnrollment = $this->eligibleEnrollment();
        $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.profiling-interview-draft.preview', $otherEnrollment))->assertNotFound();
        $this->actingAs($this->pswdo)->get(route('pswdo.enrollments.profiling-interview-draft.download', $otherEnrollment))->assertNotFound();
        $emptyPreview = $this->actingAs($this->pswdo)->get($previewUrl)->assertOk()
            ->assertSee('data-pif-empty-child-row', false)
            ->assertSee('data-pif-empty-family-row', false)
            ->assertDontSee('data-pif-continuation-page=', false)
            ->assertSee('class="pif-page', false);
        $this->assertSame(0, substr_count($emptyPreview->getContent(), 'class="box">X</span>'));
        $emptyPreviewPageCount = substr_count($emptyPreview->getContent(), 'class="pif-page');
        $this->assertLessThan(21, $emptyPreviewPageCount);
        $this->assertSame(1, substr_count($emptyPreview->getContent(), 'data-pif-dependent-page'));
        $emptyPdf = $this->actingAs($this->pswdo)->get($downloadUrl)->assertOk();
        $this->assertSame($emptyPreviewPageCount, preg_match_all('/\/Type\s*\/Page(?!s)/', $emptyPdf->getContent()));

        $this->actingAs($this->pswdo)->putJson($saveUrl, [
            'revision' => 1,
            'draft' => array_replace($base, [
                'children' => [2 => ['first_name' => 'Partially Completed Child'], 7 => []],
                'other_family_members' => [[]],
            ]),
        ])->assertOk()->assertJsonPath('revision', 2);
        $draft->refresh();
        $this->assertCount(1, $draft->payload['children']);
        $this->assertSame('Partially Completed Child', $draft->payload['children'][0]['first_name']);

        $this->actingAs($this->pswdo)->putJson($saveUrl, [
            'revision' => 2,
            'draft' => array_replace($base, [
                'children' => $this->profilingInterviewRows('Boundary Child', 5),
                'other_family_members' => $this->profilingInterviewRows('Boundary Family', 5, true),
            ]),
        ])->assertOk()->assertJsonPath('revision', 3);
        $boundaryPreview = $this->actingAs($this->pswdo)->get($previewUrl)->assertOk()
            ->assertSee('Boundary Child 5')
            ->assertSee('Boundary Family 5')
            ->assertSee('data-pif-child-row="5"', false)
            ->assertSee('data-pif-family-row="5"', false)
            ->assertDontSee('data-pif-continuation-page=', false);
        $boundaryPreviewPageCount = substr_count($boundaryPreview->getContent(), 'class="pif-page');
        $this->assertLessThan(21, $boundaryPreviewPageCount);
        $this->assertSame(4, substr_count($boundaryPreview->getContent(), 'data-pif-dependent-page'));
        $this->assertSame(2, substr_count($boundaryPreview->getContent(), 'data-pif-record-count="3"'));
        $boundaryPdf = $this->actingAs($this->pswdo)->get($downloadUrl)->assertOk();
        $this->assertSame($boundaryPreviewPageCount, preg_match_all('/\/Type\s*\/Page(?!s)/', $boundaryPdf->getContent()));

        $this->actingAs($this->pswdo)->putJson($saveUrl, [
            'revision' => 3,
            'draft' => array_replace($base, [
                'children' => $this->profilingInterviewRows('Overflow Child', 6),
                'other_family_members' => $this->profilingInterviewRows('Boundary Family', 5, true),
            ]),
        ])->assertOk()->assertJsonPath('revision', 4);
        $sixRowPreview = $this->actingAs($this->pswdo)->get($previewUrl)->assertOk()
            ->assertSee('data-pif-child-row="6"', false)
            ->assertSee('Overflow Child 6')
            ->assertDontSee('data-pif-continuation-page=', false)
            ->assertSeeInOrder(['data-pif-section="dependents"', 'Overflow Child 6', 'data-pif-section="family_health"'], false);

        $children = $this->profilingInterviewRows('Many Child', 16);
        $family = $this->profilingInterviewRows('Many Family', 16, true);
        $children[] = ['first_name' => '', 'income_sources' => ''];
        $family[] = [];
        $this->actingAs($this->pswdo)->putJson($saveUrl, [
            'revision' => 4,
            'draft' => array_replace($base, ['children' => $children, 'other_family_members' => $family]),
        ])->assertOk()->assertJsonPath('revision', 5);

        $draft->refresh();
        $this->assertCount(16, $draft->payload['children']);
        $this->assertCount(16, $draft->payload['other_family_members']);
        $this->assertSame('Many Child 16', $draft->payload['children'][15]['first_name']);
        $this->assertSame('Many Family 16', $draft->payload['other_family_members'][15]['first_name']);
        $this->assertStringNotContainsString('Many Child 16', DB::table('pswdo_enrollment_document_drafts')->value('payload'));

        $overflowPreview = $this->actingAs($this->pswdo)->get($previewUrl)->assertOk()
            ->assertSee('data-pif-child-row="16"', false)
            ->assertSee('data-pif-family-row="16"', false)
            ->assertSee('Many Child 16')
            ->assertSee('Many Family 16')
            ->assertSee('Source/s of Income Codes:')
            ->assertSee('Relationship Codes for Other Members of the Family:')
            ->assertSee('<thead>', false)
            ->assertSee('display: table-header-group', false)
            ->assertDontSee('data-pif-continuation-page=', false)
            ->assertDontSee('{&quot;children&quot;', false)
            ->assertSeeInOrder(['data-pif-section="dependents"', 'Many Child 16', 'Many Family 16', 'data-pif-section="family_health"'], false);
        $this->assertSame(10, substr_count($overflowPreview->getContent(), 'data-pif-dependent-page'));
        $this->assertSame(3, substr_count($overflowPreview->getContent(), 'data-pif-record-count="4"'));
        $this->assertSame(6, substr_count($overflowPreview->getContent(), 'data-pif-record-count="3"'));
        $this->assertLessThan(16, substr_count($overflowPreview->getContent(), 'Relationship Codes for Other Members of the Family:'));

        $download = $this->actingAs($this->pswdo)->get($downloadUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $overflowPageCount = preg_match_all('/\/Type\s*\/Page(?!s)/', $download->getContent());
        $this->assertGreaterThan(0, $overflowPageCount);
        $overflowPreviewPageCount = substr_count($overflowPreview->getContent(), 'class="pif-page');
        $this->assertSame($overflowPreviewPageCount, $overflowPageCount);

        $renderer = \Mockery::mock(OfficialDocumentPdf::class);
        $renderer->shouldReceive('render')->once()->with(\Mockery::on(fn (string $html): bool => str_contains($html, 'Many Child 16')
            && str_contains($html, 'Many Family 16')
            && str_contains($html, 'data-pif-child-row="16"')
            && str_contains($html, 'data-pif-family-row="16"')
            && str_contains($html, '@page { size: A4 portrait; margin: 25.4mm; }')
            && ! str_contains($html, 'data-pif-continuation-page=')))->andReturn("%PDF-1.4\nsynthetic\n%%EOF");
        $this->app->instance(OfficialDocumentPdf::class, $renderer);
        $this->actingAs($this->pswdo)->get($downloadUrl)->assertOk();
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

    private function profilingInterviewPayload(): array
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
            'respondent_last_name' => 'Saved Last',
            'respondent_first_name' => 'Saved First',
            'respondent_middle_name' => 'Saved Middle',
            'partner_first_name' => 'Saved Partner',
            'partner_middle_name' => 'Partner Middle',
            'partner_last_name' => 'Partner Last',
            'partner_sex' => 'female',
            'partner_birthdate' => '1992-06-07',
            'partner_education' => 'College Graduate',
            'partner_income_sources' => '2/7',
            'children' => [[
                'first_name' => 'Saved Child', 'middle_name' => 'Child Middle', 'last_name' => 'Child Last',
                'sex' => 'male', 'civil_status' => 'Single', 'birthdate' => '2012-01-02',
                'in_school' => 'yes', 'education' => 'Grade 7', 'income_sources' => '1, 4',
            ]],
            'other_family_members' => [[
                'first_name' => 'Saved Family', 'middle_name' => 'Family Middle', 'last_name' => 'Family Last',
                'sex' => 'female', 'relation' => '5', 'civil_status' => 'Single', 'birthdate' => '1988-03-04',
                'in_school' => 'no', 'education' => 'High School', 'income_sources' => '3',
            ]],
            'total_children' => 1,
            'total_family_members' => 4,
            'monthly_household_income_band' => '5001_10000',
            'average_monthly_income' => 8500,
            'eyesight_problem' => 'yes', 'eyesight_problem_details' => 'Saved Eyesight', 'eyesight_problem_count' => 1,
            'hearing_problem' => 'no', 'hearing_problem_details' => null, 'hearing_problem_count' => 0,
            'speech_problem' => 'no', 'speech_problem_details' => null, 'speech_problem_count' => 0,
            'physical_disability' => 'yes', 'physical_disability_details' => 'Saved Physical', 'physical_disability_count' => 1,
            'other_health_concern' => 'yes', 'other_health_concern_details' => 'Saved Health', 'other_health_concern_count' => 1,
            'social_protection_sss' => 'yes', 'social_protection_sss_who' => 'Saved Program Person',
            'social_protection_gsis' => 'no', 'social_protection_gsis_who' => null,
            'social_protection_philhealth' => 'yes', 'social_protection_philhealth_who' => 'Saved Family',
            'social_protection_pagibig' => 'dont_know', 'social_protection_pagibig_who' => null,
            'social_protection_4ps' => 'yes', 'social_protection_4ps_who' => 'Saved Partner',
            'social_protection_other_name' => 'Saved Program', 'social_protection_other' => 'yes',
            'social_protection_other_who' => 'Saved Child',
            'government_ids' => [
                'prc_license' => ['held' => 'no', 'validity' => null],
                'philid' => ['held' => 'yes', 'validity' => 'valid'],
            ],
            'other_government_id_name' => null,
            'has_birth_certificate' => 'yes',
            'has_legal_cases' => 'no',
            'highest_education' => 'college_level',
            'elementary_level_year' => null, 'high_school_level_year' => null, 'college_level_year' => '3',
            'prior_income_type' => 'wage_earner', 'prior_wage_sources' => ['government'],
            'prior_wage_other' => null, 'prior_self_employed_sources' => [], 'prior_self_employed_other' => null,
            'prior_income_other' => null, 'current_income_type' => 'self_employed', 'current_wage_sources' => [],
            'current_wage_other' => null, 'current_self_employed_sources' => ['home_business', 'others'],
            'current_self_employed_other' => 'Saved Income Other', 'current_income_other' => null,
            'current_skill_categories' => ['agriculture_fishery', 'culinary'],
            'current_skills' => ['agricultural_crops', 'food_beverage_services'],
            'current_agriculture_other' => 'Saved Agriculture Skill', 'current_construction_other' => null,
            'current_furniture_other' => null, 'current_garments_other' => null,
            'current_community_services_other' => null, 'current_transport_other' => null,
            'current_machine_operation_other' => null, 'current_food_processing_other' => 'Saved Processing Skill',
            'current_other_technical_skills' => 'Saved Other Skill',
            'desired_skill_categories' => ['construction', 'culinary'],
            'desired_skills' => ['carpentry', 'food_processing'],
            'desired_agriculture_other' => null, 'desired_construction_other' => 'Saved Construction Skill',
            'desired_furniture_other' => null, 'desired_garments_other' => null,
            'desired_community_services_other' => null, 'desired_transport_other' => null,
            'desired_engineering_other' => null, 'desired_culinary_other' => 'Saved Culinary Skill',
            'desired_other_technical_skills' => null,
            'house_tenures' => ['owner_amortized'], 'house_rent_free_owner_details' => null,
            'house_tenure_other' => null, 'house_materials' => ['concrete', 'typhoon_resistant'],
            'residential_lot_tenures' => ['fully_owned'], 'residential_lot_other' => null,
            'agricultural_land_tenures' => ['clt_cloa', 'others'],
            'agricultural_land_other' => 'Saved Agricultural Tenure',
            'prior_organization_member' => 'yes', 'prior_organization_types' => ['cooperative'],
            'prior_cooperative_details' => 'Saved Cooperative', 'prior_workers_union_details' => null,
            'prior_barangay_group_details' => null, 'prior_ngo_details' => null, 'prior_organization_other' => null,
            'current_organization_member' => 'no', 'current_organization_types' => [], 'current_organization_other' => null,
            'plans_join_organization' => 'yes', 'planned_organization' => 'Saved Future Organization',
            'no_join_reason' => null, 'not_sure_join_reason' => null,
            'registered_voter' => 'yes', 'plans_to_vote' => 'yes', 'plans_to_run_for_office' => 'not_sure',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function profilingInterviewRows(string $prefix, int $count, bool $family = false): array
    {
        return collect(range(1, $count))->map(function (int $number) use ($prefix, $family): array {
            $row = [
                'first_name' => $prefix.' '.$number,
                'middle_name' => 'Middle '.$number,
                'last_name' => 'Last '.$number,
                'sex' => $number % 2 === 0 ? 'female' : 'male',
                'civil_status' => 'Single',
                'birthdate' => '2000-01-'.str_pad((string) min($number, 28), 2, '0', STR_PAD_LEFT),
                'in_school' => $number % 2 === 0 ? 'no' : 'yes',
                'education' => 'Grade '.$number,
                'income_sources' => '1, 4',
            ];
            if ($family) {
                $row['relation'] = '5';
            }

            return $row;
        })->all();
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
