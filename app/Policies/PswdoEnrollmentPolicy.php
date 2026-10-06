<?php

namespace App\Policies;

use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\PswdoEnrollment;
use App\Models\User;
use App\Services\PswdoEligibilityService;

class PswdoEnrollmentPolicy
{
    public function __construct(private readonly PswdoEligibilityService $eligibility) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole('pswdo');
    }

    public function view(User $user, PswdoEnrollment $enrollment): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }
        $enrollment->loadMissing('surfacedFormerRebel');

        return $enrollment->surfacedFormerRebel !== null
            && $this->eligibility->isEligible($enrollment->surfacedFormerRebel);
    }

    public function comment(User $user, PswdoEnrollment $enrollment): bool
    {
        return $this->view($user, $enrollment);
    }

    public function uploadDocument(User $user, PswdoEnrollment $enrollment): bool
    {
        return $this->view($user, $enrollment);
    }

    public function editDraft(
        User $user,
        PswdoEnrollment $enrollment,
        PswdoEnrollmentDocumentType $documentType = PswdoEnrollmentDocumentType::EclipEnrollmentForm,
    ): bool {
        if (! $this->view($user, $enrollment)) {
            return false;
        }

        $enrollment->loadMissing('documents');

        return ! $enrollment->documents->contains(
            fn ($document): bool => $document->document_type === $documentType
                && $document->isConfirmedFinal()
        );
    }
}
