import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Layout from '../../Components/Layout';
import StatusCounters from '../../Components/StatusCounters';
import IncidentList from '../../Components/IncidentList';
import CreateIncidentForm from '../../Components/CreateIncidentForm';
import { inputClass } from '../../Components/Field';

/**
 * Pantalla principal.
 *
 * Dónde vive el estado: los filtros "oficiales" están en la URL (props.filters del backend);
 * aquí solo se guarda el texto que se está escribiendo. Al cambiar un filtro se hace una
 * visita Inertia parcial (only: incidents, counts, filters) que refresca la lista sin recargar la página.
 */
export default function Index({ incidents, filters, counts, statuses, priorities }) {
    const [q, setQ] = useState(filters.q ?? '');
    const firstRender = useRef(true);

    const applyFilters = (changes) => {
        const next = { ...filters, ...changes };
        // No enviar filtros vacíos a la URL.
        Object.keys(next).forEach((k) => (next[k] === null || next[k] === '') && delete next[k]);
        router.get('/incidents', next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['incidents', 'counts', 'filters'],
        });
    };

    // Búsqueda de texto con debounce para no lanzar una petición por tecla.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        const t = setTimeout(() => applyFilters({ q }), 350);
        return () => clearTimeout(t);
    }, [q]);

    return (
        <Layout>
            <Head title="Incidencias" />

            <StatusCounters statuses={statuses} counts={counts} active={filters.status ?? null} onSelect={(status) => applyFilters({ status })} />

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <section className="space-y-3 lg:col-span-2">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <input type="search" placeholder="Buscar por título, descripción o persona…" value={q} onChange={(e) => setQ(e.target.value)} className={inputClass} />
                        <select value={filters.status ?? ''} onChange={(e) => applyFilters({ status: e.target.value })} className={`${inputClass} sm:w-44`} aria-label="Filtrar por estado">
                            <option value="">Todos los estados</option>
                            {statuses.map((s) => (
                                <option key={s.value} value={s.value}>{s.label}</option>
                            ))}
                        </select>
                        <select value={filters.priority ?? ''} onChange={(e) => applyFilters({ priority: e.target.value })} className={`${inputClass} sm:w-44`} aria-label="Filtrar por prioridad">
                            <option value="">Todas las prioridades</option>
                            {priorities.map((p) => (
                                <option key={p.value} value={p.value}>{p.label}</option>
                            ))}
                        </select>
                    </div>

                    <IncidentList incidents={incidents.data} statuses={statuses} />

                    {incidents.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {incidents.links.map((l, idx) => (
                                <Link
                                    key={idx}
                                    href={l.url ?? '#'}
                                    preserveScroll
                                    preserveState
                                    className={`rounded px-3 py-1 text-sm ring-1 ring-slate-200 ${l.active ? 'bg-sky-600 text-white' : 'bg-white'} ${!l.url ? 'pointer-events-none opacity-40' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: l.label }}
                                />
                            ))}
                        </nav>
                    )}
                </section>

                <aside>
                    <CreateIncidentForm priorities={priorities} />
                </aside>
            </div>
        </Layout>
    );
}
