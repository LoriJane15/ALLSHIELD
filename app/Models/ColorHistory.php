<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColorHistory extends Model
{
    protected $fillable = ['map_barangay_id', 'status', 'color', 'frs'];

    public function mapBarangay(): BelongsTo
    {
        return $this->belongsTo(MapBarangay::class);
    }
}
