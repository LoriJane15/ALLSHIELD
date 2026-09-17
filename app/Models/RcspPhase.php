<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RcspPhase extends Model
{
    public const CONFIGURABLE_CATALOG_KEY = 'lgu-configurable-v1';

    public const STRUCTURAL_NAMES = [
        0 => 'Pre-Shaping',
        1 => 'Shape',
        2 => 'Access',
        3 => 'Transform',
        4 => 'Sustain',
        5 => 'Monitor',
    ];

    protected $fillable = ['name', 'number', 'catalog_key'];

    public function getDisplayNameAttribute(): string
    {
        return self::STRUCTURAL_NAMES[(int) $this->number] ?? $this->name;
    }

    public function activities(): HasMany
    {
        return $this->hasMany(RcspActivity::class);
    }
}
