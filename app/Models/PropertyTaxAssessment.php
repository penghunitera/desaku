<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyTaxAssessment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['assessed_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'due_date' => 'date', 'verified_at' => 'datetime'];
    }

    public function taxObject(): BelongsTo
    {
        return $this->belongsTo(TaxObject::class);
    }
}
