<?php

namespace App\Policies;

use App\Enums\Ib39FeaDocumentStatus;
use App\Models\Ib39FeaDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;

class Ib39FeaDocumentVersionPolicy
{
    public function preview(User $user, Ib39FeaDocumentVersion $version): bool
    {
        return $this->hasAccess($user, $version);
    }

    public function download(User $user, Ib39FeaDocumentVersion $version): bool
    {
        return $this->hasAccess($user, $version);
    }

    private function hasAccess(User $user, Ib39FeaDocumentVersion $version): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('39th_ib')) {
            return $version->processing()->whereHas('surfacedFormerRebel')->exists();
        }

        $version->loadMissing('document');
        if ($version->document?->status !== Ib39FeaDocumentStatus::Completed
            || $version->document->current_final_version_id !== $version->id) {
            return false;
        }

        if ($user->hasRole('pswdo')) {
            $version->loadMissing('processing.surfacedFormerRebel.pswdoEnrollment');
            $record = $version->processing?->surfacedFormerRebel;

            return $record?->pswdoEnrollment !== null
                && $user->can('view', $record->pswdoEnrollment);
        }

        if (! $user->hasRole('japic')) {
            return false;
        }

        $version->loadMissing('processing.surfacedFormerRebel.japicCertificationProcessing');
        $certification = $version->processing?->surfacedFormerRebel?->japicCertificationProcessing;

        return $certification instanceof JapicCertificationProcessing
            && $user->can('view', $certification);
    }
}
