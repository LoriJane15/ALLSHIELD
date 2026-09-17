<?php

namespace App\Enums;

enum Ib39FeaUploadSlot: string
{
    case Primary = 'primary';
    case FinalPrimary = 'final_primary';
    case JustificationSurrendered = 'justification_surrendered';
    case JustificationComparison = 'justification_comparison';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Draft file',
            self::FinalPrimary => 'Final file',
            self::JustificationSurrendered => 'Justification Section 3 surrendered-firearm photograph',
            self::JustificationComparison => 'Justification Section 4 comparison photograph',
        };
    }
}
