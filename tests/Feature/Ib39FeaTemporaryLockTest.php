<?php

namespace Tests\Feature;

use App\Contracts\Ib39FeaReadiness;
use App\Enums\Ib39FeaComplianceStatus;
use App\Enums\Ib39FeaDocumentStatus;
use App\Enums\Ib39FeaDocumentType;
use App\Enums\Ib39FeaUploadSlot;
use App\Enums\Ib39FrCategory;
use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\Barangay;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39FeaDocumentVersion;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\PswdoEnrollment;
use App\Models\User;
use App\Services\Ib39FeaDocumentWorkflowService;
use App\Services\Ib39FeaDraftSchema;
use App\Services\Ib39FeaUploadService;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\LockedIb39FeaReadiness;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Ib39FeaTemporaryLockTest extends TestCase
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
        $municipality = Municipality::query()->create(['name' => 'Temporary Lock Municipality']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'Temporary Lock Barangay']);
        $this->record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => 'Temporary Lock',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id, 'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-09-01', 'possessed_firearms' => true,
        ], $this->actor);
    }

    public function test_readiness_is_derived_only_from_all_four_pswdo_final_documents(): void
    {
        $readiness = app(Ib39FeaReadiness::class);

        $this->assertInstanceOf(LockedIb39FeaReadiness::class, $readiness);
        $this->assertFalse($readiness->isReady($this->record));
        $this->assertSame(self::MESSAGE, $readiness->denialMessage());
        $this->assertLocked(fn () => $readiness->assertReady($this->record));

        $this->completePswdoEnrollment();
        $this->assertTrue($readiness->isReady($this->record));
        $readiness->assertReady($this->record);
    }

    public function test_pswdo_document_presence_without_final_confirmation_does_not_unlock_fea(): void
    {
        $enrollment = $this->completePswdoEnrollment(false);
        $readiness = app(Ib39FeaReadiness::class);
        $this->assertFalse($enrollment->fresh()->isCompleted());
        $this->assertSame(3, $enrollment->fresh()->completedDocumentCount());
        $this->assertFalse($readiness->isReady($this->record));
        $processing = $this->record->feaProcessing;
        $document = $processing->documents()->where('document_type', Ib39FeaDocumentType::Tir)->firstOrFail();
        $this->actingAs($this->actor)->get(route('ib39.fea.show', $processing))
            ->assertOk()
            ->assertSee('Final FEA documents and photos can be uploaded after PSWDO enrollment is completed.')
            ->assertDontSee('type="file"', false);
        $this->actingAs($this->actor)->post(route('ib39.fea.documents.final-versions.store', [$processing, $document]), [
            'file' => $this->pdf(),
        ])->assertForbidden();
        $this->assertSame(0, $document->versions()->count());
        $this->assertSame(0, $document->uploadHistories()->count());
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_fea_actions_unlock_after_pswdo_completion_and_no_firearms_remains_not_applicable(): void
    {
        $this->completePswdoEnrollment();
        $processing = $this->record->feaProcessing;
        $document = $processing->documents()->where('document_type', Ib39FeaDocumentType::Tir)->firstOrFail();

        $this->actingAs($this->actor)->get(route('ib39.fea.show', $processing))->assertOk()
            ->assertDontSee(self::MESSAGE)->assertSee('Start Preliminary Work');
        $this->actingAs($this->actor)->post(route('ib39.fea.documents.start', [$processing, $document]))->assertRedirect();
        $this->assertSame(Ib39FeaDocumentStatus::Processing, $document->fresh()->status);

        $this->record->update(['possessed_firearms' => false]);
        $this->assertFalse(app(Ib39FeaReadiness::class)->isReady($this->record->fresh()));
        $this->assertSame('Not Applicable', $processing->fresh('surfacedFormerRebel', 'documents')->overallStatus()->value);
    }

    public function test_final_uploads_complete_the_six_required_documents_after_pswdo_completion(): void
    {
        $this->completePswdoEnrollment();
        $processing = $this->record->feaProcessing;

        foreach (Ib39FeaDocumentType::cases() as $index => $type) {
            $document = $processing->documents()->where('document_type', $type)->firstOrFail();
            $photo = in_array($type, [Ib39FeaDocumentType::FirearmPhoto, Ib39FeaDocumentType::FrWithFirearmPhoto], true);
            $file = $photo ? UploadedFile::fake()->image('final.jpg') : $this->pdf();

            $this->actingAs($this->actor)->post(route('ib39.fea.documents.final-versions.store', [$processing, $document]), [
                'file' => $file,
            ])->assertRedirect();

            $document->refresh();
            $this->assertSame(Ib39FeaDocumentStatus::Completed, $document->status);
            $this->assertNotNull($document->completed_at);
            $final = $document->currentFinalVersion;
            $this->assertNotNull($final);
            $this->assertSame(Ib39FeaUploadSlot::FinalPrimary, $final->slot);
            $this->assertStringContainsString('/finals/', $final->storage_path);
            Storage::disk('local')->assertExists($final->storage_path);
            $this->assertSame(1, $document->uploadHistories()->where('event', 'final_uploaded')->count());
            $this->assertSame($index === 5 ? 'Completed' : 'Pending', $processing->fresh()->overallStatus()->value);
        }

        $readiness = $this->mock(Ib39FeaReadiness::class);
        $readiness->shouldReceive('isReady')->andReturn(false);
        $this->assertSame('Processing', $processing->fresh()->overallStatus()->value);
        $this->assertSame(6, $processing->documents()->whereNotNull('current_final_version_id')->count());
        $this->actingAs($this->actor)->post(route('ib39.fea.documents.final-versions.store', [
            $processing, $processing->documents()->firstOrFail(),
        ]), ['file' => $this->pdf()])->assertForbidden();
        $this->assertSame(6, $processing->documents()->whereNotNull('current_final_version_id')->count());
    }

    public function test_final_version_preview_and_download_keep_japic_and_pswdo_read_only(): void
    {
        $this->completePswdoEnrollment();
        $processing = $this->record->feaProcessing;
        $document = $processing->documents()->where('document_type', Ib39FeaDocumentType::Tir)->firstOrFail();
        $otherDocument = $processing->documents()->where('document_type', Ib39FeaDocumentType::Cvif)->firstOrFail();
        $this->actingAs($this->actor)->post(route('ib39.fea.documents.final-versions.store', [$processing, $document]), [
            'file' => $this->pdf(),
        ])->assertRedirect();
        $final = $document->fresh()->currentFinalVersion;

        foreach (['japic', 'pswdo'] as $role) {
            $viewer = User::factory()->role($role)->create();
            $this->actingAs($viewer)->get(route("{$role}.fea.documents.versions.preview", [$processing, $document, $final]))->assertOk();
            $this->actingAs($viewer)->get(route("{$role}.fea.documents.versions.download", [$processing, $document, $final]))->assertOk();
            $this->actingAs($viewer)->get(route("{$role}.fea.documents.versions.preview", [$processing, $otherDocument, $final]))->assertNotFound();
            $this->actingAs($viewer)->post(route('ib39.fea.documents.final-versions.store', [$processing, $otherDocument]), [
                'file' => $this->pdf(),
            ])->assertForbidden();
        }
    }

    public function test_final_schema_round_trip_preserves_existing_drafts_and_history(): void
    {
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $document = $this->record->feaProcessing->documents()->firstOrFail();
        $draft = $this->version($document, Ib39FeaUploadSlot::Primary);
        $history = $document->histories()->create([
            'fea_processing_id' => $document->fea_processing_id,
            'user_id' => $this->actor->id,
            'event' => 'processing_started',
            'previous_values' => ['status' => 'Pending'],
            'new_values' => ['status' => 'Processing'],
        ]);
        $migration = $this->finalMigration();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('ib39_fea_documents', 'current_final_version_id'));
        $this->assertStringNotContainsString('final_primary', DB::table('sqlite_master')->where('name', 'ib39_fea_document_versions')->value('sql'));
        $this->assertStringNotContainsString("'completed'", DB::table('sqlite_master')->where('name', 'ib39_fea_document_histories')->value('sql'));
        $this->assertDatabaseHas('ib39_fea_document_versions', ['id' => $draft->id, 'slot' => 'primary']);
        $this->assertDatabaseHas('ib39_fea_document_histories', ['id' => $history->id, 'event' => 'processing_started']);

        $migration->up();
        $this->assertTrue(Schema::hasColumn('ib39_fea_documents', 'current_final_version_id'));
        $this->assertStringContainsString('final_primary', DB::table('sqlite_master')->where('name', 'ib39_fea_document_versions')->value('sql'));
        $this->assertStringContainsString("'completed'", DB::table('sqlite_master')->where('name', 'ib39_fea_document_histories')->value('sql'));
        $this->assertDatabaseHas('ib39_fea_document_versions', ['id' => $draft->id, 'slot' => 'primary']);
        $this->assertDatabaseHas('ib39_fea_document_histories', ['id' => $history->id, 'event' => 'processing_started']);
        $final = $this->version($document, Ib39FeaUploadSlot::FinalPrimary);
        $document->update(['current_final_version_id' => $final->id]);
        $document->histories()->create([
            'fea_processing_id' => $document->fea_processing_id,
            'user_id' => $this->actor->id,
            'event' => 'completed',
            'previous_values' => ['status' => 'Processing'],
            'new_values' => ['status' => 'Completed'],
        ]);
        $this->assertSame($final->id, $document->fresh()->current_final_version_id);
        $this->assertSame(1, $document->histories()->where('event', 'completed')->count());
    }

    public function test_final_pointer_rejects_a_missing_version(): void
    {
        $document = $this->record->feaProcessing->documents()->firstOrFail();
        $this->expectException(QueryException::class);
        DB::table('ib39_fea_documents')->where('id', $document->id)->update(['current_final_version_id' => 999999]);
    }

    public function test_final_pointer_rejects_a_version_owned_by_another_document(): void
    {
        $documents = $this->record->feaProcessing->documents()->orderBy('id')->get();
        $version = $this->version($documents[1], Ib39FeaUploadSlot::Primary);

        $this->expectException(QueryException::class);
        DB::table('ib39_fea_documents')->where('id', $documents[0]->id)
            ->update(['current_final_version_id' => $version->id]);
    }

    public function test_rollback_refuses_a_final_version_without_changing_schema_or_data(): void
    {
        $document = $this->record->feaProcessing->documents()->firstOrFail();
        $final = $this->version($document, Ib39FeaUploadSlot::FinalPrimary);
        $this->assertRollbackRefused($document, 'ib39_fea_document_versions', $final->id);
    }

    public function test_rollback_refuses_a_populated_final_pointer_without_changing_schema_or_data(): void
    {
        $document = $this->record->feaProcessing->documents()->firstOrFail();
        $version = $this->version($document, Ib39FeaUploadSlot::Primary);
        $document->update(['current_final_version_id' => $version->id]);
        $this->assertRollbackRefused($document, 'ib39_fea_documents', $document->id);
    }

    public function test_rollback_refuses_a_completed_history_without_changing_schema_or_data(): void
    {
        $document = $this->record->feaProcessing->documents()->firstOrFail();
        $history = $document->histories()->create([
            'fea_processing_id' => $document->fea_processing_id,
            'user_id' => $this->actor->id,
            'event' => 'completed',
        ]);
        $this->assertRollbackRefused($document, 'ib39_fea_document_histories', $history->id);
    }

    public function test_mysql_enum_statements_preserve_all_existing_values(): void
    {
        $migration = $this->finalMigration();
        $method = new \ReflectionMethod($migration, 'alterMysqlEnums');
        $queries = DB::connection()->pretend(fn () => $method->invoke($migration,
            ['primary', 'final_primary', 'justification_surrendered', 'justification_comparison'],
            ['processing_started', 'compliance_changed', 'remarks_changed', 'delay_changed', 'completed'],
        ));

        $this->assertCount(3, $queries);
        $this->assertStringContainsString("ENUM('primary', 'final_primary', 'justification_surrendered', 'justification_comparison')", $queries[0]['query']);
        $this->assertStringContainsString("ENUM('primary', 'final_primary', 'justification_surrendered', 'justification_comparison')", $queries[1]['query']);
        $this->assertStringContainsString("ENUM('processing_started', 'compliance_changed', 'remarks_changed', 'delay_changed', 'completed')", $queries[2]['query']);
    }

    public function test_initialization_profile_queue_and_workspace_remain_available_with_lock_notice(): void
    {
        $processing = $this->record->feaProcessing()->with('documents')->sole();

        $this->assertCount(6, $processing->documents);
        $this->assertEqualsCanonicalizing(
            array_column(Ib39FeaDocumentType::cases(), 'value'),
            $processing->documents->pluck('document_type')->map->value->all(),
        );
        $this->assertTrue($processing->documents->every(fn (Ib39FeaDocument $document): bool => $document->is_required));

        $this->actingAs($this->actor)->get(route('ib39.fr-profiles.show', $this->record))
            ->assertOk()
            ->assertSee('View FEA Record')
            ->assertSee(route('ib39.fea.show', $processing));
        $this->actingAs($this->actor)->get(route('ib39.fea.index'))
            ->assertOk()
            ->assertSee($this->record->reference_number)
            ->assertSee(self::MESSAGE);
        $workspace = $this->actingAs($this->actor)->get(route('ib39.fea.show', $processing))->assertOk();
        $workspace->assertSee(self::MESSAGE)
            ->assertSee('View Upload History')
            ->assertSee('Preview Saved Draft')
            ->assertDontSee('Open Official Form Editor')
            ->assertDontSee('Start Preliminary Work')
            ->assertDontSee('Update Preliminary Work')
            ->assertDontSee('type="file"', false);
    }

    public function test_all_six_mutation_routes_and_cross_record_requests_are_denied_without_side_effects(): void
    {
        $processing = $this->record->feaProcessing;
        $document = $processing->documents()->where('document_type', Ib39FeaDocumentType::Tir)->firstOrFail();
        $justification = $processing->documents()->where('document_type', Ib39FeaDocumentType::Justification)->firstOrFail();
        $other = $this->otherRecord()->feaProcessing;
        $before = $this->stateFingerprint();

        foreach ([$processing, $other] as $target) {
            $this->actingAs($this->actor)->post(route('ib39.fea.documents.start', [$target, $document]))->assertForbidden();
            $this->actingAs($this->actor)->patch(route('ib39.fea.documents.update', [$target, $document]), ['document' => $this->preliminaryPayload()])->assertForbidden();
            $this->actingAs($this->actor)->put(route('ib39.fea.documents.draft.update', [$target, $document]), ['revision' => 0, 'draft' => ['crafted' => 'value']])->assertForbidden();
            $this->actingAs($this->actor)->post(route('ib39.fea.documents.versions.store', [$target, $document]), ['file' => $this->pdf()])->assertForbidden();
            $this->actingAs($this->actor)->post(route('ib39.fea.documents.surrendered-versions.store', [$target, $justification]), ['file' => $this->pdf()])->assertForbidden();
            $this->actingAs($this->actor)->post(route('ib39.fea.documents.comparison-versions.store', [$target, $justification]), ['file' => $this->pdf()])->assertForbidden();
        }

        $this->assertSame($before, $this->stateFingerprint());
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_direct_workflow_and_upload_calls_are_denied_without_side_effects(): void
    {
        $processing = $this->record->feaProcessing;
        $document = $processing->documents()->where('document_type', Ib39FeaDocumentType::Tir)->firstOrFail();
        $justification = $processing->documents()->where('document_type', Ib39FeaDocumentType::Justification)->firstOrFail();
        $workflow = app(Ib39FeaDocumentWorkflowService::class);
        $uploads = app(Ib39FeaUploadService::class);
        $before = $this->stateFingerprint();

        $this->assertLocked(fn () => $workflow->start($processing, $document, $this->actor));
        $this->assertLocked(fn () => $workflow->update($processing, $document, $this->preliminaryPayload(), $this->actor));
        $this->assertLocked(fn () => $workflow->saveDraft(
            $processing,
            $document,
            app(Ib39FeaDraftSchema::class)->initial(Ib39FeaDocumentType::Tir, 'Locked Subject'),
            0,
            $this->actor,
        ));
        foreach ([
            [$document, Ib39FeaUploadSlot::Primary],
            [$justification, Ib39FeaUploadSlot::Primary],
            [$justification, Ib39FeaUploadSlot::JustificationSurrendered],
            [$justification, Ib39FeaUploadSlot::JustificationComparison],
        ] as [$target, $slot]) {
            $this->assertLocked(fn () => $uploads->store(
                $processing,
                $target,
                $slot,
                UploadedFile::fake()->createWithContent('invalid.bin', 'invalid'),
                null,
                null,
                $this->actor,
            ));
        }

        $this->assertSame($before, $this->stateFingerprint());
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    private function assertLocked(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The temporary FEA lock did not deny the mutation.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame(self::MESSAGE, $exception->getMessage());
        }
    }

    private function stateFingerprint(): string
    {
        $tables = [
            'ib39_fea_processings', 'ib39_fea_documents', 'ib39_fea_document_histories',
            'ib39_fea_draft_histories', 'ib39_fea_document_versions', 'ib39_fea_upload_histories',
            'ib39_fea_processing_histories', 'audit_logs', 'ib39_cdr_processings',
            'ib39_cdr_forms', 'ib39_cdr_document_versions', 'ib39_cdr_status_histories',
            'japic_certification_processings', 'japic_certification_histories', 'notifications',
            'ib39_fr_cancellations', 'rcsp_forms', 'users', 'fr_government_assistances',
        ];

        return hash('sha256', collect($tables)->mapWithKeys(
            fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toJson()]
        )->toJson());
    }

    private function preliminaryPayload(): array
    {
        return [
            'status' => Ib39FeaDocumentStatus::Processing->value,
            'compliance_status' => Ib39FeaComplianceStatus::None->value,
            'remarks' => null,
            'compliance_reason' => null,
            'is_delayed' => false,
            'delay_reason' => null,
        ];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('locked.pdf', "%PDF-1.4\nlocked\n%%EOF");
    }

    private function otherRecord(): Ib39SurfacedFormerRebel
    {
        return app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Other', 'last_name' => 'Locked Record',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $this->record->municipality_id,
            'barangay_id' => $this->record->barangay_id,
            'surfaced_at' => '2026-09-02', 'possessed_firearms' => true,
        ], $this->actor);
    }

    private function finalMigration(): Migration
    {
        return require database_path('migrations/2026_09_16_000001_add_final_versions_to_ib39_fea_documents.php');
    }

    private function version(Ib39FeaDocument $document, Ib39FeaUploadSlot $slot): Ib39FeaDocumentVersion
    {
        return $document->versions()->create([
            'fea_processing_id' => $document->fea_processing_id,
            'slot' => $slot,
            'version_number' => 1,
            'storage_path' => "ib39/fea/{$document->fea_processing_id}/fixture.pdf",
            'original_filename' => 'fixture.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'sha256' => str_repeat('f', 64),
            'uploaded_by' => $this->actor->id,
        ]);
    }

    private function assertRollbackRefused(Ib39FeaDocument $document, string $table, int $id): void
    {
        try {
            $this->finalMigration()->down();
            $this->fail('Rollback should refuse existing final data.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot roll back FEA final-version schema', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('ib39_fea_documents', 'current_final_version_id'));
        $this->assertDatabaseHas($table, ['id' => $id]);
        $this->assertDatabaseHas('ib39_fea_documents', ['id' => $document->id]);
    }

    private function completePswdoEnrollment(bool $finalSigned = true): PswdoEnrollment
    {
        $cdr = $this->record->cdrProcessing;
        $cdrVersion = $cdr->documentVersions()->create([
            'version_number' => 1, 'source_type' => 'uploaded', 'storage_path' => 'ib39/cdr/test/final.pdf',
            'original_filename' => 'cdr.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20,
            'sha256' => str_repeat('c', 64), 'created_by' => $this->actor->id, 'finalized_at' => now(),
        ]);
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $this->actor->id, 'current_final_version_id' => $cdrVersion->id]);
        $japic = JapicCertificationProcessing::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => $this->record->id, 'triggering_cdr_document_version_id' => $cdrVersion->id,
            'status' => 'Completed', 'received_at' => now(), 'due_at' => now()->addDays(14),
            'completed_at' => now(), 'completed_by' => $this->actor->id, 'lock_version' => 1,
        ]);
        $japicVersion = $japic->documentVersions()->create([
            'version_number' => 1, 'storage_path' => 'japic/certifications/test/final.pdf', 'original_filename' => 'japic.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => str_repeat('j', 64),
            'uploaded_by' => $this->actor->id, 'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $japic->forceFill(['current_final_version_id' => $japicVersion->id])->save();
        $enrollment = $this->record->pswdoEnrollment()->create(['lock_version' => 0]);
        foreach (PswdoEnrollmentDocumentType::cases() as $position => $type) {
            $enrollment->documents()->create([
                'document_type' => $type, 'storage_path' => "pswdo/enrollments/{$enrollment->id}/{$type->value}/final.pdf",
                'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20,
                'sha256' => hash('sha256', $type->value), 'uploaded_by' => $this->actor->id,
                'correct_document_type_confirmed' => true, 'belongs_to_fr_confirmed' => true,
                'final_signed_confirmed' => $finalSigned || $type !== PswdoEnrollmentDocumentType::EndorsementLetter,
                'uploaded_at' => now()->addSeconds($position),
            ]);
        }

        return $enrollment;
    }
}
