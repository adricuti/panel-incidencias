import { useForm } from '@inertiajs/react';
import Field, { inputClass } from './Field';

/**
 * Formulario de alta. No sabe que vive dentro de un modal: avisa con onSuccess / onCancel
 * y es el padre quien decide qué hacer (cerrar el modal).
 */
export default function CreateIncidentForm({ priorities, onSuccess, onCancel }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        priority: 'medium',
        requester_name: '',
        assigned_to: '',
        due_date: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/incidents', { preserveScroll: true, onSuccess });
    };

    return (
        <form onSubmit={submit} className="space-y-3" noValidate>
            <Field label="Título" error={errors.title}>
                <input className={inputClass} value={data.title} onChange={(e) => setData('title', e.target.value)} />
            </Field>

            <Field label="Descripción" error={errors.description}>
                <textarea rows={3} className={inputClass} value={data.description} onChange={(e) => setData('description', e.target.value)} />
            </Field>

            <div className="grid grid-cols-2 gap-3">
                <Field label="Prioridad" error={errors.priority}>
                    <select className={inputClass} value={data.priority} onChange={(e) => setData('priority', e.target.value)}>
                        {priorities.map((p) => (
                            <option key={p.value} value={p.value}>{p.label}</option>
                        ))}
                    </select>
                </Field>
                <Field label="Fecha límite" error={errors.due_date}>
                    <input type="date" className={inputClass} value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                </Field>
            </div>

            <Field label="Solicitante" error={errors.requester_name}>
                <input className={inputClass} value={data.requester_name} onChange={(e) => setData('requester_name', e.target.value)} />
            </Field>

            <Field label="Responsable (opcional)" error={errors.assigned_to}>
                <input className={inputClass} value={data.assigned_to} onChange={(e) => setData('assigned_to', e.target.value)} />
            </Field>

            <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={onCancel} className="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">
                    Cancelar
                </button>
                <button type="submit" disabled={processing} className="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                    {processing ? 'Guardando…' : 'Crear incidencia'}
                </button>
            </div>
        </form>
    );
}
