<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatConversationRead extends Model
{
    protected $fillable = ['user_id', 'last_read_message_id'];

    protected function casts(): array
    {
        return [
            'chat_conversation_id' => 'integer',
            'user_id' => 'integer',
            'last_read_message_id' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_read_message_id');
    }
}
