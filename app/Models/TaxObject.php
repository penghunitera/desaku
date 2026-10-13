<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxObject extends Model
{
    protected $guarded = [];

    protected $hidden = ['nop_encrypted', 'nop_hash'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(PropertyTaxAssessment::class);
    }
}
