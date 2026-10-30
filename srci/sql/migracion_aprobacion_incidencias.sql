--
-- Migración: estado de aprobación en `incidencias`
-- Idempotente en MariaDB 10.11: ADD COLUMN / ADD INDEX admiten IF NOT EXISTS;
-- las FKs se crean con SQL dinámico porque MariaDB no acepta IF NOT EXISTS en CONSTRAINT.
-- Los tipos de `aprobado_por` y `propietario_id` replican `usuarios.id` (INT con signo).
--

ALTER TABLE `incidencias`
  ADD COLUMN IF NOT EXISTS `aprobado` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `oculto`,
  ADD COLUMN IF NOT EXISTS `aprobado_en` DATETIME NULL AFTER `aprobado`,
  ADD COLUMN IF NOT EXISTS `aprobado_por` INT NULL AFTER `aprobado_en`,
  ADD COLUMN IF NOT EXISTS `propietario_id` INT NULL AFTER `usuario_id`;

ALTER TABLE `incidencias`
  ADD INDEX IF NOT EXISTS `idx_incidencias_aprobado` (`aprobado`),
  ADD INDEX IF NOT EXISTS `idx_incidencias_propietario` (`propietario_id`);

-- FK: aprobado_por -> usuarios.id (ON DELETE SET NULL)
SET @db := DATABASE();
SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `incidencias` ADD CONSTRAINT `fk_inc_aprob_por` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL',
    'SELECT ''fk_inc_aprob_por ya existe'' AS aviso')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'incidencias' AND CONSTRAINT_NAME = 'fk_inc_aprob_por'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FK: propietario_id -> usuarios.id (ON DELETE SET NULL)
SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `incidencias` ADD CONSTRAINT `fk_inc_propietario` FOREIGN KEY (`propietario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL',
    'SELECT ''fk_inc_propietario ya existe'' AS aviso')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'incidencias' AND CONSTRAINT_NAME = 'fk_inc_propietario'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
