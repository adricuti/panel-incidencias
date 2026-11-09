<?php

namespace App\Services;

use App\Enums\IncidentLogAction;
use App\Enums\IncidentStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Incident;
use App\Models\IncidentComment;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones de escritura sobre incidencias.
 *
 * Toda acción que modifica la incidencia y su trazabilidad va en una transacción:
 * o se guardan ambas cosas o ninguna.
 */
class IncidentService
{
    /** @param array<string, mixed> $data datos ya validados */
    public function create(array $data, string $actor): Incident
    {
        return DB::transaction(function () use ($data, $actor) {
            $incident = Incident::create([...$data, 'status' => IncidentStatus::Open]);

            $incident->logs()->create([
                'action' => IncidentLogAction::Created->value,
                'old_value' => null,
                'new_value' => IncidentStatus::Open->value,
                'user_name' => $actor,
            ]);

            return $incident;
        });
    }

    /**
     * @throws InvalidStatusTransition si la transición no está permitida
     */
    public function changeStatus(Incident $incident, IncidentStatus $target, string $actor, ?string $reason = null): Incident
    {
        return DB::transaction(function () use ($incident, $target, $actor, $reason) {
            // Bloqueo de fila: evita que dos operadores cambien el estado a la vez
            // partiendo del mismo estado de origen.
            $incident = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            $from = $incident->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidStatusTransition::between($from, $target);
            }

            $incident->update(['status' => $target]);

            $incident->logs()->create([
                'action' => ($from->isReopening($target) ? IncidentLogAction::Reopened : IncidentLogAction::StatusChanged)->value,
                'old_value' => $from->value,
                'new_value' => $target->value,
                'user_name' => $actor,
                'metadata' => $reason ? ['reason' => $reason] : null,
            ]);

            return $incident;
        });
    }

    public function addComment(Incident $incident, string $author, string $body): IncidentComment
    {
        return DB::transaction(function () use ($incident, $author, $body) {
            $comment = $incident->comments()->create(['author_name' => $author, 'body' => $body]);

            $incident->logs()->create([
                'action' => IncidentLogAction::CommentAdded->value,
                'user_name' => $author,
                'metadata' => ['comment_id' => $comment->id],
            ]);

            $incident->touch();

            return $comment;
        });
    }
}
