<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RcspMigrationSafetyTest extends TestCase
{
    private string $originalConnection;

    private string $databaseFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'rcsp-migration-');
        config(['database.connections.rcsp_migration_safety' => [
            'driver' => 'sqlite', 'database' => $this->databaseFile, 'prefix' => '',
            'foreign_key_constraints' => true, 'busy_timeout' => 5000,
        ]]);
        DB::setDefaultConnection('rcsp_migration_safety');
        DB::purge('rcsp_migration_safety');
        $this->migrateBaseSchema();
    }

    protected function tearDown(): void
    {
        DB::disconnect('rcsp_migration_safety');
        DB::setDefaultConnection($this->originalConnection);
        if (is_file($this->databaseFile)) {
            unlink($this->databaseFile);
        }
        parent::tearDown();
    }

    public function test_sqlite_forward_rollback_forward_preserves_original_schema_rows_and_integrity(): void
    {
        $this->insertRepresentativeRows();
        $before = $this->originalSnapshot();
        $migration = $this->migration();

        $migration->up();
        $this->assertSame($before, $this->originalSnapshot());
        $this->assertTrue(Schema::hasColumns('rcsp_forms', ['reviewed_by_user_id', 'reviewed_at']));
        $this->assertTrue(Schema::hasColumn('rcsp_phases', 'catalog_key'));
        $this->assertTrue(Schema::hasColumn('rcsp_barangays', 'catalog_key'));
        $this->assertDatabaseObjectExists('index', 'rcsp_phases_catalog_number_unique');
        $this->assertDatabaseObjectExists('index', 'rcsp_barangays_barangay_unique');
        $this->assertDatabaseObjectExists('trigger', 'rcsp_hardening_reviewer_insert_guard');
        $this->assertSame(0, DB::table('sqlite_master')->where('name', 'like', '__temp__%')->count());

        $migration->down();
        $this->assertSame($before, $this->originalSnapshot());
        $this->assertFalse(Schema::hasColumn('rcsp_forms', 'reviewed_by_user_id'));
        $this->assertDatabaseObjectMissing('trigger', 'rcsp_hardening_reviewer_insert_guard');

        $this->migration()->up();
        $this->assertSame($before, $this->originalSnapshot());

        DB::table('users')->insert(['id' => 2, 'username' => 'DEMO-reviewer', 'name' => 'DEMO Reviewer',
            'password' => 'not-a-real-login-hash', 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('rcsp_forms')->where('id', 1)->update(['reviewed_by_user_id' => 2, 'reviewed_at' => now()]);
        $this->assertSame(2, DB::table('rcsp_forms')->where('id', 1)->value('reviewed_by_user_id'));
        DB::table('users')->where('id', 2)->delete();
        $this->assertNull(DB::table('rcsp_forms')->where('id', 1)->value('reviewed_by_user_id'));

        $this->expectException(QueryException::class);
        DB::table('rcsp_forms')->where('id', 1)->update(['reviewed_by_user_id' => 999999]);
    }

    public function test_duplicate_barangay_guard_fails_before_any_schema_change(): void
    {
        $this->insertRepresentativeRows();
        DB::table('rcsp_barangays')->insert(['id' => 2, 'barangay_id' => 1, 'municipality_id' => 1,
            'status' => 'Pending', 'current_phase' => 0, 'created_at' => now(), 'updated_at' => now()]);

        try {
            $this->migration()->up();
            $this->fail('Migration accepted duplicate RCSP barangay records.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate', strtolower($exception->getMessage()));
        }
        $this->assertFalse(Schema::hasColumn('rcsp_phases', 'catalog_key'));
        $this->assertFalse(Schema::hasColumn('rcsp_forms', 'reviewed_by_user_id'));
        $this->assertCount(2, DB::table('rcsp_barangays')->get());
    }

    public function test_rollback_refuses_before_partial_change_when_new_fields_are_used(): void
    {
        $this->insertRepresentativeRows();
        $migration = $this->migration();
        $migration->up();
        DB::table('rcsp_phases')->where('id', 1)->update(['catalog_key' => 'rcsp-demo-v1']);
        $columns = Schema::getColumnListing('rcsp_phases');

        try {
            $migration->down();
            $this->fail('Rollback accepted populated catalog data.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('catalog', strtolower($exception->getMessage()));
        }
        $this->assertSame($columns, Schema::getColumnListing('rcsp_phases'));
        $this->assertSame('rcsp-demo-v1', DB::table('rcsp_phases')->where('id', 1)->value('catalog_key'));
        $this->assertDatabaseObjectExists('index', 'rcsp_phases_catalog_number_unique');
        $this->assertDatabaseObjectExists('trigger', 'rcsp_hardening_reviewer_insert_guard');

        DB::table('rcsp_phases')->where('id', 1)->update(['catalog_key' => null]);
        DB::table('users')->insert(['id' => 2, 'username' => 'DEMO-reviewer-rollback', 'name' => 'DEMO Reviewer',
            'password' => 'not-a-real-login-hash', 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('rcsp_forms')->where('id', 1)->update(['reviewed_by_user_id' => 2]);
        try {
            $migration->down();
            $this->fail('Rollback accepted populated reviewer ownership.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('reviewer ownership', strtolower($exception->getMessage()));
        }
        $this->assertTrue(Schema::hasColumn('rcsp_forms', 'reviewed_by_user_id'));

        DB::table('rcsp_forms')->where('id', 1)->update(['reviewed_by_user_id' => null, 'reviewed_at' => now()]);
        try {
            $migration->down();
            $this->fail('Rollback accepted a populated review timestamp.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('timestamp', strtolower($exception->getMessage()));
        }
        $this->assertTrue(Schema::hasColumn('rcsp_forms', 'reviewed_at'));
        $this->assertDatabaseObjectExists('trigger', 'rcsp_hardening_reviewer_insert_guard');
    }

    public function test_configurable_activity_migration_supports_forward_rollback_forward_and_version_two(): void
    {
        $this->insertRepresentativeRows();
        $this->migration()->up();
        $before = $this->originalSnapshot();
        $this->assertSame([], collect(DB::select("PRAGMA index_list('rcsp_forms')"))->pluck('name')->all());

        $migration = $this->configurableMigration();
        $migration->up();
        $this->assertSame($before, $this->originalSnapshot());
        $this->assertTrue(Schema::hasColumns('rcsp_forms', [
            'submission_version', 'original_filename', 'detected_mime_type', 'file_size_bytes', 'submitted_at',
        ]));
        $this->assertDatabaseObjectExists('index', 'rcsp_form_submission_version_unique');
        $this->assertDatabaseObjectExists('index', 'rcsp_activity_barangay_phase_title_unique');
        $this->assertDatabaseObjectExists('index', 'rcsp_phase_status_barangay_unique');
        $this->assertTrue(Schema::hasTable('rcsp_form_reviews'));
        $this->assertTrue(Schema::hasTable('rcsp_phase_transitions'));
        $this->assertSame(1, DB::table('rcsp_forms')->where('id', 1)->value('submission_version'));
        $this->assertSame('2026-01-02 03:04:05', DB::table('rcsp_forms')->where('id', 1)->value('submitted_at'));
        $this->assertSame(
            ['Pre-Shaping', 'Shape', 'Access', 'Transform', 'Sustain', 'Monitor'],
            DB::table('rcsp_phases')->where('catalog_key', 'lgu-configurable-v1')->orderBy('number')->pluck('name')->all()
        );
        $this->assertSame(0, DB::table('rcsp_activities')->whereIn('rcsp_phase_id',
            DB::table('rcsp_phases')->where('catalog_key', 'lgu-configurable-v1')->select('id'))->count());

        $migration->down();
        $this->assertSame($before, $this->originalSnapshot());
        $this->assertFalse(Schema::hasColumn('rcsp_forms', 'submission_version'));
        $this->assertFalse(Schema::hasTable('rcsp_form_reviews'));
        $this->assertSame(0, DB::table('rcsp_phases')->where('catalog_key', 'lgu-configurable-v1')->count());

        $this->configurableMigration()->up();
        $this->assertSame($before, $this->originalSnapshot());
        DB::table('rcsp_forms')->insert([
            'id' => 2, 'lgu_user_id' => 1, 'rcsp_barangay_id' => 1, 'rcsp_phase_id' => 1,
            'rcsp_activity_id' => 1, 'submission_version' => 2, 'conduct' => 'yes',
            'file' => 'private:rcsp/1/version-two.pdf', 'status' => 'submitted',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(2, DB::table('rcsp_forms')->where('rcsp_activity_id', 1)->count());

        try {
            DB::table('rcsp_forms')->insert([
                'lgu_user_id' => 1, 'rcsp_barangay_id' => 1, 'rcsp_phase_id' => 1,
                'rcsp_activity_id' => 1, 'submission_version' => 2, 'conduct' => 'yes',
                'status' => 'submitted', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('The migration accepted a duplicate immutable submission version.');
        } catch (QueryException) {
            $this->assertSame(2, DB::table('rcsp_forms')->where('rcsp_activity_id', 1)->count());
        }
    }

    public function test_configurable_activity_preflight_and_rollback_guards_fail_before_partial_change(): void
    {
        $this->insertRepresentativeRows();
        $this->migration()->up();
        DB::table('rcsp_phase_statuses')->insert([
            'id' => 2, 'rcsp_barangay_id' => 1, 'phase0_completed' => 0, 'phase1_completed' => 0,
            'phase2_completed' => 0, 'phase3_completed' => 0, 'phase4_completed' => 0,
            'phase5_completed' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $this->configurableMigration()->up();
            $this->fail('The migration accepted duplicate phase-status rows.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate phase-status', strtolower($exception->getMessage()));
        }
        $this->assertFalse(Schema::hasColumn('rcsp_forms', 'submission_version'));
        $this->assertFalse(Schema::hasTable('rcsp_form_reviews'));

        DB::table('rcsp_phase_statuses')->where('id', 2)->delete();
        $migration = $this->configurableMigration();
        $migration->up();
        DB::table('rcsp_forms')->where('id', 1)->update(['original_filename' => 'new-evidence.pdf']);
        try {
            $migration->down();
            $this->fail('Rollback accepted evidence metadata created by the configurable workflow.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('workflow has been used', strtolower($exception->getMessage()));
        }
        $this->assertTrue(Schema::hasColumn('rcsp_forms', 'submission_version'));
        $this->assertTrue(Schema::hasTable('rcsp_form_reviews'));
    }

    public function test_configurable_constraints_enforce_scoped_titles_transitions_and_foreign_key_actions(): void
    {
        $this->insertRepresentativeRows();
        $this->migration()->up();
        $this->configurableMigration()->up();
        DB::table('users')->insert(['id' => 2, 'username' => 'DEMO-constraint-user', 'name' => 'Constraint User',
            'password' => 'not-a-real-login-hash', 'role' => 'lgu', 'municipality_id' => 1,
            'created_at' => now(), 'updated_at' => now()]);
        $phaseIds = DB::table('rcsp_phases')->where('catalog_key', 'lgu-configurable-v1')
            ->orderBy('number')->pluck('id', 'number');
        DB::table('rcsp_activities')->insert(['id' => 2, 'rcsp_phase_id' => $phaseIds[0],
            'rcsp_barangay_id' => 1, 'created_by_user_id' => 2, 'description' => 'Scoped title',
            'normalized_title' => 'scoped title', 'created_at' => now(), 'updated_at' => now()]);

        try {
            DB::table('rcsp_activities')->insert(['rcsp_phase_id' => $phaseIds[0],
                'rcsp_barangay_id' => 1, 'created_by_user_id' => 2, 'description' => 'SCOPED TITLE',
                'normalized_title' => 'scoped title', 'created_at' => now(), 'updated_at' => now()]);
            $this->fail('The migration accepted a duplicate scoped normalized activity title.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('rcsp_activities')->where('normalized_title', 'scoped title')->count());
        }
        DB::table('rcsp_activities')->insert(['rcsp_phase_id' => $phaseIds[1],
            'rcsp_barangay_id' => 1, 'created_by_user_id' => 2, 'description' => 'Scoped title',
            'normalized_title' => 'scoped title', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('rcsp_phase_transitions')->insert(['rcsp_barangay_id' => 1, 'from_phase' => 0,
            'to_phase' => 1, 'advanced_by_user_id' => 2, 'advanced_at' => now(),
            'created_at' => now(), 'updated_at' => now()]);
        try {
            DB::table('rcsp_phase_transitions')->insert(['rcsp_barangay_id' => 1, 'from_phase' => 0,
                'to_phase' => 1, 'advanced_by_user_id' => 2, 'advanced_at' => now(),
                'created_at' => now(), 'updated_at' => now()]);
            $this->fail('The migration accepted a duplicate phase advancement.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('rcsp_phase_transitions')->count());
        }

        DB::table('rcsp_form_reviews')->insert(['rcsp_form_id' => 1, 'reviewer_user_id' => 2,
            'status' => 'approved', 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->where('id', 2)->delete();
        $this->assertNull(DB::table('rcsp_activities')->where('id', 2)->value('created_by_user_id'));
        $this->assertNull(DB::table('rcsp_phase_transitions')->value('advanced_by_user_id'));
        $this->assertNull(DB::table('rcsp_form_reviews')->value('reviewer_user_id'));

        $reviewForeign = collect(DB::select("PRAGMA foreign_key_list('rcsp_form_reviews')"))
            ->firstWhere('from', 'rcsp_form_id');
        $activityForeign = collect(DB::select("PRAGMA foreign_key_list('rcsp_activities')"))
            ->firstWhere('from', 'rcsp_barangay_id');
        $this->assertSame('CASCADE', $reviewForeign->on_delete);
        $this->assertSame('CASCADE', $activityForeign->on_delete);
    }

    private function migrateBaseSchema(): void
    {
        foreach (['0001_01_01_000000_create_users_table.php', '2025_01_01_000010_create_location_tables.php',
            '2025_01_01_000040_create_rcsp_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    private function insertRepresentativeRows(): void
    {
        $time = '2026-01-02 03:04:05';
        DB::table('users')->insert(['id' => 1, 'username' => 'DEMO-lgu', 'name' => 'DEMO LGU',
            'password' => 'not-a-real-login-hash', 'role' => 'lgu', 'municipality_id' => 1,
            'created_at' => $time, 'updated_at' => $time]);
        DB::table('municipalities')->insert(['id' => 1, 'name' => 'DEMO Migration Municipality', 'created_at' => $time, 'updated_at' => $time]);
        DB::table('barangays')->insert(['id' => 1, 'municipality_id' => 1, 'name' => 'DEMO Migration Barangay', 'created_at' => $time, 'updated_at' => $time]);
        foreach (range(0, 5) as $number) {
            DB::table('rcsp_phases')->insert(['id' => $number + 1, 'name' => "DEMO Migration Phase {$number}",
                'number' => $number, 'created_at' => $time, 'updated_at' => $time]);
        }
        DB::table('rcsp_activities')->insert(['id' => 1, 'rcsp_phase_id' => 1,
            'description' => 'DEMO: Migration activity', 'created_at' => $time, 'updated_at' => $time]);
        DB::table('rcsp_barangays')->insert(['id' => 1, 'barangay_id' => 1, 'municipality_id' => 1,
            'status' => 'Ongoing', 'current_phase' => 0, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('rcsp_phase_statuses')->insert(['id' => 1, 'rcsp_barangay_id' => 1,
            'phase0_completed' => 0, 'phase1_completed' => 0, 'phase2_completed' => 0,
            'phase3_completed' => 0, 'phase4_completed' => 0, 'phase5_completed' => 0,
            'created_at' => $time, 'updated_at' => $time]);
        DB::table('rcsp_forms')->insert(['id' => 1, 'lgu_user_id' => 1, 'rcsp_barangay_id' => 1,
            'rcsp_phase_id' => 1, 'rcsp_activity_id' => 1, 'conduct' => 'yes',
            'file' => 'rcsp/1/DEMO-evidence.pdf', 'status' => 'to be complied',
            'remarks' => 'DEMO migration remark', 'created_at' => $time, 'updated_at' => $time]);
        DB::table('rcsp_file_comments')->insert(['id' => 1, 'rcsp_form_id' => 1, 'rcsp_phase_id' => 1,
            'rcsp_activity_id' => 1, 'user_id' => 1, 'text' => 'DEMO migration comment',
            'created_at' => $time, 'updated_at' => $time]);
    }

    private function originalSnapshot(): array
    {
        $columns = [
            'rcsp_phases' => ['id', 'name', 'number', 'created_at', 'updated_at'],
            'rcsp_barangays' => ['id', 'barangay_id', 'municipality_id', 'status', 'current_phase', 'created_at', 'updated_at'],
            'rcsp_forms' => ['id', 'lgu_user_id', 'rcsp_barangay_id', 'rcsp_phase_id', 'rcsp_activity_id', 'conduct', 'file', 'status', 'remarks', 'created_at', 'updated_at'],
            'rcsp_phase_statuses' => ['*'], 'rcsp_file_comments' => ['*'],
        ];
        $snapshot = [];
        foreach ($columns as $table => $selection) {
            $query = DB::table($table)->select($selection)->orderBy('id');
            if ($table === 'rcsp_phases' && Schema::hasColumn('rcsp_phases', 'catalog_key')) {
                $query->where(fn ($phases) => $phases->whereNull('catalog_key')
                    ->orWhere('catalog_key', '!=', 'lgu-configurable-v1'));
            }
            $snapshot['rows'][$table] = $query->get()->map(fn ($row) => (array) $row)->all();
            $snapshot['columns'][$table] = collect(DB::select("PRAGMA table_info('{$table}')"))
                ->reject(fn ($column) => in_array($column->name, [
                    'catalog_key', 'reviewed_by_user_id', 'reviewed_at', 'submission_version',
                    'original_filename', 'detected_mime_type', 'file_size_bytes', 'submitted_at',
                ], true))->values()->all();
            $snapshot['indexes'][$table] = collect(DB::select("PRAGMA index_list('{$table}')"))
                ->reject(fn ($index) => in_array($index->name, [
                    'rcsp_phases_catalog_number_unique', 'rcsp_barangays_barangay_unique',
                    'rcsp_form_submission_version_unique', 'rcsp_phase_status_barangay_unique',
                ], true))->values()->all();
            $snapshot['foreign_keys'][$table] = DB::select("PRAGMA foreign_key_list('{$table}')");
        }

        return json_decode(json_encode($snapshot), true, flags: JSON_THROW_ON_ERROR);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_08_000001_harden_rcsp_workflow.php');
    }

    private function configurableMigration(): object
    {
        return require database_path('migrations/2026_09_14_000001_add_configurable_rcsp_activities_and_history.php');
    }

    private function assertDatabaseObjectExists(string $type, string $name): void
    {
        $this->assertSame(1, DB::table('sqlite_master')->where('type', $type)->where('name', $name)->count());
    }

    private function assertDatabaseObjectMissing(string $type, string $name): void
    {
        $this->assertSame(0, DB::table('sqlite_master')->where('type', $type)->where('name', $name)->count());
    }
}
