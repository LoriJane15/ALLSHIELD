<?php

namespace App\Models;

use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Ib39SurfacedFormerRebel extends Model
{
    use SoftDeletes;

    public const DEFAULT_PROVINCE = 'Davao del Sur';

    public const OVERALL_CASE_STATUS = 'Newly Recorded';

    public const OVERALL_CASE_STATUS_CDR_ONGOING = 'CDR Ongoing';

    public const OVERALL_CASE_STATUS_CDR_COMPLETED = 'CDR Completed';

    public const OVERALL_CASE_STATUS_JAPIC_CERTIFIED = 'JAPIC Certified';

    public const OVERALL_CASE_STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'first_name',
        'last_name',
        'category',
        'other_category_specification',
        'province',
        'municipality_id',
        'barangay_id',
        'specific_location',
        'surfaced_at',
        'possessed_firearms',
        'initial_remarks',
    ];

    protected function casts(): array
    {
        return [
            'category' => Ib39FrCategory::class,
            'surfaced_at' => 'date',
            'possessed_firearms' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(
                trim((string) $this->first_name).' '.trim((string) $this->last_name)
            ),
        );
    }

    protected function overallCaseStatus(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $this->assertOverallStatusRelationsLoaded();

                return $this->cancellation
                    ? self::OVERALL_CASE_STATUS_CANCELLED
                    : match (true) {
                        $this->hasCompletedCdrWithCurrentFinalDocument() && $this->hasCompletedJapicCertificationWithCurrentFinalDocument() => self::OVERALL_CASE_STATUS_JAPIC_CERTIFIED,
                        $this->loadedCdrStatus() === Ib39CdrStatus::Ongoing => self::OVERALL_CASE_STATUS_CDR_ONGOING,
                        $this->loadedCdrStatus() === Ib39CdrStatus::Completed => self::OVERALL_CASE_STATUS_CDR_COMPLETED,
                        default => self::OVERALL_CASE_STATUS,
                    };
            },
        );
    }

    public function hasCompletedCdrWithCurrentFinalDocument(): bool
    {
        $this->assertRelationLoaded('cdrProcessing');
        if (! $this->cdrProcessing) {
            return false;
        }
        $this->assertNestedRelationLoaded($this->cdrProcessing, 'currentFinalVersion');

        return $this->cdrProcessing->status === Ib39CdrStatus::Completed
            && $this->cdrProcessing->currentFinalVersion?->cdr_processing_id === $this->cdrProcessing->id;
    }

    public function hasCompletedJapicCertificationWithCurrentFinalDocument(): bool
    {
        $this->assertRelationLoaded('japicCertificationProcessing');
        if (! $this->japicCertificationProcessing) {
            return false;
        }
        $this->assertNestedRelationLoaded($this->japicCertificationProcessing, 'currentFinalVersion');

        return $this->japicCertificationProcessing->status === JapicCertificationStatus::Completed
            && $this->japicCertificationProcessing->currentFinalVersion?->processing_id === $this->japicCertificationProcessing->id;
    }

    protected function cdrStatus(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->loadedCdrStatus()?->value ?? 'Not Available',
        );
    }

    private function loadedCdrStatus(): ?Ib39CdrStatus
    {
        $this->assertRelationLoaded('cdrProcessing');

        return $this->cdrProcessing?->status;
    }

    private function assertOverallStatusRelationsLoaded(): void
    {
        foreach (['cancellation', 'cdrProcessing', 'japicCertificationProcessing'] as $relation) {
            $this->assertRelationLoaded($relation);
        }

        if ($this->cdrProcessing) {
            $this->assertNestedRelationLoaded($this->cdrProcessing, 'currentFinalVersion');
        }
        if ($this->japicCertificationProcessing) {
            $this->assertNestedRelationLoaded($this->japicCertificationProcessing, 'currentFinalVersion');
        }
    }

    private function assertRelationLoaded(string $relation): void
    {
        if (! $this->relationLoaded($relation)) {
            throw new LogicException("The {$relation} relation must be eager loaded before reading derived workflow status.");
        }
    }

    private function assertNestedRelationLoaded(Model $model, string $relation): void
    {
        if (! $model->relationLoaded($relation)) {
            throw new LogicException("The {$relation} relation must be eager loaded before reading derived workflow status.");
        }
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cdrProcessing(): HasOne
    {
        return $this->hasOne(Ib39CdrProcessing::class, 'ib39_surfaced_former_rebel_id');
    }

    public function feaProcessing(): HasOne
    {
        return $this->hasOne(Ib39FeaProcessing::class, 'ib39_surfaced_former_rebel_id');
    }

    public function cancellation(): HasOne
    {
        return $this->hasOne(Ib39FrCancellation::class, 'ib39_surfaced_former_rebel_id');
    }

    public function japicCertificationProcessing(): HasOne
    {
        return $this->hasOne(JapicCertificationProcessing::class, 'ib39_surfaced_former_rebel_id');
    }

    public function pswdoEnrollment(): HasOne
    {
        return $this->hasOne(PswdoEnrollment::class, 'ib39_surfaced_former_rebel_id');
    }
}
