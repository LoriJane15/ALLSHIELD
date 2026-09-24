<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PHASE_UNIQUE = 'rcsp_phases_catalog_number_unique';

    private const BARANGAY_UNIQUE = 'rcsp_barangays_barangay_unique';

    private const REVIEWER_FOREIGN = 'rcsp_forms_reviewer_foreign';

    private const REVIEWER_INSERT_TRIGGER = 'rcsp_hardening_reviewer_insert_guard';

    private const REVIEWER_UPDATE_TRIGGER = 'rcsp_hardening_reviewer_update_guard';

    private const REVIEWER_DELETE_TRIGGER = 'rcsp_hardening_reviewer_delete_set_null';

    public function up(): void
    {
        if (DB::table('rcsp_barangays')->whereNotNull('barangay_id')->select('barangay_id')
            ->groupBy('barangay_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException(
                'Cannot add the RCSP barangay uniqueness constraint: duplicate non-null barangay records exist. No schema or records were changed.'
            );
        }

        if ($this->isSqlite()) {
            $this->upSqlite();

            return;
        }

        $this->upMysql();
    }

    public function down(): void
    {
        $this->guardRollback();

        if ($this->isSqlite()) {
            $this->downSqlite();

            return;
        }

        $this->downMysql();
    }

    private function upSqlite(): void
    {
        DB::statement('ALTER TABLE rcsp_phases ADD COLUMN catalog_key VARCHAR(64) NULL');
        DB::statement('CREATE UNIQUE INDEX '.self::PHASE_UNIQUE.' ON rcsp_phases (catalog_key, number)');
        DB::statement('ALTER TABLE rcsp_barangays ADD COLUMN catalog_key VARCHAR(64) NULL');
        DB::statement('CREATE UNIQUE INDEX '.self::BARANGAY_UNIQUE.' ON rcsp_barangays (barangay_id)');
        DB::statement('ALTER TABLE rcsp_forms ADD COLUMN reviewed_by_user_id INTEGER NULL');
        DB::statement('ALTER TABLE rcsp_forms ADD COLUMN reviewed_at DATETIME NULL');

        DB::unprepared('CREATE TRIGGER '.self::REVIEWER_INSERT_TRIGGER."
            BEFORE INSERT ON rcsp_forms
            FOR EACH ROW WHEN NEW.reviewed_by_user_id IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM users WHERE id = NEW.reviewed_by_user_id)
            BEGIN
                SELECT RAISE(ABORT, 'Invalid RCSP reviewer user ID');
            END");
        DB::unprepared('CREATE TRIGGER '.self::REVIEWER_UPDATE_TRIGGER."
            BEFORE UPDATE OF reviewed_by_user_id ON rcsp_forms
            FOR EACH ROW WHEN NEW.reviewed_by_user_id IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM users WHERE id = NEW.reviewed_by_user_id)
            BEGIN
                SELECT RAISE(ABORT, 'Invalid RCSP reviewer user ID');
            END");
        DB::unprepared('CREATE TRIGGER '.self::REVIEWER_DELETE_TRIGGER.'
            AFTER DELETE ON users
            FOR EACH ROW
            BEGIN
                UPDATE rcsp_forms SET reviewed_by_user_id = NULL WHERE reviewed_by_user_id = OLD.id;
            END');
    }

    private function upMysql(): void
    {
        Schema::table('rcsp_phases', function (Blueprint $table) {
            $table->string('catalog_key', 64)->nullable()->after('number');
            $table->unique(['catalog_key', 'number'], self::PHASE_UNIQUE);
        });
        Schema::table('rcsp_barangays', function (Blueprint $table) {
            $table->string('catalog_key', 64)->nullable()->after('current_phase');
            $table->unique('barangay_id', self::BARANGAY_UNIQUE);
        });
        Schema::table('rcsp_forms', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('remarks');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->foreign('reviewed_by_user_id', self::REVIEWER_FOREIGN)
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    private function guardRollback(): void
    {
        if (DB::table('rcsp_phases')->whereNotNull('catalog_key')->exists()) {
            throw new RuntimeException('Cannot roll back RCSP hardening: phase catalog data exists. No schema was changed.');
        }
        if (DB::table('rcsp_barangays')->whereNotNull('catalog_key')->exists()) {
            throw new RuntimeException('Cannot roll back RCSP hardening: barangay catalog data exists. No schema was changed.');
        }
        if (DB::table('rcsp_forms')->whereNotNull('reviewed_by_user_id')->exists()) {
            throw new RuntimeException('Cannot roll back RCSP hardening: reviewer ownership data exists. No schema was changed.');
        }
        if (DB::table('rcsp_forms')->whereNotNull('reviewed_at')->exists()) {
            throw new RuntimeException('Cannot roll back RCSP hardening: review timestamp data exists. No schema was changed.');
        }
        if (DB::table('rcsp_phases')->select('number')->groupBy('number')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot roll back RCSP hardening: duplicate phase numbers would be ambiguous to the previous application. No schema was changed.');
        }
    }

    private function downSqlite(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS '.self::REVIEWER_INSERT_TRIGGER);
        DB::statement('DROP TRIGGER IF EXISTS '.self::REVIEWER_UPDATE_TRIGGER);
        DB::statement('DROP TRIGGER IF EXISTS '.self::REVIEWER_DELETE_TRIGGER);
        DB::statement('DROP INDEX '.self::PHASE_UNIQUE);
        DB::statement('DROP INDEX '.self::BARANGAY_UNIQUE);
        DB::statement('ALTER TABLE rcsp_forms DROP COLUMN reviewed_at');
        DB::statement('ALTER TABLE rcsp_forms DROP COLUMN reviewed_by_user_id');
        DB::statement('ALTER TABLE rcsp_barangays DROP COLUMN catalog_key');
        DB::statement('ALTER TABLE rcsp_phases DROP COLUMN catalog_key');
    }

    private function downMysql(): void
    {
        Schema::table('rcsp_forms', function (Blueprint $table) {
            $table->dropForeign(self::REVIEWER_FOREIGN);
            $table->dropColumn(['reviewed_by_user_id', 'reviewed_at']);
        });
        Schema::table('rcsp_barangays', function (Blueprint $table) {
            $table->dropUnique(self::BARANGAY_UNIQUE);
            $table->dropColumn('catalog_key');
        });
        Schema::table('rcsp_phases', function (Blueprint $table) {
            $table->dropUnique(self::PHASE_UNIQUE);
            $table->dropColumn('catalog_key');
        });
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
};
