<?php

namespace App\Enums;

enum Ib39FeaDocumentType: string
{
    case Tir = 'TIR';
    case Cvif = 'CVIF';
    case Ptis = 'PTIS';
    case Justification = 'JUSTIFICATION';
    case FirearmPhoto = 'FIREARM_PHOTO';
    case FrWithFirearmPhoto = 'FR_WITH_FIREARM_PHOTO';

    public function label(): string
    {
        return match ($this) {
            self::Tir => 'Technical Inspection Report',
            self::Cvif => 'Cost Valuation of Inventoried Firearms',
            self::Ptis => 'Property Turn-In Slip',
            self::Justification => 'Justification on the TIR and CVC/CVIF',
            self::FirearmPhoto => 'Photograph of the firearm',
            self::FrWithFirearmPhoto => 'Photograph of the FR with the firearm',
        };
    }

    public function hasDraftEditor(): bool
    {
        return in_array($this, [self::Tir, self::Cvif, self::Ptis, self::Justification], true);
    }
}
