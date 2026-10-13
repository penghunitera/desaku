<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VillagePotential extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'decimal:2'];
    }
}
