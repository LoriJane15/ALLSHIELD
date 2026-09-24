<?php

use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['japic_certification_processings', 'japic_certification_drafts',
        'japic_certification_draft_histories', 'japic_certification_histories',
        'japic_certification_document_versions'];

    private const ROLES = ['super_admin', 'admin', '39th_ib', 'gov_agency', 'lgu', 'mblrc', 'afp', 'japic'];

    public function up(): void
    {
        $this->preflight();
        DB::transaction(function (): void {
            $this->changeRole(true);
            $this->repairEmptyJapicTables();
            $this->backfill();
        });
    }

    public function repairEmptyJapicTables(): void
    {
        $this->preflight();
        $existing = collect(self::TABLES)->filter(fn (string $table): bool => Schema::hasTable($table));
        if ($existing->count() === count(self::TABLES) && $this->isCanonical()) {
            return;
        }

        $populated = $existing->first(fn (string $table): bool => DB::table($table)->exists());
        if ($populated !== null) {
            throw new RuntimeException("JAPIC schema repair refused before mutation: {$populated} contains records while the five-table schema is incompatible.");
        }

        Schema::disableForeignKeyConstraints();
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
        $this->tables();
        $this->indexes();
        $this->foreignKeys();
        $this->guards();
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('JAPIC migration rollback refused: certification records would be lost.');
            }
        }
        if (Schema::hasTable('users') && DB::table('users')->where('role', 'japic')->exists()) {
            throw new RuntimeException('JAPIC migration rollback refused: JAPIC user roles would be lost.');
        }
        DB::transaction(function (): void {
            Schema::disableForeignKeyConstraints();
            foreach (array_reverse(self::TABLES) as $table) {
                Schema::dropIfExists($table);
            }
            Schema::enableForeignKeyConstraints();
            $this->changeRole(false);
        });
    }

    private function preflight(): void
    {
        $required = [
            self::TABLES[0] => ['id', 'ib39_surfaced_former_rebel_id', 'status', 'triggering_cdr_document_version_id'],
            self::TABLES[1] => ['id', 'processing_id'],
            self::TABLES[2] => ['id', 'processing_id', 'revision'],
            self::TABLES[3] => ['id', 'processing_id', 'event'],
            self::TABLES[4] => ['id', 'processing_id', 'version_number'],
        ];
        // Complete the inspection of every existing table before mutating any schema.
        foreach ($required as $table => $columns) {
            if (Schema::hasTable($table) && DB::table($table)->exists()
                && array_diff($columns, Schema::getColumnListing($table))) {
                throw new RuntimeException("JAPIC migration refused: populated incompatible table {$table} requires manual reconciliation.");
            }
        }
        if (Schema::hasTable(self::TABLES[0]) && DB::table(self::TABLES[0])
            ->select('ib39_surfaced_former_rebel_id')->groupBy('ib39_surfaced_former_rebel_id')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('JAPIC migration refused: duplicate processing records require manual reconciliation.');
        }
    }

    private function changeRole(bool $add): void
    {
        $roles = $add ? self::ROLES : array_slice(self::ROLES, 0, -1);
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('".implode("','", $roles)."'))");

            return;
        }
        if (DB::getDriverName() === 'sqlite') {
            $definition = DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->value('sql');
            $replacement = 'check ("role" in (\''.implode("', '", $roles).'\'))';
            $changed = preg_replace('/check\s*\(\s*["\x60]?role["\x60]?\s+in\s*\([^)]*\)\s*\)/i', $replacement, $definition, 1, $count);
            if ($count === 0) {
                if (preg_match('/check\s*\([^)]*(?:["\x60]role["\x60]|\brole\b)/i', (string) $definition) === 1) {
                    throw new RuntimeException('JAPIC migration refused: users.role has an unrecognized restrictive SQLite constraint.');
                }

                return;
            }
            if ($count !== 1 || ! is_string($changed)) {
                throw new RuntimeException('JAPIC migration refused: multiple SQLite users.role constraints require manual reconciliation.');
            }
            $version = (int) DB::selectOne('PRAGMA schema_version')->schema_version;
            DB::unprepared('PRAGMA writable_schema = ON');
            DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->update(['sql' => $changed]);
            DB::unprepared('PRAGMA writable_schema = OFF');
            DB::unprepared('PRAGMA schema_version = '.($version + 1));

            return;
        }
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        DB::statement("ALTER TABLE users MODIFY role ENUM('".implode("','", $roles)."') NOT NULL");
    }

    private function tables(): void
    {
        if (! Schema::hasTable(self::TABLES[0])) {
            Schema::create(self::TABLES[0], function (Blueprint $t): void {
                $t->id();
                $t->foreignId('ib39_surfaced_former_rebel_id')->constrained()->restrictOnDelete();
                $t->foreignId('triggering_cdr_document_version_id')->constrained('ib39_cdr_document_versions')->restrictOnDelete();
                $t->string('status', 30);
                $t->timestamp('received_at');
                $t->timestamp('due_at');
                $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('started_at')->nullable();
                $t->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('completed_at')->nullable();
                $t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('cancelled_at')->nullable();
                $t->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $t->text('control_number')->nullable();
                $t->string('control_number_hash', 64)->nullable();
                $t->unsignedInteger('lock_version')->default(0);
                $t->unsignedBigInteger('current_final_version_id')->nullable();
                if (DB::getDriverName() !== 'pgsql') {
                    $t->foreign('current_final_version_id')->references('id')->on(self::TABLES[4])->restrictOnDelete();
                }
                $t->timestamps();
            });
        } else {
            $this->columns(self::TABLES[0], [
                'ib39_surfaced_former_rebel_id' => fn ($t) => $t->unsignedBigInteger('ib39_surfaced_former_rebel_id')->nullable(),
                'triggering_cdr_document_version_id' => fn ($t) => $t->unsignedBigInteger('triggering_cdr_document_version_id')->nullable(),
                'status' => fn ($t) => $t->string('status', 30)->nullable(),
                'received_at' => fn ($t) => $t->timestamp('received_at')->nullable(), 'due_at' => fn ($t) => $t->timestamp('due_at')->nullable(),
                'assigned_to' => fn ($t) => $t->unsignedBigInteger('assigned_to')->nullable(), 'started_at' => fn ($t) => $t->timestamp('started_at')->nullable(),
                'started_by' => fn ($t) => $t->unsignedBigInteger('started_by')->nullable(), 'completed_at' => fn ($t) => $t->timestamp('completed_at')->nullable(),
                'completed_by' => fn ($t) => $t->unsignedBigInteger('completed_by')->nullable(), 'cancelled_at' => fn ($t) => $t->timestamp('cancelled_at')->nullable(),
                'cancelled_by' => fn ($t) => $t->unsignedBigInteger('cancelled_by')->nullable(), 'control_number' => fn ($t) => $t->text('control_number')->nullable(),
                'control_number_hash' => fn ($t) => $t->string('control_number_hash', 64)->nullable(),
                'lock_version' => fn ($t) => $t->unsignedInteger('lock_version')->default(0),
                'current_final_version_id' => fn ($t) => $t->unsignedBigInteger('current_final_version_id')->nullable(),
                'created_at' => fn ($t) => $t->timestamp('created_at')->nullable(), 'updated_at' => fn ($t) => $t->timestamp('updated_at')->nullable(),
            ]);
            if (Schema::hasColumn(self::TABLES[0], 'received_on')) {
                DB::table(self::TABLES[0])->whereNull('received_at')
                    ->update(['received_at' => DB::raw('received_on'), 'due_at' => DB::raw('due_on')]);
            }
        }
        $this->simpleTable(self::TABLES[1], function (Blueprint $t): void {
            $t->id();
            $t->foreignId('processing_id')->constrained(self::TABLES[0])->cascadeOnDelete();
            $t->longText('payload');
            $t->unsignedSmallInteger('schema_version')->default(1);
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('last_saved_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('last_saved_at');
            $t->timestamps();
        }, ['processing_id' => fn ($t) => $t->unsignedBigInteger('processing_id')->nullable(),
            'payload' => fn ($t) => $t->longText('payload')->nullable(), 'schema_version' => fn ($t) => $t->unsignedSmallInteger('schema_version')->default(1),
            'revision' => fn ($t) => $t->unsignedInteger('revision')->default(1), 'last_saved_by' => fn ($t) => $t->unsignedBigInteger('last_saved_by')->nullable(),
            'last_saved_at' => fn ($t) => $t->timestamp('last_saved_at')->nullable(), 'created_at' => fn ($t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn ($t) => $t->timestamp('updated_at')->nullable()]);
        $this->simpleTable(self::TABLES[2], function (Blueprint $t): void {
            $t->id();
            $t->foreignId('processing_id')->constrained(self::TABLES[0])->cascadeOnDelete();
            $t->unsignedInteger('revision');
            $t->longText('payload');
            $t->foreignId('saved_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('saved_at');
        }, ['processing_id' => fn ($t) => $t->unsignedBigInteger('processing_id')->nullable(),
            'revision' => fn ($t) => $t->unsignedInteger('revision')->nullable(),
            'payload' => fn ($t) => $t->longText('payload')->nullable(),
            'saved_by' => fn ($t) => $t->unsignedBigInteger('saved_by')->nullable(),
            'saved_at' => fn ($t) => $t->timestamp('saved_at')->nullable()]);
        $this->simpleTable(self::TABLES[3], function (Blueprint $t): void {
            $t->id();
            $t->foreignId('processing_id')->constrained(self::TABLES[0])->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->string('event', 60);
            $t->text('remarks')->nullable();
            $t->text('delay_reason')->nullable();
            $t->unsignedBigInteger('document_version_id')->nullable();
            if (DB::getDriverName() !== 'pgsql') {
                $t->foreign('document_version_id')->references('id')->on(self::TABLES[4])->nullOnDelete();
            }
            $t->json('metadata')->nullable();
            $t->timestamp('occurred_at');
        }, ['actor_id' => fn ($t) => $t->unsignedBigInteger('actor_id')->nullable(),
            'from_status' => fn ($t) => $t->string('from_status', 30)->nullable(), 'to_status' => fn ($t) => $t->string('to_status', 30)->nullable(),
            'event' => fn ($t) => $t->string('event', 60)->nullable(), 'remarks' => fn ($t) => $t->text('remarks')->nullable(),
            'delay_reason' => fn ($t) => $t->text('delay_reason')->nullable(), 'document_version_id' => fn ($t) => $t->unsignedBigInteger('document_version_id')->nullable(),
            'metadata' => fn ($t) => $t->json('metadata')->nullable(), 'occurred_at' => fn ($t) => $t->timestamp('occurred_at')->nullable()]);
        $this->simpleTable(self::TABLES[4], function (Blueprint $t): void {
            $t->id();
            $t->foreignId('processing_id')->constrained(self::TABLES[0])->cascadeOnDelete();
            $t->unsignedInteger('version_number');
            $t->foreignId('replaces_version_id')->nullable()->constrained(self::TABLES[4])->restrictOnDelete();
            $t->text('replacement_reason')->nullable();
            $t->string('storage_path', 500);
            $t->string('original_filename');
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size_bytes');
            $t->string('sha256', 64);
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->boolean('all_signatories_confirmed');
            $t->boolean('correct_final_confirmed');
            $t->timestamp('uploaded_at');
            $t->timestamps();
        }, ['processing_id' => fn ($t) => $t->unsignedBigInteger('processing_id')->nullable(),
            'version_number' => fn ($t) => $t->unsignedInteger('version_number')->nullable(),
            'replaces_version_id' => fn ($t) => $t->unsignedBigInteger('replaces_version_id')->nullable(),
            'replacement_reason' => fn ($t) => $t->text('replacement_reason')->nullable(),
            'storage_path' => fn ($t) => $t->string('storage_path', 500)->nullable(),
            'original_filename' => fn ($t) => $t->string('original_filename')->nullable(),
            'mime_type' => fn ($t) => $t->string('mime_type', 100)->nullable(),
            'size_bytes' => fn ($t) => $t->unsignedBigInteger('size_bytes')->nullable(),
            'sha256' => fn ($t) => $t->string('sha256', 64)->nullable(),
            'uploaded_by' => fn ($t) => $t->unsignedBigInteger('uploaded_by')->nullable(),
            'all_signatories_confirmed' => fn ($t) => $t->boolean('all_signatories_confirmed')->default(false),
            'correct_final_confirmed' => fn ($t) => $t->boolean('correct_final_confirmed')->default(false)]);
    }

    private function simpleTable(string $table, callable $create, array $columns = []): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $create);
        } else {
            $this->columns($table, $columns);
        }
    }

    private function columns(string $table, array $columns): void
    {
        foreach ($columns as $name => $add) {
            if (! Schema::hasColumn($table, $name)) {
                Schema::table($table, $add);
            }
        }
    }

    private function indexes(): void
    {
        $this->idx(self::TABLES[0], ['ib39_surfaced_former_rebel_id'], 'japic_processing_fr_unique', true);
        $this->idx(self::TABLES[0], ['control_number_hash'], 'japic_processing_control_hash_unique', true);
        $this->idx(self::TABLES[0], ['status'], 'japic_processing_status_index');
        $this->idx(self::TABLES[1], ['processing_id'], 'japic_draft_processing_unique', true);
        $this->idx(self::TABLES[2], ['processing_id', 'revision'], 'japic_draft_history_revision_unique', true);
        $this->idx(self::TABLES[3], ['processing_id', 'occurred_at'], 'japic_history_processing_occurred_index');
        $this->idx(self::TABLES[4], ['processing_id', 'version_number'], 'japic_document_processing_version_unique', true);
    }

    private function idx(string $table, array $columns, string $name, bool $unique = false): void
    {
        if (collect(Schema::getIndexes($table))->contains(fn ($i) => ($i['name'] ?? null) === $name
            && ($i['columns'] ?? []) === $columns && (bool) ($i['unique'] ?? false) === $unique)) {
            return;
        }
        Schema::table($table, fn (Blueprint $t) => $unique ? $t->unique($columns, $name) : $t->index($columns, $name));
    }

    private function isCanonical(): bool
    {
        $columns = [
            self::TABLES[0] => ['id', 'ib39_surfaced_former_rebel_id', 'triggering_cdr_document_version_id', 'status',
                'received_at', 'due_at', 'assigned_to', 'started_at', 'started_by', 'completed_at', 'completed_by',
                'cancelled_at', 'cancelled_by', 'control_number', 'control_number_hash', 'lock_version',
                'current_final_version_id', 'created_at', 'updated_at'],
            self::TABLES[1] => ['id', 'processing_id', 'payload', 'schema_version', 'revision', 'last_saved_by',
                'last_saved_at', 'created_at', 'updated_at'],
            self::TABLES[2] => ['id', 'processing_id', 'revision', 'payload', 'saved_by', 'saved_at'],
            self::TABLES[3] => ['id', 'processing_id', 'actor_id', 'from_status', 'to_status', 'event', 'remarks',
                'delay_reason', 'document_version_id', 'metadata', 'occurred_at'],
            self::TABLES[4] => ['id', 'processing_id', 'version_number', 'replaces_version_id', 'replacement_reason',
                'storage_path', 'original_filename', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by',
                'all_signatories_confirmed', 'correct_final_confirmed', 'uploaded_at', 'created_at', 'updated_at'],
        ];
        foreach ($columns as $table => $expected) {
            if (Schema::getColumnListing($table) !== $expected) {
                return false;
            }
        }

        $indexes = [
            [self::TABLES[0], ['ib39_surfaced_former_rebel_id'], true],
            [self::TABLES[0], ['control_number_hash'], true],
            [self::TABLES[0], ['status'], false],
            [self::TABLES[1], ['processing_id'], true],
            [self::TABLES[2], ['processing_id', 'revision'], true],
            [self::TABLES[3], ['processing_id', 'occurred_at'], false],
            [self::TABLES[4], ['processing_id', 'version_number'], true],
        ];
        foreach ($indexes as [$table, $expectedColumns, $unique]) {
            if (! collect(Schema::getIndexes($table))->contains(fn (array $index): bool => ($index['columns'] ?? []) === $expectedColumns && (bool) ($index['unique'] ?? false) === $unique)) {
                return false;
            }
        }

        return $this->canonicalForeignKeys()
            && (DB::getDriverName() !== 'sqlite' || $this->canonicalSqliteTriggers());
    }

    private function canonicalForeignKeys(): bool
    {
        $expected = [
            [self::TABLES[0], 'ib39_surfaced_former_rebel_id', 'ib39_surfaced_former_rebels', 'id', 'restrict'],
            [self::TABLES[0], 'triggering_cdr_document_version_id', 'ib39_cdr_document_versions', 'id', 'restrict'],
            [self::TABLES[0], 'current_final_version_id', self::TABLES[4], 'id', 'restrict'],
            [self::TABLES[1], 'processing_id', self::TABLES[0], 'id', 'cascade'],
            [self::TABLES[2], 'processing_id', self::TABLES[0], 'id', 'cascade'],
            [self::TABLES[3], 'processing_id', self::TABLES[0], 'id', 'cascade'],
            [self::TABLES[3], 'document_version_id', self::TABLES[4], 'id', 'set null'],
            [self::TABLES[4], 'processing_id', self::TABLES[0], 'id', 'cascade'],
            [self::TABLES[4], 'replaces_version_id', self::TABLES[4], 'id', 'restrict'],
        ];
        foreach ($expected as [$table, $column, $target, $targetColumn, $delete]) {
            if (! collect(Schema::getForeignKeys($table))->contains(fn (array $key): bool => ($key['columns'] ?? []) === [$column] && ($key['foreign_table'] ?? null) === $target
                && ($key['foreign_columns'] ?? []) === [$targetColumn]
                && strtolower((string) ($key['on_delete'] ?? '')) === $delete)) {
                return false;
            }
        }

        return true;
    }

    private function canonicalSqliteTriggers(): bool
    {
        $expected = ['japic_processing_status_insert', 'japic_processing_status_update',
            'japic_processing_deadline_insert', 'japic_processing_deadline_update',
            'japic_current_document_insert', 'japic_current_document_update'];

        return DB::table('sqlite_master')->where('type', 'trigger')->whereIn('name', $expected)->count() === count($expected);
    }

    private function foreignKeys(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        foreach ([
            [self::TABLES[0], 'current_final_version_id', self::TABLES[4], 'japic_processing_current_final_fk', false],
            [self::TABLES[3], 'document_version_id', self::TABLES[4], 'japic_history_document_fk', true],
        ] as [$table, $column, $target, $name, $nullOnDelete]) {
            $exists = collect(Schema::getForeignKeys($table))
                ->contains(fn (array $key): bool => ($key['columns'] ?? []) === [$column]);
            if (! $exists) {
                Schema::table($table, function (Blueprint $blueprint) use ($column, $name, $target, $nullOnDelete): void {
                    $foreign = $blueprint->foreign($column, $name)->references('id')->on($target);
                    $nullOnDelete ? $foreign->nullOnDelete() : $foreign->restrictOnDelete();
                });
            }
        }
    }

    private function backfill(): void
    {
        $rows = DB::table('ib39_cdr_processings as c')
            ->join('ib39_cdr_document_versions as v', fn ($j) => $j->on('v.id', '=', 'c.current_final_version_id')->on('v.cdr_processing_id', '=', 'c.id'))
            ->leftJoin('ib39_fr_cancellations as x', 'x.ib39_surfaced_former_rebel_id', '=', 'c.ib39_surfaced_former_rebel_id')
            ->where('c.status', 'Completed')->whereNull('x.id')->whereNotNull('v.finalized_at')
            ->select('c.ib39_surfaced_former_rebel_id', 'c.current_final_version_id', 'c.completed_at', 'v.finalized_at')->get();
        foreach ($rows as $row) {
            $received = Carbon::parse($row->completed_at ?? $row->finalized_at);
            $id = DB::table(self::TABLES[0])->where('ib39_surfaced_former_rebel_id', $row->ib39_surfaced_former_rebel_id)->value('id');
            if (! $id) {
                $id = DB::table(self::TABLES[0])->insertGetId([
                    'ib39_surfaced_former_rebel_id' => $row->ib39_surfaced_former_rebel_id,
                    'triggering_cdr_document_version_id' => $row->current_final_version_id, 'status' => JapicCertificationStatus::Pending->value,
                    'received_at' => $received, 'due_at' => $received->copy()->addDays(14), 'lock_version' => 0,
                    'created_at' => now(), 'updated_at' => now()]);
            }
            if (! DB::table(self::TABLES[3])->where('processing_id', $id)->where('event', JapicCertificationEvent::IntakeBackfilled->value)->exists()) {
                DB::table(self::TABLES[3])->insert(['processing_id' => $id, 'from_status' => null,
                    'to_status' => JapicCertificationStatus::Pending->value, 'event' => JapicCertificationEvent::IntakeBackfilled->value,
                    'occurred_at' => $received]);
            }
        }
    }

    private function guards(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $statuses = "'".implode("','", array_column(JapicCertificationStatus::cases(), 'value'))."'";
            DB::statement("ALTER TABLE ".self::TABLES[0]." ADD CONSTRAINT japic_processing_status_check CHECK (status IN ({$statuses}))");
            DB::statement('ALTER TABLE '.self::TABLES[0]." ADD CONSTRAINT japic_processing_deadline_check CHECK (due_at = received_at + INTERVAL '14 days')");
            DB::statement('ALTER TABLE '.self::TABLES[4].' ADD CONSTRAINT japic_document_owner_unique UNIQUE (id, processing_id)');
            DB::statement('ALTER TABLE '.self::TABLES[0].' ADD CONSTRAINT japic_current_document_owner_foreign FOREIGN KEY (current_final_version_id, id) REFERENCES '.self::TABLES[4].' (id, processing_id)');

            return;
        }
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        $statuses = implode("','", array_column(JapicCertificationStatus::cases(), 'value'));
        foreach (['insert' => 'INSERT', 'update' => 'UPDATE OF status'] as $s => $op) {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS japic_processing_status_{$s} BEFORE {$op} ON ".self::TABLES[0]." WHEN NEW.status NOT IN ('{$statuses}') BEGIN SELECT RAISE(ABORT, 'Invalid JAPIC status'); END");
        }
        foreach (['insert' => 'INSERT', 'update' => 'UPDATE OF received_at, due_at'] as $s => $op) {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS japic_processing_deadline_{$s} BEFORE {$op} ON ".self::TABLES[0]." WHEN datetime(NEW.due_at) <> datetime(NEW.received_at, '+14 days') BEGIN SELECT RAISE(ABORT, 'Invalid JAPIC deadline'); END");
        }
        foreach (['insert' => 'INSERT', 'update' => 'UPDATE OF current_final_version_id'] as $s => $op) {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS japic_current_document_{$s} BEFORE {$op} ON ".self::TABLES[0].' WHEN NEW.current_final_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM '.self::TABLES[4]." v WHERE v.id=NEW.current_final_version_id AND v.processing_id=NEW.id) BEGIN SELECT RAISE(ABORT, 'Invalid JAPIC current document'); END");
        }
    }
};
