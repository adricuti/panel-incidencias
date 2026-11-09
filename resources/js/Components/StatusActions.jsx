import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useActor } from './ActorContext';
import { findStatus } from '../lib/status';

/**
 * Botones con las transiciones permitidas desde el estado actual.
 * Las transiciones y qué estados requieren confirmación vienen del backend.
 */
export default function StatusActions({ incident, statuses, compact = false }) {
    const { actor } = useActor();
    const [error, setError] = useState(null);
    const [processing, setProcessing] = useState(false);

    const current = findStatus(statuses, incident.status);
    if (!current) return null;

    const change = (target) => {
        setError(null);
        if (!actor.trim()) {
            setError('Indica tu nombre de operador en la cabecera.');
            return;
        }

        let reason = null;
        if (target.confirm) {
            // Confirmación antes de resolver o bloquear; el motivo queda en el log.
            reason = window.prompt(`¿Seguro que quieres marcar #${incident.id} como "${target.label}"?\nMotivo (opcional):`, '');
            if (reason === null) return;
        }

        router.patch(
            `/incidents/${incident.id}/status`,
            { status: target.value, actor_name: actor, reason: reason || null },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errors) => setError(Object.values(errors)[0]),
            },
        );
    };

    return (
        <div className="flex flex-col gap-1">
            <div className="flex flex-wrap gap-1">
                {current.transitions.map((value) => {
                    const target = findStatus(statuses, value);
                    const label = incident.status === 'resolved' && value === 'open' ? 'Reabrir' : `→ ${target.label}`;
                    return (
                        <button
                            key={value}
                            type="button"
                            disabled={processing}
                            onClick={() => change(target)}
                            className={`rounded-md border border-slate-300 bg-white font-medium hover:bg-slate-100 disabled:opacity-50 ${
                                compact ? 'px-2 py-0.5 text-xs' : 'px-3 py-1.5 text-sm'
                            }`}
                        >
                            {label}
                        </button>
                    );
                })}
            </div>
            {error && <p className="text-xs text-red-600">{error}</p>}
        </div>
    );
}
