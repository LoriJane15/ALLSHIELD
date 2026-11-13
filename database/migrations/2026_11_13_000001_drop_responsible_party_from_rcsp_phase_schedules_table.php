<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rcsp_phase_schedules', function (Blueprint $table): void {
            $table->dropColumn('responsible_party');
        });
    }

    public function down(): void
    {
        // Rollback restores only the nullable schema column; deleted historical values cannot be reconstructed.
        Schema::table('rcsp_phase_schedules', function (Blueprint $table): void {
            $table->string('responsible_party')->nullable();
        });
    }
};
