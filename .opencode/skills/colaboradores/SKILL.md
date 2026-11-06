---
name: colaboradores
description: Uso cuando se trabaja en la funcionalidad de Colaboradores del SRCI (srci/): red de colaboradores, aprobación de reportes, visibilidad por grupo, migraciones colaboradores/aprobado, api/aprobar_incidencia.php y la sección de colaboradores en perfil.php. Contiene reglas de negocio, modelo de datos, plan técnico y resumen ejecutivo para autoridades.
---

# Colaboradores — SRCI

Funcionalidad **implementada** (2026-10-29) en el Sistema de Reporte Ciudadano de Incidencias (`srci/`).
Estado: **modo admin-only** — la red la gestionan solo los admins hasta nuevo aviso.

## Modo actual: SOLO ADMINS

Hasta que el cliente lo habilite para todos, la funcionalidad se aplica únicamente a los admins:

- Solo un admin puede **designar/quitar colaboradores** y **aprobar/rechazar** reportes.
- El flag `SRCI_COLABORADORES_SOLO_ADMIN` (`includes/funciones.php`) está en `true`; ponerlo en `false`
  abre la red a todos los usuarios (cada uno con su propio grupo) sin tocar el código.
- Un usuario designado como colaborador carga reportes que **nacen pendientes** (`aprobado = 0`) y
  no se publican (no van al mapa ni a las exportaciones) hasta que un admin los aprueba.

## Contexto

El SRCI es de **uso interno**: usuarios designados dentro de la estructura municipal (referentes de barrio, organizadores, personal municipal). No es un sistema abierto al público.

## Reglas de negocio (acordadas)

1. **La relación colaborador → propietario se crea ya aprobada.** La crea el usuario propietario de la red o un administrador. No requiere aprobación de la vinculación.
2. **Lo que requiere aprobación es CADA REPORTE creado por un colaborador.** Nace con `aprobado = 0` y solo se publica cuando lo aprueba el propietario de su grupo o un admin.
3. **Reportes de usuarios no colaboradores se publican de inmediato** (`aprobado = 1`), como hoy.
4. **Colaborador no recibe rol admin.** Solo reporta; no gestiona el sistema ni a otros usuarios.
5. **Rechazar un reporte = estado "rechazado" visible para el autor** (`rechazado = 1` + `motivo_rechazo` + `oculto = 1`): nunca se borra, pero quien lo reportó lo ve en su perfil y en el detalle, con el motivo. El motivo es **obligatorio** (máx. 500 caracteres).
6. **Una sola red por usuario**: `colaboradores.colaborador_id` es UNIQUE. Un usuario no puede pertenecer a dos redes ni ser colaborador si es admin (no hay anidamiento).
7. **Editar un reporte es solo del autor o de un admin** (antes cualquier usuario logueado podía editar cualquier incidencia).
8. **Aviso por email al propietario de la red** cuando entra un reporte pendiente de aprobación.
9. **Restaurar** un reporte oculto limpia el estado de rechazo: vuelve a quedar pendiente de aprobación.

## Matriz de visibilidad (filtro SIEMPRE en servidor)

| Quién | Qué ve |
|---|---|
| **Admin** | Todas las incidencias (incluye pendientes y ocultos, según panel) |
| **Usuario de primer nivel** (no es colaborador, no tiene colaboradores) | Todas las incidencias **aprobadas** del sistema |
| **Propietario** (tiene colaboradores) | Todas las aprobadas del sistema + **pendientes de su grupo** (para revisar/aprobar) |
| **Colaborador** (pertenece a un grupo) | **Solo su grupo** (responsable + colaboradores del grupo, aprobados) + **sus propios pendientes**. Nunca ve reportes de usuarios ajenos |

## Modelo de datos

### Nueva tabla `colaboradores`

```sql
CREATE TABLE IF NOT EXISTS `colaboradores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `propietario_id` INT UNSIGNED NOT NULL,
  `colaborador_id` INT UNSIGNED NOT NULL,
  `estado` ENUM('aprobado','rechazado') NOT NULL DEFAULT 'aprobado',
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prop_col` (`propietario_id`, `colaborador_id`),
  KEY `idx_colaborador` (`colaborador_id`),
  CONSTRAINT `fk_col_prop` FOREIGN KEY (`propietario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_col_col` FOREIGN KEY (`colaborador_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_col_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Cambios en `incidencias`

```sql
ALTER TABLE `incidencias`
  ADD COLUMN `aprobado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `oculto`,
  ADD COLUMN `aprobado_en` DATETIME NULL AFTER `aprobado`,
  ADD COLUMN `aprobado_por` INT UNSIGNED NULL AFTER `aprobado_en`,
  ADD COLUMN `propietario_id` INT UNSIGNED NULL AFTER `usuario_id`,
  ADD INDEX `idx_incidencias_aprobado` (`aprobado`),
  ADD INDEX `idx_incidencias_propietario` (`propietario_id`),
  ADD CONSTRAINT `fk_inc_aprob_por` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inc_propietario` FOREIGN KEY (`propietario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL;
```

Backfill: incidencias existentes → `aprobado = 1`, `propietario_id = usuario_id`.

**Regla de creación:** al insertar en `api/reportes.php` POST, si el creador `es_colaborador()` → `aprobado = 0` + `propietario_id` = propietario de su grupo; si no → `aprobado = 1`.

### Cambios en `incidencias` (rechazo visible, 2026-10-30)

```sql
ALTER TABLE `incidencias`
  ADD COLUMN `rechazado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `aprobado_por`,
  ADD COLUMN `rechazado_en` DATETIME NULL AFTER `rechazado`,
  ADD COLUMN `rechazado_por` INT NULL AFTER `rechazado_en`,
  ADD COLUMN `motivo_rechazo` VARCHAR(500) NULL AFTER `rechazado_por`,
  ADD INDEX `idx_incidencias_rechazado` (`rechazado`),
  ADD CONSTRAINT `fk_inc_rechaz_por` FOREIGN KEY (`rechazado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL;
```

Migración: `srci/sql/migracion_rechazo_visible.sql` (aplicada en prod).
Visibilidad del rechazo: `perfil.php` lista "Mis reportes" con `i.oculto = 0 OR i.rechazado = 1` (solo del propio autor) y muestra badge + motivo; `detalle.php` muestra badge + motivo; para el resto sigue 404/oculto.

### Red única (2026-10-30)

`colaboradores.colaborador_id` con UNIQUE `uq_colaborador` (migración `srci/sql/migracion_colaboradores_red_unica.sql`, que además normaliza duplicados y dropea `idx_colaborador`). `agregar_colaborador()` rechaza el alta si el usuario ya está en otra red o si es admin.

## Plan técnico

### Estado de implementacion (2026-10-29)

| Paso | Estado | Donde |
|---|---|---|
| 1. Migraciones SQL | Hecho y aplicado en prod | `srci/sql/migracion_colaboradores.sql`, `migracion_aprobacion_incidencias.sql`, `migracion_backfill_aprobado.sql` |
| 2. Helpers | Hecho | `includes/funciones.php`: `es_colaborador()`, `propietario_de_colaborador()`, `ids_grupo_propietario()`, `colaboradores_de()`, `incidencias_pendientes_de_aprobacion()`, `condiciones_visibilidad_incidencias()`, `agregar_colaborador()`, `quitar_colaborador()`, `puede_gestionar_colaboradores()`, `puede_gestionar_aprobaciones()` |
| 3. `api/reportes.php` | Hecho | POST: regla de `aprobado`/`propietario_id`. GET: el mapa solo publica `aprobado = 1` |
| 4. `api/aprobar_incidencia.php` | Hecho | POST + CSRF (`X-CSRF-Token`), aprobar/rechazar, auditoria y email al creador |
| 5. UI en `perfil.php` | Hecho | Tarjetas "Mis colaboradores" y "Reportes pendientes de aprobación" (solo quien gestiona la red) |
| 6. Badges | Hecho | `.badge-aprobacion` en `incidencias.php`, `admin/reportes.php`, `detalle.php` y `perfil.php` |
| 7. Auditoría | Hecho | `agregar_colaborador`, `quitar_colaborador`, `aprobar`, `rechazar` |
| 8. Reglas del 2026-10-30 (red única, rechazo visible con motivo, aviso al propietario, editar solo autor/admin) | Hecho y desplegado | `sql/migracion_colaboradores_red_unica.sql`, `sql/migracion_rechazo_visible.sql`, `includes/funciones.php` (`notificar_propietario_por_email()`), `api/aprobar_incidencia.php`, `api/reportes.php`, `perfil.php`, `detalle.php`, `incidencias.php`, `editar.php`, `admin/reportes.php`, `.badge-rechazado` |

### Orden de implementación (referencia histórica)

1. Migraciones SQL (dos archivos idempotentes en `srci/sql/`)
2. Helpers en `includes/funciones.php`
3. `api/reportes.php` (GET con filtro de visibilidad / POST con regla de aprobado)
4. `api/aprobar_incidencia.php` (nuevo: POST + CSRF, acciones aprobar/rechazar, solo propietario del grupo o admin)
5. UI en `perfil.php` (tarjeta "Mis colaboradores" + sección "Reportes pendientes de aprobación")
6. Badges "⏳ Pendiente de aprobación" en `incidencias.php` y `detalle.php` (solo a quien puede gestionarlos)
7. Auditoría: crear colaborador, aprobar, rechazar (`registrar_auditoria()`)

### Helpers (`includes/funciones.php`)

- `es_colaborador(int $uid): bool`
- `propietario_de_colaborador(int $cid): ?int`
- `ids_grupo_propietario(int $pid): array` — propietario + colaboradores aprobados
- Filtro de visibilidad por tipo de usuario según la matriz

### Frontend

- `perfil.php`: agregar colaborador por email de usuario existente, quitar, listar pendientes con botones Aprobar/Rechazar
- `api/aprobar_incidencia.php`: nuevo endpoint (rechazo = `oculto=1`)

### Seguridad

- CSRF en toda acción
- Scope aplicado SIEMPRE en servidor (GET/POST + vistas)
- Sin borrado físico; auditoría completa (quién, cuándo, qué)

## Resumen ejecutivo para autoridades

**Sistema:** SRCI — Reporte de Incidencias (uso interno, Municipio)
**Propuesta:** Incorporar la figura del colaborador con aprobación obligatoria de reportes.

**Situación actual.** El sistema opera con usuarios designados dentro de la estructura municipal. Cada usuario carga y gestiona sus propias incidencias, que quedan visibles para todo el equipo. No existe un mecanismo formal para que un integrante del equipo reporte a través de un responsable, ni un filtro de calidad previo a la publicación.

**Propuesta.** Se incorpora una red de colaboradores: el usuario responsable puede designar colaboradores que cargan incidencias desde su propia cuenta. La relación se crea desde el sistema, queda registrada y el colaborador conserva rol de usuario sin acceso administrativo.

**Control de calidad.** Todo reporte cargado por un colaborador nace pendiente de aprobación. El responsable de la red (o un administrador) lo revisa desde su perfil y decide aprobar o rechazar. Solo los reportes aprobados se publican. Los reportes de usuarios no colaboradores se publican de inmediato.

**Visibilidad.** Cada colaborador ve únicamente los reportes de su propia red (responsable y compañeros de grupo). Los usuarios del equipo conservan su visión actual sobre las incidencias aprobadas. Las reglas se aplican en el servidor.

**Trazabilidad.** Alta de colaboradores, aprobaciones y rechazos quedan registrados en la auditoría con autor y fecha. Nada se elimina físicamente.

**Beneficios.**

- Amplía la capacidad operativa del equipo sin otorgar permisos administrativos.
- Garantiza que ninguna información de un colaborador se publique sin revisión.
- Protege la información: cada integrante accede solo a lo que le corresponde.
- Auditoría completa de quién reportó, quién aprobó y quién rechazó.

**Implementación.** Ciclo breve de desarrollo: nuevas tablas, ajuste de filtros de visibilidad y sección de gestión en el perfil. Sin cambios para los usuarios que no participan de una red.

## Pendientes / dudas abiertas

- ~~Rechazar reporte: ¿`oculto=1` o estado "rechazado" visible?~~ → **resuelto 2026-10-30: estado "rechazado" visible con motivo obligatorio.**
- ~~¿Notificar por email al colaborador cuando aprueban/rechazan?~~ → **resuelto: se avisa al creador por email (ya existía) y además al propietario de la red cuando entra un reporte pendiente.**
- ~~¿Un usuario puede ser colaborador de varios propietarios?~~ → **resuelto: no, una sola red (UNIQUE `uq_colaborador`).**
- ~~¿Propietario con colaboradores puede ser colaborador de otro?~~ → **resuelto: no hay anidamiento; un admin no puede ser colaborador.**
- Nuevo pendiente: decidir si el **email al propietario de la red** se manda también cuando el flag `SRCI_COLABORADORES_SOLO_ADMIN` se pone en `false` (hoy se manda siempre que el reporte nazca pendiente).
