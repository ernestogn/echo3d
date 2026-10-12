<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Barrios activos con los referentes asignados (nombres de usuarios activos).
// 'referentes' lo usa el formulario de reporte para precargar el contacto.
requiere_sesion();

$stmt = db()->query("
  SELECT b.id, b.nombre,
         (SELECT GROUP_CONCAT(u.nombre ORDER BY u.nombre SEPARATOR ', ')
            FROM referentes_barrios rb
            JOIN usuarios u ON u.id = rb.usuario_id
           WHERE rb.barrio_id = b.id AND u.activo = 1) AS referentes
    FROM barrios b
   WHERE b.activo = 1
   ORDER BY b.nombre");
respuesta_json($stmt->fetchAll());
