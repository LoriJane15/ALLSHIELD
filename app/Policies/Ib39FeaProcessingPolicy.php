<?php

namespace App\Policies;

use App\Models\Ib39FeaProcessing;
use App\Models\User;

class Ib39FeaProcessingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole('39th_ib');
    }

    public function view(User $user, Ib39FeaProcessing $processing): bool
    {
        return $user->is_active
            && $user->hasRole('39th_ib')
            && $processing->surfacedFormerRebel()->exists();
    }

    public function completeDocument(User $user, Ib39FeaProcessing $processing): bool
    {
        return false;
    }

    public function finalize(User $user, Ib39FeaProcessing $processing): bool
    {
        return false;
    }

    public function uploadFinalCopy(User $user, Ib39FeaProcessing $processing): bool
    {
        return false;
    }

    private function hasAccess(User $user, Ib39FeaProcessing $processing): bool
    {
        return $user->is_active
            && $user->hasRole('39th_ib')
            && $processing->surfacedFormerRebel()->whereDoesntHave('cancellation')->exists();
    }
}
