<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class ChatMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['sender_id', 'body'];

    protected function casts(): array
    {
        return [
            'chat_conversation_id' => 'integer',
            'sender_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Chat messages are immutable.'));
        static::deleting(fn () => throw new LogicException('Chat messages are immutable.'));
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function documentReference(): HasOne
    {
        return $this->hasOne(ChatMessageDocumentReference::class);
    }
}
