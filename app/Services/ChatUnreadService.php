<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatConversationRead;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatUnreadService
{
    public function summary(User $user): array
    {
        if (! $user->is_active) {
            return $this->format([]);
        }

        $counts = ChatConversation::query()
            ->from('chat_conversations as conversations')
            ->leftJoin('chat_conversation_reads as reads', function ($join) use ($user): void {
                $join->on('reads.chat_conversation_id', '=', 'conversations.id')
                    ->where('reads.user_id', '=', $user->getKey());
            })
            ->leftJoin('chat_messages as unread_messages', function ($join) use ($user): void {
                $join->on('unread_messages.chat_conversation_id', '=', 'conversations.id')
                    ->where('unread_messages.sender_id', '<>', $user->getKey())
                    ->whereRaw('unread_messages.id > COALESCE(reads.last_read_message_id, 0)');
            })
            ->where(function ($query) use ($user): void {
                $query->where('conversations.user_one_id', $user->getKey())
                    ->orWhere('conversations.user_two_id', $user->getKey());
            })
            ->groupBy('conversations.id')
            ->selectRaw('conversations.id, COUNT(unread_messages.id) AS unread_count')
            ->pluck('unread_count', 'conversations.id')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return $this->format($counts);
    }

    public function markRead(ChatConversation $conversation, User $user, int $throughId): array
    {
        DB::transaction(function () use ($conversation, $user, $throughId): void {
            $lockedConversation = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->getKey());
            if (! $user->is_active || ! $lockedConversation->hasParticipant($user)) {
                abort(404);
            }

            $message = ChatMessage::query()
                ->where('chat_conversation_id', $lockedConversation->getKey())
                ->whereKey($throughId)
                ->first();

            if (! $message) {
                throw ValidationException::withMessages([
                    'through_id' => 'The read cursor does not belong to this conversation.',
                ]);
            }

            $read = ChatConversationRead::query()
                ->where('chat_conversation_id', $lockedConversation->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $read) {
                $lockedConversation->readStates()->create([
                    'user_id' => $user->getKey(),
                    'last_read_message_id' => $throughId,
                ]);

                return;
            }

            if ($read->last_read_message_id === null || $throughId > $read->last_read_message_id) {
                $read->update(['last_read_message_id' => $throughId]);
            }
        }, 3);

        return $this->summary($user);
    }

    private function format(array $counts): array
    {
        $conversations = collect($counts)->mapWithKeys(fn (int $count, int|string $id): array => [
            (string) $id => [
                'count' => $count,
                'count_text' => $this->countText($count),
            ],
        ])->all();
        $total = array_sum($counts);

        return [
            'total' => $total,
            'total_text' => $this->countText($total),
            'conversations' => $conversations,
        ];
    }

    private function countText(int $count): string
    {
        return $count > 99 ? '99+' : (string) $count;
    }
}
