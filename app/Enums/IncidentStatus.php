<?php

namespace App\Enums;

/**
 * Estados de una incidencia y sus transiciones permitidas.
 *
 * Es la única fuente de verdad del flujo: backend (validación) y frontend
 * (botones disponibles) leen de aquí, así no hay strings mágicos dispersos.
 */
enum IncidentStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case Blocked = 'blocked';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::InReview => 'En revisión',
            self::Blocked => 'Bloqueada',
            self::Resolved => 'Resuelta',
        };
    }

    /**
     * Transiciones permitidas desde este estado.
     *
     * abierta -> en revisión -> bloqueada | resuelta
     * bloqueada -> en revisión (desbloqueo)
     * resuelta -> abierta (solo como reapertura explícita, ver isReopening()).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InReview],
            self::InReview => [self::Blocked, self::Resolved],
            self::Blocked => [self::InReview],
            self::Resolved => [self::Open],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Una reapertura se registra con una acción propia en el log. */
    public function isReopening(self $target): bool
    {
        return $this === self::Resolved && $target === self::Open;
    }

    /** Transiciones que la UI debe confirmar antes de ejecutarlas. */
    public function requiresConfirmation(): bool
    {
        return in_array($this, [self::Blocked, self::Resolved], true);
    }

    /**
     * Representación serializable para el frontend.
     *
     * @return list<array{value: string, label: string, transitions: list<string>, confirm: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'transitions' => array_map(fn (self $t) => $t->value, $s->allowedTransitions()),
            'confirm' => $s->requiresConfirmation(),
        ], self::cases());
    }
}
