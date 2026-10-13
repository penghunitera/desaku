<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyTaxSnapshot extends Model
{
    protected $guarded = [];

    protected $hidden = ['nik_hash'];

    protected function casts(): array
    {
        return ['total_outstanding' => 'decimal:2', 'queued_at' => 'datetime', 'checked_at' => 'datetime', 'attempted_at' => 'datetime'];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
