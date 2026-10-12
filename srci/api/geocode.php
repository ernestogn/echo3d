<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

// Geocodificacion inversa (punto -> direccion) via Nominatim/OpenStreetMap.
// Proxy + cache en archivos: el navegador del usuario nunca habla con OSM
// directamente (no expone su IP) y los puntos repetidos no re-consultan.
// Politica de Nominatim: max 1 req/seg y User-Agent identificable -> el cliente
// solo consulta al marcar un punto (con debounce en reporte.js) y aca hay cache.
// GET /srci/api/geocode.php?lat=-32.4826&lon=-58.2445
//   -> {ok:true, direccion:"...", detalle:"..."}  |  {ok:false, error:"..."}
requiere_sesion();

$lat = (float)($_GET['lat'] ?? 0);
$lon = (float)($_GET['lon'] ?? 0);
if ($lat === 0.0 || $lon === 0.0 || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
  respuesta_json(['ok' => false, 'error' => 'Coordenadas invalidas'], 400);
}

// Redondeo a 4 decimales (~11 m): la misma cuadra comparte cache
$lat_r   = round($lat, 4);
$lon_r   = round($lon, 4);
$archivo = sys_get_temp_dir() . '/srci-geocode/geo_' . md5($lat_r . '|' . $lon_r) . '.json';

// 1. Cache (30 dias)
$dir_cache = sys_get_temp_dir() . '/srci-geocode';
if (!is_dir($dir_cache)) {
  @mkdir($dir_cache, 0775, true);
}
if (is_file($archivo) && (time() - (int)filemtime($archivo)) < 86400 * 30) {
  $cache = json_decode((string)file_get_contents($archivo), true);
  if (is_array($cache)) {
    respuesta_json($cache);
  }
}

// 2. Nominatim (timeout corto: si falla, la direccion queda manual como siempre)
$url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
  'lat'            => $lat_r,
  'lon'            => $lon_r,
  'format'         => 'jsonv2',
  'zoom'           => 18,
  'addressdetails' => '1',
  'accept-language' => 'es',
]);
$ctx   = stream_context_create(['http' => [
  'method'  => 'GET',
  'header'  => "User-Agent: SRCI/1.0 (echo3dlaser.com.ar; contacto admin@echo3dlaser.com.ar)\r\n"
             . "Accept: application/json\r\n",
  'timeout' => 4,
]]);
$crudo = @file_get_contents($url, false, $ctx);
if ($crudo === false) {
  respuesta_json(['ok' => false, 'error' => 'Servicio no disponible']);
}
$datos = json_decode($crudo, true);
if (!is_array($datos) || empty($datos['address'])) {
  // Cachea tambien el fallo para no martillar el servicio con el mismo punto
  @file_put_contents($archivo, json_encode(['ok' => false], JSON_UNESCAPED_UNICODE));
  respuesta_json(['ok' => false, 'error' => 'Sin resultado']);
}

$a      = $datos['address'];
$calle  = (string)($a['road'] ?? $a['pedestrian'] ?? $a['footway'] ?? '');
$altura = (string)($a['house_number'] ?? '');
$partes = [];
// Todas las direcciones son de Concepcion del Uruguay: la ciudad NO se agrega
// (evita "Bartolome Mitre, Concepcion del Uruguay" cuando OSM no trae altura).
if ($calle !== '') {
  $partes[] = $calle . ($altura !== '' ? ' ' . $altura : '');
}

$respuesta = [
  'ok'        => true,
  'direccion' => $partes ? implode(', ', $partes) : (string)($datos['display_name'] ?? ''),
  'detalle'   => (string)($datos['display_name'] ?? ''),
];
@file_put_contents($archivo, json_encode($respuesta, JSON_UNESCAPED_UNICODE), LOCK_EX);
respuesta_json($respuesta);
