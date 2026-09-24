<?php

use App\Models\Ib39SurfacedFormerRebel;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    private const TABLE = 'ib39_fr_cancellations';

    private const TEMP_TABLE = '__temp__ib39_fr_cancellations_status';

    private const ORIGINAL_STATUSES = [
        Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS,
        Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_CDR_ONGOING,
        Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_CDR_COMPLETED,
    ];

    private const EXPANDED_STATUSES = [
        ...self::ORIGINAL_STATUSES,
        Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_JAPIC_CERTIFIED,
    ];

    public function up(): void
    {
        $this->reconcile(self::ORIGINAL_STATUSES, self::EXPANDED_STATUSES, false);
    }

    public function down(): void
    {
        $this->reconcile(self::EXPANDED_STATUSES, self::ORIGINAL_STATUSES, true);
    }

    private function reconcile(array $from, array $to, bool $rollback): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'pgsql') {
            if ($rollback && $connection->table(self::TABLE)
                ->where('previous_overall_status', Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_JAPIC_CERTIFIED)->exists()) {
                throw new RuntimeException('Rollback refused because a cancellation records JAPIC Certified as its previous status.');
            }
            $values = "'".implode("','", array_map(fn (string $value): string => str_replace("'", "''", $value), $to))."'";
            $connection->transaction(function () use ($connection, $values): void {
                $connection->statement('ALTER TABLE '.self::TABLE.' DROP CONSTRAINT IF EXISTS ib39_fr_cancellations_previous_overall_status_check');
                $connection->statement('ALTER TABLE '.self::TABLE." ADD CONSTRAINT ib39_fr_cancellations_previous_overall_status_check CHECK (previous_overall_status IN ({$values}))");
            });

            return;
        }
        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('This migration has been verified only for SQLite and refuses to run on other database drivers.');
        }
        if ($connection->transactionLevel() !== 0) {
            throw new RuntimeException('The cancellation status constraint cannot be rebuilt inside an existing transaction.');
        }

        $schema = $this->inspectRecognizedSchema($connection);
        if ($schema['statuses'] === $to) {
            return;
        }
        if ($schema['statuses'] !== $from) {
            throw new RuntimeException('The cancellation previous-status constraint is not a recognized compatible schema.');
        }
        if ($rollback && $connection->table(self::TABLE)
            ->where('previous_overall_status', Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_JAPIC_CERTIFIED)
            ->exists()) {
            throw new RuntimeException('Rollback refused because a cancellation records JAPIC Certified as its previous status.');
        }
        if ($this->sqliteObjectExists($connection, self::TEMP_TABLE)) {
            throw new RuntimeException('The cancellation status migration temporary table already exists.');
        }

        $foreignKeysEnabled = (int) $connection->selectOne('PRAGMA foreign_keys')->foreign_keys === 1;
        try {
            $connection->unprepared('PRAGMA foreign_keys = OFF');
            if ((int) $connection->selectOne('PRAGMA foreign_keys')->foreign_keys !== 0) {
                throw new RuntimeException('SQLite foreign-key enforcement could not be suspended safely.');
            }
            $connection->beginTransaction();
            $this->rebuild($connection, $schema, $to);
            $violations = $connection->select('PRAGMA foreign_key_check');
            if ($violations !== []) {
                throw new RuntimeException('Foreign-key validation failed after rebuilding the cancellation table.');
            }
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            throw $exception;
        } finally {
            $connection->unprepared('PRAGMA foreign_keys = '.($foreignKeysEnabled ? 'ON' : 'OFF'));
            if (((int) $connection->selectOne('PRAGMA foreign_keys')->foreign_keys === 1) !== $foreignKeysEnabled) {
                throw new RuntimeException('SQLite foreign-key enforcement could not be restored.');
            }
        }
    }

    private function rebuild(Connection $connection, array $schema, array $statuses): void
    {
        $createSql = preg_replace(
            '/^CREATE TABLE\s+["`\[]?'.preg_quote(self::TABLE, '/').'["`\]]?/i',
            'CREATE TABLE "'.self::TEMP_TABLE.'"',
            $schema['table_sql'],
            1,
            $tableReplacementCount,
        );
        $statusSql = implode(', ', array_map(fn (string $status): string => "'".str_replace("'", "''", $status)."'", $statuses));
        $createSql = preg_replace(
            $this->constraintPattern(),
            'check ("previous_overall_status" in ('.$statusSql.'))',
            $createSql,
            1,
            $constraintReplacementCount,
        );
        if ($tableReplacementCount !== 1 || $constraintReplacementCount !== 1 || ! is_string($createSql)) {
            throw new RuntimeException('The recognized cancellation table SQL could not be transformed safely.');
        }

        $connection->unprepared($createSql);
        $columns = implode(', ', array_map(fn (string $column): string => '"'.$column.'"', $schema['column_names']));
        $connection->statement('INSERT INTO "'.self::TEMP_TABLE.'" ('.$columns.') SELECT '.$columns.' FROM "'.self::TABLE.'"');
        $copiedRows = (int) $connection->table(self::TEMP_TABLE)->count();
        if ($copiedRows !== $schema['row_count'] || $this->hasLogicalDifference($connection, $columns)) {
            throw new RuntimeException('Cancellation rows were not copied exactly; the schema rebuild was aborted.');
        }

        $connection->unprepared('DROP TABLE "'.self::TABLE.'"');
        $connection->unprepared('ALTER TABLE "'.self::TEMP_TABLE.'" RENAME TO "'.self::TABLE.'"');
        foreach ($schema['objects'] as $object) {
            $connection->unprepared($object->sql);
        }
        $connection->table('sqlite_sequence')->where('name', self::TABLE)->delete();
        if ($schema['sequence'] !== null) {
            $connection->table('sqlite_sequence')->insert(['name' => self::TABLE, 'seq' => $schema['sequence']]);
        }

        $after = $this->inspectRecognizedSchema($connection);
        if ($after['statuses'] !== $statuses
            || $after['row_count'] !== $schema['row_count']
            || $after['sequence'] !== $schema['sequence']
            || $after['object_signatures'] !== $schema['object_signatures']) {
            throw new RuntimeException('Cancellation schema verification failed after reconstruction.');
        }
    }

    private function inspectRecognizedSchema(Connection $connection): array
    {
        $tableSql = $connection->table('sqlite_master')
            ->where('type', 'table')->where('name', self::TABLE)->value('sql');
        if (! is_string($tableSql)) {
            throw new RuntimeException('The cancellation table is missing.');
        }

        preg_match_all($this->constraintPattern(), $tableSql, $constraints);
        if (count($constraints[0]) !== 1) {
            throw new RuntimeException('The cancellation previous-status constraint is missing or conflicting.');
        }
        preg_match_all("/'((?:''|[^'])*)'/", $constraints['values'][0], $quotedValues);
        $remaining = preg_replace("/'(?:''|[^'])*'/", '', $constraints['values'][0]);
        if (! is_string($remaining) || trim(str_replace(',', '', $remaining)) !== '') {
            throw new RuntimeException('The cancellation previous-status constraint contains unrecognized syntax.');
        }
        $statuses = array_map(fn (string $value): string => str_replace("''", "'", $value), $quotedValues[1]);
        if ($statuses !== self::ORIGINAL_STATUSES && $statuses !== self::EXPANDED_STATUSES) {
            throw new RuntimeException('The cancellation previous-status values are not recognized.');
        }
        if ($this->normalizedSql($tableSql) !== $this->normalizedSql($this->canonicalTableSql($statuses))) {
            throw new RuntimeException('The cancellation table SQL contains an unrecognized schema variation.');
        }

        $columns = array_map(fn (object $column): array => [
            'name' => $column->name,
            'type' => strtolower($column->type),
            'notnull' => (int) $column->notnull,
            'default' => $column->dflt_value,
            'pk' => (int) $column->pk,
        ], $connection->select('PRAGMA table_info("'.self::TABLE.'")'));
        $expectedColumns = [
            ['name' => 'id', 'type' => 'integer', 'notnull' => 1, 'default' => null, 'pk' => 1],
            ['name' => 'ib39_surfaced_former_rebel_id', 'type' => 'integer', 'notnull' => 1, 'default' => null, 'pk' => 0],
            ['name' => 'previous_overall_status', 'type' => 'varchar', 'notnull' => 1, 'default' => null, 'pk' => 0],
            ['name' => 'reason', 'type' => 'text', 'notnull' => 1, 'default' => null, 'pk' => 0],
            ['name' => 'cancelled_by', 'type' => 'integer', 'notnull' => 1, 'default' => null, 'pk' => 0],
            ['name' => 'cancelled_at', 'type' => 'datetime', 'notnull' => 1, 'default' => null, 'pk' => 0],
            ['name' => 'created_at', 'type' => 'datetime', 'notnull' => 0, 'default' => null, 'pk' => 0],
            ['name' => 'updated_at', 'type' => 'datetime', 'notnull' => 0, 'default' => null, 'pk' => 0],
        ];
        if ($columns !== $expectedColumns || ! str_contains(strtolower($tableSql), 'autoincrement')) {
            throw new RuntimeException('The cancellation table columns are not the recognized canonical shape.');
        }

        $foreignKeys = collect($connection->select('PRAGMA foreign_key_list("'.self::TABLE.'")'))
            ->map(fn (object $foreignKey): array => [
                'from' => $foreignKey->from, 'table' => $foreignKey->table, 'to' => $foreignKey->to,
                'on_update' => strtoupper($foreignKey->on_update), 'on_delete' => strtoupper($foreignKey->on_delete),
                'match' => strtoupper($foreignKey->match),
            ])->sortBy('from')->values()->all();
        $expectedForeignKeys = collect([
            ['from' => 'cancelled_by', 'table' => 'users', 'to' => 'id', 'on_update' => 'NO ACTION', 'on_delete' => 'RESTRICT', 'match' => 'NONE'],
            ['from' => 'ib39_surfaced_former_rebel_id', 'table' => 'ib39_surfaced_former_rebels', 'to' => 'id', 'on_update' => 'NO ACTION', 'on_delete' => 'RESTRICT', 'match' => 'NONE'],
        ])->sortBy('from')->values()->all();
        if ($foreignKeys !== $expectedForeignKeys) {
            throw new RuntimeException('The cancellation table foreign keys are not recognized.');
        }

        $indexes = collect($connection->select('PRAGMA index_list("'.self::TABLE.'")'));
        $requiredIndexes = [
            'ib39_fr_cancellations_cancelled_at_index' => [false, ['cancelled_at']],
            'ib39_fr_cancellations_ib39_surfaced_former_rebel_id_unique' => [true, ['ib39_surfaced_former_rebel_id']],
        ];
        foreach ($requiredIndexes as $name => [$unique, $indexColumns]) {
            $index = $indexes->firstWhere('name', $name);
            $actualColumns = collect($connection->select('PRAGMA index_info("'.$name.'")'))->pluck('name')->all();
            if (! $index || (bool) $index->unique !== $unique || $actualColumns !== $indexColumns) {
                throw new RuntimeException("The required cancellation index {$name} is not recognized.");
            }
        }
        if ($indexes->contains(fn (object $index): bool => $index->origin !== 'c')) {
            throw new RuntimeException('The cancellation table contains an incompatible implicit index.');
        }

        $objects = $connection->table('sqlite_master')->where('tbl_name', self::TABLE)
            ->whereIn('type', ['index', 'trigger'])->whereNotNull('sql')->orderBy('type')->orderBy('name')
            ->get(['type', 'name', 'sql'])->all();

        return [
            'table_sql' => $tableSql,
            'statuses' => $statuses,
            'column_names' => array_column($columns, 'name'),
            'row_count' => (int) $connection->table(self::TABLE)->count(),
            'sequence' => $connection->table('sqlite_sequence')->where('name', self::TABLE)->value('seq'),
            'objects' => $objects,
            'object_signatures' => array_map(fn (object $object): array => [$object->type, $object->name, $object->sql], $objects),
        ];
    }

    private function hasLogicalDifference(Connection $connection, string $columns): bool
    {
        $originalExceptCopy = 'SELECT '.$columns.' FROM "'.self::TABLE.'" EXCEPT SELECT '.$columns.' FROM "'.self::TEMP_TABLE.'" LIMIT 1';
        $copyExceptOriginal = 'SELECT '.$columns.' FROM "'.self::TEMP_TABLE.'" EXCEPT SELECT '.$columns.' FROM "'.self::TABLE.'" LIMIT 1';

        return $connection->select($originalExceptCopy) !== [] || $connection->select($copyExceptOriginal) !== [];
    }

    private function constraintPattern(): string
    {
        return '/check\s*\(\s*["`\[]?previous_overall_status["`\]]?\s+in\s*\((?<values>(?:[^()]|\'(?:\'\'|[^\'])*\')*)\)\s*\)/i';
    }

    private function canonicalTableSql(array $statuses): string
    {
        $statusSql = implode(', ', array_map(fn (string $status): string => "'".str_replace("'", "''", $status)."'", $statuses));

        return 'CREATE TABLE "'.self::TABLE.'" ('
            .'"id" integer primary key autoincrement not null, '
            .'"ib39_surfaced_former_rebel_id" integer not null, '
            .'"previous_overall_status" varchar check ("previous_overall_status" in ('.$statusSql.')) not null, '
            .'"reason" text not null, "cancelled_by" integer not null, "cancelled_at" datetime not null, '
            .'"created_at" datetime, "updated_at" datetime, '
            .'foreign key("ib39_surfaced_former_rebel_id") references "ib39_surfaced_former_rebels"("id") on delete restrict, '
            .'foreign key("cancelled_by") references "users"("id") on delete restrict)';
    }

    private function normalizedSql(string $sql): string
    {
        return strtolower((string) preg_replace('/\s+/', ' ', trim($sql)));
    }

    private function sqliteObjectExists(Connection $connection, string $name): bool
    {
        return $connection->table('sqlite_master')->where('name', $name)->exists();
    }
};
