<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    private const NAME_COLUMN = 'name_canonical';

    private const ACRONYM_COLUMN = 'acronym_canonical';

    private const NAME_UNIQUE = 'gov_agencies_name_canonical_unique';

    private const ACRONYM_UNIQUE = 'gov_agencies_acronym_canonical_unique';

    private const USER_FOREIGN = 'users_gov_agency_id_foreign';

    private const TABLES = [
        'gov_agencies',
        'users',
        'agency_implan_responses',
        'implementation_taggings',
    ];

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb', 'pgsql'], true)) {
            throw new RuntimeException(
                "Government-agency integrity migration does not support the configured '{$driver}' database driver. No schema or data was changed."
            );
        }

        $this->assertCompatibleSchema($driver);
        $this->assertCompatibleData($driver);

        if ($driver === 'sqlite') {
            $this->upSqlite();

            return;
        }

        if ($driver === 'pgsql') {
            DB::transaction(fn () => $this->upPostgres());

            return;
        }

        $this->upMysqlFamily();
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Government-agency integrity rollback refused because it would remove canonical uniqueness and could restore unsafe deletion behavior. Restore a verified backup or use an approved corrective forward migration.'
        );
    }

    private function assertCompatibleSchema(string $driver): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required table '{$table}' is missing. No schema or data was changed.");
            }
        }

        $requiredColumns = [
            'gov_agencies' => ['id', 'name', 'acronym', 'profile', 'created_at', 'updated_at'],
            'users' => ['id', 'gov_agency_id'],
            'agency_implan_responses' => ['id', 'gov_agency_id', 'implementation_id'],
            'implementation_taggings' => ['id', 'gov_agency_id', 'implementation_id'],
        ];

        foreach ($requiredColumns as $table => $columns) {
            if (! Schema::hasColumns($table, $columns)) {
                throw new RuntimeException("Table '{$table}' does not have the required agency-integrity columns. No schema or data was changed.");
            }
        }

        if (Schema::hasColumn('gov_agencies', self::NAME_COLUMN)
            || Schema::hasColumn('gov_agencies', self::ACRONYM_COLUMN)) {
            throw new RuntimeException('Government-agency canonical columns already exist. No schema or data was changed.');
        }

        $indexNames = collect(Schema::getIndexes('gov_agencies'))->pluck('name');
        if ($indexNames->contains(self::NAME_UNIQUE) || $indexNames->contains(self::ACRONYM_UNIQUE)) {
            throw new RuntimeException('Government-agency canonical indexes already exist. No schema or data was changed.');
        }

        $this->assertAgencyForeignKey('users', null);
        $this->assertAgencyForeignKey('agency_implan_responses', 'cascade');
        $this->assertAgencyForeignKey('implementation_taggings', 'cascade');

        if ($driver === 'sqlite' && (int) DB::scalar('PRAGMA foreign_keys') !== 1) {
            throw new RuntimeException('SQLite foreign-key enforcement must be enabled before the government-agency integrity migration.');
        }
    }

    private function assertCompatibleData(string $driver): void
    {
        $checks = [
            'duplicate canonical agency names' => DB::table('gov_agencies')
                ->selectRaw('LOWER(TRIM(name)) AS canonical_identity')
                ->groupByRaw('LOWER(TRIM(name))')->havingRaw('COUNT(*) > 1')->exists(),
            'duplicate canonical agency acronyms' => DB::table('gov_agencies')
                ->selectRaw('LOWER(TRIM(acronym)) AS canonical_identity')
                ->groupByRaw('LOWER(TRIM(acronym))')->havingRaw('COUNT(*) > 1')->exists(),
            'null or blank agency names' => DB::table('gov_agencies')
                ->whereNull('name')->orWhereRaw("TRIM(name) = ''")->exists(),
            'null or blank agency acronyms' => DB::table('gov_agencies')
                ->whereNull('acronym')->orWhereRaw("TRIM(acronym) = ''")->exists(),
            'untrimmed agency names' => DB::table('gov_agencies')->whereRaw('name <> TRIM(name)')->exists(),
            'untrimmed agency acronyms' => DB::table('gov_agencies')->whereRaw('acronym <> TRIM(acronym)')->exists(),
            'dangling user agency references' => DB::table('users as dependent')
                ->leftJoin('gov_agencies as agency', 'agency.id', '=', 'dependent.gov_agency_id')
                ->whereNotNull('dependent.gov_agency_id')->whereNull('agency.id')->exists(),
            'dangling response agency references' => DB::table('agency_implan_responses as dependent')
                ->leftJoin('gov_agencies as agency', 'agency.id', '=', 'dependent.gov_agency_id')
                ->whereNotNull('dependent.gov_agency_id')->whereNull('agency.id')->exists(),
            'dangling tagging agency references' => DB::table('implementation_taggings as dependent')
                ->leftJoin('gov_agencies as agency', 'agency.id', '=', 'dependent.gov_agency_id')
                ->whereNotNull('dependent.gov_agency_id')->whereNull('agency.id')->exists(),
        ];

        if ($driver === 'sqlite') {
            $checks['existing SQLite foreign-key violations'] = DB::select('PRAGMA foreign_key_check') !== [];
        }

        foreach ($checks as $description => $failed) {
            if ($failed) {
                throw new RuntimeException("Government-agency integrity migration refused because it found {$description}. No schema or data was changed.");
            }
        }
    }

    private function assertAgencyForeignKey(string $table, ?string $expectedDelete): void
    {
        $keys = collect(Schema::getForeignKeys($table))->filter(
            fn (array $key): bool => $key['columns'] === ['gov_agency_id']
                && $key['foreign_table'] === 'gov_agencies'
                && $key['foreign_columns'] === ['id']
        )->values();

        if ($expectedDelete === null && $keys->isEmpty()) {
            return;
        }

        if ($keys->count() !== 1 || strtolower((string) $keys->first()['on_delete']) !== $expectedDelete) {
            throw new RuntimeException("Table '{$table}' has an unexpected government-agency foreign key. No schema or data was changed.");
        }
    }

    private function upSqlite(): void
    {
        $before = $this->sqliteSnapshot();

        DB::statement('PRAGMA foreign_keys = OFF');
        if ((int) DB::scalar('PRAGMA foreign_keys') !== 0) {
            throw new RuntimeException('SQLite foreign keys could not be disabled safely for the transactional table rebuild.');
        }

        try {
            DB::transaction(function () use ($before): void {
                foreach ($before['database_triggers'] as $trigger) {
                    $name = str_replace('"', '""', $trigger['name']);
                    DB::statement("DROP TRIGGER \"{$name}\"");
                }

                $this->rebuildSqliteTable('users', $before['users'], true);
                $this->rebuildSqliteTable('agency_implan_responses', $before['agency_implan_responses']);
                $this->rebuildSqliteTable('implementation_taggings', $before['implementation_taggings']);

                DB::statement(
                    'ALTER TABLE "gov_agencies" ADD COLUMN "'.self::NAME_COLUMN.'" TEXT GENERATED ALWAYS AS (LOWER(TRIM("name"))) VIRTUAL'
                );
                DB::statement(
                    'ALTER TABLE "gov_agencies" ADD COLUMN "'.self::ACRONYM_COLUMN.'" TEXT GENERATED ALWAYS AS (LOWER(TRIM("acronym"))) VIRTUAL'
                );
                DB::statement(
                    'CREATE UNIQUE INDEX "'.self::NAME_UNIQUE.'" ON "gov_agencies" ("'.self::NAME_COLUMN.'")'
                );
                DB::statement(
                    'CREATE UNIQUE INDEX "'.self::ACRONYM_UNIQUE.'" ON "gov_agencies" ("'.self::ACRONYM_COLUMN.'")'
                );

                foreach ($before['database_triggers'] as $trigger) {
                    DB::statement($trigger['sql']);
                }

                $this->verifySqliteMigration($before);
            });
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        if ((int) DB::scalar('PRAGMA foreign_keys') !== 1) {
            throw new RuntimeException('SQLite foreign-key enforcement was not restored after the government-agency integrity migration.');
        }

        $this->assertSame([], DB::select('PRAGMA foreign_key_check'), 'SQLite foreign_key_check failed after migration.');
    }

    /** @param  array<string, mixed>  $snapshot */
    private function rebuildSqliteTable(string $table, array $snapshot, bool $addAgencyForeign = false): void
    {
        $temporary = '__b2_'.$table;
        $sql = $snapshot['table_sql'];

        if ($addAgencyForeign) {
            $sql = preg_replace(
                '/\)\s*$/',
                ', foreign key("gov_agency_id") references "gov_agencies"("id") on delete restrict)',
                $sql,
                1,
                $replacements
            );
        } else {
            $sql = preg_replace(
                '/foreign key\("gov_agency_id"\) references "gov_agencies"\("id"\) on delete cascade/i',
                'foreign key("gov_agency_id") references "gov_agencies"("id") on delete restrict',
                $sql,
                1,
                $replacements
            );
        }

        if ($sql === null || $replacements !== 1) {
            throw new RuntimeException("SQLite table '{$table}' could not be transformed unambiguously. No schema or data was changed.");
        }

        $createSql = preg_replace(
            '/^CREATE TABLE\s+(?:"'.preg_quote($table, '/').'"|`'.preg_quote($table, '/').'`|\['.preg_quote($table, '/').'\]|'.preg_quote($table, '/').')/i',
            'CREATE TABLE "'.$temporary.'"',
            $sql,
            1,
            $renamed
        );

        if ($createSql === null || $renamed !== 1) {
            throw new RuntimeException("SQLite table '{$table}' could not be renamed safely for rebuilding. No schema or data was changed.");
        }

        $columns = array_column(array_filter(
            $snapshot['columns'],
            fn (array $column): bool => $column['hidden'] === 0
        ), 'name');
        $quotedColumns = implode(', ', array_map(fn (string $column): string => '"'.str_replace('"', '""', $column).'"', $columns));

        DB::statement($createSql);
        DB::statement("INSERT INTO \"{$temporary}\" ({$quotedColumns}) SELECT {$quotedColumns} FROM \"{$table}\"");
        DB::statement("DROP TABLE \"{$table}\"");
        DB::statement("ALTER TABLE \"{$temporary}\" RENAME TO \"{$table}\"");

        foreach ($snapshot['objects'] as $object) {
            if ($object['type'] === 'index') {
                DB::statement($object['sql']);
            }
        }
    }

    private function upMysqlFamily(): void
    {
        $responseForeign = $this->mysqlAgencyForeignName('agency_implan_responses');
        $taggingForeign = $this->mysqlAgencyForeignName('implementation_taggings');

        DB::statement(
            'ALTER TABLE `users` ADD CONSTRAINT `'.self::USER_FOREIGN.'` FOREIGN KEY (`gov_agency_id`) REFERENCES `gov_agencies` (`id`) ON DELETE RESTRICT'
        );
        DB::statement(
            "ALTER TABLE `agency_implan_responses` DROP FOREIGN KEY `{$responseForeign}`, ADD CONSTRAINT `{$responseForeign}` FOREIGN KEY (`gov_agency_id`) REFERENCES `gov_agencies` (`id`) ON DELETE RESTRICT"
        );
        DB::statement(
            "ALTER TABLE `implementation_taggings` DROP FOREIGN KEY `{$taggingForeign}`, ADD CONSTRAINT `{$taggingForeign}` FOREIGN KEY (`gov_agency_id`) REFERENCES `gov_agencies` (`id`) ON DELETE RESTRICT"
        );
        DB::statement(
            'ALTER TABLE `gov_agencies` '
            .'ADD COLUMN `'.self::NAME_COLUMN.'` VARCHAR(255) GENERATED ALWAYS AS (LOWER(TRIM(`name`))) STORED, '
            .'ADD COLUMN `'.self::ACRONYM_COLUMN.'` VARCHAR(50) GENERATED ALWAYS AS (LOWER(TRIM(`acronym`))) STORED, '
            .'ADD UNIQUE INDEX `'.self::NAME_UNIQUE.'` (`'.self::NAME_COLUMN.'`), '
            .'ADD UNIQUE INDEX `'.self::ACRONYM_UNIQUE.'` (`'.self::ACRONYM_COLUMN.'`)'
        );
    }

    private function upPostgres(): void
    {
        DB::statement('ALTER TABLE users ADD CONSTRAINT '.self::USER_FOREIGN.' FOREIGN KEY (gov_agency_id) REFERENCES gov_agencies (id) ON DELETE RESTRICT');

        foreach (['agency_implan_responses', 'implementation_taggings'] as $table) {
            $foreign = collect(Schema::getForeignKeys($table))->first(
                fn (array $key): bool => $key['columns'] === ['gov_agency_id'] && $key['foreign_table'] === 'gov_agencies'
            );
            $name = $foreign['name'] ?? null;
            if (! is_string($name) || ! preg_match('/\A[A-Za-z0-9_]+\z/', $name)) {
                throw new RuntimeException("Table '{$table}' has no safely addressable government-agency foreign key.");
            }
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} FOREIGN KEY (gov_agency_id) REFERENCES gov_agencies (id) ON DELETE RESTRICT");
        }

        DB::statement('ALTER TABLE gov_agencies ADD COLUMN '.self::NAME_COLUMN.' TEXT GENERATED ALWAYS AS (LOWER(TRIM(name))) STORED');
        DB::statement('ALTER TABLE gov_agencies ADD COLUMN '.self::ACRONYM_COLUMN.' TEXT GENERATED ALWAYS AS (LOWER(TRIM(acronym))) STORED');
        DB::statement('CREATE UNIQUE INDEX '.self::NAME_UNIQUE.' ON gov_agencies ('.self::NAME_COLUMN.')');
        DB::statement('CREATE UNIQUE INDEX '.self::ACRONYM_UNIQUE.' ON gov_agencies ('.self::ACRONYM_COLUMN.')');
    }

    private function mysqlAgencyForeignName(string $table): string
    {
        $key = collect(Schema::getForeignKeys($table))->first(
            fn (array $foreign): bool => $foreign['columns'] === ['gov_agency_id']
                && $foreign['foreign_table'] === 'gov_agencies'
        );
        $name = $key['name'] ?? null;

        if (! is_string($name) || ! preg_match('/\A[A-Za-z0-9_]+\z/', $name)) {
            throw new RuntimeException("Table '{$table}' has no safely addressable government-agency foreign key. No schema or data was changed.");
        }

        return $name;
    }

    /** @return array<string, array<string, mixed>> */
    private function sqliteSnapshot(): array
    {
        $snapshot = [
            'database_triggers' => DB::table('sqlite_master')->where('type', 'trigger')->whereNotNull('sql')
                ->orderBy('name')->get(['name', 'sql'])->map(fn (object $trigger): array => (array) $trigger)->all(),
        ];

        foreach (self::TABLES as $table) {
            $columns = array_map(fn (object $column): array => [
                'cid' => (int) $column->cid,
                'name' => $column->name,
                'type' => mb_strtolower($column->type),
                'notnull' => (int) $column->notnull,
                'default' => $column->dflt_value,
                'pk' => (int) $column->pk,
                'hidden' => (int) $column->hidden,
            ], DB::select('PRAGMA table_xinfo("'.$table.'")'));
            $columnNames = array_column(array_filter($columns, fn (array $column): bool => $column['hidden'] === 0), 'name');

            $snapshot[$table] = [
                'table_sql' => DB::table('sqlite_master')->where('type', 'table')->where('name', $table)->value('sql'),
                'columns' => $columns,
                'rows' => DB::table($table)->select($columnNames)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
                'objects' => DB::table('sqlite_master')->where('tbl_name', $table)
                    ->whereIn('type', ['index', 'trigger'])->whereNotNull('sql')
                    ->orderBy('type')->orderBy('name')->get(['type', 'name', 'sql'])
                    ->map(fn (object $object): array => (array) $object)->all(),
                'foreign_keys' => $this->sqliteForeignKeys($table),
            ];
        }

        return $snapshot;
    }

    /** @param  array<string, array<string, mixed>>  $before */
    private function verifySqliteMigration(array $before): void
    {
        $this->assertSame($before['database_triggers'], DB::table('sqlite_master')
            ->where('type', 'trigger')->whereNotNull('sql')->orderBy('name')->get(['name', 'sql'])
            ->map(fn (object $trigger): array => (array) $trigger)->all(), 'An existing SQLite trigger changed.');

        foreach (['users', 'agency_implan_responses', 'implementation_taggings'] as $table) {
            $this->assertSame($before[$table]['columns'], $this->sqliteColumns($table), "SQLite table '{$table}' lost or changed a column.");
            $this->assertSame($before[$table]['rows'], $this->sqliteRows($table, $before[$table]['columns']), "SQLite table '{$table}' lost or changed a row.");
            $this->assertSame($before[$table]['objects'], $this->sqliteObjects($table), "SQLite table '{$table}' lost or changed an index or trigger.");
        }

        $this->assertSame($before['gov_agencies']['rows'], $this->sqliteRows('gov_agencies', $before['gov_agencies']['columns']), 'SQLite gov_agencies rows changed.');
        $this->assertSame($before['gov_agencies']['objects'], array_values(array_filter(
            $this->sqliteObjects('gov_agencies'),
            fn (array $object): bool => ! in_array($object['name'], [self::NAME_UNIQUE, self::ACRONYM_UNIQUE], true)
        )), 'An unrelated SQLite gov_agencies index or trigger changed.');

        $govColumns = $this->sqliteColumns('gov_agencies');
        $originalGovColumns = array_values(array_filter(
            $govColumns,
            fn (array $column): bool => ! in_array($column['name'], [self::NAME_COLUMN, self::ACRONYM_COLUMN], true)
        ));
        $this->assertSame($before['gov_agencies']['columns'], $originalGovColumns, 'SQLite gov_agencies base columns changed.');

        $this->assertAgencyDeleteAction('users', 'RESTRICT');
        $this->assertAgencyDeleteAction('agency_implan_responses', 'RESTRICT');
        $this->assertAgencyDeleteAction('implementation_taggings', 'RESTRICT');
        $this->assertUnrelatedForeignKeysPreserved($before);

        $canonicalIndexes = collect($this->sqliteObjects('gov_agencies'))->where('type', 'index')->keyBy('name');
        foreach ([self::NAME_UNIQUE, self::ACRONYM_UNIQUE] as $index) {
            if (! $canonicalIndexes->has($index)) {
                throw new RuntimeException("SQLite canonical index '{$index}' was not created.");
            }
        }

        $temporaryTables = DB::table('sqlite_master')->where('type', 'table')->where('name', 'like', '__b2_%')->count();
        $this->assertSame(0, $temporaryTables, 'A temporary SQLite government-agency migration table remains.');
        $this->assertSame('ok', DB::scalar('PRAGMA quick_check'), 'SQLite quick_check failed after migration.');
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'), 'SQLite foreign_key_check failed during migration.');
    }

    /** @param  array<string, array<string, mixed>>  $before */
    private function assertUnrelatedForeignKeysPreserved(array $before): void
    {
        foreach (['agency_implan_responses', 'implementation_taggings'] as $table) {
            $original = array_values(array_filter(
                $before[$table]['foreign_keys'],
                fn (array $key): bool => $key['from'] !== 'gov_agency_id'
            ));
            $current = array_values(array_filter(
                $this->sqliteForeignKeys($table),
                fn (array $key): bool => $key['from'] !== 'gov_agency_id'
            ));
            $this->assertSame($original, $current, "SQLite table '{$table}' lost or changed an unrelated foreign key.");
        }
    }

    private function assertAgencyDeleteAction(string $table, string $expected): void
    {
        $keys = array_values(array_filter(
            $this->sqliteForeignKeys($table),
            fn (array $key): bool => $key['from'] === 'gov_agency_id' && $key['table'] === 'gov_agencies'
        ));

        if (count($keys) !== 1 || $keys[0]['on_delete'] !== $expected) {
            throw new RuntimeException("SQLite table '{$table}' does not have the required agency delete restriction.");
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function sqliteColumns(string $table): array
    {
        return array_map(fn (object $column): array => [
            'cid' => (int) $column->cid,
            'name' => $column->name,
            'type' => mb_strtolower($column->type),
            'notnull' => (int) $column->notnull,
            'default' => $column->dflt_value,
            'pk' => (int) $column->pk,
            'hidden' => (int) $column->hidden,
        ], DB::select('PRAGMA table_xinfo("'.$table.'")'));
    }

    /** @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function sqliteRows(string $table, array $columns): array
    {
        $names = array_column(array_filter($columns, fn (array $column): bool => $column['hidden'] === 0), 'name');

        return DB::table($table)->select($names)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
    }

    /** @return array<int, array{type: string, name: string, sql: string}> */
    private function sqliteObjects(string $table): array
    {
        return DB::table('sqlite_master')->where('tbl_name', $table)
            ->whereIn('type', ['index', 'trigger'])->whereNotNull('sql')
            ->orderBy('type')->orderBy('name')->get(['type', 'name', 'sql'])
            ->map(fn (object $object): array => (array) $object)->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function sqliteForeignKeys(string $table): array
    {
        $keys = array_map(fn (object $key): array => [
            'from' => $key->from,
            'table' => $key->table,
            'to' => $key->to,
            'on_update' => $key->on_update,
            'on_delete' => $key->on_delete,
            'match' => $key->match,
        ], DB::select('PRAGMA foreign_key_list("'.$table.'")'));
        usort($keys, fn (array $left, array $right): int => [$left['from'], $left['table']] <=> [$right['from'], $right['table']]);

        return $keys;
    }

    private function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message);
        }
    }
};
