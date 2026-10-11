<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

// Poligonos de los barrios (para dibujar limites y detectar barrio en el cliente)
// GET /srci/api/poligonos.php — [{id, nombre, categoria, color, anillos: [[[lat,lng],...], ...]}, ...]
requiere_sesion();

$salida = [];
foreach (db()->query('SELECT id, nombre, poligono, categoria, color FROM barrios WHERE poligono IS NOT NULL AND activo = 1') as $b) {
  $pol = json_decode((string)$b['poligono'], true);
  if (is_array($pol) && $pol) {
    $salida[] = [
      'id'        => (int)$b['id'],
      'nombre'    => $b['nombre'],
      'categoria' => $b['categoria'],
      'color'     => color_valido($b['color']) ?? color_categoria($b['categoria']),
      'anillos'   => $pol,
    ];
  }
}

respuesta_json($salida);
