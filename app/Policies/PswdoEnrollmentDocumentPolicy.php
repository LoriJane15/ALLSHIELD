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
        $document->loadMissing('enrollment');

        return $document->enrollment !== null
            && $user->can('view', $document->enrollment);
    }
}
