<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentLog extends Model
{
    // Los logs son inmutables: no tienen updated_at.
    public const UPDATED_AT = null;

    protected $fillable = ['incident_id', 'action', 'old_value', 'new_value', 'user_name', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
