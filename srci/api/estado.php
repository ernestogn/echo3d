<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

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

// Estado anterior para el log de auditoria
$st = db()->prepare('SELECT estado FROM incidencias WHERE id = :id');
$st->execute([':id' => $incidencia_id]);
$estado_anterior = (string)($st->fetchColumn() ?: 'sin estado');

// Al resolver se guarda la fecha de resolucion; al volver a otro estado se limpia
$sql = $estado === 'resuelto'
  ? 'UPDATE incidencias SET estado = :estado, fecha_resolucion = NOW() WHERE id = :id'
  : 'UPDATE incidencias SET estado = :estado, fecha_resolucion = NULL WHERE id = :id';

$stmt = db()->prepare($sql);
$stmt->execute([':estado' => $estado, ':id' => $incidencia_id]);

// Log de auditoria: quien cambio el estado
registrar_auditoria((int)$_SESSION['usuario_id'], 'cambiar_estado', $incidencia_id, "Estado: {$estado_anterior} → {$estado}");

// Espejo en Google Sheets: actualizar estado y fecha de resolucion de esa fila
enviar_a_sheets([
  'accion'           => 'estado',
  'id'               => $incidencia_id,
  'estado'           => $estado,
  'fecha_resolucion' => $estado === 'resuelto' ? date('Y-m-d H:i:s') : null,
]);

respuesta_json(['ok' => true]);
