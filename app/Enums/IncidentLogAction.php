<?php

namespace App\Enums;

/**
 * Acciones que se registran en incident_logs.
 */
enum IncidentLogAction: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Reopened = 'reopened';
    case CommentAdded = 'comment_added';

    // Acciones generadas por el diagnóstico de inconsistencias (incidents:diagnose).
    case ResolutionBackfilled = 'resolution_backfilled';
    case InconsistencyFlagged = 'inconsistency_flagged';

    /** Acciones que reflejan un cambio de estado (su new_value es un estado). */
    public static function statusActions(): array
    {
        return [self::Created->value, self::StatusChanged->value, self::Reopened->value, self::ResolutionBackfilled->value];
    }
}
