<?php
require_once __DIR__ . '/../db.php';

$rows = db()->query('SELECT id, nombre, slug, icono FROM categorias ORDER BY posicion, id')->fetchAll();
json_out($rows);