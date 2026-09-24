<?php

namespace App\Services;

use App\Enums\Ib39CdrStatus;
use App\Models\AuditLog;
use App\Models\Ib39CdrProcessing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Ib39CdrStatusService
{
    public function start(
        Ib39CdrProcessing $processing,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Ib39CdrProcessing {
        return $this->beginOngoing($processing, $actor, 'processing_started', $ipAddress, $userAgent);
    }

    public function recordDraftSaved(
        Ib39CdrProcessing $processing,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Ib39CdrProcessing {
        return $this->beginOngoing($processing, $actor, 'draft_processing_started', $ipAddress, $userAgent);
    }

    private function beginOngoing(
        Ib39CdrProcessing $processing,
        User $actor,
        string $event,
        ?string $ipAddress,
        ?string $userAgent,
    ): Ib39CdrProcessing {
        abort_unless($actor->is_active && $actor->hasRole('39th_ib'), 403);

        return DB::transaction(function () use ($processing, $actor, $event, $ipAddress, $userAgent) {
            $locked = Ib39CdrProcessing::query()->lockForUpdate()->findOrFail($processing->id);
            abort_unless($locked->surfacedFormerRebel()->whereDoesntHave('cancellation')->exists(), 403);

            if ($locked->status === Ib39CdrStatus::Completed) {
                throw ValidationException::withMessages([
                    'cdr' => 'A completed CDR cannot be started or changed through draft processing.',
                ]);
            }

            if ($locked->status === Ib39CdrStatus::Ongoing) {
                return $locked;
            }

            $startedAt = $locked->started_at ?? now();
            $fromStatus = $locked->status;
            $locked->update([
                'status' => Ib39CdrStatus::Ongoing,
                'started_at' => $startedAt,
            ]);
            $locked->statusHistories()->create([
                'user_id' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => Ib39CdrStatus::Ongoing,
                'event' => $event,
                'remarks' => null,
                'delay_reason' => null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
            ]);
            AuditLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'ib39_cdr_'.$event,
                'entity_type' => Ib39CdrProcessing::class,
                'entity_id' => $locked->id,
                'previous_values' => ['status' => $fromStatus->value],
                'new_values' => ['status' => Ib39CdrStatus::Ongoing->value],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
            ]);

            return $locked->fresh();
        }, 5);
    }
}
