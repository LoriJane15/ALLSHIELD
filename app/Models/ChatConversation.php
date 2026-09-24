<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = ['user_one_id', 'user_two_id'];

    protected function casts(): array
    {
        return [
            'user_one_id' => 'integer',
            'user_two_id' => 'integer',
        ];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function readStates(): HasMany
    {
        return $this->hasMany(ChatConversationRead::class);
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $id = $user instanceof User ? $user->getKey() : $user;

        return $query->where(fn (Builder $query): Builder => $query
            ->where('user_one_id', $id)
            ->orWhere('user_two_id', $id));
    }

    public function hasParticipant(User|int $user): bool
    {
        $id = $user instanceof User ? $user->getKey() : $user;

        return $this->user_one_id === $id || $this->user_two_id === $id;
    }
}
