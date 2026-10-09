<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funciones.php';

requiere_sesion();

// --- Filtros ---
$tipo_id     = (int)($_GET['tipo_id']     ?? 0);
$estado      = $_GET['estado']      ?? '';
$barrio_id   = (int)($_GET['barrio_id']   ?? 0);
$gravedad    = $_GET['gravedad']    ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$pagina      = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina  = 20;
$offset      = ($pagina - 1) * $por_pagina;

$donde  = [];
$params = [];

$gravedades_validas = ['baja', 'media', 'alta', 'critica'];

if ($tipo_id > 0) {
  $donde[]            = 'i.tipo_id = :tipo_id';
  $params[':tipo_id'] = $tipo_id;
}
$estados_validos = ['pendiente', 'en_proceso', 'resuelto'];
if ($estado !== '' && in_array($estado, $estados_validos, true)) {
  $donde[]          = 'i.estado = :estado';
  $params[':estado'] = $estado;
}
if ($barrio_id > 0) {
  $donde[]            = 'i.barrio_id = :barrio_id';
  $params[':barrio_id'] = $barrio_id;
}
if ($gravedad !== '' && in_array($gravedad, $gravedades_validas, true)) {
  $donde[]           = 'i.gravedad = :gravedad';
  $params[':gravedad'] = $gravedad;
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
  $sql = "SELECT i.id, i.latitud, i.longitud, i.estado, i.fecha_hora, i.fecha_resolucion, i.notas,
                 i.direccion, i.gravedad, i.familias_afectadas, i.servicio_afectado,
                 i.calle_intransitable, i.responsable_area, i.contacto_vecino,
                 b.nombre AS barrio_nombre,
                 t.nombre AS tipo_nombre, u.nombre AS usuario_nombre,
                 (SELECT ruta_archivo FROM fotos WHERE incidencia_id = i.id ORDER BY id LIMIT 1) AS foto
          FROM incidencias i
          JOIN tipos_incidencia t ON t.id = i.tipo_id
          JOIN usuarios u         ON u.id = i.usuario_id
          LEFT JOIN barrios b     ON b.id = i.barrio_id
          {$clausula_where}
          ORDER BY i.fecha_hora DESC";
  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  $filas = $stmt->fetchAll();

  // Dias abiertos por fila (como la planilla)
  $dias_fila = function (array $f): int {
    $inicio = new DateTime($f['fecha_hora']);
    $fin    = !empty($f['fecha_resolucion']) ? new DateTime($f['fecha_resolucion']) : new DateTime('now');
    return (int)$inicio->diff($fin)->days;
  };

  if ($exportar === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="incidencias_' . date('Y-m-d') . '.csv"');
    $salida = fopen('php://output', 'w');
    // Columnas alineadas con Planilla_relevamiento_barrios.xlsx
    fputcsv($salida, ['N°','Fecha','Hora','Barrio','Dirección / calle y altura','Tipo de situación','Gravedad','Familias afectadas','Servicio afectado','Calle intransitable','Estado','Responsable / área','Fecha de resolución','Días abiertos','Relevado por','Contacto vecino / referente','Observaciones','Latitud','Longitud','Foto'], ';');
    foreach ($filas as $f) {
      fputcsv($salida, [
        $f['id'],
        date('Y-m-d', strtotime($f['fecha_hora'])),
        date('H:i',    strtotime($f['fecha_hora'])),
        $f['barrio_nombre'] ?? '',
        $f['direccion'] ?? '',
        $f['tipo_nombre'],
        nombre_gravedad($f['gravedad']),
        $f['familias_afectadas'] ?? '',
        $f['servicio_afectado'] ?? '',
        $f['calle_intransitable'] === null ? '' : ($f['calle_intransitable'] ? 'Sí' : 'No'),
        nombre_estado($f['estado']),
        $f['responsable_area'] ?? '',
        $f['fecha_resolucion'] ?? '',
        $dias_fila($f),
        $f['usuario_nombre'],
        $f['contacto_vecino'] ?? '',
        $f['notas'] ?? '',
        $f['latitud'], $f['longitud'],
        $f['foto'] ?? '',
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
        'barrio'   => $f['barrio_nombre'] ?? null,
        'direccion'=> $f['direccion'] ?? null,
        'gravedad' => $f['gravedad'] ?? null,
        'familias_afectadas' => $f['familias_afectadas'],
        'servicio_afectado'  => $f['servicio_afectado'] ?? null,
        'calle_intransitable'=> $f['calle_intransitable'],
        'responsable_area'   => $f['responsable_area'] ?? null,
        'dias_abiertos'=> $dias_fila($f),
        'usuario'  => $f['usuario_nombre'], 'notas'   => $f['notas'] ?? null,
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
$sql = "SELECT i.id, i.latitud, i.longitud, i.estado, i.fecha_hora, i.fecha_resolucion, i.notas,
               i.direccion, i.gravedad, i.familias_afectadas, i.servicio_afectado,
               i.calle_intransitable, i.responsable_area, i.contacto_vecino,
               b.nombre AS barrio_nombre,
               t.nombre AS tipo_nombre, t.icono AS tipo_icono,
               u.nombre AS usuario_nombre
        FROM incidencias i
        JOIN tipos_incidencia t ON t.id = i.tipo_id
        JOIN usuarios u         ON u.id = i.usuario_id
        LEFT JOIN barrios b     ON b.id = i.barrio_id
        {$clausula_where}
        ORDER BY i.fecha_hora DESC
        LIMIT {$por_pagina} OFFSET {$offset}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$incidencias = $stmt->fetchAll();

// Tipos y barrios para los selects de filtro
$tipos_lista   = db()->query('SELECT id, nombre FROM tipos_incidencia WHERE activo=1 ORDER BY nombre')->fetchAll();
$barrios_lista = db()->query('SELECT id, nombre FROM barrios WHERE activo=1 ORDER BY nombre')->fetchAll();

$nombre_usuario = esc((string)($_SESSION['nombre'] ?? ''));

// URL base para filtros y paginacion
function url_filtros(array $extras = []): string {
  $base = ['tipo_id' => $_GET['tipo_id'] ?? '', 'estado' => $_GET['estado'] ?? '', 'barrio_id' => $_GET['barrio_id'] ?? '', 'gravedad' => $_GET['gravedad'] ?? '', 'fecha_desde' => $_GET['fecha_desde'] ?? '', 'fecha_hasta' => $_GET['fecha_hasta'] ?? ''];
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
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261010">
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
      <label for="f-barrio">Barrio</label>
      <select id="f-barrio" name="barrio_id">
        <option value="">Todos los barrios</option>
        <?php foreach ($barrios_lista as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= $barrio_id === (int)$b['id'] ? 'selected' : '' ?>>
            <?= esc($b['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="f-gravedad">Gravedad</label>
      <select id="f-gravedad" name="gravedad">
        <option value="">Toda gravedad</option>
        <option value="baja"    <?= $gravedad === 'baja'    ? 'selected' : '' ?>>Baja</option>
        <option value="media"   <?= $gravedad === 'media'   ? 'selected' : '' ?>>Media</option>
        <option value="alta"    <?= $gravedad === 'alta'    ? 'selected' : '' ?>>Alta</option>
        <option value="critica" <?= $gravedad === 'critica' ? 'selected' : '' ?>>Crítica</option>
      </select>
    </div>
    <div class="campo">
      <label for="f-estado">Estado</label>
      <select id="f-estado" name="estado">
        <option value="">Todos los estados</option>
        <option value="pendiente"  <?= $estado === 'pendiente'  ? 'selected' : '' ?>>Pendiente</option>
        <option value="en_proceso" <?= $estado === 'en_proceso' ? 'selected' : '' ?>>En gestión</option>
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
            <th>Barrio</th>
            <th>Gravedad</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Notas</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($incidencias as $inc):
            $inicio = new DateTime($inc['fecha_hora']);
            $fin    = !empty($inc['fecha_resolucion']) ? new DateTime($inc['fecha_resolucion']) : new DateTime('now');
            $dias   = (int)$inicio->diff($fin)->days;
          ?>
            <tr>
              <td><?= (int)$inc['id'] ?></td>
              <td><?= esc($inc['tipo_icono'] ?? '') ?> <?= esc($inc['tipo_nombre']) ?></td>
              <td><?= esc($inc['barrio_nombre'] ?? '—') ?></td>
              <td><span class="gravedad-badge <?= clase_gravedad($inc['gravedad']) ?>"><?= esc(nombre_gravedad($inc['gravedad'])) ?></span></td>
              <td><span class="estado-badge <?= clase_estado($inc['estado']) ?>"><?= esc(nombre_estado($inc['estado'])) ?></span></td>
              <td style="white-space:nowrap;"><?= esc(fecha_legible($inc['fecha_hora'])) ?></td>
              <td><?= esc($inc['usuario_nombre']) ?></td>
              <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= esc($inc['notas'] ?? '') ?>"><?= esc(substr($inc['notas'] ?? '', 0, 60)) ?></td>
              <td>
                <div style="display:flex;gap:4px;">
                  <button type="button" class="boton boton-secundario boton-sm" onclick="toggleFila(<?= (int)$inc['id'] ?>)" aria-expanded="false" title="Ver todos los campos">▾</button>
                  <a href="/srci/editar.php?id=<?= (int)$inc['id'] ?>" class="boton boton-primario boton-sm">Editar</a>
                </div>
              </td>
            </tr>
            <tr class="fila-expandida" id="expandida-<?= (int)$inc['id'] ?>" style="display:none;">
              <td colspan="9">
                <dl style="display:grid;grid-template-columns:170px 1fr;gap:6px var(--espacio-lg);font-size:.85rem;">
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Dirección</dt>
                  <dd><?= esc($inc['direccion'] ?? '—') ?></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Barrio</dt>
                  <dd><?= esc($inc['barrio_nombre'] ?? '—') ?></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Gravedad</dt>
                  <dd><span class="gravedad-badge <?= clase_gravedad($inc['gravedad']) ?>"><?= esc(nombre_gravedad($inc['gravedad'])) ?></span></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Familias afectadas</dt>
                  <dd><?= $inc['familias_afectadas'] !== null ? (int)$inc['familias_afectadas'] : '—' ?></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Servicio afectado</dt>
                  <dd><?= esc($inc['servicio_afectado'] ?? '—') ?></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Calle intransitable</dt>
                  <dd><?= $inc['calle_intransitable'] === null ? '—' : ($inc['calle_intransitable'] ? 'Sí' : 'No') ?></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Responsable / área</dt>
                  <dd><?= esc($inc['responsable_area'] ?? '—') ?></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Contacto vecino</dt>
                  <dd><?= esc($inc['contacto_vecino'] ?? '—') ?></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Coordenadas</dt>
                  <dd><?= esc($inc['latitud']) ?>, <?= esc($inc['longitud']) ?></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Fecha de resolución</dt>
                  <dd><?= $inc['fecha_resolucion'] ? esc(fecha_legible($inc['fecha_resolucion'])) : '—' ?></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Días abiertos</dt>
                  <dd><?= $dias ?></dd>
                  <dt style="color:var(--color-texto-suave);font-weight:600;">Observaciones</dt>
                  <dd><?= $inc['notas'] ? esc($inc['notas']) : '—' ?></dd>
                </dl>
                <div style="margin-top:var(--espacio-md);display:flex;gap:var(--espacio-sm);">
                  <a href="/srci/detalle.php?id=<?= (int)$inc['id'] ?>" class="boton boton-secundario boton-sm">Ver detalle y fotos</a>
                  <a href="/srci/editar.php?id=<?= (int)$inc['id'] ?>" class="boton boton-primario boton-sm">Editar incidencia</a>
                </div>
              </td>
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

<script>
  function toggleFila(id) {
    const fila = document.getElementById('expandida-' + id);
    const visible = fila.style.display !== 'none';
    fila.style.display = visible ? 'none' : '';
  }
</script>

</body>
</html>
