<?php

namespace App\Policies;

use App\Models\PswdoEnrollmentDocument;
use App\Models\User;

class PswdoEnrollmentDocumentPolicy
{
    public function preview(User $user, PswdoEnrollmentDocument $document): bool
    {
        return $this->hasAccess($user, $document);
    }

    public function download(User $user, PswdoEnrollmentDocument $document): bool
    {
        return $this->hasAccess($user, $document);
    }

    private function hasAccess(User $user, PswdoEnrollmentDocument $document): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $document->loadMissing('enrollment');

        $enrollment = $document->enrollment;
        if ($enrollment === null) {
            return false;
        }

        if ($user->hasRole('pswdo')) {
            return $user->can('view', $enrollment);
        }

        if ($user->hasRole('39th_ib')) {
            $enrollment->loadMissing('surfacedFormerRebel');

            return $enrollment->surfacedFormerRebel !== null
                && $user->can('view', $enrollment->surfacedFormerRebel);
        }

        if ($user->hasRole('japic')) {
            $enrollment->loadMissing('surfacedFormerRebel.japicCertificationProcessing');
            $processing = $enrollment->surfacedFormerRebel?->japicCertificationProcessing;

            return $processing !== null && $user->can('view', $processing);
        }

        return false;
    }
}
