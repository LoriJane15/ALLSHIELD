<?php

namespace Tests\Feature;

use App\Contracts\Ib39FeaReadiness;
use App\Enums\Ib39FeaDocumentStatus;
use App\Enums\Ib39FeaDocumentType;
use App\Enums\Ib39FeaUploadSlot;
use App\Enums\Ib39FrCategory;
use App\Models\AuditLog;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39FeaUploadService;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class Ib39FeaFinalUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Ib39SurfacedFormerRebel $record;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->mock(Ib39FeaReadiness::class)->shouldReceive('isReady')->andReturn(true);
        $this->actor = User::factory()->role('39th_ib')->create();
        $municipality = Municipality::query()->create(['name' => 'Final FEA Test Municipality']);
        $this->record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => 'Final FEA Test',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id,
            'surfaced_at' => '2026-09-01', 'possessed_firearms' => true,
        ], $this->actor);
    }

    public function test_four_final_forms_accept_pdf_and_reject_images_or_invalid_pdf_contents(): void
    {
        foreach ([Ib39FeaDocumentType::Tir, Ib39FeaDocumentType::Cvif, Ib39FeaDocumentType::Ptis, Ib39FeaDocumentType::Justification] as $type) {
            $document = $this->document($type);
            $this->upload($document, UploadedFile::fake()->image('wrong.png'))->assertSessionHasErrors('file');
            $this->upload($document, UploadedFile::fake()->createWithContent('false.pdf', 'not a PDF'))->assertSessionHasErrors('file');
            $this->assertSame(0, $document->versions()->count());

            $this->upload($document, $this->pdf())->assertRedirect();
            $this->assertSame(Ib39FeaDocumentStatus::Completed, $document->fresh()->status);
            $this->assertSame('application/pdf', $document->fresh()->currentFinalVersion->mime_type);
        }
    }

    public function test_two_photo_requirements_accept_jpeg_or_png_and_reject_pdf_or_invalid_images(): void
    {
        foreach ([Ib39FeaDocumentType::FirearmPhoto, Ib39FeaDocumentType::FrWithFirearmPhoto] as $index => $type) {
            $document = $this->document($type);
            $this->upload($document, $this->pdf())->assertSessionHasErrors('file');
            $this->upload($document, UploadedFile::fake()->createWithContent('false.jpg', 'not an image'))->assertSessionHasErrors('file');
            $this->assertSame(0, $document->versions()->count());

            $this->upload($document, UploadedFile::fake()->image($index === 0 ? 'final.jpg' : 'final.png'))->assertRedirect();
            $this->assertContains($document->fresh()->currentFinalVersion->mime_type, ['image/jpeg', 'image/png']);
        }
    }

    public function test_final_upload_rejects_files_over_twenty_mib_without_side_effects(): void
    {
        $document = $this->document(Ib39FeaDocumentType::Tir);
        $this->upload($document, UploadedFile::fake()->create('large.pdf', 20481, 'application/pdf'))
            ->assertSessionHasErrors('file');
        $this->assertSame(0, $document->versions()->count());
        $this->assertSame(0, $document->histories()->count());
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_only_active_39th_ib_can_upload_and_route_parents_must_match(): void
    {
        $document = $this->document(Ib39FeaDocumentType::Tir);
        foreach (['japic', 'pswdo', 'lgu'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->post($this->uploadUrl($document), ['file' => $this->pdf()])->assertForbidden();
        }
        auth()->logout();
        $this->post($this->uploadUrl($document), ['file' => $this->pdf()])->assertRedirect();
        $inactive = User::factory()->role('39th_ib')->create(['is_active' => false]);
        $this->actingAs($inactive)->post($this->uploadUrl($document), ['file' => $this->pdf()])->assertRedirect();

        $otherRecord = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Other', 'last_name' => 'Synthetic Record',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $this->record->municipality_id,
            'surfaced_at' => '2026-09-02', 'possessed_firearms' => true,
        ], $this->actor);
        $this->actingAs($this->actor)->post(route('ib39.fea.documents.final-versions.store', [
            $otherRecord->feaProcessing, $document,
        ]), ['file' => $this->pdf()])->assertForbidden();
        $this->assertSame(0, $document->versions()->count());
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_successful_final_is_immutable_audited_and_cannot_be_uploaded_twice(): void
    {
        $document = $this->document(Ib39FeaDocumentType::Tir);
        $this->upload($document, $this->pdf())->assertRedirect();
        $final = $document->fresh()->currentFinalVersion;
        $this->assertSame(Ib39FeaUploadSlot::FinalPrimary, $final->slot);
        $this->assertSame($this->actor->id, $final->uploaded_by);
        $this->assertSame(hash('sha256', "%PDF-1.4\nfinal\n%%EOF"), $final->sha256);
        $this->assertNotNull($final->created_at);
        $this->assertSame(1, $document->histories()->where('event', 'completed')->count());
        $this->assertSame(1, $document->uploadHistories()->where('event', 'final_uploaded')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'ib39_fea_final_file_uploaded')->count());
        Storage::disk('local')->assertExists($final->storage_path);

        $this->upload($document, $this->pdf())->assertForbidden();
        $this->assertSame(1, $document->versions()->count());
        $this->assertSame(1, count(Storage::disk('local')->allFiles('ib39/fea')));
        try {
            $final->update(['original_filename' => 'changed.pdf']);
            $this->fail('Final version update should be denied.');
        } catch (LogicException) {
            $this->assertSame('final.pdf', $final->fresh()->original_filename);
        }
        try {
            $final->delete();
            $this->fail('Final version deletion should be denied.');
        } catch (LogicException) {
            $this->assertDatabaseHas('ib39_fea_document_versions', ['id' => $final->id]);
        }
    }

    public function test_failed_audit_rolls_back_database_and_removes_new_private_file(): void
    {
        $document = $this->document(Ib39FeaDocumentType::Tir);
        AuditLog::creating(function (AuditLog $audit): void {
            if ($audit->action === 'ib39_fea_final_file_uploaded') {
                throw new RuntimeException('Synthetic audit failure.');
            }
        });

        try {
            app(Ib39FeaUploadService::class)->storeFinal($document->processing, $document, $this->pdf(), $this->actor);
            $this->fail('The synthetic audit failure should abort the upload.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure.', $exception->getMessage());
        }

        $this->assertSame(Ib39FeaDocumentStatus::Pending, $document->fresh()->status);
        $this->assertNull($document->fresh()->current_final_version_id);
        $this->assertSame(0, $document->versions()->count());
        $this->assertSame(0, $document->histories()->count());
        $this->assertSame(0, $document->uploadHistories()->count());
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_storage_failure_cannot_complete_a_document(): void
    {
        $document = $this->document(Ib39FeaDocumentType::Tir);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        try {
            app(Ib39FeaUploadService::class)->storeFinal($document->processing, $document, $this->pdf(), $this->actor);
            $this->fail('Storage failure should abort the upload.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The final file could not be stored.', $exception->getMessage());
        }
        $this->assertSame(Ib39FeaDocumentStatus::Pending, $document->fresh()->status);
        $this->assertSame(0, $document->versions()->count());
        $this->assertSame(0, $document->histories()->count());

    }

    public function test_compliance_issue_cannot_complete_a_document(): void
    {
        $document = $this->document(Ib39FeaDocumentType::Tir);
        $document->update(['compliance_status' => 'Has Issue', 'compliance_reason' => 'Synthetic issue']);
        $this->upload($document, $this->pdf())->assertSessionHasErrors('file');
        $this->assertSame(0, $document->versions()->count());
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    private function document(Ib39FeaDocumentType $type): Ib39FeaDocument
    {
        return $this->record->feaProcessing->documents()->where('document_type', $type)->firstOrFail();
    }

    private function upload(Ib39FeaDocument $document, UploadedFile $file): TestResponse
    {
        return $this->actingAs($this->actor)->post($this->uploadUrl($document), ['file' => $file]);
    }

    private function uploadUrl(Ib39FeaDocument $document): string
    {
        return route('ib39.fea.documents.final-versions.store', [$document->processing, $document]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('final.pdf', "%PDF-1.4\nfinal\n%%EOF");
    }
}
