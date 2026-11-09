import { createContext, useContext, useEffect, useState } from 'react';

// Sin autenticación (fuera de alcance), el "actor" que se guarda en los logs
// es el nombre que el operador escribe en la cabecera. Se recuerda en localStorage.
const ActorContext = createContext({ actor: '', setActor: () => {} });
const STORAGE_KEY = 'incidents.actor';

export function ActorProvider({ children }) {
    const [actor, setActor] = useState(() => {
        try {
            return localStorage.getItem(STORAGE_KEY) ?? '';
        } catch {
            return '';
        }
    });

    useEffect(() => {
        try {
            localStorage.setItem(STORAGE_KEY, actor);
        } catch {
            // Sin almacenamiento disponible: el nombre vive solo en memoria.
        }
    }, [actor]);

    return <ActorContext.Provider value={{ actor, setActor }}>{children}</ActorContext.Provider>;
}

export const useActor = () => useContext(ActorContext);
