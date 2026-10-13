<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['nik_encrypted', 'nik_hash', 'family_card_hash', 'citizen_pin_hash'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'citizen_last_login_at' => 'datetime'];
    }

    public function familyCard(): BelongsTo
    {
        return $this->belongsTo(FamilyCard::class);
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function taxObjects(): HasMany
    {
        return $this->hasMany(TaxObject::class);
    }

    public function propertyTaxSnapshot(): HasOne
    {
        return $this->hasOne(PropertyTaxSnapshot::class);
    }
}
