import { useEffect, useRef } from 'react';

/**
 * Modal basado en el elemento nativo <dialog>.
 *
 * showModal() ya aporta fondo oscuro (::backdrop), cierre con Esc y foco atrapado
 * dentro del diálogo, sin librerías externas. El estado abierto/cerrado lo controla
 * el componente padre con la prop `open`.
 */
export default function Modal({ open, onClose, title, children }) {
    const ref = useRef(null);

    // Sincroniza la prop `open` con el estado real del <dialog>.
    useEffect(() => {
        const dialog = ref.current;
        if (open && !dialog.open) {
            dialog.showModal();
            // showModal() enfoca el primer elemento enfocable (el botón ✕); es más útil empezar en el primer campo.
            dialog.querySelector('input, textarea, select')?.focus();
        }
        if (!open && dialog.open) dialog.close();
    }, [open]);

    // Cerrar al hacer clic fuera del contenido (sobre el backdrop).
    const handleClick = (e) => {
        if (e.target === ref.current) onClose();
    };

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={handleClick}
            aria-labelledby="modal-title"
            className="m-auto w-full max-w-lg rounded-lg p-0 shadow-xl backdrop:bg-slate-900/50"
        >
            <div className="p-5">
                <div className="mb-4 flex items-center justify-between">
                    <h2 id="modal-title" className="text-lg font-semibold">{title}</h2>
                    <button type="button" onClick={onClose} aria-label="Cerrar" className="rounded p-1 text-slate-500 hover:bg-slate-100">
                        ✕
                    </button>
                </div>
                {/* Solo se monta el contenido cuando está abierto: el formulario empieza limpio cada vez. */}
                {open && children}
            </div>
        </dialog>
    );
}
