<?php

namespace App\Policies;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChatConversationPolicy
{
    public function view(User $user, ChatConversation $conversation): Response
    {
        if (! $user->is_active || ! $conversation->hasParticipant($user)) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    public function send(User $user, ChatConversation $conversation): Response
    {
        $view = $this->view($user, $conversation);
        if ($view->denied()) {
            return $view;
        }

        $other = $conversation->user_one_id === $user->getKey()
            ? $conversation->userTwo
            : $conversation->userOne;

        if (! $other?->is_active || ! array_key_exists($other->role, config('shield.roles', []))) {
            return Response::deny('Messaging is unavailable while the other account is inactive.');
        }

        return Response::allow();
    }
}
