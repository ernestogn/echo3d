---
name: mp-checkout-echo
description: Usá este skill cuando trabajes cobros/pagos del sitio ECHO 3D LASER con Mercado Pago. Triggers: mercado pago, cobro, pagar, checkout MP, link de pago, pedidos MP, webhook MP, preferencia MP, iniciar sesión MP. Documenta el plan aprobado (Checkout Pro + link de pago por producto + WhatsApp como alternativa), dónde viven las credenciales y cómo pasar de sandbox a producción. NO usar para contenido del catálogo, estilos o deploy general (ver agent echo3dlaser y README).
---

# Mercado Pago — ECHO 3D LASER

Plan aprobado por el dueño el **2026-09-12**. Estado: **aprobado, SIN implementar todavía** (nada del código de pago existe en el sitio todavía; el carrito hoy solo escribe por WhatsApp). Fuente canónica del plan: `README.md` → sección "Plan Mercado Pago".

## Decisiones (no revertir sin consultar al dueño)

- **Checkout Pro (redirect)** = pago principal del carrito.
- **Link de pago estático por producto** = atajo "Pago directo" en cada ficha.
- **WhatsApp se mantiene** como botón alternativo en el drawer.
- Envío fuera del MVP ("a convenir" por WhatsApp).
- Alternativa descartada para fase 1: Wallet Bricks (embebido) → posible fase 3. Motivo: Checkout Pro es más simple, robusto y no requiere SDK.

## Credenciales (regla de seguridad crítica)

- **Access Token SOLO en `config.local.php` del sitio** (constantes `MP_ACCESS_TOKEN`, `MP_PUBLIC_KEY`).
- Nunca pegar el token en chats, logs, README o commits.
- Formato: sandbox `TEST-...` · producción `APP_USR-...` (empieza distinto según el entorno).
- PHP del vhost es 8.3 **sin cURL** → las llamadas a la API se hacen con `file_get_contents` + `stream_context` (openssl activo, `allow_url_fopen=On`).

## Flujo Checkout Pro (a implementar)

1. Drawer → botón "Pagar con Mercado Pago" → modal pide nombre + email (+ teléfono opcional).
2. `POST api/checkout.php`:
   - validar items contra `productos` (precio real de la DB, nunca del navegador);
   - `INSERT pedidos` (estado `pendiente`, items JSON, total);
   - `POST https://api.mercadopago.com/checkout/preferences` con `items`, `payer`, `back_urls[success|pending|failure]`, `auto_return=approved`, `notification_url` = `https://echo3dlaser.com.ar/echo3dlaser/api/mp_notify.php?ref=PED-<id>` y `external_reference=PED-<id>`;
   - devolver `init_point` al JS → redirect del navegador.
3. Cliente paga → vuelve a `gracias.php` (auto_return) → MP notifica webhook a `api/mp_notify.php`.
4. `api/mp_notify.php`: acusar 200 rápido; si `topic=payment` → `GET /v1/payments/{data.id}` con Access Token → verificar `external_reference` → `UPDATE pedidos` (estado, `mp_payment_id`) → email dueño + cliente (`notificar_pedido()` en `db.php`).

Tabla `pedidos` (a crear): `id, pref_id, mp_payment_id, estado('pendiente','aprobado','rechazado','cancelado'), nombre, email, telefono, items_json, total DECIMAL(10,2), ip, creado, actualizado`.

## Pruebas en sandbox

- Pegar token `TEST-...` en `config.local.php`.
- Crear **usuarios de prueba** (vendedor y comprador) en panel MP → desarrolladores → credenciales → usuarios de prueba.
- Cobrar con las tarjetas de prueba de MP (documentación oficial): Mastercard `5031 7554 0306 0633` y Visa `4235 6477 2802 5682`, CVV `123`, fecha futura cualquiera; aprobado/rechazado según el monto/documento (usar las del doc para forzar rechazo: e.g. cantidad rechazos según doc).
- Si no llega el webhook: revisar `notification_url` accesible (HTTPS, subred OK), `postqueue`, y probar con `curl` CLI desde el VPS un POST simulado.
- No dejar `MP_ACCESS_TOKEN` de prueba cuando se pase a producción.

## Pasar a producción

1. En el panel MP (producción), la app debe estar activa y las credenciales copiadas (no usar las de prueba).
2. Reemplazar `MP_ACCESS_TOKEN` en `config.local.php` por `APP_USR-...`.
3. Quitar badge "modo prueba" del sitio.
4. Hacer UNA compra real mínima (producto chico o reintegro) y verificar: pedido en admin, email dueño, webhook, estado `aprobado`.
5. Configurar en el panel MP las URLs de webhook/back globales si se quiere respaldo (en este plan se usa `notification_url` por preferencia).

## Link de pago por producto

1. Panel MP → **Ventas → Links de pago → Nuevo link** (precio, nombre, cantidad 1).
2. Copiar la URL `https://checkout.mercadopago.com.ar/link/...` y pegarla en el **admin del sitio → producto → campo `link_pago`**.
3. En la ficha del producto, botón "Pago directo" que abre el link. Sin estado propio (lo sigue MP).
4. Requiere migración: `ALTER TABLE productos ADD link_pago VARCHAR(255) NULL` (hacerla 1 vez vía script, luego editar solo desde el admin).

## Troubleshooting común

- `401` en la API → token inválido/vencido o mixto (TEST vs APP_USR). Verificar entorno coherente.
- Preferencia 400 → item sin `unit_price` numérico o total 0; verificar `precio` DECIMAL de la DB.
- Webhook que no llega → revisar `api/mp_notify.php` responde rápido y no hace bloqueo; probar con curl; revisar configuración de credenciales de la app (URL de notificación global vacía no bloquea `notification_url` por preferencia).
- TLS/CA → usar `stream_context` con `verify_peer` correcto; openssl presente en lsphp83.
- No exponer errores crudos de MP al frontend: loguear internamente y devolver JSON acotado.