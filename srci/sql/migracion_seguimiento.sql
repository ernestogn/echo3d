-- ============================================================
-- SRCI - Migracion: seguimiento (notas fechadas por incidencia)
-- Tabla APPEND-ONLY: solo se insertan filas, nunca se editan ni
-- se borran desde la UI. Cada fila guarda quien y cuando anoto.
-- Idempotente: se puede correr varias veces sin error.
-- Correr sobre la base de datos del SRCI ya seleccionada (no incluye USE).
-- ============================================================

CREATE TABLE IF NOT EXISTS incidencia_seguimiento (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  incidencia_id INT NOT NULL,
  usuario_id    INT,
  nota          TEXT NOT NULL,
  fecha_hora    DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_seguimiento_incidencia (incidencia_id),
  FOREIGN KEY (incidencia_id) REFERENCES incidencias(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id)    REFERENCES usuarios(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
