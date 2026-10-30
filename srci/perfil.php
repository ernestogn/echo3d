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

  // --- Alta / baja de colaboradores (solo admins en el modo actual) ---
  if ($accion === 'agregar_colaborador' || $accion === 'quitar_colaborador') {
    if (!puede_gestionar_colaboradores($uid)) {
      $mensaje  = 'No tenés permiso para gestionar colaboradores.';
      $tipo_msg = 'error';
    } else {
      if ($accion === 'agregar_colaborador') {
        $res = agregar_colaborador($uid, (string)($_POST['email_colaborador'] ?? ''), $uid);
      } else {
        $res = quitar_colaborador($uid, (int)($_POST['colaborador_id'] ?? 0), $uid);
      }
      $mensaje  = $res['mensaje'];
      $tipo_msg = $res['ok'] ? 'exito' : 'error';
    }
  }

  // --- Agregar nota de seguimiento a un reporte propio ---
  if ($accion === 'agregar_nota') {
    $iid  = (int)($_POST['incidencia_id'] ?? 0);
    $nota = trim($_POST['nota'] ?? '');

    $st = db()->prepare('SELECT usuario_id FROM incidencias WHERE id = :id');
    $st->execute([':id' => $iid]);
    $dueno = $st->fetchColumn();

    if ($dueno === false || (int)$dueno !== $uid) {
      $mensaje  = 'No podés agregar notas a ese reporte.';
      $tipo_msg = 'error';
    } elseif ($nota === '' || mb_strlen($nota) > 1000) {
      $mensaje  = 'La nota debe tener entre 1 y 1000 caracteres.';
      $tipo_msg = 'error';
    } else {
      try {
        db()->prepare(
          'INSERT INTO incidencia_seguimiento (incidencia_id, usuario_id, nota) VALUES (:i, :u, :n)'
        )->execute([':i' => $iid, ':u' => $uid, ':n' => $nota]);
      } catch (PDOException $e) {
        $mensaje  = 'No se pudo guardar la nota. Probá de nuevo.';
        $tipo_msg = 'error';
      }
      if ($tipo_msg !== 'error') {
        // Auditoria: quien agrego la nota y extracto de su contenido
        registrar_auditoria($uid, 'seguimiento', $iid, 'Agrego nota de seguimiento: ' . mb_substr($nota, 0, 200));
        header('Location: /srci/detalle.php?id=' . $iid . '#seguimiento');
        exit;
      }
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
  'SELECT i.id, i.direccion, i.estado, i.gravedad, i.fecha_hora, i.aprobado,
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

// Notas de seguimiento por reporte (conteo)
$seg_conteo = contar_seguimiento_por_incidencia(array_column($mis_reportes, 'id'));

$conteo = ['pendiente' => 0, 'en_proceso' => 0, 'resuelto' => 0];
foreach ($mis_reportes as $r) {
  if (isset($conteo[$r['estado']])) {
    $conteo[$r['estado']]++;
  }
}

$csrf = csrf_token();

// --- Red de colaboradores (solo admins en el modo actual) ---
$gestiona_colaboradores = puede_gestionar_colaboradores($uid);
$colaboradores          = $gestiona_colaboradores ? colaboradores_de($uid) : [];
$pendientes_aprobacion  = $gestiona_colaboradores ? incidencias_pendientes_de_aprobacion($uid, es_admin()) : [];

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
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261015">
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

<main class="pagina-contenedor">
  <div style="max-width:860px;margin:0 auto;">
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

    <div style="margin-top:var(--espacio-md);">
      <button type="button" class="boton boton-secundario" id="btn-cambiar-pin">🔑 Cambiar mi PIN</button>
    </div>
  </div>

  </div>

  <?php if ($gestiona_colaboradores): ?>
  <!-- Red de colaboradores (gestion de admins) -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);" id="colaboradores">
    <h2 style="margin-bottom:var(--espacio-xs);">👥 Mis colaboradores</h2>
    <p style="color:var(--color-texto-suave);font-size:.9rem;margin-bottom:var(--espacio-md);">
      Designá usuarios que cargan reportes a través tuyo. Los reportes que cargan
      quedan <strong>pendientes de aprobación</strong>: no se publican hasta que los apruebes.
    </p>

    <form method="POST" action="/srci/perfil.php#colaboradores" style="display:flex;gap:var(--espacio-sm);flex-wrap:wrap;align-items:flex-end;margin-bottom:var(--espacio-md);">
      <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
      <input type="hidden" name="accion" value="agregar_colaborador">
      <div class="campo" style="flex:1;min-width:240px;">
        <label for="email-colaborador">Email del colaborador</label>
        <input type="email" id="email-colaborador" name="email_colaborador" required
               placeholder="usuario@ejemplo.com">
      </div>
      <button type="submit" class="boton boton-primario">Agregar colaborador</button>
    </form>

    <?php if (!$colaboradores): ?>
      <p style="color:var(--color-texto-suave);">Todavía no tenés colaboradores.</p>
    <?php else: ?>
      <div class="tabla-contenedor">
        <table class="tabla-incidencias">
          <thead>
            <tr><th>Nombre</th><th>Email (login)</th><th>Estado</th><th>Alta</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($colaboradores as $col): ?>
              <tr>
                <td><?= esc($col['nombre']) ?></td>
                <td><?= esc($col['email'] ?? '—') ?></td>
                <td>
                  <?php if ($col['estado'] === 'aprobado'): ?>
                    <span class="estado-badge estado-resuelto">Colaborador</span>
                  <?php else: ?>
                    <span class="estado-badge estado-pendiente">Quitado</span>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap;"><?= esc(fecha_legible((string)$col['creado_en'])) ?></td>
                <td>
                  <?php if ($col['estado'] === 'aprobado'): ?>
                    <form method="POST" action="/srci/perfil.php#colaboradores" onsubmit="return confirm('¿Quitar a <?= esc($col['nombre']) ?> de tu red de colaboradores?');">
                      <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                      <input type="hidden" name="accion" value="quitar_colaborador">
                      <input type="hidden" name="colaborador_id" value="<?= (int)$col['colaborador_id'] ?>">
                      <button type="submit" class="boton boton-peligro boton-sm">Quitar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Reportes pendientes de aprobacion -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);" id="pendientes-aprobacion">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--espacio-sm);margin-bottom:var(--espacio-md);">
      <h2>⏳ Reportes pendientes de aprobación</h2>
      <span style="color:var(--color-texto-suave);font-size:.85rem;">
        <?= count($pendientes_aprobacion) ?> <?= count($pendientes_aprobacion) === 1 ? 'reporte' : 'reportes' ?> sin publicar
      </span>
    </div>

    <?php if (!$pendientes_aprobacion): ?>
      <p style="color:var(--color-texto-suave);">No hay reportes esperando aprobación.</p>
    <?php else: ?>
      <div class="tabla-contenedor">
        <table class="tabla-incidencias">
          <thead>
            <tr><th>#</th><th>Tipo</th><th>Dirección</th><th>Barrio</th><th>Gravedad</th><th>Reportado por</th><th>Fecha</th><th>Acción</th></tr>
          </thead>
          <tbody>
            <?php foreach ($pendientes_aprobacion as $p): ?>
              <tr>
                <td><a href="/srci/detalle.php?id=<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?></a></td>
                <td><?= esc($p['tipo_icono'] ?? '') ?> <?= esc($p['tipo_nombre']) ?></td>
                <td><?= esc($p['direccion'] ?? '—') ?></td>
                <td><?= esc($p['barrio_nombre'] ?? '—') ?></td>
                <td><span class="gravedad-badge <?= clase_gravedad($p['gravedad']) ?>"><?= esc(nombre_gravedad($p['gravedad'])) ?></span></td>
                <td><?= esc($p['usuario_nombre']) ?></td>
                <td style="white-space:nowrap;"><?= esc(fecha_legible((string)$p['fecha_hora'])) ?></td>
                <td>
                  <div style="display:flex;gap:4px;">
                    <button type="button" class="boton boton-primario boton-sm"
                            data-aprobar="<?= (int)$p['id'] ?>">✔ Aprobar</button>
                    <button type="button" class="boton boton-peligro boton-sm"
                            data-rechazar="<?= (int)$p['id'] ?>">✖ Rechazar</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Mis reportes (90% del ancho de la ventana) -->
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
            <tr><th>#</th><th>Tipo</th><th>Dirección</th><th>Barrio</th><th>Gravedad</th><th>Estado</th><th>Fecha</th><th>Seguimiento</th></tr>
          </thead>
          <tbody>
            <?php foreach ($mis_reportes as $r): $seg_n = $seg_conteo[(int)$r['id']] ?? 0; ?>
              <tr onclick="location.href='/srci/detalle.php?id=<?= (int)$r['id'] ?>'" style="cursor:pointer;">
                <td><a href="/srci/detalle.php?id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
                <td><?= esc($r['tipo_icono'] ?? '') ?> <?= esc($r['tipo_nombre']) ?></td>
                <td><?= esc($r['direccion'] ?? '—') ?></td>
                <td><?= esc($r['barrio_nombre'] ?? '—') ?></td>
                <td><span class="gravedad-badge <?= clase_gravedad($r['gravedad']) ?>"><?= esc(nombre_gravedad($r['gravedad'])) ?></span></td>
                <td><span class="estado-badge <?= clase_estado($r['estado']) ?>"><?= esc(nombre_estado($r['estado'])) ?></span>
                  <?php if ((int)$r['aprobado'] === 0): ?>
                    <span class="badge-aprobacion" title="Tu reporte todavia no fue publicado: espera la aprobación.">⏳ Pendiente</span>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap;"><?= esc(fecha_legible((string)$r['fecha_hora'])) ?></td>
                <td onclick="event.stopPropagation();" style="white-space:nowrap;">
                  <details>
                    <summary style="cursor:pointer;color:var(--color-acento);font-size:.85rem;">
                      🕓 <?= $seg_n ?> · ＋ Nota
                    </summary>
                    <div style="min-width:240px;margin-top:var(--espacio-sm);">
                      <a href="/srci/detalle.php?id=<?= (int)$r['id'] ?>#seguimiento" style="font-size:.8rem;">Ver seguimiento completo →</a>
                      <form method="POST" action="/srci/perfil.php" style="margin-top:var(--espacio-sm);">
                        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                        <input type="hidden" name="accion" value="agregar_nota">
                        <input type="hidden" name="incidencia_id" value="<?= (int)$r['id'] ?>">
                        <textarea name="nota" rows="2" maxlength="1000" required
                                  placeholder="Ej: Hoy 10 de agosto no pasó el camión a juntar la basura."
                                  style="width:100%;"></textarea>
                        <button type="submit" class="boton boton-primario boton-sm" style="margin-top:var(--espacio-xs);">Guardar nota</button>
                      </form>
                    </div>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Modal: cambiar PIN -->
<div class="modal-fondo modal-centrado" id="modal-pin-fondo" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-pin">
  <div class="modal-caja">
    <div class="modal-encabezado" style="margin-bottom:var(--espacio-sm);">
      <h2 id="titulo-modal-pin" style="margin:0;">🔑 Cambiar mi PIN</h2>
      <button type="button" class="modal-cerrar" id="btn-cerrar-pin" aria-label="Cerrar">✕</button>
    </div>
    <p style="color:var(--color-texto-suave);font-size:.9rem;margin-bottom:var(--espacio-lg);">
      Tu PIN son 4 dígitos. Para cambiarlo necesitamos el actual.
    </p>
    <form method="POST" action="/srci/perfil.php">
      <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
      <input type="hidden" name="accion"     value="cambiar_pin">
      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="pin-actual">PIN actual</label>
        <input type="password" id="pin-actual" name="pin_actual" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="current-password" required>
      </div>
      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="pin-nuevo">PIN nuevo</label>
        <input type="password" id="pin-nuevo" name="pin_nuevo" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="new-password" required>
      </div>
      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="pin-repetir">Repetir PIN nuevo</label>
        <input type="password" id="pin-repetir" name="pin_repetir" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="new-password" required>
      </div>
      <div style="display:flex;gap:var(--espacio-sm);justify-content:flex-end;margin-top:var(--espacio-lg);">
        <button type="button" class="boton boton-secundario" id="btn-cancelar-pin">Cancelar</button>
        <button type="submit" class="boton boton-primario">Guardar PIN</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const fondo      = document.getElementById('modal-pin-fondo');
  const btnAbrir   = document.getElementById('btn-cambiar-pin');
  const btnCerrar  = document.getElementById('btn-cerrar-pin');
  const btnCancelar = document.getElementById('btn-cancelar-pin');

  const abrirModal = function () {
    fondo.classList.add('visible');
    document.getElementById('pin-actual').focus();
  };
  const cerrarModal = function () {
    fondo.classList.remove('visible');
  };

  btnAbrir.addEventListener('click', abrirModal);
  btnCerrar.addEventListener('click', cerrarModal);
  btnCancelar.addEventListener('click', cerrarModal);
  fondo.addEventListener('click', function (e) {
    if (e.target === fondo) { cerrarModal(); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && fondo.classList.contains('visible')) { cerrarModal(); }
  });
})();
</script>

<!-- Aprobar / rechazar reportes pendientes -->
<script>
  (function () {
    const token = <?= json_encode($csrf) ?>;
    const enviar = async function (id, accion) {
      const pregunta = accion === 'aprobar'
        ? '¿Publicar este reporte? Va a aparecer en la lista y en el mapa.'
        : '¿Rechazar este reporte? Queda oculto y registrado (no se borra).';
      if (!confirm(pregunta)) {
        return;
      }
      try {
        const resp = await fetch('/srci/api/aprobar_incidencia.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
          body: JSON.stringify({ incidencia_id: id, accion: accion })
        });
        const datos = await resp.json();
        if (!resp.ok || !datos.ok) {
          alert(datos.error || 'No pudimos completar la acción.');
          return;
        }
        location.reload();
      } catch (e) {
        alert('No pudimos completar la acción.');
      }
    };
    document.querySelectorAll('[data-aprobar]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        enviar(parseInt(btn.getAttribute('data-aprobar'), 10), 'aprobar');
      });
    });
    document.querySelectorAll('[data-rechazar]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        enviar(parseInt(btn.getAttribute('data-rechazar'), 10), 'rechazar');
      });
    });
  })();
</script>
</body>
</html>
