<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requiere_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respuesta_json(['error' => 'Metodo no permitido.'], 405);
}

$datos         = json_decode(file_get_contents('php://input'), true);
$incidencia_id = (int)($datos['incidencia_id'] ?? 0);
$estado        = $datos['estado'] ?? '';
$estados_validos = ['pendiente', 'en_proceso', 'resuelto'];

if ($incidencia_id === 0 || !in_array($estado, $estados_validos, true)) {
  respuesta_json(['error' => 'Datos invalidos.'], 400);
}

$stmt = db()->prepare('UPDATE incidencias SET estado = :estado WHERE id = :id');
$stmt->execute([':estado' => $estado, ':id' => $incidencia_id]);

respuesta_json(['ok' => true]);
