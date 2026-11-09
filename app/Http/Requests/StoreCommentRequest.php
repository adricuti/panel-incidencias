<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['author_name' => 'operador', 'body' => 'comentario'];
    }

    public function messages(): array
    {
        // El autor del comentario es el operador indicado en la cabecera: se dice dónde está el campo.
        return ['author_name.required' => 'Indica tu nombre en el campo "Operador" de la cabecera.'];
    }
}
