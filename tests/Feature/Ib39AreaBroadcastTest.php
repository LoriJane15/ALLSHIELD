<?php

namespace Tests\Feature;

use App\Events\RcspAreaUpdated;
use App\Models\{MapBarangay, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class Ib39AreaBroadcastTest extends TestCase
{
    use DatabaseTransactions;

    public function test_changing_an_area_broadcasts_so_open_maps_repaint(): void
    {
        $this->skipUnlessLegacyDataPresent();
        Event::fake([RcspAreaUpdated::class]);

        $area = MapBarangay::where('frs', 0)->firstOrFail();
        $user = User::where('role', '39th_ib')->firstOrFail();

        $this->actingAs($user)->post('/39th-ib/areas', [
            'municipality' => $area->municipality,
            'barangay' => $area->barangay,
            'frs' => 12,
        ])->assertRedirect();

        Event::assertDispatched(RcspAreaUpdated::class, function ($e) use ($area) {
            $payload = $e->broadcastWith();

            return $e->area->id === $area->id
                && $e->broadcastOn()->name === 'rcsp-areas'
                && $e->broadcastAs() === 'area.updated'
                && $payload['status'] === 'Expansion'
                && $payload['frs'] === 12;
        });
    }

    public function test_removing_an_area_also_broadcasts(): void
    {
        $this->skipUnlessLegacyDataPresent();
        Event::fake([RcspAreaUpdated::class]);

        $area = MapBarangay::where('frs', '>', 0)->firstOrFail();
        $user = User::where('role', '39th_ib')->firstOrFail();

        $this->actingAs($user)->delete("/39th-ib/areas/{$area->id}")->assertRedirect();

        Event::assertDispatched(RcspAreaUpdated::class);
    }
}
