<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ChatMessageDocumentReference extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ib39_cdr_document_version_id',
        'japic_certification_document_version_id',
        'pswdo_enrollment_document_id',
        'ib39_fea_document_version_id',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Chat document references are immutable.'));
        static::deleting(fn () => throw new LogicException('Chat document references are immutable.'));
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function cdrDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrDocumentVersion::class, 'ib39_cdr_document_version_id');
    }

    public function japicDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(JapicCertificationDocumentVersion::class, 'japic_certification_document_version_id');
    }

    public function pswdoEnrollmentDocument(): BelongsTo
    {
        return $this->belongsTo(PswdoEnrollmentDocument::class, 'pswdo_enrollment_document_id');
    }

    public function feaDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaDocumentVersion::class, 'ib39_fea_document_version_id');
    }
}
