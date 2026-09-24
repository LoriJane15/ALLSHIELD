<?php

namespace App\Enums;

enum Ib39CdrDocumentSource: string
{
    case Generated = 'generated';
    case Uploaded = 'uploaded';
}
