<?php

namespace Tests\Feature;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Ib39CdrFinalDocument;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Support\Ib39CdrFormSchema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Ib39CdrDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Ib39SurfacedFormerRebel $record;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actor = User::factory()->role('39th_ib')->create();
        $this->record = $this->record('Stage Five');
    }

    public function test_upload_persists_one_final_document_and_second_upload_is_unavailable(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), [
            'document' => $this->pdf(), 'confirmed' => '1',
        ])->assertRedirect();

        $document = $cdr->fresh()->finalDocument;
        $this->assertInstanceOf(Ib39CdrFinalDocument::class, $document);
        $this->assertSame(Ib39CdrStatus::Completed, $cdr->fresh()->status);
        $this->assertSame(Ib39CdrDocumentSource::Uploaded, $document->source_type);
        $this->assertSame($this->actor->id, $document->created_by);
        $this->assertSame('completed-cdr.pdf', $document->original_filename);
        $this->assertSame(64, strlen($document->sha256));
        $this->assertStringStartsWith("ib39/cdr/{$cdr->id}/final-documents/", $document->getRawOriginal('storage_path'));
        Storage::disk('local')->assertExists($document->getRawOriginal('storage_path'));

        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), [
            'document' => $this->pdf(), 'confirmed' => '1',
        ])->assertForbidden();
        $this->assertDatabaseCount('ib39_cdr_final_documents', 1);
    }

    public function test_one_to_one_schema_has_no_version_or_replacement_columns(): void
    {
        $this->assertTrue(Schema::hasTable('ib39_cdr_final_documents'));
        $this->assertTrue(Schema::hasColumns('ib39_cdr_final_documents', [
            'cdr_processing_id', 'source_type', 'storage_path', 'original_filename', 'mime_type',
            'size_bytes', 'sha256', 'content_schema_version', 'content_snapshot', 'created_by', 'finalized_at',
        ]));
        $this->assertFalse(Schema::hasTable('ib39_cdr_document_versions'));
        $this->assertFalse(Schema::hasColumn('ib39_cdr_processings', 'current_final_version_id'));
        $this->assertFalse(Schema::hasColumn('ib39_cdr_final_documents', 'version_number'));
        $this->assertFalse(Schema::hasColumn('ib39_cdr_final_documents', 'replaces_version_id'));
        $this->assertFalse(Schema::hasColumn('ib39_cdr_final_documents', 'replacement_reason'));

        $cdr = $this->record->cdrProcessing;
        $attributes = [
            'cdr_processing_id' => $cdr->id,
            'source_type' => 'uploaded',
            'storage_path' => 'ib39/cdr/duplicate.pdf',
            'original_filename' => 'duplicate.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1,
            'sha256' => str_repeat('a', 64),
            'created_by' => $this->actor->id,
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('ib39_cdr_final_documents')->insert($attributes);
        $this->expectException(QueryException::class);
        DB::table('ib39_cdr_final_documents')->insert($attributes);
    }

    public function test_completed_final_document_can_be_viewed_and_downloaded_with_access_audits(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), ['document' => $this->pdf(), 'confirmed' => '1'])->assertRedirect();
        $document = $cdr->fresh()->finalDocument;

        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.preview', $cdr))
            ->assertOk()
            ->assertSee('Actual Uploaded Final CDR')
            ->assertSee('Print')
            ->assertSee('Download')
            ->assertSee(route('cdr.documents.preview', ['cdr' => $cdr, 'file' => 1]));
        $this->actingAs($this->actor)->get(route('cdr.documents.preview', ['cdr' => $cdr, 'file' => 1]))
            ->assertOk()->assertHeader('Content-Disposition', 'inline; filename="completed-cdr.pdf"');
        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.download', $cdr))->assertOk()->assertDownload('completed-cdr.pdf');
        $this->assertDatabaseHas('audit_logs', ['entity_type' => Ib39CdrFinalDocument::class, 'entity_id' => $document->id, 'action' => 'ib39_cdr_final_document_preview']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => Ib39CdrFinalDocument::class, 'entity_id' => $document->id, 'action' => 'ib39_cdr_final_document_download']);

        Storage::disk('local')->delete($document->getRawOriginal('storage_path'));
        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.preview', $cdr))->assertNotFound();
    }

    public function test_historical_generated_final_document_still_supports_view_print_and_download(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->createHistoricalGeneratedFinal($cdr, ['assessment' => 'Generated final CDR content']);

        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.preview', $cdr))->assertOk()->assertSee('Generated final CDR content');
        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.print', $cdr))->assertOk()->assertSee('Generated final CDR content');
        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.download', $cdr))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="generated-cdr.html"');
    }

    public function test_uploaded_images_use_the_same_read_only_viewer_with_print_and_download_actions(): void
    {
        foreach (['png' => 'image/png', 'jpg' => 'image/jpeg'] as $extension => $mimeType) {
            $record = $extension === 'png' ? $this->record : $this->record('Image '.uniqid());
            $cdr = $record->cdrProcessing;
            $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), [
                'document' => UploadedFile::fake()->image('final-cdr.'.$extension),
                'confirmed' => '1',
            ])->assertRedirect();

            $this->actingAs($this->actor)->get(route('ib39.cdr.documents.preview', $cdr))
                ->assertOk()
                ->assertSee('Actual Uploaded Final CDR')
                ->assertSee('Print')
                ->assertSee('Download')
                ->assertSee('<img src="'.route('cdr.documents.preview', ['cdr' => $cdr, 'file' => 1]).'"', false);
            $this->actingAs($this->actor)->get(route('cdr.documents.preview', ['cdr' => $cdr, 'file' => 1]))
                ->assertOk()->assertHeader('Content-Type', $mimeType);
        }
    }

    public function test_stage_five_does_not_create_unrelated_external_workflow_records(): void
    {
        $before = DB::table('fr_government_assistances')->count();
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $this->record->cdrProcessing), ['document' => $this->pdf(), 'confirmed' => '1'])->assertRedirect();
        $this->assertSame($before, DB::table('fr_government_assistances')->count());
    }

    private function record(string $lastName): Ib39SurfacedFormerRebel
    {
        $municipality = Municipality::query()->create(['name' => 'Stage 5 '.uniqid()]);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'Stage 5 Barangay']);

        return app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => $lastName, 'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE, 'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id, 'surfaced_at' => '2026-08-31', 'possessed_firearms' => false,
        ], $this->actor);
    }

    private function createHistoricalGeneratedFinal($cdr, array $content): void
    {
        $snapshot = ['content' => $content, 'fr_photo_version_id' => null];
        $serialized = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $completedAt = now();
        $cdr->form()->firstOrFail()->update(['content' => $content]);
        $cdr->finalDocument()->create([
            'source_type' => Ib39CdrDocumentSource::Generated,
            'storage_path' => "generated/cdr/{$cdr->id}/final",
            'original_filename' => 'generated-cdr.html',
            'mime_type' => 'text/html',
            'size_bytes' => strlen($serialized),
            'sha256' => hash('sha256', $serialized),
            'content_schema_version' => Ib39CdrFormSchema::VERSION,
            'content_snapshot' => $snapshot,
            'created_by' => $this->actor->id,
            'finalized_at' => $completedAt,
        ]);
        $cdr->update([
            'status' => Ib39CdrStatus::Completed,
            'completed_at' => $completedAt,
            'completed_by' => $this->actor->id,
        ]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('completed-cdr.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }
}
