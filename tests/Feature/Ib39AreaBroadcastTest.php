<?php

namespace Tests\Feature;

use App\Events\RcspAreaUpdated;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class Ib39AreaBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Barangay $barangay;

    private User $ib39;

    protected function setUp(): void
    {
        parent::setUp();
        $municipality = Municipality::query()->create(['name' => 'Broadcast Municipality']);
        $this->barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'Broadcast Barangay',
        ]);
        $this->ib39 = User::factory()->role('39th_ib')->create();
    }

    public function test_history_change_broadcasts_only_aggregate_current_state_on_a_private_channel(): void
    {
        Event::fake([RcspAreaUpdated::class]);

        $this->actingAs($this->ib39)->post(route('ib39.areas.store'), [
            'barangay_id' => $this->barangay->id,
            'effective_date' => '2026-09-13',
            'frs' => 12,
        ])->assertRedirect(route('ib39.areas.index'));

        Event::assertDispatched(RcspAreaUpdated::class, function (RcspAreaUpdated $event): bool {
            $channel = $event->broadcastOn();

            return $channel instanceof PrivateChannel
                && $channel->name === 'private-rcsp-areas'
                && $event->broadcastAs() === 'area.updated'
                && $event->broadcastWith() === [
                    'id' => $event->area->id,
                    'municipality' => 'Broadcast Municipality',
                    'barangay' => 'Broadcast Barangay',
                    'frs' => 12,
                    'status' => 'Expansion',
                    'color' => 'rgba(255,255,0,0.5)',
                ];
        });
    }

    public function test_only_active_39th_ib_users_can_authorize_the_private_area_channel(): void
    {
        $authorizer = Broadcast::connection()->getChannels()->get('rcsp-areas');
        $this->assertNotNull($authorizer);
        $this->assertTrue($authorizer($this->ib39));
        $this->assertFalse($authorizer(User::factory()->role('admin')->create()));
        $inactive = User::factory()->role('39th_ib')->create(['is_active' => false]);
        $this->assertFalse($authorizer($inactive));

        $this->actingAs($inactive)->post('/broadcasting/auth', [
            'channel_name' => 'private-rcsp-areas',
            'socket_id' => '1234.5678',
        ])->assertRedirect(route('login'));
    }
}
