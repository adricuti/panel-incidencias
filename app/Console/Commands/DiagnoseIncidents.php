<?php

namespace App\Console\Commands;

use App\Services\IncidentConsistencyChecker as Checker;
use Illuminate\Console\Command;

/**
 * Diagnóstico de inconsistencias. Por defecto es dry-run: solo informa de qué encontró
 * y qué haría con cada caso. Con --fix aplica las correcciones dentro de una transacción.
 */
class DiagnoseIncidents extends Command
{
    protected $signature = 'incidents:diagnose {--fix : Aplica las correcciones (sin esta opción solo se muestra qué se haría)}';

    protected $description = 'Detecta incoherencias entre incidencias y su trazabilidad (dry-run por defecto)';

    public function handle(Checker $checker): int
    {
        // Primero siempre se muestra el informe (lo mismo en dry-run que con --fix),
        // para que quede constancia de qué se encontró antes de modificar nada.
        $fix = (bool) $this->option('fix');
        $this->info($fix ? 'Modo CORRECCIÓN' : 'Modo DRY-RUN (no se modifica nada; usa --fix para aplicar)');

        // 1 y 2) Desajustes de estado: la decisión de cada fila sale de la regla única del checker.
        $this->section(
            '1-2) Estado sin log que lo respalde',
            'Si el estado actual es una transición válida desde el último log, se reconstruye el log; si no, se marca para revisión manual.',
        );
        $this->table(
            ['id', 'título', 'estado actual', 'último log', 'comprobación', 'qué se hará', 'motivo'],
            $checker->stateFindings()->map(fn ($r) => [
                $r->id, $r->title, $r->status, $r->last_log_status ?? '—', $r->check,
                $this->actionLabel($r->decision, $r->flagged), $r->reason,
            ]),
        );

        $this->section('3a) Logs duplicados (copias exactas)', 'Se conserva el de menor id, se borran las copias y se deja un log con lo borrado.');
        $this->table(['incidencia', 'acción', 'de → a', 'copias', 'se conserva', 'ids'], $checker->duplicateLogs()->map(fn ($r) => [
            $r->incident_id, $r->action, "{$r->old_value} → {$r->new_value}", $r->copies, $r->keep_id, $r->ids,
        ]));

        $this->section('3b) Logs incoherentes (estado anterior = nuevo)', 'Se marcan para revisión manual, no se borran.');
        $this->table(['log', 'incidencia', 'acción del log', 'de → a', 'qué se hará'], $checker->incoherentLogs()->map(fn ($r) => [
            $r->id, $r->incident_id, $r->action, "{$r->old_value} → {$r->new_value}", $this->actionLabel(Checker::FLAG, $r->flagged),
        ]));

        // Dry-run: termina aquí sin tocar la base de datos.
        if (! $fix) {
            return self::SUCCESS;
        }

        // Todas las correcciones se aplican en una única transacción (ver IncidentConsistencyChecker::fix()).
        $result = $checker->fix();
        $this->newLine();
        $this->info(sprintf(
            'Hecho: %d logs de estado reconstruidos, %d inconsistencias marcadas para revisión, %d logs duplicados eliminados.',
            $result['backfilled'], $result['flagged'], $result['deleted_duplicates'],
        ));

        return self::SUCCESS;
    }

    /** Texto de la acción para el informe. Una marca ya existente no se vuelve a crear. */
    private function actionLabel(string $decision, bool $flagged): string
    {
        return match (true) {
            $decision === Checker::BACKFILL => 'Reconstruir log',
            $flagged => 'Ya marcada (pendiente de revisión)',
            default => 'Marcar para revisión',
        };
    }

    /** Cabecera de cada comprobación: qué se busca y qué se hará con lo encontrado. */
    private function section(string $title, string $action): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
        $this->line("<fg=gray>Criterio: {$action}</>");
    }
}
