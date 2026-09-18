<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the barangay polygon geometry from the static
 * public/assets/mapping/barangays.geojson file into the database, so the
 * boundaries become editable in-app (draw/reshape/add/delete) rather than
 * requiring a hand-edit of the file. Stored as the GeoJSON geometry object
 * (a MultiPolygon) in a JSON column; the file remains the import/seed format.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('map_barangays', function (Blueprint $table) {
            $table->json('geometry')->nullable()->after('barangay');
        });
    }

    public function down(): void
    {
        Schema::table('map_barangays', function (Blueprint $table) {
            $table->dropColumn('geometry');
        });
    }
};
