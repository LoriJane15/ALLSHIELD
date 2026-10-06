<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Ib39FeaReadiness;
use App\Models\Ib39SurfacedFormerRebel;
use Illuminate\Support\Facades\Schema;

class LockedIb39FeaReadiness implements Ib39FeaReadiness
{
    private const DENIAL_MESSAGE = 'FEA processing is unavailable until the required PSWDO enrollment forms are completed.';

    private const WORKSPACE_READINESS_CACHE = 'fea_workspace_readiness';

    public function __construct(private readonly PswdoEligibilityService $eligibility) {}

    public function isReady(Ib39SurfacedFormerRebel $record): bool
    {
        $request = app()->bound('request') ? request() : null;
        $isWorkspaceView = $request?->isMethod('GET') && $request->routeIs('ib39.fea.show');
        $cache = $isWorkspaceView ? $request->attributes->get(self::WORKSPACE_READINESS_CACHE, []) : [];
        $recordId = $record->getKey();

        if ($isWorkspaceView && array_key_exists($recordId, $cache)) {
            return $cache[$recordId];
        }

        $ready = Schema::hasTable('pswdo_enrollments')
            && $record->possessed_firearms
            && $this->eligibility->isEligible($record)
            && ($record->pswdoEnrollment()->first()?->isCompleted() ?? false);

        if ($isWorkspaceView) {
            $cache[$recordId] = $ready;
            $request->attributes->set(self::WORKSPACE_READINESS_CACHE, $cache);
        }

        return $ready;
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
