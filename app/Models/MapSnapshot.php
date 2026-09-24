<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapSnapshot extends Model
{
    protected $fillable = ['label', 'kind', 'feature_count', 'data'];

    protected $casts = ['data' => 'array', 'feature_count' => 'integer'];
}
