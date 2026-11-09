<?php

namespace App\Console\Commands;

use App\Services\IncidentConsistencyChecker;
use Illuminate\Console\Command;

/**
 * Diagnóstico de inconsistencias. Por defecto es dry-run: solo informa.
 * Con --fix aplica las correcciones seguras dentro de una transacción.
 */
class DiagnoseIncidents extends Command
{
    protected $signature = 'incidents:diagnose {--fix : Aplica las correcciones (sin esta opción solo se muestra qué se haría)}';

    protected $description = 'Detecta incoherencias entre incidencias y su trazabilidad (dry-run por defecto)';

    public function handle(IncidentConsistencyChecker $checker): int
    {
        $fix = (bool) $this->option('fix');
        $this->info($fix ? 'Modo CORRECCIÓN' : 'Modo DRY-RUN (no se modifica nada; usa --fix para aplicar)');

        $resolved = $checker->resolvedWithoutLog();
        $this->section('1) Resueltas sin log de resolución', 'Se creará un log "resolution_backfilled" marcado como reconstruido.');
        $this->table(['id', 'título', 'estado'], $resolved->map(fn ($r) => [$r->id, $r->title, $r->status]));

        $mismatch = $checker->statusMismatch();
        $this->section('2) Estado distinto del último log', 'NO se corrige el estado (no se sabe cuál es el correcto): se marca para revisión manual.');
        $this->table(['id', 'título', 'estado actual', 'último log'], $mismatch->map(fn ($r) => [$r->id, $r->title, $r->status, $r->last_log_status]));

        $duplicates = $checker->duplicateLogs();
        $this->section('3a) Logs duplicados', 'Se borrarán las copias exactas y se conservará el log de menor id.');
        $this->table(['incidencia', 'acción', 'de → a', 'copias', 'se conserva', 'ids'], $duplicates->map(fn ($r) => [
            $r->incident_id, $r->action, "{$r->old_value} → {$r->new_value}", $r->copies, $r->keep_id, $r->ids,
        ]));

        $incoherent = $checker->incoherentLogs();
        $this->section('3b) Logs incoherentes (estado anterior = nuevo)', 'Se marcan para revisión manual, no se borran.');
        $this->table(['log', 'incidencia', 'acción', 'de → a'], $incoherent->map(fn ($r) => [$r->id, $r->incident_id, $r->action, "{$r->old_value} → {$r->new_value}"]));

        if (! $fix) {
            return self::SUCCESS;
        }

        $result = $checker->fix();
        $this->newLine();
        $this->info(sprintf(
            'Hecho: %d logs de resolución reconstruidos, %d inconsistencias marcadas para revisión, %d logs duplicados eliminados.',
            $result['backfilled'], $result['flagged'], $result['deleted_duplicates'],
        ));

        return self::SUCCESS;
    }

    private function section(string $title, string $action): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
        $this->line("<fg=gray>Acción: {$action}</>");
    }
}
