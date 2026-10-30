--
-- Migración: backfill de los registros existentes de `incidencias`
-- Ejecutar UNA sola vez, despues de migracion_aprobacion_incidencias.sql.
--
-- `aprobado` ya nasce en 1 (DEFAULT) por lo que solo se completa `propietario_id`:
-- cada reporte historico queda asociado a su propio creador como propietario.
--
UPDATE `incidencias`
SET `propietario_id` = `usuario_id`
WHERE `propietario_id` IS NULL;
