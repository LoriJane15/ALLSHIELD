<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Ib39FeaDraftHistory extends Model
{
    protected $fillable = ['fea_processing_id', 'user_id', 'revision', 'changed_fields'];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'changed_fields' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('FEA draft history entries are immutable.'));
        static::deleting(fn () => throw new LogicException('FEA draft history entries are immutable.'));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaDocument::class, 'fea_document_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
