-- ============================================================
-- SRCI - Migracion: usuarios referentes + barrios reales
-- - Siembra la tabla `barrios` con los barrios reales del
--   relevamiento (combos separados, typos normalizados)
-- - Nueva tabla `referentes_barrios` (muchos a muchos):
--   un barrio puede tener varios referentes y un referente
--   puede cubrir varios barrios
-- Uso: mysql --default-character-set=utf8mb4 -u USUARIO -p NOMBRE_BD < migracion_usuarios_barrios.sql
-- ============================================================

INSERT INTO barrios (nombre) VALUES
  ('Los Palos'),
  ('Circuito 5'),
  ('San Roque'),
  ('Itapé'),
  ('Sector del 192'),
  ('Sector 150'),
  ('La Quilmes'),
  ('Santa Teresita'),
  ('Villa Sartén'),
  ('San Cayetano'),
  ('Villa Mandarina'),
  ('Puerto Viejo'),
  ('Colonia Perfección Sur'),
  ('Planta Emisora'),
  ('La Rural'),
  ('La Tablada'),
  ('Los Tanques'),
  ('La Curva'),
  ('Cantera 25'),
  ('Mataderos'),
  ('Vicoer'),
  ('Lanús'),
  ('Villa Itapé'),
  ('100 Viviendas'),
  ('80 Viviendas'),
  ('2 de Abril'),
  ('La Higuera'),
  ('Laura Vicuña'),
  ('La Unión'),
  ('El Mirador'),
  ('La Concepción'),
  ('San José'),
  ('América'),
  ('San Isidro'),
  ('San Sebastián'),
  ('Mosconi'),
  ('San Vicente'),
  ('Asentamientos'),
  ('Internacional'),
  ('Los Cachetudos'),
  ('Villa Las Lomas Norte'),
  ('Mena'),
  ('Libertad');

-- Referentes por barrio (muchos a muchos)
CREATE TABLE IF NOT EXISTS referentes_barrios (
  usuario_id INT NOT NULL,
  barrio_id  INT NOT NULL,
  PRIMARY KEY (usuario_id, barrio_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (barrio_id)  REFERENCES barrios(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
