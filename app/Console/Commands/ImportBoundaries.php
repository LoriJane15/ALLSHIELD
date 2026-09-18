<?php

namespace App\Console\Commands;

use App\Models\MapBarangay;
use Illuminate\Console\Command;

/**
 * Loads polygon geometry from a GeoJSON FeatureCollection into map_barangays,
 * matching each feature to its row by municipality + barangay. This is how the
 * boundaries move from the file into the (editable) database, and also how an
 * uploaded GeoJSON replaces them later.
 */
class ImportBoundaries extends Command
{
    protected $signature = 'map:import-boundaries
        {file? : Path to the GeoJSON (default: public/assets/mapping/barangays.geojson)}
        {--create : Create map_barangays rows for features that have no match}';

    protected $description = 'Import barangay polygon geometry from a GeoJSON file into the database';

    public function handle(): int
    {
        $path = $this->argument('file') ?: public_path('assets/mapping/barangays.geojson');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $fc = json_decode(file_get_contents($path), true);

        if (($fc['type'] ?? null) !== 'FeatureCollection' || ! is_array($fc['features'] ?? null)) {
            $this->error('Not a GeoJSON FeatureCollection.');

            return self::FAILURE;
        }

        $matched = 0;
        $created = 0;
        $skipped = 0;

        foreach ($fc['features'] as $feature) {
            $p = $feature['properties'] ?? [];
            $municipality = trim($p['municipality'] ?? $p['NAME_2'] ?? '');
            $barangay = trim($p['barangay'] ?? $p['NAME_3'] ?? '');
            $geometry = $feature['geometry'] ?? null;

            if ($municipality === '' || $barangay === '' || ! $geometry) {
                $skipped++;

                continue;
            }

            $row = MapBarangay::where('municipality', $municipality)->where('barangay', $barangay)->first();

            if (! $row && $this->option('create')) {
                $row = new MapBarangay(['municipality' => $municipality, 'barangay' => $barangay, 'province' => 'Davao del Sur']);
                $created++;
            } elseif (! $row) {
                $skipped++;

                continue;
            } else {
                $matched++;
            }

            $row->geometry = $geometry;
            $row->save();
        }

        $this->info("Boundaries imported — matched: {$matched}, created: {$created}, skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
