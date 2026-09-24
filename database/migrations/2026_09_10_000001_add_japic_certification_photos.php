<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const PROCESSINGS = 'japic_certification_processings';

    private const PHOTOS = 'japic_certification_photo_versions';

    private const TRIGGERS = [
        'japic_photo_version_update_guard',
        'japic_photo_version_delete_guard',
        'japic_photo_replacement_owner_insert',
        'japic_current_photo_owner_insert',
        'japic_current_photo_owner_update',
    ];

    public function up(): void
    {
        $this->assertSupportedDriver();
        if (DB::connection()->pretending()) {
            $this->emitFreshSchemaSql();

            return;
        }
        $this->assertSafeExistingState();

        DB::transaction(function (): void {
            if (Schema::hasTable(self::PHOTOS) && ! $this->isCanonicalPhotoTable()) {
                Schema::drop(self::PHOTOS);
            }
            if (! Schema::hasTable(self::PHOTOS)) {
                $this->createPhotoTable();
            }
            if (! Schema::hasColumn(self::PROCESSINGS, 'current_photo_version_id')) {
                Schema::table(self::PROCESSINGS, fn (Blueprint $table) => $table->unsignedBigInteger('current_photo_version_id')->nullable());
            }

            $this->ensureIndex(self::PROCESSINGS, ['current_photo_version_id'], 'japic_processing_current_photo_index');
            $this->ensureMysqlCurrentPhotoForeignKey();
            $this->ensureTriggers();
        });
    }

    private function emitFreshSchemaSql(): void
    {
        $this->createPhotoTable();
        Schema::table(self::PROCESSINGS, fn (Blueprint $table) => $table->unsignedBigInteger('current_photo_version_id')->nullable());
        Schema::table(self::PROCESSINGS, fn (Blueprint $table) => $table->index(['current_photo_version_id'], 'japic_processing_current_photo_index'));
        $this->ensureMysqlCurrentPhotoForeignKey();
        $this->ensureTriggers();
    }

    public function down(): void
    {
        $hasRows = Schema::hasTable(self::PHOTOS) && DB::table(self::PHOTOS)->exists();
        $hasPointers = Schema::hasColumn(self::PROCESSINGS, 'current_photo_version_id')
            && DB::table(self::PROCESSINGS)->whereNotNull('current_photo_version_id')->exists();
        $hasFiles = collect(Storage::disk('local')->allFiles('japic/certifications'))
            ->contains(fn (string $path): bool => str_contains($path, '/photos/'));

        if ($hasRows || $hasPointers || $hasFiles) {
            throw new RuntimeException('JAPIC certification photo rollback refused: photo rows, selected references, or managed photo files exist.');
        }

        DB::transaction(function (): void {
            $this->dropTriggers();
            if (Schema::hasColumn(self::PROCESSINGS, 'current_photo_version_id')) {
                if (DB::getDriverName() === 'mysql') {
                    $foreign = collect(Schema::getForeignKeys(self::PROCESSINGS))
                        ->first(fn (array $key): bool => ($key['name'] ?? null) === 'japic_processing_current_photo_fk');
                    if ($foreign) {
                        Schema::table(self::PROCESSINGS, fn (Blueprint $table) => $table->dropForeign('japic_processing_current_photo_fk'));
                    }
                }
                Schema::table(self::PROCESSINGS, function (Blueprint $table): void {
                    if (collect(Schema::getIndexes(self::PROCESSINGS))->contains(fn (array $index): bool => ($index['name'] ?? null) === 'japic_processing_current_photo_index')) {
                        $table->dropIndex('japic_processing_current_photo_index');
                    }
                    $table->dropColumn('current_photo_version_id');
                });
            }
            Schema::dropIfExists(self::PHOTOS);
        });
    }

    private function createPhotoTable(): void
    {
        Schema::create(self::PHOTOS, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('processing_id')->constrained(self::PROCESSINGS)->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->unsignedBigInteger('replaces_version_id')->nullable();
            $table->string('storage_path', 500);
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->unique(['processing_id', 'version_number'], 'japic_photo_processing_version_unique');
            $table->unique(['id', 'processing_id'], 'japic_photo_id_processing_unique');
            $table->foreign(['replaces_version_id', 'processing_id'], 'japic_photo_replacement_processing_fk')
                ->references(['id', 'processing_id'])->on(self::PHOTOS)->restrictOnDelete();
        });
    }

    private function assertSafeExistingState(): void
    {
        if (! Schema::hasTable(self::PROCESSINGS)) {
            throw new RuntimeException('JAPIC certification photo migration refused: the certification processing table is missing.');
        }
        if (Schema::hasTable(self::PHOTOS) && DB::table(self::PHOTOS)->exists() && ! $this->isCanonicalPhotoTable()) {
            throw new RuntimeException('JAPIC certification photo migration refused: the photo table is populated and incompatible.');
        }
        if (Schema::hasColumn(self::PROCESSINGS, 'current_photo_version_id')) {
            $column = collect(Schema::getColumns(self::PROCESSINGS))->firstWhere('name', 'current_photo_version_id');
            $integer = str_contains(strtolower((string) ($column['type_name'] ?? $column['type'] ?? '')), 'int');
            if (! $integer || ! ($column['nullable'] ?? false)) {
                throw new RuntimeException('JAPIC certification photo migration refused: current_photo_version_id is incompatible.');
            }
            if (DB::table(self::PROCESSINGS)->whereNotNull('current_photo_version_id')->exists()
                && (! Schema::hasTable(self::PHOTOS) || ! $this->isCanonicalPhotoTable())) {
                throw new RuntimeException('JAPIC certification photo migration refused: selected photo pointers depend on incompatible objects.');
            }
        }
    }

    private function isCanonicalPhotoTable(): bool
    {
        if (! Schema::hasTable(self::PHOTOS)) {
            return false;
        }
        $expected = ['id', 'processing_id', 'version_number', 'replaces_version_id', 'storage_path', 'original_filename',
            'mime_type', 'size_bytes', 'width', 'height', 'sha256', 'uploaded_by', 'uploaded_at'];
        if (Schema::getColumnListing(self::PHOTOS) !== $expected) {
            return false;
        }
        $indexes = collect(Schema::getIndexes(self::PHOTOS));

        return $indexes->contains(fn (array $index): bool => ($index['name'] ?? null) === 'japic_photo_processing_version_unique'
                && ($index['columns'] ?? []) === ['processing_id', 'version_number'] && ($index['unique'] ?? false))
            && $indexes->contains(fn (array $index): bool => ($index['name'] ?? null) === 'japic_photo_id_processing_unique'
                && ($index['columns'] ?? []) === ['id', 'processing_id'] && ($index['unique'] ?? false));
    }

    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if (! collect(Schema::getIndexes($table))->contains(fn (array $index): bool => ($index['name'] ?? null) === $name
            && ($index['columns'] ?? []) === $columns && ! ($index['unique'] ?? false))) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
        }
    }

    private function ensureMysqlCurrentPhotoForeignKey(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $exists = collect(Schema::getForeignKeys(self::PROCESSINGS))
            ->contains(fn (array $key): bool => ($key['name'] ?? null) === 'japic_processing_current_photo_fk');
        if (! $exists) {
            Schema::table(self::PROCESSINGS, fn (Blueprint $table) => $table
                ->foreign('current_photo_version_id', 'japic_processing_current_photo_fk')
                ->references('id')->on(self::PHOTOS)->restrictOnDelete());
        }
    }

    private function ensureTriggers(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE '.self::PROCESSINGS.' ADD CONSTRAINT japic_current_photo_owner_foreign FOREIGN KEY (current_photo_version_id, id) REFERENCES '.self::PHOTOS.' (id, processing_id)');
            DB::unprepared("CREATE FUNCTION japic_photo_immutable_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'JAPIC photo versions are immutable'; END; \$\$");
            DB::statement('CREATE TRIGGER japic_photo_version_update_guard BEFORE UPDATE ON '.self::PHOTOS.' FOR EACH ROW EXECUTE FUNCTION japic_photo_immutable_guard()');
            DB::statement('CREATE TRIGGER japic_photo_version_delete_guard BEFORE DELETE ON '.self::PHOTOS.' FOR EACH ROW EXECUTE FUNCTION japic_photo_immutable_guard()');

            return;
        }
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('CREATE TRIGGER IF NOT EXISTS japic_photo_version_update_guard BEFORE UPDATE ON '.self::PHOTOS." BEGIN SELECT RAISE(ABORT, 'JAPIC photo versions are immutable'); END");
            DB::unprepared('CREATE TRIGGER IF NOT EXISTS japic_photo_version_delete_guard BEFORE DELETE ON '.self::PHOTOS." BEGIN SELECT RAISE(ABORT, 'JAPIC photo versions are immutable'); END");
            DB::unprepared('CREATE TRIGGER IF NOT EXISTS japic_photo_replacement_owner_insert BEFORE INSERT ON '.self::PHOTOS.' WHEN NEW.replaces_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM '.self::PHOTOS." p WHERE p.id = NEW.replaces_version_id AND p.processing_id = NEW.processing_id) BEGIN SELECT RAISE(ABORT, 'Invalid JAPIC photo replacement owner'); END");
            DB::unprepared('CREATE TRIGGER IF NOT EXISTS japic_current_photo_owner_insert BEFORE INSERT ON '.self::PROCESSINGS.' WHEN NEW.current_photo_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM '.self::PHOTOS." p WHERE p.id = NEW.current_photo_version_id AND p.processing_id = NEW.id) BEGIN SELECT RAISE(ABORT, 'Invalid JAPIC current photo owner'); END");
            DB::unprepared('CREATE TRIGGER IF NOT EXISTS japic_current_photo_owner_update BEFORE UPDATE OF current_photo_version_id ON '.self::PROCESSINGS.' WHEN NEW.current_photo_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM '.self::PHOTOS." p WHERE p.id = NEW.current_photo_version_id AND p.processing_id = NEW.id) BEGIN SELECT RAISE(ABORT, 'Invalid JAPIC current photo owner'); END");

            return;
        }

        foreach ($this->mysqlTriggerStatements() as $name => $statement) {
            $exists = DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->where('TRIGGER_NAME', $name)->exists();
            if (! $exists) {
                DB::unprepared($statement);
            }
        }
    }

    private function mysqlTriggerStatements(): array
    {
        return [
            'japic_photo_version_update_guard' => 'CREATE TRIGGER japic_photo_version_update_guard BEFORE UPDATE ON '.self::PHOTOS." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'JAPIC photo versions are immutable'",
            'japic_photo_version_delete_guard' => 'CREATE TRIGGER japic_photo_version_delete_guard BEFORE DELETE ON '.self::PHOTOS." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'JAPIC photo versions are immutable'",
            'japic_photo_replacement_owner_insert' => 'CREATE TRIGGER japic_photo_replacement_owner_insert BEFORE INSERT ON '.self::PHOTOS.' FOR EACH ROW BEGIN IF NEW.replaces_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM '.self::PHOTOS." p WHERE p.id = NEW.replaces_version_id AND p.processing_id = NEW.processing_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid JAPIC photo replacement owner'; END IF; END",
            'japic_current_photo_owner_insert' => 'CREATE TRIGGER japic_current_photo_owner_insert BEFORE INSERT ON '.self::PROCESSINGS.' FOR EACH ROW BEGIN IF NEW.current_photo_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM '.self::PHOTOS." p WHERE p.id = NEW.current_photo_version_id AND p.processing_id = NEW.id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid JAPIC current photo owner'; END IF; END",
            'japic_current_photo_owner_update' => 'CREATE TRIGGER japic_current_photo_owner_update BEFORE UPDATE ON '.self::PROCESSINGS.' FOR EACH ROW BEGIN IF NEW.current_photo_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM '.self::PHOTOS." p WHERE p.id = NEW.current_photo_version_id AND p.processing_id = NEW.id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid JAPIC current photo owner'; END IF; END",
        ];
    }

    private function dropTriggers(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['japic_photo_version_update_guard', 'japic_photo_version_delete_guard'] as $trigger) {
                DB::statement('DROP TRIGGER IF EXISTS '.$trigger.' ON '.self::PHOTOS);
            }
            DB::statement('DROP FUNCTION IF EXISTS japic_photo_immutable_guard()');

            return;
        }
        foreach (self::TRIGGERS as $trigger) {
            DB::unprepared('DROP TRIGGER IF EXISTS '.$trigger);
        }
    }

    private function assertSupportedDriver(): void
    {
        if (! in_array(DB::getDriverName(), ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new RuntimeException('JAPIC certification photo migration does not support the configured database driver.');
        }
    }
};
