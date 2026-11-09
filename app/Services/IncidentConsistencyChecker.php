<?php

namespace App\Services;

use App\Enums\IncidentLogAction as A;
use App\Enums\IncidentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detecta y corrige inconsistencias entre incidents e incident_logs.
 *
 * Criterio general: solo se corrige automáticamente lo que no obliga a inventar información,
 * y toda corrección deja a su vez un log con user_name = 'system:diagnose' (la corrección también
 * es trazable). Lo demás se marca para revisión manual con un log 'inconsistency_flagged'.
 *
 * Regla única para los desajustes de estado (comprobaciones 1 y 2):
 *   - Si el estado actual es una transición VÁLIDA desde el último estado registrado en los logs,
 *     el estado es plausible y solo falta el log: se reconstruye ('status_backfilled').
 *   - Si NO es una transición válida (o no hay ningún log de estado), el estado es imposible según
 *     el flujo y no se sabe qué pasó: se marca para revisión manual.
 *   La validez se decide con IncidentStatus::canTransitionTo(), la misma regla que usa la aplicación.
 *
 * Las consultas SQL están documentadas en el README (sección "Inconsistencias simuladas...").
 */
class IncidentConsistencyChecker
{
    public const ACTOR = 'system:diagnose';

    public const BACKFILL = 'backfill';

    public const FLAG = 'flag';

    /** Lista SQL de las acciones cuyo new_value representa un estado ('created','status_changed',...). */
    private function statusActions(): string
    {
        return "'".implode("','", A::statusActions())."'";
    }

    /** Subconsulta: último log de estado de la incidencia i (por fecha y, a igualdad, por id). */
    private function lastStatusLog(string $column): string
    {
        return <<<SQL
            (SELECT l.{$column} FROM incident_logs l
             WHERE l.incident_id = i.id AND l.action IN ({$this->statusActions()})
             ORDER BY l.created_at DESC, l.id DESC LIMIT 1)
            SQL;
    }

    /**
     * 1) Incidencias resueltas sin ningún log que registre la resolución.
     */
    public function resolvedWithoutLog(): Collection
    {
        return collect(DB::select(<<<SQL
            SELECT i.id, i.title, i.status,
                   {$this->lastStatusLog('new_value')} AS last_log_status,
                   {$this->lastStatusLog('id')} AS log_id
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
     * Desajustes de estado (casos 1 y 2) con la decisión que se tomaría para cada uno.
     *
     * @return Collection<int, object{id: int, title: string, status: string, last_log_status: ?string, log_id: ?int, check: string, decision: string, reason: string, flagged: bool}>
     */
    public function stateFindings(): Collection
    {
        $tag = fn (Collection $rows, string $check) => $rows->map(function ($row) use ($check) {
            $row->check = $check;
            [$row->decision, $row->reason] = $this->decide($row->last_log_status, $row->status);
            $row->flagged = $this->isFlagged($row->id, $check, $row->log_id);

            return $row;
        });

        return $tag($this->resolvedWithoutLog(), 'resolved_without_log')
            ->concat($tag($this->statusMismatch(), 'status_mismatch'))
            ->values();
    }

    /**
     * Aplica la regla única: ¿se puede llegar al estado actual desde el último registrado?
     *
     * @return array{0: string, 1: string} [decisión, motivo legible]
     */
    public function decide(?string $lastLogged, string $current): array
    {
        if ($lastLogged === null) {
            return [self::FLAG, 'No hay ningún log de estado: no hay base para reconstruir el historial.'];
        }

        $from = IncidentStatus::tryFrom($lastLogged);
        $to = IncidentStatus::tryFrom($current);

        if ($from === null || $to === null) {
            return [self::FLAG, 'Valor de estado desconocido: no se puede evaluar la transición.'];
        }

        if ($from->canTransitionTo($to)) {
            return [self::BACKFILL, "\"{$from->label()}\" → \"{$to->label()}\" es una transición válida: el estado es plausible y solo falta el log."];
        }

        return [self::FLAG, "\"{$from->label()}\" → \"{$to->label()}\" no es una transición permitida: reconstruirla obligaría a inventar pasos intermedios."];
    }

    /**
     * 3a) Logs duplicados: copias exactas en todos los campos salvo el id
     *     (misma incidencia, acción, valores, actor, metadata y fecha).
     *     metadata se compara como texto porque es una columna JSON.
     */
    public function duplicateLogs(): Collection
    {
        return collect(DB::select(<<<'SQL'
            SELECT incident_id, action, old_value, new_value, user_name, created_at,
                   COUNT(*) AS copies, MIN(id) AS keep_id, GROUP_CONCAT(id ORDER BY id) AS ids
            FROM incident_logs
            GROUP BY incident_id, action, old_value, new_value, user_name, CAST(metadata AS CHAR), created_at
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
            SQL))->map(function ($row) {
                $row->flagged = $this->isFlagged($row->incident_id, 'incoherent_log', $row->id);

                return $row;
            });
    }

    /**
     * Aplica las correcciones en una única transacción: o se aplican todas o ninguna.
     *
     * @return array<string, int> número de acciones realizadas por tipo
     */
    public function fix(): array
    {
        return DB::transaction(function () {
            $done = ['backfilled' => 0, 'flagged' => 0, 'deleted_duplicates' => 0];

            // 1 y 2) Desajustes de estado, según la regla única.
            foreach ($this->stateFindings() as $row) {
                if ($row->decision === self::BACKFILL) {
                    // No se cambia el estado (se da por bueno, porque es alcanzable) y no se inventa
                    // quién ni cuándo: se registra el paso que falta marcado como reconstruido.
                    $this->insertLog($row->id, A::StatusBackfilled, $row->last_log_status, $row->status, [
                        'reconstructed' => true,
                        'check' => $row->check,
                        'note' => 'Log reconstruido por el diagnóstico. Actor y fecha reales desconocidos.',
                    ]);
                    $done['backfilled']++;
                } else {
                    $done['flagged'] += $this->flag($row->id, $row->check, $row->log_id, $row->last_log_status, $row->status,
                        "El estado actual ({$row->status}) no cuadra con el historial. {$row->reason} Revisar manualmente.");
                }
            }

            // 3a) Copias exactas: se borran, pero antes se deja constancia en la propia incidencia
            //     de qué se borró y con qué datos, para que la limpieza también sea auditable.
            foreach ($this->duplicateLogs() as $row) {
                $removed = array_values(array_diff(explode(',', $row->ids), [(string) $row->keep_id]));

                $this->insertLog($row->incident_id, A::DuplicateLogsRemoved, null, null, [
                    'kept_id' => (int) $row->keep_id,
                    'removed_ids' => array_map('intval', $removed),
                    'original' => [
                        'action' => $row->action,
                        'old_value' => $row->old_value,
                        'new_value' => $row->new_value,
                        'user_name' => $row->user_name,
                        'created_at' => $row->created_at,
                    ],
                    'note' => count($removed) === 1
                        ? "Eliminada 1 copia exacta del log #{$row->keep_id}."
                        : sprintf('Eliminadas %d copias exactas del log #%d.', count($removed), $row->keep_id),
                ]);

                $done['deleted_duplicates'] += DB::table('incident_logs')->whereIn('id', $removed)->delete();
            }

            // 3b) Un log incoherente puede ser un error de registro o reflejar algo real: se marca, no se borra.
            foreach ($this->incoherentLogs() as $row) {
                $done['flagged'] += $this->flag($row->incident_id, 'incoherent_log', $row->id, $row->old_value, $row->new_value,
                    "El log #{$row->id} registra un cambio de estado sin cambio real. Revisar manualmente.");
            }

            return $done;
        });
    }

    /** ¿Ya existe una marca de revisión para esta comprobación y este log? (hace el comando idempotente) */
    public function isFlagged(int $incidentId, string $check, ?int $logId): bool
    {
        return DB::table('incident_logs')
            ->where('incident_id', $incidentId)
            ->where('action', A::InconsistencyFlagged->value)
            ->where('metadata->check', $check)
            ->when($logId !== null,
                fn ($q) => $q->where('metadata->log_id', $logId),
                fn ($q) => $q->whereNull('metadata->log_id'))
            ->exists();
    }

    /** Marca una inconsistencia para revisión manual (una sola vez). Devuelve 1 si la marcó, 0 si ya lo estaba. */
    private function flag(int $incidentId, string $check, ?int $logId, ?string $old, ?string $new, string $issue): int
    {
        if ($this->isFlagged($incidentId, $check, $logId)) {
            return 0;
        }

        $this->insertLog($incidentId, A::InconsistencyFlagged, $old, $new, [
            'check' => $check,
            'log_id' => $logId,
            'issue' => $issue,
        ]);

        return 1;
    }

    private function insertLog(int $incidentId, A $action, ?string $old, ?string $new, array $metadata): void
    {
        DB::table('incident_logs')->insert([
            'incident_id' => $incidentId,
            'action' => $action->value,
            'old_value' => $old,
            'new_value' => $new,
            'user_name' => self::ACTOR,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
