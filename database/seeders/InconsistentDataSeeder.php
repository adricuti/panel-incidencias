<?php

namespace Database\Seeders;

use App\Enums\IncidentLogAction as A;
use App\Enums\IncidentPriority as P;
use App\Enums\IncidentStatus as S;
use Illuminate\Support\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Simula datos corruptos típicos de mantenimiento (migraciones antiguas, procesos
 * que actualizan la tabla sin pasar por el servicio, reintentos duplicados...).
 *
 * Se insertan con DB::table a propósito: saltarse el servicio de dominio es
 * justamente lo que provoca estas inconsistencias en la vida real.
 * Se detectan y corrigen con: php artisan incidents:diagnose
 */
class InconsistentDataSeeder extends Seeder
{
    public function run(): void
    {
        $t = Carbon::now()->subDays(5);

        // Inconsistencia 1: incidencia resuelta sin log de resolución.
        $id1 = $this->incident('[SIM] Resuelta sin log de resolución', S::Resolved, $t);
        $this->log($id1, A::Created, null, S::Open, $t);
        $this->log($id1, A::StatusChanged, S::Open, S::InReview, $t->copy()->addHour());

        // Inconsistencia 2: el estado no coincide con su último log de estado.
        // La tabla dice "bloqueada", pero el último log dice "en revisión".
        $id2 = $this->incident('[SIM] Estado distinto del último log', S::Blocked, $t);
        $this->log($id2, A::Created, null, S::Open, $t);
        $this->log($id2, A::StatusChanged, S::Open, S::InReview, $t->copy()->addHour());

        // Inconsistencia 3a: log duplicado (mismo cambio registrado dos veces, p. ej. doble envío).
        $id3 = $this->incident('[SIM] Log duplicado', S::InReview, $t);
        $this->log($id3, A::Created, null, S::Open, $t);
        $this->log($id3, A::StatusChanged, S::Open, S::InReview, $t->copy()->addHour());
        $this->log($id3, A::StatusChanged, S::Open, S::InReview, $t->copy()->addHour());

        // Inconsistencia 3b: log incoherente (cambio de estado cuyo valor anterior y nuevo son iguales).
        $id4 = $this->incident('[SIM] Log incoherente', S::InReview, $t);
        $this->log($id4, A::Created, null, S::Open, $t);
        $this->log($id4, A::StatusChanged, S::Open, S::InReview, $t->copy()->addHour());
        $this->log($id4, A::StatusChanged, S::InReview, S::InReview, $t->copy()->addHours(2));
    }

    private function incident(string $title, S $status, Carbon $at): int
    {
        return DB::table('incidents')->insertGetId([
            'title' => $title,
            'description' => 'Dato simulado para el ejercicio de diagnóstico.',
            'status' => $status->value,
            'priority' => P::Medium->value,
            'requester_name' => 'Migración legacy',
            'assigned_to' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function log(int $incidentId, A $action, ?S $old, S $new, Carbon $at): void
    {
        DB::table('incident_logs')->insert([
            'incident_id' => $incidentId,
            'action' => $action->value,
            'old_value' => $old?->value,
            'new_value' => $new->value,
            'user_name' => 'Migración legacy',
            'created_at' => $at,
        ]);
    }
}
