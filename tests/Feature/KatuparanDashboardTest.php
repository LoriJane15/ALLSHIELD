<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class KatuparanDashboardTest extends TestCase
{
    public function test_dashboard_matches_the_legacy_layout(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $admin = User::where('role', 'admin')->firstOrFail();
        $html = $this->actingAs($admin)->get('/katuparan')->assertOk()->getContent();

        // Hero
        foreach (['hero-section', 'map-container', 'hero-overlay', 'hero-badge',
                  'SHIELD Program Dashboard', 'Strengthening Communities', 'hero-stats'] as $needle) {
            $this->assertStringContainsString($needle, $html, "hero missing: $needle");
        }

        // Municipality cards + chart
        foreach (['Municipality Overview', 'municipality-grid', 'municipality-card', 'municipality-seal',
                  'RCSP Barangays', 'RCSP Implementation Progress', 'rcspProgressChart',
                  'Completed', 'In Progress'] as $needle) {
            $this->assertStringContainsString($needle, $html, "section missing: $needle");
        }

        // The legacy stylesheet is actually linked
        $this->assertStringContainsString('katuparan-dashboard.css', $html);

        // All 10 municipalities render as cards
        $this->assertSame(10, substr_count($html, 'municipality-card'));
    }

    public function test_hero_map_area_data_is_available_to_katuparan(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $admin = User::where('role', 'admin')->firstOrFail();
        $rows = $this->actingAs($admin)->get('/katuparan/area-data')->assertOk()->json();

        $this->assertCount(232, $rows);
        $this->assertArrayHasKey('Digos City|Aplaya', $rows);
    }

    public function test_lgu_cannot_read_katuparan_area_data(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $lgu = User::where('role', 'lgu')->firstOrFail();
        $this->actingAs($lgu)->get('/katuparan/area-data')->assertForbidden();
    }

    /**
     * The hero map must fill its container by absolute positioning. With
     * height:100% the percentage chain resolved to 0 and Leaflet painted its
     * tiles into a zero-height box — initialised, but invisible.
     */
    public function test_hero_map_does_not_rely_on_percentage_height(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $css = file_get_contents(public_path('assets/css/katuparan-dashboard.css'));
        $this->assertMatchesRegularExpression('/#heroMap\s*\{[^}]*position:\s*absolute/', $css);
        $this->assertMatchesRegularExpression('/#heroMap\s*\{[^}]*inset:\s*0/', $css);

        $user = User::where('role', 'admin')->firstOrFail();
        $html = $this->actingAs($user)->get('/katuparan')->assertOk()->getContent();

        $this->assertStringNotContainsString('HERO MAP DIAGNOSTIC', $html, 'diagnostic must be removed');
        $this->assertStringContainsString('jquery.cookie.js', $html, 'template.js needs $.cookie');
    }

    /**
     * The hero embeds the same legacy module as the 39th-IB map, so both must
     * paint every barangay the same flat green rather than colouring by the
     * stored infestation status.
     */
    public function test_hero_map_uses_the_same_flat_green_as_the_ib39_map(): void
    {
        $hero = file_get_contents(resource_path('js/katuparan-dashboard.js'));
        $ib39 = file_get_contents(resource_path('js/ib39-map.js'));

        foreach ([$hero, $ib39] as $src) {
            $this->assertStringContainsString("rgba(0, 255, 0, 0.5)", $src);
            $this->assertStringContainsString("rgba(35,35,35,1.0)", $src);
            $this->assertStringNotContainsString('area?.color', $src, 'polygons must not be coloured from the database');
        }
    }
}
