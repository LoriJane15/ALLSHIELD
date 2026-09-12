<?php

namespace App\Services;

use App\Enums\Ib39CdrStatus;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39SurfacedFormerRebel;
use Illuminate\Database\Eloquent\Builder;

class PswdoEligibilityService
{
    public function isEligible(Ib39SurfacedFormerRebel $record): bool
    {
        return $this->apply(Ib39SurfacedFormerRebel::query())->whereKey($record->getKey())->exists();
    }

    public function apply(Builder $query): Builder
    {
        return $query->whereDoesntHave('cancellation')
            ->whereHas('cdrProcessing', fn (Builder $cdr) => $cdr
                ->where('status', Ib39CdrStatus::Completed->value)
                ->whereNotNull('current_final_version_id')
                ->whereHas('currentFinalVersion', fn (Builder $version) => $version
                    ->whereColumn('ib39_cdr_document_versions.cdr_processing_id', 'ib39_cdr_processings.id')))
            ->whereHas('japicCertificationProcessing', fn (Builder $japic) => $japic
                ->where('status', JapicCertificationStatus::Completed->value)
                ->whereNotNull('current_final_version_id')
                ->whereHas('currentFinalVersion', fn (Builder $version) => $version
                    ->whereColumn('japic_certification_document_versions.processing_id', 'japic_certification_processings.id')));
    }
}
