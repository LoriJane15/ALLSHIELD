<?php

namespace App\Enums;

enum Ib39FeaDocumentStatus: string
{
    case Pending = 'Pending';
    case Processing = 'Processing';
    case Completed = 'Completed';
}
