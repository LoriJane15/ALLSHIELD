<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rcsp_phase_schedules', function (Blueprint $table) {
            $table->text('strategic_objective')->nullable()->after('estimated_duration_weeks');
        });

        Schema::table('rcsp_phase_schedule_adjustments', function (Blueprint $table) {
            $table->text('previous_strategic_objective')->nullable()->after('updated_planned_end_date');
            $table->text('updated_strategic_objective')->nullable()->after('previous_strategic_objective');
        });
    }

    public function down(): void
    {
        Schema::table('rcsp_phase_schedule_adjustments', function (Blueprint $table) {
            $table->dropColumn(['previous_strategic_objective', 'updated_strategic_objective']);
        });

        Schema::table('rcsp_phase_schedules', function (Blueprint $table) {
            $table->dropColumn('strategic_objective');
        });
    }
};
