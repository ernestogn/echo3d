-- ============================================================
-- SRCI - Migracion: alineacion con Planilla_relevamiento_barrios
-- - Nueva tabla `barrios` (editable desde panel admin)
-- - Nuevos campos en `incidencias` (todos NULL: sin perdida de datos)
-- - 3 tipos nuevos (unificacion con la planilla)
-- Uso: mysql --default-character-set=utf8mb4 -u USUARIO -p NOMBRE_BD < migracion_planilla.sql
-- ============================================================

-- 1. Tabla de barrios
CREATE TABLE IF NOT EXISTS barrios (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nombre    VARCHAR(100) NOT NULL UNIQUE,
  activo    TINYINT(1) DEFAULT 1,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO barrios (nombre) VALUES ('Otro');

-- 2. Nuevos campos en incidencias
ALTER TABLE incidencias
  ADD COLUMN barrio_id           INT          NULL AFTER longitud,
  ADD COLUMN direccion           VARCHAR(255) NULL AFTER barrio_id,
  ADD COLUMN gravedad            ENUM('baja','media','alta','critica') NULL AFTER direccion,
  ADD COLUMN familias_afectadas  INT UNSIGNED NULL AFTER gravedad,
  ADD COLUMN servicio_afectado   VARCHAR(50)  NULL AFTER familias_afectadas,
  ADD COLUMN calle_intransitable TINYINT(1)   NULL AFTER servicio_afectado,
  ADD COLUMN responsable_area    VARCHAR(100) NULL AFTER calle_intransitable,
  ADD COLUMN contacto_vecino     VARCHAR(120) NULL AFTER responsable_area,
  ADD COLUMN fecha_resolucion    DATETIME     NULL AFTER estado,
  ADD INDEX idx_incidencias_barrio (barrio_id),
  ADD CONSTRAINT fk_incidencias_barrio
    FOREIGN KEY (barrio_id) REFERENCES barrios(id) ON DELETE SET NULL;

-- 3. Tipos nuevos (unificacion con la planilla)
INSERT INTO tipos_incidencia (clave, nombre, icono) VALUES
  ('caida_poste',  'Caida de poste',         '💡'),
  ('anegamiento',  'Anegamiento por lluvia', '🌧️'),
  ('inundacion',   'Inundacion',             '🌊');
