<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\MapBarangay;
use App\Models\Municipality;
use App\Services\BoundaryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoundaryImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_stale_feature_ids_by_canonical_location(): void
    {
        $wrongMunicipality = Municipality::create(['name' => 'Wrong Import Municipality']);
        $wrongBarangay = Barangay::create([
            'municipality_id' => $wrongMunicipality->id,
            'name' => 'Wrong Import Barangay',
        ]);
        $municipality = Municipality::create(['name' => 'Canonical Import Municipality']);
        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Canonical Import Barangay',
        ]);
        $mapBarangay = MapBarangay::create([
            'barangay_id' => $wrongBarangay->id,
            'municipality' => $municipality->name,
            'barangay' => $barangay->name,
        ]);

        $result = app(BoundaryImporter::class)->import([
            'type' => 'FeatureCollection',
            'features' => [$this->feature(
                $municipality->name,
                $barangay->name,
                $wrongBarangay->id,
            )],
        ]);

        $mapBarangay->refresh();

        $this->assertSame(1, $result['matched']);
        $this->assertSame($barangay->id, $mapBarangay->barangay_id);
        $this->assertSame('MultiPolygon', $mapBarangay->geometry['type']);
    }

    public function test_replace_repopulates_geometry_after_clearing_existing_boundaries(): void
    {
        $municipality = Municipality::create(['name' => 'Replace Municipality']);
        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Replace Barangay',
        ]);
        $keep = MapBarangay::create([
            'barangay_id' => $barangay->id,
            'municipality' => $municipality->name,
            'barangay' => $barangay->name,
            'geometry' => $this->geometry(),
        ]);
        $drop = MapBarangay::create([
            'municipality' => 'Dropped Municipality',
            'barangay' => 'Dropped Barangay',
            'geometry' => $this->geometry(),
        ]);

        app(BoundaryImporter::class)->import([
            'type' => 'FeatureCollection',
            'features' => [$this->feature($municipality->name, $barangay->name, $barangay->id)],
        ], replace: true);

        $this->assertNotNull($keep->fresh()->geometry);
        $this->assertNull($drop->fresh()->geometry);
    }

    private function feature(string $municipality, string $barangay, int $barangayId): array
    {
        return [
            'type' => 'Feature',
            'properties' => [
                'municipality' => $municipality,
                'barangay' => $barangay,
                'barangay_id' => $barangayId,
            ],
            'geometry' => $this->geometry(),
        ];
    }

    private function geometry(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [125.3, 6.7], [125.31, 6.7], [125.31, 6.71], [125.3, 6.7],
            ]],
        ];
    }
}
