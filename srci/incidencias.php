<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funciones.php';

requiere_sesion();

// --- Filtros ---
$tipo_id     = (int)($_GET['tipo_id']     ?? 0);
$estado      = $_GET['estado']      ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$pagina      = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina  = 20;
$offset      = ($pagina - 1) * $por_pagina;

$donde  = [];
$params = [];

if ($tipo_id > 0) {
  $donde[]            = 'i.tipo_id = :tipo_id';
  $params[':tipo_id'] = $tipo_id;
}
$estados_validos = ['pendiente', 'en_proceso', 'resuelto'];
if ($estado !== '' && in_array($estado, $estados_validos, true)) {
  $donde[]          = 'i.estado = :estado';
  $params[':estado'] = $estado;
}
if ($fecha_desde !== '') {
  $donde[]              = 'i.fecha_hora >= :fecha_desde';
  $params[':fecha_desde'] = $fecha_desde . ' 00:00:00';
}
if ($fecha_hasta !== '') {
  $donde[]              = 'i.fecha_hora <= :fecha_hasta';
  $params[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
}

$clausula_where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

// Exportacion CSV o GeoJSON
$exportar = $_GET['exportar'] ?? '';
if ($exportar === 'csv' || $exportar === 'geojson') {
  $sql = "SELECT i.id, i.latitud, i.longitud, i.estado, i.fecha_hora, i.notas,
                 t.nombre AS tipo_nombre, u.nombre AS usuario_nombre
          FROM incidencias i
          JOIN tipos_incidencia t ON t.id = i.tipo_id
          JOIN usuarios u         ON u.id = i.usuario_id
          {$clausula_where}
          ORDER BY i.fecha_hora DESC";
  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  $filas = $stmt->fetchAll();

  if ($exportar === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="incidencias_' . date('Y-m-d') . '.csv"');
    $salida = fopen('php://output', 'w');
    fputcsv($salida, ['ID','Tipo','Estado','Latitud','Longitud','Fecha','Usuario','Notas'], ';');
    foreach ($filas as $f) {
      fputcsv($salida, [
        $f['id'], $f['tipo_nombre'], $f['estado'],
        $f['latitud'], $f['longitud'], $f['fecha_hora'],
        $f['usuario_nombre'], $f['notas']
      ], ';');
    }
    fclose($salida);
    exit;
  }

  if ($exportar === 'geojson') {
    header('Content-Type: application/geo+json; charset=utf-8');
    header('Content-Disposition: attachment; filename="incidencias_' . date('Y-m-d') . '.geojson"');
    $features = array_map(fn($f) => [
      'type'       => 'Feature',
      'geometry'   => ['type' => 'Point', 'coordinates' => [(float)$f['longitud'], (float)$f['latitud']]],
      'properties' => [
        'id'       => $f['id'], 'tipo'    => $f['tipo_nombre'],
        'estado'   => $f['estado'], 'fecha'   => $f['fecha_hora'],
        'usuario'  => $f['usuario_nombre'], 'notas'   => $f['notas'],
      ],
    ], $filas);
    echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
  }
}

// Contar total para paginacion
$stmt_count = db()->prepare("SELECT COUNT(*) FROM incidencias i JOIN tipos_incidencia t ON t.id = i.tipo_id JOIN usuarios u ON u.id = i.usuario_id {$clausula_where}");
$stmt_count->execute($params);
$total       = (int)$stmt_count->fetchColumn();
$total_pags  = (int)ceil($total / $por_pagina);

// Consulta paginada
$sql = "SELECT i.id, i.latitud, i.longitud, i.estado, i.fecha_hora, i.notas,
               t.nombre AS tipo_nombre, t.icono AS tipo_icono,
               u.nombre AS usuario_nombre
        FROM incidencias i
        JOIN tipos_incidencia t ON t.id = i.tipo_id
        JOIN usuarios u         ON u.id = i.usuario_id
        {$clausula_where}
        ORDER BY i.fecha_hora DESC
        LIMIT {$por_pagina} OFFSET {$offset}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$incidencias = $stmt->fetchAll();

// Tipos para el select del filtro
$tipos_lista = db()->query('SELECT id, nombre FROM tipos_incidencia WHERE activo=1 ORDER BY nombre')->fetchAll();

$nombre_usuario = esc($_SESSION['nombre']);

// URL base para filtros y paginacion
function url_filtros(array $extras = []): string {
  $base = ['tipo_id' => $_GET['tipo_id'] ?? '', 'estado' => $_GET['estado'] ?? '', 'fecha_desde' => $_GET['fecha_desde'] ?? '', 'fecha_hasta' => $_GET['fecha_hasta'] ?? ''];
  $merged = array_merge($base, $extras);
  return '/srci/incidencias.php?' . http_build_query(array_filter($merged, fn($v) => $v !== '' && $v !== '0' && $v !== 0));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Listado de Incidencias — SRCI</title>
  <meta name="description" content="Listado completo de incidencias reportadas por los ciudadanos.">
  <link rel="stylesheet" href="/srci/assets/css/estilos.css">
</head>
<body>

<nav class="nav-principal" role="navigation">
  <div class="nav-contenido">
    <a href="/srci/index.php" class="nav-logo">
      <span class="nav-logo-icono" aria-hidden="true">🗺️</span>
      SRCI
    </a>
    <div class="nav-enlaces">
      <a href="/srci/index.php"       class="nav-enlace">Mapa</a>
      <a href="/srci/incidencias.php" class="nav-enlace activo">Listado</a>
      <?php if (es_admin()): ?>
        <a href="/srci/admin/reportes.php" class="nav-enlace">Admin</a>
      <?php endif; ?>
    </div>
    <div class="nav-usuario">
      <span>👤 <?= $nombre_usuario ?></span>
      <a href="/srci/logout.php" class="boton boton-secundario boton-sm">Salir</a>
    </div>
  </div>
</nav>

<main class="pagina-contenedor">
  <div class="pagina-encabezado">
    <h1>Incidencias reportadas</h1>
    <div style="display:flex;gap:var(--espacio-sm);flex-wrap:wrap;">
      <a href="<?= url_filtros(['exportar' => 'csv']) ?>" class="boton boton-secundario boton-sm">⬇ CSV</a>
      <a href="<?= url_filtros(['exportar' => 'geojson']) ?>" class="boton boton-secundario boton-sm">⬇ GeoJSON</a>
    </div>
  </div>

  <!-- Filtros -->
  <form class="filtros-barra" method="GET" action="/srci/incidencias.php">
    <div class="campo">
      <label for="f-tipo">Tipo</label>
      <select id="f-tipo" name="tipo_id">
        <option value="">Todos los tipos</option>
        <?php foreach ($tipos_lista as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= $tipo_id === (int)$t['id'] ? 'selected' : '' ?>>
            <?= esc($t['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="f-estado">Estado</label>
      <select id="f-estado" name="estado">
        <option value="">Todos los estados</option>
        <option value="pendiente"  <?= $estado === 'pendiente'  ? 'selected' : '' ?>>Pendiente</option>
        <option value="en_proceso" <?= $estado === 'en_proceso' ? 'selected' : '' ?>>En proceso</option>
        <option value="resuelto"   <?= $estado === 'resuelto'   ? 'selected' : '' ?>>Resuelto</option>
      </select>
    </div>
    <div class="campo">
      <label for="f-desde">Desde</label>
      <input type="date" id="f-desde" name="fecha_desde" value="<?= esc($fecha_desde) ?>">
    </div>
    <div class="campo">
      <label for="f-hasta">Hasta</label>
      <input type="date" id="f-hasta" name="fecha_hasta" value="<?= esc($fecha_hasta) ?>">
    </div>
    <div style="display:flex;gap:var(--espacio-sm);align-items:flex-end;">
      <button type="submit" class="boton boton-primario">Filtrar</button>
      <a href="/srci/incidencias.php" class="boton boton-secundario">Limpiar</a>
    </div>
  </form>

  <!-- Tabla -->
  <?php if (empty($incidencias)): ?>
    <div class="mensaje mensaje-info">
      <span>ℹ️</span>
      <span>No hay incidencias que coincidan con los filtros seleccionados.</span>
    </div>
  <?php else: ?>
    <div class="tabla-contenedor">
      <table class="tabla-incidencias" id="tabla-incidencias">
        <thead>
          <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Notas</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($incidencias as $inc): ?>
            <tr>
              <td><?= (int)$inc['id'] ?></td>
              <td><?= esc($inc['tipo_icono'] ?? '') ?> <?= esc($inc['tipo_nombre']) ?></td>
              <td><span class="estado-badge <?= clase_estado($inc['estado']) ?>"><?= esc(nombre_estado($inc['estado'])) ?></span></td>
              <td style="white-space:nowrap;"><?= esc(fecha_legible($inc['fecha_hora'])) ?></td>
              <td><?= esc($inc['usuario_nombre']) ?></td>
              <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= esc($inc['notas'] ?? '') ?>"><?= esc(substr($inc['notas'] ?? '', 0, 60)) ?></td>
              <td><a href="/srci/detalle.php?id=<?= (int)$inc['id'] ?>" class="boton boton-secundario boton-sm">Ver</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginacion -->
    <?php if ($total_pags > 1): ?>
      <nav class="paginacion" aria-label="Paginacion">
        <?php if ($pagina > 1): ?>
          <a href="<?= url_filtros(['pagina' => $pagina - 1]) ?>" class="pagina-enlace" aria-label="Pagina anterior">‹</a>
        <?php endif; ?>
        <?php for ($p = max(1, $pagina - 2); $p <= min($total_pags, $pagina + 2); $p++): ?>
          <a href="<?= url_filtros(['pagina' => $p]) ?>" class="pagina-enlace <?= $p === $pagina ? 'activa' : '' ?>" aria-current="<?= $p === $pagina ? 'page' : 'false' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($pagina < $total_pags): ?>
          <a href="<?= url_filtros(['pagina' => $pagina + 1]) ?>" class="pagina-enlace" aria-label="Pagina siguiente">›</a>
        <?php endif; ?>
      </nav>
      <p style="text-align:center;color:var(--color-texto-suave);font-size:.875rem;margin-top:var(--espacio-sm);">
        <?= $total ?> incidencias encontradas · Página <?= $pagina ?> de <?= $total_pags ?>
      </p>
    <?php endif; ?>
  <?php endif; ?>
</main>

</body>
</html>
