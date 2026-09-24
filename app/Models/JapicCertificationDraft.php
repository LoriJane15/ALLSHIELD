<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JapicCertificationDraft extends Model
{
    protected $guarded = ['id', 'processing_id'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'schema_version' => 'integer', 'revision' => 'integer', 'last_saved_at' => 'datetime'];
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(JapicCertificationProcessing::class, 'processing_id');
    }

    public function lastSavedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_saved_by');
    }
}
