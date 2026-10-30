--
-- Migración: tabla `colaboradores` (red de colaboradores del SRCI)
-- Idempotente: CREATE TABLE IF NOT EXISTS
-- Los tipos replican el esquema real de `usuarios.id` (INT con signo, InnoDB):
-- una FK entre INT UNSIGNED e INT con signo es invalida en MariaDB.
--

CREATE TABLE IF NOT EXISTS `colaboradores` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `propietario_id` INT NOT NULL,
  `colaborador_id` INT NOT NULL,
  `estado` ENUM('aprobado','rechazado') NOT NULL DEFAULT 'aprobado',
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` INT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prop_col` (`propietario_id`, `colaborador_id`),
  KEY `idx_colaborador` (`colaborador_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_creado_por` (`creado_por`),
  CONSTRAINT `fk_col_prop` FOREIGN KEY (`propietario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_col_col` FOREIGN KEY (`colaborador_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_col_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
