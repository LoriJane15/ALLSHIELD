<?php

namespace App\Services;

use App\Enums\Ib39FeaComplianceStatus;
use App\Enums\Ib39FeaDocumentStatus;
use App\Enums\Ib39FeaDocumentType;
use App\Models\Ib39FeaProcessing;
use App\Models\Ib39SurfacedFormerRebel;
use Illuminate\Support\Facades\DB;

class Ib39FeaSynchronizationService
{
    public function ensureForSurfacedFormerRebel(Ib39SurfacedFormerRebel $surfacedFormerRebel): ?Ib39FeaProcessing
    {
        return DB::transaction(function () use ($surfacedFormerRebel) {
            $record = Ib39SurfacedFormerRebel::query()->lockForUpdate()->findOrFail($surfacedFormerRebel->id);

            if (! $record->possessed_firearms) {
                return $record->feaProcessing()->first();
            }

            $processing = $record->feaProcessing()->firstOrCreate();

            foreach (Ib39FeaDocumentType::cases() as $type) {
                $processing->documents()->firstOrCreate(
                    ['document_type' => $type],
                    [
                        'status' => Ib39FeaDocumentStatus::Pending,
                        'compliance_status' => Ib39FeaComplianceStatus::None,
                        'is_required' => true,
                    ],
                );
            }

            return $processing->load('documents');
        }, 5);
    }
}
