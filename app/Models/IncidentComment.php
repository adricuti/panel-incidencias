<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comentario operativo de seguimiento.
 *
 * Se crea siempre desde IncidentService::addComment(), que además deja un log
 * 'comment_added' para que el comentario aparezca en la trazabilidad.
 */
class IncidentComment extends Model
{
    protected $fillable = ['incident_id', 'author_name', 'body'];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
