import { Head, Link, useForm } from '@inertiajs/react';
import Layout from '../../Components/Layout';
import StatusBadge from '../../Components/StatusBadge';
import StatusActions from '../../Components/StatusActions';
import Field, { inputClass } from '../../Components/Field';
import { useActor } from '../../Components/ActorContext';
import { findStatus, formatDate } from '../../lib/status';

const ACTION_LABELS = {
    created: 'Creación',
    status_changed: 'Cambio de estado',
    reopened: 'Reapertura',
    comment_added: 'Comentario',
    resolution_backfilled: 'Resolución reconstruida (diagnóstico)',
    inconsistency_flagged: 'Inconsistencia marcada para revisión',
};

function History({ logs, statuses }) {
    const label = (v) => (v ? findStatus(statuses, v)?.label ?? v : null);
    return (
        <ol className="space-y-3 border-l-2 border-slate-200 pl-4">
            {logs.map((log) => (
                <li key={log.id} className="text-sm">
                    <div className="font-medium">{ACTION_LABELS[log.action] ?? log.action}</div>
                    {(log.old_value || log.new_value) && (
                        <div className="text-slate-600">
                            {label(log.old_value) ?? '—'} → {label(log.new_value) ?? '—'}
                        </div>
                    )}
                    {log.metadata?.reason && <div className="text-slate-600 italic">Motivo: {log.metadata.reason}</div>}
                    {log.metadata?.issue && <div className="text-orange-700">{log.metadata.issue}</div>}
                    <div className="text-xs text-slate-400">{log.user_name} · {formatDate(log.created_at)}</div>
                </li>
            ))}
        </ol>
    );
}

function CommentForm({ incidentId }) {
    const { actor } = useActor();
    const { data, setData, post, processing, errors, reset, transform } = useForm({ body: '' });

    const submit = (e) => {
        e.preventDefault();
        transform((d) => ({ ...d, author_name: actor }));
        post(`/incidents/${incidentId}/comments`, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="space-y-2" noValidate>
            <Field label="Nuevo comentario operativo" error={errors.body || errors.author_name}>
                <textarea rows={3} className={inputClass} value={data.body} onChange={(e) => setData('body', e.target.value)} />
            </Field>
            <button type="submit" disabled={processing} className="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                Añadir comentario
            </button>
        </form>
    );
}

export default function Show({ incident, statuses }) {
    return (
        <Layout>
            <Head title={`#${incident.id} ${incident.title}`} />
            <Link href="/incidents" className="text-sm text-sky-700 hover:underline">← Volver al listado</Link>

            <div className="mt-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold">#{incident.id} {incident.title}</h1>
                        <p className="text-sm text-slate-500">
                            Solicita {incident.requester_name} · Responsable {incident.assigned_to ?? 'sin asignar'} · Prioridad {incident.priority_label}
                            {incident.due_date && ` · Límite ${incident.due_date}`}
                        </p>
                    </div>
                    <StatusBadge status={incident.status} statuses={statuses} />
                </div>
                <p className="mt-3 whitespace-pre-line text-sm">{incident.description}</p>
                <div className="mt-4"><StatusActions incident={incident} statuses={statuses} /></div>
            </div>

            <div className="mt-6 grid gap-6 md:grid-cols-2">
                <section className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <h2 className="mb-3 text-lg font-semibold">Historial de cambios</h2>
                    <History logs={incident.logs} statuses={statuses} />
                </section>

                <section className="space-y-4 rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <h2 className="text-lg font-semibold">Comentarios</h2>
                    <CommentForm incidentId={incident.id} />
                    <ul className="space-y-3">
                        {incident.comments.length === 0 && <li className="text-sm text-slate-500">Sin comentarios.</li>}
                        {incident.comments.map((c) => (
                            <li key={c.id} className="rounded-md bg-slate-50 p-3 text-sm">
                                <p className="whitespace-pre-line">{c.body}</p>
                                <div className="mt-1 text-xs text-slate-400">{c.author_name} · {formatDate(c.created_at)}</div>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </Layout>
    );
}
