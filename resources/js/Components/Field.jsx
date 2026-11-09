/**
 * Campo de formulario con etiqueta y, si lo hay, el error de validación debajo.
 * Recibe el input como children para servir igual con <input>, <textarea> o <select>.
 */
export default function Field({ label, error, children }) {
    return (
        <label className="block text-sm">
            <span className="mb-1 block font-medium text-slate-700">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

// Estilo común de inputs, para no repetir la misma cadena de clases en cada formulario.
export const inputClass =
    'w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500';
