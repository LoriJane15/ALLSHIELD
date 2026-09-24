<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ib39CdrForm extends Model
{
    protected $fillable = [
        'schema_version',
        'content',
        'last_edited_by',
    ];

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'content' => 'encrypted:array',
        ];
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrProcessing::class, 'cdr_processing_id');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }
}
