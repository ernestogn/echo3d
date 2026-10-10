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

  // Crear usuario
  if ($accion === 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email  = trim($_POST['email']  ?? '');
    $rol    = $_POST['rol'] ?? 'usuario';
    $roles  = ['usuario','admin'];

    if ($nombre === '') {
      $mensaje = 'El nombre es obligatorio.';
      $tipo_msg = 'error';
    } elseif (!in_array($rol, $roles, true)) {
      $mensaje = 'Rol invalido.';
      $tipo_msg = 'error';
    } else {
      $pin     = generar_pin();
      $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
      try {
        $stmt = db()->prepare('INSERT INTO usuarios (nombre, email, pin_hash, rol) VALUES (:n, :e, :h, :r)');
        $stmt->execute([':n' => $nombre, ':e' => $email !== '' ? $email : null, ':h' => $pin_hash, ':r' => $rol]);
        if ($email !== '') {
          enviar_pin_por_email($email, $nombre, $pin);
          $mensaje = "Usuario '{$nombre}' creado. PIN enviado por email: {$pin}";
        } else {
          $mensaje = "Usuario '{$nombre}' creado. PIN: {$pin} (guardalo, no se puede recuperar)";
        }
        $tipo_msg = 'exito';
      } catch (PDOException $e) {
        $mensaje  = 'El nombre de usuario ya existe.';
        $tipo_msg = 'error';
      }
    }
  }

  // Editar usuario (nombre, email, rol)
  if ($accion === 'editar') {
    $uid    = (int)($_POST['usuario_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $email  = trim($_POST['email']  ?? '');
    $rol    = $_POST['rol'] ?? 'usuario';
    $roles  = ['usuario','admin'];

    if ($nombre === '') {
      $mensaje  = 'El nombre es obligatorio.';
      $tipo_msg = 'error';
    } elseif (!in_array($rol, $roles, true)) {
      $mensaje  = 'Rol invalido.';
      $tipo_msg = 'error';
    } elseif ($uid <= 0) {
      $mensaje  = 'Usuario invalido.';
      $tipo_msg = 'error';
    } else {
      $st = db()->prepare('SELECT id, nombre, email, rol FROM usuarios WHERE id = :id');
      $st->execute([':id' => $uid]);
      $u = $st->fetch();

      if (!$u) {
        $mensaje  = 'El usuario no existe.';
        $tipo_msg = 'error';
      } elseif ((int)$u['id'] === (int)$_SESSION['usuario_id'] && $rol !== $u['rol']) {
        // Evita que un admin se degrade a si mismo y pierda el panel
        $mensaje  = 'No podes cambiar tu propio rol.';
        $tipo_msg = 'error';
      } else {
        $email_nuevo = $email !== '' ? $email : null;
        $cambios = [];
        if ($nombre !== $u['nombre']) {
          $cambios[] = "nombre: '{$u['nombre']}' -> '{$nombre}'";
        }
        if ($email_nuevo !== $u['email']) {
          $cambios[] = 'email: ' . ($u['email'] ?? '(vacio)') . ' -> ' . ($email_nuevo ?? '(vacio)');
        }
        if ($rol !== $u['rol']) {
          $cambios[] = "rol: {$u['rol']} -> {$rol}";
        }

        if (!$cambios) {
          $mensaje  = 'Sin cambios: los datos ya eran esos.';
          $tipo_msg = 'info';
        } else {
          try {
            $stmt = db()->prepare('UPDATE usuarios SET nombre = :n, email = :e, rol = :r WHERE id = :id');
            $stmt->execute([':n' => $nombre, ':e' => $email_nuevo, ':r' => $rol, ':id' => $uid]);
            $mensaje  = "Usuario '{$nombre}' actualizado.";
            $tipo_msg = 'exito';
            registrar_auditoria(
              (int)$_SESSION['usuario_id'],
              'editar',
              null,
              'Edito a ' . $u['nombre'] . ' (#' . $uid . '): ' . implode('; ', $cambios)
            );
          } catch (PDOException $e) {
            $mensaje  = 'Ese nombre de usuario ya existe.';
            $tipo_msg = 'error';
          }
        }
      }
    }
  }

  // Regenerar PIN
  if ($accion === 'nuevo_pin') {
    $uid = (int)($_POST['usuario_id'] ?? 0);
    if ($uid > 0) {
      $pin      = generar_pin();
      $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
      $stmt = db()->prepare('UPDATE usuarios SET pin_hash = :h WHERE id = :id');
      $stmt->execute([':h' => $pin_hash, ':id' => $uid]);
      // Obtener email para enviar
      $st2 = db()->prepare('SELECT nombre, email FROM usuarios WHERE id = :id');
      $st2->execute([':id' => $uid]);
      $u = $st2->fetch();
      if ($u['email']) {
        enviar_pin_por_email($u['email'], $u['nombre'], $pin);
        $mensaje = "PIN regenerado y enviado por email a {$u['nombre']}.";
      } else {
        $mensaje = "PIN nuevo de {$u['nombre']}: {$pin} (guardalo).";
      }
      $tipo_msg = 'exito';
    }
  }

  // Activar / Desactivar
  if ($accion === 'toggle_activo') {
    $uid = (int)($_POST['usuario_id'] ?? 0);
    if ($uid > 0 && $uid !== $_SESSION['usuario_id']) {
      $st = db()->prepare('SELECT nombre, email, activo FROM usuarios WHERE id = :id');
      $st->execute([':id' => $uid]);
      $u = $st->fetch();

      db()->prepare('UPDATE usuarios SET activo = 1 - activo WHERE id = :id')->execute([':id' => $uid]);

      // Al activar: regenerar y enviar PIN por email (el PIN viejo no es recuperable)
      if ($u && (int)$u['activo'] === 0) {
        if (!empty($u['email'])) {
          $pin      = generar_pin();
          $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
          db()->prepare('UPDATE usuarios SET pin_hash = :h WHERE id = :id')->execute([':h' => $pin_hash, ':id' => $uid]);
          $enviado  = enviar_pin_por_email($u['email'], $u['nombre'], $pin);
          $mensaje  = $enviado
            ? "Usuario activado. PIN nuevo enviado por email a {$u['email']}."
            : "Usuario activado, pero el email fallo. Usa 'Nuevo PIN' para reenviar.";
          $tipo_msg = $enviado ? 'exito' : 'error';
          registrar_auditoria(
            (int)$_SESSION['usuario_id'],
            'activar',
            null,
            "Activo a {$u['nombre']} (<{$u['email']}>) y envio PIN" . ($enviado ? '' : ' — EMAIL FALLO')
          );
        } else {
          $mensaje  = 'Usuario activado. No tiene email cargado: cargalo y usa Nuevo PIN.';
          $tipo_msg = 'error';
        }
      } else {
        $mensaje  = 'Estado del usuario actualizado.';
        $tipo_msg = 'exito';
        registrar_auditoria(
          (int)$_SESSION['usuario_id'],
          'desactivar',
          null,
          'Desactivo a ' . ($u['nombre'] ?? ('#' . $uid))
        );
      }
    }
  }
}

$usuarios = db()->query('SELECT id, nombre, email, rol, activo, creado_en FROM usuarios ORDER BY id')->fetchAll();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Usuarios · SRCI</title>
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261010b">
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
    <a href="/srci/admin/usuarios.php" class="admin-nav-item activo">👥 Usuarios</a>
    <a href="/srci/admin/tipos.php"    class="admin-nav-item">🏷️ Tipos</a>
    <a href="/srci/admin/barrios.php"  class="admin-nav-item">🏙️ Barrios</a>
    <a href="/srci/admin/auditoria.php" class="admin-nav-item">🧾 Auditoría</a>
  </aside>

  <main class="admin-main">
    <div class="pagina-encabezado">
      <h1>Gestión de usuarios</h1>
    </div>

    <?php if ($mensaje !== ''): ?>
      <div class="mensaje mensaje-<?= esc($tipo_msg) ?>" style="margin-bottom:var(--espacio-xl);" role="alert">
        <span><?= $tipo_msg === 'exito' ? '✓' : ($tipo_msg === 'info' ? 'ℹ️' : '⚠️') ?></span>
        <span><?= esc($mensaje) ?></span>
      </div>
    <?php endif; ?>

    <!-- Crear usuario -->
    <div class="tarjeta" style="margin-bottom:var(--espacio-xl);">
      <h2 style="margin-bottom:var(--espacio-lg);">Agregar usuario</h2>
      <form method="POST" action="/srci/admin/usuarios.php">
        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
        <input type="hidden" name="accion"     value="crear">
        <div style="display:grid;grid-template-columns:1fr 1fr 140px auto;gap:var(--espacio-md);align-items:flex-end;flex-wrap:wrap;">
          <div class="campo">
            <label for="nuevo-nombre">Nombre</label>
            <input type="text" id="nuevo-nombre" name="nombre" required placeholder="ej: Juan Pérez">
          </div>
          <div class="campo">
            <label for="nuevo-email">Email (para enviar PIN)</label>
            <input type="email" id="nuevo-email" name="email" placeholder="opcional">
          </div>
          <div class="campo">
            <label for="nuevo-rol">Rol</label>
            <select id="nuevo-rol" name="rol">
              <option value="usuario">Usuario</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <button type="submit" class="boton boton-primario" style="align-self:flex-end;">Crear</button>
        </div>
      </form>
    </div>

    <!-- Tabla de usuarios -->
    <div class="tabla-contenedor">
      <table class="tabla-incidencias">
        <thead>
          <tr><th>#</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Creado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td><strong><?= esc($u['nombre']) ?></strong></td>
              <td><?= esc($u['email'] ?? '—') ?></td>
              <td><span style="text-transform:capitalize;"><?= esc($u['rol']) ?></span></td>
              <td>
                <span class="estado-badge" style="<?= $u['activo'] ? 'background:rgba(34,197,94,.15);color:var(--color-verde)' : 'background:rgba(239,68,68,.15);color:var(--color-rojo)' ?>">
                  <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
              </td>
              <td><?= esc(fecha_legible($u['creado_en'])) ?></td>
              <td>
                <div style="display:flex;gap:var(--espacio-sm);flex-wrap:wrap;">
                  <!-- Editar usuario -->
                  <button type="button" class="boton boton-secundario boton-sm" onclick="abrirModalEditar(this)"
                          data-id="<?= (int)$u['id'] ?>"
                          data-nombre="<?= esc($u['nombre']) ?>"
                          data-email="<?= esc($u['email'] ?? '') ?>"
                          data-rol="<?= esc($u['rol']) ?>"
                          data-self="<?= (int)$u['id'] === (int)$_SESSION['usuario_id'] ? '1' : '0' ?>">
                    ✏️ Editar
                  </button>
                  <!-- Regenerar PIN -->
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token"  value="<?= esc($csrf) ?>">
                    <input type="hidden" name="accion"      value="nuevo_pin">
                    <input type="hidden" name="usuario_id"  value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="boton boton-secundario boton-sm"
                            onclick="return confirm('¿Generar nuevo PIN para <?= esc(addslashes($u['nombre'])) ?>?')">
                      🔑 Nuevo PIN
                    </button>
                  </form>
                  <!-- Activar/Desactivar (no afecta al admin en sesion) -->
                  <?php if ((int)$u['id'] !== (int)$_SESSION['usuario_id']): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $u['activo'] ? '¿Desactivar este usuario? No podrá ingresar hasta reactivarlo.' : '¿Activar este usuario? Se generará un PIN nuevo y se enviará por email.' ?>');">
                      <input type="hidden" name="csrf_token"  value="<?= esc($csrf) ?>">
                      <input type="hidden" name="accion"      value="toggle_activo">
                      <input type="hidden" name="usuario_id"  value="<?= (int)$u['id'] ?>">
                      <button type="submit" class="boton <?= $u['activo'] ? 'boton-peligro' : 'boton-exito' ?> boton-sm">
                        <?= $u['activo'] ? 'Desactivar' : 'Activar + PIN' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- Modal editar usuario -->
<div class="modal-fondo" id="modal-editar" role="dialog" aria-modal="true" aria-labelledby="modal-editar-titulo">
  <div class="modal-caja">
    <div class="modal-encabezado">
      <h2 id="modal-editar-titulo">Editar usuario</h2>
      <button class="modal-cerrar" type="button" aria-label="Cerrar" onclick="cerrarModalEditar()">×</button>
    </div>
    <form method="POST" action="/srci/admin/usuarios.php" id="form-editar">
      <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
      <input type="hidden" name="accion"     value="editar">
      <input type="hidden" name="usuario_id" id="editar-id" value="">
      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="editar-nombre">Nombre (es el usuario de login)</label>
        <input type="text" id="editar-nombre" name="nombre" required>
      </div>
      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="editar-email">Email (para enviar PIN)</label>
        <input type="email" id="editar-email" name="email" placeholder="opcional">
      </div>
      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="editar-rol">Rol</label>
        <select id="editar-rol" name="rol">
          <option value="usuario">Usuario</option>
          <option value="admin">Admin</option>
        </select>
        <p id="editar-rol-nota" style="display:none;color:var(--color-texto-suave);font-size:.8rem;margin:var(--espacio-xs) 0 0;">
          No podés cambiar tu propio rol.
        </p>
      </div>
      <button type="submit" class="boton boton-primario boton-bloque">Guardar cambios</button>
    </form>
  </div>
</div>

<script>
function abrirModalEditar(btn) {
  document.getElementById('editar-id').value     = btn.dataset.id;
  document.getElementById('editar-nombre').value = btn.dataset.nombre;
  document.getElementById('editar-email').value  = btn.dataset.email;
  document.getElementById('editar-rol').value    = btn.dataset.rol;
  const esSelf = btn.dataset.self === '1';
  document.getElementById('editar-rol').disabled = esSelf;
  document.getElementById('editar-rol-nota').style.display = esSelf ? 'block' : 'none';
  document.getElementById('modal-editar').classList.add('visible');
}
function cerrarModalEditar() {
  document.getElementById('modal-editar').classList.remove('visible');
}
document.getElementById('modal-editar').addEventListener('click', function (e) {
  if (e.target === this) cerrarModalEditar();
});
document.getElementById('form-editar').addEventListener('submit', function () {
  // Un select disabled no se envia: lo re-habilitamos para que llegue el rol actual
  document.getElementById('editar-rol').disabled = false;
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') cerrarModalEditar();
});
</script>
</body>
</html>
