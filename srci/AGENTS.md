# AGENTS.md — Documentacion tecnica del SRCI para agentes IA

**Proyecto:** Sistema de Reporte Ciudadano de Incidencias (SRCI)
**Raiz en repositorio:** `d:\WEB\echo3d\srci\` (local) / `/home/*/public_html/srci/` (produccion VPS)
**Implementado por:** Antigravity (sesion 12020e07-ddf7-4cdc-bc2c-b4f88b7e4065) — Octubre 2026
**Estado:** Implementacion inicial completa + deploy en produccion (https://echo3dlaser.com.ar/srci). Pendiente: iconos PWA y ajuste del From del email de produccion.

---

## 1. Que es el SRCI

Aplicacion web para que ciudadanos reporten incidencias urbanas geolocalizadas (arboles caidos, alcantarillas tapadas, fugas de agua, etc.) sobre un mapa interactivo. Tiene dos roles: `usuario` (reporta) y `admin` (gestiona estados y usuarios).

---

## 2. Stack — Reglas absolutas

| Capa        | Tecnologia                          | Prohibido absolutamente                         |
|-------------|-------------------------------------|-------------------------------------------------|
| Backend     | PHP 8.x nativo, `declare(strict_types=1)` | Laravel, Symfony, Composer, cualquier framework |
| Base datos  | MySQL 8.x con PDO + named params    | mysqli, ORM, query builders                     |
| Servidor    | LiteSpeed Web Server (lsphp)        | Apache, Nginx, Docker                           |
| Frontend    | HTML5, CSS3 vanilla, JS vanilla     | React, Vue, Angular, jQuery, Bootstrap, Tailwind|
| Mapa        | Leaflet 1.9.4 + OpenStreetMap       | Google Maps, Mapbox                             |

**Excepcion aprobada por el cliente (2026-10-10):** `Leaflet.markercluster` 1.5.3 por CDN (unpkg) para el agrupamiento de marcadores del mapa. CSS y JS se cargan en `index.php`; el globo usa la clase propia `.cluster-incidencias`. Si el plugin no carga (offline), `mapa.js` cae a marcadores sueltos.

**Excepcion aprobada por el cliente (2026-10-11):** `Leaflet.draw` 1.0.4 por CDN (unpkg) para dibujar/editar los poligonos de barrios. Solo se carga en `admin/barrio.php`; si el plugin no carga, siguen funcionando subir/pegar GeoJSON.
| Sesiones    | session_start() nativo              | JWT, OAuth, librerias externas                  |
| Fotos       | Disco local en `/uploads/`          | S3, CDN externo                                 |

**Si una tarea requiere algo fuera de esta lista, detente y pregunta al humano antes de avanzar.**

---

## 3. Estructura de archivos (estado actual)

```
srci/
├── index.php              Mapa principal (Leaflet + modal de reporte en 2 pasos)
├── incidencias.php        Listado tabular con filtros (incl. categoria), paginacion, filas expandibles (todos los campos), columna de categorias, exportacion CSV/GeoJSON (incl. categorias); columnas ordenables fecha/barrio/gravedad server-side (?orden=&dir=; columna activa con clase .orden-activa pastel; contenedor .pagina-ancha 95% para evitar scroll horizontal)
├── detalle.php            Detalle de una incidencia + cambio de estado para admin
├── editar.php             Edicion completa de una incidencia (todos los campos, con CSRF)
├── login.php              Autenticacion por nombre + PIN de 4 digitos (teclado virtual)
├── logout.php             Destruye la sesion y redirige a login.php
├── acceso.php             Magic link: token HMAC-SHA256 con vencimiento (7 dias) loguea sin PIN
├── error403.php           Pagina de error para acceso denegado (incluida por auth.php)
├── ayuda.php              Ayuda publica (guia de uso, sin sesion)
├── perfil.php             Perfil privado del usuario logueado (datos, barrios, sus reportes, cambiar PIN; avatar Gravatar)
├── manifest.json          PWA manifest (icons: assets/img/icon-192.png y icon-512.png)
├── sw.js                  Service Worker — cache estatico basico para offline
├── .htaccess              Bloquea listado de directorio y acceso a /includes/
│
├── sql/
│   ├── esquema.sql        Esquema completo de DB + datos semilla de tipos, barrios y usuario admin
│   ├── migracion_planilla.sql  Migracion: tabla barrios + campos nuevos en incidencias + 3 tipos nuevos
│   ├── migracion_usuarios_barrios.sql  Migracion: barrios reales + tabla referentes_barrios
│   ├── migracion_barrios_categoria_color.sql  Migracion: categoria (formal|informal|asentamiento) + color de render de barrios
│   ├── migracion_categorias_incidencia.sql  Migracion: categorias de incidencia (muchos-a-muchos, no excluyentes) + backfill a Climaticos
│   └── migracion_categorias_visible_usuario.sql  Migracion: categorias_incidencia.visible_usuario (TINYINT default 0) — el selector de categorias queda SOLO para admins (idempotente)
│
├── includes/
│   ├── db.php             Conexion PDO singleton (funcion db()), respuesta_json()
│   ├── auth.php           Funciones de sesion: requiere_sesion(), requiere_admin(), csrf_token(), validar_csrf()
│   └── funciones.php      Helpers: esc(), fecha_legible(), generar_pin(), guardar_foto(), enviar_pin_por_email(),
│                          barrio_por_punto(), geojson_a_anillos() / anillos_a_geojson() / contar_anillos_poligono(),
│                          nombre_categoria() / clase_categoria() / color_categoria() / color_valido(),
│                          categorias_activas() / categorias_visibles() / hay_categorias_visibles() /
│                          categoria_default_id() / categorias_de_incidencia() /
│                          guardar_categorias_incidencia() / categorias_por_incidencia()
│
├── api/
│   ├── tipos.php          GET  /srci/api/tipos.php      — lista tipos activos (JSON)
│   ├── barrios.php        GET  /srci/api/barrios.php    — lista barrios activos (JSON)
│   ├── categorias.php     GET  /srci/api/categorias.php            — lista categorias de incidencia activas (JSON; ?visibles=1 = solo las marcadas para usuarios)
│   ├── poligonos.php      GET  /srci/api/poligonos.php  — poligonos de barrios activos: [{id, nombre, categoria, color, anillos}]
│   ├── reportes.php       GET  /srci/api/reportes.php   — lista incidencias con filtros opcionales (solo con coordenadas; es el feed del mapa)
│                          POST /srci/api/reportes.php   — crea nueva incidencia (JSON body)
│   ├── subir_foto.php     POST /srci/api/subir_foto.php — sube foto (multipart/form-data)
│   ├── estado.php         POST /srci/api/estado.php     — cambia estado de incidencia (solo admin, JSON body)
│   └── sheets_entrada.php POST /srci/api/sheets_entrada.php — importa filas manuales de la planilla (auth por SRCI_SHEETS_SECRET; sin espejo)
│   └── sin_ubicacion.php  GET  /srci/api/sin_ubicacion.php — conteo de incidencias sin coordenadas (chip del mapa)
│
├── admin/
│   ├── reportes.php       Panel admin: listado de reportes (con columna de categorias) + cambio de estado via form POST
│   ├── usuarios.php       Panel admin: crear usuarios, editarlos (nombre/email/rol), regenerar PIN, activar/desactivar +
│   │                      tarjeta "Activacion masiva" (POST lote_links + CSRF): activa referentes inactivos con email
│   │                      ruteable (excluye rol=admin y emails .local) y les envia link (30 dias) + PIN de respaldo via
│   │                      enviar_activacion_por_email(), en lotes de 10 con flash por redirect; audita "Lote masivo:..."
│   ├── tipos.php          Panel admin: crear tipos de incidencia, activar/desactivar
│   ├── categorias.php     Panel admin: crear/editar/activar categorias de incidencia (nombre + icono + color + switch "visible para usuarios" por categoria)
│   ├── barrios.php        Panel admin: crear/activar barrios + tabla con categoria, swatch de color, poligono, incidencias y referentes; link a barrio.php
│   ├── barrio.php         Panel admin: detalle de un barrio (info reversa: datos, incidencias, ultimas, referentes),
│   │                      edicion de nombre/estado/poligono (subir/pegar/dibujar GeoJSON, vista previa Leaflet.draw,
│   │                      descargar/limpiar) y recalculo del barrio de incidencias sin barrio
│   └── auditoria.php      Panel admin: log de auditoria (quien, cuando, que hizo)
│
├── assets/
│   ├── css/estilos.css    Sistema de diseno completo (variables CSS, dark mode, responsive)
│   └── js/
│       ├── login.js       Logica del teclado PIN numerico en login.php
│       ├── mapa.js        Inicializacion Leaflet, carga marcadores agrupados POR BARRIO (un L.markerClusterGroup por barrio_id, asi no se fusionan puntos de distintos barrios; radio ajustable por zoom, sueltos a zoom 17+), iconos: color por gravedad + emoji del tipo; resueltas grises con check y zIndex bajo; en_gestion con anillo azul. Dibuja los limites de barrio (api/poligonos.php) con el color propio de cada barrio (p.color, fallback #4f46e5; hover-only). Filtro por categoria con dropdown (<select id> en #filtros-mapa, combina con AND con los chips de tipo y "Mis reportes") + badges de categoria en el popup
│       └── reporte.js     Modal de reporte: paso 1 (tipo) → paso 2 (chips de categorias multiseleccion, por defecto Climaticos + mini-mapa + foto + notas)
│
└── uploads/
    └── .htaccess          Bloquea ejecucion de PHP en este directorio (critico de seguridad)
```

---

## 4. Base de datos

**Nombre de la BD:** `srci`  
**Usuario MySQL de la app:** `srci_user` (solo permisos sobre `srci`)  
**Credenciales en desarrollo:** ver `includes/db.php`  
**Credenciales en produccion:** crear `includes/config.local.php` (excluido de git) con:

```php
<?php
define('SRCI_DB_HOST', 'localhost');
define('SRCI_DB_NAME', 'srci');
define('SRCI_DB_USER', 'usuario_real');
define('SRCI_DB_PASS', 'contrasena_real');
define('SRCI_MAGIC_SECRET', 'secreto_aleatorio_64_hex');  // firma los magic links (acceso.php)
```

### Esquema

```sql
usuarios          id, nombre(UNIQUE), email, pin_hash, rol(usuario|admin), activo, creado_en
tipos_incidencia  id, clave(UNIQUE), nombre, icono, activo
barrios           id, nombre(UNIQUE), poligono(TEXT NULL: JSON [[[lat,lng],...],...]),
                  categoria(ENUM formal|informal|asentamiento NULL), color(VARCHAR(7) #rrggbb NULL), activo, creado_en —
                  el poligono (relevamiento QGIS del cliente, WGS84) delimita el barrio: barrio_por_punto() en funciones.php
                  hace ray-casting y api/reportes.php asigna el barrio si el cliente no mando uno (poligono pre-selecciona,
                  usuario confirma; mismatch queda en auditoria). api/poligonos.php sirve los poligonos para el mapa
                  (limites suaves + tooltip) y para el ray-casting del cliente (reporte.js pre-selecciona).
                  categoria clasifica el barrio (formal=catastro, informal, asentamiento); color es el hex de render del
                  poligono en el mapa (NULL = usar color_categoria(categoria): formal #2563eb, informal #d97706,
                  asentamiento #dc2626, sin categoria #4f46e5).
referentes_barrios usuario_id(FK CASCADE), barrio_id(FK CASCADE) — PK(usuario_id, barrio_id)
incidencias       id, usuario_id(FK), tipo_id(FK), latitud, longitud, barrio_id(FK barrios, NULL),
                  direccion, gravedad(baja|media|alta|critica), familias_afectadas,
                  servicio_afectado, calle_intransitable, responsable_area, contacto_vecino,
                  notas, fecha_hora, estado(pendiente|en_proceso|resuelto), oculto(0|1), fecha_resolucion
fotos             id, incidencia_id(FK CASCADE), ruta_archivo, fecha_subida
auditoria         id, usuario_id(FK SET NULL), accion(crear|editar|cambiar_estado|ocultar|restaurar|subir_foto),
                  incidencia_id, detalle, fecha_hora
categorias_incidencia id, clave(UNIQUE), nombre, icono, color(VARCHAR(7) #rrggbb NULL), activo,
                  visible_usuario(TINYINT 1 UNSIGNED, default 0) —
                  categorias de incidencia (etiquetas INDEPENDIENTES del tipo, NO excluyentes). Semilla:
                  climaticos(Climáticos 🌧️), calles_rotas(Calles rotas 🛣️), luminarias(Luminarias 💡),
                  arbolado(Arbolado 🌳), basura(Basura 🗑️), agua_cloaca(Agua y cloaca 💧). "Climáticos" es la
                  categoria por defecto. CRUD en admin/categorias.php; catalogo en api/categorias.php.
                  visible_usuario=1 expone la categoria a los usuarios regulares (api ?visibles=1, selector del
                  modal y dropdown del mapa); con todas en 0 (estado actual) el selector es SOLO de admins.
incidencia_categorias incidencia_id(FK CASCADE), categoria_id(FK CASCADE) — PK(incidencia_id, categoria_id).
                  Pivote muchos-a-muchos. La migracion hace backfill: TODAS las incidencias existentes quedan en Climáticos.
```

**Regla:** nunca alterar el esquema sin proponer la migracion SQL primero y esperar confirmacion del humano.

### Datos semilla (ya aplicados)

11 tipos de incidencia con `icono` emoji (español): `🌳` arbol_caido, `🕳️` alcantarilla, `🏚️` vivienda_precaria, `🏠` techo_riesgo, `⚡` cableado, `💧` fuga_agua, `🗑️` basural, `📋` otro, `💡` caida_poste, `🌧️` anegamiento, `🌊` inundacion.  
Barrios: 44 sembrados con los barrios reales del relevamiento (Los Palos, Circuito 5, San Roque, La Quilmes, Villa Itapé, etc. — lista completa en `sql/migracion_usuarios_barrios.sql`), editables desde `/srci/admin/barrios.php`.  
Referentes por barrio: tabla `referentes_barrios` (muchos a muchos) — asignados por el script de carga masiva; los referentes que faltan por email se cargan cuando completen datos.  
Usuario admin inicial: nombre=`admin`, PIN=`0000` (password_hash de PASSWORD_DEFAULT).  
Usuarios del relevamiento: cargados por script one-shot con username = email; el 10/10/2026 se aplico la planilla completa (`sql/actualizacion_nombres_planilla.sql`): **nombres reales** en `nombre`, **login SOLO por email** (`login.php` matchea `email = :login`; el `nombre` es solo display), y 11 referentes sin email cargados como inactivos con `email NULL` (cuando aporten email: admin lo edita en usuarios.php y usa "Activar + PIN"). Asignaciones de barrio en `referentes_barrios` (visible como columna "Barrios" en usuarios.php). Emails alternativos de la planilla (salamoniniangel@, marclaymarianela@, mosquedamario774@, pipoaminero@) no cargados: solo hay una columna email. PIN por email desde `admin@echo3dlaser.com.ar` (`enviar_pin_por_email()`). 4 admins: Guillaume, Marclay, Garay, admin. Despliegue por etapas: solo activos pueden loguear.

**Sesion larga (auth.php):** `SESION_DURACION` = 90 dias, cookie persistente + **sliding** (`renovar_sesion()` en `requiere_sesion()` renueva la cookie en cada request) + `session.gc_maxlifetime` = 90 dias. La sesion permanece viva hasta logout explicito (`logout.php` destruye sesion y borra cookie). Si se quiere mas/menos, ajustar `SESION_DURACION`.

**Perfil privado del usuario (`perfil.php`):** cualquier usuario logueado (`requiere_sesion()` + CSRF en el POST) ve sus datos (nombre, email, rol, estado, alta), sus barrios asignados (`referentes_barrios`) y sus reportes (`incidencias WHERE usuario_id = sesion AND oculto = 0`). Puede **cambiar su propio PIN**: exige el PIN actual (`password_verify`), valida `/^\d{4}$/` + repeticion, rechaza igual-al-actual, y audita `cambiar_pin`. **No** se edita el email (login) desde el perfil: solo un admin lo cambia en `admin/usuarios.php`. Avatar **Gravatar** read-only via `md5(strtolower(trim(email)))` (`?s=160&d=404`) con fallback local de iniciales; no se sube foto propia. Se llega tocando el chip `👤 <nombre>` de la barra (link en todas las paginas autenticadas). `admin/auditoria.php` mapea `cambiar_pin` en `accion_legible()`/`clase_accion()`.

**Edicion de barrios y poligonos (`admin/barrio.php`):** pagina de detalle de un barrio (accesible con ✏️ Editar desde `admin/barrios.php`) que muestra info reversa (datos, conteos de incidencias por estado y gravedad, familias afectadas, sin ubicacion, incidencia abierta mas antigua, ultimas 12 incidencias con link a `detalle.php` y referentes asignados) y permite editar **nombre/estado/categoria/color/poligono**. La **categoria** (select: sin categoría / Formal (catastro) / Informal / Asentamiento) y el **color** (`<input type="color">` con boton "Usar color de la categoria") se guardan en `barrios.categoria` / `barrios.color`; el color se valida con `color_valido()` (`^#[0-9a-fA-F]{6}$`, lowercase) y si queda NULL el mapa usa `color_categoria(categoria)`. El color se ve en vivo sobre el poligono dibujado (`drawnItems.setStyle`) y NO activa `mapaModificado` (o sea, cambiar solo el color no reenvia el poligono). El poligono se carga de tres formas que convergen en GeoJSON: subir archivo `.geojson/.json`, pegar texto, o dibujar/editar en el mapa (Leaflet + Leaflet.draw, `featureGroup` `drawnItems`); el mapa de edicion usa **capa satelite Esri World Imagery** por defecto con selector Mapa/Satelite (`L.control.layers`). `anillos_a_geojson()` exporta el actual (boton ⬇ Descargar); `geojson_a_anillos()` normaliza lo entrante: acepta `FeatureCollection`/`Feature`/`Polygon`/`MultiPolygon`, convierte `[lng,lat] -> [lat,lng]`, aplana multipoligonos a anillos y **descarta huecos** (el ray-casting no los soporta); limites 2 MB / 20000 vertices. Se guarda en `barrios.poligono` (JSON `[[[lat,lng],...],...]`). El boton 🗑 Limpiar pone `poligono = NULL`. El boton 🔁 Recalcular corre `barrio_por_punto()` sobre las incidencias con `barrio_id IS NULL AND latitud IS NOT NULL` y les asigna barrio (solo local, no empuja a Sheets). Audita `editar` (con detalle de cambios) y `recalcular_barrios`.

**Alineacion con la planilla de relevamiento** (`Planilla_relevamiento_barrios.xlsx`): el formulario pide Barrio, Direccion/calle y altura, Gravedad (obligatorios) y Familias afectadas, Servicio afectado, Calle intransitable, Responsable/area, Contacto vecino (opcionales). "Dias abiertos" se calcula (fecha_hora → fecha_resolucion o hoy). Estado: "En gestion" = `en_proceso`.

**Espejo bidireccional con Google Sheets (planilla de relevamiento, gid 73522773):** via Apps Script Web App (`SRCI_SHEETS_WEBAPP_URL` + `SRCI_SHEETS_SECRET` en config.local.php). Ida (app → hoja): cada reporte/foto/cambio de estado envia fire-and-forget (`enviar_a_sheets()`, funciones.php; Apps Script responde 302 y el echo final se llama con GET — el payload viaja en user_content_key). Vuelta (hoja → app): el script `sincronizarEntrada()` (trigger cada 5 min) escanea filas de datos **sin tag en col T** y las importa via `api/sheets_entrada.php` (accion 'importar'); al importar escribe tag `APP-<id>` en col T — ese tag es el dedupe: filas de la app ya nacen taggeadas, filas importadas quedan taggeadas, ERROR-xxx si la importacion falla. Coordenadas de importados = NULL: no aparecen en el mapa (api/reportes.php GET filtra `latitud IS NOT NULL`), cuentan en `api/sin_ubicacion.php` (chip "📍 N sin ubicación" en la botonera del mapa) y se ubican desde editar.php; barrio inexistente se crea; "Relevado por" matchea usuario por nombre O email, fallback usuario dedicado 'Planilla' (inactivo). Cambiar el codigo del script requiere "Nueva version" en Administrar implementaciones.

**Categorias de incidencia (`categorias_incidencia` + `incidencia_categorias`):** etiquetas **independientes del tipo** y **no excluyentes** (una incidencia puede tener varias). Se eligen en el modal de reporte (`index.php` + `reporte.js`: chips multiseleccion en el Paso 2, **Climáticos pre-marcada**), se editan en `editar.php` (checkboxes `categorias[]`) y se muestran como badges en `incidencias.php` (columna + fila expandida), `admin/reportes.php` (columna), `detalle.php` y el popup del mapa. **El selector es SOLO para admins (decision del cliente 2026-10-11, "por ahora"):** `index.php` renderiza el campo solo si `$es_admin || hay_categorias_visibles()`; `index.php` inyecta `window.SRCI_ES_ADMIN` y `window.SRCI_MOSTRAR_CATEGORIAS` y `reporte.js`/`mapa.js` gatean con eso (admins: `/api/categorias.php`; usuarios: `/api/categorias.php?visibles=1`, y si no hay visibles no se renderiza nada). El admin enciende categoria por categoria con el switch **"👁 Mostrar a usuarios"** de `admin/categorias.php` (`visible_usuario`, columna "Usuarios", checkbox en crear/editar, accion `toggle_visibilidad`, audita "visible/oculta para usuarios"). `api/reportes.php` POST acepta `categorias: [int]` (valida activas; si viene vacio aplica la por defecto) y su GET adjunta `categorias: [{id,clave,nombre,icono,color}]` a cada incidencia (segunda query via `categorias_por_incidencia()`). `incidencias.php` permite filtrar por `categoria_id` (subquery `EXISTS`) y exporta las categorias en CSV (`Categorías`) y GeoJSON (`properties.categorias`). El mapa filtra por categoria con un **dropdown** en `#filtros-mapa` (combina AND con tipo/mis reportes; `mapa.js` guarda `reg.categoriaIds`). La importacion de la planilla (`api/sheets_entrada.php`) asigna **Climáticos**. Helpers en `funciones.php`: `categorias_activas()`, `categorias_visibles()`, `hay_categorias_visibles()`, `categoria_default_id()`, `categorias_de_incidencia($id)`, `guardar_categorias_incidencia($id, $ids)`, `categorias_por_incidencia($ids)`.

**Ocultar (soft delete) + auditoria (REGLAS):** las incidencias **nunca se borran** de la BD. "Eliminar" = pasar a `oculto = 1` (solo admins, con CSRF y confirm). El mapa, listado y exportaciones filtran `oculto = 0`; el admin ve/restaura las ocultas desde `admin/reportes.php?ocultas=1`. Toda accion (crear, editar con detalle de campos cambiados, cambiar estado, ocultar, restaurar, subir foto) se registra en la tabla `auditoria` con `registrar_auditoria()` (`includes/funciones.php`) — visible en `/srci/admin/auditoria.php`.

---

## 5. Patrones de codigo establecidos

### 5.1 Conexion a la DB

Siempre usar la funcion `db()` de `includes/db.php`. Es un singleton PDO.

```php
require_once __DIR__ . '/includes/db.php';

$stmt = db()->prepare('SELECT * FROM incidencias WHERE id = :id');
$stmt->execute([':id' => $id]);
$fila = $stmt->fetch();
```

**Nunca** concatenar variables en SQL. **Nunca** usar `mysqli`.

### 5.2 Respuestas JSON en las APIs

```php
respuesta_json($datos);           // HTTP 200
respuesta_json($datos, 201);      // HTTP 201
respuesta_json(['error' => '...'], 400);  // HTTP 4xx
```

`respuesta_json()` llama a `exit` internamente — no hace falta nada despues.

### 5.3 Seguridad de sesion

**Todo archivo no-publico** (admin/, api/) debe comenzar con:

```php
require_once __DIR__ . '/../includes/auth.php';
requiere_sesion();   // para cualquier usuario autenticado
// o bien:
requiere_admin();    // solo para admins (llama a requiere_sesion() internamente)
```

**Formularios de admin** deben incluir token CSRF:

```php
// En el template:
<input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">

// Al procesar POST:
validar_csrf(); // lanza die() si es invalido
```

### 5.4 Salida HTML

**Toda** variable PHP que se imprime en HTML pasa por `esc()`:

```php
echo esc($variable);           // correcto
echo htmlspecialchars($var);   // aceptable pero usar esc()
echo $variable;                // PROHIBIDO
```

### 5.5 Convencion de nombres

- Variables PHP: `snake_case` en espanol. Ej: `$usuario_id`, `$fecha_hora`
- Funciones PHP: `snake_case` en espanol. Ej: `obtener_reportes()`, `guardar_foto()`
- Constantes: `MAYUSCULAS`. Ej: `MAX_FOTO_BYTES`, `UPLOADS_DIR`
- Clases CSS: `kebab-case` en espanol. Ej: `.tabla-incidencias`, `.boton-primario`
- Funciones JS: `camelCase` en espanol. Ej: `mostrarModal()`, `enviarReporte()`
- JS: `const` por defecto, `let` si reasigna, `var` nunca.

---

## 6. APIs — Contratos

### GET /srci/api/tipos.php
**Auth:** sesion activa  
**Response:** `[{id, clave, nombre, icono}, ...]` — solo tipos activos

### GET /srci/api/barrios.php
**Auth:** sesion activa  
**Response:** `[{id, nombre}, ...]` — solo barrios activos, orden por nombre

### GET /srci/api/categorias.php
**Auth:** sesion activa  
**Param opcional:** `visibles=1` — devuelve solo las categorias activas con `visible_usuario = 1` (para usuarios regulares)  
**Response:** `[{id, clave, nombre, icono, color}, ...]` — solo categorias de incidencia activas, orden por id

### GET /srci/api/reportes.php
**Auth:** sesion activa  
**Params opcionales:** `tipo_id`, `estado`, `barrio_id`, `gravedad` (baja|media|alta|critica), `categoria_id`, `fecha_desde` (YYYY-MM-DD), `fecha_hasta` (YYYY-MM-DD)  
**Response:** array de incidencias con joins (tipo_nombre, tipo_icono, tipo_clave, barrio_nombre, usuario_nombre, foto, campos nuevos: direccion, gravedad, familias_afectadas, servicio_afectado, calle_intransitable, responsable_area, contacto_vecino, fecha_resolucion) + `categorias: [{id, clave, nombre, icono, color}]`  
**Limite:** 1000 registros (sin paginacion — para el mapa)

### POST /srci/api/reportes.php
**Auth:** sesion activa  
**Body JSON:** `{tipo_id: int, latitud: float, longitud: float, barrio_id: int, direccion: string, gravedad: "baja"|"media"|"alta"|"critica", categorias?: [int], familias_afectadas?: int, servicio_afectado?: string, calle_intransitable?: 0|1, responsable_area?: string, contacto_vecino?: string, notas?: string}`  
**Obligatorios:** tipo_id, latitud, longitud, barrio_id, direccion, gravedad  
**Notas:** `categorias` es opcional; si viene vacio o con ids invalidos se aplica la categoria por defecto (Climáticos)  
**Response exito:** `{ok: true, id: int}` HTTP 201  
**Response error:** `{error: string}` HTTP 400 | 401 (relogin) | 422

### POST /srci/api/subir_foto.php
**Auth:** sesion activa + propietario de la incidencia (o admin)  
**Body:** multipart/form-data con `incidencia_id` y `foto`  
**Validacion:** MIME real con `finfo_file()`, max 5 MB, solo JPG/PNG/WEBP  
**Nombre de archivo:** `bin2hex(random_bytes(16))` + extension  
**Response exito:** `{ok: true, ruta: string}` HTTP 201

### POST /srci/api/estado.php
**Auth:** solo admin  
**Body JSON:** `{incidencia_id: int, estado: "pendiente"|"en_proceso"|"resuelto"}`  
**Comportamiento:** al pasar a `resuelto` setea `fecha_resolucion = NOW()`; al salir de `resuelto` la limpia  
**Response:** `{ok: true}` | `{error: string}`

---

## 7. Flujo del modal de reporte (JS)

El modal en `index.php` tiene 2 pasos gestionados por `reporte.js`:

1. **Paso 1** (`#paso-tipo`): grilla de tipos cargada desde `/api/tipos.php`. El usuario selecciona uno.
2. **Paso 2** (`#paso-detalles`): grid 2 columnas en desktop (FullHD sin scroll) — barrio (select de `/api/barrios.php`), direccion, gravedad, **categorias (chips multiseleccion; SOLO se renderiza para admins, o para usuarios si el admin marco categorias visibles — ver seccion Categorias)**, familias afectadas, servicio afectado, responsable/area, calle intransitable (checkbox), contacto vecino — mas mini-mapa Leaflet para marcar coordenadas, textarea de notas, zona de foto. En movil: una columna.
3. Al enviar: primero POST a `/api/reportes.php` (incluye `categorias: [ids]`), luego (si hay foto) POST a `/api/subir_foto.php`. Validacion cliente: barrio, direccion y gravedad obligatorios.

**Reporte por click en el mapa:** al hacer click sobre el mapa principal (`mapa.js`), se coloca un marcador de selección (`crearIconoSeleccion`, anillo indigo) y se abre el modal con esas coordenadas precargadas via `window.abrirReporteConCoordenada(latlng)` (expuesta por `reporte.js`). En el paso 2 el mini-mapa ya queda centrado en ese punto con su marcador; el usuario puede ajustarlo tocando el mini-mapa. Al cerrar el modal, `cerrarModal()` limpia el marcador del mapa principal via `window.removerMarcadorSeleccion()`.

**Geolocalización del dispositivo:** control "🎯 Mi ubicación" (`L.Control` propio en `mapa.js`, arriba a la derecha) que usa la Geolocation API nativa (`navigator.geolocation.getCurrentPosition`). Al tocarlo centra siempre en la posición, muestra el marcador azul pulsante (`.puntero-ubicacion`) y un `L.circle` con el radio de precisión (`coords.accuracy`). No se dispara automáticamente al cargar; los errores (permiso/no disponible/timeout) se avisan con un toast discreto (`.aviso-mapa`). Todo es client-side: no se envía nada al servidor.

El mapa principal en `mapa.js` expone `window.mapaLeaflet` para que `reporte.js` pueda leer el centro actual al inicializar el mini-mapa.

Los marcadores usan SVG inline con color segun estado: amarillo=pendiente, azul=en_proceso, verde=resuelto.

---

## 8. Sistema de diseno CSS

Archivo: `assets/css/estilos.css` — Tema claro (sin dark mode, sin frameworks). El modal del reporte siempre es blanco (mismo tema).

### Variables en :root (las mas importantes)

```css
--color-acento:      #4f46e5   /* indigo principal */
--color-fondo:       #f8fafc   /* fondo de pagina */
--color-superficie:  #ffffff   /* tarjetas, nav */
--color-borde:       #e2e8f0
--color-texto:       #1e293b
--color-pendiente:   #d97706
--color-en-proceso:  #2563eb
--color-resuelto:    #16a34a
```

### Clases clave

| Clase | Uso |
|-------|-----|
| `.boton .boton-primario` | Boton principal azul |
| `.boton .boton-secundario` | Boton borde transparente |
| `.boton .boton-peligro` | Accion destructiva (rojo) |
| `.boton-sm` | Variante pequena (36px min) |
| `.boton-bloque` | Ancho completo |
| `.campo` | Wrapper de label + input |
| `.tarjeta` | Panel con borde y padding |
| `.tabla-incidencias` | Tabla de datos |
| `.fila-expandida` | Fila expandible de la tabla con todos los campos |
| `.estado-badge .estado-{estado}` | Badge de estado coloreado |
| `.gravedad-badge .gravedad-{nivel}` | Badge de gravedad (baja=verde, media=ámbar, alta=naranja, crítica=rojo) |
| `.categoria-badge` | Badge de categoria de incidencia (color propio via `--cat-color`) |
| `.chips-categoria .chip-categoria` | Chips de seleccion multiple de categorias (modal y editar) |
| `.mensaje .mensaje-{tipo}` | Feedback: exito, error, info |
| `.spinner` | Indicador de carga animado |
| `.modal-fondo .visible` | Modal deslizante desde abajo |
| `.admin-layout` | Grid sidebar + main del panel admin |
| `.fab-reportar` | Boton flotante del mapa |
| `.grilla-tipos` | Grid 2 columnas de tarjetas de tipo |

---

## 9. Checklist de seguridad

Antes de dar por terminada cualquier tarea, verificar:

- [ ] Todas las queries usan `db()->prepare()` con parametros nombrados (`:nombre`)
- [ ] Todo archivo de admin/api llama a `requiere_sesion()` o `requiere_admin()` al inicio
- [ ] Toda salida HTML usa `esc($var)`
- [ ] Subidas de archivo usan `guardar_foto()` de `funciones.php` (valida MIME real)
- [ ] Formularios de admin incluyen `csrf_token()` y llaman a `validar_csrf()` al procesar POST
- [ ] Nunca concatenar variables en SQL
- [ ] Nunca confiar en `$_FILES['type']` para validar MIME

---

## 10. Configuracion pendiente para produccion

- [ ] Crear `includes/config.local.php` en el VPS con credenciales reales de DB
- [ ] Ajustar `From:` de emails en `funciones.php::enviar_pin_por_email()` al dominio real
- [x] Cambiar el centro del mapa en `assets/js/mapa.js` (coordenadas objetivo: `-32.48262351713079, -58.24455570742029`)
- [ ] Crear iconos PWA: `assets/img/icon-192.png` y `assets/img/icon-512.png`
- [ ] Permisos del servidor: `chmod 755 uploads/` y propietario `www-data` o `lsws`
- [ ] PIN inicial del admin (`0000`) debe cambiarse en el primer login desde `/srci/admin/usuarios.php`
- [ ] En produccion HTTPS, la cookie de sesion se enviara con `Secure=true` automaticamente (ya codificado en `auth.php`)

---

## 11. Comandos utiles en el servidor

```bash
# Importar esquema
mysql -u srci_user -p srci < srci/sql/esquema.sql

# Ver logs de LiteSpeed
tail -f /usr/local/lsws/logs/error.log

# Verificar permisos de uploads
ls -la /ruta/srci/uploads/

# Backup de la BD
mysqldump -u srci_user -p srci > backup_srci_$(date +%F).sql
```

### 11.1 Como operar el VPS desde Windows/PowerShell (IMPORTANTE)

El agente corre en PowerShell 5.1 (Windows) y **NO soporta bien las comillas anidadas** al enviar comandos por `ssh` (los `"` se pierden, `&` y `(` rompen el parser, y las variables `$...` se expanden y desaparecen). Regla para evitar intentos en vano:

- **Nunca** mandar comandos complejos inline por ssh con comillas anidadas, heredocs ni `$`.
- **Para subir/editar archivos:** crear el archivo localmente (herramienta Write) y subirlo con `scp`:
  `scp -q "ruta\local" 149.50.142.160:/ruta/remota`
- **Para ejecutar comandos:** escribir un `.sh` local, subirlo con `scp` y ejecutarlo:
  `scp -q script.sh 149.50.142.160:/tmp/script.sh; ssh 149.50.142.160 "bash /tmp/script.sh"`
- **Comandos simples sin comillas/`$`/`&`:** usar comillas simples de PowerShell, ej:
  `ssh 149.50.142.160 'tail -30 /var/log/echo3d-deploy.log'`
- **PHP CLI en el server:** pasar el código como archivo (scp), nunca `php -r` inline.
- **Encoding UTF-8 (CRITICO):** `Get-Content`/`Set-Content` de PowerShell 5.1 leen como ANSI y re-guardan doble-encoded: **corrompen acentos y emojis** de los archivos. Para leer/reescribir archivos con texto en español/emojis usar metodos .NET:
  `$enc = New-Object System.Text.UTF8Encoding($false); $c = [System.IO.File]::ReadAllText($ruta, $enc); ... [System.IO.File]::WriteAllText($ruta, $c, $enc)`
- Credenciales de prod viven en `includes/config.local.php` (no en git).

### 11.2 Pitfall del Service Worker (cache viejo)

`sw.js` registra un Service Worker en `index.php`. Si cambias CSS/JS y el navegador sigue mostrando lo viejo, es porque el SW previo servia desde cache con una version fija. Reglas:

- La estrategia es **network-first** (trae lo ultimo y actualiza el cache; usa cache solo offline).
- Si cambias la lista `APP_SHELL` o la estrategia, **subi `CACHE`** (ej: `srci-v2` → `srci-v3`) para purgar el cache anterior.
- Los `<script>` de `index.php` llevan `?v=YYYYMMDD` para forzar el refresco inmediato aunque el SW viejo siga activo (bumpear al cambiar esos JS).

---

## 12. Que NO hacer

- No agregar dependencias externas (Composer, npm, CDNs no listados)
- No crear carpetas nuevas sin justificacion explicita
- No modificar el esquema de DB sin proponer la migracion SQL primero
- No refactorizar codigo existente sin que el humano lo pida
- No inventar endpoints, tablas o campos no documentados aqui
- No entregar mas de 3 archivos nuevos por respuesta sin que te lo pidan
- No usar `var` en JavaScript
- No usar `!important` en CSS salvo ultimo recurso
- No confiar en `$_FILES['type']` — siempre usar `finfo_file()`

---

## 13. Roadmap (estado)

| Semana | Entregable | Estado |
|--------|-----------|--------|
| 1 | Prototipo HTML | ⏩ Saltado por decision del cliente |
| 2 | MySQL + login PIN + sesiones | ✅ Completo |
| 3 | Mapa Leaflet + modal tipos + creacion de reporte | ✅ Completo |
| 4 | Subida y validacion de fotos | ✅ Completo |
| 5 | incidencias.php (tabla, filtros, paginacion, CSV, GeoJSON) | ✅ Completo |
| 6 | Panel admin (usuarios, tipos, estados) | ✅ Completo |
| 7 | PWA + pruebas movil + deploy VPS | 🔄 En progreso (SW y manifest listos, deploy pendiente) |

---

*Fin del documento. Actualizar este archivo ante cada cambio de alcance o decision tecnica relevante.*
