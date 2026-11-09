<?php

namespace App\Models;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Incidencia registrada.
 *
 * Importante: el estado no debe cambiarse con $incident->update(['status' => ...]) desde fuera.
 * Todo cambio de estado pasa por IncidentService::changeStatus(), que valida la transición
 * y deja el log en la misma transacción.
 */
class Incident extends Model
{
    // 'status' es asignable para que el servicio pueda fijarlo; no se expone en ningún Form Request.
    protected $fillable = [
        'title', 'description', 'status', 'priority', 'requester_name', 'assigned_to', 'due_date',
    ];

    /**
     * Conversión automática de columnas: status y priority se leen como enums
     * (nunca como strings sueltos) y due_date como fecha Carbon.
     */
    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'priority' => IncidentPriority::class,
            'due_date' => 'date:Y-m-d',
        ];
    }

    /**
     * Historial de trazabilidad, en orden cronológico (el más antiguo primero),
     * que es como se lee una línea de tiempo. El id desempata logs del mismo segundo.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(IncidentLog::class)->orderBy('created_at')->orderBy('id');
    }

    /** Comentarios operativos, el más reciente primero. */
    public function comments(): HasMany
    {
        return $this->hasMany(IncidentComment::class)->latest();
    }

    /**
     * Vencida: tiene fecha límite pasada y no está resuelta.
     * El día de la fecha límite todavía no cuenta como vencida.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== IncidentStatus::Resolved
            && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    /**
     * Scope de filtros del listado: Incident::query()->filter($filters).
     *
     * Cada filtro solo se aplica si tiene valor (when). Los valores ya llegan validados
     * desde el controlador, así que aquí no se revalidan.
     *
     * @param  array{status?: ?string, priority?: ?string, q?: ?string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['priority'] ?? null, fn (Builder $q, string $p) => $q->where('priority', $p))
            ->when($filters['q'] ?? null, function (Builder $q, string $text) {
                // Los OR van agrupados entre paréntesis: sin el where() exterior, un OR suelto
                // anularía los filtros de estado y prioridad.
                $q->where(function (Builder $q) use ($text) {
                    $like = '%'.$text.'%';
                    $q->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('requester_name', 'like', $like)
                        ->orWhere('assigned_to', 'like', $like);
                });
            });
    }
}
