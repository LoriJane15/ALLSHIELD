<?php

namespace App\Console\Commands;

use App\Services\BoundaryImporter;
use Illuminate\Console\Command;

/**
 * Loads polygon geometry from a GeoJSON file into map_barangays (see
 * App\Services\BoundaryImporter). Also the seed path: run with the committed
 * public/assets/mapping/barangays.geojson to populate a fresh database.
 */
class ImportBoundaries extends Command
{
    protected $signature = 'map:import-boundaries
        {file? : Path to the GeoJSON (default: public/assets/mapping/barangays.geojson)}
        {--create : Create map_barangays rows for features that have no match}
        {--replace : Clear all geometry first, so dropped features disappear}';

    protected $description = 'Import barangay polygon geometry from a GeoJSON file into the database';

    public function handle(BoundaryImporter $importer): int
    {
        $path = $this->argument('file') ?: public_path('assets/mapping/barangays.geojson');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $fc = json_decode(file_get_contents($path), true);

        if (! is_array($fc)) {
            $this->error('Could not parse JSON.');

            return self::FAILURE;
        }

        $r = $importer->import($fc, $this->option('create'), $this->option('replace'));

        foreach ($r['errors'] as $e) {
            $this->warn($e);
        }

        $this->info("Boundaries imported — matched: {$r['matched']}, created: {$r['created']}, skipped: {$r['skipped']}.");

        return self::SUCCESS;
    }
}
