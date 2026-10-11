<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funciones.php';

requiere_sesion();

$uid      = (int)$_SESSION['usuario_id'];
$mensaje  = '';
$tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validar_csrf();
  $accion = $_POST['accion'] ?? '';

  // --- Cambiar mi PIN (exige el PIN actual) ---
  if ($accion === 'cambiar_pin') {
    $actual  = trim($_POST['pin_actual']  ?? '');
    $nuevo   = trim($_POST['pin_nuevo']   ?? '');
    $repetir = trim($_POST['pin_repetir'] ?? '');

    $st = db()->prepare('SELECT pin_hash FROM usuarios WHERE id = :id');
    $st->execute([':id' => $uid]);
    $hash = (string)$st->fetchColumn();

    if (!preg_match('/^\d{4}$/', $nuevo) || $nuevo !== $repetir) {
      $mensaje  = 'El PIN nuevo debe tener 4 dígitos y coincidir con la repetición.';
      $tipo_msg = 'error';
    } elseif (!password_verify($actual, $hash)) {
      $mensaje  = 'El PIN actual no es correcto.';
      $tipo_msg = 'error';
    } elseif ($nuevo === $actual) {
      $mensaje  = 'El PIN nuevo no puede ser igual al actual.';
      $tipo_msg = 'error';
    } else {
      $nuevo_hash = password_hash($nuevo, PASSWORD_DEFAULT);
      db()->prepare('UPDATE usuarios SET pin_hash = :h WHERE id = :id')
          ->execute([':h' => $nuevo_hash, ':id' => $uid]);
      registrar_auditoria($uid, 'cambiar_pin', null, 'Cambio su PIN de acceso');
      $mensaje  = 'PIN actualizado. Usalo la próxima vez que ingreses.';
      $tipo_msg = 'exito';
    }
  }

}

// --- Datos actuales ---
$st = db()->prepare('SELECT id, nombre, email, rol, activo, creado_en FROM usuarios WHERE id = :id');
$st->execute([':id' => $uid]);
$u = $st->fetch();

// --- Barrios asignados (referente) ---
$stb = db()->prepare(
  'SELECT b.nombre
   FROM referentes_barrios rb
   JOIN barrios b ON b.id = rb.barrio_id
   WHERE rb.usuario_id = :id
   ORDER BY b.nombre'
);
$stb->execute([':id' => $uid]);
$barrios = $stb->fetchAll(PDO::FETCH_COLUMN);

// --- Mis reportes (nunca borrados: solo ocultos) ---
$str = db()->prepare(
  'SELECT i.id, i.direccion, i.estado, i.gravedad, i.fecha_hora,
          t.nombre AS tipo_nombre, t.icono AS tipo_icono,
          b.nombre AS barrio_nombre
   FROM incidencias i
   JOIN tipos_incidencia t ON t.id = i.tipo_id
   LEFT JOIN barrios b ON b.id = i.barrio_id
   WHERE i.usuario_id = :id AND i.oculto = 0
   ORDER BY i.fecha_hora DESC
   LIMIT 200'
);
$str->execute([':id' => $uid]);
$mis_reportes = $str->fetchAll();

$conteo = ['pendiente' => 0, 'en_proceso' => 0, 'resuelto' => 0];
foreach ($mis_reportes as $r) {
  if (isset($conteo[$r['estado']])) {
    $conteo[$r['estado']]++;
  }
}

$csrf = csrf_token();

// --- Avatar: Gravatar (hash del email) con fallback de iniciales ---
$email_norm = strtolower(trim((string)($u['email'] ?? '')));
$gravatar   = $email_norm !== ''
  ? 'https://www.gravatar.com/avatar/' . md5($email_norm) . '?s=160&d=404'
  : '';

// Primera letra de las dos primeras palabras del nombre
$iniciales = '';
foreach (preg_split('/\s+/', trim((string)$u['nombre'])) ?: [] as $parte) {
  if ($parte === '') {
    continue;
  }
  if (preg_match('/^\X/u', $parte, $m)) {
    $iniciales .= strtoupper($m[0]);
  }
  if (strlen($iniciales) >= 2) {
    break;
  }
}
if ($iniciales === '') {
  $iniciales = '?';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi perfil — SRCI</title>
  <meta name="description" content="Tus datos, tu PIN y tus reportes en el SRCI.">
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261011h">
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
      <a href="/srci/incidencias.php" class="nav-enlace">Listado</a>
      <a href="/srci/ayuda.php"       class="nav-enlace">Ayuda</a>
      <?php if (es_admin()): ?>
        <a href="/srci/admin/reportes.php" class="nav-enlace">Admin</a>
      <?php endif; ?>
    </div>
    <div class="nav-usuario">
      <a href="/srci/perfil.php" class="nav-perfil activo">👤 <?= esc($u['nombre']) ?></a>
      <a href="/srci/logout.php" class="boton boton-secundario boton-sm">Salir</a>
    </div>
  </div>
</nav>

<main class="pagina-contenedor" style="max-width:860px;">
  <div class="pagina-encabezado">
    <h1>Mi perfil</h1>
    <p style="color:var(--color-texto-suave);margin-top:var(--espacio-xs);">
      Tus datos, tu PIN de acceso y los reportes que cargaste.
    </p>
  </div>

  <?php if ($mensaje !== ''): ?>
    <div class="mensaje mensaje-<?= esc($tipo_msg) ?>" style="margin-bottom:var(--espacio-xl);" role="alert">
      <span><?= $tipo_msg === 'exito' ? '✓' : ($tipo_msg === 'info' ? 'ℹ️' : '⚠️') ?></span>
      <span><?= esc($mensaje) ?></span>
    </div>
  <?php endif; ?>

  <!-- Datos -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <div class="perfil-encabezado">
      <div class="perfil-avatar" aria-hidden="true">
        <span class="perfil-avatar-iniciales"><?= esc($iniciales) ?></span>
        <?php if ($gravatar !== ''): ?>
          <img src="<?= esc($gravatar) ?>" alt="" width="80" height="80"
               onerror="this.remove()">
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:200px;">
        <h2 style="margin-bottom:var(--espacio-xs);"><?= esc($u['nombre']) ?></h2>
        <p style="color:var(--color-texto-suave);font-size:.9rem;">
          <span style="text-transform:capitalize;font-weight:600;"><?= esc($u['rol']) ?></span>
          ·
          <span class="estado-badge" style="<?= $u['activo'] ? 'background:rgba(34,197,94,.15);color:var(--color-verde)' : 'background:rgba(239,68,68,.15);color:var(--color-rojo)' ?>">
            <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
          </span>
        </p>
        <p style="color:var(--color-texto-suave);font-size:.85rem;margin-top:var(--espacio-xs);">
          En el sistema desde <?= esc(fecha_legible((string)$u['creado_en'])) ?>
        </p>
      </div>
    </div>

    <dl class="perfil-datos">
      <div>
        <dt>Email (login)</dt>
        <dd><?= esc($u['email'] ?? '—') ?></dd>
      </div>
      <div>
        <dt>Barrios a mi cargo</dt>
        <dd><?= $barrios ? esc(implode(', ', $barrios)) : 'Sin barrios asignados' ?></dd>
      </div>
    </dl>
  </div>

  <!-- Cambiar PIN -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <h2 style="margin-bottom:var(--espacio-xs);">🔑 Cambiar mi PIN</h2>
    <p style="color:var(--color-texto-suave);font-size:.9rem;margin-bottom:var(--espacio-lg);">
      Tu PIN son 4 dígitos. Para cambiarlo necesitamos el actual.
    </p>
    <form method="POST" action="/srci/perfil.php">
      <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
      <input type="hidden" name="accion"     value="cambiar_pin">
      <div class="grilla-form-4">
        <div class="campo">
          <label for="pin-actual">PIN actual</label>
          <input type="password" id="pin-actual" name="pin_actual" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="current-password" required>
        </div>
        <div class="campo">
          <label for="pin-nuevo">PIN nuevo</label>
          <input type="password" id="pin-nuevo" name="pin_nuevo" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="new-password" required>
        </div>
        <div class="campo">
          <label for="pin-repetir">Repetir PIN nuevo</label>
          <input type="password" id="pin-repetir" name="pin_repetir" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="new-password" required>
        </div>
        <button type="submit" class="boton boton-primario" style="align-self:flex-end;">Guardar PIN</button>
      </div>
    </form>
  </div>

  <!-- Mis reportes -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--espacio-sm);margin-bottom:var(--espacio-md);">
      <h2>📋 Mis reportes</h2>
      <span style="color:var(--color-texto-suave);font-size:.85rem;">
        <?= $conteo['pendiente'] ?> pendientes · <?= $conteo['en_proceso'] ?> en gestión · <?= $conteo['resuelto'] ?> resueltos
      </span>
    </div>

    <?php if (!$mis_reportes): ?>
      <p style="color:var(--color-texto-suave);">
        Todavía no cargaste reportes. Tocá <strong>“＋ Reportar incidencia”</strong> en el mapa.
      </p>
    <?php else: ?>
      <div class="tabla-contenedor">
        <table class="tabla-incidencias">
          <thead>
            <tr><th>#</th><th>Tipo</th><th>Dirección</th><th>Barrio</th><th>Gravedad</th><th>Estado</th><th>Fecha</th></tr>
          </thead>
          <tbody>
            <?php foreach ($mis_reportes as $r): ?>
              <tr onclick="location.href='/srci/detalle.php?id=<?= (int)$r['id'] ?>'" style="cursor:pointer;">
                <td><a href="/srci/detalle.php?id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
                <td><?= esc($r['tipo_icono'] ?? '') ?> <?= esc($r['tipo_nombre']) ?></td>
                <td><?= esc($r['direccion'] ?? '—') ?></td>
                <td><?= esc($r['barrio_nombre'] ?? '—') ?></td>
                <td><span class="gravedad-badge <?= clase_gravedad($r['gravedad']) ?>"><?= esc(nombre_gravedad($r['gravedad'])) ?></span></td>
                <td><span class="estado-badge <?= clase_estado($r['estado']) ?>"><?= esc(nombre_estado($r['estado'])) ?></span></td>
                <td style="white-space:nowrap;"><?= esc(fecha_legible((string)$r['fecha_hora'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
