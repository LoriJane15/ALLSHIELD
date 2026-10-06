<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'logo',
        'municipality_id',
        'gov_agency_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Role helpers --------------------------------------------------------
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Landing route for this user's role after login. */
    public function homeRoute(): string
    {
        return match ($this->role) {
            'super_admin' => 'super_admin.dashboard',
            'admin' => 'admin.dashboard',
            '39th_ib' => 'ib39.dashboard',
            'gov_agency' => 'gov_agency.dashboard',
            'lgu' => 'lgu.dashboard',
            'mblrc' => 'mblrc.dashboard',
            'afp' => 'afp.dashboard',
            'japic' => 'japic.dashboard',
            'pswdo' => 'pswdo.dashboard',
            default => 'login',
        };
    }

    /** Safe public URL for the profile image already configured for this account. */
    public function profileImageUrl(): ?string
    {
        if ($this->hasRole('gov_agency') && $this->govAgency?->profile) {
            $filename = ltrim($this->govAgency->profile, '/');

            return is_file(public_path('assets/logoAgency/'.$filename))
                ? asset('assets/logoAgency/'.$filename)
                : null;
        }

        if (! $this->logo) {
            return null;
        }

        if (Storage::disk('public')->exists($this->logo)) {
            return Storage::disk('public')->url($this->logo);
        }

        $filename = ltrim($this->logo, '/');

        return is_file(public_path('assets/'.$filename))
            ? asset('assets/'.$filename)
            : null;
    }

    // Relationships -------------------------------------------------------
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function govAgency(): BelongsTo
    {
        return $this->belongsTo(GovAgency::class);
    }

    public function createdIb39SurfacedFormerRebels(): HasMany
    {
        return $this->hasMany(Ib39SurfacedFormerRebel::class, 'created_by');
    }

    public function completedIb39CdrProcessings(): HasMany
    {
        return $this->hasMany(Ib39CdrProcessing::class, 'completed_by');
    }

    public function assignedJapicCertificationProcessings(): HasMany
    {
        return $this->hasMany(JapicCertificationProcessing::class, 'assigned_to');
    }

    public function uploadedPswdoEnrollmentDocuments(): HasMany
    {
        return $this->hasMany(PswdoEnrollmentDocument::class, 'uploaded_by');
    }
}
