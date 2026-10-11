<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Poligonos de los barrios (para dibujar limites y detectar barrio en el cliente)
// GET /srci/api/poligonos.php — [{id, nombre, anillos: [[[lat,lng],...], ...]}, ...]
requiere_sesion();

$salida = [];
foreach (db()->query('SELECT id, nombre, poligono FROM barrios WHERE poligono IS NOT NULL AND activo = 1') as $b) {
  $pol = json_decode((string)$b['poligono'], true);
  if (is_array($pol) && $pol) {
    $salida[] = ['id' => (int)$b['id'], 'nombre' => $b['nombre'], 'anillos' => $pol];
  }
}

respuesta_json($salida);
