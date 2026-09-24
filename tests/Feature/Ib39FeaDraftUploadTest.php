<?php

namespace Tests\Feature;

use App\Enums\Ib39FeaDocumentType;
use App\Enums\Ib39FeaUploadSlot;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39FeaDocumentVersion;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39FeaUploadService;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Ib39FeaDraftUploadTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'FEA processing is unavailable until the required PSWDO enrollment forms are completed.';

    private User $actor;

    private Ib39SurfacedFormerRebel $record;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actor = User::factory()->role('39th_ib')->create();
        $municipality = Municipality::query()->create(['name' => 'Upload Municipality']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'Upload Barangay']);
        $this->record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => 'Upload Subject',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id, 'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-09-01', 'possessed_firearms' => true,
        ], $this->actor);
    }

    public function test_all_upload_routes_and_primary_replacement_are_denied_without_storage(): void
    {
        $tir = $this->document(Ib39FeaDocumentType::Tir);
        $justification = $this->document(Ib39FeaDocumentType::Justification);
        $existing = $this->existingVersion($tir);

        $requests = [
            [route('ib39.fea.documents.versions.store', [$tir->processing, $tir]), ['file' => $this->pdf()]],
            [route('ib39.fea.documents.versions.store', [$tir->processing, $tir]), ['file' => $this->pdf('replacement.pdf'), 'expected_current_version_id' => $existing->id, 'replacement_reason' => 'Replacement attempt']],
            [route('ib39.fea.documents.surrendered-versions.store', [$justification->processing, $justification]), ['file' => $this->pdf('surrendered.pdf')]],
            [route('ib39.fea.documents.comparison-versions.store', [$justification->processing, $justification]), ['file' => $this->pdf('comparison.pdf')]],
        ];

        foreach ($requests as [$url, $payload]) {
            $this->actingAs($this->actor)->post($url, $payload)->assertForbidden();
        }

        $this->assertDatabaseCount('ib39_fea_document_versions', 1);
        $this->assertDatabaseCount('ib39_fea_upload_histories', 0);
        $this->assertSame($existing->id, $tir->fresh()->current_draft_version_id);
        $this->assertSame(['ib39/fea/existing.pdf'], Storage::disk('local')->allFiles());
    }

    public function test_direct_upload_service_denies_before_inspection_or_file_writing_for_every_slot(): void
    {
        $uploads = app(Ib39FeaUploadService::class);
        $tir = $this->document(Ib39FeaDocumentType::Tir);
        $justification = $this->document(Ib39FeaDocumentType::Justification);

        foreach ([
            [$tir, Ib39FeaUploadSlot::Primary],
            [$justification, Ib39FeaUploadSlot::Primary],
            [$justification, Ib39FeaUploadSlot::JustificationSurrendered],
            [$justification, Ib39FeaUploadSlot::JustificationComparison],
        ] as [$document, $slot]) {
            try {
                $uploads->store($document->processing, $document, $slot, UploadedFile::fake()->createWithContent('malformed.bin', 'not a valid upload'), null, null, $this->actor);
                $this->fail('The temporary FEA lock did not deny a direct upload.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
                $this->assertSame(self::MESSAGE, $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('ib39_fea_document_versions', 0);
        $this->assertDatabaseCount('ib39_fea_upload_histories', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_existing_version_remains_previewable_and_downloadable_while_locked(): void
    {
        $document = $this->document(Ib39FeaDocumentType::Tir);
        $version = $this->existingVersion($document);
        $historyCounts = [$document->histories()->count(), $document->uploadHistories()->count()];

        $this->actingAs($this->actor)
            ->get(route('ib39.fea.documents.versions.preview', [$document->processing, $document, $version]))
            ->assertOk();
        $this->actingAs($this->actor)
            ->get(route('ib39.fea.documents.versions.download', [$document->processing, $document, $version]))
            ->assertOk();

        $this->assertSame($historyCounts, [$document->histories()->count(), $document->uploadHistories()->count()]);
        $this->assertSame($version->id, $document->fresh()->current_draft_version_id);
        Storage::disk('local')->assertExists('ib39/fea/existing.pdf');
    }

    private function existingVersion(Ib39FeaDocument $document): Ib39FeaDocumentVersion
    {
        $contents = "%PDF-1.4\nexisting\n%%EOF";
        Storage::disk('local')->put('ib39/fea/existing.pdf', $contents);
        $version = $document->versions()->create([
            'fea_processing_id' => $document->fea_processing_id,
            'slot' => Ib39FeaUploadSlot::Primary,
            'version_number' => 1,
            'storage_path' => 'ib39/fea/existing.pdf',
            'original_filename' => 'existing.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'uploaded_by' => $this->actor->id,
        ]);
        $document->update(['current_draft_version_id' => $version->id]);

        return $version;
    }

    private function document(Ib39FeaDocumentType $type): Ib39FeaDocument
    {
        return $this->record->feaProcessing->documents()->where('document_type', $type)->firstOrFail();
    }

    private function pdf(string $name = 'draft.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nlocked\n%%EOF");
    }
}
