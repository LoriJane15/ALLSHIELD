<?php

namespace App\Models;

use App\Enums\Ib39FeaOverallStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Ib39FeaProcessingHistory extends Model
{
    protected $fillable = ['user_id', 'from_status', 'to_status', 'event'];

    protected function casts(): array
    {
        return [
            'from_status' => Ib39FeaOverallStatus::class,
            'to_status' => Ib39FeaOverallStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('FEA processing history entries are immutable.'));
        static::deleting(fn () => throw new LogicException('FEA processing history entries are immutable.'));
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaProcessing::class, 'fea_processing_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
