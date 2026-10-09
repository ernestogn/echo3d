<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funciones.php';

requiere_sesion();

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) { header('Location: /srci/incidencias.php'); exit; }

$gravedades_validas   = ['baja', 'media', 'alta', 'critica'];
$servicios_validos    = ['Ninguno', 'Luz', 'Agua', 'Luz y agua', 'Vialidad', 'Otro'];
$responsables_validos = ['Cooperativa eléctrica', 'Defensa Civil', 'Municipio', 'Provincia', 'Otro'];

$mensaje  = '';
$tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validar_csrf();

  $tipo_id            = (int)($_POST['tipo_id'] ?? 0);
  $barrio_id          = (int)($_POST['barrio_id'] ?? 0);
  $latitud            = (float)str_replace(',', '.', $_POST['latitud'] ?? '');
  $longitud           = (float)str_replace(',', '.', $_POST['longitud'] ?? '');
  $direccion          = trim($_POST['direccion'] ?? '');
  $gravedad           = $_POST['gravedad'] ?? '';
  $familias           = ($_POST['familias_afectadas'] ?? '') !== '' ? (int)$_POST['familias_afectadas'] : null;
  $servicio           = trim($_POST['servicio_afectado'] ?? '');
  $calleIntransitable = !empty($_POST['calle_intransitable']) ? 1 : 0;
  $responsable        = trim($_POST['responsable_area'] ?? '');
  $contacto           = trim($_POST['contacto_vecino'] ?? '');
  $notas              = trim($_POST['notas'] ?? '');

  if ($tipo_id === 0 || $barrio_id === 0 || $latitud === 0.0 || $longitud === 0.0 || $direccion === '' || !in_array($gravedad, $gravedades_validas, true)) {
    $mensaje  = 'Faltan datos obligatorios (tipo, barrio, coordenadas, dirección y gravedad).';
    $tipo_msg = 'error';
  } elseif ($servicio !== '' && !in_array($servicio, $servicios_validos, true)) {
    $mensaje  = 'Servicio afectado no valido.';
    $tipo_msg = 'error';
  } elseif ($responsable !== '' && !in_array($responsable, $responsables_validos, true)) {
    $mensaje  = 'Responsable / area no valido.';
    $tipo_msg = 'error';
  } else {
    try {
      $stmt = db()->prepare(
        'UPDATE incidencias SET
           tipo_id = :tipo_id, barrio_id = :barrio_id, latitud = :latitud, longitud = :longitud,
           direccion = :direccion, gravedad = :gravedad, familias_afectadas = :familias,
           servicio_afectado = :servicio, calle_intransitable = :calle,
           responsable_area = :responsable, contacto_vecino = :contacto, notas = :notas
         WHERE id = :id'
      );
      $stmt->execute([
        ':tipo_id'     => $tipo_id,
        ':barrio_id'   => $barrio_id,
        ':latitud'     => $latitud,
        ':longitud'    => $longitud,
        ':direccion'   => mb_substr($direccion, 0, 255),
        ':gravedad'    => $gravedad,
        ':familias'    => $familias,
        ':servicio'    => $servicio !== '' ? $servicio : null,
        ':calle'       => $calleIntransitable,
        ':responsable' => $responsable !== '' ? $responsable : null,
        ':contacto'    => $contacto !== '' ? mb_substr($contacto, 0, 120) : null,
        ':notas'       => $notas !== '' ? $notas : null,
        ':id'          => $id,
      ]);
      $mensaje  = 'Incidencia actualizada.';
      $tipo_msg = 'exito';
    } catch (PDOException $e) {
      $mensaje  = 'No se pudo actualizar la incidencia.';
      $tipo_msg = 'error';
    }
  }
}

$stmt = db()->prepare(
  'SELECT i.*, t.nombre AS tipo_nombre, t.icono AS tipo_icono, u.nombre AS usuario_nombre, b.nombre AS barrio_nombre
   FROM incidencias i
   JOIN tipos_incidencia t ON t.id = i.tipo_id
   JOIN usuarios u         ON u.id = i.usuario_id
   LEFT JOIN barrios b     ON b.id = i.barrio_id
   WHERE i.id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$inc = $stmt->fetch();
if (!$inc) { http_response_code(404); die('Incidencia no encontrada.'); }

$tipos_lista   = db()->query('SELECT id, nombre, icono FROM tipos_incidencia WHERE activo = 1 ORDER BY id')->fetchAll();
$barrios_lista = db()->query('SELECT id, nombre FROM barrios WHERE activo = 1 ORDER BY nombre')->fetchAll();
$csrf          = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar incidencia #<?= $id ?> — SRCI</title>
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261010">
</head>
<body>

<nav class="nav-principal">
  <div class="nav-contenido">
    <a href="/srci/index.php" class="nav-logo"><span class="nav-logo-icono">🗺️</span>SRCI</a>
    <div class="nav-enlaces">
      <a href="/srci/index.php"       class="nav-enlace">Mapa</a>
      <a href="/srci/incidencias.php" class="nav-enlace">Listado</a>
      <?php if (es_admin()): ?>
        <a href="/srci/admin/reportes.php" class="nav-enlace">Admin</a>
      <?php endif; ?>
    </div>
    <div class="nav-usuario">
      <a href="/srci/logout.php" class="boton boton-secundario boton-sm">Salir</a>
    </div>
  </div>
</nav>

<main class="pagina-contenedor" style="max-width:800px;">
  <div style="margin-bottom:var(--espacio-lg);display:flex;justify-content:space-between;flex-wrap:wrap;gap:var(--espacio-md);">
    <a href="/srci/incidencias.php" style="color:var(--color-texto-suave);font-size:.9rem;">← Volver al listado</a>
    <a href="/srci/detalle.php?id=<?= $id ?>" style="color:var(--color-texto-suave);font-size:.9rem;">Ver detalle y fotos →</a>
  </div>

  <div class="pagina-encabezado" style="margin-bottom:var(--espacio-lg);">
    <h1 style="font-size:1.375rem;"><?= esc($inc['tipo_icono']) ?> Editar incidencia #<?= (int)$inc['id'] ?></h1>
    <span class="estado-badge <?= clase_estado($inc['estado']) ?>"><?= esc(nombre_estado($inc['estado'])) ?></span>
  </div>

  <?php if ($mensaje !== ''): ?>
    <div class="mensaje mensaje-<?= esc($tipo_msg) ?>" style="margin-bottom:var(--espacio-xl);">
      <span><?= $tipo_msg === 'exito' ? '✓' : '⚠️' ?></span>
      <span><?= esc($mensaje) ?></span>
    </div>
  <?php endif; ?>

  <div class="tarjeta">
    <dl style="display:grid;grid-template-columns:140px 1fr;gap:var(--espacio-xs) var(--espacio-lg);font-size:.85rem;margin-bottom:var(--espacio-lg);color:var(--color-texto-suave);">
      <dt style="font-weight:600;">Relevado por</dt><dd><?= esc($inc['usuario_nombre']) ?></dd>
      <dt style="font-weight:600;">Fecha del reporte</dt><dd><?= esc(fecha_legible($inc['fecha_hora'])) ?></dd>
      <?php if ($inc['fecha_resolucion']): ?>
        <dt style="font-weight:600;">Fecha de resolución</dt><dd><?= esc(fecha_legible($inc['fecha_resolucion'])) ?></dd>
      <?php endif; ?>
    </dl>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--espacio-md);">
        <div class="campo">
          <label for="e-tipo">Tipo de situación *</label>
          <select id="e-tipo" name="tipo_id" required>
            <?php foreach ($tipos_lista as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= $inc['tipo_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                <?= esc($t['icono']) ?> <?= esc($t['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="e-barrio">Barrio *</label>
          <select id="e-barrio" name="barrio_id" required>
            <?php foreach ($barrios_lista as $b): ?>
              <option value="<?= (int)$b['id'] ?>" <?= $inc['barrio_id'] === (int)$b['id'] ? 'selected' : '' ?>>
                <?= esc($b['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="e-direccion">Dirección / calle y altura *</label>
          <input type="text" id="e-direccion" name="direccion" required maxlength="255" value="<?= esc($inc['direccion'] ?? '') ?>">
        </div>

        <div class="campo">
          <label for="e-gravedad">Gravedad *</label>
          <select id="e-gravedad" name="gravedad" required>
            <option value="baja"    <?= $inc['gravedad'] === 'baja'    ? 'selected' : '' ?>>Baja</option>
            <option value="media"   <?= $inc['gravedad'] === 'media'   ? 'selected' : '' ?>>Media</option>
            <option value="alta"    <?= $inc['gravedad'] === 'alta'    ? 'selected' : '' ?>>Alta</option>
            <option value="critica" <?= $inc['gravedad'] === 'critica' ? 'selected' : '' ?>>Crítica</option>
          </select>
        </div>

        <div class="campo">
          <label for="e-familias">Familias afectadas</label>
          <input type="number" id="e-familias" name="familias_afectadas" min="0" max="9999" value="<?= esc($inc['familias_afectadas'] ?? '') ?>">
        </div>

        <div class="campo">
          <label for="e-servicio">Servicio afectado</label>
          <select id="e-servicio" name="servicio_afectado">
            <option value="">Sin dato</option>
            <?php foreach ($servicios_validos as $s): ?>
              <option value="<?= esc($s) ?>" <?= ($inc['servicio_afectado'] ?? '') === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="e-responsable">Responsable / área</label>
          <select id="e-responsable" name="responsable_area">
            <option value="">Sin dato</option>
            <?php foreach ($responsables_validos as $r): ?>
              <option value="<?= esc($r) ?>" <?= ($inc['responsable_area'] ?? '') === $r ? 'selected' : '' ?>><?= esc($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="e-contacto">Contacto vecino / referente</label>
          <input type="text" id="e-contacto" name="contacto_vecino" maxlength="120" value="<?= esc($inc['contacto_vecino'] ?? '') ?>">
        </div>

        <div class="campo">
          <label for="e-latitud">Latitud *</label>
          <input type="number" id="e-latitud" name="latitud" step="0.000001" min="-90" max="90" required value="<?= esc($inc['latitud']) ?>">
        </div>

        <div class="campo">
          <label for="e-longitud">Longitud *</label>
          <input type="number" id="e-longitud" name="longitud" step="0.000001" min="-180" max="180" required value="<?= esc($inc['longitud']) ?>">
        </div>

        <div class="campo" style="grid-column:1 / -1;">
          <label class="check-linea">
            <input type="checkbox" id="e-calle" name="calle_intransitable" value="1" <?= $inc['calle_intransitable'] ? 'checked' : '' ?>>
            Calle intransitable
          </label>
        </div>

        <div class="campo" style="grid-column:1 / -1;">
          <label for="e-notas">Observaciones</label>
          <textarea id="e-notas" name="notas" maxlength="1000"><?= esc($inc['notas'] ?? '') ?></textarea>
        </div>
      </div>

      <div style="margin-top:var(--espacio-lg);display:flex;gap:var(--espacio-sm);">
        <button type="submit" class="boton boton-primario">Guardar cambios</button>
        <a href="/srci/incidencias.php" class="boton boton-secundario">Cancelar</a>
      </div>
    </form>
  </div>
</main>

</body>
</html>
