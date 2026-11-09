# Panel operativo de incidencias

Aplicación pequeña para registrar, filtrar y operar incidencias con **trazabilidad completa** de cada cambio,
más una herramienta de **diagnóstico de inconsistencias** de datos.

**Stack:** Laravel 12 · MySQL · Inertia.js + React 19 · Tailwind CSS 4 · Vite 6

---

## Requisitos

| Herramienta | Versión |
|---|---|
| PHP | 8.2 o superior (extensiones: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`) |
| Composer | 2.x |
| Node.js | 20 o superior (con npm) |
| MySQL | 5.7 o superior (motor InnoDB) |

## Instalación

```bash
git clone <url-del-repositorio> panel-incidencias
cd panel-incidencias

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Crea la base de datos (por defecto `panel_incidencias`) y ajusta `DB_*` en `.env` si tu usuario/contraseña no son `root` sin contraseña:

```sql
CREATE DATABASE panel_incidencias CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Migraciones y datos de ejemplo

```bash
php artisan migrate:fresh --seed
```

Carga 7 incidencias coherentes (creadas a través del servicio de dominio, con sus logs) y 4 incidencias
marcadas como `[SIM]` con inconsistencias simuladas para el ejercicio de diagnóstico.

## Levantar la aplicación

```bash
npm run build
php artisan serve
```

Abrir <http://127.0.0.1:8000>. Para desarrollo con recarga en caliente: `npm run dev` en otra terminal.

No hay autenticación (fuera de alcance). El **nombre del operador** se escribe en la cabecera y es el que
queda registrado en los logs como actor. Si se intenta cambiar un estado o comentar sin haberlo indicado, el
campo "Operador" se resalta en rojo y recibe el foco.

## Diagnóstico de inconsistencias

```bash
php artisan incidents:diagnose          # dry-run: solo muestra qué encontraría y qué haría
php artisan incidents:diagnose --fix    # aplica las correcciones seguras en una transacción
```

El informe indica, para cada caso, qué se hará y por qué. El comando es idempotente: ejecutarlo varias veces
con `--fix` no duplica correcciones ni marcas; lo ya marcado aparece como "Ya marcada (pendiente de revisión)".

---

## Funcionalidad

- **Listado** con filtros por estado, prioridad y texto libre (título, descripción, solicitante, responsable), paginado.
- **Contador por estado**, que además funciona como **filtro rápido** sin recargar la página.
- **Resaltado** de incidencias bloqueadas (fondo rojo) y vencidas (borde naranja + "vencida").
- **Crear incidencia** desde un botón que abre un **modal**, con validación y errores mostrados junto a cada
  campo (el modal no se cierra hasta que la incidencia se crea correctamente).
- **Cambio de estado** solo con las transiciones permitidas; antes de bloquear o resolver se abre un **modal de
  confirmación** con un motivo opcional, que queda registrado en el historial (`metadata.reason` del log).
- **Detalle** con historial de cambios (logs) y comentarios operativos; añadir comentarios.
- Diseño responsive: tabla en escritorio, tarjetas en móvil.

### Flujo de estados

| Desde | Hacia |
|---|---|
| Abierta | En revisión |
| En revisión | Bloqueada, Resuelta |
| Bloqueada | En revisión (desbloqueo) |
| Resuelta | Abierta (solo como **reapertura**, con acción `reopened` propia en el log) |

Definido en un único sitio: `app/Enums/IncidentStatus.php`. El frontend no duplica la lógica: recibe del
backend las transiciones permitidas y qué estados requieren confirmación.

---

## Decisiones técnicas

- **Estados, prioridades y acciones de log como enums PHP** (`app/Enums`), con *cast* en el modelo. En BD se
  guardan como `string`, no como `ENUM` de MySQL, para poder añadir valores sin alterar la columna. Cada enum
  tiene su `label()`, y el frontend recibe las etiquetas del backend: no hay textos ni valores duplicados en React.
- **Servicio de dominio** (`app/Services/IncidentService.php`): toda escritura pasa por él. Cada acción que
  modifica la incidencia y su log va en `DB::transaction`. El cambio de estado usa `lockForUpdate()` para
  evitar que dos operadores cambien el estado a la vez desde el mismo estado de origen.
- **Validación en dos capas:** los *Form Requests* validan el formato; el dominio valida la coherencia de la
  transición (`InvalidStatusTransition`), que el controlador traduce a un error de validación legible.
- **Logs inmutables:** `incident_logs` solo tiene `created_at`. La FK es `restrictOnDelete` para que la
  trazabilidad no desaparezca en cascada. Los comentarios sí se borran en cascada con la incidencia.
- **Índices:** `(status, priority)` y `priority` para los filtros, `created_at` para el orden del listado,
  `(incident_id, created_at)` en logs y comentarios para el historial y para buscar el último log de estado.
- **Operador sin autenticación.** El enunciado exige registrar el actor de cada cambio, pero la autenticación
  no es obligatoria. Sin login la aplicación no sabe quién la usa, así que el operador escribe su nombre en
  la cabecera y se envía con cada acción (`incident_logs.user_name`, `incident_comments.author_name`).
  - **Limitación:** es texto libre, cualquiera puede escribir cualquier nombre, así que **no es fiable como
    auditoría**. Es aceptable para el alcance de la prueba, no para producción.
  - **Con autenticación** (p. ej. Laravel Breeze con Inertia + React): desaparece el campo "Operador" y el
    actor se toma en el backend del usuario autenticado (`$request->user()`), sin que lo envíe el cliente, por
    lo que no se puede falsificar. En BD, `user_name`, `author_name`, `requester_name` y `assigned_to` pasarían
    a ser `user_id`, `author_id`, `requester_id` y `assigned_to_id`, con clave foránea a `users`.
- **Campo extra `due_date`** (fecha límite, opcional) para poder calcular incidencias vencidas.
- **Frontend:** Inertia + React. El estado de los filtros vive en la URL (props que devuelve el backend); al
  filtrar se hace una visita parcial (`only: [incidents, counts, filters]`) que refresca la lista sin recargar.
  El nombre del operador vive en un contexto React (`ActorContext`) y se recuerda en `localStorage`. El mismo
  contexto expone `requireActor()`: las acciones lo llaman antes de enviar la petición y, si falta el nombre,
  la cabecera resalta el campo sin hacer la llamada. El servidor lo valida igualmente (los Form Requests).
- **Tailwind CSS en lugar de Bootstrap** (el enunciado permite ambos):
  - Laravel 12 ya lo trae instalado y configurado (`@tailwindcss/vite`), sin dependencias extra.
  - Encaja con componentes React pequeños: cada componente lleva sus estilos en las clases y no hay una hoja
    CSS global que crezca sin control. El único CSS propio está en `resources/css/app.css`: la importación de
    Tailwind y una regla base que restaura el cursor de "mano" en los botones (Tailwind 4 lo quita por defecto)
    y muestra "no permitido" en los deshabilitados. Es global para que ningún botón nuevo se quede sin ella.
  - En el build solo se genera el CSS de las clases realmente usadas (las rutas `@source` le indican dónde buscarlas).
  - Responsive con prefijos (`md:`, `sm:`, `lg:`): p. ej. tabla en escritorio (`hidden md:block`) y tarjetas en móvil (`md:hidden`).
- **Componentes pequeños** en `resources/js/Components`; los estilos por estado están centralizados en
  `resources/js/lib/status.js`, para no repetir colores en cada componente.
- **Modal con `<dialog>` nativo** (`Components/Modal.jsx`): `showModal()` ya aporta fondo, cierre con Esc y
  foco atrapado, sin librerías. El estado abierto/cerrado vive en la página (`useState`); el formulario no
  sabe que está en un modal, solo avisa con `onSuccess` / `onCancel`, así que es reutilizable. El mismo
  componente se usa para el alta y para confirmar cambios de estado (en vez de `window.prompt()`, que no se
  puede maquetar ni es accesible). Al abrirse, el foco va al primer campo del formulario.
- **MySQL 5.7:** se fuerza el motor `InnoDB` en `config/database.php` (`DB_ENGINE`) porque algunas
  instalaciones usan MyISAM por defecto, sin FKs ni transacciones.

---

## Inconsistencias simuladas, diagnóstico y corrección

Los datos los crea `database/seeders/InconsistentDataSeeder.php` con `DB::table` (saltándose el servicio,
que es justamente lo que provoca este tipo de problemas en producción: scripts, migraciones antiguas, reintentos).
El diagnóstico está en `app/Services/IncidentConsistencyChecker.php` y se lanza con `incidents:diagnose`.

### Criterio de prudencia

| Caso | ¿Se corrige solo? | Qué se hace |
|---|---|---|
| 1. Resuelta sin log de resolución | Según la regla única | En los datos simulados: **sí**, se reconstruye el log |
| 2. Estado distinto del último log | Según la regla única | En los datos simulados: **no**, se marca para revisión |
| 3a. Log duplicado (copia exacta) | Sí | Se borran las copias y se deja un log con lo borrado |
| 3b. Log incoherente | No | Se marca para revisión |

Principios:

- **No se inventa información.** Nunca se cambia el estado de una incidencia ni se inventa quién o cuándo
  hizo algo. Lo reconstruido se marca como tal (`metadata.reconstructed`, actor `system:diagnose`).
- **La corrección también es trazable.** Cada acción del diagnóstico deja un log en la incidencia afectada,
  visible en su historial, incluida la eliminación de duplicados.
- **Todo en una transacción**, y siempre con `dry-run` previo.

**Regla única para los desajustes de estado (casos 1 y 2).** El caso 1 es en realidad un caso particular del 2
(la tabla dice "resuelta" y el último log dice otra cosa), así que ambos siguen la misma regla, en lugar de
confiar en la tabla en uno y no en el otro:

- Si el estado actual es una **transición válida** desde el último estado registrado
  (`IncidentStatus::canTransitionTo()`, la misma regla que aplica la aplicación), el estado es plausible y
  solo falta el log: se crea un log `status_backfilled` con el paso que falta.
- Si **no es una transición válida** (o no hay ningún log de estado), el estado es imposible según el flujo:
  falta al menos un paso intermedio y no se sabe cuál. Reconstruirlo sería inventar, así que se marca
  (`inconsistency_flagged`) para revisión manual.

En los datos simulados: la incidencia 1 pasó de "en revisión" a "resuelta" (válido → se reconstruye) y la 2 de
"abierta" a "bloqueada" (no permitido → se marca).

> El log reconstruido se crea con la fecha de la corrección, no con la fecha real del cambio, que se desconoce.
> En el historial aparece como "Cambio de estado reconstruido (diagnóstico)", así que no se confunde con un
> cambio real hecho ese día.

Para todas las consultas, el "último log de estado" se toma entre las acciones que representan un estado
(`created`, `status_changed`, `reopened`, `status_backfilled`): un comentario o una marca no cambian el estado.

### 1. Incidencia resuelta sin log de resolución

```sql
SELECT i.id, i.title, i.status
FROM incidents i
WHERE i.status = 'resolved'
  AND NOT EXISTS (
      SELECT 1 FROM incident_logs l
      WHERE l.incident_id = i.id
        AND l.new_value = 'resolved'
        AND l.action IN ('created','status_changed','reopened','status_backfilled')
  );
```

**Por qué funciona:** toda resolución legítima pasa por el servicio y deja un log con `new_value = 'resolved'`;
si no existe ninguno, el estado se cambió por fuera.

**Corrección:** según la regla única. Si el último estado registrado puede pasar a "resuelta" (solo desde
"en revisión"), se reconstruye el log; si no, se marca para revisión.

### 2. Estado distinto del último log de estado

```sql
SELECT i.id, i.status, l.new_value AS last_log_status
FROM incidents i
JOIN incident_logs l ON l.id = (
    SELECT l2.id FROM incident_logs l2
    WHERE l2.incident_id = i.id
      AND l2.action IN ('created','status_changed','reopened','status_backfilled')
    ORDER BY l2.created_at DESC, l2.id DESC
    LIMIT 1
)
WHERE l.new_value <> i.status;
```

**Por qué funciona:** el último log de estado debería describir el estado actual; si difieren, uno de los dos
está mal. Se excluyen las del caso 1 para no reportarlas dos veces.

**Corrección:** según la regla única. En el caso simulado ("abierta" → "bloqueada", transición no permitida)
**no se corrige automáticamente**: no se sabe qué pasó entre medias ni si la verdad es la tabla o el log, y
cambiar el estado podría ocultar un problema real. Se añade un log `inconsistency_flagged` con el motivo,
visible en el historial, y el comando lo sigue listando como "pendiente de revisión" hasta que alguien lo resuelva.

### 3a. Log duplicado

```sql
SELECT incident_id, action, old_value, new_value, user_name, created_at,
       COUNT(*) AS copies, MIN(id) AS keep_id, GROUP_CONCAT(id ORDER BY id) AS ids
FROM incident_logs
GROUP BY incident_id, action, old_value, new_value, user_name, CAST(metadata AS CHAR), created_at
HAVING COUNT(*) > 1;
```

**Por qué funciona:** dos registros idénticos en **todos** los campos salvo el `id` (incluidos actor, metadata
y fecha) son el mismo evento registrado dos veces (doble envío, reintento). Si difieren en algo, no se
consideran duplicados. `metadata` se compara como texto porque es una columna JSON.

**Corrección (automática):** se conserva el de menor `id` y se borran las copias. Como los logs son el registro
de auditoría, el borrado no es silencioso: antes se crea en la incidencia un log `duplicate_logs_removed` con
los ids borrados, el id conservado y los datos originales. No se pierde información y la limpieza queda trazada.

### 3b. Log incoherente

```sql
SELECT id, incident_id, action, old_value, new_value
FROM incident_logs
WHERE action IN ('status_changed','reopened') AND old_value = new_value;
```

**Por qué funciona:** un cambio de estado en el que el estado no cambia no tiene sentido.

**Corrección: NO automática.** Puede ser un error de registro o esconder un cambio real mal guardado; se
marca para revisión, no se borra.

---

## Estructura relevante

```
app/Enums/                                  Estados, prioridades y acciones de log
app/Services/IncidentService.php            Escrituras con transacción + log
app/Services/IncidentConsistencyChecker.php Diagnóstico y corrección
app/Console/Commands/DiagnoseIncidents.php  Comando incidents:diagnose
app/Http/Controllers/IncidentController.php
app/Http/Requests/                          Validación de entrada
database/migrations/                        incidents, incident_logs, incident_comments
database/seeders/                           Datos coherentes + datos inconsistentes simulados
resources/js/Pages/Incidents/               Index (listado) y Show (detalle + historial)
resources/js/Components/                    Componentes de UI
ai-process/                                 Uso de IA en esta prueba
```

## Qué mejoraría con más tiempo

- Tests de feature para transiciones de estado y para el comando de diagnóstico.
- Autenticación real para que el actor de los logs no sea un texto libre (ver "Operador sin autenticación"
  en Decisiones técnicas).
- Una pantalla para resolver las inconsistencias marcadas para revisión manual.
