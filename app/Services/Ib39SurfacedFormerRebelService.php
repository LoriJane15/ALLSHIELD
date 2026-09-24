<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Ib39SurfacedFormerRebelService
{
    public function __construct(
        private readonly Ib39ReferenceSequenceService $references,
        private readonly Ib39CdrProcessingService $cdrProcessings,
        private readonly Ib39FeaSynchronizationService $feaProcessings,
    ) {}

    public function create(
        array $attributes,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Ib39SurfacedFormerRebel {
        abort_unless($actor->hasRole('39th_ib'), 403);

        $attributes['province'] ??= Ib39SurfacedFormerRebel::DEFAULT_PROVINCE;
        $this->validateLocation($attributes);

        return DB::transaction(function () use ($attributes, $actor, $ipAddress, $userAgent) {
            $record = new Ib39SurfacedFormerRebel;
            $record->fill($attributes);
            $record->reference_number = $this->references->reserveSurfacedFormerRebelReference();
            $record->created_by = $actor->id;
            $record->save();

            $this->cdrProcessings->ensureForSurfacedFormerRebel(
                $record,
                $actor,
                $ipAddress,
                $userAgent,
            );

            $this->feaProcessings->ensureForSurfacedFormerRebel($record);

            AuditLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'ib39_surfaced_former_rebel_recorded',
                'entity_type' => Ib39SurfacedFormerRebel::class,
                'entity_id' => $record->id,
                'previous_values' => null,
                'new_values' => [
                    'reference_number' => $record->reference_number,
                    'category' => $record->category->value,
                    'municipality_id' => $record->municipality_id,
                    'barangay_id' => $record->barangay_id,
                    'surfaced_at' => $record->surfaced_at->toDateString(),
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $record;
        }, 5);
    }

    private function validateLocation(array $attributes): void
    {
        if (($attributes['province'] ?? null) !== Ib39SurfacedFormerRebel::DEFAULT_PROVINCE) {
            throw ValidationException::withMessages([
                'province' => 'The province must be Davao del Sur.',
            ]);
        }

        if (! isset($attributes['barangay_id'])) {
            return;
        }

        $belongsToMunicipality = Barangay::query()
            ->whereKey($attributes['barangay_id'])
            ->where('municipality_id', $attributes['municipality_id'] ?? null)
            ->exists();

        if (! $belongsToMunicipality) {
            throw ValidationException::withMessages([
                'barangay_id' => 'The selected barangay does not belong to the selected municipality.',
            ]);
        }
    }
}
