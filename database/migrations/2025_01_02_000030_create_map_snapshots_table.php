<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net for the boundary editor. Holds full GeoJSON copies of the barangay
 * boundaries so edits are never immediately permanent:
 *   - kind 'draft'      : the working copy the editor autosaves to; the live map
 *                         is untouched until it is published.
 *   - kind 'checkpoint' : a manual restore point.
 *   - kind 'original'   : the pristine boundaries, always restorable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->enum('kind', ['draft', 'checkpoint', 'original'])->default('checkpoint');
            $table->unsignedInteger('feature_count')->default(0);
            $table->longText('data'); // a GeoJSON FeatureCollection
            $table->timestamps();

            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_snapshots');
    }
};
