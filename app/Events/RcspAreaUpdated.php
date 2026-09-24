<?php

namespace App\Events;

use App\Models\MapBarangay;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An RCSP area's former-rebel count changed, so any open map should repaint.
 *
 * Replaces the legacy final_mapping/rcsp_updates.php, which held an SSE
 * connection open and polled `frmap_barangays` in a `while (true)` loop to
 * decide when to tell the client to redraw. This pushes on write instead.
 */
class RcspAreaUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public MapBarangay $area)
    {
    }

    public function broadcastOn(): Channel
    {
        // Everyone who can see the map sees the same public colouring.
        return new Channel('rcsp-areas');
    }

    public function broadcastAs(): string
    {
        return 'area.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->area->id,
            'municipality' => $this->area->municipality,
            'barangay' => $this->area->barangay,
            'frs' => (int) $this->area->frs,
            'status' => $this->area->status,
            'color' => $this->area->infestation_color,
        ];
    }
}
