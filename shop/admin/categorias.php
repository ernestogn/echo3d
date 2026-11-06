<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_helpers.php';

if (($_GET['accion'] ?? null) === 'borrar' && ($id = (int) ($_GET['id'] ?? 0))) {
    db()->prepare('DELETE FROM categorias WHERE id = ?')->execute([$id]);
    header('Location: categorias.php?msg=' . urlencode('Categoría eliminada (sus productos quedaron sin categoría)'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $icono = trim($_POST['icono'] ?? '');
    $posicion = (int) ($_POST['posicion'] ?? 0);
    $id = isset($_POST['id']) ? (int) $_POST['id'] : null;

    if ($nombre === '' || slugify($nombre) === '') {
        header('Location: categorias.php?err=' . urlencode('El nombre es obligatorio'));
        exit;
    }

    $slug = slugify($nombre);

    if ($id) {
        db()->prepare('UPDATE categorias SET nombre=?, slug=?, icono=?, posicion=? WHERE id=?')
            ->execute([$nombre, $slug, $icono ?: null, $posicion, $id]);
        header('Location: categorias.php?msg=' . urlencode('Categoría actualizada'));
        exit;
    }

    try {
        db()->prepare('INSERT INTO categorias (nombre, slug, icono, posicion) VALUES (?, ?, ?, ?)')
            ->execute([$nombre, $slug, $icono ?: null, $posicion]);
        header('Location: categorias.php?msg=' . urlencode('Categoría creada'));
        exit;
    } catch (PDOException $e) {
        header('Location: categorias.php?err=' . urlencode('Ya existe una categoría con ese nombre (slug duplicado)'));
        exit;
    }
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$p = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM categorias WHERE id = ?');
    $st->execute([$editId]);
    $p = $st->fetch();
}

$rows = db()->query('SELECT c.*, COUNT(p.id) AS n FROM categorias c LEFT JOIN productos p ON p.categoria_id = c.id GROUP BY c.id ORDER BY c.posicion, c.id')->fetchAll();

$adminPage = 'categorias';
require __DIR__ . '/_layout.php';
$q = pedido();
?>
<?php if ($q['ok']): ?><div class="admin-msg ok"><?= htmlspecialchars($q['ok']) ?></div><?php endif; ?>
<?php if ($q['err']): ?><div class="admin-msg err"><?= htmlspecialchars($q['err']) ?></div><?php endif; ?>

<?php if (isset($_GET['nueva']) || $p): ?>
<div class="admin-card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
    <h2><?= $p ? 'Editar categoría' : 'Nueva categoría' ?></h2>
    <a class="admin-btn" href="categorias.php">← Volver</a>
  </div>
  <form class="admin-form" method="post" action="categorias.php">
    <input type="hidden" name="id" value="<?= $p['id'] ?? '' ?>">
    <div class="fila">
      <label>Nombre *
        <input type="text" name="nombre" required value="<?= htmlspecialchars($p['nombre'] ?? '') ?>" placeholder="Accesorios">
      </label>
      <label>Ícono (emoji)
        <input type="text" name="icono" value="<?= htmlspecialchars($p['icono'] ?? '') ?>" placeholder="🎁">
      </label>
    </div>
    <label>Posición (orden)
      <input type="number" name="posicion" value="<?= $p['posicion'] ?? 0 ?>" min="0">
    </label>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit"><?= $p ? 'Guardar' : 'Crear categoría' ?></button>
      <a class="admin-btn" href="categorias.php">Cancelar</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="admin-card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
    <h2>Categorías (<?= count($rows) ?>)</h2>
    <a class="btn btn-primary" href="categorias.php?nueva=1" style="font-size:.85rem;padding:9px 16px">+ Nueva</a>
  </div>
  <table class="admin-table">
    <tr><th>Ícono</th><th>Nombre</th><th>Slug</th><th>Productos</th><th>Pos.</th><th>Acciones</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td style="font-size:1.3rem"><?= htmlspecialchars($r['icono'] ?? '—') ?></td>
      <td><b><?= htmlspecialchars($r['nombre']) ?></b></td>
      <td style="font-family:var(--font-mono);font-size:.82rem"><?= htmlspecialchars($r['slug']) ?></td>
      <td><?= $r['n'] ?></td>
      <td><?= $r['posicion'] ?></td>
      <td>
        <div class="admin-actions">
          <a class="admin-btn" href="categorias.php?id=<?= $r['id'] ?>">Editar</a>
          <a class="admin-btn peligro" href="categorias.php?accion=borrar&id=<?= $r['id'] ?>" onclick="return confirm('¿Eliminar «<?= htmlspecialchars($r['nombre']) ?>»?')">Borrar</a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php require __DIR__ . '/_foot.php'; ?>