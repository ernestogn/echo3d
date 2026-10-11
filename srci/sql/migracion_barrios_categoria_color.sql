-- Migracion: categoria y color de render de barrios
-- categoria: naturaleza del barrio (formal definido por catastro / informal / asentamiento)
-- color:     color hex (#rrggbb) con el que se dibuja el poligono en el mapa; NULL = usar el de la categoria
-- Idempotente: verificar con "SHOW COLUMNS FROM barrios" antes de correr (o usar el runner en PHP que chequea).

ALTER TABLE barrios
  ADD COLUMN categoria ENUM('formal','informal','asentamiento') NULL DEFAULT NULL AFTER poligono,
  ADD COLUMN color     VARCHAR(7) NULL DEFAULT NULL AFTER categoria;
