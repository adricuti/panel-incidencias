<?php

namespace App\Http\Requests;

use App\Enums\IncidentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de la petición. La coherencia de la transición
 * (de qué estado a cuál) la valida el dominio en IncidentService.
 */
class ChangeIncidentStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(IncidentStatus::class)],
            'actor_name' => ['required', 'string', 'max:100'],
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
