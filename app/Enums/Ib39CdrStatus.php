<?php

namespace App\Enums;

enum Ib39CdrStatus: string
{
    case Pending = 'Pending';
    case Ongoing = 'Ongoing';
    case Completed = 'Completed';
}
