<?php

namespace App\Policies;

use App\Enums\JapicCertificationStatus;
use App\Models\JapicCertificationProcessing;
use App\Models\User;

class JapicCertificationProcessingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole('japic');
    }

    public function view(User $user, JapicCertificationProcessing $processing): bool
    {
        return $this->viewAny($user)
            && ($processing->assigned_to === null || $processing->assigned_to === $user->id);
    }

    public function update(User $user, JapicCertificationProcessing $processing): bool
    {
        return $this->view($user, $processing) && $processing->status->isActive();
    }

    public function editDraft(User $user, JapicCertificationProcessing $processing): bool
    {
        return $this->view($user, $processing) && in_array($processing->status, [JapicCertificationStatus::Pending, JapicCertificationStatus::Drafting], true)
            && ! $processing->surfacedFormerRebel()->whereHas('cancellation')->exists();
    }

    public function saveDraft(User $user, JapicCertificationProcessing $processing): bool
    {
        return $this->editDraft($user, $processing);
    }

    public function uploadPhoto(User $user, JapicCertificationProcessing $processing): bool
    {
        return $this->editDraft($user, $processing);
    }

    public function previewDraft(User $user, JapicCertificationProcessing $processing): bool
    {
        return $this->view($user, $processing) && $processing->draft()->exists();
    }

    public function printDraft(User $user, JapicCertificationProcessing $processing): bool
    {
        return $this->previewDraft($user, $processing);
    }

    public function submitForSigning(User $user, JapicCertificationProcessing $processing): bool
    {
        return $this->view($user, $processing) && $processing->status === JapicCertificationStatus::Drafting
            && ! $processing->surfacedFormerRebel()->whereHas('cancellation')->exists();
    }

    public function confirmSigningComplete(User $user, JapicCertificationProcessing $processing): bool
    {
        return $this->view($user, $processing) && $processing->status === JapicCertificationStatus::ForSigning
            && ! $processing->surfacedFormerRebel()->whereHas('cancellation')->exists();
    }

    public function uploadFinal(User $user, JapicCertificationProcessing $processing): bool
    {
        return $this->view($user, $processing)
            && in_array($processing->status, [
                JapicCertificationStatus::Pending,
                JapicCertificationStatus::Drafting,
                JapicCertificationStatus::ForSigning,
                JapicCertificationStatus::AwaitingFinalUpload,
            ], true)
            && $processing->current_final_version_id === null
            && ! $processing->surfacedFormerRebel()->whereHas('cancellation')->exists();
    }
}
