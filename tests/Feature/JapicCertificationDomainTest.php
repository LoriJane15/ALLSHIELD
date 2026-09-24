<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationDraft;
use App\Models\JapicCertificationDraftHistory;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Support\JapicCertificationDraftSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class JapicCertificationDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_constraints_encryption_immutability_and_role_policy(): void
    {
        foreach (['japic_certification_processings', 'japic_certification_drafts',
            'japic_certification_draft_histories', 'japic_certification_histories',
            'japic_certification_document_versions', 'japic_certification_photo_versions'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $japic = User::factory()->role('japic')->create();
        $this->assertSame('japic.dashboard', $japic->homeRoute());
        $this->assertTrue($japic->can('viewAny', JapicCertificationProcessing::class));
        $this->assertFalse(User::factory()->role('39th_ib')->create()->can('viewAny', JapicCertificationProcessing::class));
        $this->assertSame(2, JapicCertificationDraftSchema::VERSION);
        $this->assertTrue(Schema::hasColumn('japic_certification_processings', 'current_photo_version_id'));
        $this->assertSame(
            app(JapicCertificationDraftSchema::class)->controlNumberHash('  Japic-AbC-001 '),
            app(JapicCertificationDraftSchema::class)->controlNumberHash('japic-abc-001')
        );
    }

    public function test_sensitive_values_are_encrypted_and_histories_are_immutable(): void
    {
        [$processing, $user] = $this->processing();
        $processing->update(['control_number' => 'JAPIC-Secret-001', 'control_number_hash' => hash('sha256', 'japic-secret-001')]);
        $draft = JapicCertificationDraft::query()->forceCreate(['processing_id' => $processing->id,
            'payload' => ['personal_name' => 'Private Person'], 'schema_version' => 1, 'revision' => 1,
            'last_saved_by' => $user->id, 'last_saved_at' => now()]);
        $history = JapicCertificationDraftHistory::query()->forceCreate(['processing_id' => $processing->id,
            'revision' => 1, 'payload' => ['personal_name' => 'Private Person'], 'saved_by' => $user->id, 'saved_at' => now()]);
        $this->assertStringNotContainsString('JAPIC-Secret-001', DB::table('japic_certification_processings')->value('control_number'));
        $this->assertStringNotContainsString('Private Person', DB::table('japic_certification_drafts')->value('payload'));
        $this->assertSame('Private Person', $draft->payload['personal_name']);
        $this->expectException(LogicException::class);
        $history->update(['revision' => 2]);
    }

    private function processing(): array
    {
        $user = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'JAPIC Domain Municipality', 'created_at' => now(), 'updated_at' => now()]);
        $frModel = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Test', 'last_name' => 'Person', 'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE, 'municipality_id' => $municipality,
            'barangay_id' => null, 'specific_location' => null, 'surfaced_at' => now()->toDateString(),
            'possessed_firearms' => false, 'initial_remarks' => null,
        ], $user);
        $fr = $frModel->id;
        $cdr = $frModel->cdrProcessing()->value('id');
        $version = DB::table('ib39_cdr_document_versions')->insertGetId(['cdr_processing_id' => $cdr, 'version_number' => 1,
            'source_type' => 'generated', 'storage_path' => 'x', 'original_filename' => 'x.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 1, 'sha256' => str_repeat('a', 64), 'created_by' => $user->id, 'finalized_at' => now(),
            'created_at' => now(), 'updated_at' => now()]);
        $received = now();
        $processing = JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr,
            'triggering_cdr_document_version_id' => $version, 'status' => JapicCertificationStatus::Pending,
            'received_at' => $received, 'due_at' => $received->copy()->addDays(14), 'lock_version' => 0]);

        return [$processing, $user];
    }
}
