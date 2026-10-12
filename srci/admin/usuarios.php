<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiere_admin();

$mensaje = '';
$tipo_msg = '';
$magic_link   = '';
$magic_nombre = '';

// --- Activacion masiva en lotes (solo referentes con email ruteable) ---
// Los administradores se activan uno por uno; emails no ruteables (.local) quedan fuera.
define('LOTE_LINKS_TAMANO', 10);
$SQL_LOTE_WHERE = "activo = 0 AND rol = 'usuario' AND email IS NOT NULL AND email <> '' AND email NOT LIKE '%@%.local'";

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

  // Magic link de acceso (para enviar por WhatsApp, sin PIN)
  if ($accion === 'magic_link') {
    $uid = (int)($_POST['usuario_id'] ?? 0);
    if ($uid > 0) {
      $st = db()->prepare('SELECT nombre FROM usuarios WHERE id = :id');
      $st->execute([':id' => $uid]);
      $u = $st->fetch();
      if ($u) {
        $magic_link   = generar_magic_link($uid);
        $magic_nombre = $u['nombre'];
        registrar_auditoria(
          (int)$_SESSION['usuario_id'],
          'magic_link',
          null,
          'Genero link de acceso para ' . $u['nombre'] . ' (#' . $uid . ')'
        );
      }
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

  // Activacion masiva: activa y envia link + PIN de respaldo a un lote de inactivos
  if ($accion === 'lote_links') {
    $inactivos = db()->query(
      'SELECT id, nombre, email FROM usuarios
        WHERE ' . $SQL_LOTE_WHERE . '
        ORDER BY id
        LIMIT ' . LOTE_LINKS_TAMANO
    )->fetchAll();

    $resultados = [];
    foreach ($inactivos as $u) {
      $pin = generar_pin();
      // activo=1 + pin nuevo (condicion activo=0: nunca re-activar un ya activo)
      db()->prepare('UPDATE usuarios SET activo = 1, pin_hash = :h WHERE id = :id AND activo = 0')
         ->execute([':h' => password_hash($pin, PASSWORD_DEFAULT), ':id' => (int)$u['id']]);
      $link    = generar_magic_link((int)$u['id']);
      $enviado = enviar_activacion_por_email($u['email'], $u['nombre'], $link, $pin);
      registrar_auditoria(
        (int)$_SESSION['usuario_id'],
        'activar',
        null,
        'Lote masivo: activo a ' . $u['nombre'] . ' (<' . $u['email'] . '>) — PIN nuevo + link de acceso (' . MAGIC_LINK_DIAS . ' dias)' . ($enviado ? '' : ' — EMAIL FALLO')
      );
      $resultados[] = ['nombre' => $u['nombre'], 'email' => $u['email'], 'ok' => $enviado];
    }

    // Flash + redirect: evita reenviar el lote al recargar
    $_SESSION['lote_links_resultado'] = $resultados;
    header('Location: /srci/admin/usuarios.php#lote');
    exit;
  }
}

// Resultado del ultimo lote (flash) + datos de la tarjeta de activacion masiva
$resultado_lote = $_SESSION['lote_links_resultado'] ?? null;
unset($_SESSION['lote_links_resultado']);

$pendientes_email = (int)db()->query("SELECT COUNT(*) FROM usuarios WHERE {$SQL_LOTE_WHERE}")->fetchColumn();
$sin_email_lista  = db()->query("SELECT nombre FROM usuarios WHERE activo = 0 AND (email IS NULL OR email = '') ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$historial_lotes  = db()->query("SELECT a.fecha_hora, a.detalle, u.nombre AS admin_nombre
                                   FROM auditoria a
                              LEFT JOIN usuarios u ON u.id = a.usuario_id
                                  WHERE a.accion = 'activar' AND a.detalle LIKE 'Lote masivo:%'
                               ORDER BY a.id DESC LIMIT 10")->fetchAll();

$usuarios = db()->query(
  "SELECT u.id, u.nombre, u.email, u.rol, u.activo, u.creado_en,
          GROUP_CONCAT(b.nombre ORDER BY b.nombre SEPARATOR ', ') AS barrios
   FROM usuarios u
   LEFT JOIN referentes_barrios rb ON rb.usuario_id = u.id
   LEFT JOIN barrios b ON b.id = rb.barrio_id
   GROUP BY u.id
   ORDER BY u.id"
)->fetchAll();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Usuarios · SRCI</title>
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
    <a href="/srci/admin/usuarios.php" class="admin-nav-item activo">👥 Usuarios</a>
    <a href="/srci/admin/tipos.php"    class="admin-nav-item">🏷️ Tipos</a>
    <a href="/srci/admin/categorias.php" class="admin-nav-item">🗂️ Categorías</a>
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

    <?php if ($magic_link !== ''): ?>
      <div class="mensaje mensaje-info" style="margin-bottom:var(--espacio-xl);" role="note">
        <span>🔗</span>
        <span style="flex:1;">
          Link de acceso para <strong><?= esc($magic_nombre) ?></strong> — válido <?= MAGIC_LINK_DIAS ?> días, entra directo sin PIN. Reenvialo por WhatsApp o email:
          <div style="display:flex;gap:var(--espacio-sm);margin-top:var(--espacio-sm);flex-wrap:wrap;">
            <input type="text" readonly id="magic-link-url" value="<?= esc($magic_link) ?>" onclick="this.select()"
                   style="flex:1;min-width:240px;padding:8px 10px;border:1px solid var(--color-borde);border-radius:var(--radio-md);font-size:.8rem;background:var(--color-fondo);color:var(--color-texto);">
            <button type="button" class="boton boton-primario boton-sm" onclick="copiarMagicLink(this)">📋 Copiar</button>
          </div>
        </span>
      </div>
    <?php endif; ?>

    <!-- Crear usuario -->
    <div class="tarjeta" style="margin-bottom:var(--espacio-xl);">
      <h2 style="margin-bottom:var(--espacio-lg);">Agregar usuario</h2>
      <form method="POST" action="/srci/admin/usuarios.php">
        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
        <input type="hidden" name="accion"     value="crear">
        <div class="grilla-form-4">
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

    <!-- Activacion masiva en lotes -->
    <div class="tarjeta" id="lote" style="margin-bottom:var(--espacio-xl);border-color:rgba(79,70,229,.35);">
      <h2 style="margin-bottom:var(--espacio-md);">🚀 Activación masiva <span style="font-weight:400;color:var(--color-texto-suave);font-size:.85rem;">(lotes trazables)</span></h2>
      <p style="color:var(--color-texto);line-height:1.7;margin-bottom:var(--espacio-md);">
        Activa referentes inactivos y les envía por email un <strong>link de acceso directo</strong>
        (válido <?= MAGIC_LINK_DIAS ?> días, entra sin PIN) más un <strong>PIN de respaldo</strong>.
        Solo procesa cuentas de <strong>referentes</strong> con email válido; los
        <strong>administradores</strong> se activan uno por uno con el botón "Activar + PIN" de la tabla.
        Cada click procesa un lote de <?= LOTE_LINKS_TAMANO ?>; cada activación queda registrada en
        <a href="/srci/admin/auditoria.php">Auditoría</a> con autor, fecha y resultado del envío.
      </p>
      <p style="color:var(--color-texto);line-height:1.7;margin-bottom:var(--espacio-md);">
        📬 Pendientes con email: <strong><?= $pendientes_email ?></strong>
        <?php if ($sin_email_lista): ?>
          <br>🚫 Sin email (cargalo en la tabla de abajo para poder activarlos):
          <strong><?= count($sin_email_lista) ?></strong> — <?= esc(implode(', ', $sin_email_lista)) ?>
        <?php endif; ?>
      </p>

      <form method="POST" action="/srci/admin/usuarios.php" onsubmit="return confirm('¿Activar y enviar link a los próximos <?= min(LOTE_LINKS_TAMANO, $pendientes_email) ?> usuarios inactivos con email?');">
        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
        <input type="hidden" name="accion"     value="lote_links">
        <button type="submit" class="boton boton-primario" <?= $pendientes_email === 0 ? 'disabled' : '' ?>>
          🚀 Activar y enviar link (lote de <?= min(LOTE_LINKS_TAMANO, $pendientes_email) ?>)
        </button>
        <?php if ($pendientes_email === 0): ?>
          <span style="color:var(--color-verde);font-weight:600;">✅ No queda nadie pendiente con email</span>
        <?php endif; ?>
      </form>

      <?php if (is_array($resultado_lote) && $resultado_lote):
        $oks = count(array_filter($resultado_lote, fn($r) => $r['ok']));
      ?>
        <div class="mensaje mensaje-<?= $oks === count($resultado_lote) ? 'exito' : 'info' ?>" style="margin-top:var(--espacio-md);">
          <span><?= $oks === count($resultado_lote) ? '✅' : '⚠️' ?></span>
          <span>Último lote: <strong><?= $oks ?> de <?= count($resultado_lote) ?></strong> emails enviados.
          Los usuarios ya están activos; si algún email falló, reenvialo desde la tabla (🔗 Link o Nuevo PIN).</span>
        </div>
        <div class="tabla-contenedor" style="margin-top:var(--espacio-md);">
          <table class="tabla-incidencias">
            <thead><tr><th>Usuario</th><th>Email</th><th>Resultado</th></tr></thead>
            <tbody>
              <?php foreach ($resultado_lote as $r): ?>
                <tr>
                  <td><?= esc($r['nombre']) ?></td>
                  <td><?= esc($r['email']) ?></td>
                  <td>
                    <?php if ($r['ok']): ?>
                      <span class="estado-badge estado-resuelto">✅ Activado + link enviado</span>
                    <?php else: ?>
                      <span class="estado-badge estado-pendiente">❌ Email falló — activado igual</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($historial_lotes): ?>
        <h3 style="margin-top:var(--espacio-lg);">🧾 Últimos lotes (trazabilidad)</h3>
        <ul style="line-height:1.9;color:var(--color-texto);font-size:.88rem;padding-left:var(--espacio-lg);">
          <?php foreach ($historial_lotes as $h): ?>
            <li>
              <strong><?= esc(fecha_legible($h['fecha_hora'])) ?></strong> — <?= esc($h['detalle']) ?>
              <?php if ($h['admin_nombre']): ?><span style="color:var(--color-texto-suave);">(por <?= esc($h['admin_nombre']) ?>)</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Buscador -->
    <div style="display:flex;gap:var(--espacio-md);align-items:center;margin-bottom:var(--espacio-md);flex-wrap:wrap;">
      <input type="search" id="buscador-usuarios" autocomplete="off"
             placeholder="Buscar por nombre, email o barrio…  (tecla /)"
             style="flex:1;min-width:260px;padding:10px 14px;border:1px solid var(--color-borde);border-radius:var(--radio-md);font-size:.95rem;background:var(--color-superficie);color:var(--color-texto);">
      <span id="contador-usuarios" style="color:var(--color-texto-suave);font-size:.85rem;"></span>
    </div>

    <!-- Tabla de usuarios -->
    <div class="tabla-contenedor">
      <table class="tabla-incidencias">
        <thead>
          <tr><th>#</th><th>Nombre</th><th>Email</th><th>Barrios</th><th>Rol</th><th>Estado</th><th>Creado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td><strong><?= esc($u['nombre']) ?></strong></td>
              <td><?= esc($u['email'] ?? '—') ?></td>
              <td style="font-size:.8rem;color:var(--color-texto-suave);max-width:220px;"><?= esc($u['barrios'] ?? '—') ?></td>
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
                  <!-- Magic link -->
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token"  value="<?= esc($csrf) ?>">
                    <input type="hidden" name="accion"      value="magic_link">
                    <input type="hidden" name="usuario_id"  value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="boton boton-secundario boton-sm"
                            onclick="return confirm('¿Generar link de acceso para <?= esc(addslashes($u['nombre'])) ?>? (válido <?= MAGIC_LINK_DIAS ?> días, sin PIN)')">
                      🔗 Link
                    </button>
                  </form>
                  <!-- Regenerar PIN -->
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token"  value="<?= esc($csrf) ?>">
                    <input type="hidden" name="accion"      value="nuevo_pin">
                    <input type="hidden" name="usuario_id"  value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="boton boton-secundario boton-sm">
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
<div class="modal-fondo modal-centrado" id="modal-editar" role="dialog" aria-modal="true" aria-labelledby="modal-editar-titulo">
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
// Buscador: filtra filas por nombre, email, barrio, rol o estado
const buscador  = document.getElementById('buscador-usuarios');
const filas     = Array.from(document.querySelectorAll('.tabla-incidencias tbody tr'));
const contador  = document.getElementById('contador-usuarios');
function filtrarUsuarios() {
  const q = buscador.value.trim().toLowerCase();
  let visibles = 0;
  filas.forEach(function (f) {
    const ok = f.textContent.toLowerCase().includes(q);
    f.style.display = ok ? '' : 'none';
    if (ok) visibles++;
  });
  contador.textContent = q ? (visibles + ' de ' + filas.length) : '';
}
buscador.addEventListener('input', filtrarUsuarios);
// Tecla / enfoca el buscador (si no se esta escribiendo en otro campo)
document.addEventListener('keydown', function (e) {
  const tag = (document.activeElement && document.activeElement.tagName) || '';
  if (e.key === '/' && !['INPUT', 'SELECT', 'TEXTAREA'].includes(tag)) {
    e.preventDefault();
    buscador.focus();
  }
});

function copiarMagicLink(btn) {
  const input = document.getElementById('magic-link-url');
  input.select();
  navigator.clipboard.writeText(input.value).then(function () {
    const original = btn.textContent;
    btn.textContent = '✓ Copiado';
    setTimeout(function () { btn.textContent = original; }, 2000);
  });
}

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
