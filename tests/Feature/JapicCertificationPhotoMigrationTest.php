<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class JapicCertificationPhotoMigrationTest extends TestCase
{
    private string $originalConnection;

    private string $databaseFile;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->originalConnection = DB::getDefaultConnection();
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'japic-photo-migration-');
        config(['database.connections.japic_photo_migration' => [
            'driver' => 'sqlite', 'database' => $this->databaseFile, 'prefix' => '',
            'foreign_key_constraints' => true, 'busy_timeout' => 5000,
        ]]);
        DB::setDefaultConnection('japic_photo_migration');
        DB::purge('japic_photo_migration');
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (! str_contains($file, '2026_09_10_000001')) {
                (require $file)->up();
            }
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect('japic_photo_migration');
        DB::setDefaultConnection($this->originalConnection);
        if (is_file($this->databaseFile)) {
            unlink($this->databaseFile);
        }
        parent::tearDown();
    }

    public function test_forward_rollback_forward_and_repeated_forward_are_safe_and_idempotent(): void
    {
        $migration = $this->migration();
        $beforeProcessings = $this->processingSnapshot();
        $migration->up();
        $canonical = $this->schemaSnapshot();
        $migration->up();
        $this->assertSame($canonical, $this->schemaSnapshot());
        $this->assertSame($beforeProcessings['rows'], $this->processingSnapshot()['rows']);

        $migration->down();
        $this->assertFalse(Schema::hasTable('japic_certification_photo_versions'));
        $this->assertFalse(Schema::hasColumn('japic_certification_processings', 'current_photo_version_id'));
        $this->migration()->up();
        $this->assertSame($canonical, $this->schemaSnapshot());
    }

    public function test_pretend_emits_only_the_fresh_japic_photo_schema(): void
    {
        $queries = DB::connection()->pretend(fn () => $this->migration()->up());
        $sql = strtolower(collect($queries)->pluck('query')->implode("\n"));

        $this->assertStringContainsString('create table "japic_certification_photo_versions"', $sql);
        $this->assertStringContainsString('alter table "japic_certification_processings" add column "current_photo_version_id"', $sql);
        $this->assertStringContainsString('japic_processing_current_photo_index', $sql);
        $this->assertStringContainsString('japic_photo_version_update_guard', $sql);
        $this->assertStringContainsString('japic_current_photo_owner_update', $sql);
        $this->assertDoesNotMatchRegularExpression('/^(drop|update|delete|insert)\b|\brename\b/m', $sql);
        $this->assertDoesNotMatchRegularExpression('/(?:alter|create|drop) table ["`]?\b(users|rcsp|ib39_cdr|ib39_fea|assistance|pswdo)/', $sql);
    }

    public function test_empty_partial_objects_are_reconciled_but_populated_incompatible_objects_refuse_before_mutation(): void
    {
        Schema::create('japic_certification_photo_versions', fn ($table) => $table->id());
        $this->migration()->up();
        $this->assertTrue(Schema::hasColumns('japic_certification_photo_versions', [
            'processing_id', 'version_number', 'storage_path', 'sha256', 'uploaded_by', 'uploaded_at',
        ]));

        $this->migration()->down();
        Schema::create('japic_certification_photo_versions', fn ($table) => $table->id());
        DB::table('japic_certification_photo_versions')->insert(['id' => 99]);
        $before = DB::table('sqlite_master')->where('name', 'japic_certification_photo_versions')->value('sql');
        try {
            $this->migration()->up();
            $this->fail('A populated incompatible photo table was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('populated and incompatible', $exception->getMessage());
        }
        $this->assertSame($before, DB::table('sqlite_master')->where('name', 'japic_certification_photo_versions')->value('sql'));
        $this->assertSame(99, DB::table('japic_certification_photo_versions')->value('id'));
    }

    public function test_rollback_refuses_before_ddl_when_photo_rows_exist(): void
    {
        $this->migration()->up();
        $ids = $this->foundation();
        $this->insertPhoto($ids);

        $this->assertRollbackRefused();
        $this->assertTrue(Schema::hasTable('japic_certification_photo_versions'));
        $this->assertSame(1, DB::table('japic_certification_photo_versions')->count());
    }

    public function test_rollback_refuses_before_ddl_when_a_current_photo_pointer_exists(): void
    {
        $this->migration()->up();
        $ids = $this->foundation();

        // Build a pointer-only legacy state so this guard is verified independently.
        DB::unprepared('DROP TRIGGER japic_current_photo_owner_update');
        DB::table('japic_certification_processings')->where('id', $ids['processing'])->update(['current_photo_version_id' => 999]);

        $this->assertRollbackRefused();
        $this->assertTrue(Schema::hasTable('japic_certification_photo_versions'));
        $this->assertSame(999, DB::table('japic_certification_processings')->where('id', $ids['processing'])->value('current_photo_version_id'));
    }

    public function test_rollback_refuses_before_ddl_when_managed_photo_files_exist(): void
    {
        $this->migration()->up();
        Storage::disk('local')->put('japic/certifications/1/photos/a.png', 'x');

        $this->assertRollbackRefused();
        $this->assertTrue(Schema::hasTable('japic_certification_photo_versions'));
        Storage::disk('local')->assertExists('japic/certifications/1/photos/a.png');
    }

    private function insertPhoto(array $ids): void
    {
        DB::table('japic_certification_photo_versions')->insert([
            'id' => 1, 'processing_id' => $ids['processing'], 'version_number' => 1, 'replaces_version_id' => null,
            'storage_path' => 'japic/certifications/1/photos/a.png', 'original_filename' => 'a.png', 'mime_type' => 'image/png',
            'size_bytes' => 1, 'width' => 100, 'height' => 100, 'sha256' => str_repeat('a', 64),
            'uploaded_by' => $ids['user'], 'uploaded_at' => now(),
        ]);
    }

    private function assertRollbackRefused(): void
    {
        try {
            $this->migration()->down();
            $this->fail('Rollback accepted protected photo state.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rollback refused', $exception->getMessage());
        }
    }

    private function foundation(): array
    {
        $time = now();
        $user = DB::table('users')->insertGetId(['username' => 'photo-migration', 'name' => 'Photo Migration', 'password' => 'hash',
            'role' => 'japic', 'is_active' => 1, 'created_at' => $time, 'updated_at' => $time]);
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Migration Municipality', 'created_at' => $time, 'updated_at' => $time]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => 'PHOTO-MIGRATION', 'first_name' => 'Photo', 'last_name' => 'Migration',
            'category' => 'Regular Member', 'province' => 'Davao del Sur', 'municipality_id' => $municipality, 'surfaced_at' => '2026-09-01',
            'possessed_firearms' => 0, 'created_by' => $user, 'created_at' => $time, 'updated_at' => $time]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed',
            'completed_at' => $time, 'completed_by' => $user, 'created_at' => $time, 'updated_at' => $time]);
        $version = DB::table('ib39_cdr_document_versions')->insertGetId(['cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'generated',
            'storage_path' => 'generated/migration', 'original_filename' => 'migration.html', 'mime_type' => 'text/html', 'size_bytes' => 1,
            'sha256' => str_repeat('b', 64), 'created_by' => $user, 'finalized_at' => $time, 'created_at' => $time, 'updated_at' => $time]);
        $processing = DB::table('japic_certification_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr,
            'triggering_cdr_document_version_id' => $version, 'status' => 'Pending', 'received_at' => $time,
            'due_at' => $time->copy()->addDays(14), 'lock_version' => 0, 'created_at' => $time, 'updated_at' => $time]);

        return compact('user', 'processing');
    }

    private function processingSnapshot(): array
    {
        return ['columns' => Schema::getColumns('japic_certification_processings'),
            'rows' => DB::table('japic_certification_processings')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
    }

    private function schemaSnapshot(): array
    {
        return json_decode(json_encode([
            'photo_sql' => DB::table('sqlite_master')->where('type', 'table')->where('name', 'japic_certification_photo_versions')->value('sql'),
            'photo_indexes' => Schema::getIndexes('japic_certification_photo_versions'),
            'photo_foreign_keys' => Schema::getForeignKeys('japic_certification_photo_versions'),
            'processing_columns' => Schema::getColumns('japic_certification_processings'),
            'processing_indexes' => Schema::getIndexes('japic_certification_processings'),
            'triggers' => DB::table('sqlite_master')->where('type', 'trigger')->where('name', 'like', 'japic_%photo%')->orderBy('name')->get()->all(),
        ]), true, flags: JSON_THROW_ON_ERROR);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_10_000001_add_japic_certification_photos.php');
    }
}
