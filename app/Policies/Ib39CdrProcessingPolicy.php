<?php

namespace App\Policies;

use App\Enums\Ib39CdrStatus;
use App\Models\Ib39CdrProcessing;
use App\Models\User;

class Ib39CdrProcessingPolicy
{
    public function view(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->canView($user, $processing);
    }

    public function start(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->hasAccess($user, $processing) && $processing->status !== Ib39CdrStatus::Completed;
    }

    public function updateDraft(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->start($user, $processing);
    }

    public function previewDraft(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->canView($user, $processing);
    }

    public function printDraft(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->canView($user, $processing);
    }

    public function uploadPhoto(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->updateDraft($user, $processing);
    }

    public function finalize(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->hasAccess($user, $processing) && $processing->status === Ib39CdrStatus::Ongoing;
    }

    public function uploadFinal(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->hasAccess($user, $processing)
            && in_array($processing->status, [Ib39CdrStatus::Pending, Ib39CdrStatus::Ongoing], true)
            && $processing->current_final_version_id === null;
    }

    public function replaceFinal(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->hasAccess($user, $processing)
            && $processing->status === Ib39CdrStatus::Completed
            && $processing->current_final_version_id !== null;
    }

    public function viewVersionHistory(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->canView($user, $processing);
    }

    public function viewPhoto(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->canView($user, $processing);
    }

    private function hasAccess(User $user, Ib39CdrProcessing $processing): bool
    {
        return $user->is_active
            && $user->hasRole('39th_ib')
            && $processing->surfacedFormerRebel()->whereDoesntHave('cancellation')->exists();
    }

    private function canView(User $user, Ib39CdrProcessing $processing): bool
    {
        return $user->is_active
            && $user->hasRole('39th_ib')
            && $processing->surfacedFormerRebel()->exists();
    }
}
