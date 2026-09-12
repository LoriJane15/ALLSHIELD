<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Ib39FeaReadiness;
use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\Ib39SurfacedFormerRebel;
use Illuminate\Support\Facades\Schema;

class LockedIb39FeaReadiness implements Ib39FeaReadiness
{
    private const DENIAL_MESSAGE = 'FEA processing is unavailable until the required PSWDO enrollment forms are completed.';

    public function __construct(private readonly PswdoEligibilityService $eligibility) {}

    public function isReady(Ib39SurfacedFormerRebel $record): bool
    {
        if (! Schema::hasTable('pswdo_enrollments')
            || ! $record->possessed_firearms || ! $this->eligibility->isEligible($record)) {
            return false;
        }

        return $record->pswdoEnrollment()->whereHas('documents', fn ($query) => $query
            ->selectRaw('1')->groupBy('pswdo_enrollment_id')
            ->havingRaw('COUNT(DISTINCT document_type) = ?', [count(PswdoEnrollmentDocumentType::cases())]))->exists();
    }

    public function assertReady(Ib39SurfacedFormerRebel $record): void
    {
        abort_unless($this->isReady($record), 403, $this->denialMessage());
    }

    public function denialMessage(): string
    {
        return self::DENIAL_MESSAGE;
    }
}
