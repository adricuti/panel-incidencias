import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { ActorProvider, useActor } from './ActorContext';

function ActorInput() {
    const { actor, setActor, missing } = useActor();
    const ref = useRef(null);

    // Cuando una acción lo necesita y está vacío, se lleva el foco (y el scroll) al campo.
    useEffect(() => {
        if (missing) ref.current?.focus();
    }, [missing]);

    return (
        <label className="flex items-center gap-2 text-sm">
            <span className={missing ? 'font-semibold text-red-300' : 'text-slate-300'}>Operador:</span>
            <input
                ref={ref}
                value={actor}
                onChange={(e) => setActor(e.target.value)}
                placeholder="Tu nombre"
                aria-invalid={missing}
                className={`w-40 rounded-md border-0 bg-slate-700 px-2 py-1 text-white placeholder:text-slate-400 focus:outline-none ${
                    missing ? 'ring-2 ring-red-500 focus:ring-red-500' : 'focus:ring-2 focus:ring-sky-400'
                }`}
            />
        </label>
    );
}

function Flash() {
    const { flash } = usePage().props;
    if (!flash?.success) return null;
    return (
        <div role="status" className="mb-4 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
            {flash.success}
        </div>
    );
}

export default function Layout({ children }) {
    return (
        <ActorProvider>
            <header className="bg-slate-800 text-white">
                <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Link href="/incidents" className="text-xl font-bold">
                            Panel operativo de incidencias
                        </Link>
                        <p className="text-sm text-slate-300">
                            Registra, prioriza y sigue el ciclo de vida de las incidencias con trazabilidad completa.
                        </p>
                    </div>
                    <ActorInput />
                </div>
            </header>
            <main className="mx-auto max-w-7xl px-4 py-6">
                <Flash />
                {children}
            </main>
        </ActorProvider>
    );
}
