-- ============================================================
-- SRCI - Migracion: coordenadas opcionales
-- Las incidencias importadas desde la planilla no tienen
-- ubicacion: latitud/longitud pasan a NULL y no aparecen en el
-- mapa hasta que alguien las ubique desde editar.php
-- Uso: mysql --default-character-set=utf8mb4 -u USUARIO -p NOMBRE_BD < migracion_coords_null.sql
-- ============================================================

ALTER TABLE incidencias
  MODIFY latitud DECIMAL(10,7) NULL,
  MODIFY longitud DECIMAL(10,7) NULL;
