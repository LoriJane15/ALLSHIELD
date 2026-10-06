<?php

namespace App\Policies;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Models\Ib39CdrFinalDocument;
use App\Models\JapicCertificationProcessing;
use App\Models\User;

class Ib39CdrFinalDocumentPolicy
{
    public function preview(User $user, Ib39CdrFinalDocument $document): bool
    {
        return $this->hasAccess($user, $document);
    }

    public function download(User $user, Ib39CdrFinalDocument $document): bool
    {
        return $user->hasRole('39th_ib') && $this->hasAccess($user, $document);
    }

    public function print(User $user, Ib39CdrFinalDocument $document): bool
    {
        return $user->hasRole('39th_ib')
            && $this->hasAccess($user, $document)
            && $document->source_type === Ib39CdrDocumentSource::Generated;
    }

    private function hasAccess(User $user, Ib39CdrFinalDocument $document): bool
    {
        if (! $user->is_active || $document->processing?->status !== Ib39CdrStatus::Completed) {
            return false;
        }

        if ($user->hasRole('39th_ib')) {
            return $document->processing()->whereHas('surfacedFormerRebel')->exists();
        }

        if ($user->hasRole('pswdo')) {
            $document->loadMissing('processing.surfacedFormerRebel.pswdoEnrollment');
            $record = $document->processing?->surfacedFormerRebel;

            return $record?->pswdoEnrollment !== null
                && $user->can('view', $record->pswdoEnrollment);
        }

        if (! $user->hasRole('japic')) {
            return false;
        }

        $document->loadMissing('processing.surfacedFormerRebel.japicCertificationProcessing');
        $certification = $document->processing?->surfacedFormerRebel?->japicCertificationProcessing;

        return $certification instanceof JapicCertificationProcessing
            && $user->can('view', $certification);
    }
}
