<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['form_data' => 'array', 'consented_at' => 'datetime', 'submitted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ServiceRequestDocument::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ServiceRequestHistory::class)->oldest();
    }
}
