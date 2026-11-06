INSERT INTO categorias (id, nombre, slug, icono, posicion) VALUES
(1, 'Impresión 3D', 'impresion-3d', '🖨️', 1),
(2, 'Corte Láser', 'corte-laser', '✂️', 2),
(3, 'Grabado', 'grabado', '✍️', 3),
(4, 'Diseño', 'diseño', '📐', 4),
(5, 'Filamentos', 'filamentos', '🧵', 5)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), icono = VALUES(icono), posicion = VALUES(posicion);

INSERT INTO productos (id, categoria_id, nombre, slug, descripcion, precio, destacado, activo, imagen, posicion) VALUES
(1, 1, 'Figura Personalizada', 'figura-personalizada', 'Imprimimos tu personaje, mascota o idea en PLAs de color. Ideal para regalos que no se consiguen en ningún lado.', 18000, 1, 1, 'figura-personalizada.svg', 1),
(2, 1, 'Repuesto Técnico 3D', 'repuesto-tecnico-3d', 'Piezas de producción o reemplazo de repuestos difíciles de conseguir, con tolerancias cuidadas.', 12500, 0, 1, 'repuesto-tecnico.svg', 2),
(3, 2, 'Corte Láser MDF', 'corte-laser-mdf', 'Cortamos madera MDF en medidas exactas para proyectos, maquetas y estructuras.', 9900, 1, 1, 'corte-laser-mdf.svg', 3),
(4, 2, 'Letras Corpóreas', 'letras-corporeas', 'Letras 3D con alma de MDF para fachadas y cartelería con terminación prolija.', 14500, 0, 1, 'letras-corporeas.svg', 4),
(5, 3, 'Grabado en Vidrio', 'grabado-vidrio', 'Grabamos vasos, copas y botellas con láser. Perfecto para eventos y regalos corporativos.', 7500, 0, 1, 'grabado-vidrio.svg', 5),
(6, 3, 'Grabado en Madera', 'grabado-madera', 'Tablas, mates y souvenirs personalizados con grabado profundo que no se borra.', 8500, 1, 1, 'grabado-madera.svg', 6),
(7, 4, 'Diseño + Impresión', 'diseno-impresion', 'Si no tenés el archivo, lo diseñamos nosotros y lo fabricamos de principio a fin.', 22000, 1, 1, 'diseno-impresion.svg', 7),
(8, 5, 'Filamento PLA 1kg', 'filamento-pla-1kg', 'Bobina de filamento de calidad para tus proyectos en casa.', 16000, 0, 1, 'filamento-pla.svg', 8)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), categoria_id = VALUES(categoria_id), descripcion = VALUES(descripcion), precio = VALUES(precio), destacado = VALUES(destacado), imagen = VALUES(imagen), posicion = VALUES(posicion);

INSERT INTO marcas (id, nombre, activo, posicion) VALUES
(1, 'PLA', 1, 1),
(2, 'PETG', 1, 2),
(3, 'TPU', 1, 3),
(4, 'ABS', 1, 4),
(5, 'ASA', 1, 5),
(6, 'PC', 1, 6),
(7, 'NYLON', 1, 7),
(8, 'CARBON', 1, 8)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = VALUES(activo);