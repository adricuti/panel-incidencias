// Estilos visuales por estado. La lógica (etiquetas, transiciones, confirmación)
// viene del backend (App\Enums\IncidentStatus); aquí solo vive la presentación.
export const STATUS_STYLES = {
    open: 'bg-sky-100 text-sky-800 ring-sky-300',
    in_review: 'bg-amber-100 text-amber-800 ring-amber-300',
    blocked: 'bg-red-100 text-red-800 ring-red-300',
    resolved: 'bg-emerald-100 text-emerald-800 ring-emerald-300',
};

export const PRIORITY_STYLES = {
    low: 'text-slate-500',
    medium: 'text-slate-700',
    high: 'text-orange-700 font-semibold',
    critical: 'text-red-700 font-bold',
};

/** Busca la definición de un estado en la lista que envía el backend. */
export const findStatus = (statuses, value) => statuses.find((s) => s.value === value);

export const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' }) : '—';
