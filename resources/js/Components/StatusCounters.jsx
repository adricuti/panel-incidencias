import { STATUS_STYLES } from '../lib/status';

/**
 * Contador por estado. Cada tarjeta actúa también como filtro rápido:
 * llama a onSelect y el padre hace una visita Inertia parcial (sin recargar la página).
 */
export default function StatusCounters({ statuses, counts, active, onSelect }) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {statuses.map((s) => {
                const isActive = active === s.value;
                return (
                    <button
                        key={s.value}
                        type="button"
                        onClick={() => onSelect(isActive ? null : s.value)}
                        aria-pressed={isActive}
                        className={`rounded-lg p-3 text-left ring-1 ring-inset transition ${STATUS_STYLES[s.value]} ${
                            isActive ? 'ring-2 shadow-md' : 'opacity-80 hover:opacity-100'
                        }`}
                    >
                        <div className="text-2xl font-bold">{counts[s.value] ?? 0}</div>
                        <div className="text-sm">{s.label}</div>
                    </button>
                );
            })}
        </div>
    );
}
