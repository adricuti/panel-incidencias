<?php

namespace App\Services;

use App\Enums\IncidentLogAction as A;
use App\Enums\IncidentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detecta y corrige inconsistencias entre incidents e incident_logs.
 *
 * Criterio: solo se corrige automáticamente lo que no requiere inventar información.
 * Lo demás se marca para revisión manual con un log 'inconsistency_flagged'.
 * Las consultas SQL están documentadas en el README (sección Diagnóstico).
 */
class IncidentConsistencyChecker
{
    public const ACTOR = 'system:diagnose';

    /** Acciones cuyo new_value representa un estado de la incidencia. */
    private function statusActions(): string
    {
        return "'".implode("','", A::statusActions())."'";
    }

    /**
     * 1) Incidencias resueltas sin ningún log que registre la resolución.
     */
    public function resolvedWithoutLog(): Collection
    {
        return collect(DB::select(<<<SQL
            SELECT i.id, i.title, i.status
            FROM incidents i
            WHERE i.status = ?
              AND NOT EXISTS (
                  SELECT 1 FROM incident_logs l
                  WHERE l.incident_id = i.id
                    AND l.new_value = ?
                    AND l.action IN ({$this->statusActions()})
              )
            SQL, [IncidentStatus::Resolved->value, IncidentStatus::Resolved->value]));
    }

    /**
     * 2) Incidencias cuyo estado actual no coincide con el new_value de su último log de estado.
     *    Se excluyen las del caso 1 para no reportarlas dos veces.
     */
    public function statusMismatch(): Collection
    {
        $excluded = $this->resolvedWithoutLog()->pluck('id');

        return collect(DB::select(<<<SQL
            SELECT i.id, i.title, i.status, l.id AS log_id, l.new_value AS last_log_status
            FROM incidents i
            JOIN incident_logs l ON l.id = (
                SELECT l2.id FROM incident_logs l2
                WHERE l2.incident_id = i.id
                  AND l2.action IN ({$this->statusActions()})
                ORDER BY l2.created_at DESC, l2.id DESC
                LIMIT 1
            )
            WHERE l.new_value <> i.status
            SQL))->reject(fn ($r) => $excluded->contains($r->id))->values();
    }

    /**
     * 3a) Logs duplicados: misma incidencia, acción, valores y fecha.
     *     Se conserva el de menor id; el resto son copias exactas.
     */
    public function duplicateLogs(): Collection
    {
        return collect(DB::select(<<<'SQL'
            SELECT incident_id, action, old_value, new_value, created_at,
                   COUNT(*) AS copies, MIN(id) AS keep_id, GROUP_CONCAT(id ORDER BY id) AS ids
            FROM incident_logs
            GROUP BY incident_id, action, old_value, new_value, created_at
            HAVING COUNT(*) > 1
            SQL));
    }

    /**
     * 3b) Logs incoherentes: un cambio de estado cuyo valor anterior y nuevo son iguales.
     */
    public function incoherentLogs(): Collection
    {
        return collect(DB::select(<<<'SQL'
            SELECT id, incident_id, action, old_value, new_value, created_at
            FROM incident_logs
            WHERE action IN ('status_changed', 'reopened')
              AND old_value = new_value
            SQL));
    }

    /**
     * Aplica las correcciones en una única transacción.
     *
     * @return array<string, int> número de acciones realizadas por tipo
     */
    public function fix(): array
    {
        return DB::transaction(function () {
            $done = ['backfilled' => 0, 'flagged' => 0, 'deleted_duplicates' => 0];

            // 1) Se reconstruye el log de resolución: el estado "resuelta" es un hecho en la tabla,
            //    pero NO se conoce quién ni cuándo resolvió. Se deja constancia en metadata.
            foreach ($this->resolvedWithoutLog() as $row) {
                $previous = DB::table('incident_logs')
                    ->where('incident_id', $row->id)
                    ->whereIn('action', A::statusActions())
                    ->orderByDesc('created_at')->orderByDesc('id')
                    ->value('new_value');

                DB::table('incident_logs')->insert([
                    'incident_id' => $row->id,
                    'action' => A::ResolutionBackfilled->value,
                    'old_value' => $previous,
                    'new_value' => IncidentStatus::Resolved->value,
                    'user_name' => self::ACTOR,
                    'metadata' => json_encode(['reconstructed' => true, 'note' => 'Actor y fecha reales desconocidos']),
                    'created_at' => now(),
                ]);
                $done['backfilled']++;
            }

            // 2) No se sabe si la verdad es la tabla o el log: NO se cambia el estado.
            //    Se marca para revisión manual (una sola vez por log afectado).
            foreach ($this->statusMismatch() as $row) {
                $done['flagged'] += $this->flag($row->id, 'status_mismatch', $row->log_id, $row->last_log_status, $row->status,
                    "El estado ({$row->status}) no coincide con el último log ({$row->last_log_status}). Revisar manualmente.");
            }

            // 3a) Copias exactas: borrar no pierde información.
            foreach ($this->duplicateLogs() as $row) {
                $extra = array_diff(explode(',', $row->ids), [(string) $row->keep_id]);
                $done['deleted_duplicates'] += DB::table('incident_logs')->whereIn('id', $extra)->delete();
            }

            // 3b) Un log incoherente puede ser un error de registro o reflejar algo real: se marca, no se borra.
            foreach ($this->incoherentLogs() as $row) {
                $done['flagged'] += $this->flag($row->incident_id, 'incoherent_log', $row->id, $row->old_value, $row->new_value,
                    "El log #{$row->id} registra un cambio de estado sin cambio real. Revisar manualmente.");
            }

            return $done;
        });
    }

    private function flag(int $incidentId, string $check, int $logId, ?string $old, ?string $new, string $issue): int
    {
        $alreadyFlagged = DB::table('incident_logs')
            ->where('incident_id', $incidentId)
            ->where('action', A::InconsistencyFlagged->value)
            ->where('metadata->check', $check)
            ->where('metadata->log_id', $logId)
            ->exists();

        if ($alreadyFlagged) {
            return 0;
        }

        DB::table('incident_logs')->insert([
            'incident_id' => $incidentId,
            'action' => A::InconsistencyFlagged->value,
            'old_value' => $old,
            'new_value' => $new,
            'user_name' => self::ACTOR,
            'metadata' => json_encode(['check' => $check, 'log_id' => $logId, 'issue' => $issue]),
            'created_at' => now(),
        ]);

        return 1;
    }
}
