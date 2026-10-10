<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiere_admin();

// Cambio de estado por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validar_csrf();
  $accion  = $_POST['accion'] ?? '';
  $id_inc  = (int)($_POST['incidencia_id'] ?? 0);
  $estado  = $_POST['estado'] ?? '';
  $estados = ['pendiente','en_proceso','resuelto'];

  // Traer filas nuevas de la planilla de Google (sync manual)
  if ($accion === 'traer_google') {
    [$ok_sync, $proc, $err_sync, $detalle_sync] = sincronizar_hoja();
    if ($proc > 0) {
      registrar_auditoria((int)$_SESSION['usuario_id'], 'sync_planilla', null, "Trajo {$proc} fila(s) de la planilla de Google" . ($err_sync ? " ({$err_sync} con error)" : ''));
    }
    if ($ok_sync) {
      header('Location: /srci/admin/reportes.php?sync=1&proc=' . $proc . '&err=' . $err_sync);
    } else {
      header('Location: /srci/admin/reportes.php?sync=0&msg=' . urlencode(is_string($detalle_sync) ? $detalle_sync : 'error desconocido'));
    }
    exit;
  } elseif ($accion === 'restaurar' && $id_inc > 0) {
    db()->prepare('UPDATE incidencias SET oculto = 0 WHERE id = :id')->execute([':id' => $id_inc]);
    registrar_auditoria((int)$_SESSION['usuario_id'], 'restaurar', $id_inc, 'Restauro la incidencia');
  } elseif ($id_inc > 0 && in_array($estado, $estados, true)) {
    $st = db()->prepare('SELECT estado FROM incidencias WHERE id = :id');
    $st->execute([':id' => $id_inc]);
    $estado_anterior = (string)($st->fetchColumn() ?: 'sin estado');
    $sql = $estado === 'resuelto'
      ? 'UPDATE incidencias SET estado = :estado, fecha_resolucion = NOW() WHERE id = :id'
      : 'UPDATE incidencias SET estado = :estado, fecha_resolucion = NULL WHERE id = :id';
    $stmt = db()->prepare($sql);
    $stmt->execute([':estado' => $estado, ':id' => $id_inc]);
    registrar_auditoria((int)$_SESSION['usuario_id'], 'cambiar_estado', $id_inc, "Estado: {$estado_anterior} → {$estado}");

    // Espejo en Google Sheets: actualizar estado y fecha de resolucion de esa fila
    enviar_a_sheets([
      'accion'           => 'estado',
      'id'               => $id_inc,
      'estado'           => $estado,
      'fecha_resolucion' => $estado === 'resuelto' ? date('Y-m-d H:i:s') : null,
    ]);
  }
  header('Location: /srci/admin/reportes.php');
  exit;
}

$ver_ocultas = ($_GET['ocultas'] ?? '') === '1';
$where_oculto = $ver_ocultas ? 'WHERE i.oculto = 1' : 'WHERE i.oculto = 0';

$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 25;
$offset     = ($pagina - 1) * $por_pagina;

$total = (int)db()->query('SELECT COUNT(*) FROM incidencias WHERE oculto = ' . ($ver_ocultas ? '1' : '0'))->fetchColumn();
$total_pags = (int)ceil($total / $por_pagina);

$stmt = db()->prepare(
  "SELECT i.id, i.estado, i.oculto, i.fecha_hora, i.notas, i.direccion, i.gravedad, b.nombre AS barrio,
          t.nombre AS tipo, u.nombre AS usuario
   FROM incidencias i
   JOIN tipos_incidencia t ON t.id = i.tipo_id
   JOIN usuarios u         ON u.id = i.usuario_id
   LEFT JOIN barrios b     ON b.id = i.barrio_id
   {$where_oculto}
   ORDER BY i.fecha_hora DESC
   LIMIT :lim OFFSET :off"
);
$stmt->bindValue(':lim', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,     PDO::PARAM_INT);
$stmt->execute();
$filas = $stmt->fetchAll();
$csrf  = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Reportes · SRCI</title>
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261010e">
</head>
<body>
<nav class="nav-principal">
  <div class="nav-contenido">
    <a href="/srci/index.php" class="nav-logo"><span class="nav-logo-icono">🗺️</span>SRCI</a>
    <div class="nav-enlaces">
      <a href="/srci/index.php"       class="nav-enlace">Mapa</a>
      <a href="/srci/incidencias.php" class="nav-enlace">Listado</a>
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
    <a href="/srci/admin/reportes.php" class="admin-nav-item activo">📋 Reportes</a>
    <a href="/srci/admin/usuarios.php" class="admin-nav-item">👥 Usuarios</a>
    <a href="/srci/admin/tipos.php"    class="admin-nav-item">🏷️ Tipos</a>
    <a href="/srci/admin/barrios.php"  class="admin-nav-item">🏙️ Barrios</a>
    <a href="/srci/admin/auditoria.php" class="admin-nav-item">🧾 Auditoría</a>
  </aside>

  <main class="admin-main">
    <div class="pagina-encabezado">
      <h1><?= $ver_ocultas ? 'Incidencias ocultas' : 'Gestión de reportes' ?></h1>
      <span style="color:var(--color-texto-suave);font-size:.9rem;"><?= $total ?> incidencias</span>
      <a href="/srci/admin/reportes.php<?= $ver_ocultas ? '' : '?ocultas=1' ?>" class="boton <?= $ver_ocultas ? 'boton-secundario' : 'boton-primario' ?> boton-sm">
        <?= $ver_ocultas ? '← Ver activas' : '👁 Ver ocultas' ?>
      </a>
      <?php if (!$ver_ocultas): ?>
        <form method="POST" action="/srci/admin/reportes.php" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
          <input type="hidden" name="accion"     value="traer_google">
          <button type="submit" class="boton boton-secundario boton-sm">
            ⬇ Traer de Google
          </button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (isset($_GET['sync'])): ?>
      <?php $sync_proc = (int)($_GET['proc'] ?? 0); $sync_err = (int)($_GET['err'] ?? 0); ?>
      <?php if ($_GET['sync'] === '0'): ?>
        <div class="mensaje mensaje-error" style="margin-bottom:var(--espacio-lg);" role="alert">
          <span>⚠️</span>
          <span>No se pudo sincronizar con Google: <?= esc($_GET['msg'] ?? 'error desconocido') ?></span>
        </div>
      <?php elseif ($sync_proc === 0 && $sync_err === 0): ?>
        <div class="mensaje mensaje-info" id="toast-sync" style="margin-bottom:var(--espacio-lg);" role="status">
          <span>ℹ️</span>
          <span>Nada nuevo: la planilla ya está al día.</span>
        </div>
      <?php else: ?>
        <div class="mensaje mensaje-<?= $sync_err ? 'info' : 'exito' ?>" style="margin-bottom:var(--espacio-lg);" role="status">
          <span>✓</span>
          <span>Sincronización con Google: <?= $sync_proc ?> fila(s) importada(s)<?= $sync_err ? ", {$sync_err} con error" : '' ?>.</span>
        </div>
      <?php endif; ?>
      <script>
        // El "nada nuevo" se auto-oculta como toast
        setTimeout(function () {
          const t = document.getElementById('toast-sync');
          if (t) {
            t.style.transition = 'opacity .6s';
            t.style.opacity = '0';
            setTimeout(function () { t.remove(); }, 650);
          }
        }, 3000);
      </script>
    <?php endif; ?>

    <div class="tabla-contenedor">
      <table class="tabla-incidencias">
        <thead>
          <tr>
            <th>#</th><th>Tipo</th><th>Barrio</th><th>Dirección</th><th>Gravedad</th><th>Usuario</th><th>Fecha</th><th>Estado</th><th>Cambiar estado</th><th>Ver</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $f): ?>
            <tr>
              <td><?= (int)$f['id'] ?></td>
              <td><?= esc($f['tipo']) ?></td>
              <td><?= esc($f['barrio'] ?? '—') ?></td>
              <td><?= esc($f['direccion'] ?? '—') ?></td>
              <td><span class="gravedad-badge <?= clase_gravedad($f['gravedad']) ?>"><?= esc(nombre_gravedad($f['gravedad'])) ?></span></td>
              <td><?= esc($f['usuario']) ?></td>
              <td style="white-space:nowrap;"><?= esc(fecha_legible($f['fecha_hora'])) ?></td>
              <td><span class="estado-badge <?= clase_estado($f['estado']) ?>"><?= esc(nombre_estado($f['estado'])) ?></span></td>
              <?php if ($ver_ocultas): ?>
                <td>
                  <form method="POST" action="/srci/admin/reportes.php" onsubmit="return confirm('¿Restaurar esta incidencia a la lista activa?');">
                    <input type="hidden" name="csrf_token"    value="<?= esc($csrf) ?>">
                    <input type="hidden" name="incidencia_id" value="<?= (int)$f['id'] ?>">
                    <input type="hidden" name="accion"        value="restaurar">
                    <button type="submit" class="boton boton-primario boton-sm">👁 Restaurar</button>
                  </form>
                </td>
              <?php else: ?>
                <td>
                  <form method="POST" action="/srci/admin/reportes.php" style="display:flex;gap:4px;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token"    value="<?= esc($csrf) ?>">
                    <input type="hidden" name="incidencia_id" value="<?= (int)$f['id'] ?>">
                    <select name="estado" aria-label="Nuevo estado" style="padding:4px 8px;background:var(--color-fondo);border:1px solid var(--color-borde);border-radius:6px;color:var(--color-texto);font-size:.85rem;">
                      <option value="pendiente"  <?= $f['estado']==='pendiente'  ? 'selected':'' ?>>Pendiente</option>
                      <option value="en_proceso" <?= $f['estado']==='en_proceso' ? 'selected':'' ?>>En gestión</option>
                      <option value="resuelto"   <?= $f['estado']==='resuelto'   ? 'selected':'' ?>>Resuelto</option>
                    </select>
                    <button type="submit" class="boton boton-primario boton-sm">Guardar</button>
                  </form>
                </td>
              <?php endif; ?>
              <td><a href="/srci/detalle.php?id=<?= (int)$f['id'] ?>" class="boton boton-secundario boton-sm">Ver</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pags > 1): ?>
      <nav class="paginacion">
        <?php for ($p = 1; $p <= $total_pags; $p++): ?>
          <a href="/srci/admin/reportes.php?pagina=<?= $p ?>" class="pagina-enlace <?= $p===$pagina?'activa':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
