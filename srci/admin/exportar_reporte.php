<?php
declare(strict_types=1);

/**
 * Exportacion de datos crudos del generador de reportes.
 *
 * Formatos:
 *  - csv     : UTF-8 con BOM + separador ";" (Excel en espanol).
 *  - geojson : FeatureCollection WGS84 con propiedades enriquecidas (QGIS).
 *
 * Solo admins. Cada descarga queda registrada en auditoria como
 * "exportar_reporte". Las filas sin coordenadas se omiten del GeoJSON
 * (siguen disponibles en el CSV).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/funciones_reportes.php';

requiere_admin();

$formato = strtolower(trim((string)($_GET['formato'] ?? '')));
if (!in_array($formato, ['csv', 'geojson'], true)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'Formato no valido. Use formato=csv o formato=geojson.';
  exit;
}

$filtros = normalizar_filtros_reportes($_GET);
['where' => $where_sql, 'params' => $params] = construir_consulta_reportes($filtros);

$filas = obtener_incidencias_reporte(
  $where_sql,
  $params,
  'fecha',
  'desc',
  REPORTES_LIMITE_EXPORTACION
);

$mapa_categorias = categorias_por_incidencia(array_column($filas, 'id'));

$marca_tiempo = date('Y-m-d_Hi');

if ($formato === 'csv') {
  registrar_salida_reporte('exportar_reporte', 'CSV', $filtros, count($filas));

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="reporte_incidencias_' . $marca_tiempo . '.csv"');
  header('Cache-Control: no-store');

  // BOM UTF-8 para que Excel en espanol respete acentos y emojis.
  echo "\xEF\xBB\xBF";

  $salida = fopen('php://output', 'w');
  if ($salida === false) {
    exit;
  }

  fputcsv($salida, columnas_csv_reporte(), ';');

  foreach ($filas as $fila) {
    $categorias = $mapa_categorias[(int)$fila['id']] ?? [];
    fputcsv($salida, fila_csv_reporte($fila, $categorias), ';');
  }

  fclose($salida);
  exit;
}

// GeoJSON
$geojson = construir_geojson_reporte($filas, $mapa_categorias);
registrar_salida_reporte(
  'exportar_reporte',
  'GeoJSON',
  $filtros,
  count($geojson['features'])
);

header('Content-Type: application/geo+json; charset=utf-8');
header('Content-Disposition: attachment; filename="reporte_incidencias_' . $marca_tiempo . '.geojson"');
header('Cache-Control: no-store');

echo json_encode(
  $geojson,
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);
exit;
