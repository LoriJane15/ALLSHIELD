<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfestationRule extends Model
{
    protected $fillable = ['min_frs', 'status', 'color', 'label', 'sort_order'];

    protected $casts = ['min_frs' => 'integer', 'sort_order' => 'integer'];
}
