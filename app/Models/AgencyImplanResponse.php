<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgencyImplanResponse extends Model
{
    protected $fillable = [
        'gov_agency_id', 'implementation_id', 'response_status', 'rejection_reason',
        'program', 'beneficiaries', 'outcome', 'resources', 'support', 'duration',
        'type_gov', 'sources', 'action_taken', 'remarks',
    ];

    public function govAgency(): BelongsTo
    {
        return $this->belongsTo(GovAgency::class);
    }

    public function implementation(): BelongsTo
    {
        return $this->belongsTo(Implementation::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ImplementationFile::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ImplementationPhoto::class);
    }
}
