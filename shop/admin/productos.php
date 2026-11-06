<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_helpers.php';

$categorias = db()->query('SELECT id, nombre FROM categorias ORDER BY posicion, id')->fetchAll();

$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isNuevo = isset($_GET['nuevo']);

if (($_GET['accion'] ?? null) === 'borrar' && $editId) {
    db()->prepare('DELETE FROM productos WHERE id = ?')->execute([$editId]);
    header('Location: productos.php?msg=' . urlencode('Producto eliminado'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $categoria_id = ($_POST['categoria_id'] ?? '') !== '' ? (int) $_POST['categoria_id'] : null;
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = validar_precio($_POST['precio'] ?? '0');
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $posicion = (int) ($_POST['posicion'] ?? 0);
    $id = isset($_POST['id']) ? (int) $_POST['id'] : null;

    if ($nombre === '') {
        header('Location: productos.php?err=' . urlencode('El nombre es obligatorio'));
        exit;
    }

    $imagen = subir_imagen();
    if (!$imagen && !empty(trim($_POST['imagen_manual'] ?? ''))) {
        $imagen = strtolower(preg_replace('/[^a-z0-9.\-_]+/', '-', basename(trim($_POST['imagen_manual']))));
    }

    if ($id) {
        if ($imagen !== null) {
            db()->prepare('UPDATE productos SET nombre=?, categoria_id=?, descripcion=?, precio=?, destacado=?, activo=?, posicion=?, imagen=? WHERE id=?')
                ->execute([$nombre, $categoria_id, $descripcion, $precio, $destacado, $activo, $posicion, $imagen, $id]);
        } else {
            db()->prepare('UPDATE productos SET nombre=?, categoria_id=?, descripcion=?, precio=?, destacado=?, activo=?, posicion=? WHERE id=?')
                ->execute([$nombre, $categoria_id, $descripcion, $precio, $destacado, $activo, $posicion, $id]);
        }
        header('Location: productos.php?msg=' . urlencode('Producto actualizado'));
        exit;
    }

    db()->prepare('INSERT INTO productos (nombre, slug, categoria_id, descripcion, precio, destacado, activo, imagen, posicion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$nombre, slugify($nombre), $categoria_id, $descripcion, $precio, $destacado, $activo, $imagen ?? 'placeholder.svg', $posicion]);
    header('Location: productos.php?msg=' . urlencode('Producto creado'));
    exit;
}

$p = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM productos WHERE id = ?');
    $st->execute([$editId]);
    $p = $st->fetch();
}

$rows = db()->query('SELECT p.*, c.nombre AS categoria FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id ORDER BY p.posicion, p.id')->fetchAll();
$total = count($rows);

$adminPage = 'productos';
require __DIR__ . '/_layout.php';
$q = pedido();
?>
<?php if ($q['ok']): ?><div class="admin-msg ok"><?= htmlspecialchars($q['ok']) ?></div><?php endif; ?>
<?php if ($q['err']): ?><div class="admin-msg err"><?= htmlspecialchars($q['err']) ?></div><?php endif; ?>

<?php if ($isNuevo || $p): ?>
<div class="admin-card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
    <h2><?= $p ? 'Editar producto' : 'Nuevo producto' ?></h2>
    <a class="admin-btn" href="productos.php">← Volver a la lista</a>
  </div>
  <form class="admin-form" method="post" action="productos.php" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $p['id'] ?? '' ?>">
    <div class="fila">
      <label>Nombre *
        <input type="text" name="nombre" required value="<?= htmlspecialchars($p['nombre'] ?? '') ?>">
      </label>
      <label>Categoría
        <select name="categoria_id">
          <option value="">— Sin categoría —</option>
          <?php foreach ($categorias as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (int)($p['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label>Descripción
      <textarea name="descripcion" rows="3"><?= htmlspecialchars($p['descripcion'] ?? '') ?></textarea>
    </label>
    <div class="fila">
      <label>Precio (AR$)
        <input type="text" name="precio" value="<?= isset($p) && $p ? number_format((float)$p['precio'], 0, '', '.') : '' ?>" placeholder="18000">
      </label>
      <label>Posición (orden)
        <input type="number" name="posicion" value="<?= $p['posicion'] ?? 0 ?>" min="0">
      </label>
    </div>
    <div class="fila">
      <?php if ($p): ?><label>Imagen actual
        <span style="font-weight:400;text-transform:none;display:flex;align-items:center;gap:8px"><img class="admin-thumb" src="../uploads/productos/<?= htmlspecialchars($p['imagen'] ?? 'placeholder.svg') ?>" alt=""> <?= htmlspecialchars($p['imagen'] ?? '') ?></span>
      </label><?php endif; ?>
      <label>Subir imagen nueva
        <input type="file" name="imagen" accept=".svg,.png,.jpg,.jpeg,.webp,.gif">
      </label>
    </div>
    <label>… o escribir nombre de archivo (debe estar en uploads/productos/)
      <input type="text" name="imagen_manual" value="" placeholder="mi-imagen.svg">
    </label>
    <div style="display:flex;gap:18px;flex-wrap:wrap">
      <label style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="destacado" style="width:auto" <?= !empty($p['destacado']) ? 'checked' : '' ?>> Destacado</label>
      <label style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="activo" style="width:auto" <?= !$p || !empty($p['activo']) ? 'checked' : '' ?>> Activo</label>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit"><?= $p ? 'Guardar cambios' : 'Crear producto' ?></button>
      <a class="admin-btn" href="productos.php">Cancelar</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="admin-card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
    <h2>Productos (<?= $total ?>)</h2>
    <a class="btn btn-primary" href="productos.php?nuevo=1" style="font-size:.85rem;padding:9px 16px">+ Nuevo</a>
  </div>
  <table class="admin-table">
    <tr><th></th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Dest.</th><th>Act.</th><th>Acciones</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><img class="admin-thumb" src="../uploads/productos/<?= htmlspecialchars($r['imagen'] ?? 'placeholder.svg') ?>" alt=""></td>
      <td><b><?= htmlspecialchars($r['nombre']) ?></b></td>
      <td><?= htmlspecialchars($r['categoria'] ?? '—') ?></td>
      <td>$<?= number_format((float)$r['precio'], 0, ',', '.') ?></td>
      <td><?= $r['destacado'] ? '<span class="pill si">sí</span>' : '<span class="pill no">no</span>' ?></td>
      <td><?= $r['activo'] ? '<span class="pill si">sí</span>' : '<span class="pill no">no</span>' ?></td>
      <td>
        <div class="admin-actions">
          <a class="admin-btn" href="productos.php?id=<?= $r['id'] ?>">Editar</a>
          <a class="admin-btn peligro" href="productos.php?accion=borrar&id=<?= $r['id'] ?>" onclick="return confirm('¿Eliminar «<?= htmlspecialchars($r['nombre']) ?>»?')">Borrar</a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php require __DIR__ . '/_foot.php'; ?>