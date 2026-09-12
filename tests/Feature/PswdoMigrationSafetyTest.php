<?php

namespace Tests\Feature;

use App\Contracts\Ib39FeaReadiness;
use App\Models\Ib39SurfacedFormerRebel;
use App\Services\PswdoEnrollmentIntakeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PswdoMigrationSafetyTest extends TestCase
{
    private string $originalConnection;

    private string $databaseFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'pswdo-migration-');
        config(['database.connections.pswdo_migration_safety' => [
            'driver' => 'sqlite', 'database' => $this->databaseFile, 'prefix' => '',
            'foreign_key_constraints' => true, 'busy_timeout' => 5000,
        ]]);
        DB::setDefaultConnection('pswdo_migration_safety');
        DB::purge('pswdo_migration_safety');
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (! str_contains($file, '2026_09_11_000002')) {
                (require $file)->up();
            }
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect('pswdo_migration_safety');
        DB::setDefaultConnection($this->originalConnection);
        if (is_file($this->databaseFile)) {
            unlink($this->databaseFile);
        }
        parent::tearDown();
    }

    public function test_forward_repeat_rollback_forward_preserves_users_and_builds_exact_constraints(): void
    {
        $this->removeUsersRoleConstraint();
        DB::table('users')->insert(['id' => 8001, 'username' => 'legacy-lswdo', 'name' => 'Legacy LSWDO', 'password' => 'preserved-hash', 'role' => 'lswdo', 'is_active' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $users = DB::table('users')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertSame($users, DB::table('users')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertTrue(Schema::hasColumns('pswdo_enrollments', ['ib39_surfaced_former_rebel_id', 'lock_version']));
        $this->assertTrue(Schema::hasColumns('pswdo_enrollment_documents', ['document_type', 'storage_path', 'sha256', 'uploaded_by', 'uploaded_at']));
        $this->assertTrue(collect(Schema::getIndexes('pswdo_enrollments'))->contains(fn ($index) => $index['columns'] === ['ib39_surfaced_former_rebel_id'] && $index['unique']));
        $this->assertTrue(collect(Schema::getIndexes('pswdo_enrollment_documents'))->contains(fn ($index) => $index['columns'] === ['pswdo_enrollment_id', 'document_type'] && $index['unique']));
        $this->assertSame(2, DB::table('sqlite_master')->where('type', 'trigger')->where('name', 'like', 'pswdo_documents_no_%')->count());

        $migration->down();
        $this->assertFalse(Schema::hasTable('pswdo_enrollments'));
        $this->migration()->up();
        $this->assertTrue(Schema::hasTable('pswdo_enrollments'));
        $this->assertSame('lswdo', DB::table('users')->where('id', 8001)->value('role'));
    }

    public function test_backfill_is_idempotent_and_populated_rollback_is_refused(): void
    {
        $this->eligibleRows();
        $migration = $this->migration();
        $migration->up();
        $migration->up();
        $this->assertSame(1, DB::table('pswdo_enrollments')->count());
        $this->assertSame(1, DB::table('pswdo_enrollments')->where('ib39_surfaced_former_rebel_id', 1)->count());

        try {
            $migration->down();
            $this->fail('Populated PSWDO rollback was accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('enrollment records would be lost', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('pswdo_enrollments'));
    }

    public function test_database_guards_reject_final_document_updates_and_deletes(): void
    {
        $this->eligibleRows();
        $this->migration()->up();
        DB::table('users')->insert(['id' => 2, 'username' => 'pswdo-guard', 'name' => 'PSWDO Guard', 'password' => 'hash', 'role' => 'pswdo', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pswdo_enrollment_documents')->insert(['id' => 1, 'pswdo_enrollment_id' => 1, 'document_type' => 'eclip_enrollment_form', 'storage_path' => 'private.pdf', 'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => str_repeat('a', 64), 'uploaded_by' => 2, 'correct_document_type_confirmed' => 1, 'belongs_to_fr_confirmed' => 1, 'final_signed_confirmed' => 1, 'uploaded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        foreach (['UPDATE pswdo_enrollment_documents SET original_filename = \'changed.pdf\' WHERE id = 1', 'DELETE FROM pswdo_enrollment_documents WHERE id = 1'] as $sql) {
            try {
                DB::statement($sql);
                $this->fail('An immutable final document was mutated.');
            } catch (\Throwable $exception) {
                $this->assertStringContainsString('immutable', strtolower($exception->getMessage()));
            }
        }
        $this->assertSame('final.pdf', DB::table('pswdo_enrollment_documents')->value('original_filename'));
    }

    public function test_existing_japic_and_fea_flows_fail_closed_before_pswdo_schema_is_applied(): void
    {
        $this->eligibleRows();
        $record = Ib39SurfacedFormerRebel::query()->findOrFail(1);

        $this->assertFalse(app(Ib39FeaReadiness::class)->isReady($record));
        $this->assertNull(DB::transaction(fn () => app(PswdoEnrollmentIntakeService::class)->receiveEligible($record)));
        $this->assertFalse(Schema::hasTable('pswdo_enrollments'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_11_000002_create_pswdo_enrollment_upload_workflow.php');
    }

    private function removeUsersRoleConstraint(): void
    {
        $definition = DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->value('sql');
        $changed = preg_replace('/check\s*\(\s*["`]?role["`]?\s+in\s*\([^)]*\)\s*\)/i', '', $definition, 1, $count);
        $this->assertSame(1, $count);
        $version = (int) DB::selectOne('PRAGMA schema_version')->schema_version;
        DB::unprepared('PRAGMA writable_schema = ON');
        DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->update(['sql' => $changed]);
        DB::unprepared('PRAGMA writable_schema = OFF');
        DB::unprepared('PRAGMA schema_version = '.($version + 1));
    }

    private function eligibleRows(): void
    {
        $time = '2026-09-01 10:00:00';
        DB::table('users')->insert(['id' => 1, 'username' => 'migration-ib39', 'name' => 'Migration IB39', 'password' => 'hash', 'role' => '39th_ib', 'is_active' => 1, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('municipalities')->insert(['id' => 1, 'name' => 'Migration Municipality', 'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_surfaced_former_rebels')->insert(['id' => 1, 'reference_number' => 'PSWDO-BACKFILL', 'first_name' => 'Backfill', 'last_name' => 'Subject', 'category' => 'Regular Member', 'province' => 'Davao del Sur', 'municipality_id' => 1, 'surfaced_at' => '2026-09-01', 'possessed_firearms' => 1, 'created_by' => 1, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_cdr_processings')->insert(['id' => 1, 'ib39_surfaced_former_rebel_id' => 1, 'status' => 'Completed', 'completed_at' => $time, 'completed_by' => 1, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_cdr_document_versions')->insert(['id' => 1, 'cdr_processing_id' => 1, 'version_number' => 1, 'source_type' => 'uploaded', 'storage_path' => 'cdr.pdf', 'original_filename' => 'cdr.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => str_repeat('c', 64), 'created_by' => 1, 'finalized_at' => $time, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_cdr_processings')->where('id', 1)->update(['current_final_version_id' => 1]);
        DB::table('japic_certification_processings')->insert(['id' => 1, 'ib39_surfaced_former_rebel_id' => 1, 'triggering_cdr_document_version_id' => 1, 'status' => 'Completed', 'received_at' => $time, 'due_at' => '2026-09-15 10:00:00', 'completed_at' => $time, 'completed_by' => 1, 'lock_version' => 1, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('japic_certification_document_versions')->insert(['id' => 1, 'processing_id' => 1, 'version_number' => 1, 'storage_path' => 'japic.pdf', 'original_filename' => 'japic.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => str_repeat('j', 64), 'uploaded_by' => 1, 'all_signatories_confirmed' => 1, 'correct_final_confirmed' => 1, 'uploaded_at' => $time, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('japic_certification_processings')->where('id', 1)->update(['current_final_version_id' => 1]);
    }
}
