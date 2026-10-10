<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiere_admin();

$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 50;
$offset     = ($pagina - 1) * $por_pagina;

$total      = (int)db()->query('SELECT COUNT(*) FROM auditoria')->fetchColumn();
$total_pags = (int)ceil($total / $por_pagina);

$stmt = db()->prepare(
  'SELECT a.id, a.accion, a.incidencia_id, a.detalle, a.fecha_hora, u.nombre AS usuario
   FROM auditoria a
   LEFT JOIN usuarios u ON u.id = a.usuario_id
   ORDER BY a.id DESC
   LIMIT :lim OFFSET :off'
);
$stmt->bindValue(':lim', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,     PDO::PARAM_INT);
$stmt->execute();
$registros = $stmt->fetchAll();

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
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261010e">
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
    <a href="/srci/admin/reportes.php" class="admin-nav-item activo">📋 Reportes</a>
    <a href="/srci/admin/usuarios.php" class="admin-nav-item">👥 Usuarios</a>
    <a href="/srci/admin/tipos.php"    class="admin-nav-item">🏷️ Tipos</a>
    <a href="/srci/admin/barrios.php"  class="admin-nav-item">🏙️ Barrios</a>
    <a href="/srci/admin/auditoria.php" class="admin-nav-item">🧾 Auditoría</a>
  </aside>

  <main class="admin-main">
    <div class="pagina-encabezado">
      <h1>Auditoría</h1>
      <span style="color:var(--color-texto-suave);font-size:.9rem;"><?= $total ?> registros · quién, cuándo y qué hizo</span>
    </div>

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
          <a href="/srci/admin/auditoria.php?pagina=<?= $pagina - 1 ?>" class="pagina-enlace">‹</a>
        <?php endif; ?>
        <?php for ($p = max(1, $pagina - 2); $p <= min($total_pags, $pagina + 2); $p++): ?>
          <a href="/srci/admin/auditoria.php?pagina=<?= $p ?>" class="pagina-enlace <?= $p === $pagina ? 'activa' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($pagina < $total_pags): ?>
          <a href="/srci/admin/auditoria.php?pagina=<?= $pagina + 1 ?>" class="pagina-enlace">›</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
