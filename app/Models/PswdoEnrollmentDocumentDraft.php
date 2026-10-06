<?php

namespace App\Models;

use App\Enums\PswdoEnrollmentDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PswdoEnrollmentDocumentDraft extends Model
{
    protected $guarded = ['id', 'pswdo_enrollment_id'];

    protected function casts(): array
    {
        return [
            'document_type' => PswdoEnrollmentDocumentType::class,
            'payload' => 'encrypted:array',
            'schema_version' => 'integer',
            'revision' => 'integer',
            'saved_at' => 'immutable_datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PswdoEnrollment::class, 'pswdo_enrollment_id');
    }

    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }
}
