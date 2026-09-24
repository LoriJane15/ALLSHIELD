<?php

namespace App\Enums;

enum Ib39ForwardingOrganization: string
{
    case Lgu = 'LGU';
    case Afp = 'AFP';
    case Pnp = 'PNP';
    case Other = 'Other';
}
