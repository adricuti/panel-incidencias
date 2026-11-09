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
