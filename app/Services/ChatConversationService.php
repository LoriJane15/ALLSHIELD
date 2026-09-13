<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatConversationService
{
    public function start(User $actor, int $recipientId): ChatConversation
    {
        if ($actor->getKey() === $recipientId) {
            $this->unavailable();
        }

        [$userOneId, $userTwoId] = collect([$actor->getKey(), $recipientId])->sort()->values()->all();

        try {
            $conversation = DB::transaction(function () use ($actor, $recipientId, $userOneId, $userTwoId): ChatConversation {
                $users = User::query()
                    ->whereKey([$userOneId, $userTwoId])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $freshActor = $users->get($actor->getKey());
                $recipient = $users->get($recipientId);

                if (! $this->eligible($freshActor) || ! $this->eligible($recipient)) {
                    $this->unavailable();
                }

                return ChatConversation::query()->firstOrCreate([
                    'user_one_id' => $userOneId,
                    'user_two_id' => $userTwoId,
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $conversation = ChatConversation::query()
                ->where('user_one_id', $userOneId)
                ->where('user_two_id', $userTwoId)
                ->first();

            if (! $conversation) {
                throw $exception;
            }
        }

        return $conversation->load(['userOne:id,name,role,is_active', 'userTwo:id,name,role,is_active']);
    }

    private function eligible(?User $user): bool
    {
        return (bool) $user?->is_active
            && array_key_exists($user->role, config('shield.roles', []));
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages([
            'recipient_id' => 'The selected account is unavailable.',
        ]);
    }
}
