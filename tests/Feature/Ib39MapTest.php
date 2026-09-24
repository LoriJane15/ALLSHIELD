<?php

namespace Tests\Feature;

use App\Models\{ColorHistory, MapBarangay, User};
use Tests\TestCase;

class Ib39MapTest extends TestCase
{
    public function test_map_page_reproduces_the_legacy_module(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $user = User::where('role', '39th_ib')->firstOrFail();
        $html = $this->actingAs($user)->get('/39th-ib/map')->assertOk()->getContent();

        // Legacy sidebar markup
        foreach (['ib39FullMap', 'id="barangayDetail"', 'History Barangay Details',
                  'infestation-color', 'province-value', 'municipality-value',
                  'barangay-value', 'status-value', 'fr-count-value', 'color-history'] as $needle) {
            $this->assertStringContainsString($needle, $html, "missing: $needle");
        }

        $this->assertStringContainsString('ib39-map.css', $html);
        $this->assertStringContainsString('39th-ib/boundaries', $html); // geometry now served from the DB
    }

    public function test_area_data_feeds_the_polygons(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $user = User::where('role', '39th_ib')->firstOrFail();
        $rows = $this->actingAs($user)->get('/39th-ib/area-data')->assertOk()->json();

        $this->assertCount(232, $rows);
        $this->assertArrayHasKey('Digos City|Aplaya', $rows);
    }

    public function test_barangay_detail_returns_row_and_colour_history(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $area = MapBarangay::whereHas('colorHistories')->firstOrFail();
        $user = User::where('role', '39th_ib')->firstOrFail();

        $data = $this->actingAs($user)
            ->get('/39th-ib/barangay-data?'.http_build_query([
                'municipality' => $area->municipality,
                'barangay' => $area->barangay,
            ]))
            ->assertOk()->json();

        $this->assertSame($area->barangay, $data['barangay']);
        $this->assertSame($area->municipality, $data['municipality']);
        $this->assertSame('Davao del Sur', $data['province']);
        $this->assertArrayHasKey('frs', $data);
        $this->assertArrayHasKey('is_rcsp', $data);

        // At most the five most recent entries, matching the legacy LIMIT 5.
        $this->assertNotEmpty($data['color_history']);
        $this->assertLessThanOrEqual(5, count($data['color_history']));
        $this->assertArrayHasKey('formatted_timestamp', $data['color_history'][0]);
        $this->assertArrayHasKey('color', $data['color_history'][0]);
    }

    public function test_unknown_barangay_returns_the_legacy_error_shape(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $user = User::where('role', '39th_ib')->firstOrFail();

        $this->actingAs($user)
            ->get('/39th-ib/barangay-data?municipality=Nowhere&barangay=Nothing')
            ->assertNotFound()
            ->assertJson(['error' => 'No data found for this location']);
    }

    public function test_other_roles_cannot_read_the_map_endpoints(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $mblrc = User::where('role', 'mblrc')->firstOrFail();
        $this->actingAs($mblrc)->get('/39th-ib/barangay-data?municipality=x&barangay=y')->assertForbidden();
    }

    /** Legacy painted every polygon the same green and never showed a legend. */
    public function test_map_has_no_invented_legend(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $user = User::where('role', '39th_ib')->firstOrFail();
        $html = $this->actingAs($user)->get('/39th-ib/map')->assertOk()->getContent();

        $this->assertStringNotContainsString('Infestation status', $html);
        $this->assertStringNotContainsString('ib39-legend', $html);
    }

    /**
     * The detail panel must not reuse SkyDash's own `.sidebar` class — that is
     * the main nav, styled (width 235px, z-index 11) and driven by template.js,
     * which swallowed the panel and kept it off-screen.
     */
    public function test_detail_panel_does_not_collide_with_the_skydash_nav(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $user = User::where('role', '39th_ib')->firstOrFail();
        $html = $this->actingAs($user)->get('/39th-ib/map')->assertOk()->getContent();

        $this->assertStringContainsString('class="ib39-detail"', $html);
        $this->assertStringNotContainsString('<div class="sidebar" id=', $html);
        $this->assertStringNotContainsString('class="sidebar-content"', $html);
    }
}
