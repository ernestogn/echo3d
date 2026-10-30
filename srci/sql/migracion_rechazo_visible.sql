--
-- Migración: rechazo visible para el autor del reporte
-- Idempotente. Un reporte rechazado queda oculto del mapa y del listado general
-- (oculto = 1) pero el autor lo ve en su perfil con el motivo del rechazo.
-- Los tipos replican el esquema real de `usuarios.id` (INT con signo).
--

ALTER TABLE `incidencias`
  ADD COLUMN IF NOT EXISTS `rechazado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `aprobado`,
  ADD COLUMN IF NOT EXISTS `rechazado_en` DATETIME NULL AFTER `rechazado`,
  ADD COLUMN IF NOT EXISTS `rechazado_por` INT NULL AFTER `rechazado_en`,
  ADD COLUMN IF NOT EXISTS `motivo_rechazo` VARCHAR(500) NULL AFTER `rechazado_por`;

ALTER TABLE `incidencias`
  ADD INDEX IF NOT EXISTS `idx_incidencias_rechazado` (`rechazado`);

-- FK: rechazado_por -> usuarios.id (ON DELETE SET NULL)
SET @db := DATABASE();
SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `incidencias` ADD CONSTRAINT `fk_inc_rechaz_por` FOREIGN KEY (`rechazado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL',
    'SELECT ''fk_inc_rechaz_por ya existe'' AS aviso')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'incidencias' AND CONSTRAINT_NAME = 'fk_inc_rechaz_por'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
