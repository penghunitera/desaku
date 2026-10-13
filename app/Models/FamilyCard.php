<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyCard extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $hidden = ['number_encrypted', 'number_hash'];

    protected function casts(): array
    {
        return ['issued_at' => 'date'];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }
}
