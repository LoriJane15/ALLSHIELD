<?php

namespace App\Enums;

enum Ib39FeaComplianceStatus: string
{
    case None = 'None';
    case ReturnedForCompliance = 'Returned for Compliance';
    case HasIssue = 'Has Issue';
}
