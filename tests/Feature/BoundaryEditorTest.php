<?php

namespace Tests\Feature;

use App\Models\{MapBarangay, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BoundaryEditorTest extends TestCase
{
    // These write geometry to the real dataset; roll back after each test.
    use DatabaseTransactions;

    private function ib39(): User
    {
        return User::where('role', '39th_ib')->firstOrFail();
    }

    private function square(float $lng, float $lat): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng, $lat], [$lng + 0.01, $lat], [$lng + 0.01, $lat + 0.01], [$lng, $lat + 0.01], [$lng, $lat],
        ]]];
    }

    public function test_editor_page_renders_with_the_toolbar_hooks(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $html = $this->actingAs($this->ib39())->get('/39th-ib/boundary-editor')->assertOk()->getContent();

        $this->assertStringContainsString('id="boundaryMap"', $html);
        $this->assertStringContainsString('data-boundaries', $html);
        $this->assertStringContainsString('data-store', $html);
        $this->assertStringContainsString('newAreaModal', $html);
        $this->assertStringContainsString('leaflet-geoman.css', $html);
    }

    public function test_reshaping_a_boundary_saves_new_geometry(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $area = MapBarangay::whereNotNull('geometry')->firstOrFail();
        $geom = ['type' => 'MultiPolygon', 'coordinates' => [$this->square(125.3, 6.7)['coordinates']]];

        $this->actingAs($this->ib39())
            ->putJson("/39th-ib/boundaries/{$area->id}", ['geometry' => $geom])
            ->assertOk()->assertJson(['saved' => true]);

        $this->assertSame('MultiPolygon', $area->fresh()->geometry['type']);
    }

    public function test_drawing_a_new_area_creates_a_row_and_normalises_to_multipolygon(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $res = $this->actingAs($this->ib39())->postJson('/39th-ib/boundaries', [
            'municipality' => 'Digos City',
            'barangay' => 'Test Drawn Barangay',
            'geometry' => $this->square(125.31, 6.71), // a Polygon
        ])->assertCreated()->json();

        $this->assertTrue($res['created']);
        $row = MapBarangay::find($res['id']);
        $this->assertSame('MultiPolygon', $row->geometry['type'], 'Polygon normalised to MultiPolygon');
    }

    public function test_deleting_an_empty_area_removes_the_row_but_keeps_one_with_data(): void
    {
        $this->skipUnlessLegacyDataPresent();

        // empty area (no FRs, no history) -> row deleted
        $empty = MapBarangay::create([
            'municipality' => 'Digos City', 'barangay' => 'Ephemeral', 'province' => 'Davao del Sur',
            'geometry' => ['type' => 'MultiPolygon', 'coordinates' => [$this->square(125.3, 6.7)['coordinates']]],
        ]);
        $this->actingAs($this->ib39())->deleteJson("/39th-ib/boundaries/{$empty->id}")
            ->assertOk()->assertJson(['deleted' => true]);
        $this->assertNull(MapBarangay::find($empty->id));

        // area with FR data -> geometry cleared, row kept
        $withData = MapBarangay::whereNotNull('geometry')->where('frs', '>', 0)->firstOrFail();
        $this->actingAs($this->ib39())->deleteJson("/39th-ib/boundaries/{$withData->id}")
            ->assertOk()->assertJson(['deleted' => false]);
        $this->assertNotNull(MapBarangay::find($withData->id));
        $this->assertNull($withData->fresh()->geometry);
    }

    public function test_other_roles_cannot_edit_boundaries(): void
    {
        $this->skipUnlessLegacyDataPresent();
        $mblrc = User::where('role', 'mblrc')->firstOrFail();
        $area = MapBarangay::whereNotNull('geometry')->firstOrFail();

        $this->actingAs($mblrc)->putJson("/39th-ib/boundaries/{$area->id}", ['geometry' => ['type' => 'MultiPolygon', 'coordinates' => []]])
            ->assertForbidden();
    }
}
