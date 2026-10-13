<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

// Borra una foto de una incidencia: registro en DB + archivo en /uploads.
// Permiso: el dueño de la incidencia o un admin (igual que subir_foto.php).
// POST /srci/api/borrar_foto.php  JSON: {foto_id: int}
//   -> {ok:true} | {error:"..."}
requiere_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respuesta_json(['error' => 'Metodo no permitido.'], 405);
}

$datos = json_decode((string)file_get_contents('php://input'), true);
$foto_id = (int)($datos['foto_id'] ?? 0);
if ($foto_id <= 0) {
  respuesta_json(['error' => 'Falta el ID de la foto.'], 400);
}

$stmt = db()->prepare('
  SELECT f.id, f.incidencia_id, f.ruta_archivo, i.usuario_id
    FROM fotos f
    JOIN incidencias i ON i.id = f.incidencia_id
   WHERE f.id = :id
   LIMIT 1');
$stmt->execute([':id' => $foto_id]);
$foto = $stmt->fetch();

if (!$foto) {
  respuesta_json(['error' => 'La foto no existe.'], 404);
}
if ((int)$foto['usuario_id'] !== (int)$_SESSION['usuario_id'] && ($_SESSION['rol'] ?? '') !== 'admin') {
  respuesta_json(['error' => 'No tenes permiso para borrar esta foto.'], 403);
}

// Borrar el archivo del disco: basename nunca confia en la ruta guardada
$ruta_segura = basename((string)$foto['ruta_archivo']);
$abs = dirname(__DIR__) . '/uploads/' . $ruta_segura;
if (is_file($abs)) {
  @unlink($abs);
}

db()->prepare('DELETE FROM fotos WHERE id = :id')->execute([':id' => $foto_id]);

registrar_auditoria(
  (int)$_SESSION['usuario_id'],
  'borrar_foto',
  (int)$foto['incidencia_id'],
  'Borro una foto (' . $ruta_segura . ')'
);

// Nota: NO se avisa a Google Sheets al borrar (el espejo conserva el link
// historico de la fila; la app es la fuente de verdad del listado actual).

respuesta_json(['ok' => true]);
