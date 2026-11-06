<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_helpers.php';

if (($_GET['accion'] ?? null) === 'leido' && ($id = (int) ($_GET['id'] ?? 0))) {
    db()->prepare('UPDATE mensajes_contacto SET leido = 1 - leido WHERE id = ?')->execute([$id]);
    header('Location: mensajes.php');
    exit;
}

if (($_GET['accion'] ?? null) === 'borrar' && ($id = (int) ($_GET['id'] ?? 0))) {
    db()->prepare('DELETE FROM mensajes_contacto WHERE id = ?')->execute([$id]);
    header('Location: mensajes.php');
    exit;
}

$adminPage = 'mensajes';
require __DIR__ . '/_layout.php';

$rows = db()->query('SELECT * FROM mensajes_contacto ORDER BY id DESC')->fetchAll();
?>
<div class="admin-card">
  <h2>Mensajes de contacto (<?= count($rows) ?>)</h2>
  <?php if (!$rows): ?>
    <p style="color:var(--gris-linea)">No hay mensajes todavía.</p>
  <?php else: ?>
  <table class="admin-table">
    <tr><th>Fecha</th><th>Nombre</th><th>Contacto</th><th>Mensaje</th><th>IP</th><th></th></tr>
    <?php foreach ($rows as $m): ?>
    <tr style="<?= $m['leido'] ? '' : 'background:rgba(198,211,75,.16)' ?>">
      <td style="white-space:nowrap;font-size:.82rem"><?= date('d/m/Y H:i', strtotime($m['creado'])) ?></td>
      <td><b><?= htmlspecialchars($m['nombre']) ?></b> <?= $m['leido'] ? '' : '<span class="pill si">nuevo</span>' ?></td>
      <td style="font-size:.85rem">
        <a href="mailto:<?= htmlspecialchars($m['email']) ?>"><?= htmlspecialchars($m['email']) ?></a>
        <?= $m['telefono'] ? '<br>' . htmlspecialchars($m['telefono']) : '' ?>
      </td>
      <td style="max-width:340px"><?= nl2br(htmlspecialchars($m['mensaje'])) ?></td>
      <td style="font-size:.8rem;font-family:var(--font-mono)"><?= htmlspecialchars($m['ip'] ?? '—') ?></td>
      <td>
        <div class="admin-actions">
          <a class="admin-btn" href="mensajes.php?accion=leido&id=<?= $m['id'] ?>"><?= $m['leido'] ? 'Marcar no leído' : 'Marcar leído' ?></a>
          <a class="admin-btn peligro" href="mensajes.php?accion=borrar&id=<?= $m['id'] ?>" onclick="return confirm('¿Eliminar mensaje?')">Borrar</a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_foot.php'; ?>