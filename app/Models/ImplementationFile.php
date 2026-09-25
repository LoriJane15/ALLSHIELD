<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImplementationFile extends Model
{
    protected $fillable = ['implementation_id', 'agency_implan_response_id', 'file_name', 'description', 'pdf'];

    public function implementation(): BelongsTo
    {
        return $this->belongsTo(Implementation::class);
    }

    public function agencyResponse(): BelongsTo
    {
        return $this->belongsTo(AgencyImplanResponse::class, 'agency_implan_response_id');
    }
}
