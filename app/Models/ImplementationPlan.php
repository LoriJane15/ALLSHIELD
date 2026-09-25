<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImplementationPlan extends Model
{
    protected $fillable = ['lgu_user_id', 'title', 'status', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'date'];
    }

    public function lguUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lgu_user_id');
    }

    public function implementations(): HasMany
    {
        return $this->hasMany(Implementation::class);
    }
}
