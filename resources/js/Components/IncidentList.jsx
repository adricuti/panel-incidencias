import { Link } from '@inertiajs/react';
import StatusBadge from './StatusBadge';
import StatusActions from './StatusActions';
import { PRIORITY_STYLES, formatDate } from '../lib/status';

// Resaltado: bloqueadas con fondo rojo suave, vencidas con borde lateral naranja.
// El borde transparente en las demás mantiene todas las filas alineadas.
const rowHighlight = (i) =>
    [i.status === 'blocked' ? 'bg-red-50' : '', i.is_overdue ? 'border-l-4 border-l-orange-500' : 'border-l-4 border-l-transparent'].join(' ');

/** Fecha límite; si está vencida (is_overdue, calculado en el backend) se marca en naranja. */
function DueDate({ incident }) {
    if (!incident.due_date) return <span className="text-slate-400">—</span>;
    return (
        <span className={incident.is_overdue ? 'font-semibold text-orange-700' : ''}>
            {incident.due_date}
            {incident.is_overdue && ' (vencida)'}
        </span>
    );
}

/**
 * Listado de incidencias de la página actual.
 *
 * Se pintan dos versiones con los mismos datos y Tailwind muestra solo una según el ancho:
 * tabla en escritorio (md y superior) y tarjetas en móvil, donde una tabla de 7 columnas no cabe.
 */
export default function IncidentList({ incidents, statuses }) {
    if (incidents.length === 0) {
        return <p className="rounded-lg bg-white p-6 text-center text-slate-500 ring-1 ring-slate-200">No hay incidencias con estos filtros.</p>;
    }

    return (
        <>
            {/* Escritorio: tabla */}
            <div className="hidden overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200 md:block">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-3 py-2">#</th>
                            <th className="px-3 py-2">Incidencia</th>
                            <th className="px-3 py-2">Estado</th>
                            <th className="px-3 py-2">Prioridad</th>
                            <th className="px-3 py-2">Responsable</th>
                            <th className="px-3 py-2">Creada / Límite</th>
                            <th className="px-3 py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {incidents.map((i) => (
                            <tr key={i.id} className={rowHighlight(i)}>
                                <td className="px-3 py-2 text-slate-500">{i.id}</td>
                                <td className="px-3 py-2">
                                    <Link href={`/incidents/${i.id}`} className="font-medium text-sky-700 hover:underline">{i.title}</Link>
                                    <div className="text-xs text-slate-500">Solicita: {i.requester_name}</div>
                                </td>
                                <td className="px-3 py-2"><StatusBadge status={i.status} statuses={statuses} /></td>
                                <td className={`px-3 py-2 ${PRIORITY_STYLES[i.priority]}`}>{i.priority_label}</td>
                                <td className="px-3 py-2">{i.assigned_to ?? <span className="text-slate-400">Sin asignar</span>}</td>
                                <td className="px-3 py-2 text-xs">
                                    <div>{formatDate(i.created_at)}</div>
                                    <DueDate incident={i} />
                                </td>
                                <td className="px-3 py-2">
                                    <StatusActions incident={i} statuses={statuses} compact />
                                    <Link href={`/incidents/${i.id}`} className="mt-1 inline-block text-xs text-sky-700 hover:underline">Historial</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Móvil: tarjetas */}
            <ul className="space-y-3 md:hidden">
                {incidents.map((i) => (
                    <li key={i.id} className={`rounded-lg bg-white p-3 shadow-sm ring-1 ring-slate-200 ${rowHighlight(i)}`}>
                        <div className="flex items-start justify-between gap-2">
                            <Link href={`/incidents/${i.id}`} className="font-medium text-sky-700">#{i.id} {i.title}</Link>
                            <StatusBadge status={i.status} statuses={statuses} />
                        </div>
                        <div className="mt-1 text-xs text-slate-600">
                            <span className={PRIORITY_STYLES[i.priority]}>{i.priority_label}</span> · {i.assigned_to ?? 'Sin asignar'} · <DueDate incident={i} />
                        </div>
                        <div className="mt-2"><StatusActions incident={i} statuses={statuses} compact /></div>
                    </li>
                ))}
            </ul>
        </>
    );
}
