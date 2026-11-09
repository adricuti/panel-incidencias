# Uso de IA

## Herramientas
- **Claude Code** (modelo Claude Opus 5.5), en la app de escritorio, con acceso al proyecto local.

## Prompts principales (resumen)
1. Preparar el entorno: comprobar PHP, Composer, Node, MySQL y git.
2. Pasarle el enunciado (PDF) y pedir la implementación con Laravel + MySQL + Inertia/React,
   con comentarios y README en castellano, en tiempo limitado.
3. Decisiones tomadas por mí antes de empezar: frontend con Inertia + React e idioma del código y la documentación.
4. Revisión posterior, mejora por mejora (ver "Revisión y decisiones manuales").

## Partes generadas con ayuda de IA
- Migraciones, modelos, enums, servicio de dominio, controlador y Form Requests.
- Componentes React y páginas Inertia.
- Seeders (datos coherentes y datos inconsistentes) y el comando `incidents:diagnose`.
- Borrador del README.

## Errores detectados durante el proceso y cómo se corrigieron
- **La migración fallaba en MySQL 5.7** ("Specified key was too long; max key length is 1000 bytes"): el
  servidor usaba MyISAM por defecto. Se fuerza `InnoDB` en `config/database.php`, que además es necesario
  para las FKs y las transacciones.
- **Conflicto de dependencias npm:** la última versión de `@vitejs/plugin-react` no es compatible con Vite 6,
  que es el que trae Laravel 12. Se fija `@vitejs/plugin-react@^4`.
- **Mensajes de validación en inglés** pese a `APP_LOCALE=es`: se añade `lang/es/validation.php` con las reglas usadas.

## Verificación realizada
- Transición no permitida (abierta → resuelta) rechazada con un mensaje claro; transición válida registra el log.
- Filtro rápido por estado sin recarga y errores de validación del formulario, comprobados en el navegador.
- `incidents:diagnose` en dry-run, con `--fix` y re-ejecutado (idempotente: la segunda vez no hace nada).

## Revisión y decisiones manuales
<!-- Completar tras la revisión: qué he cambiado, qué he validado y qué decisiones he tomado yo. -->
- Elección de Inertia + React frente a Livewire.
- **Tailwind frente a Bootstrap:** la IA eligió Tailwind (viene con Laravel 12). Lo revisé y decidí mantenerlo
  porque evita CSS global y encaja con componentes pequeños; dejé documentado el motivo en el README.
- Criterio de corrección de inconsistencias: qué se corrige automáticamente y qué se marca para revisión manual.
- **Formulario de alta en un modal.** Tras probar la primera versión, decidí que el formulario fijo en una
  columna lateral restaba espacio al listado. Pedí sustituirlo por un botón que abra un modal y que el
  listado ocupe todo el ancho. Implementado con `<dialog>` nativo.
- **Mensaje de operador confuso.** Al probar los comentarios sin nombre, el error decía "El campo autor es
  obligatorio", pero en pantalla ese campo se llama "Operador" y está en la cabecera. Pedí que el mensaje
  hable de "Operador" y que el campo se resalte en rojo. Se cambió el mensaje en los Form Requests y se
  añadió el resaltado (con foco) desde `ActorContext`, también para los cambios de estado.
- **Confirmación de cambio de estado en modal.** La primera versión usaba `window.prompt()` del navegador para
  confirmar y pedir el motivo. Pedí que fuera un modal centrado como el de alta, por coherencia visual. Se
  reutilizó el componente `Modal`. Al probarlo se detectó que el foco iba al botón de cerrar en lugar de al
  campo de motivo; se corrigió en `Modal` para que enfoque el primer campo (mejora también el modal de alta).
- **Comentarios de código insuficientes.** Al revisar el código consideré pobres los comentarios de varias
  partes (controlador, modelos, migraciones, página de detalle). Pedí completarlos explicando el porqué de cada
  decisión, no lo que hace cada línea. En esa revisión apareció además una duplicación: las etiquetas de las
  acciones del historial estaban escritas a mano en React (`ACTION_LABELS`), repitiendo el enum del backend, que
  es justo lo que el enunciado pide evitar ("strings mágicos dispersos"). Se movieron a
  `IncidentLogAction::label()` y el backend las envía a la página.
- **Cursor en los botones.** Detecté que los botones no mostraban el cursor de "mano", lo que hace menos evidente
  que son clicables. Es un cambio de Tailwind 4 respecto a versiones anteriores. Se añadió una regla base global
  en `app.css` (la solución que recomienda Tailwind) en lugar de añadir `cursor-pointer` botón por botón.
- **Revisión del criterio de prudencia del ejercicio 5.** Revisé el diagnóstico frente al requisito de
  prudencia y detecté dos puntos débiles en la primera versión, que pedí corregir:
  - **Borrado de duplicados sin rastro:** se decía que los logs son un registro de auditoría inmutable, pero el
    diagnóstico borraba copias sin dejar constancia. Ahora deja antes un log con lo borrado, y solo considera
    duplicados las copias exactas en todos los campos (también actor y metadata).
  - **Criterio incoherente entre los casos 1 y 2:** en el caso 1 se confiaba en la tabla (se reconstruía el log)
    y en el 2 no (se marcaba), aunque el 1 es un caso particular del 2. Ahora ambos siguen una regla única:
    se reconstruye solo si el estado actual es una transición válida desde el último registrado
    (`IncidentStatus::canTransitionTo()`); si no, se marca para revisión. El caso simulado 2 se cambió a un
    salto no permitido ("abierta" → "bloqueada") para que ejemplifique la rama de revisión manual.
