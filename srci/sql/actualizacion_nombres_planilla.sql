-- ============================================================
-- SRCI - Migracion de datos: nombres reales + referentes faltantes
-- Fuente: planilla de relevamiento "LISTA ACTUALIZADA COMPLETA" (61 referentes)
--
-- 1) Renombra usuarios cargados como username=email a su nombre real
--    (login.php acepta nombre O email, nadie pierde acceso)
-- 2) Carga los 11 referentes que faltaban por email (activo=0, email NULL;
--    cuando aporten email: admin edita email + "Activar + PIN")
-- 3) Asigna barrios de los nuevos referentes en referentes_barrios
--
-- Idempotente. Los barrios ya asignados coincidian con la planilla (verificado).
-- Uso: mysql --default-character-set=utf8mb4 -u USUARIO -p NOMBRE_BD < actualizacion_nombres_planilla.sql
-- (aplicada en produccion el 10/10/2026 via script PHP one-shot con db())
-- ============================================================

-- 1) Nombres reales (email -> nombre)
UPDATE usuarios SET nombre = 'Alejandro Sittner'          WHERE email = 'alesittner5@gmail.com';
UPDATE usuarios SET nombre = 'Ángel Salamonini'           WHERE email = 'salamonini@yahoo.com.ar';
UPDATE usuarios SET nombre = 'Belén Altamirano'           WHERE email = 'mbaltamirano48@gmail.com';
UPDATE usuarios SET nombre = 'Belén Mérida'               WHERE email = 'belenmerida80@gmail.com';
UPDATE usuarios SET nombre = 'Catalina País'              WHERE email = 'CatalinaPais28@gmail.com';
UPDATE usuarios SET nombre = 'Celeste Olmos'              WHERE email = 'olmosmariaceleste0@gmail.com';
UPDATE usuarios SET nombre = 'Charly Mendoza'             WHERE email = 'charlymendozza0414@gmail.com';
UPDATE usuarios SET nombre = 'Daniel Ramírez'             WHERE email = 'dannnniramirez@gmail.com';
UPDATE usuarios SET nombre = 'Darío Barón'                WHERE email = 'dariobaron5@gmail.com';
UPDATE usuarios SET nombre = 'Ernesto Gabriel Guillaume'  WHERE email = 'ernestogn@gmail.com';
UPDATE usuarios SET nombre = 'Esteban Barthet'            WHERE email = 'estebanbartet1985@gmail.com';
UPDATE usuarios SET nombre = 'Flavio Silva'               WHERE email = 'Flavio_dario_silva@hotmail.com';
UPDATE usuarios SET nombre = 'Florencia Igarzabal'        WHERE email = 'florigarzabal@gmail.com';
UPDATE usuarios SET nombre = 'Gabriel Perdomo'            WHERE email = 'participacionciudadanauruguay@gmail.com';
UPDATE usuarios SET nombre = 'Graciela Guerrero'          WHERE email = 'gracielaguerrero232@gmail.com';
UPDATE usuarios SET nombre = 'Graciela Verón'             WHERE email = 'graciela67veron@gmail.com';
UPDATE usuarios SET nombre = 'Guillermo González'         WHERE email = 'gonzalgui@yahoo.com.ar';
UPDATE usuarios SET nombre = 'Guillermo Lugrín'           WHERE email = 'guillelugrin@gmail.com';
UPDATE usuarios SET nombre = 'Guillermo Ziegler'          WHERE email = 'GuillermoZiegler3@gmail.com';
UPDATE usuarios SET nombre = 'Hilda Arraygada'            WHERE email = 'hildaarraygada1@gmail.com';
UPDATE usuarios SET nombre = 'Jonathan Álvarez'           WHERE email = 'alvarezyonatan82@gmail.com';
UPDATE usuarios SET nombre = 'Jorge Dellavedova'          WHERE email = 'dellavedovajorge@hotmail.com.ar';
UPDATE usuarios SET nombre = 'Jorge Sittoni'              WHERE email = 'sittonijorge@gmail.com';
UPDATE usuarios SET nombre = 'Juan C. Botta'              WHERE email = 'juancitobotta8@gmail.com';
UPDATE usuarios SET nombre = 'Juan Martín Garay'          WHERE email = 'garayjuanmartin@gmail.com';
UPDATE usuarios SET nombre = 'Lucía Monzón'               WHERE email = 'luciamonzon918@gmail.com';
UPDATE usuarios SET nombre = 'Luciano Rodríguez'          WHERE email = 'lucianomrodriguez8@gmail.com';
UPDATE usuarios SET nombre = 'Luis Quinteros'             WHERE email = 'luissalud00@gmail.com';
UPDATE usuarios SET nombre = 'Marcelo Quinteros'          WHERE email = 'Marceloq73@gmail.com';
UPDATE usuarios SET nombre = 'María Rosa Pinget'          WHERE email = 'mariy123pinget@gmail.com';
UPDATE usuarios SET nombre = 'Mariana Bardisa'            WHERE email = 'marianabardisa@gmail.com';
UPDATE usuarios SET nombre = 'Mariana Marclay'            WHERE email = 'comunicaciondiputadamarclay@gmail.com';
UPDATE usuarios SET nombre = 'Mariano Martinez'           WHERE email = 'danilomarianomartinez94@gmail.com';
UPDATE usuarios SET nombre = 'Mario José Mosqueda'        WHERE email = 'mariomosqueda774@gmail.com';
UPDATE usuarios SET nombre = 'Maximiliano Scaglia'        WHERE email = 'maxiscaglia2@gmail.com';
UPDATE usuarios SET nombre = 'Milagros Miguez'            WHERE email = 'dmmiguez11@gmail.com';
UPDATE usuarios SET nombre = 'Nanci Orcajo'               WHERE email = 'elisabetorcajo76@gmail.com';
UPDATE usuarios SET nombre = 'Nicolás Chiozza'            WHERE email = 'fnicolaschiozza@gmail.com';
UPDATE usuarios SET nombre = 'Norma Sosa'                 WHERE email = 'normasosa43@gmail.com';
UPDATE usuarios SET nombre = 'Paola Jacquement'           WHERE email = 'sildajacquement@gmail.com';
UPDATE usuarios SET nombre = 'Paula Benítez'              WHERE email = 'benitez.pau88@gmail.com';
UPDATE usuarios SET nombre = 'Pepo (Hugo Fabián) Barrios' WHERE email = 'hugofabianbarrios@hotmail.com';
UPDATE usuarios SET nombre = 'Romina López'               WHERE email = 'romina.noemi.lopez25@gmail.com';
UPDATE usuarios SET nombre = 'Sergio Vereda'              WHERE email = 'sergiivereda1@gmail.com';
UPDATE usuarios SET nombre = 'Taka Rondoni'               WHERE email = 'luchorondoni@yahoo.com';
UPDATE usuarios SET nombre = 'Valeria Gómez'              WHERE email = 'valetag078@gmail.com';
UPDATE usuarios SET nombre = 'Valeria Jauregui'           WHERE email = 'valejauregui09@gmail.com';
UPDATE usuarios SET nombre = 'Vamesa Zanandrea'           WHERE email = 'vanezanandrea@gmail.com';
UPDATE usuarios SET nombre = 'Vanesa Miño'                WHERE email = 'vanesamabel0@gmail.com';
UPDATE usuarios SET nombre = 'Walter Barsotti'            WHERE email = 'walterbarsotti@gmail.com';

-- 2) Referentes sin email (activo=0 hasta que aporten email; el pin_hash
--    es un placeholder aleatorio: el PIN real se genera en "Activar + PIN")
INSERT INTO usuarios (nombre, email, pin_hash, rol, activo)
SELECT x.nombre, NULL, x.hash, 'usuario', 0
FROM (
  SELECT 'Alejandro Osuna'        AS nombre, '$2y$10$cargaPendientePlaceholderCambiaAlActivar' AS hash
  UNION ALL SELECT 'Cristian Bonato'
  UNION ALL SELECT 'Damián (Pacha) Avalos'
  UNION ALL SELECT 'Ezequiel Valdunciel'
  UNION ALL SELECT 'Luciano Cieri'
  UNION ALL SELECT 'Marcelo Reynoso'
  UNION ALL SELECT 'Miguel Toledo'
  UNION ALL SELECT 'Nicolás Angellini'
  UNION ALL SELECT 'Pato Richard'
  UNION ALL SELECT 'René Serrano'
  UNION ALL SELECT 'Jorgito Dennis'
) x
WHERE NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.nombre = x.nombre);

-- 3) Barrios de los nuevos referentes
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Los Palos'
WHERE u.nombre = 'Alejandro Osuna';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Circuito 5'
WHERE u.nombre = 'Alejandro Osuna';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Puerto Viejo'
WHERE u.nombre = 'Cristian Bonato';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Planta Emisora'
WHERE u.nombre = 'Damián (Pacha) Avalos';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'La Rural'
WHERE u.nombre = 'Damián (Pacha) Avalos';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'La Tablada'
WHERE u.nombre = 'Ezequiel Valdunciel';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'El Mirador'
WHERE u.nombre = 'Luciano Cieri';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Santa Teresita'
WHERE u.nombre = 'Luciano Cieri';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Mataderos'
WHERE u.nombre = 'Marcelo Reynoso';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'América'
WHERE u.nombre = 'Marcelo Reynoso';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Circuito 5'
WHERE u.nombre = 'Miguel Toledo';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = '80 Viviendas'
WHERE u.nombre = 'Miguel Toledo';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'San Roque'
WHERE u.nombre = 'Miguel Toledo';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Santa Teresita'
WHERE u.nombre = 'Nicolás Angellini';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Cantera 25'
WHERE u.nombre = 'Nicolás Angellini';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'La Concepción'
WHERE u.nombre = 'Pato Richard';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'La Quilmes'
WHERE u.nombre = 'Pato Richard';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Villa Itapé'
WHERE u.nombre = 'René Serrano';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'Mataderos'
WHERE u.nombre = 'René Serrano';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'La Quilmes'
WHERE u.nombre = 'René Serrano';
INSERT IGNORE INTO referentes_barrios (usuario_id, barrio_id)
SELECT u.id, b.id FROM usuarios u JOIN barrios b ON b.nombre = 'La Concepción'
WHERE u.nombre = 'Jorgito Dennis';
