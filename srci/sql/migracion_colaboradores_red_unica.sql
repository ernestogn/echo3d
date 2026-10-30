--
-- Migración: una sola red por usuario (UNIQUE en colaboradores.colaborador_id)
-- Idempotente. Normaliza los vinculos duplicados (deja el mas reciente) y
-- convierte el indice simple en UNIQUE, de modo que un colaborador no pueda
-- pertenecer a dos redes a la vez.
--

-- 1. Si un colaborador estaba en varias redes, queda en la mas reciente
DELETE c1 FROM colaboradores c1
  JOIN colaboradores c2
    ON c1.colaborador_id = c2.colaborador_id
   AND c1.id < c2.id;

-- 2. El UNIQUE se crea primero (lo usa la FK `fk_col_col` como indice de apoyo);
--    despues se puede quitar el indice simple, que queda redundante.
ALTER TABLE `colaboradores` ADD UNIQUE KEY IF NOT EXISTS `uq_colaborador` (`colaborador_id`);
ALTER TABLE `colaboradores` DROP INDEX IF EXISTS `idx_colaborador`;
