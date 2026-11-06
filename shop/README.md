# ECHO 3D LASER — Sitio web

Clon funcional/estructural de `printingbruno.com` (sin datos de ellos), para el negocio propio de **impresión 3D y corte láser**. Estética **retro-futurista + tecnológica + artesanal** (hoja de estilo de referencia en la conversación del 2026-09-12).

## URLs

| Qué | URL |
|---|---|
| Sitio (pruebas) | https://echo3dlaser.com.ar/echo3dlaser/ |
| Admin | https://echo3dlaser.com.ar/echo3dlaser/admin/login.php |

- **Admin:** usuario `echo` / clave `EchoAdmin26!` (cambiar en `config.php` regenerando el hash con `password_hash()`).
- Los datos de la tienda (teléfono, dirección) son **de fantasía** hasta que el dueño los confirme.

## Stack

- Frontend: HTML + CSS custom (variables, `data-theme` heredado del diseño) + **JS vanilla**.
- Backend: **PHP 8.3 vanilla (vhost lsphp83 — SIN cURL; HTTP externo con stream_context + openssl) + PDO + MySQL (MariaDB 10.11)**.
- Librería externa: **Three.js r128** (CDN cdnjs) para el marquee de materiales.
- Sin framework, sin build tooling, sin npm.

## Estructura

```
echo3dlaser/
├─ index.php · catalogo.php · nosotros.php · contacto.php · mi-cuenta.php
├─ config.php            credenciales DB + datos del sitio + admin
├─ db.php                helper PDO + json_out() + wa_link()
├─ parts/header.php      head + nav (tienda)
├─ parts/footer.php      drawer carrito + WhatsApp float + footer + scripts
├─ api/
│  ├─ productos.php      GET ?destacados=1 | ?categoria=slug | ?id=
│  ├─ categorias.php     GET lista
│  ├─ marcas.php         GET lista de materiales (marquee)
│  └─ contacto.php       POST → inserta en mensajes_contacto + email a SITE_NOTIF_EMAIL
├─ admin/
│  ├─ login.php · logout.php
│  ├─ index.php          panel: stats + últimos mensajes
│  ├─ productos.php      listar / crear / editar / borrar / subir imagen
│  ├─ categorias.php     listar / crear / editar / borrar
│  ├─ mensajes.php       leer / marcar leído / borrar
│  └─ _auth.php · _layout.php · _foot.php · _helpers.php
├─ assets/css/styles.css   hoja de estilo completa (tienda + admin)
├─ assets/js/ products.js · cart.js · main.js (marquee Three.js)
├─ uploads/productos/     imágenes (SVG placeholder + foto subidas)
└─ sql/schema.sql · seed.sql
```

## Base de datos (VPS)

- DB `echo_shop` / usuario `echo_admin` / clave en `config.php`.
- Tablas: `categorias`, `productos` (slug NOT NULL único, FK a categorias ON DELETE SET NULL), `marcas`, `mensajes_contacto`.
- **Plan MP (no implementado todavía):** tabla `pedidos` + columna `productos.link_pago` (ver sección Plan Mercado Pago abajo).

## Plan Mercado Pago (aprobado 2026-09-12 — SIN implementar aún)

Decisión tomada con el dueño: **Checkout Pro (carrito) + Link de pago por producto**; **WhatsApp se mantiene** como botón alternativo. Envío queda **fuera del MVP** ("a convenir", coordinado por WhatsApp).

### Estudio de alternativas

| Opción | Pro | Contra | Veredicto |
|---|---|---|---|
| **Checkout Pro** (redirect a checkout.mercadopago.com.ar) | Fácil y robusto, carrito dinámico, webhook + estados, sin SDK/composer | Sale del sitio | ✔ **Principal** |
| Wallet Bricks (embebido en la página) | El cliente no sale del sitio | Más integración (JS/SDK), sin estado propio; requiere retoques de estilo | Fase 3 (opcional) |
| Link de pago estático | Cero código de pago; MP lleva su propio seguimiento | Sin carrito dinámico ni estado en nuestra DB | ✔ **Atajo por producto** |

### Flujo a implementar

- **Checkout Pro:** drawer → botón "Pagar con Mercado Pago" → modal (nombre, email, teléfono opcional) → `POST api/checkout.php` (valida items contra DB, calcula total en servidor, INSERT `pedidos` estado `pendiente`, crea preferencia `POST /checkout/preferences`) → devuelve `init_point` → redirect → vuelve a `gracias.php` (auto_return) → webhook `api/mp_notify.php` (verifica pago con `GET /v1/payments/{id}`, UPDATE estado, email aviso) → admin sección **Pedidos**.
- **Link de pago por producto:** el dueño crea el link en el panel MP (Ventas → Links de pago) → pega la URL en el admin (campo `link_pago` del producto) → botón "Pago directo" en la ficha (sin estado propio).

### Piezas (archivos planificados)

| Archivo | Función |
|---|---|
| `config.local.php` | `MP_ACCESS_TOKEN` / `MP_PUBLIC_KEY` — **solo acá, fuera de git/chats/README** |
| `pagos/mp.php` (nuevo) | `http_json()` (stream+openssl), `crear_preferencia()`, `get_pago()` |
| `api/checkout.php` (nuevo) | valida carrito, INSERT pedido, crea preferencia → `{init_point}` |
| `api/mp_notify.php` (nuevo) | webhook: valida payment_id, UPDATE estado, `notificar_pedido()` |
| `gracias.php` (nuevo) | confirmación según estado + nº de pedido |
| `admin/pedidos.php` + nav | listar pedidos, items JSON, estado, mp_payment_id |
| `db.php` | `notificar_pedido()` (email dueño + cliente) |
| `parts/footer.php` · `cart.js` · `styles.css` | botón MP + modal datos + guardar precio numérico en carrito + redirect |

### Seguridad y datos clave

- Token MP **solo en `config.local.php`** (nunca loguear/copiar a chats; mismo criterio que la DB).
- Total siempre recalculado en servidor (no confiar en precios del navegador).
- Identificar pagos con `external_reference = PED-<id>` y webhook con verificación vía API (no solo el POST).

### Pasos de implementación (pendientes)

1. Sandbox: token `TEST-...` en `config.local.php` → `pagos/mp.php` + tabla `pedidos` + `api/checkout.php` → probar preferencia (CLI + navegador).
2. Drawer/modal/redirect + `gracias.php` + admin `pedidos.php`.
3. Webhook + emails → probar tarjetas de prueba MP (aprobado y rechazado).
4. Link de pago por producto (columna + campo admin + botón en ficha).
5. Producción: token `APP_USR-...`, quitar "modo prueba", compra real mínima (reintegro/MercadoCrédito).

> Operación diaria del cobro: ver skill `.opencode/skills/mp-checkout-echo/SKILL.md`.

## Despliegue (patrón usado)

Los archivos se suben por `scp` al VPS y el dueño/cliente corre permisos una sola vez por carpeta nueva:

```
scp -P 5178 -r <carpeta>/* root@149.50.142.160:/home/echo3dlaser.com.ar/public_html/echo3dlaser/
ssh -p 5178 root@149.50.142.160 "chown -R echod6966:nogroup <ruta> && find <ruta> -type d -exec chmod 755 {} + && find <ruta> -type f -exec chmod 644 {} +"
```

- VPS: `149.50.142.160:5178`, user `root`, siempre `-o BatchMode=yes`. OpenLiteSpeed + PHP 8.3 (lsphp83), web root `/home/echo3dlaser.com.ar/public_html/`.
- ⚠️ El vhost tiene un **sitio vivo** en la raíz: **no tocar** `index.php`, `data/`, `echodata/`, `mailer/`, `simplepress/` de la raíz. Todo va dentro de `/echo3dlaser/`.
- Credenciales nunca en el README público; `config.php` no debe servirse crudo (LiteSpeed lo ejecuta como PHP).

## Verificado (2026-09-12)

- 8 páginas/APIs → HTTP 200; CSS/SVG 200; JSON correcto (`precio_txt`, `imagen_url`).
- Formulario de contacto inserta en DB (prueba borrada).
- 📧 **Notificación email (2026-09-12)**: `contacto.php` además avisa por `mail()` → va a `SITE_NOTIF_EMAIL = robot@echo3dlaser.com.ar` (casilla real del servidor). Formato a prueba de webmails estrictos: subject RFC2047 base64 (`=?UTF-8?B?...?=`) y cuerpo **base64** con `charset=UTF-8` (cero bytes 8-bit). Alternativa inicial 8-bit crudo daba `unknown encoding: unknown-8bit` en Webmail CyberPanel → corregida. Origen/RTT: `SITE_MAIL_FROM`.
- Admin: login correcto/incorrecto, CRUD productos (alta→editar→borrar), logout → 302. Se usó curl + cookies; el alta inicial daba 500 por `slug` faltante → corregido generándolo con `slugify()`.
- Estado final: 8 productos semilla, 5 categorías, 8 marcas, 0 mensajes (fila de prueba borrada; queda 1 email de prueba en el INBOX de `robot@`).

## Roadmap (a dónde vamos)

1. ✅ Sitio vitrina: hero retro-futurista, marquee Three.js, catálogo vía API, carrito (localStorage), checkout por **WhatsApp**, contacto (DB), responsive, placeholder `mi-cuenta`.
2. ✅ Admin simple: login, panel, productos (con subida de imagen), categorías, mensajes.
3. ⏳ Datos reales del negocio (WhatsApp, dirección, email) en `config.php`.
4. ⏳ Imágenes reales en `uploads/productos/` (hoy SVG placeholder).
5. ⏳ Checkout **Mercado Pago** — ✅ plan aprobado (Checkout Pro + link por producto + WhatsApp sigue) → ⏳ implementación pendiente (ver sección Plan Mercado Pago).
6. ⏳ Cuentas de cliente (`mi-cuenta.php`): pedidos y estado.
7. ⏳ SEO (GA4 propio, Open Graph, sitemap) y review mobile-first real.
8. ⏳ Promover el sitio a la raíz del dominio y/o mantener en subdir según decisión del dueño.

## Notas técnicas para el agente

- PHO: `db.php` (patrón Bienal): PDO localhost/prod, `json_out()`, `wa_link()`.
- Slugs: `slugify()` en `admin/_helpers.php` (iconv ASCII//TRANSLIT). `productos.slug` es NOT NULL.
- Imágenes nuevas se validan (`svg/png/jpg/webp/gif`, < 2 MB) y se guardan en `uploads/productos/prod-<time>-<rand>.<ext>`.
- En PowerShell 5.1: evitar quotes anidados con `ssh`/`scp` — para comandos complejos usar **scripts**.sh subidos por scp y ejecutados con `bash script`; los `.ps1` locales se guardan UTF-8 con BOM.
- El agente opencode de este proyecto vive en `.opencode/agent/echo3dlaser.md` (ver README de la raíz si se reubica).