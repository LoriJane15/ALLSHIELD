<?php

namespace App\Models;

use App\Enums\Ib39CdrPhotoType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ib39CdrPhoto extends Model
{
    protected $fillable = [
        'photo_type',
        'current_photo_version_id',
    ];

    protected function casts(): array
    {
        return [
            'photo_type' => Ib39CdrPhotoType::class,
        ];
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrProcessing::class, 'cdr_processing_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Ib39CdrPhotoVersion::class, 'cdr_photo_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrPhotoVersion::class, 'current_photo_version_id');
    }
}
