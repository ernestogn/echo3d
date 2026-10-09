-- ============================================================
-- SRCI - Sistema de Reporte Ciudadano de Incidencias
-- Esquema de base de datos
-- Motor: InnoDB | Charset: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS srci
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE srci;

CREATE TABLE IF NOT EXISTS usuarios (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nombre    VARCHAR(100) NOT NULL UNIQUE,
  email     VARCHAR(150),
  pin_hash  VARCHAR(255) NOT NULL,
  rol       ENUM('usuario','admin') DEFAULT 'usuario',
  activo    TINYINT(1) DEFAULT 1,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_incidencia (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  clave  VARCHAR(50)  NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  icono  VARCHAR(50),
  activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incidencias (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo_id    INT NOT NULL,
  latitud    DECIMAL(10,7) NOT NULL,
  longitud   DECIMAL(10,7) NOT NULL,
  notas      TEXT,
  fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
  estado     ENUM('pendiente','en_proceso','resuelto') DEFAULT 'pendiente',
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (tipo_id)    REFERENCES tipos_incidencia(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fotos (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  incidencia_id INT NOT NULL,
  ruta_archivo  VARCHAR(255) NOT NULL,
  fecha_subida  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (incidencia_id) REFERENCES incidencias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tipos_incidencia (clave, nombre, icono) VALUES
  ('arbol_caido',       'Arbol caido / en riesgo',           'tree'),
  ('alcantarilla',      'Alcantarilla tapada / obstruida',   'drain'),
  ('vivienda_precaria', 'Vivienda precaria',                 'house'),
  ('techo_riesgo',      'Techo dudoso / en riesgo',          'roof'),
  ('cableado',          'Cableado peligroso',                'cable'),
  ('fuga_agua',         'Fuga de agua',                      'water'),
  ('basural',           'Basural / acumulacion de residuos', 'trash'),
  ('otro',              'Otro',                              'other');

-- PIN por defecto del admin: 0000
-- Cambiar en primer uso desde el panel admin
INSERT INTO usuarios (nombre, email, pin_hash, rol) VALUES
  ('admin', 'admin@srci.local', '.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
