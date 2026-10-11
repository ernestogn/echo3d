-- Migracion: ocultar el selector de categorias a los usuarios regulares.
-- Agrega categorias_incidencia.visible_usuario (TINYINT, default 0 = NO visible para usuarios).
-- Con esto, por ahora el selector de categorias queda SOLO para admins.
-- Desde el admin (admin/categorias.php, boton "switch por categoria") se puede
-- marcar una categoria como visible para los usuarios y aparecera de nuevo.
-- Idempotente: revisa information_schema antes de alterar.

SET @existe = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'categorias_incidencia'
     AND COLUMN_NAME  = 'visible_usuario'
);

SET @sql = IF(@existe = 0,
  'ALTER TABLE categorias_incidencia ADD COLUMN visible_usuario TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER activo',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;