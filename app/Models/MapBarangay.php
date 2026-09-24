<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapBarangay extends Model
{
    protected $fillable = [
        'fid', 'province', 'municipality', 'barangay',
        'geometry', 'frs', 'status', 'infestation_color', 'rebels',
    ];

    protected $casts = [
        // The GeoJSON geometry object (a MultiPolygon) for this barangay.
        'geometry' => 'array',
    ];

    public function colorHistories(): HasMany
    {
        return $this->hasMany(ColorHistory::class);
    }

    /**
     * Legend for the choropleth, in the same order the legacy map showed it.
     * Keep in sync with classify().
     */
    public const LEGEND = [
        ['status' => 'Konsolidado', 'color' => 'rgba(255,0,0,0.5)',   'label' => '20 or more FRs'],
        ['status' => 'Rekonsilida', 'color' => 'rgba(255,165,0,0.5)', 'label' => '15–19 FRs'],
        ['status' => 'Expansion',   'color' => 'rgba(255,255,0,0.5)', 'label' => '10–14 FRs'],
        ['status' => 'Recovery',    'color' => 'rgba(0,255,0,0.5)',   'label' => 'Fewer than 10 FRs'],
    ];

    /**
     * RCSP infestation classification by former-rebel count, driven by the
     * admin-editable infestation_rules table (highest matching threshold wins).
     * Falls back to the legacy LEGEND if the table is empty or unavailable.
     */
    public static function classify(int $frs): array
    {
        foreach (self::rules() as $rule) {
            if ($frs >= $rule['min_frs']) {
                return ['status' => $rule['status'], 'color' => $rule['color']];
            }
        }

        // No rule matched (every threshold is above this count) — lowest band.
        $bands = self::legend();
        $last = end($bands) ?: ['status' => 'Recovery', 'color' => 'rgba(0,255,0,0.5)'];

        return ['status' => $last['status'], 'color' => $last['color']];
    }

    /** The classification bands for the legend, ordered highest threshold first. */
    public static function legend(): array
    {
        $rules = self::rules();

        return $rules ?: self::LEGEND;
    }

    /**
     * Cached rules, highest min_frs first. Cached per-request so a hot path like
     * a bulk re-classify does not hit the table for every row.
     */
    private static ?array $ruleCache = null;

    private static function rules(): array
    {
        if (self::$ruleCache !== null) {
            return self::$ruleCache;
        }

        try {
            self::$ruleCache = InfestationRule::orderByDesc('min_frs')->orderBy('sort_order')
                ->get(['min_frs', 'status', 'color', 'label'])
                ->map(fn ($r) => $r->toArray())
                ->all();
        } catch (\Throwable) {
            self::$ruleCache = [];
        }

        return self::$ruleCache;
    }

    /** Drop the per-request rule cache after the rules are edited. */
    public static function forgetRuleCache(): void
    {
        self::$ruleCache = null;
    }
}
