<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Ib39CdrForm;
use App\Models\Ib39CdrProcessing;
use App\Models\User;
use App\Support\Ib39CdrFormSchema;
use Illuminate\Support\Facades\DB;

class Ib39CdrDraftService
{
    public function __construct(private readonly Ib39CdrStatusService $statuses) {}

    public function save(
        Ib39CdrProcessing $processing,
        array $content,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Ib39CdrForm {
        return DB::transaction(function () use ($processing, $content, $actor, $ipAddress, $userAgent) {
            $locked = Ib39CdrProcessing::query()->lockForUpdate()->findOrFail($processing->id);
            abort_unless($locked->surfacedFormerRebel()->whereDoesntHave('cancellation')->exists(), 403);
            $this->statuses->recordDraftSaved($locked, $actor, $ipAddress, $userAgent);

            $form = Ib39CdrForm::query()
                ->where('cdr_processing_id', $locked->id)
                ->lockForUpdate()
                ->firstOrFail();

            $form->update([
                'schema_version' => Ib39CdrFormSchema::VERSION,
                'content' => $content,
                'last_edited_by' => $actor->id,
            ]);
            AuditLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'ib39_cdr_draft_saved',
                'entity_type' => Ib39CdrProcessing::class,
                'entity_id' => $locked->id,
                'previous_values' => null,
                'new_values' => ['schema_version' => Ib39CdrFormSchema::VERSION],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
            ]);

            return $form->fresh();
        }, 5);
    }
}
