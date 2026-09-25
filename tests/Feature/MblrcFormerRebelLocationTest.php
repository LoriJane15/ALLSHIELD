<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FormerRebel;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MblrcFormerRebelLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FormerRebel $formerRebel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->role('mblrc')->create(['name' => 'MBLRC Locator']);
        $municipality = Municipality::query()->create(['name' => 'Sulop']);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'Poblacion',
        ]);
        $this->formerRebel = FormerRebel::query()->create([
            'classified_id' => 'FR-#0001',
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'residential_address' => 'Purok 1',
            'barangay_id' => $barangay->id,
            'municipality_id' => $municipality->id,
            'province' => 'Davao del Sur',
        ]);
    }

    public function test_profile_without_coordinates_can_preview_the_registration_address_without_writing_history(): void
    {
        $this->formerRebel->update(['residential_address' => null]);
        $this->fakeGeocoder('6.60234567', '125.34345678', 'Poblacion, Sulop, Davao del Sur, Philippines');

        $this->actingAs($this->user)
            ->get(route('mblrc.fr.show', $this->formerRebel))
            ->assertOk()
            ->assertSee('data-has-saved-location="0"', false)
            ->assertSee('data-location-geocode=', false)
            ->assertSee('Locating the saved residential address', false);

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.geocode', $this->formerRebel), [
                'registration_address' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'latitude' => '6.60234567',
                'longitude' => '125.34345678',
                'display_name' => 'Poblacion, Sulop, Davao del Sur, Philippines',
                'match_level' => 'barangay',
            ]);

        $this->formerRebel->refresh();
        $this->assertNull($this->formerRebel->latitude);
        $this->assertNull($this->formerRebel->longitude);
        $this->assertDatabaseCount('fr_location_histories', 0);

        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);
        /** @var Request $request */
        $request = $recorded[0][0];
        $this->assertStringStartsWith('https://nominatim.openstreetmap.org/search?', $request->url());
        $this->assertSame('jsonv2', $request['format']);
        $this->assertSame('1', (string) $request['limit']);
        $this->assertSame('ph', $request['countrycodes']);
        $this->assertSame('Barangay Poblacion, Sulop, Davao del Sur, Philippines', $request['q']);
        $this->assertTrue($request->hasHeader('User-Agent'));
    }

    public function test_registration_lookup_uses_the_full_saved_address_first(): void
    {
        $this->fakeGeocoder('6.60234567', '125.34345678', 'Purok 1, Poblacion, Sulop');

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.geocode', $this->formerRebel), [
                'registration_address' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'match_level' => 'address',
            ]);

        Http::assertSent(fn (Request $request): bool =>
            $request['q'] === 'Purok 1, Barangay Poblacion, Sulop, Davao del Sur, Philippines'
        );
    }

    public function test_registration_lookup_falls_back_from_full_address_to_barangay(): void
    {
        Http::fakeSequence()
            ->push([], 200)
            ->push([[
                'lat' => '6.60234567',
                'lon' => '125.34345678',
                'display_name' => 'Poblacion, Sulop, Davao del Sur, Philippines',
            ]], 200);

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.geocode', $this->formerRebel), [
                'registration_address' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'match_level' => 'barangay',
                'latitude' => '6.60234567',
                'longitude' => '125.34345678',
            ]);

        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);
        $this->assertSame('Purok 1, Barangay Poblacion, Sulop, Davao del Sur, Philippines', $recorded[0][0]['q']);
        $this->assertSame('Barangay Poblacion, Sulop, Davao del Sur, Philippines', $recorded[1][0]['q']);
        $this->assertDatabaseCount('fr_location_histories', 0);
    }

    public function test_unresolved_registration_lookup_returns_clean_error_without_writes(): void
    {
        Http::fakeSequence()
            ->push([], 200)
            ->push([], 200)
            ->push([], 200);

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.geocode', $this->formerRebel), [
                'registration_address' => true,
            ])
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Saved residential address could not be located automatically.',
            ]);

        $this->assertCount(3, Http::recorded());
        $this->formerRebel->refresh();
        $this->assertNull($this->formerRebel->latitude);
        $this->assertNull($this->formerRebel->longitude);
        $this->assertDatabaseCount('fr_location_histories', 0);
    }

    public function test_registration_lookup_service_failure_returns_clean_error_without_writes(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.geocode', $this->formerRebel), [
                'registration_address' => true,
            ])
            ->assertStatus(503)
            ->assertJson([
                'message' => 'Location lookup is temporarily unavailable. Please try again.',
            ]);

        $this->assertCount(1, Http::recorded());
        $this->formerRebel->refresh();
        $this->assertNull($this->formerRebel->latitude);
        $this->assertNull($this->formerRebel->longitude);
        $this->assertDatabaseCount('fr_location_histories', 0);
    }

    public function test_placement_preview_returns_the_geocoded_location_without_writes(): void
    {
        $this->fakeGeocoder('6.59814080', '125.34483750', 'Poblacion, Sulop, Davao del Sur, Philippines');

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.geocode', $this->formerRebel), [
                'address' => 'Poblacion, Sulop, Davao del Sur',
                'landmark' => null,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'latitude' => '6.59814080',
                'longitude' => '125.34483750',
                'display_name' => 'Poblacion, Sulop, Davao del Sur, Philippines',
            ]);

        Http::assertSent(fn (Request $request): bool =>
            $request['q'] === 'Poblacion, Sulop, Davao del Sur, Philippines'
        );
        $this->formerRebel->refresh();
        $this->assertNull($this->formerRebel->latitude);
        $this->assertNull($this->formerRebel->longitude);
        $this->assertDatabaseCount('fr_location_histories', 0);
    }

    public function test_empty_placement_preview_is_not_reported_as_a_service_failure(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.geocode', $this->formerRebel), [
                'address' => 'Unknown placement address',
            ])
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Location could not be found. Please provide a more specific address or landmark.',
            ]);

        $this->assertDatabaseCount('fr_location_histories', 0);
    }

    public function test_placement_preview_retains_the_upstream_429_diagnostic(): void
    {
        Log::spy();
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.geocode', $this->formerRebel), [
                'address' => 'Poblacion, Sulop, Davao del Sur',
            ])
            ->assertStatus(503)
            ->assertJson([
                'message' => 'Location lookup is temporarily unavailable. Please try again.',
            ]);

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool =>
                $message === 'Nominatim geocoding returned an unsuccessful response.'
                && $context['status'] === 429
                && $context['query'] === 'Poblacion, Sulop, Davao del Sur, Philippines'
        );
        $this->assertDatabaseCount('fr_location_histories', 0);
    }

    public function test_explicit_save_uses_address_and_landmark_geocoding_and_creates_one_history_row(): void
    {
        $this->fakeGeocoder('6.81234567', '125.41234567', 'New Opon, Magsaysay, Davao del Sur, Philippines');

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.save', $this->formerRebel), [
                'placement_address' => 'Purok 2, New Opon, Magsaysay, Davao del Sur',
                'landmark' => 'New Opon Elementary School',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->formerRebel->refresh();
        $this->assertSame('Purok 2, New Opon, Magsaysay, Davao del Sur', $this->formerRebel->placement_address);
        $this->assertSame(6.81234567, (float) $this->formerRebel->latitude);
        $this->assertSame(125.41234567, (float) $this->formerRebel->longitude);
        $this->assertDatabaseHas('fr_location_histories', [
            'former_rebel_id' => $this->formerRebel->id,
            'placement_address' => 'Purok 2, New Opon, Magsaysay, Davao del Sur',
            'latitude' => 6.81234567,
            'longitude' => 125.41234567,
            'updated_by' => 'MBLRC Locator',
        ]);
        $this->assertDatabaseCount('fr_location_histories', 1);

        Http::assertSent(fn (Request $request): bool =>
            $request['q'] === 'Purok 2, New Opon, Magsaysay, Davao del Sur, near New Opon Elementary School, Philippines'
        );
    }

    public function test_client_coordinates_cannot_override_server_geocoding(): void
    {
        $this->fakeGeocoder('6.12345678', '125.87654321', 'Sulop, Davao del Sur, Philippines');

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.save', $this->formerRebel), [
                'placement_address' => 'Sulop, Davao del Sur',
                'latitude' => '14.5995',
                'longitude' => '120.9842',
            ])
            ->assertOk();

        $this->formerRebel->refresh();
        $this->assertSame(6.12345678, (float) $this->formerRebel->latitude);
        $this->assertSame(125.87654321, (float) $this->formerRebel->longitude);

        $history = $this->formerRebel->locationHistories()->sole();
        $this->assertSame(6.12345678, (float) $history->latitude);
        $this->assertSame(125.87654321, (float) $history->longitude);
    }

    public function test_unresolved_address_preserves_existing_location_and_does_not_create_history(): void
    {
        $this->setExistingLocation();
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.save', $this->formerRebel), [
                'placement_address' => 'Unknown location',
                'landmark' => 'Unknown landmark',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('placement_address');

        $this->assertExistingLocationWasPreserved();
        $this->assertDatabaseCount('fr_location_histories', 0);
    }

    public function test_geocoding_service_failure_preserves_existing_location_and_history(): void
    {
        $this->setExistingLocation();
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('mblrc.fr.location.save', $this->formerRebel), [
                'placement_address' => 'Purok 9, Sulop, Davao del Sur',
            ])
            ->assertStatus(503)
            ->assertJson([
                'message' => 'Location lookup is temporarily unavailable. Please try again.',
            ]);

        $this->assertExistingLocationWasPreserved();
        $this->assertDatabaseCount('fr_location_histories', 0);
    }

    public function test_existing_coordinates_are_rendered_directly_without_server_side_regeocoding(): void
    {
        $this->setExistingLocation();
        Http::fake();

        $this->actingAs($this->user)
            ->get(route('mblrc.fr.show', $this->formerRebel))
            ->assertOk()
            ->assertSee('data-has-saved-location="1"', false)
            ->assertSee('data-lat="6.50000000"', false)
            ->assertSee('data-lng="125.50000000"', false)
            ->assertSee('Saved location: Existing placement');

        Http::assertNothingSent();
        $this->assertDatabaseCount('fr_location_histories', 0);
    }

    public function test_profile_uses_only_the_bundled_non_clickable_map_initializer(): void
    {
        $html = $this->actingAs($this->user)
            ->get(route('mblrc.fr.show', $this->formerRebel))
            ->assertOk()
            ->getContent();
        $script = file_get_contents(resource_path('js/mblrc.js'));

        $this->assertStringNotContainsString('L.map(', $html);
        $this->assertStringNotContainsString("map.on('click'", $html);
        $this->assertStringNotContainsString('leaflet@1.9.4/dist/leaflet.js', $html);
        $this->assertSame(1, substr_count($script, 'function initLocationMap(root)'));
        $this->assertStringNotContainsString("map.on('click'", $script);
        $this->assertStringNotContainsString('draggable:', $script);
        $this->assertStringContainsString('[data-bs-toggle="tab"][href="#tab-geotag"]', $script);
        $this->assertStringContainsString("addEventListener('shown.bs.tab', refreshMapLayout)", $script);
        $this->assertStringContainsString('window.requestAnimationFrame(() => {', $script);
        $this->assertStringContainsString('map.invalidateSize()', $script);
        $this->assertStringContainsString('map.setView(marker.getLatLng(), map.getZoom()', $script);
        $this->assertStringContainsString('lookupLocation({ registrationAddress: true })', $script);
        $this->assertStringContainsString('pendingLookupKey === key', $script);
        $this->assertStringContainsString('lookupTimer = null;', $script);
    }

    private function fakeGeocoder(string $latitude, string $longitude, string $displayName): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([[
                'lat' => $latitude,
                'lon' => $longitude,
                'display_name' => $displayName,
            ]], 200),
        ]);
    }

    private function setExistingLocation(): void
    {
        $this->formerRebel->update([
            'placement_address' => 'Existing placement',
            'latitude' => '6.50000000',
            'longitude' => '125.50000000',
        ]);
    }

    private function assertExistingLocationWasPreserved(): void
    {
        $this->formerRebel->refresh();
        $this->assertSame('Existing placement', $this->formerRebel->placement_address);
        $this->assertSame(6.5, (float) $this->formerRebel->latitude);
        $this->assertSame(125.5, (float) $this->formerRebel->longitude);
    }
}
