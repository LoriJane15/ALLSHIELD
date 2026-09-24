<?php

namespace App\Services;

use App\Enums\Ib39CdrStatus;
use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39CdrProcessing;
use App\Models\JapicCertificationProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JapicCertificationIntakeService
{
    public function __construct(private readonly JapicCertificationIntakeNotifier $notifier) {}

    public function createForCompletedCdr(Ib39CdrProcessing $cdr): ?JapicCertificationProcessing
    {
        return DB::transaction(function () use ($cdr): ?JapicCertificationProcessing {
            $locked = Ib39CdrProcessing::query()->lockForUpdate()->findOrFail($cdr->id);
            $surfacedFr = $locked->surfacedFormerRebel()->lockForUpdate()->firstOrFail();

            if ($surfacedFr->cancellation()->exists()
                || $locked->status !== Ib39CdrStatus::Completed
                || $locked->current_final_version_id === null
                || ! $locked->documentVersions()->whereKey($locked->current_final_version_id)->exists()) {
                return null;
            }

            $existing = JapicCertificationProcessing::query()
                ->where('ib39_surfaced_former_rebel_id', $surfacedFr->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $receivedAt = $locked->completed_at ?? $locked->currentFinalVersion()->value('finalized_at');
            if ($receivedAt === null) {
                return null;
            }

            $attributes = [
                'status' => JapicCertificationStatus::Pending,
                'triggering_cdr_document_version_id' => $locked->current_final_version_id,
                'received_at' => $receivedAt,
                'due_at' => $receivedAt->copy()->addDays(14),
                'lock_version' => 0,
            ];
            if (Schema::hasColumn('japic_certification_processings', 'received_on')) {
                $attributes['received_on'] = $receivedAt->toDateString();
                $attributes['due_on'] = $receivedAt->copy()->addDays(14)->toDateString();
                $attributes['trigger_source'] = 'cdr_completion';
            }

            $processing = $surfacedFr->japicCertificationProcessing()->create($attributes);
            $processing->histories()->create([
                'from_status' => null,
                'to_status' => JapicCertificationStatus::Pending,
                'event' => JapicCertificationEvent::IntakeCreated,
                'occurred_at' => $receivedAt,
            ]);

            DB::afterCommit(function () use ($processing): void {
                try {
                    $this->notifier->notify($processing->fresh());
                } catch (\Throwable $exception) {
                    report($exception);
                    logger()->warning('JAPIC intake notification delivery failed.', [
                        'processing_id' => $processing->id,
                    ]);
                }
            });

            return $processing;
        }, 5);
    }
}
