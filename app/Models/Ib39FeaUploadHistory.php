<?php

namespace App\Models;

use App\Enums\Ib39FeaUploadSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ib39FeaUploadHistory extends Model
{
    protected $fillable = [
        'fea_processing_id', 'fea_document_version_id', 'user_id', 'event', 'slot', 'version_number',
    ];

    protected function casts(): array
    {
        return ['slot' => Ib39FeaUploadSlot::class, 'version_number' => 'integer'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
