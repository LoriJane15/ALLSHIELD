<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'japic_certification_processings',
        'japic_certification_drafts',
        'japic_certification_draft_histories',
        'japic_certification_histories',
        'japic_certification_document_versions',
    ];

    public function up(): void
    {
        // The preceding migration builds the canonical PostgreSQL schema. Its
        // legacy repair comparison uses SQLite/MySQL column and index metadata.
        if (DB::getDriverName() === 'pgsql') {
            return;
        }

        DB::transaction(function (): void {
            $foundation = require database_path('migrations/2026_09_09_000001_create_or_reconcile_japic_certification_workflow.php');
            $foundation->repairEmptyJapicTables();
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("JAPIC repair rollback refused: {$table} contains certification records.");
            }
        }

        // The foundation migration owns these tables. On an empty schema, rolling
        // back this repair only unregisters the repair and deliberately retains it.
    }
};
