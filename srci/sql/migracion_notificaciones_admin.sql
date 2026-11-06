-- Migracion: preferencias de notificacion para administradores
-- Permite a los admins desactivar notificacion por incidente y digest diario
-- Ejecutar en produccion

ALTER TABLE usuarios 
  ADD COLUMN IF NOT EXISTS notifica_incidentes_admin TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER activo,
  ADD COLUMN IF NOT EXISTS notifica_digest_admin TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER notifica_incidentes_admin;

-- Asegurar valores por defecto para admins existentes
UPDATE usuarios SET notifica_incidentes_admin = 1 WHERE rol = 'admin' AND notifica_incidentes_admin IS NULL;
UPDATE usuarios SET notifica_digest_admin = 1 WHERE rol = 'admin' AND notifica_digest_admin IS NULL;
