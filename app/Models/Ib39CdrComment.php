<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ib39CdrComment extends Model
{
    protected $fillable = [
        'user_id',
        'author_role',
        'text',
    ];

    public function processing(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrProcessing::class, 'cdr_processing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
