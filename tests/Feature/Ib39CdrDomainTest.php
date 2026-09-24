<?php

namespace Tests\Feature;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrPhotoType;
use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\Ib39CdrProcessing;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class Ib39CdrDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_schema_contains_cdr_tables_columns_indexes_and_foreign_keys(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        foreach ([
            'ib39_cdr_processings',
            'ib39_cdr_forms',
            'ib39_cdr_status_histories',
            'ib39_cdr_document_versions',
            'ib39_cdr_photos',
            'ib39_cdr_photo_versions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertTrue(Schema::hasColumns('ib39_cdr_processings', [
            'ib39_surfaced_former_rebel_id', 'status', 'started_at', 'due_at',
            'completed_at', 'completed_by', 'current_final_version_id', 'remarks',
            'delay_reason',
        ]));
        $this->assertTrue(Schema::hasColumns('ib39_cdr_forms', [
            'cdr_processing_id', 'schema_version', 'content', 'last_edited_by',
        ]));
        $this->assertTrue(Schema::hasColumns('ib39_cdr_document_versions', [
            'cdr_processing_id', 'version_number', 'source_type', 'replaces_version_id',
            'replacement_reason', 'storage_path', 'original_filename', 'mime_type',
            'size_bytes', 'sha256', 'content_schema_version', 'content_snapshot',
            'created_by', 'finalized_at',
        ]));

        $processingIndexes = collect(Schema::getIndexes('ib39_cdr_processings'));
        $this->assertTrue($processingIndexes->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === ['ib39_surfaced_former_rebel_id']
        ));
        $versionIndexes = collect(Schema::getIndexes('ib39_cdr_document_versions'));
        $this->assertTrue($versionIndexes->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === ['cdr_processing_id', 'version_number']
        ));

        $processingForeignKeys = collect(Schema::getForeignKeys('ib39_cdr_processings'));
        $this->assertTrue($processingForeignKeys->contains(
            fn (array $key) => $key['columns'] === ['ib39_surfaced_former_rebel_id']
                && $key['foreign_table'] === 'ib39_surfaced_former_rebels'
                && $key['on_delete'] === 'restrict'
        ));
        $this->assertTrue($processingForeignKeys->contains(
            fn (array $key) => $key['columns'] === ['current_final_version_id']
                && $key['foreign_table'] === 'ib39_cdr_document_versions'
        ));
    }

    public function test_statuses_and_document_sources_are_controlled_by_sqlite(): void
    {
        $processing = $this->processing();

        try {
            DB::table('ib39_cdr_processings')->where('id', $processing->id)->update(['status' => 'Uncontrolled']);
            $this->fail('SQLite accepted an uncontrolled CDR status.');
        } catch (QueryException) {
            $this->assertSame(Ib39CdrStatus::Pending, $processing->fresh()->status);
        }

        try {
            DB::table('ib39_cdr_status_histories')
                ->where('cdr_processing_id', $processing->id)
                ->update(['to_status' => 'Uncontrolled']);
            $this->fail('SQLite accepted an uncontrolled CDR history status.');
        } catch (QueryException) {
            $this->assertSame(
                Ib39CdrStatus::Pending,
                $processing->statusHistories()->sole()->to_status,
            );
        }

        $this->expectException(QueryException::class);
        DB::table('ib39_cdr_document_versions')->insert([
            ...$this->versionAttributes($processing),
            'source_type' => 'uncontrolled',
        ]);
    }

    public function test_backfill_creates_one_empty_pending_cdr_and_history_for_every_parent_including_trashed(): void
    {
        $active = $this->surfacedFormerRebel();
        $trashed = $this->surfacedFormerRebel();
        $trashed->delete();

        $this->runBackfill();
        $this->runBackfill();

        $this->assertDatabaseCount('ib39_cdr_processings', 2);
        $this->assertDatabaseCount('ib39_cdr_forms', 2);
        $this->assertDatabaseCount('ib39_cdr_status_histories', 2);

        foreach ([$active, $trashed] as $parent) {
            $processing = Ib39CdrProcessing::query()
                ->where('ib39_surfaced_former_rebel_id', $parent->id)
                ->sole();
            $this->assertSame(Ib39CdrStatus::Pending, $processing->status);
            $this->assertNull($processing->started_at);
            $this->assertNull($processing->due_at);
            $this->assertNull($processing->completed_at);
            $this->assertNull($processing->completed_by);
            $this->assertNull($processing->current_final_version_id);
            $this->assertNull($processing->remarks);
            $this->assertNull($processing->delay_reason);
            $this->assertNull($processing->form->content);
            $this->assertSame(1, $processing->form->schema_version);

            $history = $processing->statusHistories()->sole();
            $this->assertSame('system_backfill_created', $history->event);
            $this->assertNull($history->user_id);
            $this->assertNull($history->from_status);
            $this->assertSame(Ib39CdrStatus::Pending, $history->to_status);
            $this->assertNull($history->remarks);
            $this->assertNull($history->delay_reason);
        }

    }

    public function test_unique_constraint_prevents_duplicate_processing_records(): void
    {
        $processing = $this->processing();

        $this->expectException(QueryException::class);
        Ib39CdrProcessing::query()->create([
            'ib39_surfaced_former_rebel_id' => $processing->ib39_surfaced_former_rebel_id,
            'status' => Ib39CdrStatus::Pending,
        ]);
    }

    public function test_unique_constraints_prevent_duplicate_forms_and_photo_slots(): void
    {
        $processing = $this->processing();

        try {
            $processing->form()->create(['schema_version' => 1]);
            $this->fail('A second CDR form was created.');
        } catch (QueryException) {
            $this->assertDatabaseCount('ib39_cdr_forms', 1);
        }

        $processing->photos()->create(['photo_type' => Ib39CdrPhotoType::FrPhoto]);

        $this->expectException(QueryException::class);
        $processing->photos()->create(['photo_type' => Ib39CdrPhotoType::FrPhoto]);
    }

    public function test_database_trigger_accepts_only_the_forward_corrected_fr_photo_type(): void
    {
        $processing = $this->processing();
        $processing->photos()->create(['photo_type' => Ib39CdrPhotoType::FrPhoto]);

        foreach (['whole_body_with_firearm', 'half_body_without_firearm', 'other'] as $type) {
            try {
                DB::table('ib39_cdr_photos')->insert([
                    'cdr_processing_id' => $processing->id,
                    'photo_type' => $type,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->fail("The database accepted the legacy photo type {$type}.");
            } catch (QueryException) {
                $this->assertDatabaseMissing('ib39_cdr_photos', ['photo_type' => $type]);
            }
        }
    }

    public function test_photo_type_correction_rolls_back_and_re_migrates_on_disposable_sqlite(): void
    {
        $migration = require database_path('migrations/2026_08_31_000004_correct_ib39_cdr_photo_type.php');
        $processing = $this->processing();

        $migration->down();
        DB::table('ib39_cdr_photos')->insert([
            'cdr_processing_id' => $processing->id,
            'photo_type' => 'whole_body_with_firearm',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        try {
            DB::table('ib39_cdr_photos')->insert([
                'cdr_processing_id' => $processing->id,
                'photo_type' => 'fr_photo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Rollback did not restore the previous controlled photo values.');
        } catch (QueryException) {
            $this->assertDatabaseHas('ib39_cdr_photos', ['photo_type' => 'whole_body_with_firearm']);
        }

        DB::table('ib39_cdr_photos')->where('cdr_processing_id', $processing->id)->delete();
        $migration->up();
        DB::table('ib39_cdr_photos')->insert([
            'cdr_processing_id' => $processing->id,
            'photo_type' => 'fr_photo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        try {
            DB::table('ib39_cdr_photos')->insert([
                'cdr_processing_id' => $processing->id,
                'photo_type' => 'half_body_without_firearm',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Re-migration accepted a legacy photo type.');
        } catch (QueryException) {
            $this->assertDatabaseHas('ib39_cdr_photos', ['photo_type' => 'fr_photo']);
            $this->assertDatabaseMissing('ib39_cdr_photos', ['photo_type' => 'half_body_without_firearm']);
        }
    }

    public function test_document_version_numbers_are_unique_within_a_cdr(): void
    {
        $processing = $this->processing();
        $processing->documentVersions()->create($this->versionAttributes($processing));

        $this->expectException(QueryException::class);
        $processing->documentVersions()->create($this->versionAttributes($processing));
    }

    public function test_replacement_requires_a_reason_and_a_version_from_the_same_cdr(): void
    {
        $processing = $this->processing();
        $first = $processing->documentVersions()->create($this->versionAttributes($processing));

        try {
            $processing->documentVersions()->create([
                ...$this->versionAttributes($processing),
                'version_number' => 2,
                'replaces_version_id' => $first->id,
                'replacement_reason' => null,
            ]);
            $this->fail('A replacement without a reason was created.');
        } catch (QueryException) {
            $this->assertDatabaseCount('ib39_cdr_document_versions', 1);
        }

        $replacement = $processing->documentVersions()->create([
            ...$this->versionAttributes($processing),
            'version_number' => 2,
            'replaces_version_id' => $first->id,
            'replacement_reason' => 'Synthetic correction reason.',
        ]);

        $this->assertTrue($replacement->replacesVersion->is($first));
        $this->assertTrue($first->replacementVersions->contains($replacement));
    }

    public function test_model_relationships_enum_datetime_and_encrypted_content_casts_work(): void
    {
        $processing = $this->processing();
        $editor = User::factory()->role('39th_ib')->create();
        $form = $processing->form;
        $form->update([
            'content' => ['assessment' => 'Synthetic classified draft text'],
            'last_edited_by' => $editor->id,
        ]);
        $version = $processing->documentVersions()->create([
            ...$this->versionAttributes($processing),
            'content_snapshot' => ['assessment' => 'Synthetic immutable snapshot'],
            'content_schema_version' => 1,
        ]);
        $photo = $processing->photos()->create([
            'photo_type' => Ib39CdrPhotoType::FrPhoto,
        ]);
        $photoVersion = $photo->versions()->create($this->photoVersionAttributes());
        $photo->update(['current_photo_version_id' => $photoVersion->id]);
        $processing->update(['current_final_version_id' => $version->id]);

        $this->assertTrue($processing->surfacedFormerRebel->is($processing->surfacedFormerRebel));
        $this->assertTrue($processing->surfacedFormerRebel->cdrProcessing->is($processing));
        $this->assertTrue($form->lastEditor->is($editor));
        $this->assertSame(['assessment' => 'Synthetic classified draft text'], $form->fresh()->content);
        $this->assertSame(Ib39CdrDocumentSource::Generated, $version->source_type);
        $this->assertSame(['assessment' => 'Synthetic immutable snapshot'], $version->content_snapshot);
        $this->assertTrue($processing->fresh()->currentFinalVersion->is($version));
        $this->assertTrue($photo->fresh()->currentVersion->is($photoVersion));

        $rawFormContent = DB::table('ib39_cdr_forms')->where('id', $form->id)->value('content');
        $rawSnapshot = DB::table('ib39_cdr_document_versions')->where('id', $version->id)->value('content_snapshot');
        $this->assertStringNotContainsString('Synthetic classified draft text', $rawFormContent);
        $this->assertStringNotContainsString('Synthetic immutable snapshot', $rawSnapshot);
        $this->assertArrayNotHasKey('storage_path', $version->toArray());
        $this->assertArrayNotHasKey('storage_path', $photoVersion->toArray());
    }

    public function test_status_history_document_versions_and_photo_versions_are_immutable_through_models(): void
    {
        $processing = $this->processing();
        $history = $processing->statusHistories()->sole();
        $document = $processing->documentVersions()->create($this->versionAttributes($processing));
        $photo = $processing->photos()->create(['photo_type' => Ib39CdrPhotoType::FrPhoto]);
        $photoVersion = $photo->versions()->create($this->photoVersionAttributes());

        foreach ([$history, $document, $photoVersion] as $immutable) {
            try {
                $immutable->forceFill(match (true) {
                    $immutable instanceof Ib39CdrStatusHistory => ['event' => 'changed'],
                    $immutable instanceof Ib39CdrDocumentVersion => ['original_filename' => 'changed.pdf'],
                    default => ['original_filename' => 'changed.jpg'],
                });
                $immutable->save();
                $this->fail($immutable::class.' was updated.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('immutable', $exception->getMessage());
            }

            try {
                $immutable->delete();
                $this->fail($immutable::class.' was deleted.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('immutable', $exception->getMessage());
            }

            $this->assertTrue($immutable::query()->whereKey($immutable->id)->exists());
        }
    }

    private function processing(): Ib39CdrProcessing
    {
        $parent = $this->surfacedFormerRebel();
        $this->runBackfill();

        return $parent->cdrProcessing()->with(['form', 'statusHistories'])->sole();
    }

    private function surfacedFormerRebel(): Ib39SurfacedFormerRebel
    {
        $municipality = Municipality::query()->create(['name' => 'Synthetic CDR Municipality '.uniqid()]);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'Synthetic CDR Barangay',
        ]);

        $record = new Ib39SurfacedFormerRebel;
        $record->fill([
            'first_name' => 'Synthetic',
            'last_name' => 'Record',
            'category' => Ib39FrCategory::RegularMember,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-30',
            'possessed_firearms' => false,
        ]);
        $record->reference_number = 'FR-CDR-'.strtoupper(uniqid());
        $record->created_by = User::factory()->role('39th_ib')->create()->id;
        $record->save();

        return $record;
    }

    private function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_08_31_000003_create_ib39_cdr_tables.php');
        $migration->backfillExistingSurfacedFormerRebels();
    }

    private function versionAttributes(Ib39CdrProcessing $processing): array
    {
        return [
            'cdr_processing_id' => $processing->id,
            'version_number' => 1,
            'source_type' => Ib39CdrDocumentSource::Generated,
            'storage_path' => 'ib39/cdr/synthetic/final.html',
            'original_filename' => 'synthetic-cdr.html',
            'mime_type' => 'text/html',
            'size_bytes' => 128,
            'sha256' => str_repeat('a', 64),
            'created_by' => User::factory()->role('39th_ib')->create()->id,
            'finalized_at' => now(),
        ];
    }

    private function photoVersionAttributes(): array
    {
        return [
            'version_number' => 1,
            'storage_path' => 'ib39/cdr/synthetic/photo.jpg',
            'original_filename' => 'synthetic-photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 64,
            'sha256' => str_repeat('b', 64),
            'uploaded_by' => User::factory()->role('39th_ib')->create()->id,
        ];
    }
}
