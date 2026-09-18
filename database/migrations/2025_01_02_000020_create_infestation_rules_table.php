<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable classification rules for the map: a barangay's former-rebel
 * count is matched against these thresholds (highest min_frs first) to derive
 * its status and colour. Previously hardcoded in MapBarangay::classify().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infestation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('min_frs');       // applies when frs >= min_frs
            $table->string('status');
            $table->string('color', 50);              // rgba(...) fill
            $table->string('label')->nullable();      // legend caption
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed the legacy 39th-IB thresholds so behaviour is unchanged out of the box.
        DB::table('infestation_rules')->insert([
            ['min_frs' => 20, 'status' => 'Konsolidado', 'color' => 'rgba(255,0,0,0.5)',   'label' => '20 or more FRs',   'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['min_frs' => 15, 'status' => 'Rekonsilida', 'color' => 'rgba(255,165,0,0.5)', 'label' => '15–19 FRs',         'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['min_frs' => 10, 'status' => 'Expansion',   'color' => 'rgba(255,255,0,0.5)', 'label' => '10–14 FRs',         'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['min_frs' => 0,  'status' => 'Recovery',    'color' => 'rgba(0,255,0,0.5)',   'label' => 'Fewer than 10 FRs', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('infestation_rules');
    }
};
