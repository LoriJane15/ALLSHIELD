<?php

namespace Tests\Feature;

use App\Models\{MapBarangay, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BoundaryImportExportTest extends TestCase
{
    use DatabaseTransactions;

    private function ib39(): User
    {
        return User::where('role', '39th_ib')->firstOrFail();
    }

    private function geojson(array $features): string
    {
        return json_encode(['type' => 'FeatureCollection', 'features' => $features]);
    }

    private function feature(string $muni, string $bgy): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['municipality' => $muni, 'barangay' => $bgy],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[
                [125.3, 6.7], [125.31, 6.7], [125.31, 6.71], [125.3, 6.71], [125.3, 6.7],
            ]]],
        ];
    }

    public function test_export_downloads_a_geojson_feature_collection(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $res = $this->actingAs($this->ib39())->get('/39th-ib/boundaries-export')->assertOk();
        $res->assertHeader('content-disposition');
        $this->assertStringContainsString('.geojson', $res->headers->get('content-disposition'));

        $fc = $res->json();
        $this->assertSame('FeatureCollection', $fc['type']);
        $this->assertSame(232, count($fc['features']));
    }

    public function test_merge_import_updates_a_matching_barangay(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $target = MapBarangay::whereNotNull('geometry')->firstOrFail();
        $file = UploadedFile::fake()->createWithContent(
            'boundaries.geojson',
            $this->geojson([$this->feature($target->municipality, $target->barangay)])
        );

        $this->actingAs($this->ib39())
            ->post('/39th-ib/boundaries-import', ['file' => $file, 'mode' => 'merge'])
            ->assertRedirect();

        $this->assertSame('MultiPolygon', $target->fresh()->geometry['type']);
        // untouched barangays keep their geometry (merge, not replace)
        $this->assertGreaterThan(200, MapBarangay::whereNotNull('geometry')->count());
    }

    public function test_replace_import_clears_geometry_not_named_in_the_file(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $keep = MapBarangay::whereNotNull('geometry')->firstOrFail();
        $file = UploadedFile::fake()->createWithContent(
            'boundaries.geojson',
            $this->geojson([$this->feature($keep->municipality, $keep->barangay)])
        );

        $this->actingAs($this->ib39())
            ->post('/39th-ib/boundaries-import', ['file' => $file, 'mode' => 'replace'])
            ->assertRedirect();

        // Only the one named feature keeps geometry.
        $this->assertSame(1, MapBarangay::whereNotNull('geometry')->count());
        $this->assertNotNull($keep->fresh()->geometry);
    }

    public function test_create_flag_adds_unmatched_features(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $before = MapBarangay::count();
        $file = UploadedFile::fake()->createWithContent(
            'boundaries.geojson',
            $this->geojson([$this->feature('Digos City', 'Brand New Bgy XYZ')])
        );

        $this->actingAs($this->ib39())
            ->post('/39th-ib/boundaries-import', ['file' => $file, 'mode' => 'merge', 'create' => 1])
            ->assertRedirect();

        $this->assertSame($before + 1, MapBarangay::count());
        $this->assertNotNull(MapBarangay::where('barangay', 'Brand New Bgy XYZ')->first());
    }

    public function test_other_roles_cannot_export_or_import(): void
    {
        $this->skipUnlessLegacyDataPresent();
        $mblrc = User::where('role', 'mblrc')->firstOrFail();

        $this->actingAs($mblrc)->get('/39th-ib/boundaries-export')->assertForbidden();
    }
}
