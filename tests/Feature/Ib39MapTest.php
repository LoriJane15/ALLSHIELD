<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\MapBarangay;
use App\Models\Municipality;
use App\Models\User;
use App\Services\RcspAreaHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ib39MapTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $municipality;

    private Barangay $barangay;

    private User $ib39;

    protected function setUp(): void
    {
        parent::setUp();
        $this->municipality = Municipality::query()->create(['name' => 'Map Municipality']);
        $this->barangay = Barangay::query()->create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Map Barangay',
        ]);
        $this->ib39 = User::factory()->role('39th_ib')->create();
    }

    public function test_map_page_and_endpoints_require_an_active_39th_ib_account(): void
    {
        $this->get(route('ib39.map'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role('mblrc')->create())
            ->getJson(route('ib39.area.data'))->assertForbidden();
        $this->actingAs(User::factory()->role('39th_ib')->create(['is_active' => false]))
            ->get(route('ib39.map'))->assertRedirect(route('login'));

        $this->actingAs($this->ib39)->get(route('ib39.map'))
            ->assertOk()
            ->assertSee('ib39FullMap')
            ->assertSee(route('ib39.boundaries'))
            ->assertSee('History Barangay Details');
    }

    public function test_area_data_is_keyed_by_canonical_id_and_contains_only_aggregate_current_state(): void
    {
        app(RcspAreaHistoryService::class)->record($this->barangay, '2026-09-13', 15);

        $data = $this->actingAs($this->ib39)->getJson(route('ib39.area.data'))->assertOk()->json();
        $this->assertSame([
            'frs' => 15,
            'status' => 'Rekonsilido',
            'color' => 'rgba(255,165,0,0.5)',
        ], $data[(string) $this->barangay->id]);
        $this->assertSame(['frs', 'status', 'color'], array_keys($data[(string) $this->barangay->id]));
    }

    public function test_details_use_canonical_id_and_return_complete_deterministically_ordered_history(): void
    {
        $service = app(RcspAreaHistoryService::class);
        $service->record($this->barangay, '2026-09-01', 5);
        $service->record($this->barangay, '2026-09-03', 10);
        $service->record($this->barangay, '2026-09-03', 15);
        $service->record($this->barangay, '2026-09-04', 20);
        $service->record($this->barangay, '2026-09-05', 9);
        $service->record($this->barangay, '2026-09-06', 14);

        $data = $this->actingAs($this->ib39)
            ->getJson(route('ib39.barangay.data', ['barangay_id' => $this->barangay->id]))
            ->assertOk()
            ->json();

        $this->assertSame(MapBarangay::DEFAULT_PROVINCE, $data['province']);
        $this->assertSame($this->municipality->name, $data['municipality']);
        $this->assertSame($this->barangay->name, $data['barangay']);
        $this->assertSame('Expansion', $data['status']);
        $this->assertSame(14, $data['frs']);
        $this->assertCount(6, $data['color_history']);
        $this->assertSame(
            ['2026-09-06', '2026-09-05', '2026-09-04', '2026-09-03', '2026-09-03', '2026-09-01'],
            array_column($data['color_history'], 'effective_date')
        );
        $this->assertSame([15, 10], array_column(array_slice($data['color_history'], 3, 2), 'frs'));
    }

    public function test_canonical_barangay_without_history_is_neutral(): void
    {
        $data = $this->actingAs($this->ib39)
            ->getJson(route('ib39.barangay.data', ['barangay_id' => $this->barangay->id]))
            ->assertOk()
            ->json();

        $this->assertNull($data['status']);
        $this->assertSame(0, $data['frs']);
        $this->assertSame(MapBarangay::NEUTRAL_COLOR, $data['color']);
        $this->assertSame([], $data['color_history']);
    }

    public function test_invalid_canonical_barangay_id_is_rejected(): void
    {
        $this->actingAs($this->ib39)
            ->getJson(route('ib39.barangay.data', ['barangay_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('barangay_id');
    }

    public function test_private_fr_map_endpoint_is_absent_and_map_payloads_expose_no_private_fields(): void
    {
        $this->actingAs($this->ib39)->get('/39th-ib/map-data')->assertNotFound();
        app(RcspAreaHistoryService::class)->record($this->barangay, '2026-09-13', 5);

        $payload = json_encode([
            $this->actingAs($this->ib39)->getJson(route('ib39.area.data'))->json(),
            $this->actingAs($this->ib39)->getJson(route('ib39.barangay.data', [
                'barangay_id' => $this->barangay->id,
            ]))->json(),
        ], JSON_THROW_ON_ERROR);

        foreach (['firstname', 'lastname', 'name', 'address', 'latitude', 'longitude', 'batch', 'storage', 'file'] as $privateKey) {
            $this->assertStringNotContainsString('"'.$privateKey.'"', $payload);
        }
    }

    public function test_geojson_contains_only_verified_canonical_ids_and_reports_four_unresolved_features(): void
    {
        $geo = json_decode(file_get_contents(public_path('assets/mapping/barangays.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $matched = array_filter($geo['features'], fn (array $feature): bool => isset($feature['properties']['barangay_id']));
        $unresolved = array_values(array_map(
            fn (array $feature): string => $feature['properties']['municipality'].'|'.$feature['properties']['barangay'],
            array_filter($geo['features'], fn (array $feature): bool => ! isset($feature['properties']['barangay_id']))
        ));

        $this->assertCount(228, $matched);
        $this->assertSame([
            'Bansalan|Santo Niño',
            'Kiblawan|Santo Niño',
            'Padada|N C Ordaneza Distric',
            'Sulop|Osmeña',
        ], $unresolved);
    }

    public function test_map_javascript_uses_server_colors_and_private_updates_instead_of_fixed_green(): void
    {
        $javascript = file_get_contents(resource_path('js/ib39-map.js'));
        $this->assertStringContainsString('currentArea(areaData, feature)?.color', $javascript);
        $this->assertStringContainsString("Echo.private('rcsp-areas')", $javascript);
        $this->assertStringContainsString(MapBarangay::NEUTRAL_COLOR, $javascript);
        $this->assertStringNotContainsString('const DEFAULT_FILL', $javascript);
    }
}
