<?php

namespace App\Services;

use App\Models\MapBarangay;
use Illuminate\Support\Facades\DB;

/**
 * Loads barangay polygon geometry from a GeoJSON FeatureCollection into
 * map_barangays. Shared by the `map:import-boundaries` command and the
 * Boundary Editor's upload, so both behave identically.
 */
class BoundaryImporter
{
    /**
     * @param  array  $featureCollection  decoded GeoJSON
     * @param  bool   $create   create rows for features with no name match
     * @param  bool   $replace  clear ALL geometry first, so features dropped from
     *                          the file disappear from the map
     * @return array{matched:int,created:int,skipped:int,errors:list<string>}
     */
    public function import(array $featureCollection, bool $create = false, bool $replace = false): array
    {
        if (($featureCollection['type'] ?? null) !== 'FeatureCollection' || ! is_array($featureCollection['features'] ?? null)) {
            return ['matched' => 0, 'created' => 0, 'skipped' => 0, 'errors' => ['Not a GeoJSON FeatureCollection.']];
        }

        $matched = $created = $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($featureCollection, $create, $replace, &$matched, &$created, &$skipped, &$errors) {
            if ($replace) {
                MapBarangay::whereNotNull('geometry')->update(['geometry' => null]);
            }

            foreach ($featureCollection['features'] as $i => $feature) {
                $p = $feature['properties'] ?? [];
                $municipality = trim($p['municipality'] ?? $p['NAME_2'] ?? '');
                $barangay = trim($p['barangay'] ?? $p['NAME_3'] ?? '');
                $geometry = $feature['geometry'] ?? null;

                if ($municipality === '' || $barangay === '' || ! is_array($geometry)) {
                    $skipped++;
                    if (count($errors) < 5) {
                        $errors[] = "Feature #{$i}: missing municipality/barangay/geometry.";
                    }

                    continue;
                }

                $row = MapBarangay::where('municipality', $municipality)->where('barangay', $barangay)->first();

                if (! $row) {
                    if (! $create) {
                        $skipped++;

                        continue;
                    }
                    $row = new MapBarangay([
                        'municipality' => $municipality,
                        'barangay' => $barangay,
                        'province' => 'Davao del Sur',
                    ]);
                    $created++;
                } else {
                    $matched++;
                }

                $row->geometry = $this->normalize($geometry);
                $row->save();
            }
        });

        return compact('matched', 'created', 'skipped', 'errors');
    }

    /** Store polygons as MultiPolygon for one consistent geometry type. */
    private function normalize(array $geometry): array
    {
        if (($geometry['type'] ?? null) === 'Polygon') {
            return ['type' => 'MultiPolygon', 'coordinates' => [$geometry['coordinates']]];
        }

        return $geometry;
    }
}
