<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ib39ReferenceSequence extends Model
{
    public const SURFACED_FORMER_REBELS = 'surfaced_former_rebels';

    protected $primaryKey = 'sequence_name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
        ];
    }
}
