<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiere_admin();

$mensaje  = '';
$tipo_msg = '';

$id = (int)($_GET['id'] ?? $_POST['barrio_id'] ?? 0);
if ($id <= 0) {
  header('Location: /srci/admin/barrios.php');
  exit;
}

$cargar_barrio = function () use ($id) {
  $st = db()->prepare('SELECT id, nombre, poligono, categoria, color, activo, creado_en FROM barrios WHERE id = :id');
  $st->execute([':id' => $id]);
  return $st->fetch();
};

$barrio = $cargar_barrio();
if (!$barrio) {
  header('Location: /srci/admin/barrios.php');
  exit;
}

// Descargar el poligono actual como GeoJSON
if (($_GET['descargar'] ?? '') === '1') {
  $anillos = $barrio['poligono'] ? json_decode($barrio['poligono'], true) : [];
  $geojson = anillos_a_geojson(is_array($anillos) ? $anillos : []);
  header('Content-Type: application/geo+json; charset=utf-8');
  header('Content-Disposition: attachment; filename="barrio-' . $id . '.geojson"');
  echo json_encode($geojson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validar_csrf();
  $accion = $_POST['accion'] ?? '';

  if ($accion === 'guardar') {
    $nombre    = trim($_POST['nombre'] ?? '');
    $activo    = isset($_POST['activo']) ? 1 : 0;
    $categoria = (string)($_POST['categoria'] ?? '');
    $geojson   = (string)($_POST['geojson'] ?? '');

    if (!in_array($categoria, ['formal', 'informal', 'asentamiento'], true)) {
      $categoria = null;
    }
    $color = color_valido($_POST['color'] ?? '');

    if ($nombre === '') {
      $mensaje  = 'El nombre del barrio es obligatorio.';
      $tipo_msg = 'error';
    } else {
      $st = db()->prepare('SELECT id FROM barrios WHERE nombre = :n AND id <> :id');
      $st->execute([':n' => $nombre, ':id' => $id]);
      if ($st->fetch()) {
        $mensaje  = 'Ya existe otro barrio con ese nombre.';
        $tipo_msg = 'error';
      } else {
        $cambios  = [];
        $poligono = $barrio['poligono']; // sin cambios por defecto

        if ($nombre !== $barrio['nombre']) {
          $cambios[] = "nombre: '{$barrio['nombre']}' -> '{$nombre}'";
        }
        if ($activo !== (int)$barrio['activo']) {
          $cambios[] = 'estado: ' . ($barrio['activo'] ? 'activo' : 'inactivo') . ' -> ' . ($activo ? 'activo' : 'inactivo');
        }
        if ($categoria !== $barrio['categoria']) {
          $cambios[] = 'categoría: ' . nombre_categoria($barrio['categoria']) . ' -> ' . nombre_categoria($categoria);
        }
        if ($color !== $barrio['color']) {
          $cambios[] = 'color: ' . ($barrio['color'] ?? '(auto)') . ' -> ' . ($color ?? '(auto)');
        }

        // Poligono: solo si el cliente dibujo/cargo algo (geojson no vacio)
        if (trim($geojson) !== '') {
          $res = geojson_a_anillos($geojson);
          if (!$res['ok']) {
            $mensaje  = 'No se pudo leer el polígono: ' . $res['error'];
            $tipo_msg = 'error';
          } else {
            $nuevo = json_encode($res['anillos'], JSON_UNESCAPED_SLASHES);
            if ($nuevo !== $barrio['poligono']) {
              $c = contar_anillos_poligono($nuevo);
              $cambios[] = 'polígono: ' . $c['anillos'] . ' anillo(s), ' . $c['vertices'] . ' vértice(s)';
            }
            $poligono = $nuevo;
          }
        }

        if ($mensaje === '') {
          if (!$cambios) {
            $mensaje  = 'Sin cambios: los datos ya eran esos.';
            $tipo_msg = 'info';
          } else {
            db()->prepare('UPDATE barrios SET nombre = :n, activo = :a, categoria = :cat, color = :col, poligono = :p WHERE id = :id')
               ->execute([':n' => $nombre, ':a' => $activo, ':cat' => $categoria, ':col' => $color, ':p' => $poligono, ':id' => $id]);
            registrar_auditoria(
              (int)$_SESSION['usuario_id'],
              'editar',
              null,
              'Barrio #' . $id . ' (' . $nombre . '): ' . implode('; ', $cambios)
            );
            $mensaje  = 'Barrio actualizado.';
            $tipo_msg = 'exito';
          }
          $barrio = $cargar_barrio();
        }
      }
    }
  }

  if ($accion === 'limpiar') {
    if ($barrio['poligono'] === null || $barrio['poligono'] === '') {
      $mensaje  = 'El barrio no tenía polígono.';
      $tipo_msg = 'info';
    } else {
      db()->prepare('UPDATE barrios SET poligono = NULL WHERE id = :id')->execute([':id' => $id]);
      registrar_auditoria((int)$_SESSION['usuario_id'], 'editar', null, 'Barrio #' . $id . ' (' . $barrio['nombre'] . '): polígono eliminado');
      $mensaje  = 'Polígono eliminado.';
      $tipo_msg = 'exito';
      $barrio   = $cargar_barrio();
    }
  }

  if ($accion === 'recalcular') {
    $filas = db()->query('SELECT id, latitud, longitud FROM incidencias WHERE barrio_id IS NULL AND latitud IS NOT NULL AND longitud IS NOT NULL')->fetchAll();
    $n   = 0;
    $upd = db()->prepare('UPDATE incidencias SET barrio_id = :b WHERE id = :id');
    foreach ($filas as $row) {
      $hit = barrio_por_punto((float)$row['latitud'], (float)$row['longitud']);
      if ($hit) {
        $upd->execute([':b' => $hit['id'], ':id' => $row['id']]);
        $n++;
      }
    }
    registrar_auditoria((int)$_SESSION['usuario_id'], 'recalcular_barrios', null, "Asignó barrio a {$n} incidencia(s) sin barrio");
    $mensaje  = "Recalculado: se asignó barrio a {$n} incidencia(s).";
    $tipo_msg = 'exito';
  }
}

// --- Info reversa ---
$anillos = $barrio['poligono'] ? json_decode($barrio['poligono'], true) : [];
$anillos = is_array($anillos) ? $anillos : [];
$conteo  = contar_anillos_poligono($barrio['poligono']);

$estados = ['pendiente' => 0, 'en_proceso' => 0, 'resuelto' => 0];
$total_inc = 0;
$st = db()->prepare('SELECT estado, COUNT(*) AS c FROM incidencias WHERE barrio_id = :id AND oculto = 0 GROUP BY estado');
$st->execute([':id' => $id]);
foreach ($st->fetchAll() as $r) {
  if (isset($estados[$r['estado']])) $estados[$r['estado']] = (int)$r['c'];
  $total_inc += (int)$r['c'];
}

$gravedades = ['baja' => 0, 'media' => 0, 'alta' => 0, 'critica' => 0];
$st = db()->prepare('SELECT gravedad, COUNT(*) AS c FROM incidencias WHERE barrio_id = :id AND oculto = 0 GROUP BY gravedad');
$st->execute([':id' => $id]);
foreach ($st->fetchAll() as $r) {
  if (isset($gravedades[$r['gravedad']])) $gravedades[$r['gravedad']] = (int)$r['c'];
}

$st = db()->prepare('SELECT COUNT(*) FROM incidencias WHERE barrio_id = :id AND oculto = 0 AND latitud IS NULL');
$st->execute([':id' => $id]);
$sin_ubicacion = (int)$st->fetchColumn();

$st = db()->prepare('SELECT COALESCE(SUM(familias_afectadas), 0) FROM incidencias WHERE barrio_id = :id AND oculto = 0');
$st->execute([':id' => $id]);
$familias = (int)$st->fetchColumn();

$st = db()->prepare("SELECT MIN(fecha_hora) FROM incidencias WHERE barrio_id = :id AND oculto = 0 AND estado <> 'resuelto'");
$st->execute([':id' => $id]);
$mas_antigua = $st->fetchColumn();

$st = db()->prepare('SELECT i.id, i.estado, i.gravedad, i.fecha_hora, i.direccion, t.nombre AS tipo, t.icono
                     FROM incidencias i LEFT JOIN tipos_incidencia t ON t.id = i.tipo_id
                     WHERE i.barrio_id = :id AND i.oculto = 0 ORDER BY i.fecha_hora DESC LIMIT 12');
$st->execute([':id' => $id]);
$ultimas = $st->fetchAll();

$st = db()->prepare('SELECT u.nombre, u.email FROM referentes_barrios rb JOIN usuarios u ON u.id = rb.usuario_id WHERE rb.barrio_id = :id ORDER BY u.nombre');
$st->execute([':id' => $id]);
$referentes = $st->fetchAll();

// Centro del mapa (promedio de vertices; fallback: centro por defecto)
$lat_prom = null;
$lng_prom = null;
if ($anillos) {
  $sla = 0.0; $slo = 0.0; $nv = 0;
  foreach ($anillos as $a) {
    foreach ($a as $pt) { $sla += (float)$pt[0]; $slo += (float)$pt[1]; $nv++; }
  }
  if ($nv > 0) { $lat_prom = $sla / $nv; $lng_prom = $slo / $nv; }
}
$centro = $lat_prom !== null ? [$lat_prom, $lng_prom] : [-32.48262351713079, -58.24455570742029];

$color_actual = color_valido($barrio['color']) ?? color_categoria($barrio['categoria']);

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — <?= esc($barrio['nombre']) ?> · SRCI</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261011f">
</head>
<body>
<nav class="nav-principal">
  <div class="nav-contenido">
    <a href="/srci/index.php" class="nav-logo"><span class="nav-logo-icono">🗺️</span>SRCI</a>
    <div class="nav-enlaces">
      <a href="/srci/index.php"          class="nav-enlace">Mapa</a>
      <a href="/srci/incidencias.php"    class="nav-enlace">Listado</a>
      <a href="/srci/admin/reportes.php" class="nav-enlace activo">Admin</a>
    </div>
    <div class="nav-usuario">
      <a href="/srci/perfil.php" class="nav-perfil">👤 <?= esc((string)($_SESSION['nombre'] ?? '')) ?></a>
      <a href="/srci/logout.php" class="boton boton-secundario boton-sm">Salir</a>
    </div>
  </div>
</nav>

<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-titulo">Panel de administración</div>
    <a href="/srci/admin/reportes.php" class="admin-nav-item">📋 Reportes</a>
    <a href="/srci/admin/usuarios.php" class="admin-nav-item">👥 Usuarios</a>
    <a href="/srci/admin/tipos.php"    class="admin-nav-item">🏷️ Tipos</a>
    <a href="/srci/admin/barrios.php"  class="admin-nav-item activo">🏙️ Barrios</a>
    <a href="/srci/admin/auditoria.php" class="admin-nav-item">🧾 Auditoría</a>
  </aside>

  <main class="admin-main">
    <div class="pagina-encabezado">
      <h1><?= esc($barrio['nombre']) ?></h1>
      <a href="/srci/admin/barrios.php" class="boton boton-secundario boton-sm">← Volver a barrios</a>
    </div>

    <?php if ($mensaje !== ''): ?>
      <div class="mensaje mensaje-<?= esc($tipo_msg) ?>" style="margin-bottom:var(--espacio-xl);">
        <span><?= $tipo_msg === 'exito' ? '✓' : ($tipo_msg === 'info' ? 'ℹ️' : '⚠️') ?></span>
        <span><?= esc($mensaje) ?></span>
      </div>
    <?php endif; ?>

    <!-- Info reversa -->
    <div class="barrio-info">
      <div class="tarjeta barrio-info-tarjeta">
        <h2>Datos</h2>
        <dl class="barrio-datos">
          <dt>ID</dt><dd>#<?= (int)$barrio['id'] ?></dd>
          <dt>Estado</dt>
          <dd>
            <span class="estado-badge" style="<?= $barrio['activo'] ? 'background:rgba(34,197,94,.15);color:var(--color-verde)' : 'background:rgba(239,68,68,.15);color:var(--color-rojo)' ?>">
              <?= $barrio['activo'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </dd>
          <dt>Alta</dt><dd><?= esc(fecha_legible($barrio['creado_en'])) ?></dd>
          <dt>Categoría</dt>
          <dd><span class="estado-badge <?= esc(clase_categoria($barrio['categoria'])) ?>"><?= esc(nombre_categoria($barrio['categoria'])) ?></span></dd>
          <dt>Polígono</dt>
          <dd>
            <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:<?= esc($color_actual) ?>;vertical-align:middle;border:1px solid var(--color-borde);margin-right:6px;"></span>
            <?= $conteo['anillos'] > 0 ? 'Sí' : '<span style="color:var(--color-texto-suave);">No</span>' ?>
          </dd>
        </dl>
      </div>

      <div class="tarjeta barrio-info-tarjeta">
        <h2>Incidencias (<?= (int)$total_inc ?>)</h2>
        <div class="barrio-pills">
          <span class="estado-badge estado-pendiente"><?= (int)$estados['pendiente'] ?> Pendientes</span>
          <span class="estado-badge estado-en-proceso"><?= (int)$estados['en_proceso'] ?> En gestión</span>
          <span class="estado-badge estado-resuelto"><?= (int)$estados['resuelto'] ?> Resueltas</span>
        </div>
        <div class="barrio-pills" style="margin-top:var(--espacio-sm);">
          <span class="gravedad-badge gravedad-baja"><?= (int)$gravedades['baja'] ?> Baja</span>
          <span class="gravedad-badge gravedad-media"><?= (int)$gravedades['media'] ?> Media</span>
          <span class="gravedad-badge gravedad-alta"><?= (int)$gravedades['alta'] ?> Alta</span>
          <span class="gravedad-badge gravedad-critica"><?= (int)$gravedades['critica'] ?> Crítica</span>
        </div>
      </div>

      <div class="tarjeta barrio-info-tarjeta">
        <h2>Más datos</h2>
        <dl class="barrio-datos">
          <dt>Familias afectadas</dt><dd><?= (int)$familias ?></dd>
          <dt>Sin ubicación</dt><dd><?= (int)$sin_ubicacion ?></dd>
          <dt>Abierta más antigua</dt><dd><?= $mas_antigua ? esc(fecha_legible($mas_antigua)) : '—' ?></dd>
        </dl>
      </div>

      <div class="tarjeta barrio-info-tarjeta">
        <h2>Referentes (<?= count($referentes) ?>)</h2>
        <?php if ($referentes): ?>
          <ul class="barrio-referentes">
            <?php foreach ($referentes as $r): ?>
              <li>
                <strong><?= esc($r['nombre']) ?></strong>
                <?php if (!empty($r['email'])): ?><span style="color:var(--color-texto-suave);"> · <?= esc($r['email']) ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p style="color:var(--color-texto-suave);margin:0;">Sin referentes asignados.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Últimas incidencias -->
    <div class="tarjeta" style="margin-bottom:var(--espacio-xl);">
      <h2 style="margin-bottom:var(--espacio-md);">Últimas incidencias</h2>
      <?php if ($ultimas): ?>
        <div class="tabla-contenedor">
          <table class="tabla-incidencias">
            <thead>
              <tr><th>#</th><th>Tipo</th><th>Dirección</th><th>Gravedad</th><th>Estado</th><th>Fecha</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($ultimas as $u): ?>
                <tr>
                  <td><?= (int)$u['id'] ?></td>
                  <td><?= esc($u['icono'] ?? '') ?> <?= esc($u['tipo'] ?? '—') ?></td>
                  <td><?= esc($u['direccion'] ?: '—') ?></td>
                  <td><span class="gravedad-badge <?= esc(clase_gravedad($u['gravedad'])) ?>"><?= esc(nombre_gravedad($u['gravedad'])) ?></span></td>
                  <td><span class="estado-badge <?= esc(clase_estado($u['estado'])) ?>"><?= esc(nombre_estado($u['estado'])) ?></span></td>
                  <td><?= esc(fecha_legible($u['fecha_hora'])) ?></td>
                  <td><a href="/srci/detalle.php?id=<?= (int)$u['id'] ?>" class="boton boton-secundario boton-sm">Ver</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--color-texto-suave);margin:0;">Este barrio todavía no tiene incidencias.</p>
      <?php endif; ?>
    </div>

    <!-- Edición -->
    <div class="tarjeta" style="margin-bottom:var(--espacio-xl);">
      <h2 style="margin-bottom:var(--espacio-md);">Editar barrio</h2>
      <form method="POST" action="/srci/admin/barrio.php?id=<?= (int)$id ?>" id="form-guardar">
        <input type="hidden" name="csrf_token"  value="<?= esc($csrf) ?>">
        <input type="hidden" name="accion"      value="guardar">
        <input type="hidden" name="barrio_id"   value="<?= (int)$id ?>">
        <input type="hidden" name="geojson"     id="geojson-oculto" value="">

        <div class="grilla-form-2" style="align-items:flex-end;margin-bottom:var(--espacio-md);">
          <div class="campo">
            <label for="e-nombre">Nombre del barrio</label>
            <input type="text" id="e-nombre" name="nombre" required maxlength="100" value="<?= esc($barrio['nombre']) ?>">
          </div>
          <div class="campo">
            <label style="display:flex;align-items:center;gap:var(--espacio-sm);cursor:pointer;">
              <input type="checkbox" name="activo" value="1" <?= $barrio['activo'] ? 'checked' : '' ?> style="width:auto;">
              Barrio activo
            </label>
          </div>
        </div>

        <div class="grilla-form-2" style="align-items:flex-end;margin-bottom:var(--espacio-md);">
          <div class="campo">
            <label for="e-categoria">Categoría</label>
            <select id="e-categoria" name="categoria">
              <option value="" <?= $barrio['categoria'] === null ? 'selected' : '' ?>>Sin categoría</option>
              <option value="formal"       <?= $barrio['categoria'] === 'formal'       ? 'selected' : '' ?>>Formal (catastro)</option>
              <option value="informal"     <?= $barrio['categoria'] === 'informal'     ? 'selected' : '' ?>>Informal</option>
              <option value="asentamiento" <?= $barrio['categoria'] === 'asentamiento' ? 'selected' : '' ?>>Asentamiento</option>
            </select>
          </div>
          <div class="campo">
            <label for="e-color">Color del polígono en el mapa</label>
            <div style="display:flex;gap:var(--espacio-sm);align-items:center;">
              <input type="color" id="e-color" name="color" value="<?= esc($color_actual) ?>" style="width:56px;height:40px;padding:2px;cursor:pointer;">
              <button type="button" id="btn-color-cat" class="boton boton-secundario boton-sm">Usar color de la categoría</button>
            </div>
          </div>
        </div>

        <div style="margin-bottom:var(--espacio-sm);display:flex;flex-wrap:wrap;gap:var(--espacio-sm);align-items:center;">
          <label class="boton boton-secundario boton-sm" style="cursor:pointer;margin:0;">
            📁 Subir GeoJSON
            <input type="file" id="archivo-geojson" accept=".geojson,.json,application/geo+json,application/json" style="display:none;">
          </label>
          <?php if ($conteo['anillos'] > 0): ?>
            <a href="/srci/admin/barrio.php?id=<?= (int)$id ?>&descargar=1" class="boton boton-secundario boton-sm">⬇ Descargar actual</a>
          <?php endif; ?>
        </div>

        <div class="campo" style="margin-bottom:var(--espacio-md);">
          <label for="geojson-texto">O pegá el GeoJSON acá</label>
          <textarea id="geojson-texto" rows="3" placeholder='{"type":"FeatureCollection","features":[...]}' style="font-family:monospace;font-size:.8rem;"></textarea>
          <button type="button" id="btn-aplicar-pegado" class="boton boton-secundario boton-sm" style="margin-top:var(--espacio-sm);align-self:flex-start;">Aplicar GeoJSON pegado</button>
        </div>

        <div id="aviso-draw" class="mensaje mensaje-info" style="display:none;margin-bottom:var(--espacio-md);gap:var(--espacio-sm);">
          <span>ℹ️</span>
          <span>No se pudo cargar el editor de dibujo (Leaflet.draw). Igual podés subir o pegar un GeoJSON.</span>
        </div>

        <div class="contenedor-mapa" style="position:relative;margin-bottom:var(--espacio-md);">
          <div id="mapa-barrio" class="mapa-barrio-edicion"></div>
          <div id="aviso-mapa" class="aviso-mapa"></div>
        </div>
        <p style="color:var(--color-texto-suave);font-size:.82rem;margin:-8px 0 var(--espacio-md);">
          Usá las herramientas del mapa para dibujar o editar el polígono. Para quitar el polígono existente usá <strong>Limpiar polígono</strong>.
        </p>

        <div style="display:flex;flex-wrap:wrap;gap:var(--espacio-sm);">
          <button type="submit" class="boton boton-primario">💾 Guardar cambios</button>
        </div>
      </form>

      <?php if ($conteo['anillos'] > 0): ?>
        <form method="POST" action="/srci/admin/barrio.php?id=<?= (int)$id ?>" style="margin-top:var(--espacio-md);"
              onsubmit="return confirm('¿Eliminar el polígono de este barrio?');">
          <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
          <input type="hidden" name="accion"     value="limpiar">
          <input type="hidden" name="barrio_id"  value="<?= (int)$id ?>">
          <button type="submit" class="boton boton-peligro boton-sm">🗑 Limpiar polígono</button>
        </form>
      <?php endif; ?>
    </div>

    <!-- Recalcular -->
    <div class="tarjeta">
      <h2 style="margin-bottom:var(--espacio-sm);">Mantenimiento</h2>
      <p style="color:var(--color-texto-suave);font-size:.88rem;margin-bottom:var(--espacio-md);">
        Recalcula el barrio de las incidencias <strong>sin barrio</strong> que caigan dentro de algún polígono cargado (útil para las ubicadas desde el editor). No modifica las que ya tienen barrio.
      </p>
      <form method="POST" action="/srci/admin/barrio.php?id=<?= (int)$id ?>"
            onsubmit="return confirm('¿Recalcular el barrio de las incidencias sin barrio?');">
        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
        <input type="hidden" name="accion"     value="recalcular">
        <input type="hidden" name="barrio_id"  value="<?= (int)$id ?>">
        <button type="submit" class="boton boton-secundario">🔁 Recalcular barrios de incidencias sin barrio</button>
      </form>
    </div>
  </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script>
const ANILLOS = <?= json_encode($anillos, JSON_UNESCAPED_SLASHES) ?>;
const CENTRO  = <?= json_encode($centro) ?>;
const inputColor = document.getElementById('e-color');
function estiloPoli() {
  const c = (inputColor && inputColor.value) ? inputColor.value : '#4f46e5';
  return { color: c, weight: 2, fillColor: c, fillOpacity: 0.15 };
}

const mapa = L.map('mapa-barrio').setView(CENTRO, 14);
const capaMapa = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' });
const capaSatelite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Tiles © Esri — Esri, Maxar, Earthstar Geographics' });
capaSatelite.addTo(mapa);
L.control.layers({ 'Satélite': capaSatelite, 'Mapa': capaMapa }, null, { position: 'topright' }).addTo(mapa);

const drawnItems   = L.featureGroup().addTo(mapa);
let mapaModificado = false;

ANILLOS.forEach(function (a) {
  if (Array.isArray(a) && a.length >= 3) L.polygon(a, estiloPoli()).addTo(drawnItems);
});
if (drawnItems.getLayers().length) mapa.fitBounds(drawnItems.getBounds().pad(0.15));

// Cambiar el color actualiza en vivo los poligonos del mapa
if (inputColor) {
  inputColor.addEventListener('input', function () { drawnItems.setStyle(estiloPoli()); });
}
// Boton "Usar color de la categoria"
const COLOR_CATEGORIA = { 'formal': '#2563eb', 'informal': '#d97706', 'asentamiento': '#dc2626', '': '#4f46e5' };
const selCategoria = document.getElementById('e-categoria');
const btnColorCat  = document.getElementById('btn-color-cat');
if (btnColorCat && inputColor) {
  btnColorCat.addEventListener('click', function () {
    inputColor.value = COLOR_CATEGORIA[selCategoria ? selCategoria.value : ''] || '#4f46e5';
    drawnItems.setStyle(estiloPoli());
  });
}

if (L.Control && L.Control.Draw) {
  mapa.addControl(new L.Control.Draw({
    position: 'topleft',
    edit: { featureGroup: drawnItems, remove: true },
    draw: {
      polygon: { allowIntersection: false, showArea: true, shapeOptions: estiloPoli() },
      polyline: false, rectangle: false, circle: false, circlemarker: false, marker: false
    }
  }));
  mapa.on(L.Draw.Event.CREATED, function (e) { e.layer.setStyle(estiloPoli()); drawnItems.addLayer(e.layer); mapaModificado = true; });
  mapa.on(L.Draw.Event.EDITED,  function () { mapaModificado = true; });
  mapa.on(L.Draw.Event.DELETED, function () { mapaModificado = true; });
} else {
  document.getElementById('aviso-draw').style.display = 'flex';
}

function exportarGeojson() {
  const features = [];
  drawnItems.eachLayer(function (l) { if (typeof l.toGeoJSON === 'function') features.push(l.toGeoJSON()); });
  return { type: 'FeatureCollection', features: features };
}

function normalizarGeojson(data) {
  const geoms = [];
  if (!data || !data.type) throw new Error('sin type');
  if (data.type === 'FeatureCollection' && Array.isArray(data.features)) {
    data.features.forEach(function (f) { if (f && f.geometry) geoms.push(f.geometry); else if (f && f.type) geoms.push(f); });
  } else if (data.type === 'Feature' && data.geometry) {
    geoms.push(data.geometry);
  } else {
    geoms.push(data);
  }
  const anillos = [];
  geoms.forEach(function (g) {
    if (!g || !g.type || !g.coordinates) return;
    let polis;
    if (g.type === 'Polygon') polis = [g.coordinates];
    else if (g.type === 'MultiPolygon') polis = g.coordinates;
    else return;
    polis.forEach(function (poly) {
      if (!Array.isArray(poly) || !Array.isArray(poly[0])) return;
      const anillo = [];
      poly[0].forEach(function (pt) {
        if (!Array.isArray(pt) || pt.length < 2) return;
        const lng = parseFloat(pt[0]), lat = parseFloat(pt[1]);
        if (isNaN(lat) || isNaN(lng)) return;
        anillo.push([lat, lng]);
      });
      if (anillo.length >= 3) anillos.push(anillo);
    });
  });
  return anillos;
}

function aplicarGeojsonTexto(txt) {
  let anillos;
  try { anillos = normalizarGeojson(JSON.parse(txt)); }
  catch (err) { aviso('El texto no es un GeoJSON válido.', 'err'); return; }
  if (!anillos.length) { aviso('No se encontró ningún polígono válido.', 'err'); return; }
  drawnItems.clearLayers();
  anillos.forEach(function (a) { L.polygon(a, estiloPoli()).addTo(drawnItems); });
  mapa.fitBounds(drawnItems.getBounds().pad(0.15));
  mapaModificado = true;
  aviso('Polígono cargado en el mapa. Revisá y guardá.', 'ok');
}

document.getElementById('archivo-geojson').addEventListener('change', function () {
  const f = this.files[0];
  if (!f) return;
  const reader = new FileReader();
  reader.onload = function () { aplicarGeojsonTexto(String(reader.result)); };
  reader.readAsText(f);
});

document.getElementById('btn-aplicar-pegado').addEventListener('click', function () {
  aplicarGeojsonTexto(document.getElementById('geojson-texto').value);
});

document.getElementById('form-guardar').addEventListener('submit', function () {
  const gj = exportarGeojson();
  // Solo mandar el poligono si el usuario dibujo/cargo algo
  document.getElementById('geojson-oculto').value =
    (mapaModificado && gj.features.length) ? JSON.stringify(gj) : '';
});

function aviso(texto, tipo) {
  const caja = document.getElementById('aviso-mapa');
  if (!caja) return;
  caja.textContent = texto;
  caja.className = 'aviso-mapa visible' + (tipo ? ' aviso-mapa-' + tipo : '');
  clearTimeout(caja._t);
  caja._t = setTimeout(function () { caja.className = 'aviso-mapa'; }, 4000);
}
</script>
</body>
</html>
