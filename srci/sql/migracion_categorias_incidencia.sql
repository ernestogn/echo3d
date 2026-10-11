-- SRCI - Migracion: categorias de incidencias
-- Categoria = etiqueta INDEPENDIENTE del tipo de incidencia. Una incidencia puede
-- tener varias categorias a la vez (no excluyentes). La categoria por defecto es
-- "Climaticos": todas las incidencias existentes quedan con esa categoria.
-- Idempotente: se puede correr mas de una vez sin error.

CREATE TABLE IF NOT EXISTS categorias_incidencia (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  clave  VARCHAR(50)  NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  icono  VARCHAR(50),
  color  VARCHAR(7) NULL,
  activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categorias_incidencia (clave, nombre, icono, color) VALUES
  ('climaticos',   'Climáticos',    '🌧️', '#2563eb'),
  ('calles_rotas', 'Calles rotas',  '🛣️', '#ea580c'),
  ('luminarias',   'Luminarias',    '💡', '#d97706'),
  ('arbolado',     'Arbolado',      '🌳', '#16a34a'),
  ('basura',       'Basura',        '🗑️', '#64748b'),
  ('agua_cloaca',  'Agua y cloaca', '💧', '#0891b2');

-- Pivote muchos-a-muchos: incidencia <-> categoria
CREATE TABLE IF NOT EXISTS incidencia_categorias (
  incidencia_id INT NOT NULL,
  categoria_id  INT NOT NULL,
  PRIMARY KEY (incidencia_id, categoria_id),
  FOREIGN KEY (incidencia_id) REFERENCES incidencias(id)          ON DELETE CASCADE,
  FOREIGN KEY (categoria_id)  REFERENCES categorias_incidencia(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: las incidencias existentes quedan en "Climáticos" (categoria por defecto)
INSERT IGNORE INTO incidencia_categorias (incidencia_id, categoria_id)
SELECT i.id, c.id
  FROM incidencias i
  JOIN categorias_incidencia c ON c.clave = 'climaticos';
