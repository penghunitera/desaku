<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TbTreatmentLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['recorded_at' => 'date', 'notes_encrypted' => 'encrypted'];
    }

    public function tbCase(): BelongsTo
    {
        return $this->belongsTo(TbCase::class);
    }
}
