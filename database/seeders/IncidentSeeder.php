<?php

namespace Database\Seeders;

use App\Enums\IncidentPriority as P;
use App\Enums\IncidentStatus as S;
use App\Services\IncidentService;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo coherentes: se crean a través del servicio de dominio,
 * por lo que cada cambio de estado deja su log correspondiente.
 */
class IncidentSeeder extends Seeder
{
    public function run(IncidentService $service): void
    {
        // Cada fila: título, descripción, prioridad, solicitante, responsable,
        // fecha límite relativa a hoy (null = sin fecha) y transiciones a aplicar tras crearla.
        // Las fechas pasadas generan incidencias vencidas para ver el resaltado en el listado.
        $rows = [
            ['Caída del TPV en tienda Diagonal', 'Los terminales no sincronizan ventas desde las 9:00.', P::Critical, 'Laura Gil', 'Marc Puig', '-2 days', [S::InReview]],
            ['Error en importación de stock', 'El CSV del proveedor llega con columnas cambiadas.', P::High, 'Jordi Serra', 'Anna Roca', '+3 days', [S::InReview, S::Blocked]],
            ['Etiquetas de precio desalineadas', 'La impresora de etiquetas corta el código de barras.', P::Low, 'Marta Vidal', null, '+10 days', []],
            ['Informe de ventas semanal vacío', 'El informe del lunes salió sin datos de dos tiendas.', P::Medium, 'Pere Font', 'Marc Puig', '-1 day', [S::InReview, S::Resolved]],
            ['Usuario sin acceso al CRM', 'Alta nueva en RRHH sin permisos de consulta.', P::Medium, 'Núria Pons', 'Anna Roca', null, [S::InReview, S::Resolved, S::Open]],
            ['Lentitud en ficha de cliente', 'La ficha tarda más de 8 segundos en cargar.', P::High, 'Laura Gil', 'Marc Puig', '+1 day', [S::InReview]],
            ['Duplicados en fidelización', 'Clientes con dos tarjetas activas.', P::Medium, 'Jordi Serra', null, null, []],
        ];

        foreach ($rows as [$title, $description, $priority, $requester, $assigned, $due, $transitions]) {
            $incident = $service->create([
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'requester_name' => $requester,
                'assigned_to' => $assigned,
                'due_date' => $due ? now()->modify($due)->toDateString() : null,
            ], $requester);

            // Las transiciones pasan por changeStatus(): se validan y cada una deja su log,
            // igual que si un operador las hiciera desde la interfaz.
            foreach ($transitions as $status) {
                $service->changeStatus($incident, $status, $assigned ?? 'operador', 'Dato de ejemplo');
            }
        }
    }
}
