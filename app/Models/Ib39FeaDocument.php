<?php

namespace App\Models;

use App\Enums\Ib39FeaComplianceStatus;
use App\Enums\Ib39FeaDocumentStatus;
use App\Enums\Ib39FeaDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ib39FeaDocument extends Model
{
    protected $fillable = [
        'document_type', 'status', 'compliance_status', 'is_required',
        'started_at', 'completed_at', 'remarks', 'compliance_reason',
        'is_delayed', 'delay_reason',
        'prepared_by', 'last_updated_by',
        'draft_data', 'draft_schema_version', 'draft_revision', 'draft_saved_at', 'draft_saved_by',
        'current_draft_version_id', 'current_surrendered_photo_version_id', 'current_supporting_photo_version_id',
        'current_final_version_id',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => Ib39FeaDocumentType::class,
            'status' => Ib39FeaDocumentStatus::class,
            'compliance_status' => Ib39FeaComplianceStatus::class,
            'is_required' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'remarks' => 'encrypted',
            'compliance_reason' => 'encrypted',
            'is_delayed' => 'boolean',
            'delay_reason' => 'encrypted',
            'draft_data' => 'encrypted:array',
            'draft_schema_version' => 'integer',
            'draft_revision' => 'integer',
            'draft_saved_at' => 'datetime',
        ];
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaProcessing::class, 'fea_processing_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function lastUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(Ib39FeaDocumentHistory::class, 'fea_document_id');
    }

    public function draftSaver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'draft_saved_by');
    }

    public function draftHistories(): HasMany
    {
        return $this->hasMany(Ib39FeaDraftHistory::class, 'fea_document_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Ib39FeaDocumentVersion::class, 'fea_document_id');
    }

    public function uploadHistories(): HasMany
    {
        return $this->hasMany(Ib39FeaUploadHistory::class, 'fea_document_id');
    }

    public function currentDraftVersion(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaDocumentVersion::class, 'current_draft_version_id');
    }

    public function currentFinalVersion(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaDocumentVersion::class, 'current_final_version_id');
    }

    public function currentSupportingPhotoVersion(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaDocumentVersion::class, 'current_supporting_photo_version_id');
    }

    public function currentSurrenderedPhotoVersion(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaDocumentVersion::class, 'current_surrendered_photo_version_id');
    }
}
