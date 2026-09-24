<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Ib39SurfacedFormerRebel;

interface Ib39FeaReadiness
{
    public function isReady(Ib39SurfacedFormerRebel $record): bool;

    public function assertReady(Ib39SurfacedFormerRebel $record): void;

    public function denialMessage(): string;
}
