<?php

namespace App\Policies;

use App\Enums\JapicCertificationStatus;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Services\PswdoEligibilityService;

class JapicCertificationDocumentVersionPolicy
{
    public function preview(User $user, JapicCertificationDocumentVersion $version): bool
    {
        return $this->hasAccess($user, $version);
    }

    public function download(User $user, JapicCertificationDocumentVersion $version): bool
    {
        return $this->hasAccess($user, $version);
    }

    private function hasAccess(User $user, JapicCertificationDocumentVersion $version): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $processing = $version->processing;
        if (! $processing instanceof JapicCertificationProcessing
            || $processing->status !== JapicCertificationStatus::Completed
            || $processing->current_final_version_id !== $version->id) {
            return false;
        }

        if ($user->hasRole('japic')) {
            return app(JapicCertificationProcessingPolicy::class)->view($user, $processing);
        }

        if ($user->hasRole('pswdo')) {
            $processing->loadMissing('surfacedFormerRebel.pswdoEnrollment');
            $record = $processing->surfacedFormerRebel;

            return $record?->pswdoEnrollment !== null
                && app(PswdoEligibilityService::class)->isEligible($record);
        }

        if (! $user->hasRole('39th_ib')) {
            return false;
        }

        $processing->loadMissing('surfacedFormerRebel');

        return $processing->surfacedFormerRebel !== null
            && $user->can('view', $processing->surfacedFormerRebel);
    }
}
