<?php

namespace App\Http\Requests;

use App\Enums\IncidentPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del alta de incidencia.
 *
 * El estado no se acepta desde el formulario: toda incidencia nace "abierta" (lo fija el servicio).
 * Si la validación falla, Laravel redirige atrás con los errores y el formulario React los
 * muestra junto a cada campo.
 */
class StoreIncidentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            // Solo valores del enum: evita prioridades inventadas.
            'priority' => ['required', Rule::enum(IncidentPriority::class)],
            'requester_name' => ['required', 'string', 'max:100'],
            'assigned_to' => ['nullable', 'string', 'max:100'],
            // No tiene sentido crear una incidencia que ya nace vencida.
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /** Nombres de los campos en los mensajes de error ("El campo título es obligatorio"). */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'description' => 'descripción',
            'priority' => 'prioridad',
            'requester_name' => 'solicitante',
            'assigned_to' => 'responsable',
            'due_date' => 'fecha límite',
        ];
    }
}
