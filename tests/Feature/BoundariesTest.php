<?php

namespace Tests\Feature;

use App\Models\{MapBarangay, User};
use Tests\TestCase;

class BoundariesTest extends TestCase
{
    public function test_boundaries_endpoint_serves_geojson_from_the_database(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $user = User::where('role', '39th_ib')->firstOrFail();
        $fc = $this->actingAs($user)->get('/39th-ib/boundaries')->assertOk()->json();

        $this->assertSame('FeatureCollection', $fc['type']);
        $this->assertCount(MapBarangay::whereNotNull('geometry')->count(), $fc['features']);
        $this->assertSame(232, count($fc['features']), 'all barangays have geometry');

        $f = $fc['features'][0];
        $this->assertSame('Feature', $f['type']);
        $this->assertSame('MultiPolygon', $f['geometry']['type']);
        $this->assertArrayHasKey('municipality', $f['properties']);
        $this->assertArrayHasKey('barangay', $f['properties']);
        $this->assertArrayHasKey('id', $f['properties']);
    }
}
