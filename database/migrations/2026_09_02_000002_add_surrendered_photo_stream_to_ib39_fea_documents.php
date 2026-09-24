<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE ib39_fea_document_versions MODIFY slot ENUM('primary', 'justification_surrendered', 'justification_comparison') NOT NULL");
            DB::statement("ALTER TABLE ib39_fea_upload_histories MODIFY slot ENUM('primary', 'justification_surrendered', 'justification_comparison') NOT NULL");
        } elseif ($driver === 'pgsql') {
            $this->alterPostgresSlotChecks(['primary', 'justification_surrendered', 'justification_comparison']);
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteSlotTables(['primary', 'justification_surrendered', 'justification_comparison']);
            Schema::table('ib39_fea_documents', function (Blueprint $table) {
                $table->unsignedBigInteger('current_surrendered_photo_version_id')->nullable()->after('current_draft_version_id');
            });
        } else {
            Schema::table('ib39_fea_documents', function (Blueprint $table) {
                $table->foreignId('current_surrendered_photo_version_id')
                    ->nullable()
                    ->after('current_draft_version_id')
                    ->constrained('ib39_fea_document_versions')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::table('ib39_fea_document_versions')->where('slot', 'justification_surrendered')->exists()) {
            throw new RuntimeException('Cannot roll back while Justification Section 3 photo versions exist.');
        }

        $driver = DB::getDriverName();
        Schema::table('ib39_fea_documents', function (Blueprint $table) use ($driver) {
            if ($driver === 'sqlite') {
                $table->dropColumn('current_surrendered_photo_version_id');
            } else {
                $table->dropConstrainedForeignId('current_surrendered_photo_version_id');
            }
        });

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE ib39_fea_document_versions MODIFY slot ENUM('primary', 'justification_comparison') NOT NULL");
            DB::statement("ALTER TABLE ib39_fea_upload_histories MODIFY slot ENUM('primary', 'justification_comparison') NOT NULL");
        } elseif ($driver === 'sqlite') {
            $this->rebuildSqliteSlotTables(['primary', 'justification_comparison']);
        } elseif ($driver === 'pgsql') {
            $this->alterPostgresSlotChecks(['primary', 'justification_comparison']);
        }
    }

    private function alterPostgresSlotChecks(array $slots): void
    {
        $values = implode(', ', array_map(fn (string $slot): string => DB::getPdo()->quote($slot), $slots));

        foreach (['ib39_fea_document_versions', 'ib39_fea_upload_histories'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_slot_check");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_slot_check CHECK (slot IN ({$values}))");
        }
    }

    private function rebuildSqliteSlotTables(array $slots): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($slots): void {
                Schema::create('ib39_fea_document_versions_slot_migration', function (Blueprint $table) use ($slots) {
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
                DB::statement('INSERT INTO ib39_fea_document_versions_slot_migration SELECT * FROM ib39_fea_document_versions');

                Schema::create('ib39_fea_upload_histories_slot_migration', function (Blueprint $table) use ($slots) {
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
                DB::statement('INSERT INTO ib39_fea_upload_histories_slot_migration SELECT * FROM ib39_fea_upload_histories');

                Schema::drop('ib39_fea_upload_histories');
                Schema::drop('ib39_fea_document_versions');
                Schema::rename('ib39_fea_document_versions_slot_migration', 'ib39_fea_document_versions');
                Schema::rename('ib39_fea_upload_histories_slot_migration', 'ib39_fea_upload_histories');

                Schema::table('ib39_fea_document_versions', function (Blueprint $table) {
                    $table->unique(['fea_document_id', 'slot', 'version_number'], 'ib39_fea_version_number_unique');
                    $table->index(['fea_processing_id', 'created_at'], 'ib39_fea_version_processing_index');
                });
                Schema::table('ib39_fea_upload_histories', function (Blueprint $table) {
                    $table->index(['fea_document_id', 'created_at'], 'ib39_fea_upload_history_document_index');
                });
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
