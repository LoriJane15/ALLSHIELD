<?php

namespace App\Models;

use App\Enums\PswdoEnrollmentDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PswdoEnrollmentDocument extends Model
{
    protected $guarded = ['id', 'pswdo_enrollment_id'];

    protected $hidden = ['storage_path', 'sha256'];

    protected function casts(): array
    {
        return [
            'document_type' => PswdoEnrollmentDocumentType::class,
            'size_bytes' => 'integer',
            'correct_document_type_confirmed' => 'boolean',
            'belongs_to_fr_confirmed' => 'boolean',
            'final_signed_confirmed' => 'boolean',
            'uploaded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('PSWDO final documents are immutable.'));
        static::deleting(fn () => throw new LogicException('PSWDO final documents are immutable.'));
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PswdoEnrollment::class, 'pswdo_enrollment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
