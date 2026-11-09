import { createContext, useContext, useEffect, useState } from 'react';

// Sin autenticación (fuera de alcance), el "actor" que se guarda en los logs
// es el nombre que el operador escribe en la cabecera. Se recuerda en localStorage.
const ActorContext = createContext(null);
const STORAGE_KEY = 'incidents.actor';

export const MISSING_ACTOR_MESSAGE = 'Indica tu nombre en el campo "Operador" de la cabecera.';

export function ActorProvider({ children }) {
    const [actor, setActor] = useState(() => {
        try {
            return localStorage.getItem(STORAGE_KEY) ?? '';
        } catch {
            return '';
        }
    });

    // true cuando una acción ha necesitado el operador y estaba vacío:
    // la cabecera lo usa para resaltar el campo en rojo y darle el foco.
    const [missing, setMissing] = useState(false);

    useEffect(() => {
        try {
            localStorage.setItem(STORAGE_KEY, actor);
        } catch {
            // Sin almacenamiento disponible: el nombre vive solo en memoria.
        }
        if (actor.trim()) setMissing(false);
    }, [actor]);

    /**
     * Devuelve el operador si existe. Si está vacío, marca el campo como pendiente
     * y devuelve null para que el llamador no envíe la petición.
     */
    const requireActor = () => {
        if (actor.trim()) return actor.trim();
        setMissing(true);
        return null;
    };

    return <ActorContext.Provider value={{ actor, setActor, missing, requireActor }}>{children}</ActorContext.Provider>;
}

export const useActor = () => useContext(ActorContext);
