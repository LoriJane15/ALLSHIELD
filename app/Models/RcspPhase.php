<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RcspPhase extends Model
{
    protected $fillable = ['name', 'number'];

    public function activities(): HasMany
    {
        return $this->hasMany(RcspActivity::class);
    }
}
