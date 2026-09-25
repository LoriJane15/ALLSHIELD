<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImplementationPhoto extends Model
{
    protected $fillable = ['implementation_id', 'agency_implan_response_id', 'image'];

    public function implementation(): BelongsTo
    {
        return $this->belongsTo(Implementation::class);
    }

    public function agencyResponse(): BelongsTo
    {
        return $this->belongsTo(AgencyImplanResponse::class, 'agency_implan_response_id');
    }
}
