<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RcspActivity extends Model
{
    protected $fillable = [
        'rcsp_phase_id', 'rcsp_barangay_id', 'created_by_user_id', 'description', 'normalized_title',
    ];

    public static function normalizeTitle(string $title): string
    {
        $normalized = trim($title);
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($normalized, \Normalizer::FORM_KC) ?: $normalized;
        }

        return Str::lower(Str::squish($normalized));
    }

    public function scopeForBarangayPhase(
        Builder $query,
        RcspBarangay $barangay,
        RcspPhase $phase
    ): Builder {
        $query->where('rcsp_phase_id', $phase->id);

        if ($barangay->catalog_key !== RcspPhase::CONFIGURABLE_CATALOG_KEY) {
            return $query;
        }

        return $query
            ->where(function (Builder $scope) use ($barangay, $phase): void {
                $scope->where('rcsp_barangay_id', $barangay->id)
                    ->orWhere(function (Builder $legacy) use ($barangay, $phase): void {
                        $legacy->whereNull('rcsp_barangay_id')
                            ->whereHas('forms', fn (Builder $forms) => $forms
                                ->where('rcsp_barangay_id', $barangay->id)
                                ->where('rcsp_phase_id', $phase->id));
                    });
            });
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(RcspPhase::class, 'rcsp_phase_id');
    }

    public function rcspBarangay(): BelongsTo
    {
        return $this->belongsTo(RcspBarangay::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(RcspForm::class, 'rcsp_activity_id');
    }
}
