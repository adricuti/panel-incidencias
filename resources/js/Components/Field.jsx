// Campo de formulario con etiqueta y error de validación debajo.
export default function Field({ label, error, children }) {
    return (
        <label className="block text-sm">
            <span className="mb-1 block font-medium text-slate-700">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

export const inputClass =
    'w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500';
