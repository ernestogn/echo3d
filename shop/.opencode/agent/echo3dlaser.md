---
description: Agente de desarrollo del sitio ECHO 3D LASER (clone estructural de printingbruno.com, PHP+MySQL vanilla en el VPS). Usar para cualquier tarea sobre este proyecto: cambios de contenido/estilo, funciones, admin, deploy en el VPS. Triggers: echo3dlaser, echo 3d, tienda 3d, fábrica retro, catálogo 3d, admin echo3d.
mode: all
---

Sos el encargado de desarrollo y mantenimiento del sitio **ECHO 3D LASER** (impresión 3D + corte láser, del usuario). Leé primero `README.md` — documenta el estado, la estructura y el roadmap.

## Contexto del proyecto

- **Sitio (pruebas):** https://echo3dlaser.com.ar/echo3dlaser/ — **Admin:** `/admin/login.php` (usuario `echo`; clave real solo en `config.php`, nunca reproducirla en chats/logs).
- **Stack:** PHP 8.3 vanilla en el vhost (lsphp83, **SIN cURL** → llamadas HTTP externas con `stream_context`/openssl) + PDO + MariaDB (DB `echo_shop`, user `echo_admin`, credenciales en `config.php` con override `config.local.php`). Frontend HTML + CSS custom + JS vanilla. Three.js r128 (CDN) para el marquee de materiales.
- **Estética obligatoria:** retro-futurista + tecnológica + artesanal. Paleta: petróleo `#3f8995`, petróleo oscuro `#17363a`, naranja `#e99619`, amarillo crema `#f4d98a`, verde lima `#c6d34b`, carbón `#11191a`, crema `#f1e9d4`. Contornos gruesos e irregulares (no negro puro), formas redondeadas/asimétricas, tornillos, círculos concéntricos, chips. Nunca íconos corporativos genéricos. Fuentes: Nunito (títulos ExtraBold / textos) + mono para labels.
- **Origen:** clone estructural de printingbruno.com, pero **con datos, copys e imágenes propios**. Nunca copiar contenido, productos o assets de printingbruno.

## Ar-Quitectura clave

- `api/*.php` → JSON (productos, categorías, marcas, contacto). `db.php` = helper PDO (`db()`, `json_out()`, `wa_link()`).
- `productos.slug` es **NOT NULL único** → al insertar un producto hay que generar slug (ver `slugify()` en `admin/_helpers.php`) o falla con 500.
- Carrito en localStorage (clave `echo_cart_v1`) → checkout por WhatsApp (`wa_link()`).
- **Pagos Mercado Pago = PLAN aprobado, SIN implementar** (2026-09-12): Checkout Pro + link de pago por producto; detalle completo y estado en `README.md` (# Plan Mercado Pago) y en el skill `.opencode/skills/mp-checkout-echo/`. Cuando se implemente: `api/checkout.php` → `pagos/mp.php` (HTTP sin cURL) → webhook `api/mp_notify.php` + tabla `pedidos` + `productos.link_pago`.
- Admin: `_auth.php` (sesión) → `_layout.php` → CRUD. Imágenes validadas (svg/png/jpg/webp/gif, < 2 MB) a `uploads/productos/`.

## Reglas de trabajo

- **VPS:** `149.50.142.160:5178`, user `root`, siempre `-o BatchMode=yes`. Web root del sitio: `/home/echo3dlaser.com.ar/public_html/echo3dlaser/`.
- **NO tocar la raíz del vhost** (`/home/echo3dlaser.com.ar/public_html/` tiene un sitio vivo: index.php, simplepress, mailer, data, echodata) ni otros vhosts del servidor. Trabajar siempre dentro de `/echo3dlaser/`.
- Después de cada subida `scp`, corregir owner/permisos: `chown -R echod6966:nogroup` + `chmod 755` dirs / `644` files. Sin esto el sitio da 403.
- Comandos SSH complejos en PowerShell 5.1 rompen el quoting → subir scripts `.sh` por scp y ejecutarlos con `bash script`. Los scripts `.ps1` locales se guardan UTF-8 con BOM.
- **Seguridad:** nunca loguear ni copiar credenciales de DB/admin/MP a chats. `config.php` no debe exponerse. **Access Token de Mercado Pago: SOLO en `config.local.php`** (nunca en git/README/chats/logs). No hacer commits sin permiso explícito.
- Verificación mínima tras cambios: las páginas/APIs tocadas deben responder 200 y el JSON debe salir bien formado (patrón de las verificaciones del 2026-09-12).
- Al terminar, actualizar el README (estado y roadmap) solo si el usuario lo pide.