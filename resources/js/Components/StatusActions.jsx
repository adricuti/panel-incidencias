import { router } from '@inertiajs/react';
import { useState } from 'react';
import { MISSING_ACTOR_MESSAGE, useActor } from './ActorContext';
import Modal from './Modal';
import { inputClass } from './Field';
import { findStatus } from '../lib/status';

/**
 * Botones con las transiciones permitidas desde el estado actual.
 * Las transiciones y qué estados requieren confirmación vienen del backend.
 *
 * Flujo: clic en un botón -> si el estado destino requiere confirmación (bloquear, resolver)
 * se abre un modal con motivo opcional -> al confirmar se envía el PATCH.
 */
export default function StatusActions({ incident, statuses, compact = false }) {
    const { requireActor } = useActor();
    const [error, setError] = useState(null);
    const [processing, setProcessing] = useState(false);
    // Transición pendiente de confirmar (abre el modal) y motivo escrito.
    const [pending, setPending] = useState(null);
    const [reason, setReason] = useState('');

    const current = findStatus(statuses, incident.status);
    if (!current) return null;

    const closeModal = () => {
        setPending(null);
        setReason('');
    };

    const send = (target, actor, reasonText = null) => {
        router.patch(
            `/incidents/${incident.id}/status`,
            { status: target.value, actor_name: actor, reason: reasonText || null },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: closeModal,
                onError: (errors) => setError(Object.values(errors)[0]),
            },
        );
    };

    const change = (target) => {
        setError(null);
        const actor = requireActor();
        if (!actor) {
            setError(MISSING_ACTOR_MESSAGE);
            return;
        }

        if (target.confirm) {
            setPending(target);
            return;
        }
        send(target, actor);
    };

    const confirm = (e) => {
        e.preventDefault();
        const actor = requireActor();
        if (!actor) {
            setError(MISSING_ACTOR_MESSAGE);
            closeModal();
            return;
        }
        send(pending, actor, reason.trim());
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
            {error && !pending && <p className="text-xs text-red-600">{error}</p>}

            <Modal open={pending !== null} onClose={closeModal} title={`Marcar como "${pending?.label ?? ''}"`}>
                <form onSubmit={confirm} className="space-y-4">
                    <p className="text-sm text-slate-700">
                        ¿Seguro que quieres marcar la incidencia <strong>#{incident.id} {incident.title}</strong> como{' '}
                        <strong>{pending?.label}</strong>?
                    </p>
                    <label className="block text-sm">
                        <span className="mb-1 block font-medium text-slate-700">Motivo (opcional)</span>
                        <textarea
                            rows={3}
                            maxLength={500}
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder="Quedará registrado en el historial"
                            className={inputClass}
                        />
                    </label>
                    {error && <p className="text-sm text-red-600">{error}</p>}
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={closeModal} className="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className={`rounded-md px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 ${
                                pending?.value === 'blocked' ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'
                            }`}
                        >
                            {processing ? 'Guardando…' : 'Confirmar'}
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
