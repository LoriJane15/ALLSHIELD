<?php

use App\Models\RcspForm;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
| Comment thread for one RCSP form — the legacy Ratchet "room" keyed by form id.
| The legacy server let any connected client join any room; here the same
| municipality rule the HTTP endpoints enforce is applied to the socket too.
*/
Broadcast::channel('rcsp-form.{formId}', function (User $user, int $formId) {
    $form = RcspForm::with('rcspBarangay')->find($formId);

    if (! $form) {
        return false;
    }

    return match ($user->role) {
        // Katuparan Center reviews every submission; AFP observes.
        'admin', 'super_admin', 'afp' => true,
        // An LGU only sees threads for its own municipality.
        'lgu' => $user->municipality_id === $form->rcspBarangay?->municipality_id,
        default => false,
    };
});
