<?php

namespace App\Services;

use App\Enums\Ib39CdrStatus;
use App\Models\Ib39CdrProcessing;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Ib39CdrProcessingService
{
    public function ensureForSurfacedFormerRebel(
        Ib39SurfacedFormerRebel $surfacedFormerRebel,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Ib39CdrProcessing {
        abort_unless($actor->is_active && $actor->hasRole('39th_ib'), 403);

        return DB::transaction(function () use ($surfacedFormerRebel, $actor, $ipAddress, $userAgent) {
            $processing = $surfacedFormerRebel->cdrProcessing()->firstOrCreate([], [
                'status' => Ib39CdrStatus::Pending,
            ]);

            $processing->form()->firstOrCreate([], [
                'schema_version' => 1,
                'content' => null,
            ]);

            if ($processing->wasRecentlyCreated) {
                $processing->statusHistories()->create([
                    'user_id' => $actor->id,
                    'from_status' => null,
                    'to_status' => Ib39CdrStatus::Pending,
                    'event' => 'surfaced_fr_created',
                    'remarks' => null,
                    'delay_reason' => null,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                ]);
            }

            return $processing->loadMissing(['form', 'statusHistories']);
        }, 5);
    }
}
