-- ============================================================
-- SISTEMA DE GESTIÓN DE CARPAS Y EVENTOS — SCHEMA SQL
-- Prefijo de tablas: carp_
-- Las tablas 'tenants' y 'usuarios' ya existen en el sistema
-- de encuestas, se añaden columnas faltantes con ALTER TABLE.
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ============================================================
-- EXTENSIÓN DE TABLA TENANTS EXISTENTE
-- Agregar columnas del sistema de carpas si no existen
-- ============================================================

ALTER TABLE `tenants`
  ADD COLUMN IF NOT EXISTS `moneda`        VARCHAR(10)  NOT NULL DEFAULT 'ARS' AFTER `nombre`,
  ADD COLUMN IF NOT EXISTS `zona_horaria`  VARCHAR(60)  NOT NULL DEFAULT 'America/Argentina/Buenos_Aires' AFTER `moneda`,
  ADD COLUMN IF NOT EXISTS `logo_path`     VARCHAR(255) NULL AFTER `zona_horaria`,
  ADD COLUMN IF NOT EXISTS `email_contacto` VARCHAR(150) NULL AFTER `logo_path`,
  ADD COLUMN IF NOT EXISTS `costo_km`      DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER `email_contacto`,
  ADD COLUMN IF NOT EXISTS `alerta_saldo_dias` INT NOT NULL DEFAULT 3 COMMENT 'Días antes del evento para alertar saldo pendiente' AFTER `costo_km`;

-- ============================================================
-- EXTENSIÓN DE TABLA USUARIOS EXISTENTE
-- Añadir columnas necesarias para el sistema de carpas
-- ============================================================

ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `ultimo_login`   DATETIME NULL AFTER `activo`,
  ADD COLUMN IF NOT EXISTS `intentos_login` TINYINT NOT NULL DEFAULT 0 AFTER `ultimo_login`,
  ADD COLUMN IF NOT EXISTS `bloqueado_hasta` DATETIME NULL AFTER `intentos_login`;

-- ============================================================
-- CLIENTES
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_clientes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED NOT NULL,
  `nombre`        VARCHAR(150) NOT NULL,
  `tipo`          ENUM('particular','empresa','organizador') NOT NULL DEFAULT 'particular',
  `telefono`      VARCHAR(30) NULL,
  `email`         VARCHAR(150) NULL,
  `documento`     VARCHAR(30) NULL COMMENT 'DNI, CUIT, etc.',
  `calificacion`  ENUM('normal','vip','con_deuda','bloqueado') NOT NULL DEFAULT 'normal',
  `observaciones` TEXT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_nombre` (`tenant_id`, `nombre`),
  INDEX `idx_email`  (`tenant_id`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INVENTARIO DE EQUIPOS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_inventario_equipos` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`         INT UNSIGNED NOT NULL,
  `nombre`            VARCHAR(150) NOT NULL,
  `tipo`              ENUM('carpa','modulo','piso','alfombra','electrico','luminaria','otro') NOT NULL DEFAULT 'otro',
  `cantidad_total`    INT UNSIGNED NOT NULL DEFAULT 1,
  `estado`            ENUM('activo','en_reparacion','baja') NOT NULL DEFAULT 'activo',
  `costo_adquisicion` DECIMAL(12,2) NULL DEFAULT NULL,
  `descripcion`       TEXT NULL,
  `foto_path`         VARCHAR(255) NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_tipo`   (`tenant_id`, `tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EVENTOS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_eventos` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`           INT UNSIGNED NOT NULL,
  `cliente_id`          INT UNSIGNED NULL,
  `nombre_evento`       VARCHAR(200) NOT NULL,
  `tipo_evento`         VARCHAR(80)  NOT NULL DEFAULT 'otro',
  `fecha_montaje`       DATETIME NOT NULL,
  `fecha_inicio_evento` DATETIME NOT NULL,
  `fecha_fin_evento`    DATETIME NOT NULL,
  `fecha_desmontaje`    DATETIME NOT NULL,
  `direccion`           VARCHAR(255) NULL,
  `localidad`           VARCHAR(100) NULL,
  `distancia_km`        DECIMAL(8,2) NULL DEFAULT NULL,
  `capacidad_personas`  INT UNSIGNED NULL,
  `precio_total`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `estado`              ENUM(
                          'consulta','presupuestado','reservado_con_sena',
                          'confirmado','en_montaje','en_curso','en_desmontaje',
                          'finalizado','cerrado','cancelado','postergado'
                        ) NOT NULL DEFAULT 'consulta',
  `motivo_cancelacion`  TEXT NULL,
  `observaciones`       TEXT NULL,
  `creado_por`          INT UNSIGNED NULL COMMENT 'FK usuarios.id',
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant`        (`tenant_id`),
  INDEX `idx_cliente`       (`tenant_id`, `cliente_id`),
  INDEX `idx_estado`        (`tenant_id`, `estado`),
  INDEX `idx_fechas`        (`tenant_id`, `fecha_montaje`, `fecha_desmontaje`),
  CONSTRAINT `fk_evento_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `carp_clientes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PIVOT: EVENTO ↔ EQUIPOS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_evento_equipos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED NOT NULL,
  `evento_id`   INT UNSIGNED NOT NULL,
  `equipo_id`   INT UNSIGNED NOT NULL,
  `cantidad`    INT UNSIGNED NOT NULL DEFAULT 1,
  `observacion` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evento_equipo` (`evento_id`, `equipo_id`),
  INDEX `idx_tenant`  (`tenant_id`),
  INDEX `idx_equipo`  (`equipo_id`),
  CONSTRAINT `fk_ee_evento` FOREIGN KEY (`evento_id`) REFERENCES `carp_eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ee_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `carp_inventario_equipos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAGOS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_pagos` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT UNSIGNED NOT NULL,
  `evento_id`       INT UNSIGNED NOT NULL,
  `fecha`           DATE NOT NULL,
  `monto`           DECIMAL(12,2) NOT NULL,
  `forma_pago`      ENUM('efectivo','transferencia','tarjeta','cheque','otro') NOT NULL DEFAULT 'efectivo',
  `referencia`      VARCHAR(150) NULL COMMENT 'Nro. de transferencia, cheque, etc.',
  `es_sena`         TINYINT(1) NOT NULL DEFAULT 0,
  `registrado_por`  INT UNSIGNED NULL COMMENT 'FK usuarios.id',
  `observacion`     TEXT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_evento` (`tenant_id`, `evento_id`),
  CONSTRAINT `fk_pago_evento` FOREIGN KEY (`evento_id`) REFERENCES `carp_eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EMPLEADOS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_empleados` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT UNSIGNED NOT NULL,
  `nombre`          VARCHAR(100) NOT NULL,
  `rol_habitual`    VARCHAR(80)  NULL,
  `telefono`        VARCHAR(30)  NULL,
  `email`           VARCHAR(150) NULL,
  `tarifa_jornal`   DECIMAL(10,2) NULL DEFAULT NULL,
  `tarifa_evento`   DECIMAL(10,2) NULL DEFAULT NULL,
  `usuario_id`      INT UNSIGNED NULL COMMENT 'FK usuarios.id si tiene login',
  `activo`          TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRASLADOS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_traslados` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT UNSIGNED NOT NULL,
  `evento_id`       INT UNSIGNED NOT NULL,
  `tipo`            ENUM('ida_montaje','vuelta_desmontaje','apoyo','vuelta_anticipada','otro') NOT NULL DEFAULT 'ida_montaje',
  `fecha_hora`      DATETIME NOT NULL,
  `origen`          VARCHAR(200) NULL,
  `destino`         VARCHAR(200) NULL,
  `vehiculo`        VARCHAR(100) NULL,
  `conductor_id`    INT UNSIGNED NULL COMMENT 'FK carp_empleados.id',
  `km_distancia`    DECIMAL(8,2) NULL DEFAULT NULL,
  `observaciones`   TEXT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_evento` (`tenant_id`, `evento_id`),
  CONSTRAINT `fk_traslado_evento` FOREIGN KEY (`evento_id`) REFERENCES `carp_eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_traslado_conductor` FOREIGN KEY (`conductor_id`) REFERENCES `carp_empleados` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ASIGNACIÓN EMPLEADOS A EVENTOS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_evento_empleados` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED NOT NULL,
  `evento_id`     INT UNSIGNED NOT NULL,
  `empleado_id`   INT UNSIGNED NOT NULL,
  `funcion`       VARCHAR(100) NULL,
  `dias`          TINYINT UNSIGNED NULL,
  `horas`         DECIMAL(5,2) NULL,
  `monto_acordado` DECIMAL(10,2) NULL DEFAULT NULL,
  `estado_pago`   ENUM('pendiente','pago_parcial','pago_total') NOT NULL DEFAULT 'pendiente',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evento_empleado` (`evento_id`, `empleado_id`),
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_evempl_evento`   FOREIGN KEY (`evento_id`)   REFERENCES `carp_eventos`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evempl_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `carp_empleados`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INCIDENCIAS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_incidencias` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT UNSIGNED NOT NULL,
  `evento_id`       INT UNSIGNED NOT NULL,
  `tipo`            ENUM('dano_equipo','falla_tecnica','problema_cliente','demora','extravio','accidente','queja_post','otro') NOT NULL DEFAULT 'otro',
  `descripcion`     TEXT NOT NULL,
  `fecha_hora`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reportado_por`   INT UNSIGNED NULL COMMENT 'FK usuarios.id',
  `estado`          ENUM('abierta','en_seguimiento','resuelta') NOT NULL DEFAULT 'abierta',
  `acciones`        TEXT NULL,
  `costo_generado`  DECIMAL(10,2) NULL DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_evento` (`tenant_id`, `evento_id`),
  INDEX `idx_estado` (`tenant_id`, `estado`),
  CONSTRAINT `fk_incidencia_evento` FOREIGN KEY (`evento_id`) REFERENCES `carp_eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FOTOS DE INCIDENCIAS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_incidencia_fotos` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED NOT NULL,
  `incidencia_id` INT UNSIGNED NOT NULL,
  `foto_path`     VARCHAR(255) NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_incidencia` (`incidencia_id`),
  CONSTRAINT `fk_foto_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `carp_incidencias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- GASTOS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_gastos` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    INT UNSIGNED NOT NULL,
  `evento_id`    INT UNSIGNED NULL COMMENT 'NULL = gasto general del negocio',
  `categoria`    ENUM('combustible','peajes','viaticos','personal','materiales','reparacion','seguro','mantenimiento','compra_equipo','incidencia','otro') NOT NULL DEFAULT 'otro',
  `descripcion`  VARCHAR(255) NOT NULL,
  `monto`        DECIMAL(12,2) NOT NULL,
  `fecha`        DATE NOT NULL,
  `comprobante`  VARCHAR(255) NULL,
  `registrado_por` INT UNSIGNED NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_evento` (`tenant_id`, `evento_id`),
  INDEX `idx_fecha`  (`tenant_id`, `fecha`),
  CONSTRAINT `fk_gasto_evento` FOREIGN KEY (`evento_id`) REFERENCES `carp_eventos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAQUETES DE EQUIPOS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_paquetes` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    INT UNSIGNED NOT NULL,
  `nombre`       VARCHAR(150) NOT NULL,
  `descripcion`  TEXT NULL,
  `precio_base`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `activo`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- COLA DE EMAILS
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_email_queue` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    INT UNSIGNED NOT NULL,
  `destinatario` VARCHAR(150) NOT NULL,
  `asunto`       VARCHAR(255) NOT NULL,
  `template`     VARCHAR(80)  NOT NULL,
  `variables`    JSON NOT NULL,
  `estado`       ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
  `intentos`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ultimo_error` TEXT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`      DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_estado` (`estado`, `intentos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDIT LOG
-- ============================================================

CREATE TABLE IF NOT EXISTS `carp_audit_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED NULL,
  `usuario_id`  INT UNSIGNED NULL,
  `accion`      VARCHAR(80)  NOT NULL COMMENT 'create, update, delete, estado_cambio, pago_registrado, etc.',
  `tabla`       VARCHAR(60)  NOT NULL,
  `registro_id` INT UNSIGNED NULL,
  `datos_antes` JSON NULL,
  `datos_despues` JSON NULL,
  `ip`          VARCHAR(45)  NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant`     (`tenant_id`, `created_at`),
  INDEX `idx_usuario`    (`usuario_id`),
  INDEX `idx_tabla`      (`tabla`, `registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
