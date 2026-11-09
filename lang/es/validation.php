<?php

// Solo las reglas usadas en la aplicación; el resto cae al idioma de respaldo (en).
return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'after_or_equal' => 'La :attribute debe ser hoy o una fecha posterior.',
    'enum' => 'El valor de :attribute no es válido.',
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.'],
    'max' => ['string' => 'El campo :attribute no puede superar los :max caracteres.'],
];
