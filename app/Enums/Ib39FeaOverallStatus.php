<?php

namespace App\Enums;

enum Ib39FeaOverallStatus: string
{
    case Cancelled = 'Cancelled';
    case NotApplicable = 'Not Applicable';
    case ForCompliance = 'For Compliance';
    case Completed = 'Completed';
    case Processing = 'Processing';
    case Pending = 'Pending';
    case ReadyForProcessing = 'Ready for Processing';
}
