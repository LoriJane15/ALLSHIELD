<?php

namespace App\Policies;

use App\Models\RcspForm;
use App\Models\User;

class RcspFormPolicy
{
    public function view(User $user, RcspForm $form): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'lgu' && $user->municipality_id !== null
                && $user->municipality_id === $form->rcspBarangay?->municipality_id);
    }

    public function viewEvidence(User $user, RcspForm $form): bool
    {
        return $this->view($user, $form);
    }

    public function comment(User $user, RcspForm $form): bool
    {
        return $this->view($user, $form) && $form->rcspBarangay?->status !== 'Completed';
    }
}
