<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loads the SHIELD dataset that was transformed out of the legacy
 * `kp_datacenter` database, so a fresh clone has every record without needing
 * the legacy database or `php artisan import:legacy`.
 *
 * The snapshot in database/seeders/data/legacy-snapshot.sql is a data-only dump
 * of the transformed `shield_db` (framework tables — migrations, cache,
 * sessions, jobs — excluded). Regenerate it after a fresh import with:
 *
 *   mysqldump -u root --no-create-info --skip-triggers --complete-insert \
 *     --ignore-table=shield_db.migrations ... shield_db \
 *     > database/seeders/data/legacy-snapshot.sql
 *
 * File uploads referenced by these rows live under storage/app/public and are
 * committed alongside this snapshot, so the paths resolve on any clone.
 */
class LegacyDataSeeder extends Seeder
{
    /** Domain tables the snapshot populates, child-before-parent for clean truncation. */
    private const TABLES = [
        'implementation_taggings', 'implementation_photos', 'implementation_files',
        'agency_implan_responses', 'implementations',
        'rcsp_file_comments', 'rcsp_lgu_comments', 'rcsp_forms',
        'rcsp_phase_statuses', 'rcsp_barangays', 'rcsp_activities', 'rcsp_phases',
        'color_histories', 'map_barangays',
        'fr_government_assistances', 'fr_skills', 'fr_location_histories',
        'fr_education_works', 'fr_program_statuses', 'former_rebels',
        'gov_agencies', 'barangays', 'municipalities', 'users',
        'infestation_rules',
    ];

    public function run(): void
    {
        $snapshot = database_path('seeders/data/legacy-snapshot.sql');

        if (! is_file($snapshot)) {
            $this->command->warn("Legacy snapshot not found at {$snapshot} — skipping.");

            return;
        }

        Schema::disableForeignKeyConstraints();

        // Idempotent: wipe the domain tables so re-seeding never duplicates rows.
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        // The dump preserves original ids (id 0 = the "Pre-Shaping" RCSP phase).
        DB::unprepared("SET SESSION sql_mode=(SELECT CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO'))");
        DB::unprepared(file_get_contents($snapshot));

        Schema::enableForeignKeyConstraints();

        $this->command->info('Legacy dataset seeded from snapshot ('.count(self::TABLES).' tables).');
    }
}
