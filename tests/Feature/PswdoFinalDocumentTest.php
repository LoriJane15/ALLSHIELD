<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\PswdoEnrollment;
use App\Models\PswdoEnrollmentDocument;
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
            $this->upload($type, 'required-'.$position)->assertRedirect(route('pswdo.enrollments.workspace', $this->enrollment));
        }
        $this->assertFalse($this->enrollment->fresh()->isCompleted());
        $this->upload(PswdoEnrollmentDocumentType::EndorsementLetter, 'endorsement')->assertSessionHasNoErrors();
        $fresh = $this->enrollment->fresh('documents');
        $this->assertTrue($fresh->isCompleted());
        $this->assertSame(4, $fresh->completedDocumentCount());
        $this->assertTrue($fresh->documents->every(fn ($document) => str_starts_with($document->getRawOriginal('storage_path'), "pswdo/enrollments/{$fresh->id}/")));
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
        $cdrVersion = $cdr->documentVersions()->create(['version_number' => 1, 'source_type' => 'uploaded', 'storage_path' => "ib39/cdr/{$cdr->id}/final.pdf", 'original_filename' => 'cdr.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => hash('sha256', uniqid()), 'created_by' => $ib39->id, 'finalized_at' => now()]);
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $ib39->id, 'current_final_version_id' => $cdrVersion->id]);
        $processing = JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id, 'triggering_cdr_document_version_id' => $cdrVersion->id, 'status' => 'Completed', 'received_at' => now(), 'due_at' => now()->addDays(14), 'completed_at' => now(), 'completed_by' => $japic->id, 'lock_version' => 1]);
        $japicVersion = $processing->documentVersions()->create(['version_number' => 1, 'storage_path' => "japic/certifications/{$processing->id}/final-documents/final.pdf", 'original_filename' => 'japic.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => hash('sha256', uniqid()), 'uploaded_by' => $japic->id, 'all_signatories_confirmed' => true, 'correct_final_confirmed' => true, 'uploaded_at' => now()]);
        $processing->forceFill(['current_final_version_id' => $japicVersion->id])->save();

        return DB::transaction(fn () => app(PswdoEnrollmentIntakeService::class)->receiveEligible($record->id));
    }
}
