<?php
require_once __DIR__ . '/../db.php';

if (isset($_GET['id'])) {
    $st = db()->prepare('SELECT p.*, c.nombre AS categoria, c.slug AS categoria_slug
        FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE p.id = ? AND p.activo = 1');
    $st->execute([(int) $_GET['id']]);
    $row = $st->fetch();
    json_out($row ?: ['error' => 'no encontrado'], $row ? 200 : 404);
}

$sql = 'SELECT p.*, c.nombre AS categoria, c.slug AS categoria_slug
    FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.activo = 1';
$params = [];

if (isset($_GET['destacados'])) {
    $sql .= ' AND p.destacado = 1';
}

if (!empty($_GET['categoria'])) {
    $sql .= ' AND c.slug = ?';
    $params[] = $_GET['categoria'];
}

$sql .= ' ORDER BY p.posicion, p.id';

$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

foreach ($rows as &$p) {
    $p['precio_txt'] = '$' . number_format((float) $p['precio'], 0, ',', '.');
    $p['imagen_url'] = 'uploads/productos/' . $p['imagen'];
}
unset($p);

json_out($rows);