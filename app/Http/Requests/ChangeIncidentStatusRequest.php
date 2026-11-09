<?php

namespace App\Http\Requests;

use App\Enums\IncidentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de la petición de cambio de estado: que el estado exista,
 * que haya operador y que el motivo no sea excesivo.
 *
 * La coherencia de la transición (de qué estado a cuál) NO se valida aquí, porque depende
 * del estado actual en BD y debe comprobarse con la fila bloqueada: la valida IncidentService.
 */
class ChangeIncidentStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(IncidentStatus::class)],
            // Operador de la cabecera; queda como user_name en el log.
            'actor_name' => ['required', 'string', 'max:100'],
            // Motivo opcional del modal de confirmación; se guarda en metadata.reason del log.
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'estado', 'actor_name' => 'operador', 'reason' => 'motivo'];
    }

    public function messages(): array
    {
        return ['actor_name.required' => 'Indica tu nombre en el campo "Operador" de la cabecera.'];
    }
}
