<?php

namespace App\Http\Controllers;

use App\Enums\IncidentLogAction;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Http\Requests\ChangeIncidentStatusRequest;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Models\Incident;
use App\Services\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador de incidencias.
 *
 * Es deliberadamente delgado: valida la entrada (Form Requests), delega toda escritura
 * en IncidentService y devuelve una página Inertia o una redirección. No contiene reglas
 * de negocio, así que una futura API podría reutilizar el mismo servicio.
 *
 * Patrón Inertia: las lecturas devuelven Inertia::render() con las props de la página React;
 * las escrituras devuelven back() con un mensaje flash, e Inertia recarga solo las props.
 */
class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $service) {}

    /**
     * Pantalla principal: listado filtrado y paginado, contadores por estado y catálogos.
     */
    public function index(Request $request): Response
    {
        // Los filtros vienen de la URL (?status=...&priority=...&q=...). Se validan contra los enums
        // para que un valor manipulado devuelva un error en lugar de una consulta sin sentido.
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(IncidentStatus::class)],
            'priority' => ['nullable', Rule::enum(IncidentPriority::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $incidents = Incident::query()
            ->filter($filters)
            // Más recientes primero; el id desempata las creadas en el mismo segundo.
            ->latest()
            ->latest('id')
            ->paginate(15)
            // Mantiene los filtros en los enlaces de paginación.
            ->withQueryString()
            ->through(fn (Incident $i) => $this->present($i));

        // Contador por estado en una sola consulta (GROUP BY). Se calcula sobre el total, sin filtros:
        // sirve de resumen global y de filtro rápido, y no tendría sentido que se vaciara al filtrar.
        $counts = Incident::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Los (object) evitan que un array vacío se serialice como [] en JSON: el frontend espera un objeto.
        return Inertia::render('Incidents/Index', [
            'incidents' => $incidents,
            'filters' => (object) $filters,
            'counts' => (object) $counts->all(),
            // Catálogos desde los enums: el frontend no duplica etiquetas ni transiciones.
            'statuses' => IncidentStatus::options(),
            'priorities' => IncidentPriority::options(),
        ]);
    }

    /**
     * Detalle de una incidencia con su historial (logs) y comentarios.
     */
    public function show(Incident $incident): Response
    {
        // Carga ansiosa (eager loading) de las dos relaciones: 2 consultas en lugar de N+1.
        $incident->load(['logs', 'comments']);

        return Inertia::render('Incidents/Show', [
            'incident' => [
                ...$this->present($incident),
                'description' => $incident->description,
                'logs' => $incident->logs,
                'comments' => $incident->comments,
            ],
            'statuses' => IncidentStatus::options(),
            'logActions' => IncidentLogAction::labels(),
        ]);
    }

    /**
     * Alta de incidencia. La validación la hace StoreIncidentRequest antes de entrar aquí:
     * si falla, Laravel redirige atrás con los errores e Inertia los muestra en el formulario.
     */
    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        // Sin autenticación, el actor del log de creación es el propio solicitante.
        $incident = $this->service->create($request->validated(), $request->validated('requester_name'));

        return back()->with('success', "Incidencia #{$incident->id} creada.");
    }

    /**
     * Cambio de estado. El Form Request valida el formato (que el estado exista y haya operador);
     * la coherencia de la transición (de qué estado a cuál) la decide el dominio en el servicio.
     */
    public function changeStatus(ChangeIncidentStatusRequest $request, Incident $incident): RedirectResponse
    {
        try {
            $this->service->changeStatus(
                $incident,
                IncidentStatus::from($request->validated('status')),
                $request->validated('actor_name'),
                $request->validated('reason'),
            );
        } catch (InvalidStatusTransition $e) {
            // La excepción de dominio se traduce a un error de validación: así llega al frontend
            // por el mismo canal que el resto de errores y se muestra junto al botón, sin un 500.
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return back()->with('success', "Estado de la incidencia #{$incident->id} actualizado.");
    }

    /**
     * Añade un comentario operativo (y su log) a la incidencia.
     */
    public function storeComment(StoreCommentRequest $request, Incident $incident): RedirectResponse
    {
        $this->service->addComment($incident, $request->validated('author_name'), $request->validated('body'));

        return back()->with('success', 'Comentario añadido.');
    }

    /**
     * Forma común en que se envía una incidencia al frontend.
     *
     * Se usa en lugar de serializar el modelo entero para controlar qué campos salen,
     * enviar los enums como su valor string y añadir datos calculados (is_overdue, priority_label).
     */
    private function present(Incident $i): array
    {
        return [
            'id' => $i->id,
            'title' => $i->title,
            'status' => $i->status->value,
            'priority' => $i->priority->value,
            'priority_label' => $i->priority->label(),
            'requester_name' => $i->requester_name,
            'assigned_to' => $i->assigned_to,
            'due_date' => $i->due_date?->toDateString(),
            'is_overdue' => $i->isOverdue(),
            'created_at' => $i->created_at->toIso8601String(),
        ];
    }
}
