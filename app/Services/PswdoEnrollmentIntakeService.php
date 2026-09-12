<?php

namespace App\Services;

use App\Models\Ib39SurfacedFormerRebel;
use App\Models\PswdoEnrollment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

class PswdoEnrollmentIntakeService
{
    public function __construct(private readonly PswdoEligibilityService $eligibility) {}

    public function receiveEligible(int|Ib39SurfacedFormerRebel $record): ?PswdoEnrollment
    {
        if (! Schema::hasTable('pswdo_enrollments')) {
            return null;
        }
        $id = $record instanceof Ib39SurfacedFormerRebel ? $record->getKey() : $record;
        $locked = Ib39SurfacedFormerRebel::query()->lockForUpdate()->find($id);
        if (! $locked || ! $this->eligibility->isEligible($locked)) {
            return null;
        }

        $existing = PswdoEnrollment::query()->where('ib39_surfaced_former_rebel_id', $locked->id)->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        try {
            return PswdoEnrollment::query()->forceCreate([
                'ib39_surfaced_former_rebel_id' => $locked->id,
                'lock_version' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            return PswdoEnrollment::query()->where('ib39_surfaced_former_rebel_id', $locked->id)->firstOrFail();
        }
    }
}
