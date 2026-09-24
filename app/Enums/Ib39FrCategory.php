<?php

namespace App\Enums;

enum Ib39FrCategory: string
{
    case MilisyaNgBayan = 'Milisya ng Bayan';
    case RegularMember = 'Regular Member';
    case UndergroundMassOrganization = 'UGMOs/Underground Mass Organization';
    case Other = 'Other';
}
