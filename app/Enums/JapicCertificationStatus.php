<?php

namespace App\Enums;

enum JapicCertificationStatus: string
{
    case Pending = 'Pending';
    case Drafting = 'Drafting';
    case ForSigning = 'For Signing';
    case AwaitingFinalUpload = 'Awaiting Final Upload';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';

    public function isActive(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
