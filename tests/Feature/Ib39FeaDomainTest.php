<?php

namespace Tests\Feature;

use App\Enums\Ib39FeaComplianceStatus;
use App\Enums\Ib39FeaDocumentStatus;
use App\Enums\Ib39FeaDocumentType;
use App\Enums\Ib39FeaOverallStatus;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39FeaSynchronizationService;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Ib39FeaDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_firearms_yes_creates_one_processing_and_six_required_unique_slots(): void
    {
        $record = $this->createSurfacedFormerRebel(true);
        $processing = $record->feaProcessing()->with('documents')->sole();

        $this->assertCount(6, $processing->documents);
        $this->assertEqualsCanonicalizing(
            array_column(Ib39FeaDocumentType::cases(), 'value'),
            $processing->documents->pluck('document_type')->map->value->all(),
        );
        $this->assertTrue($processing->documents->every(fn ($document) => $document->is_required));
        $this->assertTrue($processing->documents->every(fn ($document) => $document->status === Ib39FeaDocumentStatus::Pending));
        $this->assertTrue($processing->documents->every(fn ($document) => $document->compliance_status === Ib39FeaComplianceStatus::None));
        $this->assertSame(Ib39FeaOverallStatus::Pending, $processing->overallStatus());
    }

    public function test_firearms_no_creates_no_processing(): void
    {
        $record = $this->createSurfacedFormerRebel(false);

        $this->assertNull($record->feaProcessing()->first());
        $this->assertDatabaseCount('ib39_fea_processings', 0);
        $this->assertDatabaseCount('ib39_fea_documents', 0);
    }

    public function test_repeated_synchronization_is_idempotent_and_constraints_prevent_duplicates(): void
    {
        $record = $this->createSurfacedFormerRebel(true);
        $service = app(Ib39FeaSynchronizationService::class);

        $first = $service->ensureForSurfacedFormerRebel($record);
        $second = $service->ensureForSurfacedFormerRebel($record);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('ib39_fea_processings', 1);
        $this->assertDatabaseCount('ib39_fea_documents', 6);

        $this->expectException(QueryException::class);
        DB::table('ib39_fea_documents')->insert([
            'fea_processing_id' => $first->id,
            'document_type' => Ib39FeaDocumentType::Tir->value,
            'status' => Ib39FeaDocumentStatus::Pending->value,
            'compliance_status' => Ib39FeaComplianceStatus::None->value,
            'is_required' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_migration_backfills_only_active_existing_firearms_records(): void
    {
        Schema::drop('ib39_fea_documents');
        Schema::drop('ib39_fea_processings');

        $eligible = $this->insertExistingRecord(true);
        $this->insertExistingRecord(false);
        $deleted = $this->insertExistingRecord(true);
        DB::table('ib39_surfaced_former_rebels')->where('id', $deleted)->update(['deleted_at' => now()]);

        $migration = require database_path('migrations/2026_08_31_000005_create_ib39_fea_processing_tables.php');
        $migration->up();

        $this->assertDatabaseHas('ib39_fea_processings', ['ib39_surfaced_former_rebel_id' => $eligible]);
        $this->assertDatabaseMissing('ib39_fea_processings', ['ib39_surfaced_former_rebel_id' => $deleted]);
        $this->assertDatabaseCount('ib39_fea_processings', 1);
        $this->assertDatabaseCount('ib39_fea_documents', 6);
    }

    public function test_indicator_change_preserves_history_and_reports_not_applicable(): void
    {
        $record = $this->createSurfacedFormerRebel(true);
        $processing = $record->feaProcessing()->firstOrFail();

        $record->forceFill(['possessed_firearms' => false])->save();

        $this->assertDatabaseHas('ib39_fea_processings', ['id' => $processing->id]);
        $this->assertDatabaseCount('ib39_fea_documents', 6);
        $this->assertSame(Ib39FeaOverallStatus::NotApplicable, $processing->fresh()->overallStatus());
    }

    public function test_schema_has_no_cross_workflow_identity_link_and_status_is_not_stored(): void
    {
        foreach (['mblrc_enrollment_id', 'former_rebel_id', 'overall_status'] as $column) {
            $this->assertFalse(Schema::hasColumn('ib39_fea_processings', $column));
        }

        $processing = $this->createSurfacedFormerRebel(true)->feaProcessing()->firstOrFail();
        $processing->forceFill(['overall_status' => 'Completed']);
        $this->assertFalse($processing->isFillable('overall_status'));
        $this->assertSame(Ib39FeaOverallStatus::Pending, $processing->overallStatus());
    }

    public function test_unsupported_final_capabilities_are_denied(): void
    {
        $actor = User::factory()->role('39th_ib')->create();
        $processing = $this->createSurfacedFormerRebel(true)->feaProcessing()->firstOrFail();

        $this->assertFalse($actor->can('completeDocument', $processing));
        $this->assertFalse($actor->can('finalize', $processing));
        $this->assertFalse($actor->can('uploadFinalCopy', $processing));
    }

    private function createSurfacedFormerRebel(bool $possessedFirearms): Ib39SurfacedFormerRebel
    {
        $actor = User::factory()->role('39th_ib')->create();
        [$municipality, $barangay] = $this->location();

        return app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic',
            'last_name' => 'Record',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-30',
            'possessed_firearms' => $possessedFirearms,
        ], $actor);
    }

    private function insertExistingRecord(bool $possessedFirearms): int
    {
        $actor = User::factory()->role('39th_ib')->create();
        [$municipality, $barangay] = $this->location();

        return DB::table('ib39_surfaced_former_rebels')->insertGetId([
            'reference_number' => 'BACKFILL-'.fake()->unique()->numerify('####'),
            'first_name' => 'Synthetic',
            'last_name' => 'Backfill',
            'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-30',
            'possessed_firearms' => $possessedFirearms,
            'created_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function location(): array
    {
        $municipality = Municipality::query()->create(['name' => fake()->unique()->city()]);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => fake()->unique()->streetName()]);

        return [$municipality, $barangay];
    }
}
