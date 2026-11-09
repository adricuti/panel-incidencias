<?php

namespace App\Http\Controllers;

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
 * Controlador delgado: valida, delega en IncidentService y devuelve la vista.
 */
class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $service) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(IncidentStatus::class)],
            'priority' => ['nullable', Rule::enum(IncidentPriority::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $incidents = Incident::query()
            ->filter($filters)
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Incident $i) => $this->present($i));

        // Contador por estado (sobre el total, sin filtros) en una sola consulta.
        $counts = Incident::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Incidents/Index', [
            'incidents' => $incidents,
            'filters' => (object) $filters,
            'counts' => (object) $counts->all(),
            'statuses' => IncidentStatus::options(),
            'priorities' => IncidentPriority::options(),
        ]);
    }

    public function show(Incident $incident): Response
    {
        $incident->load(['logs', 'comments']);

        return Inertia::render('Incidents/Show', [
            'incident' => [
                ...$this->present($incident),
                'description' => $incident->description,
                'logs' => $incident->logs,
                'comments' => $incident->comments,
            ],
            'statuses' => IncidentStatus::options(),
        ]);
    }

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $incident = $this->service->create($request->validated(), $request->validated('requester_name'));

        return back()->with('success', "Incidencia #{$incident->id} creada.");
    }

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
            // Se devuelve como error de validación para que la UI lo muestre de forma clara.
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return back()->with('success', "Estado de la incidencia #{$incident->id} actualizado.");
    }

    public function storeComment(StoreCommentRequest $request, Incident $incident): RedirectResponse
    {
        $this->service->addComment($incident, $request->validated('author_name'), $request->validated('body'));

        return back()->with('success', 'Comentario añadido.');
    }

    /** Forma común en que se envía una incidencia al frontend. */
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
