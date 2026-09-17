<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RcspFormReview extends Model
{
    protected $fillable = ['rcsp_form_id', 'reviewer_user_id', 'status', 'remarks', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(RcspForm::class, 'rcsp_form_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
