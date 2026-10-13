<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TbContact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['priority' => 'boolean', 'screened_at' => 'date', 'notes_encrypted' => 'encrypted'];
    }

    public function tbCase(): BelongsTo
    {
        return $this->belongsTo(TbCase::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
