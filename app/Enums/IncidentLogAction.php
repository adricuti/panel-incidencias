<?php

namespace App\Enums;

/**
 * Acciones que se registran en incident_logs (columna `action`).
 *
 * Igual que con los estados, las acciones y sus etiquetas viven solo aquí:
 * el frontend recibe las etiquetas del backend (ver labels()) y no las duplica.
 */
enum IncidentLogAction: string
{
    // Acciones generadas por el uso normal de la aplicación (IncidentService).
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Reopened = 'reopened';
    case CommentAdded = 'comment_added';

    // Acciones generadas por el diagnóstico de inconsistencias (incidents:diagnose).
    case ResolutionBackfilled = 'resolution_backfilled';
    case InconsistencyFlagged = 'inconsistency_flagged';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Creación',
            self::StatusChanged => 'Cambio de estado',
            self::Reopened => 'Reapertura',
            self::CommentAdded => 'Comentario',
            self::ResolutionBackfilled => 'Resolución reconstruida (diagnóstico)',
            self::InconsistencyFlagged => 'Inconsistencia marcada para revisión',
        };
    }

    /**
     * Acciones cuyo new_value representa un estado de la incidencia.
     *
     * Las usa el diagnóstico para saber cuál es el "último log de estado" de una incidencia:
     * un comentario o una marca de inconsistencia no cambian el estado y no deben contar.
     *
     * @return list<string>
     */
    public static function statusActions(): array
    {
        return [self::Created->value, self::StatusChanged->value, self::Reopened->value, self::ResolutionBackfilled->value];
    }

    /**
     * Mapa valor => etiqueta para el frontend (historial de la incidencia).
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_combine(
            array_map(fn (self $a) => $a->value, self::cases()),
            array_map(fn (self $a) => $a->label(), self::cases()),
        );
    }
}
