-- ============================================================
-- SRCI - Migracion: poligonos de barrios
-- Columna `poligono` en barrios: JSON [[[lat,lng],...], ...]
-- (array de anillos exteriores; un barrio puede tener varios)
-- Fuente: relevamiento QGIS del cliente (todas_capas_unidas.csv, WGS84)
-- Uso: mysql --default-character-set=utf8mb4 -u USUARIO -p NOMBRE_BD < migracion_poligonos.sql
-- ============================================================

ALTER TABLE barrios ADD COLUMN poligono TEXT NULL AFTER nombre;
