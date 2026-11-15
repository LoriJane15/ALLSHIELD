<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Implementation extends Model
{
    protected $fillable = [
        'implementation_plan_id', 'lgu_user_id', 'uploaded_at', 'issues', 'program', 'target_areas', 'agencies',
        'beneficiaries', 'outcome', 'resources', 'support', 'duration', 'status',
        'type_gov', 'sources', 'remarks', 'tagging',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'date',
            'target_areas' => 'array',
            'agencies' => 'array',
        ];
    }

    public function scopeForMunicipality(Builder $query, int $municipalityId): Builder
    {
        return $query->whereHas(
            'lguUser',
            fn (Builder $userQuery) => $userQuery->where('municipality_id', $municipalityId)
        );
    }

    public function scopeAssignedToAgency(Builder $query, int $agencyId): Builder
    {
        return $query->whereJsonContains('agencies', $agencyId);
    }

    public function scopeVisibleToAgency(Builder $query, int $agencyId): Builder
    {
        return $query->assignedToAgency($agencyId)
            ->where('status', '!=', 'not yet started');
    }

    public function isAssignedToAgency(int $agencyId): bool
    {
        return collect($this->agencies ?? [])
            ->map(fn ($assignedAgencyId) => (int) $assignedAgencyId)
            ->contains($agencyId);
    }

    public function lguUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lgu_user_id');
    }

    public function implementationPlan(): BelongsTo
    {
        return $this->belongsTo(ImplementationPlan::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ImplementationPhoto::class);
    }

    public function originalPhotos(): HasMany
    {
        return $this->hasMany(ImplementationPhoto::class)->whereNull('agency_implan_response_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AgencyImplanResponse::class);
    }

    public function taggings(): HasMany
    {
        return $this->hasMany(ImplementationTagging::class);
    }
}
