<?php

use App\Models\Ib39SurfacedFormerRebel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ib39_fr_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ib39_surfaced_former_rebel_id')
                ->unique()
                ->constrained('ib39_surfaced_former_rebels')
                ->restrictOnDelete();
            $table->enum('previous_overall_status', [
                Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS,
                Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_CDR_ONGOING,
                Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_CDR_COMPLETED,
            ]);
            $table->text('reason');
            $table->foreignId('cancelled_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ib39_fr_cancellations');
    }
};
