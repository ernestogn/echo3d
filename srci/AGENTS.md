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
├── CHANGELOG.md           Cronologia de cambios funcionales del sistema (por fecha/version)
├── index.php              Mapa principal (Leaflet + modal de reporte en 2 pasos)
├── incidencias.php        Listado tabular con filtros (incl. categoria), paginacion, filas expandibles (todos los campos), columna de categorias, exportacion CSV/GeoJSON (incl. categorias); columnas ordenables fecha/barrio/gravedad server-side (?orden=&dir=; columna activa con clase .orden-activa pastel; contenedor .pagina-ancha 95% para evitar scroll horizontal)
├── detalle.php            Detalle de una incidencia + cambio de estado para admin
├── editar.php             Edicion completa de una incidencia (todos los campos, con CSRF) + seccion Fotos:
│                           miniaturas de las existentes y subida de fotos nuevas (fetch a api/subir_foto.php con
│                           vista previa y spinner; recarga al terminar) — disponible tenga o no fotos ya
├── login.php              Autenticacion por nombre + PIN de 4 digitos (teclado virtual)
├── logout.php             Destruye la sesion y redirige a login.php
├── acceso.php             Magic link: token HMAC-SHA256 con vencimiento (7 dias) loguea sin PIN
├── error403.php           Pagina de error para acceso denegado (incluida por auth.php)
├── ayuda.php              Ayuda publica (guia de uso, sin sesion)
├── perfil.php             Perfil privado del usuario logueado (datos, barrios, sus reportes, cambiar PIN; avatar Gravatar)
├── ayuda.php              Ayuda publica (guia de uso, sin sesion)
├── manifest.json          PWA manifest (icons: assets/img/icon-192.png y icon-512.png)
├── sw.js                  Service Worker DESACTIVADO: se autodesinstala y borra todos los caches al activarse.
│                          La app SIEMPRE carga de la red (los assets llevan ?v=YYYYMMDD para el cache HTTP normal).
├── .htaccess              Bloquea listado de directorio y acceso a /includes/
│
├── sql/
│   ├── esquema.sql        Esquema completo de DB + datos semilla de tipos, barrios y usuario admin
│   ├── migracion_planilla.sql  Migracion: tabla barrios + campos nuevos en incidencias + 3 tipos nuevos
│   ├── migracion_usuarios_barrios.sql  Migracion: barrios reales + tabla referentes_barrios
│   ├── migracion_barrios_categoria_color.sql  Migracion: categoria (formal|informal|asentamiento) + color de render de barrios
│   ├── migracion_categorias_incidencia.sql  Migracion: categorias de incidencia (muchos-a-muchos, no excluyentes) + backfill a Climaticos
│   ├── migracion_categorias_visible_usuario.sql  Migracion: categorias_incidencia.visible_usuario (TINYINT default 0) — el selector de categorias queda SOLO para admins (idempotente)
│   ├── migracion_seguimiento.sql  Migracion: tabla incidencia_seguimiento (notas fechadas por incidencia, append-only, con autor)
│   ├── migracion_colaboradores.sql  Migracion: tabla colaboradores (red de colaboradores: propietario_id + colaborador_id + estado)
│   ├── migracion_aprobacion_incidencias.sql  Migracion: incidencias.aprobado / aprobado_en / aprobado_por / propietario_id + indices + FKs
│   ├── migracion_backfill_aprobado.sql  Migracion: backfill de incidencias existentes (aprobado=1, propietario_id=usuario_id) — ejecutar UNA sola vez
│   ├── migracion_colaboradores_red_unica.sql  Migracion: normaliza colaboradores duplicados (deja el mas reciente) + UNIQUE uq_colaborador (una sola red por usuario) + dropea idx_colaborador
│   └── migracion_rechazo_visible.sql  Migracion: incidencias.rechazado / rechazado_en / rechazado_por / motivo_rechazo + indice (el rechazo queda visible para el autor con su motivo)
│
├── includes/
│   ├── db.php             Conexion PDO singleton (funcion db()), respuesta_json()
│   ├── auth.php           Funciones de sesion: requiere_sesion(), requiere_admin(), csrf_token(), validar_csrf()
│   └── funciones.php      Helpers: esc(), fecha_legible(), generar_pin(), guardar_foto(), enviar_pin_por_email(),
│                          enviar_activacion_por_email() (link 30 dias + PIN de respaldo),
│                          barrio_por_punto(), geojson_a_anillos() / anillos_a_geojson() / contar_anillos_poligono(),
│                          nombre_categoria() / clase_categoria() / color_categoria() / color_valido(),
│                          categorias_activas() / categorias_visibles() / hay_categorias_visibles() /
│                          categoria_default_id() / categorias_de_incidencia() /
│                          guardar_categorias_incidencia() / categorias_por_incidencia(),
│                          seguimiento_de_incidencia() / contar_seguimiento_por_incidencia() /
│                          notificar_creador_por_email() / email_ruteable()
│
├── api/
│   ├── tipos.php          GET  /srci/api/tipos.php      — lista tipos activos (JSON)
│   ├── barrios.php        GET  /srci/api/barrios.php    — lista barrios activos (JSON)
│   ├── categorias.php     GET  /srci/api/categorias.php            — lista categorias de incidencia activas (JSON; ?visibles=1 = solo las marcadas para usuarios)
│   ├── geocode.php        GET  /srci/api/geocode.php?lat=&lon= — geocodificacion inversa (punto -> direccion) via
│   │                      Nominatim/OSM, con proxy + cache en archivos (sys_get_temp_dir/srci-geocode, 30 dias, redondeo a
│   │                      4 decimales). Devuelve "calle numero" o solo "calle" si no hay altura; si no hay calle responde
│   │                      {ok:false} (la ciudad no se incluye, todas las direcciones son de Concepcion del Uruguay).
│   ├── poligonos.php      GET  /srci/api/poligonos.php  — poligonos de barrios activos: [{id, nombre, categoria, color, anillos}]
│   ├── reportes.php       GET  /srci/api/reportes.php   — lista incidencias con filtros opcionales (solo con coordenadas; es el feed del mapa)
│                          POST /srci/api/reportes.php   — crea nueva incidencia (JSON body)
│   ├── subir_foto.php     POST /srci/api/subir_foto.php — sube foto (multipart/form-data)
│   ├── estado.php         POST /srci/api/estado.php     — cambia estado de incidencia (solo admin, JSON body)
│   ├── aprobar_incidencia.php POST /srci/api/aprobar_incidencia.php — aprueba o rechaza un reporte pendiente de la red de
│   │                      colaboradores (JSON body + CSRF por header X-CSRF-Token; en el modo actual solo admins).
│   │                      Al rechazar exige "motivo" (max 500) y guarda rechazado/rechazado_en/rechazado_por/motivo_rechazo;
│   │                      al aprobar limpia ese estado.
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
                  notas, fecha_hora, estado(pendiente|en_proceso|resuelto), oculto(0|1), fecha_resolucion,
                  aprobado(TINYINT 1 UNSIGNED, default 1), aprobado_en(DATETIME NULL), aprobado_por(FK usuarios, NULL),
                  propietario_id(FK usuarios, NULL) — red de colaboradores (ver abajo)
colaboradores     id, propietario_id(FK usuarios CASCADE), colaborador_id(FK usuarios CASCADE),
                  estado(ENUM aprobado|rechazado, default aprobado), creado_en, creado_por(FK usuarios SET NULL) —
                  UNIQUE (propietario_id, colaborador_id). Es la red: el propietario designa colaboradores;
                  los reportes de un colaborador nacen con aprobado=0 y propietario_id=su propietario.
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
incidencia_seguimiento id, incidencia_id(FK CASCADE), usuario_id(FK SET NULL), nota(TEXT NOT NULL),
                  fecha_hora(DATETIME default CURRENT_TIMESTAMP) — APPEND-ONLY: notas fechadas de seguimiento.
                  Solo INSERT (nunca UPDATE/DELETE desde la UI) = trazable. Guarda quien y cuando anoto.
                  indice idx_seguimiento_incidencia(incidencia_id).
```

**Regla:** nunca alterar el esquema sin proponer la migracion SQL primero y esperar confirmacion del humano.

### Datos semilla (ya aplicados)

11 tipos de incidencia con `icono` emoji (español): `🌳` arbol_caido, `🕳️` alcantarilla, `🏚️` vivienda_precaria, `🏠` techo_riesgo, `⚡` cableado, `💧` fuga_agua, `🗑️` basural, `📋` otro, `💡` caida_poste, `🌧️` anegamiento, `🌊` inundacion.  
Barrios: 44 sembrados con los barrios reales del relevamiento (Los Palos, Circuito 5, San Roque, La Quilmes, Villa Itapé, etc. — lista completa en `sql/migracion_usuarios_barrios.sql`), editables desde `/srci/admin/barrios.php`.  
Referentes por barrio: tabla `referentes_barrios` (muchos a muchos) — asignados por el script de carga masiva; los referentes que faltan por email se cargan cuando completen datos.  
Usuario admin inicial: nombre=`admin`, PIN=`0000` (password_hash de PASSWORD_DEFAULT).  
Usuarios del relevamiento: cargados por script one-shot con username = email; el 10/10/2026 se aplico la planilla completa (`sql/actualizacion_nombres_planilla.sql`): **nombres reales** en `nombre`, **login SOLO por email** (`login.php` matchea `email = :login`; el `nombre` es solo display), y 11 referentes sin email cargados como inactivos con `email NULL` (cuando aporten email: admin lo edita en usuarios.php y usa "Activar + PIN"). Asignaciones de barrio en `referentes_barrios` (visible como columna "Barrios" en usuarios.php). Emails alternativos de la planilla (salamoniniangel@, marclaymarianela@, mosquedamario774@, pipoaminero@) no cargados: solo hay una columna email. PIN por email desde `admin@echo3dlaser.com.ar` (`enviar_pin_por_email()`). 4 admins: Guillaume, Marclay, Garay, admin. Despliegue por etapas: solo activos pueden loguear.

**Sesion larga (auth.php):** `SESION_DURACION` = 90 dias, cookie persistente + **sliding** (`renovar_sesion()` en `requiere_sesion()` renueva la cookie en cada request) + `session.gc_maxlifetime` = 90 dias. La sesion permanece viva hasta logout explicito (`logout.php` destruye sesion y borra cookie). Si se quiere mas/menos, ajustar `SESION_DURACION`.

**Anti fuerza bruta en el login (auth.php, 2026-10-14):** el PIN de 4 digitos tiene solo 10.000 combinaciones, asi que `login.php` limita reintentos con contadores en ARCHIVOS de `sys_get_temp_dir()` (patron cache-en-archivos como el geocode; sin tocar el esquema de DB; escritura atomica con flock). Dos llaves simultaneas: `email|IP` (5 fallos en 15 min → bloqueo 15 min, ataca una cuenta) y `ip|IP` (30 fallos en 15 min → bloqueo 15 min, spray de muchos emails desde una IP). La ventana se resetea al expirar; el bloqueo NO se extiende con nuevos intentos (evita DoS al usuario legitimo). Helpers: `login_bloqueado($ip, $email)` (chequea antes de verificar el PIN), `login_registrar_fallo()` (cuenta "usuario no encontrado" y "PIN incorrecto" + demora random 100-300 ms), `login_registrar_exito()` (limpia la llave email|IP; el contador global por IP se conserva). El mensaje de bloqueo es generico ("Demasiados intentos fallidos. Esperá unos minutos...") sin revelar tiempo exacto ni si el email existe. GC: 1% de los requests borra archivos con mas de 1 dia. Si tmp no es escribible, degrada sin romper el login. Probado en prod: 5 fallos → 6to request bloqueado.

**Auditoria del bloqueo (2026-10-15):** la activacion del bloqueo se registra en `auditoria` con accion `bloqueo_login` (detalle: email/IP/fallos/duracion; solo en la transicion a bloqueado, no cada fallo extra) y los intentos rechazados con bloqueo activo se registran como `acceso_bloqueado` con **throttle de 1 fila por minuto** por email+IP (para que un atacante no llene la tabla). Ambos con `usuario_id NULL` (no hay sesion): `registrar_auditoria()` ahora acepta `?int $usuario_id`. Mapeados en `admin/auditoria.php` (`accion_legible`/`clase_accion`, badge rojo aud-ocultar). El detalle usa duracion relativa ("Bloqueado por 15 minutos"), NO hora del reloj: PHP corre en UTC y MySQL en UTC-3, un `date('H:i')` mostraria una hora inconsistente con `fecha_hora` de la tabla.

**Perfil privado del usuario (`perfil.php`):** cualquier usuario logueado (`requiere_sesion()` + CSRF en el POST) ve sus datos (nombre, email, rol, estado, alta), sus barrios asignados (`referentes_barrios`) y sus reportes (`incidencias WHERE usuario_id = sesion AND oculto = 0`). Puede **cambiar su propio PIN**: exige el PIN actual (`password_verify`), valida `/^\d{4}$/` + repeticion, rechaza igual-al-actual, y audita `cambiar_pin`. **No** se edita el email (login) desde el perfil: solo un admin lo cambia en `admin/usuarios.php`. Avatar **Gravatar** read-only via `md5(strtolower(trim(email)))` (`?s=160&d=404`) con fallback local de iniciales; no se sube foto propia. Se llega tocando el chip `👤 <nombre>` de la barra (link en todas las paginas autenticadas). `admin/auditoria.php` mapea `cambiar_pin` en `accion_legible()`/`clase_accion()`.

**Activacion masiva de usuarios (`admin/usuarios.php`, tarjeta `#lote`, solo admins):** boton "🚀 Activar y enviar link (lote de 10)". Cada click procesa un lote de 10 usuarios inactivos (`LOTE_LINKS_TAMANO`) que cumplan `$SQL_LOTE_WHERE` (`activo=0 AND rol='usuario' AND email presente AND email NOT LIKE '%@%.local'` — excluye admins y emails no ruteables como la cuenta semilla `admin`). Por usuario: `activo=1` + PIN nuevo (`generar_pin()`), magic link de 30 dias (`generar_magic_link()`) y email via `enviar_activacion_por_email()` (boton "Entrar al mapa" + PIN de respaldo para cuando venza). Verificable: tabla del ultimo lote con enviado/fallo por usuario (flash en `$_SESSION['lote_links_resultado']` + redirect a `#lote` para no reenviar al recargar). Auditable: cada activacion registra `activar` con detalle "Lote masivo: activo a X (<email>) — PIN nuevo + link de acceso (30 dias)" (+"— EMAIL FALLO" si falla); la tarjeta muestra el historial de los ultimos 10 lotes desde `auditoria` y el contador de pendientes (44 con email / 11 sin email al 11/10/2026; los sin email se activan cargando el email en la tabla y usando "Activar + PIN").

**Edicion de barrios y poligonos (`admin/barrio.php`):** pagina de detalle de un barrio (accesible con ✏️ Editar desde `admin/barrios.php`) que muestra info reversa (datos, conteos de incidencias por estado y gravedad, familias afectadas, sin ubicacion, incidencia abierta mas antigua, ultimas 12 incidencias con link a `detalle.php` y referentes asignados) y permite editar **nombre/estado/categoria/color/poligono**. La **categoria** (select: sin categoría / Formal (catastro) / Informal / Asentamiento) y el **color** (`<input type="color">` con boton "Usar color de la categoria") se guardan en `barrios.categoria` / `barrios.color`; el color se valida con `color_valido()` (`^#[0-9a-fA-F]{6}$`, lowercase) y si queda NULL el mapa usa `color_categoria(categoria)`. El color se ve en vivo sobre el poligono dibujado (`drawnItems.setStyle`) y NO activa `mapaModificado` (o sea, cambiar solo el color no reenvia el poligono). El poligono se carga de tres formas que convergen en GeoJSON: subir archivo `.geojson/.json`, pegar texto, o dibujar/editar en el mapa (Leaflet + Leaflet.draw, `featureGroup` `drawnItems`); el mapa de edicion usa **capa satelite Esri World Imagery** por defecto con selector Mapa/Satelite (`L.control.layers`). `anillos_a_geojson()` exporta el actual (boton ⬇ Descargar); `geojson_a_anillos()` normaliza lo entrante: acepta `FeatureCollection`/`Feature`/`Polygon`/`MultiPolygon`, convierte `[lng,lat] -> [lat,lng]`, aplana multipoligonos a anillos y **descarta huecos** (el ray-casting no los soporta); limites 2 MB / 20000 vertices. Se guarda en `barrios.poligono` (JSON `[[[lat,lng],...],...]`). El boton 🗑 Limpiar pone `poligono = NULL`. El boton 🔁 Recalcular corre `barrio_por_punto()` sobre las incidencias con `barrio_id IS NULL AND latitud IS NOT NULL` y les asigna barrio (solo local, no empuja a Sheets). Audita `editar` (con detalle de cambios) y `recalcular_barrios`.

**Alineacion con la planilla de relevamiento** (`Planilla_relevamiento_barrios.xlsx`): el formulario pide Barrio, Direccion/calle y altura, Gravedad (obligatorios) y Familias afectadas, Servicio afectado, Calle intransitable, Responsable/area, Contacto vecino (opcionales). "Dias abiertos" se calcula (fecha_hora → fecha_resolucion o hoy). Estado: "En gestion" = `en_proceso`.

**Espejo bidireccional con Google Sheets (planilla de relevamiento, gid 73522773):** via Apps Script Web App (`SRCI_SHEETS_WEBAPP_URL` + `SRCI_SHEETS_SECRET` en config.local.php). Ida (app → hoja): cada reporte/foto/cambio de estado envia fire-and-forget (`enviar_a_sheets()`, funciones.php; Apps Script responde 302 y el echo final se llama con GET — el payload viaja en user_content_key). Vuelta (hoja → app): el script `sincronizarEntrada()` (trigger cada 5 min) escanea filas de datos **sin tag en col T** y las importa via `api/sheets_entrada.php` (accion 'importar'); al importar escribe tag `APP-<id>` en col T — ese tag es el dedupe: filas de la app ya nacen taggeadas, filas importadas quedan taggeadas, ERROR-xxx si la importacion falla. Coordenadas de importados = NULL: no aparecen en el mapa (api/reportes.php GET filtra `latitud IS NOT NULL`), cuentan en `api/sin_ubicacion.php` (chip "📍 N sin ubicación" en la botonera del mapa) y se ubican desde editar.php; barrio inexistente se crea; "Relevado por" matchea usuario por nombre O email, fallback usuario dedicado 'Planilla' (inactivo). Cambiar el codigo del script requiere "Nueva version" en Administrar implementaciones.

**Categorias de incidencia (`categorias_incidencia` + `incidencia_categorias`):** etiquetas **independientes del tipo** y **no excluyentes** (una incidencia puede tener varias). Se eligen en el modal de reporte (`index.php` + `reporte.js`: chips multiseleccion en el Paso 2, **Climáticos pre-marcada**), se editan en `editar.php` (checkboxes `categorias[]`) y se muestran como badges en `incidencias.php` (columna + fila expandida), `admin/reportes.php` (columna), `detalle.php` y el popup del mapa. **El selector es SOLO para admins (decision del cliente 2026-10-11, "por ahora"):** `index.php` renderiza el campo solo si `$es_admin || hay_categorias_visibles()`; `index.php` inyecta `window.SRCI_ES_ADMIN` y `window.SRCI_MOSTRAR_CATEGORIAS` y `reporte.js`/`mapa.js` gatean con eso (admins: `/api/categorias.php`; usuarios: `/api/categorias.php?visibles=1`, y si no hay visibles no se renderiza nada). El admin enciende categoria por categoria con el switch **"👁 Mostrar a usuarios"** de `admin/categorias.php` (`visible_usuario`, columna "Usuarios", checkbox en crear/editar, accion `toggle_visibilidad`, audita "visible/oculta para usuarios"). `api/reportes.php` POST acepta `categorias: [int]` (valida activas; si viene vacio aplica la por defecto) y su GET adjunta `categorias: [{id,clave,nombre,icono,color}]` a cada incidencia (segunda query via `categorias_por_incidencia()`). `incidencias.php` permite filtrar por `categoria_id` (subquery `EXISTS`) y exporta las categorias en CSV (`Categorías`) y GeoJSON (`properties.categorias`). El mapa filtra por categoria con un **dropdown** en `#filtros-mapa` (combina AND con tipo/mis reportes; `mapa.js` guarda `reg.categoriaIds`). La importacion de la planilla (`api/sheets_entrada.php`) asigna **Climáticos**. Helpers en `funciones.php`: `categorias_activas()`, `categorias_visibles()`, `hay_categorias_visibles()`, `categoria_default_id()`, `categorias_de_incidencia($id)`, `guardar_categorias_incidencia($id, $ids)`, `categorias_por_incidencia($ids)`.

**Seguimiento de incidencias (`incidencia_seguimiento`, 2026-10-13):** cada usuario **dueño del reporte** (o un admin) puede dejar **notas fechadas** de seguimiento sobre una incidencia (ej: "Hoy 10 de agosto no pasó el camión a juntar la basura"). La tabla es **APPEND-ONLY**: solo INSERT, nunca se editan ni borran desde la UI, por lo que es **trazable** (cada fila guarda `usuario_id` + `fecha_hora`). Se agregan desde el timeline de `detalle.php` (tarjeta "🕓 Seguimiento", ancla `#seguimiento`; POST acción `agregar_nota` con CSRF, valida dueño/admin, nota no vacía ≤ 1000) y desde el formulario rápido por reporte en `perfil.php` (columna "Seguimiento" con conteo + `<details>` que se despliega con el textarea). Ambos insertan y luego **auditan**: `registrar_auditoria(uid, 'seguimiento', $incidencia_id, 'Agrego nota de seguimiento: <extracto 200>')` (visible en `admin/auditoria.php`, mapeado en `accion_legible()`/`clase_accion()` como "Nota de seguimiento"). Helpers en `funciones.php`: `seguimiento_de_incidencia($id)` (lista con autor, asc) y `contar_seguimiento_por_incidencia($ids)` (conteo por incidencia para badges, una query GROUP BY). Requiere correr `sql/migracion_seguimiento.sql`.

**Red de colaboradores (`colaboradores` + `incidencias.aprobado`, 2026-10-29 — IMPLEMENTADA, MODO ADMIN-ONLY):** cada usuario puede tener una red de colaboradores. El vinculo se crea **ya aprobado** (lo crea el propietario de la red o un admin; no requiere aprobacion de la vinculacion) y lo que se aprueba es **cada reporte** del colaborador: nace con `aprobado = 0` y `propietario_id = <propietario de su grupo>`; el propietario del grupo (o un admin) lo aprueba o lo rechaza (rechazar = `oculto = 1`, soft delete, nunca borrado fisico). Reportes de usuarios **no** colaboradores se publican de inmediato (`aprobado = 1`).

**MODO ACTUAL: SOLO ADMINS (`SRCI_COLABORADORES_SOLO_ADMIN = true` en `includes/funciones.php`).** Hasta nuevo aviso la red la gestionan unicamente los admins: solo ellos designan/quitan colaboradores y solo ellos aprueban/rechazan. El flag en `false` abre la funcionalidad a todos los usuarios (cada uno con su propia red) sin tocar el codigo. Implementacion:
- Helpers en `includes/funciones.php`: `puede_gestionar_colaboradores()`, `puede_gestionar_aprobaciones()`, `es_colaborador()`, `propietario_de_colaborador()`, `ids_grupo_propietario()`, `colaboradores_de()`, `incidencias_pendientes_de_aprobacion()`, `condiciones_visibilidad_incidencias()`, `agregar_colaborador()`, `quitar_colaborador()`, `notificar_propietario_por_email()`. Cache en `$GLOBALS['srci_cache_colaboradores']` (se invalida al alta/baja de vinculos).
- **Una sola red por usuario:** `colaboradores.colaborador_id` tiene UNIQUE (`uq_colaborador`, migracion `sql/migracion_colaboradores_red_unica.sql`). `agregar_colaborador()` rechaza el alta si el usuario ya pertenece a otra red, y un admin no puede ser colaborador (sin anidar).
- Creacion del reporte (`api/reportes.php` POST): si el creador `es_colaborador()` -> `aprobado = 0` + `propietario_id` = su propietario; si no -> `aprobado = 1` + `propietario_id` = el propio creador.
- **Publicacion:** los reportes pendientes NUNCA van al mapa (`api/reportes.php` GET filtra `i.aprobado = 1`) ni a las exportaciones CSV/GeoJSON (`incidencias.php` exporta solo `aprobado = 1`). Se ven solo en el listado del admin y en el panel de aprobacion.
- Visibilidad (filtro SIEMPRE en servidor): admin ve todas (incluidas pendientes); el resto ve las **aprobadas** + sus **propias pendientes** (`condiciones_visibilidad_incidencias()`). `detalle.php` y `editar.php` devuelven 404 a un reporte pendiente para quien no es admin ni su autor.
- UI: `perfil.php` tiene las tarjetas "Mis colaboradores" (alta por email / baja) y "Reportes pendientes de aprobacion" (botones Aprobar/Rechazar), visibles solo para quien puede gestionar la red. `detalle.php` muestra el badge "⏳ Pendiente de aprobación" y, para admins, los botones Aprobar/Rechazar. `incidencias.php` y `admin/reportes.php` muestran el badge "⏳ Pendiente". Clase CSS `.badge-aprobacion`.
- Endpoint nuevo `api/aprobar_incidencia.php`: POST JSON `{incidencia_id, accion: 'aprobar'|'rechazar'}` + token CSRF por header `X-CSRF-Token` (helper `csrf_token_valido()` en `includes/auth.php`). Aprobar -> `aprobado=1, aprobado_en=NOW(), aprobado_por`; rechazar -> `aprobado=0, oculto=1`. Audita `aprobar` / `rechazar` y avisa al creador por email (eventos `aprobar` / `rechazar` de `notificar_creador_por_email()`).
- Auditoria: `agregar_colaborador`, `quitar_colaborador`, `aprobar`, `rechazar` (mapeados en `admin/auditoria.php`). La baja de un vinculo NO borra: queda `estado = 'rechazado'`.
- Esquema: tabla `colaboradores` (`propietario_id`, `colaborador_id`, `estado` ENUM aprobado|rechazado, `creado_en`, `creado_por`) con UNIQUE (propietario_id, colaborador_id) y FKs CASCADE a `usuarios`; en `incidencias`: `aprobado` TINYINT(1) UNSIGNED default 1, `aprobado_en` DATETIME NULL, `aprobado_por` INT NULL (FK SET NULL), `propietario_id` INT NULL (FK SET NULL). **Ojo con los tipos:** el esquema real usa `usuarios.id` = INT(11) **con signo**, asi que las columnas y FKs deben ser INT con signo (un INT UNSIGNED no puede referenciar un INT con signo en MariaDB). Backfill de las 57 incidencias existentes: `aprobado = 1` y `propietario_id = usuario_id`. Migraciones idempotentes en `sql/migracion_colaboradores.sql`, `sql/migracion_aprobacion_incidencias.sql` (las FKs se crean con SQL dinamico porque MariaDB no acepta `ADD CONSTRAINT IF NOT EXISTS`) y `sql/migracion_backfill_aprobado.sql` (UNA sola vez).

**Notificaciones por email al creador (2026-10-14):** cada evento sobre una incidencia le llega por email al creador (`incidencias.usuario_id` → `usuarios.email`), lo haya hecho el mismo o un admin: **crear** (`api/reportes.php` POST, siempre), **editar** (`editar.php`, SOLO si cambiaron campos o categorias — no envia si "Guardo sin cambios"), **cambio de estado** (`api/estado.php` y `admin/reportes.php`, SOLO si el estado realmente cambio) y **ocultar/restaurar** (`detalle.php` POST, `incidencias.php` POST — tiene SU PROPIO handler de ocultar/restaurar, no es el mismo que detalle.php, y la rama `restaurar` de `admin/reportes.php`). Helper `notificar_creador_por_email($incidencia_id, $evento, $detalle = '')` + `email_ruteable()` en `funciones.php`: una query trae incidencia + creador; si el creador no tiene email ruteable (NULL o `*.local`, misma regla que la activacion masiva) o el evento es desconocido, no envia; nunca rompe el flujo (patron fire-and-forget igual que `enviar_a_sheets`). El email usa el mismo estilo de PIN/activacion (header indigo, multipart/alternative base64, From `admin@echo3dlaser.com.ar` + envelope `-f`), incluye boton "Ver el reporte" a `detalle.php?id=N`, resumen (tipo/direccion/barrio/gravedad/estado), el detalle opcional (ej: "Estado: Pendiente → Resuelto") y quien lo hizo ("Lo hiciste vos desde tu cuenta" vs "Lo hizo X (organizador/referente)"). `mail()` es sincrono (~ms por envio, mismo costo que el envio de PIN). Probado en prod: guard con email NULL devuelve false y envio real OK (destinatario admin).

**Ocultar (soft delete) + auditoria (REGLAS):** las incidencias **nunca se borran** de la BD. "Eliminar" = pasar a `oculto = 1` (solo admins, con CSRF y confirm). El mapa, listado y exportaciones filtran `oculto = 0`; el admin ve/restaura las ocultas desde `admin/reportes.php?ocultas=1`. Toda accion (crear, editar con detalle de campos cambiados, cambiar estado, ocultar, restaurar, subir foto, **agregar nota de seguimiento**) se registra en la tabla `auditoria` con `registrar_auditoria()` (`includes/funciones.php`) — visible en `/srci/admin/auditoria.php`.

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
**Response:** `[{id, nombre, referentes}, ...]` — barrios activos por nombre; `referentes` = nombres (GROUP_CONCAT) de los referentes asignados al barrio (referentes_barrios + usuarios, activos o no: el listado 2027 manda); lo usa el reporte para precargar el contacto

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

### POST /srci/api/aprobar_incidencia.php
**Auth:** sesion activa + quien puede aprobar (modo actual: solo admin)  
**Body JSON:** `{incidencia_id: int, accion: "aprobar"|"rechazar", motivo?: string}` + token CSRF en el header `X-CSRF-Token` (o `csrf_token` en el body)  
**Comportamiento:** `aprobar` -> `aprobado=1, aprobado_en=NOW(), aprobado_por=<quien aprueba>` y limpia `rechazado/rechazado_en/rechazado_por/motivo_rechazo`; `rechazar` -> `aprobado=0, oculto=1, rechazado=1, rechazado_en=NOW(), rechazado_por=<quien rechaza>, motivo_rechazo=<motivo obligatorio, max 500>` (soft delete: nunca se borra fisicamente). Audita `aprobar`/`rechazar` (el detalle de la auditoria incluye el motivo) y avisa al creador por email  
**Response:** `{ok: true, accion: string, id: int}` | `{error: string}`

### Reglas de la red de colaboradores (decisiones 2026-10-30)
- **Una sola red por usuario:** `colaboradores.colaborador_id` es UNIQUE. `agregar_colaborador()` rechaza con mensaje claro si el usuario ya pertenece a otra red. Un admin no puede ser colaborador (no hay anidamiento de redes).
- **Rechazo visible:** el reporte rechazado no se borra ni se oculta para su autor: queda con `rechazado=1` + `motivo_rechazo`, y `perfil.php` ("Mis reportes") lo lista aunque `oculto=1` (filtro `i.oculto = 0 OR i.rechazado = 1`) mostrando el badge "✖ Rechazado" y el motivo. `detalle.php` muestra el mismo badge y el motivo. Para el resto de los usuarios sigue oculto (404).
- **Aviso al propietario:** `notificar_propietario_por_email()` (en `includes/funciones.php`) manda un email al `propietario_id` de la red cuando entra un reporte pendiente (`aprobado=0`), llamado desde `api/reportes.php` (POST). No interrumpe el flujo si el mail falla.
- **Editar = autor o admin:** `editar.php` devuelve 403 si quien edita no es el autor del reporte ni admin (antes cualquier usuario logueado podia editar cualquier incidencia) y 404 para pendientes ajenos. Los botones "✏️ Editar" de `incidencias.php` y el enlace "Editar incidencia" de `detalle.php` solo se muestran a autor/admin.
- **Restaurar limpia el rechazo:** al restaurar un reporte oculto (`detalle.php`, `incidencias.php`, `admin/reportes.php`) se borran `rechazado/rechazado_en/rechazado_por/motivo_rechazo`; el reporte vuelve a quedar pendiente de aprobacion.

---

## 7. Flujo del modal de reporte (JS) — 3 PASOS (rediseño 2026-10-13)

El modal en `index.php` tiene 3 pasos gestionados por `reporte.js` (`mostrarPaso('ubicacion'|'tipo'|'detalles')`, cada paso con su indicador "Paso N de 3"):

1. **Paso 1 — Ubicación** (`#paso-ubicacion`): SIN mini-mapa (eliminado: confundia; la posicion se elige tocando el mapa principal, que abre el modal con el punto precargado). Muestra las coordenadas y el boton "🗺️ Elegir / corregir la ubicacion en el mapa" (`#btn-elegir-mapa`: cierra el modal + `avisoMapa()` para tocar el mapa, que lo reabre con el punto). **Barrio detectado** y **dirección detectada** se completan al marcar el punto. El botón "Siguiente →" (`#btn-siguiente-ubicacion`) se habilita solo con punto + barrio + dirección (`actualizarBtnUbicacion()`).
1b. **Contacto vecino precargado (2026-10-13):** `api/barrios.php` devuelve `referentes` (nombres de referentes activos por barrio) y `reporte.js` (`autocompletarContacto()`) precarga el campo "Contacto vecino / referente" del paso 3 al elegir el barrio (al detectarlo por poligono o al escribirlo). NO pisa lo escrito a mano: solo reemplaza si el valor actual fue autocompletado (flag `contactoAutoPrevio`, se apaga al tipear).
2. **Paso 2 — Tipo** (`#paso-tipo`): grilla de iconos de `/api/tipos.php`. **Al tocar un icono avanza solo** al paso 3 (`seleccionarTipo()` → `mostrarPaso('detalles')`), sin botón intermedio. Botón "← Volver" al paso 1 (`#btn-volver-tipo`).
3. **Paso 3 — Gravedad y datos** (`#paso-detalles`): **gravedad como BOTONERA de 4 colores** (`.botonera-gravedad` con `.btn-gravedad` + `data-valor`, hidden input `#gravedad-reporte`), luego categorías (solo admins, ver sección Categorías), familias, servicio, responsable, calle intransitable, contacto, notas, foto. Botón "← Volver" al paso 2 (`#btn-volver`) y "Enviar reporte" (POST `/api/reportes.php` + foto a `/api/subir_foto.php`).

**Deteccion de barrio con poligonos superpuestos (2026-10-13):** entre todos los poligonos que contienen el punto gana el de **MENOR area** (shoelace en grados^2), en el cliente (`reporte.js` `barrioPorPunto()`/`areaAnillo()`) y en el server (`funciones.php` `barrio_por_punto()`/`area_anillo_barrio()`); ej: Las Mandarinas (informal, interna) dentro de Villa Itape devuelve Las Mandarinas.

Validación cliente: barrio, dirección y gravedad obligatorios (gravedad valida contra la lista baja/media/alta/critica; además del punto en el mapa).

**Reporte por click en el mapa:** al hacer click sobre el mapa principal (`mapa.js`), se coloca un marcador de selección (`crearIconoSeleccion`, anillo indigo) y se abre el modal con esas coordenadas precargadas via `window.abrirReporteConCoordenada(latlng)` (expuesta por `reporte.js`). En el paso 2 el mini-mapa ya queda centrado en ese punto con su marcador; el usuario puede ajustarlo tocando el mini-mapa. Al cerrar el modal, `cerrarModal()` limpia el marcador del mapa principal via `window.removerMarcadorSeleccion()`.

**Geolocalización del dispositivo:** control "🎯 Mi ubicación" (`L.Control` propio en `mapa.js`, arriba a la derecha) que usa la Geolocation API nativa (`navigator.geolocation.getCurrentPosition`). Al tocarlo centra siempre en la posición, muestra el marcador azul pulsante (`.puntero-ubicacion`) y un `L.circle` con el radio de precisión (`coords.accuracy`). No se dispara automáticamente al cargar; los errores (permiso/no disponible/timeout) se avisan con un toast discreto (`.aviso-mapa`). Todo es client-side: no se envía nada al servidor.

**Geocodificacion inversa (punto -> direccion, 2026-10-13):** al marcar el punto en el mini-mapa (o precargarlo por click en el mapa principal), `reporte.js` (`colocarPunto()` -> `sugerirDireccion()`, con AbortController para cancelar pedidos previos) consulta `api/geocode.php` (proxy a Nominatim/OSM con User-Agent identificable, timeout 4s y cache en archivos 30 dias; politica de Nominatim: max 1 req/seg). Sugiere "calle numero" o solo "calle" si no hay altura; sin calle no sugiere nada (el campo queda manual). Si el campo Direccion esta VACIO se autocompleta; si ya tiene texto se muestra el boton "📍 Usar direccion detectada: ..." que no pisa lo escrito. Si OSM falla, todo queda manual como siempre (el envio no depende del geocode). resetearModal limpia la sugerencia y aborta pedidos pendientes.

El mapa principal en `mapa.js` expone `window.mapaLeaflet` para que `reporte.js` pueda leer el centro actual al inicializar el mini-mapa.

**Modal de reporte RECONSTRUIDO (2026-10-13, a prueba de caches):** tras 3 intentos con CSS (hoja inferior, fullscreen <900px, fullscreen fixed+inset) se descubrio que el telefono del cliente (Android con tamano de pantalla reducido, layout ~617px CSS) y/o su navegador no aplicaban de forma confiable las reglas nuevas. Solucion definitiva: modal reconstruido con **ids/clases NUEVOS y geometria INLINE** - <div id= + 'modal-reporte' (overlay fixed, top/left, width:100% height:100%, opacity/pointer-events inline) + <div id= + 'panel-reporte' (fixed, width/height 100%, padding 16, overflow-y auto, overflow-x hidden, transform translateY(100%) para la animacion). **Ninguna regla vieja de estilos.css puede afectarlo** (el markup del reporte ya no usa .modal-fondo/.modal-caja; esas clases quedan solo para los modales admin .modal-centrado). eporte.js abre/cierra con estilos inline (style.opacity/pointerEvents/transform), sin clases. Encabezado sticky inline. Arrastre eliminado. Titulo con etiqueta de build visible (v20261013) para diagnosticar caches. Ademas: body { overflow-x: hidden } y .contenedor-mapa { overflow: hidden } para que ningun desborde horizontal (ej. cinta de filtros) expanda el layout. En >=900px #paso-detalles va en 2 columnas dentro del panel.

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

### 11.2 Service Worker DESACTIVADO (decision 2026-10-12)

El SW (`sw.js`, antes network-first con cache `srci-vN`) fue **eliminado** porque los clientes con el SW viejo seguian viendo CSS/JS viejos aunque recargaran (el SW persiste aunque cierren el navegador). Estado actual:

- `index.php` **NO registra** ningun Service Worker.
- `sw.js` es un **autodesinstalador**: quien lo tenga instalado, al hacer el chequeo de actualizacion (proxima navegacion; el etag del archivo cambio), borra TODOS los caches y se desregistra. No tiene fetch handler: no intercepta nada.
- La frescura se garantiza con **HTTP cache normal**: los assets de `index.php` llevan `?v=YYYYMMDD` (bumpear al cambiarlos) y las paginas PHP mandan `no-store` por sesion.
- NO volver a registrar un SW sin aprobacion del cliente (fue fuente de "no se actualiza" repetido). El modo offline/PWA basico quedo sin efecto.

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
