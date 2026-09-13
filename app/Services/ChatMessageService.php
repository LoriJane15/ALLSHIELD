<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatMessageService
{
    public function __construct(private readonly ChatDocumentReferenceService $references) {}

    public function send(
        ChatConversation $conversation,
        User $actor,
        string $body,
        ?string $documentReference = null,
    ): ChatMessage {
        return DB::transaction(function () use ($conversation, $actor, $body, $documentReference): ChatMessage {
            $lockedConversation = ChatConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->getKey());

            if (! $lockedConversation->hasParticipant($actor)) {
                abort(404);
            }

            $participants = User::query()
                ->whereKey([$lockedConversation->user_one_id, $lockedConversation->user_two_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ([$lockedConversation->user_one_id, $lockedConversation->user_two_id] as $participantId) {
                $participant = $participants->get($participantId);
                if (! $participant?->is_active || ! array_key_exists($participant->role, config('shield.roles', []))) {
                    throw ValidationException::withMessages([
                        'body' => 'Messaging is unavailable while a participant account is inactive.',
                    ]);
                }
            }

            $referenceAttributes = $this->references->resolveForSend(
                $lockedConversation,
                $actor,
                $documentReference,
                $participants,
            );

            $message = $lockedConversation->messages()->create([
                'sender_id' => $actor->getKey(),
                'body' => $body,
            ]);

            if ($referenceAttributes !== null) {
                $message->documentReference()->create($referenceAttributes);
            }

            $lockedConversation->touch();

            return $message->load(['sender:id,name', ...ChatDocumentReferenceService::EAGER_LOADS]);
        }, 3);
    }
}
