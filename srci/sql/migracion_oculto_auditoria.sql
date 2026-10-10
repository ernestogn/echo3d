-- ============================================================
-- SRCI - Migracion: ocultar incidencias (sin borrar) + auditoria
-- - Columna `oculto` en incidencias (soft delete)
-- - Tabla `auditoria`: log de quien y cuando crea/edita/modifica/oculta
-- Uso: mysql --default-character-set=utf8mb4 -u USUARIO -p NOMBRE_BD < migracion_oculto_auditoria.sql
-- ============================================================

ALTER TABLE incidencias
  ADD COLUMN oculto TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;

CREATE TABLE IF NOT EXISTS auditoria (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT,
  accion        VARCHAR(50) NOT NULL,
  incidencia_id INT,
  detalle       TEXT,
  fecha_hora    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
