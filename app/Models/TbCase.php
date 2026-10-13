<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TbCase extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'screened_at' => 'date',
            'treatment_started_at' => 'date',
            'treatment_due_at' => 'date',
            'next_visit_at' => 'date',
            'clinical_data_encrypted' => 'encrypted:array',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(TbContact::class);
    }

    public function treatmentLogs(): HasMany
    {
        return $this->hasMany(TbTreatmentLog::class);
    }
}
