<?php

namespace App\Models;

use App\Enums\Ib39FeaDocumentHistoryEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Ib39FeaDocumentHistory extends Model
{
    protected $fillable = [
        'fea_processing_id', 'user_id', 'event', 'previous_values', 'new_values',
    ];

    protected function casts(): array
    {
        return [
            'event' => Ib39FeaDocumentHistoryEvent::class,
            'previous_values' => 'encrypted:array',
            'new_values' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('FEA document history entries are immutable.'));
        static::deleting(fn () => throw new LogicException('FEA document history entries are immutable.'));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaDocument::class, 'fea_document_id');
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
