<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PswdoEnrollmentComment extends Model
{
    protected $fillable = ['user_id', 'author_role', 'text'];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PswdoEnrollment::class, 'pswdo_enrollment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
