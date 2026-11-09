<?php

namespace App\Models;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    protected $fillable = [
        'title', 'description', 'status', 'priority', 'requester_name', 'assigned_to', 'due_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'priority' => IncidentPriority::class,
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IncidentLog::class)->orderBy('created_at')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IncidentComment::class)->latest();
    }

    /** Vencida: tiene fecha límite pasada y no está resuelta. */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== IncidentStatus::Resolved
            && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    /**
     * Filtros del listado. Los valores ya llegan validados desde el controlador.
     *
     * @param  array{status?: ?string, priority?: ?string, q?: ?string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['priority'] ?? null, fn (Builder $q, string $p) => $q->where('priority', $p))
            ->when($filters['q'] ?? null, function (Builder $q, string $text) {
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
