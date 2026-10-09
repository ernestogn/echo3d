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
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre === '') {
      $mensaje  = 'El nombre del barrio es obligatorio.';
      $tipo_msg = 'error';
    } else {
      try {
        $stmt = db()->prepare('INSERT INTO barrios (nombre) VALUES (:n)');
        $stmt->execute([':n' => $nombre]);
        $mensaje  = "Barrio '{$nombre}' creado.";
        $tipo_msg = 'exito';
      } catch (PDOException $e) {
        $mensaje  = 'Ya existe un barrio con ese nombre.';
        $tipo_msg = 'error';
      }
    }
  }

  if ($accion === 'toggle') {
    $id = (int)($_POST['barrio_id'] ?? 0);
    if ($id > 0) {
      db()->prepare('UPDATE barrios SET activo = 1 - activo WHERE id = :id')->execute([':id' => $id]);
      $mensaje  = 'Estado del barrio actualizado.';
      $tipo_msg = 'exito';
    }
  }
}

$barrios = db()->query('SELECT id, nombre, activo, creado_en FROM barrios ORDER BY nombre')->fetchAll();
$csrf    = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Barrios · SRCI</title>
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
    <a href="/srci/admin/tipos.php"    class="admin-nav-item">🏷️ Tipos</a>
    <a href="/srci/admin/barrios.php"  class="admin-nav-item activo">🏙️ Barrios</a>
  </aside>

  <main class="admin-main">
    <div class="pagina-encabezado"><h1>Barrios</h1></div>

    <?php if ($mensaje !== ''): ?>
      <div class="mensaje mensaje-<?= esc($tipo_msg) ?>" style="margin-bottom:var(--espacio-xl);">
        <span><?= $tipo_msg === 'exito' ? '✓' : '⚠️' ?></span>
        <span><?= esc($mensaje) ?></span>
      </div>
    <?php endif; ?>

    <div class="tarjeta" style="margin-bottom:var(--espacio-xl);">
      <h2 style="margin-bottom:var(--espacio-lg);">Agregar barrio</h2>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
        <input type="hidden" name="accion"     value="crear">
        <div style="display:grid;grid-template-columns:1fr auto;gap:var(--espacio-md);align-items:flex-end;">
          <div class="campo">
            <label for="b-nombre">Nombre del barrio (también informales y asentamientos)</label>
            <input type="text" id="b-nombre" name="nombre" required maxlength="100" placeholder="ej: Barrio Los Álamos">
          </div>
          <button type="submit" class="boton boton-primario" style="align-self:flex-end;">Crear</button>
        </div>
      </form>
    </div>

    <div class="tabla-contenedor">
      <table class="tabla-incidencias">
        <thead>
          <tr><th>#</th><th>Nombre</th><th>Creado</th><th>Estado</th><th>Acción</th></tr>
        </thead>
        <tbody>
          <?php foreach ($barrios as $b): ?>
            <tr>
              <td><?= (int)$b['id'] ?></td>
              <td><?= esc($b['nombre']) ?></td>
              <td><?= esc(fecha_legible($b['creado_en'])) ?></td>
              <td>
                <span class="estado-badge" style="<?= $b['activo'] ? 'background:rgba(34,197,94,.15);color:var(--color-verde)' : 'background:rgba(239,68,68,.15);color:var(--color-rojo)' ?>">
                  <?= $b['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                  <input type="hidden" name="accion"     value="toggle">
                  <input type="hidden" name="barrio_id"  value="<?= (int)$b['id'] ?>">
                  <button type="submit" class="boton <?= $b['activo'] ? 'boton-peligro' : 'boton-exito' ?> boton-sm">
                    <?= $b['activo'] ? 'Desactivar' : 'Activar' ?>
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
