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
queda registrado en los logs como actor.

## Diagnóstico de inconsistencias

```bash
php artisan incidents:diagnose          # dry-run: solo muestra qué encontraría y qué haría
php artisan incidents:diagnose --fix    # aplica las correcciones seguras en una transacción
```

El comando es idempotente: ejecutarlo varias veces con `--fix` no duplica correcciones ni marcas.

---

## Funcionalidad

- **Listado** con filtros por estado, prioridad y texto libre (título, descripción, solicitante, responsable), paginado.
- **Contador por estado**, que además funciona como **filtro rápido** sin recargar la página.
- **Resaltado** de incidencias bloqueadas (fondo rojo) y vencidas (borde naranja + "vencida").
- **Crear incidencia** con validación y errores mostrados junto a cada campo.
- **Cambio de estado** solo con las transiciones permitidas; **confirmación** (con motivo opcional) antes de bloquear o resolver.
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

- **Estados y prioridades como enums PHP** (`app/Enums`), con *cast* en el modelo. En BD se guardan como
  `string`, no como `ENUM` de MySQL, para poder añadir estados sin alterar la columna.
- **Servicio de dominio** (`app/Services/IncidentService.php`): toda escritura pasa por él. Cada acción que
  modifica la incidencia y su log va en `DB::transaction`. El cambio de estado usa `lockForUpdate()` para
  evitar que dos operadores cambien el estado a la vez desde el mismo estado de origen.
- **Validación en dos capas:** los *Form Requests* validan el formato; el dominio valida la coherencia de la
  transición (`InvalidStatusTransition`), que el controlador traduce a un error de validación legible.
- **Logs inmutables:** `incident_logs` solo tiene `created_at`. La FK es `restrictOnDelete` para que la
  trazabilidad no desaparezca en cascada. Los comentarios sí se borran en cascada con la incidencia.
- **Índices:** `(status, priority)` y `priority` para los filtros, `created_at` para el orden del listado,
  `(incident_id, created_at)` en logs y comentarios para el historial y para buscar el último log de estado.
- **Campo extra `due_date`** (fecha límite, opcional) para poder calcular incidencias vencidas.
- **Frontend:** Inertia + React. El estado de los filtros vive en la URL (props que devuelve el backend); al
  filtrar se hace una visita parcial (`only: [incidents, counts, filters]`) que refresca la lista sin recargar.
  El nombre del operador vive en un contexto React (`ActorContext`) y se recuerda en `localStorage`.
- **Componentes pequeños** en `resources/js/Components`; los estilos por estado están centralizados en
  `resources/js/lib/status.js`.
- **MySQL 5.7:** se fuerza el motor `InnoDB` en `config/database.php` (`DB_ENGINE`) porque algunas
  instalaciones usan MyISAM por defecto, sin FKs ni transacciones.

---

## Inconsistencias simuladas, diagnóstico y corrección

Los datos los crea `database/seeders/InconsistentDataSeeder.php` con `DB::table` (saltándose el servicio,
que es justamente lo que provoca este tipo de problemas en producción: scripts, migraciones antiguas, reintentos).
El diagnóstico está en `app/Services/IncidentConsistencyChecker.php` y se lanza con `incidents:diagnose`.

### 1. Incidencia resuelta sin log de resolución

```sql
SELECT i.id, i.title, i.status
FROM incidents i
WHERE i.status = 'resolved'
  AND NOT EXISTS (
      SELECT 1 FROM incident_logs l
      WHERE l.incident_id = i.id
        AND l.new_value = 'resolved'
        AND l.action IN ('created','status_changed','reopened','resolution_backfilled')
  );
```

**Por qué funciona:** toda resolución legítima pasa por el servicio y deja un log con `new_value = 'resolved'`;
si no existe ninguno, el estado se cambió por fuera.

**Corrección (automática):** se crea un log `resolution_backfilled` con `user_name = system:diagnose` y
`metadata.reconstructed = true`. **No se inventa** quién ni cuándo resolvió: queda explícito que es reconstruido.

### 2. Estado distinto del último log de estado

```sql
SELECT i.id, i.status, l.new_value AS last_log_status
FROM incidents i
JOIN incident_logs l ON l.id = (
    SELECT l2.id FROM incident_logs l2
    WHERE l2.incident_id = i.id
      AND l2.action IN ('created','status_changed','reopened','resolution_backfilled')
    ORDER BY l2.created_at DESC, l2.id DESC
    LIMIT 1
)
WHERE l.new_value <> i.status;
```

**Por qué funciona:** el último log de estado debería describir el estado actual; si difieren, uno de los dos
está mal. Se excluyen las del caso 1 para no reportarlas dos veces.

**Corrección: NO automática.** No hay forma de saber si la verdad es la tabla o el log, y cambiar el estado
podría ocultar un problema real. Se añade un log `inconsistency_flagged` con el detalle, visible en el
historial de la incidencia, para revisión manual. El comando lo sigue listando hasta que alguien lo resuelva.

### 3a. Log duplicado

```sql
SELECT incident_id, action, old_value, new_value, created_at,
       COUNT(*) AS copies, MIN(id) AS keep_id, GROUP_CONCAT(id ORDER BY id) AS ids
FROM incident_logs
GROUP BY incident_id, action, old_value, new_value, created_at
HAVING COUNT(*) > 1;
```

**Por qué funciona:** dos registros idénticos en todo salvo el `id` son el mismo evento registrado dos veces
(doble envío, reintento).

**Corrección (automática):** se conserva el de menor `id` y se borran las copias. Es seguro porque son copias
exactas: no se pierde información.

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
- Autenticación real para que el actor de los logs no sea un texto libre.
- Una pantalla para resolver las inconsistencias marcadas para revisión manual.
