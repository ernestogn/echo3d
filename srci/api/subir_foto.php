<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiere_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respuesta_json(['error' => 'Metodo no permitido.'], 405);
}

$incidencia_id = (int)($_POST['incidencia_id'] ?? 0);

if ($incidencia_id === 0) {
  respuesta_json(['error' => 'Falta el ID de la incidencia.'], 400);
}

// Verificar que la incidencia pertenece al usuario en sesion (o es admin)
$stmt = db()->prepare('SELECT usuario_id FROM incidencias WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $incidencia_id]);
$inc = $stmt->fetch();

if (!$inc) {
  respuesta_json(['error' => 'Incidencia no encontrada.'], 404);
}
if ($inc['usuario_id'] !== $_SESSION['usuario_id'] && $_SESSION['rol'] !== 'admin') {
  respuesta_json(['error' => 'No tenes permiso para modificar esta incidencia.'], 403);
}

if (empty($_FILES['foto'])) {
  respuesta_json(['error' => 'No se recibio ningun archivo.'], 400);
}

try {
  $nombre_archivo = guardar_foto($_FILES['foto']);
} catch (RuntimeException $e) {
  respuesta_json(['error' => $e->getMessage()], 422);
}

$stmt = db()->prepare('INSERT INTO fotos (incidencia_id, ruta_archivo) VALUES (:incidencia_id, :ruta)');
$stmt->execute([':incidencia_id' => $incidencia_id, ':ruta' => $nombre_archivo]);

respuesta_json(['ok' => true, 'ruta' => UPLOADS_URL . $nombre_archivo], 201);
