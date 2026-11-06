<?php
$adminPage = 'home';
require __DIR__ . '/_layout.php';

$productos = db()->query('SELECT COUNT(*) AS n FROM productos')->fetch()['n'];
$categorias = db()->query('SELECT COUNT(*) AS n FROM categorias')->fetch()['n'];
$marcas = db()->query('SELECT COUNT(*) AS n FROM marcas')->fetch()['n'];
$mensajes = db()->query('SELECT COUNT(*) AS n FROM mensajes_contacto')->fetch()['n'];
$noLeidos = db()->query('SELECT COUNT(*) AS n FROM mensajes_contacto WHERE leido = 0')->fetch()['n'];
$recientes = db()->query('SELECT * FROM mensajes_contacto ORDER BY id DESC LIMIT 5')->fetchAll();
?>
<div class="admin-card">
  <h2>Panel de control</h2>
  <div class="admin-stats">
    <div class="admin-stat"><b><?= $productos ?></b><span>Productos</span></div>
    <div class="admin-stat"><b><?= $categorias ?></b><span>Categorías</span></div>
    <div class="admin-stat"><b><?= $marcas ?></b><span>Materiales</span></div>
    <div class="admin-stat"><b><?= $mensajes ?></b><span>Mensajes</span></div>
    <?php if ($noLeidos > 0): ?>
    <div class="admin-stat" style="background:var(--naranja);color:var(--carbon)"><b><?= $noLeidos ?></b><span>Sin leer</span></div>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card">
  <h2>Últimos mensajes</h2>
  <?php if (!$recientes): ?>
    <p style="color:var(--gris-linea)">Todavía no recibiste mensajes.</p>
  <?php else: ?>
  <table class="admin-table">
    <tr><th>Nombre</th><th>Contacto</th><th>Mensaje</th><th>Fecha</th><th></th></tr>
    <?php foreach ($recientes as $m): ?>
    <tr>
      <td><b><?= htmlspecialchars($m['nombre']) ?></b> <?= $m['leido'] ? '' : '<span class="pill si">nuevo</span>' ?></td>
      <td style="font-size:.85rem"><?= htmlspecialchars($m['email']) ?><?= $m['telefono'] ? '<br>' . htmlspecialchars($m['telefono']) : '' ?></td>
      <td style="max-width:280px"><?= htmlspecialchars(mb_strimwidth($m['mensaje'], 0, 80, '…')) ?></td>
      <td style="white-space:nowrap;font-size:.82rem"><?= date('d/m H:i', strtotime($m['creado'])) ?></td>
      <td><a class="admin-btn" href="mensajes.php">Ver →</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h2>Accesos rápidos</h2>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn btn-primary" href="productos.php?nuevo=1" style="font-size:.85rem;padding:9px 16px">+ Nuevo producto</a>
    <a class="btn btn-secondary" href="categorias.php?nueva=1" style="font-size:.85rem;padding:9px 16px">+ Nueva categoría</a>
    <a class="admin-btn" href="../index.php" target="_blank" rel="noopener" style="padding:9px 16px">Ver la tienda</a>
  </div>
</div>

<?php require __DIR__ . '/_foot.php'; ?>