<?php

namespace Tests\Feature;

use App\Enums\JapicCertificationStatus;
use App\Models\JapicCertificationHistory;
use App\Models\JapicCertificationProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JapicMigrationSafetyTest extends TestCase
{
    private string $originalConnection;

    private string $databaseFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'japic-migration-');
        config(['database.connections.japic_migration_safety' => [
            'driver' => 'sqlite', 'database' => $this->databaseFile, 'prefix' => '',
            'foreign_key_constraints' => true, 'busy_timeout' => 5000,
        ]]);
        DB::setDefaultConnection('japic_migration_safety');
        DB::purge('japic_migration_safety');
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (! str_contains($file, '2026_09_09_000001') && ! str_contains($file, '2026_09_09_000002')
                && ! str_contains($file, '2026_09_10_000001')) {
                (require $file)->up();
            }
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect('japic_migration_safety');
        DB::setDefaultConnection($this->originalConnection);
        if (is_file($this->databaseFile)) {
            unlink($this->databaseFile);
        }
        parent::tearDown();
    }

    public function test_forward_rollback_forward_preserves_exact_users_schema_rows_and_related_objects(): void
    {
        DB::table('users')->insert(['id' => 9001, 'username' => 'japic-safety', 'name' => 'Safety User',
            'password' => '$2y$04$preserve-this-password-hash', 'role' => 'admin', 'is_active' => 0,
            'municipality_id' => null, 'gov_agency_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $before = $this->usersSnapshot();
        $migration = $this->migration();
        $migration->up();
        $after = $this->usersSnapshot();
        $this->assertSame($before['columns'], $after['columns']);
        $this->assertSame($before['indexes'], $after['indexes']);
        $this->assertSame($before['foreign_keys'], $after['foreign_keys']);
        $this->assertSame($before['rows'], $after['rows']);
        $this->assertSame($before['triggers'], $after['triggers']);
        $this->assertStringContainsString("'japic'", $after['sql']);
        $this->assertSame(0, DB::table('sqlite_master')->where('name', 'like', '__temp__%')->count());

        $migration->down();
        $rolledBack = $this->usersSnapshot();
        $this->assertSame($before, $rolledBack);
        $this->migration()->up();
        $this->assertSame($after, $this->usersSnapshot());
    }

    public function test_unconstrained_live_style_users_schema_is_an_unchanged_idempotent_no_op(): void
    {
        Schema::table('users', fn ($table) => $table->string('role_migrated_from')->nullable());
        $this->replaceUsersRoleConstraint(null);
        DB::table('users')->insert(['id' => 9002, 'username' => 'unconstrained-safety', 'name' => 'Safety User',
            'password' => '$2y$04$preserve-unconstrained-password', 'role' => 'admin', 'is_active' => 0,
            'role_migrated_from' => 'legacy-admin', 'created_at' => now(), 'updated_at' => now()]);
        $before = $this->usersSnapshot();

        $migration = $this->migration();
        $migration->up();
        $this->assertSame($before, $this->usersSnapshot());
        $this->assertTrue(Schema::hasTable('japic_certification_processings'));

        DB::table('users')->insert(['id' => 9003, 'username' => 'japic-role-proof', 'name' => 'Role Proof',
            'password' => '$2y$04$proof-only-password', 'role' => 'japic', 'is_active' => 1,
            'role_migrated_from' => null, 'created_at' => now(), 'updated_at' => now()]);
        $afterRoleInsert = $this->usersSnapshot();
        $migration->up();
        $this->assertSame($afterRoleInsert, $this->usersSnapshot());
        $this->assertSame('legacy-admin', DB::table('users')->where('id', 9002)->value('role_migrated_from'));
    }

    public function test_unrecognized_restrictive_role_constraint_refuses_before_any_change(): void
    {
        $this->replaceUsersRoleConstraint('check (length("role") > 0)');
        $before = $this->usersSnapshot();
        try {
            $this->migration()->up();
            $this->fail('An unrecognized restrictive users.role constraint was accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('unrecognized restrictive', $exception->getMessage());
        }
        $this->assertSame($before, $this->usersSnapshot());
        $this->assertFalse(Schema::hasTable('japic_certification_processings'));
    }

    public function test_empty_divergent_table_is_reconciled_and_repeated_forward_is_idempotent(): void
    {
        Schema::create('japic_certification_processings', fn ($t) => $t->id());
        $migration = $this->migration();
        $migration->up();
        $migration->up();
        $this->assertTrue(Schema::hasColumns('japic_certification_processings',
            ['ib39_surfaced_former_rebel_id', 'status', 'received_at', 'due_at', 'current_final_version_id']));
        foreach (['japic_processing_status_insert', 'japic_processing_status_update',
            'japic_current_document_insert', 'japic_current_document_update'] as $trigger) {
            $this->assertSame(1, DB::table('sqlite_master')->where('type', 'trigger')->where('name', $trigger)->count());
        }
    }

    public function test_populated_incompatible_table_refuses_before_any_change(): void
    {
        Schema::create('japic_certification_processings', fn ($t) => $t->id());
        DB::table('japic_certification_processings')->insert(['id' => 1]);
        $users = $this->usersSnapshot();
        try {
            $this->migration()->up();
            $this->fail('Populated incompatible JAPIC table was accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('populated incompatible', $exception->getMessage());
        }
        $this->assertSame($users, $this->usersSnapshot());
        $this->assertFalse(Schema::hasTable('japic_certification_drafts'));
    }

    public function test_backfill_retry_creates_one_processing_and_one_history_from_authoritative_completion(): void
    {
        $this->eligibleCdr();
        $migration = $this->migration();
        $migration->up();
        $migration->up();
        $processing = DB::table('japic_certification_processings')->sole();
        $this->assertSame('2026-09-01 10:11:12', $processing->received_at);
        $this->assertSame('2026-09-15 10:11:12', $processing->due_at);
        $this->assertSame(1, DB::table('japic_certification_processings')->count());
        $this->assertSame(1, DB::table('japic_certification_histories')->where('event', 'intake_backfilled')->count());
    }

    public function test_rollback_refuses_before_losing_japic_data_or_users(): void
    {
        $this->eligibleCdr();
        $migration = $this->migration();
        $migration->up();
        try {
            $migration->down();
            $this->fail('Rollback accepted populated JAPIC certification records.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('certification records', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('japic_certification_processings'));

        DB::table('japic_certification_histories')->delete();
        DB::table('japic_certification_processings')->delete();
        DB::table('users')->insert(['id' => 2, 'username' => 'japic-rollback', 'name' => 'JAPIC Rollback',
            'password' => 'preserved-hash', 'role' => 'japic', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        try {
            $migration->down();
            $this->fail('Rollback accepted an existing JAPIC user.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('JAPIC user roles', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('japic_certification_processings'));
    }

    public function test_fresh_foundation_is_canonical_and_repair_is_an_idempotent_no_op(): void
    {
        $this->migration()->up();
        $before = $this->japicSchemaSnapshot();
        $this->assertCanonicalJapicSchema();
        $this->repairMigration()->up();
        $this->repairMigration()->up();
        $this->assertSame($before, $this->japicSchemaSnapshot());
    }

    public function test_repair_replaces_empty_mixed_legacy_schema_and_canonical_models_can_insert(): void
    {
        $this->createLegacyJapicTables();
        $this->repairMigration()->up();
        $this->assertCanonicalJapicSchema();
        $this->assertNotContains('japic_certification_processing_id',
            Schema::getColumnListing('japic_certification_histories'));
        $this->assertSame(['processing_id', 'occurred_at'],
            $this->indexColumns('japic_certification_histories', 'japic_history_processing_occurred_index'));

        $this->eligibleCdr();
        $received = now()->startOfSecond();
        $processing = JapicCertificationProcessing::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => 1, 'triggering_cdr_document_version_id' => 1,
            'status' => JapicCertificationStatus::Pending, 'received_at' => $received,
            'due_at' => $received->copy()->addDays(14), 'lock_version' => 0,
        ]);
        JapicCertificationHistory::query()->forceCreate([
            'processing_id' => $processing->id, 'from_status' => null,
            'to_status' => JapicCertificationStatus::Pending, 'event' => 'intake_created',
            'occurred_at' => $received,
        ]);
        $this->assertDatabaseCount('japic_certification_processings', 1);
        $this->assertDatabaseCount('japic_certification_histories', 1);
    }

    public function test_repair_refuses_populated_incompatible_set_before_any_mutation(): void
    {
        $this->createLegacyJapicTables();
        DB::table('japic_certification_processings')->insert(['id' => 1, 'status' => 'Pending']);
        $before = $this->japicSchemaSnapshot(true);
        try {
            $this->repairMigration()->up();
            $this->fail('Repair accepted a populated incompatible JAPIC table.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('japic_certification_processings', $exception->getMessage());
        }
        $this->assertSame($before, $this->japicSchemaSnapshot(true));
    }

    public function test_failed_repair_is_atomic_on_sqlite(): void
    {
        $this->createLegacyJapicTables();
        DB::statement('CREATE INDEX japic_processing_fr_unique ON municipalities (name)');
        $before = $this->japicSchemaSnapshot();
        try {
            $this->repairMigration()->up();
            $this->fail('Forced index collision did not fail the repair.');
        } catch (\Throwable) {
            $this->assertSame($before, $this->japicSchemaSnapshot());
        }
    }

    public function test_repair_down_retains_empty_schema_and_refuses_populated_schema(): void
    {
        $this->migration()->up();
        $before = $this->japicSchemaSnapshot();
        $this->repairMigration()->down();
        $this->assertSame($before, $this->japicSchemaSnapshot());

        $this->eligibleCdr();
        $this->migration()->up();
        try {
            $this->repairMigration()->down();
            $this->fail('Repair rollback accepted populated certification data.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('contains certification records', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('japic_certification_processings'));
    }

    private function eligibleCdr(): void
    {
        $time = '2026-09-01 10:11:12';
        DB::table('users')->insert(['id' => 1, 'username' => 'backfill-user', 'name' => 'Backfill User',
            'password' => 'preserved-hash', 'role' => '39th_ib', 'is_active' => 1, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('municipalities')->insert(['id' => 1, 'name' => 'Backfill Municipality', 'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_surfaced_former_rebels')->insert(['id' => 1, 'reference_number' => 'BACKFILL-1',
            'first_name' => 'Backfill', 'last_name' => 'Eligible', 'category' => 'Regular Member',
            'province' => 'Davao del Sur', 'municipality_id' => 1, 'surfaced_at' => '2026-08-01',
            'possessed_firearms' => 0, 'created_by' => 1, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_cdr_processings')->insert(['id' => 1, 'ib39_surfaced_former_rebel_id' => 1,
            'status' => 'Completed', 'completed_at' => $time, 'completed_by' => 1, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_cdr_document_versions')->insert(['id' => 1, 'cdr_processing_id' => 1, 'version_number' => 1,
            'source_type' => 'generated', 'storage_path' => 'x', 'original_filename' => 'x.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('d', 64),
            'created_by' => 1, 'finalized_at' => '2026-08-31 10:11:12', 'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_cdr_processings')->where('id', 1)->update(['current_final_version_id' => 1]);
    }

    private function usersSnapshot(): array
    {
        return json_decode(json_encode([
            'sql' => DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->value('sql'),
            'columns' => DB::select("PRAGMA table_info('users')"), 'indexes' => DB::select("PRAGMA index_list('users')"),
            'foreign_keys' => DB::select("PRAGMA foreign_key_list('users')"),
            'referencing_foreign_keys' => $this->foreignKeysReferencingUsers(),
            'triggers' => DB::table('sqlite_master')->where('type', 'trigger')->where('sql', 'like', '%users%')->orderBy('name')->get()->all(),
            'rows' => DB::table('users')->orderBy('id')->get()->all(),
        ]), true, flags: JSON_THROW_ON_ERROR);
    }

    private function replaceUsersRoleConstraint(?string $replacement): void
    {
        $definition = DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->value('sql');
        $changed = preg_replace('/check\s*\(\s*["\x60]?role["\x60]?\s+in\s*\([^)]*\)\s*\)/i', $replacement ?? '', $definition, 1, $count);
        $this->assertSame(1, $count);
        $version = (int) DB::selectOne('PRAGMA schema_version')->schema_version;
        DB::unprepared('PRAGMA writable_schema = ON');
        DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->update(['sql' => $changed]);
        DB::unprepared('PRAGMA writable_schema = OFF');
        DB::unprepared('PRAGMA schema_version = '.($version + 1));
    }

    private function foreignKeysReferencingUsers(): array
    {
        $references = [];
        foreach (DB::table('sqlite_master')->where('type', 'table')->pluck('name') as $table) {
            if (str_starts_with($table, 'japic_')) {
                continue;
            }
            foreach (DB::select("PRAGMA foreign_key_list('{$table}')") as $key) {
                if ($key->table === 'users') {
                    $references[] = [$table, (array) $key];
                }
            }
        }

        return $references;
    }

    private function createLegacyJapicTables(): void
    {
        Schema::create('japic_certification_processings', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('ib39_surfaced_former_rebel_id')->nullable();
            $table->string('status')->nullable();
            $table->date('received_on')->nullable();
        });
        Schema::create('japic_certification_drafts', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('japic_certification_processing_id')->nullable();
            $table->text('content')->nullable();
        });
        Schema::create('japic_certification_draft_histories', fn ($table) => $table->id());
        Schema::create('japic_certification_histories', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('japic_certification_processing_id')->nullable();
            $table->string('status')->nullable();
        });
        Schema::create('japic_certification_document_versions', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('japic_certification_processing_id')->nullable();
            $table->unsignedInteger('version_number')->nullable();
        });
        Schema::table('japic_certification_histories',
            fn ($table) => $table->index(['status'], 'japic_history_processing_occurred_index'));
    }

    private function assertCanonicalJapicSchema(): void
    {
        $this->assertSame(['id', 'processing_id', 'actor_id', 'from_status', 'to_status', 'event', 'remarks',
            'delay_reason', 'document_version_id', 'metadata', 'occurred_at'],
            Schema::getColumnListing('japic_certification_histories'));
        $this->assertSame(['processing_id', 'occurred_at'],
            $this->indexColumns('japic_certification_histories', 'japic_history_processing_occurred_index'));
        $this->assertTrue(collect(Schema::getIndexes('japic_certification_processings'))
            ->contains(fn (array $index): bool => $index['columns'] === ['ib39_surfaced_former_rebel_id'] && $index['unique']));
        foreach ([
            ['japic_certification_processings', 'current_final_version_id', 'japic_certification_document_versions'],
            ['japic_certification_histories', 'processing_id', 'japic_certification_processings'],
            ['japic_certification_histories', 'document_version_id', 'japic_certification_document_versions'],
            ['japic_certification_document_versions', 'processing_id', 'japic_certification_processings'],
        ] as [$table, $column, $target]) {
            $this->assertTrue(collect(Schema::getForeignKeys($table))->contains(fn (array $key): bool => $key['columns'] === [$column] && $key['foreign_table'] === $target && $key['foreign_columns'] === ['id']));
        }
        $this->assertSame(6, DB::table('sqlite_master')->where('type', 'trigger')
            ->where('name', 'like', 'japic_%')->count());
    }

    private function indexColumns(string $table, string $name): array
    {
        $index = collect(Schema::getIndexes($table))->firstWhere('name', $name);

        return $index['columns'] ?? [];
    }

    private function japicSchemaSnapshot(bool $withRows = false): array
    {
        $snapshot = [];
        foreach (['japic_certification_processings', 'japic_certification_drafts',
            'japic_certification_draft_histories', 'japic_certification_histories',
            'japic_certification_document_versions'] as $table) {
            $snapshot[$table] = [
                'sql' => DB::table('sqlite_master')->where('type', 'table')->where('name', $table)->value('sql'),
                'indexes' => Schema::getIndexes($table),
                'foreign_keys' => Schema::getForeignKeys($table),
                'rows' => $withRows ? DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all() : [],
            ];
        }
        $snapshot['triggers'] = DB::table('sqlite_master')->where('type', 'trigger')
            ->where('name', 'like', 'japic_%')->orderBy('name')->get()->map(fn ($row) => (array) $row)->all();

        return json_decode(json_encode($snapshot), true, flags: JSON_THROW_ON_ERROR);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_09_000001_create_or_reconcile_japic_certification_workflow.php');
    }

    private function repairMigration(): object
    {
        return require database_path('migrations/2026_09_09_000002_repair_empty_legacy_japic_certification_tables.php');
    }
}
