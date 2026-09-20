<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DRAFT_SLOTS = ['primary', 'justification_surrendered', 'justification_comparison'];

    private const FINAL_SLOTS = ['primary', 'final_primary', 'justification_surrendered', 'justification_comparison'];

    private const PRELIMINARY_EVENTS = ['processing_started', 'compliance_changed', 'remarks_changed', 'delay_changed'];

    private const FINAL_EVENTS = ['processing_started', 'compliance_changed', 'remarks_changed', 'delay_changed', 'completed'];

    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $this->alterMysqlEnums(self::FINAL_SLOTS, self::FINAL_EVENTS);
        } elseif ($driver === 'sqlite') {
            $this->rebuildSqliteEnumTables(self::FINAL_SLOTS, self::FINAL_EVENTS);
        } elseif ($driver === 'pgsql') {
            $this->alterPostgresChecks(self::FINAL_SLOTS, self::FINAL_EVENTS);
        } else {
            throw new RuntimeException('Unsupported database driver for FEA final-version migration.');
        }

        Schema::table('ib39_fea_document_versions', function (Blueprint $table): void {
            $table->unique(['fea_document_id', 'id'], 'ib39_fea_version_owner_unique');
        });
        Schema::table('ib39_fea_documents', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_final_version_id')->nullable()->after('current_draft_version_id');
            $table->foreign(['id', 'current_final_version_id'], 'ib39_fea_current_final_owner_foreign')
                ->references(['fea_document_id', 'id'])->on('ib39_fea_document_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('ib39_fea_document_versions')->where('slot', 'final_primary')->exists()
            || DB::table('ib39_fea_upload_histories')->where('slot', 'final_primary')->exists()
            || DB::table('ib39_fea_documents')->whereNotNull('current_final_version_id')->exists()
            || DB::table('ib39_fea_document_histories')->where('event', 'completed')->exists()) {
            throw new RuntimeException('Cannot roll back FEA final-version schema while final versions, pointers, or completed histories exist.');
        }

        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            throw new RuntimeException('Unsupported database driver for FEA final-version migration.');
        }
        if ($driver === 'sqlite') {
            // SQLite ignores foreign_keys changes inside the test transaction.
            // Defer checks until the referenced tables have been rebuilt.
            DB::statement('PRAGMA defer_foreign_keys = ON');
        }

        Schema::table('ib39_fea_documents', function (Blueprint $table) use ($driver): void {
            $table->dropForeign($driver === 'sqlite' ? ['id', 'current_final_version_id'] : 'ib39_fea_current_final_owner_foreign');
            $table->dropColumn('current_final_version_id');
        });
        Schema::table('ib39_fea_document_versions', function (Blueprint $table): void {
            $table->dropUnique('ib39_fea_version_owner_unique');
        });

        if ($driver === 'mysql') {
            $this->alterMysqlEnums(self::DRAFT_SLOTS, self::PRELIMINARY_EVENTS);
        } elseif ($driver === 'sqlite') {
            $this->rebuildSqliteEnumTables(self::DRAFT_SLOTS, self::PRELIMINARY_EVENTS);
        } elseif ($driver === 'pgsql') {
            $this->alterPostgresChecks(self::DRAFT_SLOTS, self::PRELIMINARY_EVENTS);
        } else {
            throw new RuntimeException('Unsupported database driver for FEA final-version migration.');
        }
    }

    private function alterMysqlEnums(array $slots, array $events): void
    {
        $slotValues = implode(', ', array_map(fn (string $value): string => "'{$value}'", $slots));
        $eventValues = implode(', ', array_map(fn (string $value): string => "'{$value}'", $events));
        DB::statement("ALTER TABLE ib39_fea_document_versions MODIFY slot ENUM({$slotValues}) NOT NULL");
        DB::statement("ALTER TABLE ib39_fea_upload_histories MODIFY slot ENUM({$slotValues}) NOT NULL");
        DB::statement("ALTER TABLE ib39_fea_document_histories MODIFY event ENUM({$eventValues}) NOT NULL");
    }

    private function alterPostgresChecks(array $slots, array $events): void
    {
        foreach (['ib39_fea_document_versions', 'ib39_fea_upload_histories'] as $table) {
            $values = implode(', ', array_map(fn (string $value): string => "'{$value}'", $slots));
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_slot_check");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_slot_check CHECK (slot IN ({$values}))");
        }
        $values = implode(', ', array_map(fn (string $value): string => "'{$value}'", $events));
        DB::statement('ALTER TABLE ib39_fea_document_histories DROP CONSTRAINT IF EXISTS ib39_fea_document_histories_event_check');
        DB::statement("ALTER TABLE ib39_fea_document_histories ADD CONSTRAINT ib39_fea_document_histories_event_check CHECK (event IN ({$values}))");
    }

    private function rebuildSqliteEnumTables(array $slots, array $events): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($slots, $events): void {
                Schema::create('ib39_fea_document_versions_final_migration', function (Blueprint $table) use ($slots): void {
                    $table->id();
                    $table->foreignId('fea_document_id')->constrained('ib39_fea_documents')->restrictOnDelete();
                    $table->foreignId('fea_processing_id')->constrained('ib39_fea_processings')->restrictOnDelete();
                    $table->enum('slot', $slots);
                    $table->unsignedInteger('version_number');
                    $table->foreignId('replaces_version_id')->nullable()->constrained('ib39_fea_document_versions')->restrictOnDelete();
                    $table->text('replacement_reason')->nullable();
                    $table->string('storage_path', 500);
                    $table->string('original_filename');
                    $table->string('mime_type', 100);
                    $table->unsignedBigInteger('size_bytes');
                    $table->char('sha256', 64);
                    $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
                    $table->timestamps();
                });
                DB::statement('INSERT INTO ib39_fea_document_versions_final_migration SELECT * FROM ib39_fea_document_versions');

                Schema::create('ib39_fea_upload_histories_final_migration', function (Blueprint $table) use ($slots): void {
                    $table->id();
                    $table->foreignId('fea_document_id')->constrained('ib39_fea_documents')->restrictOnDelete();
                    $table->foreignId('fea_processing_id')->constrained('ib39_fea_processings')->restrictOnDelete();
                    $table->foreignId('fea_document_version_id')->constrained('ib39_fea_document_versions')->restrictOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('event', 40);
                    $table->enum('slot', $slots);
                    $table->unsignedInteger('version_number');
                    $table->timestamps();
                });
                DB::statement('INSERT INTO ib39_fea_upload_histories_final_migration SELECT * FROM ib39_fea_upload_histories');

                Schema::create('ib39_fea_document_histories_final_migration', function (Blueprint $table) use ($events): void {
                    $table->id();
                    $table->foreignId('fea_document_id')->constrained('ib39_fea_documents')->restrictOnDelete();
                    $table->foreignId('fea_processing_id')->constrained('ib39_fea_processings')->restrictOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->enum('event', $events);
                    $table->text('previous_values')->nullable();
                    $table->text('new_values')->nullable();
                    $table->timestamps();
                });
                DB::statement('INSERT INTO ib39_fea_document_histories_final_migration SELECT * FROM ib39_fea_document_histories');

                Schema::drop('ib39_fea_upload_histories');
                Schema::drop('ib39_fea_document_histories');
                Schema::drop('ib39_fea_document_versions');
                Schema::rename('ib39_fea_document_versions_final_migration', 'ib39_fea_document_versions');
                Schema::rename('ib39_fea_upload_histories_final_migration', 'ib39_fea_upload_histories');
                Schema::rename('ib39_fea_document_histories_final_migration', 'ib39_fea_document_histories');

                Schema::table('ib39_fea_document_versions', function (Blueprint $table): void {
                    $table->unique(['fea_document_id', 'slot', 'version_number'], 'ib39_fea_version_number_unique');
                    $table->index(['fea_processing_id', 'created_at'], 'ib39_fea_version_processing_index');
                });
                Schema::table('ib39_fea_upload_histories', function (Blueprint $table): void {
                    $table->index(['fea_document_id', 'created_at'], 'ib39_fea_upload_history_document_index');
                });
                Schema::table('ib39_fea_document_histories', function (Blueprint $table): void {
                    $table->index(['fea_document_id', 'created_at'], 'ib39_fea_document_history_created_index');
                    $table->index(['fea_processing_id', 'created_at'], 'ib39_fea_processing_document_history_index');
                });
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
