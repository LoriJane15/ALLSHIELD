<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rcsp_implementation_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rcsp_barangay_id')->unique()
                ->constrained('rcsp_barangays')->cascadeOnDelete();
            $table->date('implementation_start_date');
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rcsp_phase_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rcsp_implementation_timeline_id')
                ->constrained('rcsp_implementation_timelines')->cascadeOnDelete();
            $table->foreignId('rcsp_phase_id')
                ->constrained('rcsp_phases')->restrictOnDelete();
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->unsignedSmallInteger('estimated_duration_weeks');
            $table->string('responsible_party');
            $table->text('implementation_note')->nullable();
            $table->timestamps();

            $table->unique(
                ['rcsp_implementation_timeline_id', 'rcsp_phase_id'],
                'rcsp_timeline_phase_unique'
            );
            $table->index('planned_end_date');
        });

        Schema::create('rcsp_phase_schedule_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rcsp_phase_schedule_id')
                ->constrained('rcsp_phase_schedules')->cascadeOnDelete();
            $table->date('previous_planned_start_date');
            $table->date('previous_planned_end_date');
            $table->date('updated_planned_start_date');
            $table->date('updated_planned_end_date');
            $table->text('reason');
            $table->foreignId('changed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('adjusted_at');
            $table->timestamps();

            $table->index(['rcsp_phase_schedule_id', 'adjusted_at'], 'rcsp_schedule_adjustment_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rcsp_phase_schedule_adjustments');
        Schema::dropIfExists('rcsp_phase_schedules');
        Schema::dropIfExists('rcsp_implementation_timelines');
    }
};
