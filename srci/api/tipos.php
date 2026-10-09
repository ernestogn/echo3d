<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requiere_sesion();

$stmt = db()->query('SELECT id, clave, nombre, icono FROM tipos_incidencia WHERE activo = 1 ORDER BY id');
respuesta_json($stmt->fetchAll());
