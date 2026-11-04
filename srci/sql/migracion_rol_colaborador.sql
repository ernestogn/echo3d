-- ============================================================
-- Migracion: rol 'colaborador' en usuarios.rol
-- ============================================================
-- Agrega el valor 'colaborador' al ENUM de usuarios.rol para poder
-- distinguir, en el futuro, a los usuarios que reportan a traves de
-- otro usuario (su rol queda en la tabla colaboradores).
--
-- IDEMPOTENTE: se puede correr varias veces sin efecto.
-- NO cambia permisos: hoy sigue siendo admin = rol 'admin'.
-- Los colaboradores existentes se marcan con rol 'colaborador'
-- (una sola red por usuario: uq_colaborador).
--
-- Uso: mysql --default-character-set=utf8mb4 -u USUARIO -p NOMBRE_BD < migracion_rol_colaborador.sql
-- ============================================================

SET @db := DATABASE();

-- 1) Ampliar el ENUM solo si falta el valor (MySQL 5.7/8 y MariaDB 10.x)
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE usuarios MODIFY COLUMN rol ENUM(''usuario'',''admin'',''colaborador'') NOT NULL DEFAULT ''usuario''',
    'SELECT "rol colaborador ya existe"'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME   = 'usuarios'
    AND COLUMN_NAME  = 'rol'
    AND COLUMN_TYPE LIKE '%''colaborador''%'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Marcar como colaboradores a los usuarios vinculados en 'colaboradores'
--    (solo los que hoy son 'usuario': no pisa admins ni otros roles)
UPDATE usuarios u
  JOIN (SELECT DISTINCT colaborador_id FROM colaboradores) c
    ON c.colaborador_id = u.id
   SET u.rol = 'colaborador'
 WHERE u.rol = 'usuario';
