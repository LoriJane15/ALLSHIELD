<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class Ib39CancellationStatusMigrationSafetyTest extends TestCase
{
    private string $connectionName = 'cancellation_migration_safety';

    private string $databasePath;

    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        $path = tempnam(sys_get_temp_dir(), 'shield_cancellation_');
        $this->assertNotFalse($path);
        $this->databasePath = $path;
        $this->assertNotSame(realpath(database_path('database.sqlite')), realpath($this->databasePath));

        config(["database.connections.{$this->connectionName}" => [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
        ]]);
        DB::purge($this->connectionName);
        DB::setDefaultConnection($this->connectionName);
        DB::connection()->unprepared('PRAGMA foreign_keys = ON');
        $this->createRecognizedSchema();
    }

    protected function tearDown(): void
    {
        DB::disconnect($this->connectionName);
        DB::setDefaultConnection($this->originalConnection);
        @unlink($this->databasePath);

        parent::tearDown();
    }

    public function test_forward_preserves_schema_data_foreign_keys_sequence_and_other_tables(): void
    {
        foreach (['Newly Recorded', 'CDR Ongoing', 'CDR Completed'] as $offset => $status) {
            $this->insertParents($offset + 1);
            $this->insertCancellation($offset + 1, $status);
        }
        $this->insertParents(4);
        $this->insertCancellation(4, 'CDR Completed');
        DB::table('ib39_fr_cancellations')->where('id', 4)->delete();
        $before = $this->snapshot();

        $this->migration()->up();

        $after = $this->snapshot();
        $this->assertSame($before['columns'], $after['columns']);
        $this->assertSame($before['foreign_keys'], $after['foreign_keys']);
        $this->assertSame($before['indexes'], $after['indexes']);
        $this->assertSame($before['triggers'], $after['triggers']);
        $this->assertSame($before['rows'], $after['rows']);
        $this->assertSame($before['sequence'], $after['sequence']);
        $this->assertSame($before['other_table'], $after['other_table']);
        $this->assertSame(1, $this->foreignKeysEnabled());
        $this->assertStringContainsString("'JAPIC Certified'", $after['table_sql']);
        $this->assertSame(0, $this->temporaryTableCount());

        foreach (['Newly Recorded', 'CDR Ongoing', 'CDR Completed', 'JAPIC Certified'] as $offset => $status) {
            $id = $offset + 10;
            $this->insertParents($id);
            $this->insertCancellation($id, $status);
        }
        $this->assertSame(8, DB::table('ib39_fr_cancellations')->max('id'));
        $this->insertParents(20);
        try {
            $this->insertCancellation(20, 'Invalid Status');
            $this->fail('The expanded constraint accepted an invalid status.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('CHECK constraint failed', $exception->getMessage());
        }
        $this->assertSame(1, $this->foreignKeysEnabled());
    }

    public function test_repeated_forward_and_an_already_expanded_schema_are_no_ops(): void
    {
        $this->insertParents(1);
        $this->insertCancellation(1, 'CDR Completed');
        $this->migration()->up();
        $expanded = $this->snapshot();

        $this->migration()->up();

        $this->assertSame($expanded, $this->snapshot());
        $this->assertSame(1, $this->foreignKeysEnabled());
        $this->assertSame(0, $this->temporaryTableCount());
    }

    public function test_unrecognized_and_missing_column_schemas_refuse_before_changes(): void
    {
        $unrecognizedSql = str_replace(
            "'CDR Completed'))",
            "'CDR Completed', 'Unexpected'))",
            $this->originalTableSql(),
        );
        $this->replaceCancellationTable($unrecognizedSql);
        $before = $this->completeDatabaseSnapshot();
        $this->assertMigrationRefusesWithoutChanges($before);

        $conflictingSql = str_replace(
            "')) not null, \"reason\"",
            "')) check (\"previous_overall_status\" <> 'Unexpected') not null, \"reason\"",
            $this->originalTableSql(),
        );
        $this->replaceCancellationTable($conflictingSql);
        $before = $this->completeDatabaseSnapshot();
        $this->assertMigrationRefusesWithoutChanges($before);

        $this->replaceCancellationTable(str_replace('"updated_at" datetime, ', '', $this->originalTableSql()));
        $before = $this->completeDatabaseSnapshot();
        $this->assertMigrationRefusesWithoutChanges($before);
    }

    public function test_forward_rollback_forward_and_conservative_rollback_refusal(): void
    {
        $this->insertParents(1);
        $this->insertCancellation(1, 'CDR Completed');

        $this->migration()->up();
        $this->migration()->down();
        $this->assertStringNotContainsString('JAPIC Certified', $this->currentTableSql());
        $this->migration()->up();
        $this->assertStringContainsString('JAPIC Certified', $this->currentTableSql());

        $this->insertParents(2);
        $this->insertCancellation(2, 'JAPIC Certified');
        $before = $this->completeDatabaseSnapshot();
        try {
            $this->migration()->down();
            $this->fail('Rollback accepted a JAPIC Certified cancellation row.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Rollback refused', $exception->getMessage());
        }
        $this->assertSame($before, $this->completeDatabaseSnapshot());
        $this->assertSame(1, $this->foreignKeysEnabled());
        $this->assertSame(0, $this->temporaryTableCount());
    }

    public function test_rebuild_failure_rolls_back_schema_rows_and_temporary_objects(): void
    {
        DB::connection()->unprepared('PRAGMA foreign_keys = OFF');
        DB::table('ib39_fr_cancellations')->insert([
            'ib39_surfaced_former_rebel_id' => 999,
            'previous_overall_status' => 'CDR Completed',
            'reason' => 'orphaned disposable row',
            'cancelled_by' => 999,
            'cancelled_at' => '2026-09-11 00:00:00',
            'created_at' => '2026-09-11 00:00:00',
            'updated_at' => '2026-09-11 00:00:00',
        ]);
        DB::connection()->unprepared('PRAGMA foreign_keys = ON');
        $before = $this->completeDatabaseSnapshot();

        try {
            $this->migration()->up();
            $this->fail('The rebuild accepted a foreign-key violation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Foreign-key validation failed', $exception->getMessage());
        }

        $this->assertSame($before, $this->completeDatabaseSnapshot());
        $this->assertSame(1, $this->foreignKeysEnabled());
        $this->assertSame(0, $this->temporaryTableCount());
    }

    private function createRecognizedSchema(): void
    {
        DB::unprepared('CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null)');
        DB::unprepared('CREATE TABLE "ib39_surfaced_former_rebels" ("id" integer primary key autoincrement not null, "reference_number" varchar not null)');
        DB::unprepared('CREATE TABLE "migration_probe" ("id" integer primary key autoincrement not null, "cancellation_id" integer not null, "event" varchar not null)');
        DB::unprepared($this->originalTableSql());
        DB::unprepared('CREATE UNIQUE INDEX "ib39_fr_cancellations_ib39_surfaced_former_rebel_id_unique" on "ib39_fr_cancellations" ("ib39_surfaced_former_rebel_id")');
        DB::unprepared('CREATE INDEX "ib39_fr_cancellations_cancelled_at_index" on "ib39_fr_cancellations" ("cancelled_at")');
        DB::unprepared('CREATE TRIGGER "ib39_fr_cancellations_probe" AFTER INSERT ON "ib39_fr_cancellations" BEGIN INSERT INTO "migration_probe" ("cancellation_id", "event") VALUES (NEW."id", \'created\'); END');
        DB::table('migration_probe')->insert(['cancellation_id' => 0, 'event' => 'preexisting']);
    }

    private function originalTableSql(): string
    {
        return <<<'SQL'
CREATE TABLE "ib39_fr_cancellations" ("id" integer primary key autoincrement not null, "ib39_surfaced_former_rebel_id" integer not null, "previous_overall_status" varchar check ("previous_overall_status" in ('Newly Recorded', 'CDR Ongoing', 'CDR Completed')) not null, "reason" text not null, "cancelled_by" integer not null, "cancelled_at" datetime not null, "created_at" datetime, "updated_at" datetime, foreign key("ib39_surfaced_former_rebel_id") references "ib39_surfaced_former_rebels"("id") on delete restrict, foreign key("cancelled_by") references "users"("id") on delete restrict)
SQL;
    }

    private function insertParents(int $id): void
    {
        DB::table('users')->insertOrIgnore(['id' => $id, 'name' => "User {$id}"]);
        DB::table('ib39_surfaced_former_rebels')->insertOrIgnore(['id' => $id, 'reference_number' => "FR-{$id}"]);
    }

    private function insertCancellation(int $parentId, string $status): void
    {
        DB::table('ib39_fr_cancellations')->insert([
            'ib39_surfaced_former_rebel_id' => $parentId,
            'previous_overall_status' => $status,
            'reason' => "reason-{$parentId}",
            'cancelled_by' => $parentId,
            'cancelled_at' => "2026-09-11 00:00:{$parentId}",
            'created_at' => "2026-09-11 01:00:{$parentId}",
            'updated_at' => "2026-09-11 02:00:{$parentId}",
        ]);
    }

    private function replaceCancellationTable(string $sql): void
    {
        DB::connection()->unprepared('PRAGMA foreign_keys = OFF');
        DB::unprepared('DROP TABLE "ib39_fr_cancellations"');
        DB::unprepared($sql);
        DB::unprepared('CREATE UNIQUE INDEX "ib39_fr_cancellations_ib39_surfaced_former_rebel_id_unique" on "ib39_fr_cancellations" ("ib39_surfaced_former_rebel_id")');
        DB::unprepared('CREATE INDEX "ib39_fr_cancellations_cancelled_at_index" on "ib39_fr_cancellations" ("cancelled_at")');
        DB::connection()->unprepared('PRAGMA foreign_keys = ON');
    }

    private function assertMigrationRefusesWithoutChanges(array $before): void
    {
        try {
            $this->migration()->up();
            $this->fail('An unrecognized cancellation schema was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('recognized', $exception->getMessage());
        }
        $this->assertSame($before, $this->completeDatabaseSnapshot());
        $this->assertSame(1, $this->foreignKeysEnabled());
        $this->assertSame(0, $this->temporaryTableCount());
    }

    private function snapshot(): array
    {
        return [
            'table_sql' => $this->currentTableSql(),
            'columns' => array_map(fn (object $column): array => (array) $column, DB::select('PRAGMA table_info("ib39_fr_cancellations")')),
            'foreign_keys' => array_map(fn (object $foreignKey): array => (array) $foreignKey, DB::select('PRAGMA foreign_key_list("ib39_fr_cancellations")')),
            'indexes' => DB::table('sqlite_master')->where('tbl_name', 'ib39_fr_cancellations')->where('type', 'index')->orderBy('name')->get(['type', 'name', 'tbl_name', 'sql'])->map(fn (object $row): array => (array) $row)->all(),
            'triggers' => DB::table('sqlite_master')->where('tbl_name', 'ib39_fr_cancellations')->where('type', 'trigger')->orderBy('name')->get(['type', 'name', 'tbl_name', 'sql'])->map(fn (object $row): array => (array) $row)->all(),
            'rows' => DB::table('ib39_fr_cancellations')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'sequence' => DB::table('sqlite_sequence')->where('name', 'ib39_fr_cancellations')->value('seq'),
            'other_table' => DB::table('migration_probe')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ];
    }

    private function completeDatabaseSnapshot(): array
    {
        return [
            'master' => DB::table('sqlite_master')->whereNotLike('name', 'sqlite_%')->orderBy('type')->orderBy('name')->get()->map(fn (object $row): array => (array) $row)->all(),
            'cancellations' => DB::table('ib39_fr_cancellations')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'probe' => DB::table('migration_probe')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'sequences' => DB::table('sqlite_sequence')->orderBy('name')->get()->map(fn (object $row): array => (array) $row)->all(),
        ];
    }

    private function currentTableSql(): string
    {
        return (string) DB::table('sqlite_master')->where('type', 'table')->where('name', 'ib39_fr_cancellations')->value('sql');
    }

    private function foreignKeysEnabled(): int
    {
        return (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;
    }

    private function temporaryTableCount(): int
    {
        return DB::table('sqlite_master')->where('name', '__temp__ib39_fr_cancellations_status')->count();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_11_000001_allow_japic_certified_cancellation_previous_status.php');
    }
}
