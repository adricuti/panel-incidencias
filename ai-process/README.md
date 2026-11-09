# Uso de IA

## Herramientas
- **Claude Code** (modelo Claude Opus 5.5), en la app de escritorio, con acceso al proyecto local.

## Prompts principales (resumen)
1. Preparar el entorno: comprobar PHP, Composer, Node, MySQL y git.
2. Pasarle el enunciado (PDF) y pedir la implementación con Laravel + MySQL + Inertia/React,
   con comentarios y README en castellano, en tiempo limitado.
3. Decisiones tomadas por mí antes de empezar: frontend con Inertia + React e idioma del código y la documentación.

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
- Criterio de corrección de inconsistencias: qué se corrige automáticamente y qué se marca para revisión manual.
