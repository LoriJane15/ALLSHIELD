<?php

namespace App\Policies;

use App\Enums\Ib39CdrDocumentSource;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Services\PswdoEligibilityService;

class Ib39CdrDocumentVersionPolicy
{
    public function preview(User $user, Ib39CdrDocumentVersion $version): bool
    {
        return $this->hasAccess($user, $version);
    }

    public function download(User $user, Ib39CdrDocumentVersion $version): bool
    {
        return $this->hasAccess($user, $version)
            && $version->source_type === Ib39CdrDocumentSource::Uploaded;
    }

    public function print(User $user, Ib39CdrDocumentVersion $version): bool
    {
        return $this->hasAccess($user, $version)
            && $version->source_type === Ib39CdrDocumentSource::Generated;
    }

    private function hasAccess(User $user, Ib39CdrDocumentVersion $version): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('39th_ib')) {
            return $version->processing()->whereHas('surfacedFormerRebel')->exists();
        }

        if ($user->hasRole('pswdo')) {
            $version->loadMissing('processing.surfacedFormerRebel.pswdoEnrollment');
            $record = $version->processing?->surfacedFormerRebel;

            return $version->processing?->current_final_version_id === $version->id
                && $record?->pswdoEnrollment !== null
                && app(PswdoEligibilityService::class)->isEligible($record);
        }

        if (! $user->hasRole('japic')) {
            return false;
        }

        $version->loadMissing('processing.surfacedFormerRebel.japicCertificationProcessing');
        $processing = $version->processing;
        $certification = $processing?->surfacedFormerRebel?->japicCertificationProcessing;

        return $processing?->current_final_version_id === $version->id
            && $certification instanceof JapicCertificationProcessing
            && $user->can('view', $certification);
    }
}
