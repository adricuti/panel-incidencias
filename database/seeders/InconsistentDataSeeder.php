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
 * Las incidencias llevan el prefijo [SIM] (simulada) en el título para distinguirlas de los datos
 * de ejemplo coherentes. Solicitantes y actores son personas, como en el resto de datos: lo que está
 * mal es la coherencia entre el estado y su historial, no quién las pidió.
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
        // El último log dice "en revisión" y "en revisión → resuelta" es una transición válida:
        // el estado es plausible y solo falta el log -> el diagnóstico lo reconstruye.
        $id1 = $this->incident('[SIM] Resuelta sin log de resolución', S::Resolved, 'Marta Vidal', 'Anna Roca', $t);
        $this->log($id1, A::Created, null, S::Open, 'Marta Vidal', $t);
        $this->log($id1, A::StatusChanged, S::Open, S::InReview, 'Anna Roca', $t->copy()->addHour());

        // Inconsistencia 2: el estado no coincide con su último log de estado.
        // La tabla dice "bloqueada", pero el último log dice "abierta", y "abierta → bloqueada" no es
        // una transición permitida: falta al menos un paso y no se sabe cuál -> se marca para revisión.
        $id2 = $this->incident('[SIM] Estado distinto del último log', S::Blocked, 'Pere Font', 'Marc Puig', $t);
        $this->log($id2, A::Created, null, S::Open, 'Pere Font', $t);

        // Inconsistencia 3a: log duplicado (mismo cambio registrado dos veces, p. ej. doble envío).
        // Las dos filas son idénticas en todo (también actor y fecha): copia exacta.
        $id3 = $this->incident('[SIM] Log duplicado', S::InReview, 'Núria Pons', 'Anna Roca', $t);
        $this->log($id3, A::Created, null, S::Open, 'Núria Pons', $t);
        $this->log($id3, A::StatusChanged, S::Open, S::InReview, 'Anna Roca', $t->copy()->addHour());
        $this->log($id3, A::StatusChanged, S::Open, S::InReview, 'Anna Roca', $t->copy()->addHour());

        // Inconsistencia 3b: log incoherente (cambio de estado cuyo valor anterior y nuevo son iguales).
        $id4 = $this->incident('[SIM] Log incoherente', S::InReview, 'Jordi Serra', 'Marc Puig', $t);
        $this->log($id4, A::Created, null, S::Open, 'Jordi Serra', $t);
        $this->log($id4, A::StatusChanged, S::Open, S::InReview, 'Marc Puig', $t->copy()->addHour());
        $this->log($id4, A::StatusChanged, S::InReview, S::InReview, 'Marc Puig', $t->copy()->addHours(2));
    }

    private function incident(string $title, S $status, string $requester, string $assigned, Carbon $at): int
    {
        return DB::table('incidents')->insertGetId([
            'title' => $title,
            'description' => 'Dato simulado: incidencia importada del sistema anterior con el historial '
                .'incompleto o incorrecto. Sirve para probar el diagnóstico (php artisan incidents:diagnose).',
            'status' => $status->value,
            'priority' => P::Medium->value,
            'requester_name' => $requester,
            'assigned_to' => $assigned,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function log(int $incidentId, A $action, ?S $old, S $new, string $actor, Carbon $at): void
    {
        DB::table('incident_logs')->insert([
            'incident_id' => $incidentId,
            'action' => $action->value,
            'old_value' => $old?->value,
            'new_value' => $new->value,
            'user_name' => $actor,
            'created_at' => $at,
        ]);
    }
}
