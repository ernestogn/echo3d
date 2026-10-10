<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/funciones.php';

// Entrada desde la planilla (Apps Script -> app). Auth: secreto compartido.
// Accion 'importar': crea una incidencia desde una fila de la hoja sin tag.
// NO dispara el espejo (la fila ya esta en la hoja).

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respuesta_json(['error' => 'Metodo no permitido.'], 405);
}

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
if (!defined('SRCI_SHEETS_SECRET') || SRCI_SHEETS_SECRET === '' || ($datos['secreto'] ?? '') !== SRCI_SHEETS_SECRET) {
  respuesta_json(['error' => 'No autorizado.'], 401);
}
if (($datos['accion'] ?? '') !== 'importar') {
  respuesta_json(['error' => 'Accion desconocida.'], 400);
}

$fila = (int)($datos['fila'] ?? 0);

// Sanitizar sin rechazar: los referentes cargan con datos incompletos.
// Todo campo es opcional; lo que falte queda NULL/vacio y se completa despues.

// Tipo por nombre (collation ci); si no existe -> 'Otro'
$st = db()->prepare('SELECT id FROM tipos_incidencia WHERE nombre = :n LIMIT 1');
$st->execute([':n' => trim((string)($datos['tipo'] ?? ''))]);
$tipo_id = (int)($st->fetchColumn() ?: 0);
if ($tipo_id === 0) {
  $tipo_id = (int)db()->query("SELECT id FROM tipos_incidencia WHERE clave = 'otro' LIMIT 1")->fetchColumn();
}
if ($tipo_id === 0) {
  respuesta_json(['ok' => false, 'error' => 'Tipo no encontrado.'], 422);
}

// Barrio por nombre; si no existe se crea (la planilla es la fuente)
$barrio_nombre = trim((string)($datos['barrio'] ?? ''));
$barrio_id = null;
if ($barrio_nombre !== '') {
  $st = db()->prepare('SELECT id FROM barrios WHERE nombre = :n LIMIT 1');
  $st->execute([':n' => $barrio_nombre]);
  $encontrado = $st->fetchColumn(); // una sola llamada: consume el cursor
  $barrio_id = $encontrado ? (int)$encontrado : null;
  if ($barrio_id === null) {
    $st = db()->prepare('INSERT INTO barrios (nombre) VALUES (:n)');
    $st->execute([':n' => $barrio_nombre]);
    $barrio_id = (int)db()->lastInsertId();
  }
}

// "Relevado por" -> usuario por nombre O email (los referentes son los mismos); fallback 'Planilla'
$relevado   = trim((string)($datos['relevado_por'] ?? ''));
$usuario_id = 0;
if ($relevado !== '') {
  $st = db()->prepare('SELECT id FROM usuarios WHERE nombre = :q OR email = :q LIMIT 1');
  $st->execute([':q' => $relevado]);
  $usuario_id = (int)($st->fetchColumn() ?: 0);
}
if ($usuario_id === 0) {
  $st = db()->query("SELECT id FROM usuarios WHERE nombre = 'Planilla' LIMIT 1");
  $usuario_id = (int)($st->fetchColumn() ?: 0);
  if ($usuario_id === 0) {
    $st = db()->prepare("INSERT INTO usuarios (nombre, email, pin_hash, rol, activo) VALUES ('Planilla', NULL, :h, 'usuario', 0)");
    $st->execute([':h' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $usuario_id = (int)db()->lastInsertId();
  }
}

// Mapeos planilla -> app
$map_gravedad = ['baja' => 'baja', 'media' => 'media', 'alta' => 'alta', 'critica' => 'critica', 'crítica' => 'critica'];
$map_estado   = ['pendiente' => 'pendiente', 'en gestión' => 'en_proceso', 'en gestion' => 'en_proceso', 'resuelto' => 'resuelto'];

$gravedad = $map_gravedad[mb_strtolower(trim((string)($datos['gravedad'] ?? '')))] ?? 'media';
$estado   = $map_estado[mb_strtolower(trim((string)($datos['estado'] ?? '')))] ?? 'pendiente';

$servicios = ['Luz', 'Agua', 'Luz y agua', 'Ninguno', 'Vialidad', 'Otro'];
$servicio  = trim((string)($datos['servicio'] ?? ''));
foreach ($servicios as $s) {
  if (mb_strtolower($servicio) === mb_strtolower($s)) { $servicio = $s; break; }
}
if (!in_array($servicio, $servicios, true)) {
  $servicio = null;
}

$calle_str = trim((string)($datos['calle'] ?? ''));
$calle = null;
if (in_array($calle_str, ['Sí', 'Si', 'si', 'sí', '1'], true)) {
  $calle = 1;
} elseif (in_array($calle_str, ['No', 'no', '0'], true)) {
  $calle = 0;
}

$familias = (($datos['familias'] ?? '') !== '' && is_numeric($datos['familias'])) ? (int)$datos['familias'] : null;

// Fecha + hora de la fila; si no se puede parsear -> ahora
$ts = strtotime(trim((string)($datos['fecha'] ?? '')) . ' ' . trim((string)($datos['hora'] ?? '')));
$fecha_hora = $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');

// Fecha de resolucion (solo si venia resuelto con fecha)
$fr = trim((string)($datos['fecha_resolucion'] ?? ''));
$fecha_resolucion = $fr !== '' && strtotime($fr) ? date('Y-m-d H:i:s', strtotime($fr)) : ($estado === 'resuelto' ? $fecha_hora : null);

// Sin coordenadas en la planilla: quedan NULL y NO aparecen en el mapa
// hasta que alguien las ubique desde editar.php
$lat = null;
$lng = null;
$notas = trim((string)($datos['notas'] ?? ''));
$sufijo = '[importado de la planilla, fila ' . $fila . ']';
$notas = $notas !== '' ? $notas . ' ' . $sufijo : $sufijo;

try {
  $stmt = db()->prepare(
    'INSERT INTO incidencias (usuario_id, tipo_id, latitud, longitud, barrio_id, direccion,
                              gravedad, familias_afectadas, servicio_afectado, calle_intransitable,
                              responsable_area, contacto_vecino, notas, fecha_hora, estado, fecha_resolucion)
     VALUES (:usuario_id, :tipo_id, :lat, :lng, :barrio_id, :direccion,
             :gravedad, :familias, :servicio, :calle,
             :responsable, :contacto, :notas, :fecha_hora, :estado, :fecha_resolucion)'
  );
  $stmt->execute([
    ':usuario_id'       => $usuario_id,
    ':tipo_id'          => $tipo_id,
    ':lat'              => $lat,
    ':lng'              => $lng,
    ':barrio_id'        => $barrio_id,
    ':direccion'        => mb_substr($direccion, 0, 255),
    ':gravedad'         => $gravedad,
    ':familias'         => $familias,
    ':servicio'         => $servicio,
    ':calle'            => $calle,
    ':responsable'      => trim((string)($datos['responsable'] ?? '')) ?: null,
    ':contacto'         => mb_substr(trim((string)($datos['contacto'] ?? '')), 0, 120) ?: null,
    ':notas'            => mb_substr($notas, 0, 65535),
    ':fecha_hora'       => $fecha_hora,
    ':estado'           => $estado,
    ':fecha_resolucion' => $fecha_resolucion,
  ]);
} catch (PDOException $e) {
  respuesta_json(['ok' => false, 'error' => 'Error al guardar: ' . $e->getMessage()], 500);
}

$id = (int)db()->lastInsertId();

registrar_auditoria(
  $usuario_id,
  'crear',
  $id,
  'Importado de la planilla (fila ' . $fila . ')' . ($relevado !== '' ? ' — relevado por ' . $relevado : '')
);

respuesta_json(['ok' => true, 'id' => $id]);
