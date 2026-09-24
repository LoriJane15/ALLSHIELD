<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\MapBarangay;
use App\Models\MapSnapshot;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergedBoundaryFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_boundary_editor_is_integrated_with_canonical_allshield_geography(): void
    {
        $user = User::factory()->role('39th_ib')->create();
        $municipality = Municipality::create(['name' => 'Digos']);
        $barangay = Barangay::create(['municipality_id' => $municipality->id, 'name' => 'Aplaya']);
        $area = MapBarangay::create([
            'barangay_id' => $barangay->id,
            'province' => MapBarangay::DEFAULT_PROVINCE,
            'municipality' => $municipality->name,
            'barangay' => $barangay->name,
            'geometry' => $this->polygon(),
        ]);

        $this->actingAs($user)->get(route('ib39.boundaries.editor'))
            ->assertOk()
            ->assertSee('Boundary Editor');

        $featureCollection = $this->actingAs($user)->get(route('ib39.boundaries'))
            ->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->json();

        $this->assertSame($barangay->id, $featureCollection['features'][0]['properties']['barangay_id']);

        $this->actingAs($user)->putJson(route('ib39.boundaries.draft.save'), [
            'featureCollection' => [
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'geometry' => $this->polygon(125.4),
                    'properties' => ['id' => $area->id],
                ]],
            ],
        ])->assertOk()->assertJson(['saved' => true]);

        $this->assertDatabaseHas('map_snapshots', ['kind' => 'draft']);
        $this->actingAs($user)->postJson(route('ib39.boundaries.publish'))
            ->assertOk()->assertJson(['published' => true]);
        $this->assertSame(125.4, $area->fresh()->geometry['coordinates'][0][0][0][0]);
        $this->assertSame(1, MapSnapshot::where('kind', 'checkpoint')->count());
    }

    public function test_editable_rules_drive_the_existing_allshield_classifier(): void
    {
        MapBarangay::forgetRuleCache();

        $this->assertSame('Konsolidado', MapBarangay::classify(20)['status']);
        $this->assertSame('Rekonsilido', MapBarangay::classify(15)['status']);
        $this->assertSame('Recovery', MapBarangay::classify(0)['status']);
    }

    private function polygon(float $longitude = 125.3): array
    {
        return [
            'type' => 'MultiPolygon',
            'coordinates' => [[[[
                $longitude, 6.7,
            ], [
                $longitude + 0.01, 6.7,
            ], [
                $longitude, 6.71,
            ], [
                $longitude, 6.7,
            ]]]],
        ];
    }
}
