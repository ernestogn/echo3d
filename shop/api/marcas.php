<?php
require_once __DIR__ . '/../db.php';

$rows = db()->query('SELECT nombre FROM marcas WHERE activo = 1 ORDER BY posicion, id')->fetchAll();
json_out(['marcas' => array_column($rows, 'nombre')]);