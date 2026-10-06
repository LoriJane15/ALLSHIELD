<?php

namespace App\Policies;

use App\Enums\Ib39CdrStatus;
use App\Models\Ib39CdrProcessing;
use App\Models\User;

class Ib39CdrProcessingPolicy
{
    public function view(User $user, Ib39CdrProcessing $processing): bool
    {
        if ($this->canView($user, $processing)) {
            return true;
        }

        if (! $user->is_active) {
            return false;
        }

        $processing->loadMissing('surfacedFormerRebel.pswdoEnrollment', 'surfacedFormerRebel.japicCertificationProcessing');
        $record = $processing->surfacedFormerRebel;

        if ($user->hasRole('pswdo')) {
            return $record?->pswdoEnrollment !== null
                && $user->can('view', $record->pswdoEnrollment);
        }

        return $user->hasRole('japic')
            && $record?->japicCertificationProcessing !== null
            && $user->can('view', $record->japicCertificationProcessing);
    }

    public function comment(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->view($user, $processing);
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
        return $this->canView($user, $processing)
            || ($processing->status === Ib39CdrStatus::Completed
                && $processing->form()->exists()
                && $this->view($user, $processing));
    }

    public function printDraft(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->canView($user, $processing);
    }

    public function downloadDraft(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->canView($user, $processing) && $processing->form()->exists();
    }

    public function uploadPhoto(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->updateDraft($user, $processing);
    }

    public function uploadFinal(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->hasAccess($user, $processing)
            && in_array($processing->status, [Ib39CdrStatus::Pending, Ib39CdrStatus::Ongoing], true)
            && ! $processing->finalDocument()->exists();
    }

    public function viewPhoto(User $user, Ib39CdrProcessing $processing): bool
    {
        return $this->canView($user, $processing)
            || ($processing->status === Ib39CdrStatus::Completed && $this->view($user, $processing));
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
