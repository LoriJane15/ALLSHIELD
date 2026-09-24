<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BARANGAY_UNIQUE = 'map_barangays_barangay_id_unique';

    private const HISTORY_CURRENT_INDEX = 'color_histories_current_index';

    private const MAP_COLUMNS = [
        ['id', 'integer', 1, null, 1],
        ['fid', 'varchar', 0, null, 0],
        ['province', 'varchar', 0, null, 0],
        ['municipality', 'varchar', 0, null, 0],
        ['barangay', 'varchar', 0, null, 0],
        ['frs', 'integer', 1, "'0'", 0],
        ['status', 'varchar', 0, null, 0],
        ['infestation_color', 'varchar', 0, null, 0],
        ['rebels', 'integer', 1, "'0'", 0],
        ['created_at', 'datetime', 0, null, 0],
        ['updated_at', 'datetime', 0, null, 0],
    ];

    private const HISTORY_COLUMNS = [
        ['id', 'integer', 1, null, 1],
        ['map_barangay_id', 'integer', 0, null, 0],
        ['status', 'varchar', 0, null, 0],
        ['color', 'varchar', 0, null, 0],
        ['frs', 'integer', 0, null, 0],
        ['created_at', 'datetime', 0, null, 0],
        ['updated_at', 'datetime', 0, null, 0],
    ];

    private const MAP_COLUMN_NAMES = [
        'id', 'fid', 'province', 'municipality', 'barangay', 'frs',
        'status', 'infestation_color', 'rebels', 'created_at', 'updated_at',
    ];

    private const HISTORY_COLUMN_NAMES = [
        'id', 'map_barangay_id', 'status', 'color', 'frs', 'created_at', 'updated_at',
    ];

    public function up(): void
    {
        if ($this->isSqlite() && DB::connection()->pretending()) {
            $this->pretendSqlite();

            return;
        }

        $assignments = $this->verifiedCanonicalAssignments();
        $historyDates = $this->validatedHistoryDates();
        $this->assertNoActiveAreaWithoutHistory();

        if ($this->isSqlite()) {
            $this->upSqlite($assignments, $historyDates);

            return;
        }

        Schema::table('map_barangays', function (Blueprint $table): void {
            $table->foreignId('barangay_id')->nullable()->after('fid')
                ->constrained('barangays')->nullOnDelete();
            $table->unique('barangay_id', self::BARANGAY_UNIQUE);
        });

        Schema::table('color_histories', function (Blueprint $table): void {
            $table->date('effective_date')->nullable()->after('frs');
            $table->index(['map_barangay_id', 'effective_date', 'id'], self::HISTORY_CURRENT_INDEX);
        });

        DB::transaction(function () use ($assignments, $historyDates): void {
            $this->migrateData($assignments, $historyDates, true);
        });
    }

    public function down(): void
    {
        if ($this->isSqlite()) {
            throw new RuntimeException(
                'RCSP area-history rollback refused on SQLite because removing its foreign key requires a table rebuild.'
            );
        }

        if (DB::table('map_barangays')->whereNotNull('barangay_id')->exists()
            || DB::table('color_histories')->whereNotNull('effective_date')->exists()) {
            throw new RuntimeException(
                'RCSP area-history rollback refused because canonical or effective-date data would be lost.'
            );
        }

        Schema::table('color_histories', function (Blueprint $table): void {
            $table->dropIndex(self::HISTORY_CURRENT_INDEX);
            $table->dropColumn('effective_date');
        });

        Schema::table('map_barangays', function (Blueprint $table): void {
            $table->dropUnique(self::BARANGAY_UNIQUE);
            $table->dropConstrainedForeignId('barangay_id');
        });
    }

    /** @param  array<int, int>  $assignments */
    private function upSqlite(array $assignments, array $historyDates): void
    {
        $this->assertSqliteBaselineSchema();

        $before = [
            'map_rows' => $this->rows('map_barangays', self::MAP_COLUMN_NAMES),
            'history_rows' => $this->rows('color_histories', self::HISTORY_COLUMN_NAMES),
            'map_indexes' => $this->sqliteIndexes('map_barangays'),
            'history_indexes' => $this->sqliteIndexes('color_histories'),
            'map_foreign_keys' => $this->sqliteForeignKeys('map_barangays'),
            'history_foreign_keys' => $this->sqliteForeignKeys('color_histories'),
            'map_triggers' => $this->sqliteTriggers('map_barangays'),
            'history_triggers' => $this->sqliteTriggers('color_histories'),
        ];

        DB::transaction(function () use ($assignments, $historyDates, $before): void {
            DB::statement(
                'ALTER TABLE "map_barangays" ADD COLUMN "barangay_id" INTEGER REFERENCES "barangays" ("id") ON DELETE SET NULL'
            );
            DB::statement(
                'CREATE UNIQUE INDEX "'.self::BARANGAY_UNIQUE.'" ON "map_barangays" ("barangay_id")'
            );
            DB::statement('ALTER TABLE "color_histories" ADD COLUMN "effective_date" DATE');
            DB::statement(
                'CREATE INDEX "'.self::HISTORY_CURRENT_INDEX.'" ON "color_histories" ("map_barangay_id", "effective_date", "id")'
            );

            $this->migrateData($assignments, $historyDates);
            $this->verifySqliteMigration($before, $assignments, $historyDates);
        });
    }

    private function pretendSqlite(): void
    {
        DB::statement(
            'ALTER TABLE "map_barangays" ADD COLUMN "barangay_id" INTEGER REFERENCES "barangays" ("id") ON DELETE SET NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX "'.self::BARANGAY_UNIQUE.'" ON "map_barangays" ("barangay_id")'
        );
        DB::statement('ALTER TABLE "color_histories" ADD COLUMN "effective_date" DATE');
        DB::statement(
            'CREATE INDEX "'.self::HISTORY_CURRENT_INDEX.'" ON "color_histories" ("map_barangay_id", "effective_date", "id")'
        );
        DB::table('map_barangays')->where('status', 'Rekonsilida')->update(['status' => 'Rekonsilido']);
        DB::table('color_histories')->where('status', 'Rekonsilida')->update(['status' => 'Rekonsilido']);
        DB::statement('UPDATE "color_histories" SET "effective_date" = date("created_at")');
    }

    /** @param  array<int, int>  $assignments */
    private function migrateData(array $assignments, array $historyDates, bool $sqlite = false): void
    {
        DB::table('map_barangays')->where('status', 'Rekonsilida')->update(['status' => 'Rekonsilido']);
        DB::table('color_histories')->where('status', 'Rekonsilida')->update(['status' => 'Rekonsilido']);

        if ($sqlite) {
            DB::statement('UPDATE "color_histories" SET "effective_date" = date("created_at")');
        } else {
            foreach ($historyDates as $historyId => $effectiveDate) {
                DB::table('color_histories')->where('id', $historyId)->update([
                    'effective_date' => $effectiveDate,
                ]);
            }
        }

        foreach ($assignments as $mapBarangayId => $barangayId) {
            DB::table('map_barangays')->where('id', $mapBarangayId)->update([
                'barangay_id' => $barangayId,
            ]);
        }

        DB::table('map_barangays')->whereNotNull('status')->orderBy('id')->each(function (object $area): void {
            $current = DB::table('color_histories')
                ->where('map_barangay_id', $area->id)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->first();

            if (! $current) {
                throw new RuntimeException(
                    "Cannot synchronize active RCSP area {$area->id} because it has no history. The migration transaction was rolled back."
                );
            }

            DB::table('map_barangays')->where('id', $area->id)->update([
                'frs' => $current->frs,
                'rebels' => $current->frs,
                'status' => $current->status,
                'infestation_color' => $current->color,
            ]);
        });
    }

    private function assertSqliteBaselineSchema(): void
    {
        if ((int) DB::scalar('PRAGMA foreign_keys') !== 1) {
            throw new RuntimeException('SQLite foreign-key enforcement must be enabled before the RCSP migration.');
        }

        $this->assertSame(self::MAP_COLUMNS, $this->sqliteColumns('map_barangays'),
            'map_barangays schema differs from the expected pre-migration schema.');
        $this->assertSame(self::HISTORY_COLUMNS, $this->sqliteColumns('color_histories'),
            'color_histories schema differs from the expected pre-migration schema.');
        $this->assertSame([], $this->sqliteForeignKeys('map_barangays'),
            'map_barangays has an unexpected foreign key.');
        $this->assertSame([
            ['map_barangay_id', 'map_barangays', 'id', 'NO ACTION', 'CASCADE', 'NONE'],
        ], $this->sqliteForeignKeys('color_histories'),
            'color_histories foreign keys differ from the expected schema.');

        if (isset($this->sqliteIndexes('map_barangays')[self::BARANGAY_UNIQUE])
            || isset($this->sqliteIndexes('color_histories')[self::HISTORY_CURRENT_INDEX])) {
            throw new RuntimeException('An RCSP migration target index already exists.');
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<int, int>  $assignments
     * @param  array<int, string>  $historyDates
     */
    private function verifySqliteMigration(array $before, array $assignments, array $historyDates): void
    {
        $this->assertSame([...self::MAP_COLUMNS, ['barangay_id', 'integer', 0, null, 0]],
            $this->sqliteColumns('map_barangays'), 'map_barangays columns were not preserved exactly.');
        $this->assertSame([...self::HISTORY_COLUMNS, ['effective_date', 'date', 0, null, 0]],
            $this->sqliteColumns('color_histories'), 'color_histories columns were not preserved exactly.');

        $mapIndexes = $this->sqliteIndexes('map_barangays');
        $newMapIndex = $mapIndexes[self::BARANGAY_UNIQUE] ?? null;
        unset($mapIndexes[self::BARANGAY_UNIQUE]);
        $this->assertSame($before['map_indexes'], $mapIndexes, 'An existing map_barangays index changed.');
        $this->assertSame([1, 'c', 0, ['barangay_id']], $newMapIndex, 'The canonical unique index is invalid.');

        $historyIndexes = $this->sqliteIndexes('color_histories');
        $newHistoryIndex = $historyIndexes[self::HISTORY_CURRENT_INDEX] ?? null;
        unset($historyIndexes[self::HISTORY_CURRENT_INDEX]);
        $this->assertSame($before['history_indexes'], $historyIndexes, 'An existing color_histories index changed.');
        $this->assertSame([0, 'c', 0, ['map_barangay_id', 'effective_date', 'id']],
            $newHistoryIndex, 'The current-history index is invalid.');

        $this->assertSame(
            [...$before['map_foreign_keys'], ['barangay_id', 'barangays', 'id', 'NO ACTION', 'SET NULL', 'NONE']],
            $this->sqliteForeignKeys('map_barangays'), 'The map_barangays foreign keys are invalid.');
        $this->assertSame($before['history_foreign_keys'], $this->sqliteForeignKeys('color_histories'),
            'An existing color_histories foreign key changed.');
        $this->assertSame($before['map_triggers'], $this->sqliteTriggers('map_barangays'), 'A map trigger changed.');
        $this->assertSame($before['history_triggers'], $this->sqliteTriggers('color_histories'), 'A history trigger changed.');

        $expectedHistories = $before['history_rows'];
        foreach ($expectedHistories as &$history) {
            if ($history['status'] === 'Rekonsilida') {
                $history['status'] = 'Rekonsilido';
            }
            $history['effective_date'] = $historyDates[(int) $history['id']];
        }
        unset($history);
        $actualHistories = $this->rows('color_histories', [...self::HISTORY_COLUMN_NAMES, 'effective_date']);
        $this->assertSame($expectedHistories, $actualHistories, 'History rows, IDs, or values were not preserved.');

        $currentByArea = [];
        foreach ($expectedHistories as $history) {
            $areaId = $history['map_barangay_id'];
            if ($areaId === null) {
                continue;
            }
            $existing = $currentByArea[$areaId] ?? null;
            if ($existing === null
                || [$history['effective_date'], (int) $history['id']] > [$existing['effective_date'], (int) $existing['id']]) {
                $currentByArea[$areaId] = $history;
            }
        }

        $expectedMaps = $before['map_rows'];
        foreach ($expectedMaps as &$area) {
            $areaId = (int) $area['id'];
            if ($area['status'] === 'Rekonsilida') {
                $area['status'] = 'Rekonsilido';
            }
            if ($area['status'] !== null) {
                $current = $currentByArea[$areaId] ?? null;
                if ($current === null) {
                    throw new RuntimeException("Active RCSP area {$areaId} lost its authoritative history.");
                }
                $area['frs'] = $current['frs'];
                $area['rebels'] = $current['frs'];
                $area['status'] = $current['status'];
                $area['infestation_color'] = $current['color'];
            }
            $area['barangay_id'] = $assignments[$areaId] ?? null;
        }
        unset($area);
        $actualMaps = $this->rows('map_barangays', [...self::MAP_COLUMN_NAMES, 'barangay_id']);
        $this->assertSame($expectedMaps, $actualMaps, 'Map rows, IDs, or values were not preserved.');

        $this->assertSame('ok', DB::scalar('PRAGMA quick_check'), 'SQLite quick_check failed after migration.');
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'), 'SQLite foreign_key_check failed after migration.');
        $temporaryTables = DB::table('sqlite_master')->where('type', 'table')
            ->whereIn('name', ['__temp__map_barangays', '__temp__color_histories'])->count();
        $this->assertSame(0, $temporaryTables, 'A temporary SQLite migration table remains.');
    }

    /** @return array<int, string> */
    private function validatedHistoryDates(): array
    {
        $dates = [];
        DB::table('color_histories')->orderBy('id')->get(['id', 'created_at'])
            ->each(function (object $history) use (&$dates): void {
                if ($history->created_at === null) {
                    throw new RuntimeException(
                        'Cannot backfill RCSP effective dates because a history row has no created_at value. No schema or data was changed.'
                    );
                }
                $dates[(int) $history->id] = CarbonImmutable::parse($history->created_at)->toDateString();
            });

        return $dates;
    }

    private function assertNoActiveAreaWithoutHistory(): void
    {
        $areaId = DB::table('map_barangays as mb')
            ->leftJoin('color_histories as ch', 'ch.map_barangay_id', '=', 'mb.id')
            ->whereNotNull('mb.status')->whereNull('ch.id')->value('mb.id');
        if ($areaId !== null) {
            throw new RuntimeException(
                "Cannot synchronize active RCSP area {$areaId} because it has no history. No schema or data was changed."
            );
        }
    }

    /** @return array<int, int> map_barangay_id => barangay_id */
    private function verifiedCanonicalAssignments(): array
    {
        $canonical = [];
        DB::table('barangays as b')->join('municipalities as m', 'm.id', '=', 'b.municipality_id')
            ->get(['b.id', 'b.name as barangay', 'm.name as municipality'])
            ->each(function (object $barangay) use (&$canonical): void {
                $key = $this->locationKey($barangay->municipality, $barangay->barangay);
                if (isset($canonical[$key])) {
                    throw new RuntimeException(
                        "Cannot backfill RCSP geography because canonical location '{$key}' is ambiguous. No schema or data was changed."
                    );
                }
                $canonical[$key] = (int) $barangay->id;
            });

        $assignments = [];
        $claimedBarangays = [];
        DB::table('map_barangays')->get(['id', 'municipality', 'barangay'])
            ->each(function (object $area) use (&$assignments, &$claimedBarangays, $canonical): void {
                if ($area->municipality === null || $area->barangay === null) {
                    return;
                }
                $key = $this->locationKey($area->municipality, $area->barangay);
                $barangayId = $canonical[$key] ?? null;
                if ($barangayId === null) {
                    return;
                }
                if (isset($claimedBarangays[$barangayId])) {
                    throw new RuntimeException(
                        "Cannot backfill RCSP geography because map rows {$claimedBarangays[$barangayId]} and {$area->id} match the same canonical barangay. No schema or data was changed."
                    );
                }
                $assignments[(int) $area->id] = $barangayId;
                $claimedBarangays[$barangayId] = (int) $area->id;
            });

        return $assignments;
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(string $table, array $columns): array
    {
        return DB::table($table)->orderBy('id')->get($columns)
            ->map(fn (object $row): array => (array) $row)->all();
    }

    /** @return array<int, array{string, string, int, ?string, int}> */
    private function sqliteColumns(string $table): array
    {
        return array_map(fn (object $column): array => [
            $column->name, mb_strtolower($column->type), (int) $column->notnull,
            $column->dflt_value, (int) $column->pk,
        ], DB::select('PRAGMA table_info("'.$table.'")'));
    }

    /** @return array<string, array{int, string, int, array<int, string>}> */
    private function sqliteIndexes(string $table): array
    {
        $indexes = [];
        foreach (DB::select('PRAGMA index_list("'.$table.'")') as $index) {
            if (str_starts_with($index->name, 'sqlite_')) {
                continue;
            }
            $columns = array_map(fn (object $column): string => $column->name,
                DB::select('PRAGMA index_info("'.$index->name.'")'));
            $indexes[$index->name] = [(int) $index->unique, $index->origin, (int) $index->partial, $columns];
        }
        ksort($indexes);

        return $indexes;
    }

    /** @return array<int, array{string, string, string, string, string, string}> */
    private function sqliteForeignKeys(string $table): array
    {
        $foreignKeys = array_map(fn (object $key): array => [
            $key->from, $key->table, $key->to, $key->on_update, $key->on_delete, $key->match,
        ], DB::select('PRAGMA foreign_key_list("'.$table.'")'));
        sort($foreignKeys);

        return $foreignKeys;
    }

    /** @return array<string, string> */
    private function sqliteTriggers(string $table): array
    {
        return DB::table('sqlite_master')->where('type', 'trigger')->where('tbl_name', $table)
            ->orderBy('name')->pluck('sql', 'name')->all();
    }

    private function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message);
        }
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private function locationKey(string $municipality, string $barangay): string
    {
        $normalize = static fn (string $value): string => mb_strtoupper(
            preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value)
        );

        return $normalize($municipality).'|'.$normalize($barangay);
    }
};
