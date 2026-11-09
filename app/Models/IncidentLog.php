<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de trazabilidad de una incidencia.
 *
 * Es un registro de auditoría: solo se insertan filas, nunca se editan.
 * La única excepción es el diagnóstico, que puede borrar copias exactas duplicadas.
 */
class IncidentLog extends Model
{
    // Inmutable: sin updated_at (la columna no existe en la tabla).
    public const UPDATED_AT = null;

    protected $fillable = ['incident_id', 'action', 'old_value', 'new_value', 'user_name', 'metadata'];

    /** metadata es JSON en BD y se trabaja como array en PHP (p. ej. ['reason' => '...']). */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
