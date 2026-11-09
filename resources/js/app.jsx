import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

// Punto de entrada del frontend. Inertia monta aquí la página React que indique el controlador
// con Inertia::render('Incidents/Index', ...) -> resources/js/Pages/Incidents/Index.jsx.
createInertiaApp({
    // Título de la pestaña: cada página pone el suyo con <Head title="..."/>.
    title: (title) => (title ? `${title} · Panel de incidencias` : 'Panel de incidencias'),
    // Resuelve el nombre de la página a su fichero. eager: todas las páginas en un solo bundle
    // (son dos; con muchas páginas convendría carga diferida).
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
