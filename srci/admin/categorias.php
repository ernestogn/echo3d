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
    $color  = color_valido($_POST['color'] ?? '') ?? '#64748b';
    $visible = (int)(($_POST['visible_usuario'] ?? 0) === '1');
    if ($clave === '' || $nombre === '') {
      $mensaje  = 'La clave y el nombre son obligatorios.';
      $tipo_msg = 'error';
    } else {
      try {
        $stmt = db()->prepare('INSERT INTO categorias_incidencia (clave, nombre, icono, color, visible_usuario) VALUES (:c, :n, :i, :col, :v)');
        $stmt->execute([':c' => $clave, ':n' => $nombre, ':i' => $icono !== '' ? $icono : null, ':col' => $color, ':v' => $visible]);
        $mensaje  = "Categoría '{$nombre}' creada.";
        $tipo_msg = 'exito';
      } catch (PDOException $e) {
        $mensaje  = 'La clave ya existe.';
        $tipo_msg = 'error';
      }
    }
  }

  if ($accion === 'editar') {
    $id     = (int)($_POST['categoria_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $icono  = trim($_POST['icono']  ?? '');
    $color  = color_valido($_POST['color'] ?? '');
    $visible = (int)(($_POST['visible_usuario'] ?? 0) === '1');

    if ($id <= 0) {
      $mensaje  = 'Categoría invalida.';
      $tipo_msg = 'error';
    } elseif ($nombre === '') {
      $mensaje  = 'El nombre es obligatorio.';
      $tipo_msg = 'error';
    } else {
      $st = db()->prepare('SELECT nombre, icono, color, visible_usuario FROM categorias_incidencia WHERE id = :id');
      $st->execute([':id' => $id]);
      $c = $st->fetch();

      if (!$c) {
        $mensaje  = 'La categoría no existe.';
        $tipo_msg = 'error';
      } else {
        $icono_nuevo = $icono !== '' ? $icono : null;
        $cambios = [];
        if ($nombre !== $c['nombre']) {
          $cambios[] = "nombre: '{$c['nombre']}' -> '{$nombre}'";
        }
        if ($icono_nuevo !== $c['icono']) {
          $cambios[] = 'icono: ' . ($c['icono'] ?? '(ninguno)') . ' -> ' . ($icono_nuevo ?? '(ninguno)');
        }
        if ($color !== $c['color']) {
          $cambios[] = 'color: ' . ($c['color'] ?? '(auto)') . ' -> ' . ($color ?? '(auto)');
        }
        if ($visible !== (int)$c['visible_usuario']) {
          $cambios[] = $visible ? 'visible para usuarios' : 'oculta para usuarios';
        }

        if (!$cambios) {
          $mensaje  = 'Sin cambios: los datos ya eran esos.';
          $tipo_msg = 'info';
        } else {
          db()->prepare('UPDATE categorias_incidencia SET nombre = :n, icono = :i, color = :col, visible_usuario = :v WHERE id = :id')
             ->execute([':n' => $nombre, ':i' => $icono_nuevo, ':col' => $color, ':v' => $visible, ':id' => $id]);
          $mensaje  = "Categoría '{$nombre}' actualizada.";
          $tipo_msg = 'exito';
          registrar_auditoria(
            (int)$_SESSION['usuario_id'],
            'editar',
            null,
            'Categoría #' . $id . ' (' . $c['nombre'] . '): ' . implode('; ', $cambios)
          );
        }
      }
    }
  }

  if ($accion === 'toggle') {
    $id = (int)($_POST['categoria_id'] ?? 0);
    if ($id > 0) {
      db()->prepare('UPDATE categorias_incidencia SET activo = 1 - activo WHERE id = :id')->execute([':id' => $id]);
      $mensaje  = 'Estado de la categoría actualizado.';
      $tipo_msg = 'exito';
    }
  }

  if ($accion === 'toggle_visibilidad') {
    $id = (int)($_POST['categoria_id'] ?? 0);
    $st = db()->prepare('SELECT visible_usuario FROM categorias_incidencia WHERE id = :id');
    $st->execute([':id' => $id]);
    $visible = (int)$st->fetchColumn();
    if ($visible === 0 || $visible === 1) {
      db()->prepare('UPDATE categorias_incidencia SET visible_usuario = 1 - visible_usuario WHERE id = :id')->execute([':id' => $id]);
      $mensaje  = $visible ? 'Categoría oculta para usuarios.' : 'Categoría visible para usuarios.';
      $tipo_msg = 'exito';
    }
  }
}

$categorias = db()->query('SELECT id, clave, nombre, icono, color, activo, visible_usuario FROM categorias_incidencia ORDER BY id')->fetchAll();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Categorías · SRCI</title>
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261011k">
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
    <a href="/srci/admin/reportes.php"   class="admin-nav-item">📋 Reportes</a>
    <a href="/srci/admin/usuarios.php"   class="admin-nav-item">👥 Usuarios</a>
    <a href="/srci/admin/tipos.php"      class="admin-nav-item">🏷️ Tipos</a>
    <a href="/srci/admin/categorias.php" class="admin-nav-item activo">🗂️ Categorías</a>
    <a href="/srci/admin/barrios.php"    class="admin-nav-item">🏙️ Barrios</a>
    <a href="/srci/admin/auditoria.php"  class="admin-nav-item">🧾 Auditoría</a>
  </aside>

  <main class="admin-main">
    <div class="pagina-encabezado"><h1>Categorías de incidencia</h1></div>

    <?php if ($mensaje !== ''): ?>
      <div class="mensaje mensaje-<?= esc($tipo_msg) ?>" style="margin-bottom:var(--espacio-xl);">
        <span><?= $tipo_msg === 'exito' ? '✓' : '⚠️' ?></span>
        <span><?= esc($mensaje) ?></span>
      </div>
    <?php endif; ?>

    <div class="tarjeta" style="margin-bottom:var(--espacio-md);">
      <p style="color:var(--color-texto-suave);line-height:1.7;margin:0;">
        Las categorías son <strong>etiquetas independientes del tipo</strong> de incidencia y
        <strong>no excluyentes</strong>: un mismo reporte puede tener varias a la vez (ej: Basura + Agua y cloaca).
        La categoría por defecto es <strong>Climáticos</strong>. Por ahora el selector de categorías
        <strong>solo lo usan los administradores</strong>; si querés que una categoría la elija también el vecino
        al reportar, marcala como <em>visible para usuarios</em> (además tiene que estar Activa).
      </p>
    </div>

    <div class="tarjeta" style="margin-bottom:var(--espacio-xl);">
      <h2 style="margin-bottom:var(--espacio-lg);">Agregar categoría</h2>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
        <input type="hidden" name="accion"     value="crear">
        <div class="grilla-form-4" style="grid-template-columns:1fr 1fr 120px 90px 150px auto;">
          <div class="campo">
            <label for="c-clave">Clave (única, sin espacios)</label>
            <input type="text" id="c-clave" name="clave" required placeholder="ej: alumbrado">
          </div>
          <div class="campo">
            <label for="c-nombre">Nombre para mostrar</label>
            <input type="text" id="c-nombre" name="nombre" required placeholder="ej: Alumbrado">
          </div>
          <div class="campo">
            <label for="c-icono">Emoji (tocá para elegir)</label>
            <input type="text" id="c-icono" name="icono" maxlength="10" placeholder="🗂️" onclick="abrirSelectorIconos(this)" style="cursor:pointer;font-size:1.3rem;text-align:center;">
          </div>
          <div class="campo">
            <label for="c-color">Color</label>
            <input type="color" id="c-color" name="color" value="#64748b" style="width:100%;height:40px;padding:2px;cursor:pointer;">
          </div>
          <div class="campo" style="align-self:flex-end;padding-bottom:8px;">
            <label class="check-linea">
              <input type="checkbox" id="c-visible" name="visible_usuario" value="1">
              Visible para usuarios
            </label>
          </div>
          <button type="submit" class="boton boton-primario" style="align-self:flex-end;">Crear</button>
        </div>
      </form>
    </div>

    <div class="tabla-contenedor">
      <table class="tabla-incidencias">
        <thead>
          <tr><th>#</th><th>Clave</th><th>Nombre</th><th>Icono</th><th>Color</th><th>Estado</th><th>Usuarios</th><th>Acción</th></tr>
        </thead>
        <tbody>
          <?php foreach ($categorias as $c): ?>
            <tr>
              <td><?= (int)$c['id'] ?></td>
              <td><code style="font-size:.85rem;"><?= esc($c['clave']) ?></code></td>
              <td><?= esc($c['nombre']) ?></td>
              <td style="font-size:1.5rem;"><?= esc($c['icono'] ?? '—') ?></td>
              <td>
                <?php $col = color_valido($c['color']) ?? '#64748b'; ?>
                <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:<?= esc($col) ?>;vertical-align:middle;border:1px solid var(--color-borde);margin-right:6px;"></span>
                <code style="font-size:.8rem;"><?= esc($col) ?></code>
              </td>
              <td>
                <span class="estado-badge" style="<?= $c['activo'] ? 'background:rgba(34,197,94,.15);color:var(--color-verde)' : 'background:rgba(239,68,68,.15);color:var(--color-rojo)' ?>">
                  <?= $c['activo'] ? 'Activa' : 'Inactiva' ?>
                </span>
              </td>
              <td>
                <span class="estado-badge" style="<?= $c['visible_usuario'] ? 'background:rgba(79,70,229,.12);color:var(--color-acento)' : 'background:rgba(100,116,139,.12);color:var(--color-texto-suave)' ?>">
                  <?= $c['visible_usuario'] ? '👁 Visible' : 'Oculta' ?>
                </span>
              </td>
              <td>
                <div style="display:flex;gap:var(--espacio-sm);flex-wrap:wrap;">
                  <button type="button" class="boton boton-secundario boton-sm" onclick="abrirModalEditar(this)"
                          data-id="<?= (int)$c['id'] ?>"
                          data-nombre="<?= esc($c['nombre']) ?>"
                          data-icono="<?= esc($c['icono'] ?? '') ?>"
                          data-color="<?= esc(color_valido($c['color']) ?? '#64748b') ?>"
                          data-visible="<?= (int)$c['visible_usuario'] ?>">
                    ✏️ Editar
                  </button>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                    <input type="hidden" name="accion"     value="toggle_visibilidad">
                    <input type="hidden" name="categoria_id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="boton <?= $c['visible_usuario'] ? 'boton-secundario' : 'boton-exito' ?> boton-sm">
                      <?= $c['visible_usuario'] ? '🙈 Ocultar a usuarios' : '👁 Mostrar a usuarios' ?>
                    </button>
                  </form>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                    <input type="hidden" name="accion"     value="toggle">
                    <input type="hidden" name="categoria_id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="boton <?= $c['activo'] ? 'boton-peligro' : 'boton-exito' ?> boton-sm">
                      <?= $c['activo'] ? 'Desactivar' : 'Activar' ?>
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

<!-- Modal editar categoría -->
<div class="modal-fondo modal-centrado" id="modal-editar" role="dialog" aria-modal="true" aria-labelledby="modal-editar-titulo">
  <div class="modal-caja">
    <div class="modal-encabezado">
      <h2 id="modal-editar-titulo">Editar categoría</h2>
      <button class="modal-cerrar" type="button" aria-label="Cerrar" onclick="cerrarModalEditar()">×</button>
    </div>
    <form method="POST" action="/srci/admin/categorias.php" id="form-editar">
      <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
      <input type="hidden" name="accion"     value="editar">
      <input type="hidden" name="categoria_id" id="editar-id" value="">
      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="editar-nombre">Nombre para mostrar</label>
        <input type="text" id="editar-nombre" name="nombre" required>
      </div>
      <div class="grilla-form-2" style="margin-bottom:var(--espacio-lg);">
        <div class="campo">
          <label for="editar-icono">Icono (emoji)</label>
          <input type="text" id="editar-icono" name="icono" maxlength="10" placeholder="🗂️" onclick="abrirSelectorIconos(this)" style="cursor:pointer;font-size:1.3rem;text-align:center;">
        </div>
        <div class="campo">
          <label for="editar-color">Color</label>
          <input type="color" id="editar-color" name="color" style="width:100%;height:40px;padding:2px;cursor:pointer;">
        </div>
      </div>
      <label class="check-linea" style="margin-bottom:var(--espacio-lg);">
        <input type="checkbox" id="editar-visible" name="visible_usuario" value="1">
        Visible para usuarios (la eligen también los vecinos al reportar)
      </label>
      <button type="submit" class="boton boton-primario boton-bloque">Guardar cambios</button>
    </form>
  </div>
</div>

<!-- Modal selector de icono -->
<div class="modal-fondo modal-centrado" id="modal-iconos" role="dialog" aria-modal="true" aria-label="Elegir icono" style="z-index:1100;">
  <div class="modal-caja">
    <div class="modal-encabezado">
      <h2>Elegir icono</h2>
      <button class="modal-cerrar" type="button" aria-label="Cerrar" onclick="cerrarSelectorIconos()">×</button>
    </div>
    <div id="grilla-iconos" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(52px,1fr));gap:8px;"></div>
  </div>
</div>

<script>
const ICONOS_CATEG = ['🌧️','🛣️','💡','🌳','🗑️','💧','🚧','🚦','🔥','🐕','🦟','🐀','🏗️','🚰','🌿','🌊','⚡','🏚️','🏠','🕳️','📋','🏙️','🚌','🚏','⛲','🪑','🧱','🪧','⚠️','🛟','🔧','🌡️','❄️','🏭'];

let inputIconoActivo = null;
function abrirSelectorIconos(input) {
  inputIconoActivo = input;
  input.blur();
  document.getElementById('modal-iconos').classList.add('visible');
}
function cerrarSelectorIconos() {
  inputIconoActivo = null;
  document.getElementById('modal-iconos').classList.remove('visible');
}

const grillaIconos = document.getElementById('grilla-iconos');
ICONOS_CATEG.forEach(function (ico) {
  const b = document.createElement('button');
  b.type = 'button';
  b.textContent = ico;
  b.title = ico;
  b.style.cssText = 'width:52px;height:52px;font-size:1.6rem;border:1px solid var(--color-borde);border-radius:var(--radio-md);background:var(--color-fondo);cursor:pointer;line-height:1;';
  b.addEventListener('click', function () {
    if (inputIconoActivo) inputIconoActivo.value = ico;
    cerrarSelectorIconos();
  });
  grillaIconos.appendChild(b);
});
document.getElementById('modal-iconos').addEventListener('click', function (e) {
  if (e.target === this) cerrarSelectorIconos();
});

function abrirModalEditar(btn) {
  document.getElementById('editar-id').value     = btn.dataset.id;
  document.getElementById('editar-nombre').value = btn.dataset.nombre;
  document.getElementById('editar-icono').value  = btn.dataset.icono;
  document.getElementById('editar-color').value  = btn.dataset.color || '#64748b';
  document.getElementById('editar-visible').checked = btn.dataset.visible === '1';
  document.getElementById('modal-editar').classList.add('visible');
}
function cerrarModalEditar() {
  document.getElementById('modal-editar').classList.remove('visible');
}
document.getElementById('modal-editar').addEventListener('click', function (e) {
  if (e.target === this) cerrarModalEditar();
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    cerrarModalEditar();
    cerrarSelectorIconos();
  }
});
</script>
</body>
</html>
