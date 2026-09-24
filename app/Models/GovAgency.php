<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovAgency extends Model
{
    protected $fillable = ['name', 'acronym', 'profile'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AgencyImplanResponse::class);
    }
}
