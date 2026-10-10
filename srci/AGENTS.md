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
| Sesiones    | session_start() nativo              | JWT, OAuth, librerias externas                  |
| Fotos       | Disco local en `/uploads/`          | S3, CDN externo                                 |

**Si una tarea requiere algo fuera de esta lista, detente y pregunta al humano antes de avanzar.**

---

## 3. Estructura de archivos (estado actual)

```
srci/
├── index.php              Mapa principal (Leaflet + modal de reporte en 2 pasos)
├── incidencias.php        Listado tabular con filtros, paginacion, filas expandibles (todos los campos), exportacion CSV/GeoJSON
├── detalle.php            Detalle de una incidencia + cambio de estado para admin
├── editar.php             Edicion completa de una incidencia (todos los campos, con CSRF)
├── login.php              Autenticacion por nombre + PIN de 4 digitos (teclado virtual)
├── logout.php             Destruye la sesion y redirige a login.php
├── error403.php           Pagina de error para acceso denegado (incluida por auth.php)
├── manifest.json          PWA manifest (icons: assets/img/icon-192.png y icon-512.png)
├── sw.js                  Service Worker — cache estatico basico para offline
├── .htaccess              Bloquea listado de directorio y acceso a /includes/
│
├── sql/
│   ├── esquema.sql        Esquema completo de DB + datos semilla de tipos, barrios y usuario admin
│   ├── migracion_planilla.sql  Migracion: tabla barrios + campos nuevos en incidencias + 3 tipos nuevos
│   └── migracion_usuarios_barrios.sql  Migracion: barrios reales + tabla referentes_barrios
│
├── includes/
│   ├── db.php             Conexion PDO singleton (funcion db()), respuesta_json()
│   ├── auth.php           Funciones de sesion: requiere_sesion(), requiere_admin(), csrf_token(), validar_csrf()
│   └── funciones.php      Helpers: esc(), fecha_legible(), generar_pin(), guardar_foto(), enviar_pin_por_email()
│
├── api/
│   ├── tipos.php          GET  /srci/api/tipos.php      — lista tipos activos (JSON)
│   ├── barrios.php        GET  /srci/api/barrios.php    — lista barrios activos (JSON)
│   ├── reportes.php       GET  /srci/api/reportes.php   — lista incidencias con filtros opcionales
│                          POST /srci/api/reportes.php   — crea nueva incidencia (JSON body)
│   ├── subir_foto.php     POST /srci/api/subir_foto.php — sube foto (multipart/form-data)
│   └── estado.php         POST /srci/api/estado.php     — cambia estado de incidencia (solo admin, JSON body)
│
├── admin/
│   ├── reportes.php       Panel admin: listado de reportes + cambio de estado via form POST
│   ├── usuarios.php       Panel admin: crear usuarios, editarlos (nombre/email/rol), regenerar PIN, activar/desactivar
│   ├── tipos.php          Panel admin: crear tipos de incidencia, activar/desactivar
│   ├── barrios.php        Panel admin: crear barrios, activar/desactivar
│   └── auditoria.php      Panel admin: log de auditoria (quien, cuando, que hizo)
│
├── assets/
│   ├── css/estilos.css    Sistema de diseno completo (variables CSS, dark mode, responsive)
│   └── js/
│       ├── login.js       Logica del teclado PIN numerico en login.php
│       ├── mapa.js        Inicializacion Leaflet, carga marcadores, iconos SVG por estado
│       └── reporte.js     Modal de reporte: paso 1 (tipo) → paso 2 (mini-mapa + foto + notas)
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
```

### Esquema

```sql
usuarios          id, nombre(UNIQUE), email, pin_hash, rol(usuario|admin), activo, creado_en
tipos_incidencia  id, clave(UNIQUE), nombre, icono, activo
barrios           id, nombre(UNIQUE), activo, creado_en
referentes_barrios usuario_id(FK CASCADE), barrio_id(FK CASCADE) — PK(usuario_id, barrio_id)
incidencias       id, usuario_id(FK), tipo_id(FK), latitud, longitud, barrio_id(FK barrios, NULL),
                  direccion, gravedad(baja|media|alta|critica), familias_afectadas,
                  servicio_afectado, calle_intransitable, responsable_area, contacto_vecino,
                  notas, fecha_hora, estado(pendiente|en_proceso|resuelto), oculto(0|1), fecha_resolucion
fotos             id, incidencia_id(FK CASCADE), ruta_archivo, fecha_subida
auditoria         id, usuario_id(FK SET NULL), accion(crear|editar|cambiar_estado|ocultar|restaurar|subir_foto),
                  incidencia_id, detalle, fecha_hora
```

**Regla:** nunca alterar el esquema sin proponer la migracion SQL primero y esperar confirmacion del humano.

### Datos semilla (ya aplicados)

11 tipos de incidencia con `icono` emoji (español): `🌳` arbol_caido, `🕳️` alcantarilla, `🏚️` vivienda_precaria, `🏠` techo_riesgo, `⚡` cableado, `💧` fuga_agua, `🗑️` basural, `📋` otro, `💡` caida_poste, `🌧️` anegamiento, `🌊` inundacion.  
Barrios: 44 sembrados con los barrios reales del relevamiento (Los Palos, Circuito 5, San Roque, La Quilmes, Villa Itapé, etc. — lista completa en `sql/migracion_usuarios_barrios.sql`), editables desde `/srci/admin/barrios.php`.  
Referentes por barrio: tabla `referentes_barrios` (muchos a muchos) — asignados por el script de carga masiva; los referentes que faltan por email se cargan cuando completen datos.  
Usuario admin inicial: nombre=`admin`, PIN=`0000` (password_hash de PASSWORD_DEFAULT).  
Usuarios del relevamiento: cargados por script one-shot con username = email; el 10/10/2026 se aplico la planilla completa (`sql/actualizacion_nombres_planilla.sql`): **nombres reales** en `nombre`, **login acepta nombre O email** (`login.php` matchea `nombre = :login OR email = :login`), y 11 referentes sin email cargados como inactivos con `email NULL` (cuando aporten email: admin lo edita en usuarios.php y usa "Activar + PIN"). Asignaciones de barrio en `referentes_barrios` (visible como columna "Barrios" en usuarios.php). Emails alternativos de la planilla (salamoniniangel@, marclaymarianela@, mosquedamario774@, pipoaminero@) no cargados: solo hay una columna email. PIN por email desde `admin@echo3dlaser.com.ar` (`enviar_pin_por_email()`). 4 admins: Guillaume, Marclay, Garay, admin. Despliegue por etapas: solo activos pueden loguear.

**Sesion larga (auth.php):** `SESION_DURACION` = 90 dias, cookie persistente + **sliding** (`renovar_sesion()` en `requiere_sesion()` renueva la cookie en cada request) + `session.gc_maxlifetime` = 90 dias. La sesion permanece viva hasta logout explicito (`logout.php` destruye sesion y borra cookie). Si se quiere mas/menos, ajustar `SESION_DURACION`.

**Alineacion con la planilla de relevamiento** (`Planilla_relevamiento_barrios.xlsx`): el formulario pide Barrio, Direccion/calle y altura, Gravedad (obligatorios) y Familias afectadas, Servicio afectado, Calle intransitable, Responsable/area, Contacto vecino (opcionales). "Dias abiertos" se calcula (fecha_hora → fecha_resolucion o hoy). Estado: "En gestion" = `en_proceso`.

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

### GET /srci/api/reportes.php
**Auth:** sesion activa  
**Params opcionales:** `tipo_id`, `estado`, `barrio_id`, `gravedad` (baja|media|alta|critica), `fecha_desde` (YYYY-MM-DD), `fecha_hasta` (YYYY-MM-DD)  
**Response:** array de incidencias con joins (tipo_nombre, tipo_icono, tipo_clave, barrio_nombre, usuario_nombre, foto y campos nuevos: direccion, gravedad, familias_afectadas, servicio_afectado, calle_intransitable, responsable_area, contacto_vecino, fecha_resolucion)  
**Limite:** 1000 registros (sin paginacion — para el mapa)

### POST /srci/api/reportes.php
**Auth:** sesion activa  
**Body JSON:** `{tipo_id: int, latitud: float, longitud: float, barrio_id: int, direccion: string, gravedad: "baja"|"media"|"alta"|"critica", familias_afectadas?: int, servicio_afectado?: string, calle_intransitable?: 0|1, responsable_area?: string, contacto_vecino?: string, notas?: string}`  
**Obligatorios:** tipo_id, latitud, longitud, barrio_id, direccion, gravedad  
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
2. **Paso 2** (`#paso-detalles`): grid 2 columnas en desktop (FullHD sin scroll) — barrio (select de `/api/barrios.php`), direccion, gravedad, familias afectadas, servicio afectado, responsable/area, calle intransitable (checkbox), contacto vecino — mas mini-mapa Leaflet para marcar coordenadas, textarea de notas, zona de foto. En movil: una columna.
3. Al enviar: primero POST a `/api/reportes.php`, luego (si hay foto) POST a `/api/subir_foto.php`. Validacion cliente: barrio, direccion y gravedad obligatorios.

**Reporte por click en el mapa:** al hacer click sobre el mapa principal (`mapa.js`), se coloca un marcador de selección (`crearIconoSeleccion`, anillo indigo) y se abre el modal con esas coordenadas precargadas via `window.abrirReporteConCoordenada(latlng)` (expuesta por `reporte.js`). En el paso 2 el mini-mapa ya queda centrado en ese punto con su marcador; el usuario puede ajustarlo tocando el mini-mapa. Al cerrar el modal, `cerrarModal()` limpia el marcador del mapa principal via `window.removerMarcadorSeleccion()`.

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
