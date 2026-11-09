<?php

namespace App\Enums;

/**
 * Prioridades de una incidencia (columna `priority`).
 *
 * Se guardan como string en BD y se convierten a este enum con el cast del modelo Incident,
 * de forma que en el código nunca se comparan strings sueltos como 'high'.
 */
enum IncidentPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /** Texto que se muestra en la interfaz. */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baja',
            self::Medium => 'Media',
            self::High => 'Alta',
            self::Critical => 'Crítica',
        };
    }

    /**
     * Lista serializable para los selects del frontend (filtro y formulario de alta).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $p) => ['value' => $p->value, 'label' => $p->label()], self::cases());
    }
}
