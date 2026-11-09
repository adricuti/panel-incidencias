<?php

namespace App\Exceptions;

use App\Enums\IncidentStatus;
use DomainException;

class InvalidStatusTransition extends DomainException
{
    public static function between(IncidentStatus $from, IncidentStatus $to): self
    {
        return new self(sprintf(
            'No se puede pasar una incidencia de "%s" a "%s".',
            $from->label(),
            $to->label(),
        ));
    }
}
