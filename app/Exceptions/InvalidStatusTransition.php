<?php

namespace App\Exceptions;

use App\Enums\IncidentStatus;
use DomainException;

/**
 * Se lanza cuando se intenta un cambio de estado no permitido por IncidentStatus::allowedTransitions().
 *
 * Es una excepción de dominio (regla de negocio), no de validación de formulario:
 * la lanza IncidentService y el controlador la traduce a un error legible para el usuario.
 */
class InvalidStatusTransition extends DomainException
{
    /** Construye la excepción con un mensaje que ya se puede mostrar al usuario tal cual. */
    public static function between(IncidentStatus $from, IncidentStatus $to): self
    {
        return new self(sprintf(
            'No se puede pasar una incidencia de "%s" a "%s".',
            $from->label(),
            $to->label(),
        ));
    }
}
