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

$barrios = db()->query(
  'SELECT b.id, b.nombre, b.activo, b.creado_en, b.categoria, b.color,
          (b.poligono IS NOT NULL AND b.poligono <> "") AS tiene_pol,
          (SELECT COUNT(*) FROM incidencias i WHERE i.barrio_id = b.id AND i.oculto = 0) AS n_inc,
          (SELECT COUNT(*) FROM referentes_barrios rb WHERE rb.barrio_id = b.id) AS n_ref
   FROM barrios b ORDER BY b.nombre'
)->fetchAll();
$csrf    = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Barrios · SRCI</title>
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261015">
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
    <a href="/srci/admin/categorias.php" class="admin-nav-item">🗂️ Categorías</a>
    <a href="/srci/admin/barrios.php"  class="admin-nav-item activo">🏙️ Barrios</a>
    <a href="/srci/admin/auditoria.php" class="admin-nav-item">🧾 Auditoría</a>
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

    <!-- Buscador -->
    <div style="display:flex;gap:var(--espacio-md);align-items:center;margin-bottom:var(--espacio-md);flex-wrap:wrap;">
      <input type="search" id="buscador-barrios" autocomplete="off"
             placeholder="Buscar barrio por nombre…  (tecla /)"
             style="flex:1;min-width:260px;padding:10px 14px;border:1px solid var(--color-borde);border-radius:var(--radio-md);font-size:.95rem;background:var(--color-superficie);color:var(--color-texto);">
      <span id="contador-barrios" style="color:var(--color-texto-suave);font-size:.85rem;"></span>
    </div>

    <div class="tabla-contenedor">
      <table class="tabla-incidencias">
        <thead>
          <tr><th>#</th><th>Nombre</th><th>Categoría</th><th>Polígono</th><th>Incidencias</th><th>Referentes</th><th>Creado</th><th>Estado</th><th>Acción</th></tr>
        </thead>
        <tbody>
          <?php foreach ($barrios as $b): ?>
            <tr>
              <td><?= (int)$b['id'] ?></td>
              <td><?= esc($b['nombre']) ?></td>
              <td><span class="estado-badge <?= esc(clase_categoria($b['categoria'])) ?>"><?= esc(nombre_categoria($b['categoria'])) ?></span></td>
              <td style="text-align:center;">
                <?php if ($b['tiene_pol']): ?>
                  <span style="display:inline-block;width:13px;height:13px;border-radius:3px;background:<?= esc(color_valido($b['color']) ?? color_categoria($b['categoria'])) ?>;vertical-align:middle;border:1px solid var(--color-borde);margin-right:5px;"></span>✔
                <?php else: ?>
                  <span style="color:var(--color-texto-suave);">—</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <?php if ((int)$b['n_inc'] > 0): ?>
                  <a href="/srci/incidencias.php?barrio_id=<?= (int)$b['id'] ?>"><?= (int)$b['n_inc'] ?></a>
                <?php else: ?>
                  <span style="color:var(--color-texto-suave);">0</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;"><?= (int)$b['n_ref'] ?></td>
              <td><?= esc(fecha_legible($b['creado_en'])) ?></td>
              <td>
                <span class="estado-badge" style="<?= $b['activo'] ? 'background:rgba(34,197,94,.15);color:var(--color-verde)' : 'background:rgba(239,68,68,.15);color:var(--color-rojo)' ?>">
                  <?= $b['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
              </td>
              <td>
                <div style="display:flex;gap:var(--espacio-sm);flex-wrap:wrap;">
                  <a href="/srci/admin/barrio.php?id=<?= (int)$b['id'] ?>" class="boton boton-primario boton-sm">✏️ Editar</a>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                    <input type="hidden" name="accion"     value="toggle">
                    <input type="hidden" name="barrio_id"  value="<?= (int)$b['id'] ?>">
                    <button type="submit" class="boton <?= $b['activo'] ? 'boton-peligro' : 'boton-exito' ?> boton-sm">
                      <?= $b['activo'] ? 'Desactivar' : 'Activar' ?>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script>
// Buscador: filtra barrios por nombre (o cualquier texto de la fila)
const buscador = document.getElementById('buscador-barrios');
const filas    = Array.from(document.querySelectorAll('.tabla-incidencias tbody tr'));
const contador = document.getElementById('contador-barrios');
function filtrarBarrios() {
  const q = buscador.value.trim().toLowerCase();
  let visibles = 0;
  filas.forEach(function (f) {
    const ok = f.textContent.toLowerCase().includes(q);
    f.style.display = ok ? '' : 'none';
    if (ok) visibles++;
  });
  contador.textContent = q ? (visibles + ' de ' + filas.length) : '';
}
buscador.addEventListener('input', filtrarBarrios);
// Tecla / enfoca el buscador (si no se esta escribiendo en otro campo)
document.addEventListener('keydown', function (e) {
  const tag = (document.activeElement && document.activeElement.tagName) || '';
  if (e.key === '/' && !['INPUT', 'SELECT', 'TEXTAREA'].includes(tag)) {
    e.preventDefault();
    buscador.focus();
  }
});
</script>
</body>
</html>
