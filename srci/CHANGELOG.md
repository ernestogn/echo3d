# Changelog — SRCI

Sistema de Reporte Ciudadano de Incidencias (`srci/`).
Cronología de cambios funcionales del sistema. Las fechas son las de producción.

---

## v2026-11-04 — Auditoría: filtros y registro de accesos

`admin/auditoria.php` ahora permite encontrar un registro sin recorrer la tabla
completa, y el sistema registra los ingresos con PIN (antes solo quedaba
registrado el magic link).

### Qué se agregó

- **Filtros combinables (GET):** usuario (`usuario_id`), tipo de incidencia
  (`tipo_id`), acción (`accion`) y búsqueda libre (`q`) sobre detalle, usuario y
  acción. La paginación conserva los filtros.
- **Vista "Inicios de sesión":** botón `🔐 Ver inicios de sesión`
  (`?ver=logins`) que muestra solo `login` (PIN) y `login_link` (magic link),
  con la misma búsqueda y filtro por usuario. Botón para volver a toda la
  auditoría.
- **Registro del login exitoso:** `login.php` audita `login` con el usuario y
  la IP; `acceso.php` agrega la IP al detalle de `login_link`. Los intentos
  fallidos y los bloqueos ya se auditaban.
- **Etiquetas nuevas:** `login`, `login_link`, `magic_link`, `sync_planilla`,
  `recalcular_barrios`, `activar`, `desactivar`, `nuevo_pin` y
  `toggle_visibilidad` en `accion_legible()` / `clase_accion()`.
- La columna "Incidencia" muestra también el tipo (icono + nombre) para
  verificar el filtro por tipo.

### Verificación

- `php -l` en el VPS sin errores en `admin/auditoria.php`, `login.php` y
  `acceso.php`.
- Smoke test **read-only** contra la base de producción: filtros combinados,
  búsqueda con `%`/`_` escapados y vista de logins; 319 registros totales y 125
  de acceso histórico (magic link), sin errores SQL.

---

## v2026-11-03 — Módulo de Reportes y Análisis Territorial

Nuevo módulo **solo para administradores** que aprovecha los datos ya existentes
(no crea tablas ni migraciones) para producir reportes operativos y ejecutivos:
generador con filtros combinables, presets de un clic, dossier ejecutivo A4,
hoja de cuadrilla A4 en blanco y negro, exportación CSV para Excel y GeoJSON
para QGIS. Todo es **GET server-side**, sin AJAX, sin librerías externas y con
los mismos patrones de seguridad del resto del sistema.

### Qué se agregó

| Pieza | Archivo | Detalle |
|---|---|---|
| Motor del módulo | `includes/funciones_reportes.php` | Constantes, presets, normalización de filtros, constructor de WHERE, KPIs, distribuciones, listado, CSV/GeoJSON, URLs de salida y auditoría. |
| Generador | `admin/reportes_generador.php` | Formulario GET con todos los filtros, 5 presets, 8 KPI cards, distribuciones en barras CSS y preview de 50 casos. |
| Dossier ejecutivo | `admin/imprimir_dossier.php` | A4 color: membrete, indicadores, distribuciones, tabla detallada, firmas y foja. Tope 300 casos. |
| Hoja de cuadrilla | `admin/imprimir_cuadrilla.php` | A4 B&W: agrupada por barrio, ordenada por gravedad, casillas para tildar, observaciones y firmas. Tope 300 casos. |
| Exportación | `admin/exportar_reporte.php` | CSV (BOM UTF-8 + separador `;`) y GeoJSON WGS84. Tope 5000 casos. |

### Filtros disponibles

Fechas (`fecha_desde`, `fecha_hasta`), barrios (múltiple), tipo de barrio
(formal/informal/asentamiento), estados, gravedades, días abiertos mínimos,
familias afectadas > 0, calle intransitable, área responsable, con foto,
con seguimiento, categorías de incidencia (múltiple), tipos de problema
(múltiple), búsqueda libre en dirección/notas/contacto, incluir pendientes de
aprobación y orden/dirección. Todos se combinan con AND.

### Presets (1 clic, `?preset=pN`)

| Clave | Título | Filtros | Casos al 2026-11-03 |
|---|---|---|---|
| p1 | Emergencias climáticas activas | categoría `climaticos`, gravedad alta/crítica, abiertos | 20 |
| p2 | Casos estancados (15+ días) | estado pendiente, `dias_min = 15` | 24 |
| p3 | Plan de mantenimiento vial | tipo `alcantarilla`, abiertos | 7 |
| p4 | Alumbrado y redes eléctricas | tipos `luminariarota` + `postecaido`, abiertos | 15 |
| p5 | Balance mensual de gestión | fechas del mes en curso, todos los estados | 0 (sin datos de noviembre) |

Los presets usan **claves semánticas** resueltas a ids en runtime, nunca ids
fijos: funcionan igual en desarrollo y producción. La resolución de claves es
case-insensitive porque el catálogo histórico mezcla formatos (`LuminariaRota`,
`PosteCaido`).

### Decisiones técnicas y de seguridad

- **Categorías con `EXISTS`, nunca JOIN**: el pivote `incidencia_categorias` es
  N:M; un JOIN multiplicaría filas y rompería `COUNT`/`SUM` de los KPIs.
- **Un placeholder por valor** (`:estado_0`, `:gravedad_1`, `:categoria_0`,
  `:texto_1..3`): nunca se reutiliza el mismo named param (evita `HY093` con
  `EMULATE_PREPARES = false`).
- **"Días abiertos"** = `DATEDIFF(COALESCE(i.fecha_resolucion, NOW()), i.fecha_hora)`,
  la misma regla que la planilla y el listado público.
- **`ORDER BY` por lista blanca**: `fecha, barrio, gravedad, estado, dias,
  familias, id, cuadrilla`; nunca se concatena entrada del usuario.
- **Texto libre** escapa `\`, `%` y `_` y usa `ESCAPE '\\'` en los tres `LIKE`.
- **Límites**: preview 50, impresión 300, exportación 5000
  (`REPORTES_LIMITE_PREVIEW/IMPRESION/EXPORTACION`).
- **CSV**: BOM UTF-8 + `;` (Excel en español); 19 columnas con fecha y hora
  separadas, categorías concatenadas y URL de foto. Las celdas de texto se
  neutralizan contra inyección de fórmulas (apóstrofo si empiezan con `=`, `+`,
  `-` o `@`), porque dirección y notas las escribe el ciudadano.
- **GeoJSON**: descarta filas sin latitud/longitud (siguen en el CSV) para no
  romper QGIS.
- **Fechas**: el preset de mes usa la zona `America/Argentina/Buenos_Aires` y
  los documentos imprimen la hora local de MySQL (`DATE_FORMAT(NOW())`), porque
  PHP puede correr en UTC y el documento mostraría 3 horas de más.
- **Auditoría**: `generar_reporte` (cuadrilla/dossier) y `exportar_reporte`
  (CSV/GeoJSON) con tipo de salida, cantidad de casos y descripción de filtros.
  Mapeadas en `admin/auditoria.php` como "Generó reporte" / "Exportó datos".

### Navegación

- Link `📊 Generador` en el sidebar de `reportes.php`, `usuarios.php`,
  `tipos.php`, `categorias.php`, `barrios.php`, `barrio.php` y `auditoria.php`.
- Botón "📊 Generador de reportes y cuadrillas" en el encabezado de
  `admin/reportes.php`.
- En `auditoria.php` se corrigió el ítem activo del sidebar (marcaba Reportes
  en lugar de Auditoría).

### Verificación (2026-11-03)

- `php -l` en el VPS con `/usr/local/lsws/lsphp83/bin/php`: **sin errores** en
  los 5 archivos nuevos y en los 7 archivos admin modificados.
- Smoke test **read-only** contra la base de producción (copia temporal en
  `/tmp/srci_lint`, borrada después): 5 presets + 20 combinaciones de filtros +
  órdenes + CSV/GeoJSON + URLs, sin errores SQL ni de tipos.
- Datos al momento de la prueba: 61 incidencias (39 visibles/aprobadas, 22
  ocultas), 0 pendientes de aprobación; CSV de 38 filas con 19 columnas
  correctas; GeoJSON de 25 features con coordenadas `[lng, lat]`.
- **Pendiente:** subir los archivos al VPS y probar las 4 salidas en el
  navegador con sesión admin. No requiere migración de base de datos.

### Desvíos respecto del plan original

- **p3** ya no exige `calle_intransitable`: en los datos reales no había casos
  con esa combinación y el preset quedaba vacío. El filtro sigue disponible a
  mano en el formulario.
- **p4** usa los tipos `luminariarota` y `postecaido` en lugar de la categoría
  `luminarias`, porque el backfill de categorías solo cargó `climaticos`; la
  categoría dejaba el preset en 0.

---

## v2026-11-01 — Notificaciones a administradores + digest diario

Nueva funcionalidad pedida por el cliente: que **cada admin reciba un mail por cada
incidencia creada** (lo haya creado quien la haya creado), con la posibilidad de
**desactivarlo desde su perfil**, más un **reporte diario tipo digest a las 08:00**
enviado por cron del VPS.

### Qué se agregó

| Pieza | Dónde | Detalle |
|---|---|---|
| Preferencias por admin | `usuarios.notifica_incidentes_admin`, `usuarios.notifica_digest_admin` (TINYINT, default 1) | Dos banderas independientes: mail por incidente y digest diario. |
| Migración | `sql/migracion_notificaciones_admin.sql` | Idempotente, aplicada en producción. |
| Helpers | `includes/funciones_notificaciones_admin.php` | `obtener_admins_con_notificacion_incidente()`, `obtener_admins_con_notificacion_digest()`, `notificar_administradores_nuevo_incidente()`, `enviar_digest_diario_admin()` y los armadores de cada mail. |
| Disparo por incidente | `api/reportes.php` (POST) | Al crear la incidencia se notifica a todos los admins activos con la bandera prendida. |
| Toggle en el perfil | `perfil.php` | Tarjeta "🔔 Notificaciones de administrador" (solo visible para admins) con los dos checkboxes. |
| Digest por cron | `bin/daily_digest.php` + crontab del VPS | `0 8 * * *` todos los días, con `/usr/local/lsws/lsphp83/bin/php` y log en `/var/log/srci-digest.log`. |

### Reglas acordadas

- Cada admin recibe **su propio mail** por incidente (no un mail conjunto a varios).
- Llega **independientemente de quién** creó el reporte.
- **Desactivable individualmente** desde el perfil, para el mail por incidente y para el digest, por separado.
- El digest se envía **todos los días a las 08:00** vía cron del VPS.
- **Nunca se manda un digest vacío**: si no hay incidencias nuevas en 24 h no sale ningún correo (verificación explícita antes de enviar).
- Los mails usan el mismo estilo y el mismo `mail()` con envelope `-f` que el PIN y las notificaciones al creador, así que no agregan dependencias ni riesgo de spam.
- Si el envío falla o no hay destinatarios ruteables, el flujo principal (crear el reporte) **no se interrumpe**.

### Verificación (en producción, usuario Ernesto = id 11)

- Migración aplicada: columnas creadas con default `1`.
- Mail por incidente enviado a los 3 admins activos (`ernestogn`, `garay`, `marclay`) por la última incidencia publicada (#88): `notificar_administradores_nuevo_incidente()` => `true`.
- Toggle: al apagar `notifica_incidentes_admin` de Ernesto, sale de la lista de destinatarios; al volver a encenderlo, vuelve a entrar. El digest apagado lo excluye igual.
- Digest real (ventana 24 h): con 0 incidencias nuevas no se envió nada (comportamiento esperado). Con ventana de prueba de 30 días (38 incidencias) se envió a los 3 admins: el armado y el envío funcionan.
- El script del cron corre con el binario de LiteSpeed (`lsphp83`, PHP 8.3.36) y sale con código 0.
- `php -l` sin errores en los tres archivos modificados/nuevos.
- Bug encontrado y corregido durante la prueba: `esc()` recibía un `int` (`inc['id']`) y rompía con `strict_types=1`; ahora se castea a string en todas las filas del digest.
- Backups: crontab respaldado en `/root/cron.backup.srci.txt` (chmod 600, contiene rutas sensibles).

---

## v2026-10-30 — Reglas de la red de colaboradores (6 decisiones del cliente)

Se definieron seis reglas de negocio para la red de colaboradores y se aplicaron en el código.

| Decisión | Comportamiento nuevo |
|---|---|
| **1. Sigue en modo admin-only** | Sin cambios: la red la gestionan solo los administradores (`SRCI_COLABORADORES_SOLO_ADMIN = true`). |
| **2. Una sola red por usuario** | Un usuario pertenece como máximo a **una** red. `colaboradores.colaborador_id` tiene índice UNIQUE `uq_colaborador`; si ya estaba en otra red, el alta se rechaza con un mensaje que lo explica. |
| **3. Sin redes anidadas** | Un administrador no puede ser colaborador de otra red (ni colaborarse a sí mismo). |
| **4. El rechazo se ve** | El reporte rechazado no se borra ni se oculta para su autor: queda con `rechazado = 1` y `motivo_rechazo`. El autor lo ve en "Mis reportes" (`perfil.php`) y en `detalle.php`, con el badge **✖ Rechazado** y el motivo. Para el resto de los usuarios sigue oculto. |
| **5. Aviso al administrador** | Cuando entra un reporte pendiente de aprobación, se manda un email al **propietario de la red** con el resumen del reporte y el link al panel de pendientes. |
| **6. Cerrado el gap de edición** | Antes **cualquier usuario logueado** podía editar **cualquier** incidencia. Ahora solo el **autor del reporte** o un **admin** pueden editarla (el resto recibe 403); los pendientes ajenos siguen dando 404. |

### Cambios técnicos

- **Migraciones nuevas** (aplicadas en producción, con backup previo):
  - `sql/migracion_colaboradores_red_unica.sql`: normaliza colaboradores duplicados (conserva el vínculo más reciente), crea el UNIQUE `uq_colaborador` y dropea el índice `idx_colaborador` viejo.
  - `sql/migracion_rechazo_visible.sql`: `incidencias.rechazado` (TINYINT default 0), `rechazado_en`, `rechazado_por` (FK `fk_inc_rechaz_por`, SET NULL) y `motivo_rechazo` (VARCHAR 500), con su índice.
- `api/aprobar_incidencia.php`: el rechazo **exige un motivo** (máx. 500 caracteres; si falta, responde 400 y no toca el reporte) y guarda `rechazado/rechazado_en/rechazado_por/motivo_rechazo`; al aprobar se limpia ese estado. La UI (`perfil.php` y `detalle.php`) pide el motivo con un `prompt` y no envía el rechazo si queda vacío. La auditoría y el email al creador incluyen el motivo.
- `includes/funciones.php`: `agregar_colaborador()` rechaza alta en una segunda red y a los admins como colaboradores; nuevo helper `notificar_propietario_por_email()`.
- `api/reportes.php` (POST): avisa por email al propietario de la red cuando el reporte nace pendiente.
- `perfil.php`: "Mis reportes" incluye los rechazados (`i.oculto = 0 OR i.rechazado = 1`) con badge y motivo; el prompt de motivo en el rechazo.
- `detalle.php`: badge "✖ Rechazado", bloque con el motivo y la fecha del rechazo; botones de edición solo para autor/admin.
- `incidencias.php` y `detalle.php`: los botones "✏️ Editar" solo se muestran al autor o al admin.
- `detalle.php`, `incidencias.php` y `admin/reportes.php`: al **restaurar** un reporte se borra el estado de rechazo (vuelve a quedar pendiente de aprobación).
- `editar.php`: 403 para quien no es autor ni admin; 404 para pendientes ajenos.
- CSS: nueva clase `.badge-rechazado`.

### Verificación

- Lint PHP de los 10 archivos modificados: sin errores.
- Test funcional en base de datos (transacción + rollback): **14/14** — alta en una segunda red rechazada, admin no colaborable, motivo guardado, autor ve el rechazo y otro usuario no, restauración limpia el estado, aprobar limpia el rechazo.
- E2E contra el sitio real (`https://echo3dlaser.com.ar/srci`, usuarios de prueba `.local` para no enviar emails reales, espejo de Sheets desactivado y restaurado): **30/30** — una sola red por usuario, admin no colaborable, rechazo con motivo y sin motivo, el autor ve el badge y el motivo en el perfil y en el detalle, para el resto sigue 404 y fuera del mapa, editar (200 autor / 200 admin / 404 pendiente ajeno / 403 publicado ajeno), restaurar limpia el rechazo, aprobar publica, auditoría con el motivo, la gestión sigue siendo solo de admins. Sin residuos al terminar (0 reportes, 0 usuarios y 0 colaboradores huérfanos).
- Email al propietario: verificado con un `sendmail` simulado sobre una base clonada (**no salió ningún correo real**): 1 solo mensaje, al propietario de la red, con el resumen del reporte (tipo, dirección, barrio, gravedad, autor, fecha) y los links al panel de pendientes y al reporte; al colaborador con email no ruteable no se le escribe.
- Despliegue en producción: commit `ed50b97` + `aaa6c92` (push a `main`, deploy automático OK).

---

## v2026-10-29 — Red de colaboradores con aprobación de reportes (modo admin-only)

Primera implementación de la funcionalidad de **colaboradores**: un usuario (por ahora,
solo administradores) puede designar colaboradores que cargan reportes a través suyo.
Cada reporte de un colaborador **nace pendiente de aprobación** y no se publica hasta
que quien administra la red lo aprueba.

### Reglas de negocio

| Regla | Comportamiento |
|---|---|
| Vínculo colaborador → propietario | Se crea **ya aprobado** (lo crea el propietario de la red o un admin). No requiere aprobación de la vinculación. |
| Aprobación | Lo que se aprueba es **cada reporte** del colaborador: nace con `aprobado = 0` y `propietario_id = <propietario de su grupo>`. |
| Reporte de usuario no colaborador | Se publica de inmediato (`aprobado = 1`), como antes. |
| Aprobar | `aprobado = 1`, `aprobado_en = NOW()`, `aprobado_por = <quien aprueba>` → pasa a la lista y al mapa. |
| Rechazar | `oculto = 1` (soft delete). **Nunca se borra físicamente**: la incidencia queda registrada. |
| Baja de un colaborador | El vínculo pasa a `estado = 'rechazado'` (no se borra: queda trazado). |
| Alcance | El colaborador **no** recibe rol admin: solo reporta. |

### Modo actual: solo administradores

La funcionalidad está **restringida a los admins** hasta que el cliente decida abrirla.
Se controla con un flag, sin tocar el código:

```php
// includes/funciones.php
define('SRCI_COLABORADORES_SOLO_ADMIN', true); // true = solo admins | false = todos
```

Con el flag en `true`:
- Solo un admin puede **designar / quitar colaboradores** y **aprobar / rechazar** reportes.
- Los usuarios normales no ven las tarjetas de gestión (la UI se oculta en el servidor, no solo con CSS).
- Un usuario designado como colaborador carga reportes que quedan pendientes hasta que un admin los aprueba.

Con el flag en `false` se abre la red a todos los usuarios (cada uno con su propio grupo)
y queda activa la matriz de visibilidad por grupo descrita abajo.

### Matriz de visibilidad (filtro siempre en servidor)

| Quién | Qué ve |
|---|---|
| **Admin** | Todas las incidencias, incluidas las **pendientes** de aprobación. |
| **Usuario de primer nivel** (sin grupo) | Todas las incidencias **aprobadas**. |
| **Propietario** (tiene colaboradores) | Las aprobadas + las **pendientes de su grupo** (para revisar/aprobar). |
| **Colaborador** | En el modo actual: las aprobadas + sus **propias pendientes** (para seguir su reporte antes de publicarse). La restricción "solo su grupo" queda para cuando se abra la función a todos. |

### Publicación

Los reportes pendientes **no se publican**:
- **Mapa** (`api/reportes.php` GET): solo devuelve `aprobado = 1`.
- **Exportaciones** CSV / GeoJSON (`incidencias.php`): solo `aprobado = 1`.
- Se ven únicamente en el listado del admin, en el panel de aprobación y en el detalle (con badge).

### Modelo de datos

**Tabla nueva `colaboradores`** (`srci/sql/migracion_colaboradores.sql`):

| Columna | Tipo | Notas |
|---|---|---|
| `id` | INT AUTO_INCREMENT | PK |
| `propietario_id` | INT | FK `usuarios.id` ON DELETE CASCADE |
| `colaborador_id` | INT | FK `usuarios.id` ON DELETE CASCADE |
| `estado` | ENUM(`aprobado`,`rechazado`) | default `aprobado` |
| `creado_en` | DATETIME | default CURRENT_TIMESTAMP |
| `creado_por` | INT NULL | FK `usuarios.id` ON DELETE SET NULL |

Índices: UNIQUE (`propietario_id`,`colaborador_id`), `idx_colaborador`, `idx_estado`, `idx_creado_por`.

**Columnas nuevas en `incidencias`** (`srci/sql/migracion_aprobacion_incidencias.sql`):

| Columna | Tipo | Notas |
|---|---|---|
| `aprobado` | TINYINT(1) UNSIGNED | NOT NULL, default `1` |
| `aprobado_en` | DATETIME NULL | cuándo se aprobó |
| `aprobado_por` | INT NULL | FK `usuarios.id` ON DELETE SET NULL |
| `propietario_id` | INT NULL | FK `usuarios.id` ON DELETE SET NULL (dueño del reporte) |

Índices: `idx_incidencias_aprobado`, `idx_incidencias_propietario`.

**Backfill** (`srci/sql/migracion_backfill_aprobado.sql`, ejecutar **una sola vez**):
las 57 incidencias existentes quedaron con `aprobado = 1` y `propietario_id = usuario_id`.

> **Ojo con los tipos:** el esquema real usa `usuarios.id` = `INT(11)` **con signo**, por lo que
> las columnas y FKs nuevas son `INT` con signo (una FK `INT UNSIGNED` contra un `INT` con signo
> es inválida en MariaDB). Las FKs se crean con SQL dinámico porque MariaDB no acepta
> `ADD CONSTRAINT IF NOT EXISTS`.

### API nueva

`POST /srci/api/aprobar_incidencia.php`

| | |
|---|---|
| **Auth** | Sesión activa + quien puede aprobar (modo actual: solo admins) |
| **Body** | JSON `{incidencia_id: int, accion: "aprobar"\|"rechazar"}` |
| **CSRF** | Token en el header `X-CSRF-Token` (o `csrf_token` en el body) — helper `csrf_token_valido()` en `includes/auth.php` |
| **Respuesta** | `{ok: true, accion: string, id: int}` \| `{error: string}` |
| **Errores** | 403 (sin permiso / token inválido) · 400 (datos o acción inválida) · 404 (no existe) |

Además avisa al creador por email y registra la acción en la auditoría.

### Interfaz

- **`perfil.php`** — tarjetas *👥 Mis colaboradores* (alta por email, baja con confirmación) y
  *⏳ Reportes pendientes de aprobación* (botones Aprobar / Rechazar). Visibles solo para quien
  puede gestionar la red.
- **`detalle.php`** — badge *⏳ Pendiente de aprobación* y botones Aprobar / Rechazar (admins).
  Un pendiente ajeno devuelve 404.
- **`incidencias.php`** y **`admin/reportes.php`** — badge *⏳ Pendiente* en la columna Estado.
- **`editar.php`** — un pendiente ajeno devuelve 404.
- **`assets/css/estilos.css`** — nueva clase `.badge-aprobacion`.

### Auditoría y emails

| Acción de auditoría | Cuándo |
|---|---|
| `agregar_colaborador` | Alta o reactivación de un vínculo |
| `quitar_colaborador` | Baja de un vínculo |
| `aprobar` | Publicación de un reporte pendiente |
| `rechazar` | Rechazo de un reporte pendiente |

Notificaciones al creador (`notificar_creador_por_email()`): eventos nuevos `aprobar` y
`rechazar`; el evento `crear` ahora avisa cuando el reporte queda pendiente de aprobación.
Las nuevas acciones están mapeadas en `admin/auditoria.php`.

### Archivos involved

**Nuevos**
- `api/aprobar_incidencia.php`
- `sql/migracion_colaboradores.sql`
- `sql/migracion_aprobacion_incidencias.sql`
- `sql/migracion_backfill_aprobado.sql`

**Modificados**
- `includes/funciones.php` (helpers de la red + eventos de email)
- `includes/auth.php` (`csrf_token_valido()`)
- `api/reportes.php` (regla de creación + puerta de publicación)
- `perfil.php`, `incidencias.php`, `detalle.php`, `editar.php` (UI, visibilidad, guardas)
- `admin/reportes.php`, `admin/auditoria.php`
- `assets/css/estilos.css`
- `AGENTS.md` (documentación técnica del sistema)

### Despliegue

- Migraciones aplicadas en producción (DB `echo_srci`, MariaDB 10.11) **después de un backup**:
  `/home/backup/echo_srci_pre_colaboradores_2026-10-29_210340.sql`.
- Código desplegado por el mecanismo habitual: commit + push a `main` → webhook → cron →
  `git reset --hard origin/main` + `rsync` a `/home/echo3dlaser.com.ar/public_html/srci/`.
- Commits: `0e6ceb2` (migraciones + documentación) y `4949c17` (implementación).

### Verificación

- Lint `php -l` sin errores en los 10 archivos PHP modificados.
- Test funcional de la lógica de la red en el VPS: **22/22 checks** (transacción con *rollback*).
- Test end-to-end por HTTP contra el sitio real: **20/20 checks** (login, alta de colaborador,
  reporte pendiente, no publicación en el mapa, panel del admin, rechazo sin token CSRF → 403,
  aprobación, aparición en el feed, auditoría, rechazo con soft delete, baja de colaborador).
- Sin residuos: 0 reportes, 0 usuarios y 0 vínculos de prueba; auditoría huérfana depurada.

### Decisiones abiertas

- Cuándo abrir la red a todos los usuarios (cambiar el flag) y activar la matriz de visibilidad
  por grupo completa (hoy el colaborador ve las aprobadas + sus pendientes).
- Si un usuario puede pertenecer a varias redes a la vez (hoy el UNIQUE es por par
  propietario/colaborador y la búsqueda toma el vínculo más reciente).
- Si un propietario con colaboradores puede a su vez ser colaborador de otro.
- Notificación por email al propietario cuando entra un reporte pendiente (hoy solo se avisa
  al creador).
- Cerrar el permisos de edición de `editar.php`: hoy cualquier usuario logueado puede editar
  cualquier incidencia (gap previo; para los pendientes se bloqueó, para el resto sigue abierto).

### Cómo revertir

1. Apagar la regla de creación de pendientes: `es_colaborador()` devuelve `false`
   (o poner `SRCI_COLABORADORES_SOLO_ADMIN` en `false` para dejar de gestionarla).
2. Publicar de golpe lo pendiente: `UPDATE incidencias SET aprobado = 1 WHERE aprobado = 0;`
   (con el `aprobado_por` correspondiente si se quiere trazabilidad).
3. Restablecer la base de datos desde el backup:
   `mysql -u <user> -p echo_srci < /home/backup/echo_srci_pre_colaboradores_2026-10-29_210340.sql`
   (deshace también las 3 migraciones: basta con borrar la tabla `colaboradores` y las 4 columnas).
