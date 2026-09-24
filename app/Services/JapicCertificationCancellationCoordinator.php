<?php

namespace App\Services;

use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39FrCancellation;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationProcessing;
use App\Models\User;

class JapicCertificationCancellationCoordinator
{
    public function coordinate(Ib39SurfacedFormerRebel $surfacedFr, Ib39FrCancellation $cancellation, User $actor): void
    {
        $processing = JapicCertificationProcessing::query()
            ->where('ib39_surfaced_former_rebel_id', $surfacedFr->id)
            ->lockForUpdate()
            ->first();
        if (! $processing || $processing->status === JapicCertificationStatus::Cancelled) {
            return;
        }

        $fromStatus = $processing->status;
        $event = JapicCertificationEvent::FrCancelled;
        $toStatus = JapicCertificationStatus::Cancelled;
        if ($processing->status === JapicCertificationStatus::Completed) {
            $event = JapicCertificationEvent::FrCancelledAfterCompletion;
            $toStatus = JapicCertificationStatus::Completed;
        } else {
            $processing->forceFill([
                'status' => JapicCertificationStatus::Cancelled,
                'cancelled_at' => $cancellation->cancelled_at,
                'cancelled_by' => $actor->id,
                'lock_version' => $processing->lock_version + 1,
            ])->save();
        }

        $processing->histories()->create([
            'actor_id' => $actor->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event' => $event,
            'metadata' => ['ib39_cancellation_id' => $cancellation->id],
            'occurred_at' => $cancellation->cancelled_at,
        ]);
    }
}
