<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    protected $fillable = ['municipality_id', 'name'];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function ib39SurfacedFormerRebels(): HasMany
    {
        return $this->hasMany(Ib39SurfacedFormerRebel::class);
    }
}
