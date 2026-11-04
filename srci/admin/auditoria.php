<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiere_admin();

$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 50;
$offset     = ($pagina - 1) * $por_pagina;

// Filtros. En la vista de accesos ("?ver=logins") se ignoran tipo y accion.
$ver_logins = (($_GET['ver'] ?? '') === 'logins');
$f_usuario  = is_string($_GET['usuario'] ?? null) ? mb_substr(trim($_GET['usuario']), 0, 120) : '';
$f_tipo     = $ver_logins ? 0 : max(0, (int)($_GET['tipo_id'] ?? 0));
$f_accion   = $ver_logins ? '' : (is_string($_GET['accion'] ?? null) ? trim($_GET['accion']) : '');
$f_q        = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 120) : '';

$donde  = [];
$params = [];

if ($f_usuario !== '') {
  $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f_usuario);
  $donde[]  = "CONCAT_WS(' ', u.nombre, u.email) LIKE :usuario ESCAPE '\\\\'";
  $params[':usuario'] = '%' . $escapado . '%';
}
if ($f_tipo > 0) {
  $donde[]           = 'i.tipo_id = :tipo_id';
  $params[':tipo_id'] = $f_tipo;
}
if ($ver_logins) {
  $donde[] = "a.accion IN ('login', 'login_link')";
} elseif ($f_accion !== '') {
  $donde[]           = 'a.accion = :accion';
  $params[':accion'] = $f_accion;
}
if ($f_q !== '') {
  $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f_q);
  $donde[]  = "CONCAT_WS(' ', a.detalle, u.nombre, a.accion) LIKE :q ESCAPE '\\\\'";
  $params[':q'] = '%' . $escapado . '%';
}

$where_sql = $donde ? (' WHERE ' . implode(' AND ', $donde)) : '';
$base_sql  = 'FROM auditoria a
  LEFT JOIN usuarios u ON u.id = a.usuario_id
  LEFT JOIN incidencias i ON i.id = a.incidencia_id
  LEFT JOIN tipos_incidencia t ON t.id = i.tipo_id';

$stmt = db()->prepare('SELECT COUNT(*) ' . $base_sql . $where_sql);
foreach ($params as $clave => $valor) {
  $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$total      = (int)$stmt->fetchColumn();
$total_pags = (int)ceil($total / $por_pagina);
if ($total_pags > 0 && $pagina > $total_pags) {
  $pagina = $total_pags;
  $offset = ($pagina - 1) * $por_pagina;
}

$stmt = db()->prepare(
  'SELECT a.id, a.accion, a.incidencia_id, a.detalle, a.fecha_hora, u.nombre AS usuario,
          t.nombre AS tipo_nombre, t.icono AS tipo_icono '
  . $base_sql . $where_sql . '
   ORDER BY a.id DESC
   LIMIT :lim OFFSET :off'
);
foreach ($params as $clave => $valor) {
  $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':lim', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,     PDO::PARAM_INT);
$stmt->execute();
$registros = $stmt->fetchAll();

// Listas para los selects de filtros
$tipos_lista    = db()->query('SELECT id, nombre, icono FROM tipos_incidencia ORDER BY nombre')->fetchAll();
$acciones_lista = db()->query('SELECT DISTINCT accion FROM auditoria ORDER BY accion')->fetchAll(PDO::FETCH_COLUMN);
foreach (['login', 'login_link'] as $accion_login) {
  if (!in_array($accion_login, $acciones_lista, true)) {
    $acciones_lista[] = $accion_login;
  }
}
sort($acciones_lista, SORT_NATURAL | SORT_FLAG_CASE);

// Paginacion que conserva los filtros activos
$params_url = [];
if ($ver_logins)           { $params_url['ver'] = 'logins'; }
if ($f_usuario !== '')     { $params_url['usuario'] = $f_usuario; }
if ($f_tipo > 0)           { $params_url['tipo_id'] = $f_tipo; }
if ($f_accion !== '') { $params_url['accion'] = $f_accion; }
if ($f_q !== '')      { $params_url['q'] = $f_q; }
$url_pagina = static function (int $p) use ($params_url): string {
  $query = $params_url;
  $query['pagina'] = $p;
  return '/srci/admin/auditoria.php?' . http_build_query($query);
};

// Nombre legible y color de cada accion
function accion_legible(string $accion): string
{
  return match($accion) {
    'crear'          => 'Cargo',
    'editar'         => 'Edito',
    'cambiar_estado' => 'Cambio estado',
    'ocultar'        => 'Oculto',
    'restaurar'      => 'Restauro',
    'subir_foto'     => 'Subio foto',
    'borrar_foto'    => 'Borro foto',
    'cambiar_pin'    => 'Cambio su PIN',
    'seguimiento'    => 'Nota de seguimiento',
    'bloqueo_login'  => 'Bloqueo anti-fuerza bruta',
    'acceso_bloqueado' => 'Intento bloqueado',
    'login'          => 'Ingreso con PIN',
    'login_link'     => 'Ingreso por magic link',
    'magic_link'     => 'Genero link de acceso',
    'sync_planilla'  => 'Sincronizo planilla',
    'recalcular_barrios' => 'Recalculo barrios',
    'activar'        => 'Activo usuario',
    'desactivar'     => 'Desactivo usuario',
    'nuevo_pin'      => 'Regenero PIN',
    'toggle_visibilidad' => 'Cambio visibilidad',
    'aprobar'        => 'Aprobo reporte',
    'rechazar'       => 'Rechazo reporte',
    'agregar_colaborador' => 'Designo colaborador',
    'quitar_colaborador'  => 'Quito colaborador',
    'generar_reporte'     => 'Genero reporte',
    'exportar_reporte'    => 'Exporto datos',
    default          => ucfirst($accion),
  };
}

function clase_accion(string $accion): string
{
  return match($accion) {
    'crear'          => 'aud-crear',
    'editar'         => 'aud-editar',
    'cambiar_estado' => 'aud-estado',
    'ocultar'        => 'aud-ocultar',
    'restaurar'      => 'aud-restaurar',
    'subir_foto'     => 'aud-foto',
    'borrar_foto'    => 'aud-foto',
    'cambiar_pin'    => 'aud-editar',
    'seguimiento'    => 'aud-editar',
    'bloqueo_login'  => 'aud-ocultar',
    'acceso_bloqueado' => 'aud-ocultar',
    'login'          => 'aud-restaurar',
    'login_link'     => 'aud-restaurar',
    'magic_link'     => 'aud-editar',
    'sync_planilla'  => 'aud-editar',
    'recalcular_barrios' => 'aud-editar',
    'activar'        => 'aud-restaurar',
    'desactivar'     => 'aud-ocultar',
    'nuevo_pin'      => 'aud-editar',
    'toggle_visibilidad' => 'aud-editar',
    'aprobar'        => 'aud-restaurar',
    'rechazar'       => 'aud-ocultar',
    'agregar_colaborador' => 'aud-editar',
    'quitar_colaborador'  => 'aud-ocultar',
    'generar_reporte'     => 'aud-editar',
    'exportar_reporte'    => 'aud-foto',
    default          => '',
  };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Auditoría · SRCI</title>
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
    <a href="/srci/admin/reportes_generador.php" class="admin-nav-item">📊 Generador</a>
    <a href="/srci/admin/usuarios.php" class="admin-nav-item">👥 Usuarios</a>
    <a href="/srci/admin/tipos.php"    class="admin-nav-item">🏷️ Tipos</a>
    <a href="/srci/admin/categorias.php" class="admin-nav-item">🗂️ Categorías</a>
    <a href="/srci/admin/barrios.php"  class="admin-nav-item">🏙️ Barrios</a>
    <a href="/srci/admin/auditoria.php" class="admin-nav-item activo">🧾 Auditoría</a>
  </aside>

  <main class="admin-main">
    <div class="pagina-encabezado">
      <div>
        <h1>Auditoría</h1>
        <span style="color:var(--color-texto-suave);font-size:.9rem;">
          <?= $total ?> registro<?= $total === 1 ? '' : 's' ?><?= $ver_logins ? ' de inicio de sesión' : '' ?> · quién, cuándo y qué hizo
        </span>
      </div>
      <div style="display:flex;gap:var(--espacio-sm);flex-wrap:wrap;">
        <?php if ($ver_logins): ?>
          <a href="/srci/admin/auditoria.php" class="boton boton-secundario boton-sm">← Ver toda la auditoría</a>
        <?php else: ?>
          <a href="/srci/admin/auditoria.php?ver=logins" class="boton boton-primario boton-sm">🔐 Ver inicios de sesión</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($ver_logins): ?>
      <div class="mensaje mensaje-info" style="margin-bottom:var(--espacio-lg);">
        <span>🔐</span>
        <span>Mostrando solo inicios de sesión (PIN y magic link).</span>
      </div>
    <?php endif; ?>

    <form class="filtros-barra" method="GET" action="/srci/admin/auditoria.php">
      <?php if ($ver_logins): ?><input type="hidden" name="ver" value="logins"><?php endif; ?>
      <div class="campo">
        <label for="f-usuario">Usuario</label>
        <input type="search" id="f-usuario" name="usuario" placeholder="Nombre o email…" value="<?= esc($f_usuario) ?>">
      </div>
      <?php if (!$ver_logins): ?>
        <div class="campo">
          <label for="f-tipo">Tipo de incidencia</label>
          <select id="f-tipo" name="tipo_id">
            <option value="">Todos</option>
            <?php foreach ($tipos_lista as $t): ?>
              <?php $tipo_label = trim(((string)($t['icono'] ?? '')) . ' ' . ((string)($t['nombre'] ?? ''))); ?>
              <option value="<?= (int)$t['id'] ?>" <?= $f_tipo === (int)$t['id'] ? 'selected' : '' ?>>
                <?= esc($tipo_label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="f-accion">Acción</label>
          <select id="f-accion" name="accion">
            <option value="">Todas</option>
            <?php foreach ($acciones_lista as $a): ?>
              <option value="<?= esc((string)$a) ?>" <?= $f_accion === $a ? 'selected' : '' ?>>
                <?= esc(accion_legible((string)$a)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="campo">
        <label for="f-q">Buscar</label>
        <input type="search" id="f-q" name="q" placeholder="Detalle, usuario o acción…" value="<?= esc($f_q) ?>">
      </div>
      <div style="display:flex;gap:var(--espacio-sm);align-items:flex-end;">
        <button type="submit" class="boton boton-primario">Filtrar</button>
        <a href="/srci/admin/auditoria.php<?= $ver_logins ? '?ver=logins' : '' ?>" class="boton boton-secundario">Limpiar</a>
      </div>
    </form>

    <div class="tabla-contenedor">
      <table class="tabla-incidencias">
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha y hora</th>
            <th>Usuario</th>
            <th>Acción</th>
            <th>Incidencia</th>
            <th>Detalle</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($registros as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td style="white-space:nowrap;"><?= esc(fecha_legible($r['fecha_hora'])) ?></td>
              <td><?= esc($r['usuario'] ?? '—') ?></td>
              <td><span class="aud-badge <?= clase_accion($r['accion']) ?>"><?= esc(accion_legible($r['accion'])) ?></span></td>
              <td>
                <?php if ($r['incidencia_id']): ?>
                  <a href="/srci/detalle.php?id=<?= (int)$r['incidencia_id'] ?>">#<?= (int)$r['incidencia_id'] ?></a>
                  <?php $tipo_txt = trim(((string)($r['tipo_icono'] ?? '')) . ' ' . ((string)($r['tipo_nombre'] ?? ''))); ?>
                  <?php if ($tipo_txt !== ''): ?>
                    <div style="color:var(--color-texto-suave);font-size:.78rem;"><?= esc($tipo_txt) ?></div>
                  <?php endif; ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= esc($r['detalle'] ?? '') ?>"><?= esc($r['detalle'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pags > 1): ?>
      <nav class="paginacion">
        <?php if ($pagina > 1): ?>
          <a href="<?= esc($url_pagina($pagina - 1)) ?>" class="pagina-enlace">‹</a>
        <?php endif; ?>
        <?php for ($p = max(1, $pagina - 2); $p <= min($total_pags, $pagina + 2); $p++): ?>
          <a href="<?= esc($url_pagina($p)) ?>" class="pagina-enlace <?= $p === $pagina ? 'activa' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($pagina < $total_pags): ?>
          <a href="<?= esc($url_pagina($pagina + 1)) ?>" class="pagina-enlace">›</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
