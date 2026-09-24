<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MapBarangay extends Model
{
    public const DEFAULT_PROVINCE = 'Davao del Sur';

    public const NEUTRAL_COLOR = 'rgba(190,178,151,0.1)';

    /**
     * The single authoritative RCSP classification definition.
     * Keep the bands ordered from highest to lowest minimum count.
     */
    public const CLASSIFICATIONS = [
        ['minimum' => 20, 'status' => 'Konsolidado', 'color' => 'rgba(255,0,0,0.5)', 'label' => '20 or more FRs'],
        ['minimum' => 15, 'status' => 'Rekonsilido', 'color' => 'rgba(255,165,0,0.5)', 'label' => '15–19 FRs'],
        ['minimum' => 10, 'status' => 'Expansion', 'color' => 'rgba(255,255,0,0.5)', 'label' => '10–14 FRs'],
        ['minimum' => 0, 'status' => 'Recovery', 'color' => 'rgba(0,255,0,0.5)', 'label' => '0–9 FRs'],
    ];

    protected $fillable = [
        'fid', 'barangay_id', 'province', 'municipality', 'barangay',
        'geometry', 'frs', 'status', 'infestation_color', 'rebels',
    ];

    protected $casts = [
        'geometry' => 'array',
    ];

    private static ?array $ruleCache = null;

    public function barangayRecord(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'barangay_id');
    }

    public function colorHistories(): HasMany
    {
        return $this->hasMany(ColorHistory::class);
    }

    public function latestColorHistory(): HasOne
    {
        return $this->hasOne(ColorHistory::class)
            ->ofMany(['effective_date' => 'max', 'id' => 'max']);
    }

    /**
     * RCSP infestation classification by former-rebel count.
     * Thresholds and colors preserved from the legacy 39th-IB module.
     */
    public static function classify(int $frs): array
    {
        if ($frs < 0) {
            throw new \InvalidArgumentException('Former-rebel count cannot be negative.');
        }

        foreach (self::rules() as $classification) {
            if ($frs >= $classification['minimum']) {
                return [
                    'status' => $classification['status'],
                    'color' => $classification['color'],
                ];
            }
        }

        return ['status' => 'Recovery', 'color' => 'rgba(0,255,0,0.5)'];
    }

    public static function legend(): array
    {
        return self::rules();
    }

    public static function forgetRuleCache(): void
    {
        self::$ruleCache = null;
    }

    private static function rules(): array
    {
        if (self::$ruleCache !== null) {
            return self::$ruleCache;
        }

        try {
            $rules = InfestationRule::query()
                ->orderByDesc('min_frs')
                ->orderBy('sort_order')
                ->get(['min_frs', 'status', 'color', 'label'])
                ->map(fn (InfestationRule $rule) => [
                    'minimum' => $rule->min_frs,
                    'status' => $rule->status,
                    'color' => $rule->color,
                    'label' => $rule->label,
                ])
                ->all();

            return self::$ruleCache = $rules ?: self::CLASSIFICATIONS;
        } catch (\Throwable) {
            return self::$ruleCache = self::CLASSIFICATIONS;
        }
    }
}
