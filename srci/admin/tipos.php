<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiere_admin();

$mensaje = '';
$tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validar_csrf();
  $accion = $_POST['accion'] ?? '';

  if ($accion === 'crear') {
    $clave  = trim($_POST['clave']  ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $icono  = trim($_POST['icono']  ?? '');
    if ($clave === '' || $nombre === '') {
      $mensaje  = 'La clave y el nombre son obligatorios.';
      $tipo_msg = 'error';
    } else {
      try {
        $stmt = db()->prepare('INSERT INTO tipos_incidencia (clave, nombre, icono) VALUES (:c, :n, :i)');
        $stmt->execute([':c' => $clave, ':n' => $nombre, ':i' => $icono !== '' ? $icono : null]);
        $mensaje  = "Tipo '{$nombre}' creado.";
        $tipo_msg = 'exito';
      } catch (PDOException $e) {
        $mensaje  = 'La clave ya existe.';
        $tipo_msg = 'error';
      }
    }
  }

  if ($accion === 'toggle') {
    $id = (int)($_POST['tipo_id'] ?? 0);
    if ($id > 0) {
      db()->prepare('UPDATE tipos_incidencia SET activo = 1 - activo WHERE id = :id')->execute([':id' => $id]);
      $mensaje  = 'Estado del tipo actualizado.';
      $tipo_msg = 'exito';
    }
  }
}

$tipos = db()->query('SELECT id, clave, nombre, icono, activo FROM tipos_incidencia ORDER BY id')->fetchAll();
$csrf  = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Tipos · SRCI</title>
  <link rel="stylesheet" href="/srci/assets/css/estilos.css">
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
      <a href="/srci/logout.php" class="boton boton-secundario boton-sm">Salir</a>
    </div>
  </div>
</nav>

<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-titulo">Panel de administración</div>
    <a href="/srci/admin/reportes.php" class="admin-nav-item">📋 Reportes</a>
    <a href="/srci/admin/usuarios.php" class="admin-nav-item">👥 Usuarios</a>
    <a href="/srci/admin/tipos.php"    class="admin-nav-item activo">🏷️ Tipos</a>
  </aside>

  <main class="admin-main">
    <div class="pagina-encabezado"><h1>Tipos de incidencia</h1></div>

    <?php if ($mensaje !== ''): ?>
      <div class="mensaje mensaje-<?= esc($tipo_msg) ?>" style="margin-bottom:var(--espacio-xl);">
        <span><?= $tipo_msg === 'exito' ? '✓' : '⚠️' ?></span>
        <span><?= esc($mensaje) ?></span>
      </div>
    <?php endif; ?>

    <div class="tarjeta" style="margin-bottom:var(--espacio-xl);">
      <h2 style="margin-bottom:var(--espacio-lg);">Agregar tipo</h2>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
        <input type="hidden" name="accion"     value="crear">
        <div style="display:grid;grid-template-columns:1fr 1fr 80px auto;gap:var(--espacio-md);align-items:flex-end;">
          <div class="campo">
            <label for="t-clave">Clave (única, sin espacios)</label>
            <input type="text" id="t-clave" name="clave" required placeholder="ej: bache_calle">
          </div>
          <div class="campo">
            <label for="t-nombre">Nombre para mostrar</label>
            <input type="text" id="t-nombre" name="nombre" required placeholder="ej: Bache en calzada">
          </div>
          <div class="campo">
            <label for="t-icono">Emoji</label>
            <input type="text" id="t-icono" name="icono" maxlength="10" placeholder="🚧">
          </div>
          <button type="submit" class="boton boton-primario" style="align-self:flex-end;">Crear</button>
        </div>
      </form>
    </div>

    <div class="tabla-contenedor">
      <table class="tabla-incidencias">
        <thead>
          <tr><th>#</th><th>Clave</th><th>Nombre</th><th>Icono</th><th>Estado</th><th>Acción</th></tr>
        </thead>
        <tbody>
          <?php foreach ($tipos as $t): ?>
            <tr>
              <td><?= (int)$t['id'] ?></td>
              <td><code style="font-size:.85rem;"><?= esc($t['clave']) ?></code></td>
              <td><?= esc($t['nombre']) ?></td>
              <td style="font-size:1.5rem;"><?= esc($t['icono'] ?? '—') ?></td>
              <td>
                <span class="estado-badge" style="<?= $t['activo'] ? 'background:rgba(34,197,94,.15);color:var(--color-verde)' : 'background:rgba(239,68,68,.15);color:var(--color-rojo)' ?>">
                  <?= $t['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                  <input type="hidden" name="accion"     value="toggle">
                  <input type="hidden" name="tipo_id"    value="<?= (int)$t['id'] ?>">
                  <button type="submit" class="boton <?= $t['activo'] ? 'boton-peligro' : 'boton-exito' ?> boton-sm">
                    <?= $t['activo'] ? 'Desactivar' : 'Activar' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
