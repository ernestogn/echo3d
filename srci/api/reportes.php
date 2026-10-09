<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiere_sesion();

$metodo = $_SERVER['REQUEST_METHOD'];

// GET: listar incidencias (para el mapa y para la tabla)
if ($metodo === 'GET') {
  $donde  = [];
  $params = [];

  if (!empty($_GET['tipo_id'])) {
    $donde[]              = 'i.tipo_id = :tipo_id';
    $params[':tipo_id']   = (int)$_GET['tipo_id'];
  }
  if (!empty($_GET['estado'])) {
    $donde[]              = 'i.estado = :estado';
    $params[':estado']    = $_GET['estado'];
  }
  if (!empty($_GET['fecha_desde'])) {
    $donde[]              = 'i.fecha_hora >= :fecha_desde';
    $params[':fecha_desde'] = $_GET['fecha_desde'] . ' 00:00:00';
  }
  if (!empty($_GET['fecha_hasta'])) {
    $donde[]              = 'i.fecha_hora <= :fecha_hasta';
    $params[':fecha_hasta'] = $_GET['fecha_hasta'] . ' 23:59:59';
  }

  $clausula_where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

  // Para el mapa no paginamos (devolvemos todo, max 1000)
  $sql = "SELECT i.id, i.latitud, i.longitud, i.estado, i.fecha_hora, i.notas,
                 t.nombre AS tipo_nombre, t.icono AS tipo_icono, t.clave AS tipo_clave,
                 u.nombre AS usuario_nombre,
                 (SELECT ruta_archivo FROM fotos WHERE incidencia_id = i.id ORDER BY id LIMIT 1) AS foto
          FROM incidencias i
          JOIN tipos_incidencia t ON t.id = i.tipo_id
          JOIN usuarios u         ON u.id = i.usuario_id
          {$clausula_where}
          ORDER BY i.fecha_hora DESC
          LIMIT 1000";

  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  respuesta_json($stmt->fetchAll());
}

// POST: crear nueva incidencia
if ($metodo === 'POST') {
  $datos = json_decode(file_get_contents('php://input'), true);

  $tipo_id  = (int)($datos['tipo_id']  ?? 0);
  $latitud  = (float)($datos['latitud']  ?? 0);
  $longitud = (float)($datos['longitud'] ?? 0);
  $notas    = trim($datos['notas'] ?? '');

  if ($tipo_id === 0 || $latitud === 0.0 || $longitud === 0.0) {
    respuesta_json(['error' => 'Faltan datos obligatorios.'], 400);
  }

  // Verificar que el tipo exista y este activo
  $st = db()->prepare('SELECT id FROM tipos_incidencia WHERE id = :id AND activo = 1');
  $st->execute([':id' => $tipo_id]);
  if (!$st->fetch()) {
    respuesta_json(['error' => 'Tipo de incidencia no valido.'], 400);
  }

  $stmt = db()->prepare(
    'INSERT INTO incidencias (usuario_id, tipo_id, latitud, longitud, notas)
     VALUES (:usuario_id, :tipo_id, :latitud, :longitud, :notas)'
  );
  $stmt->execute([
    ':usuario_id' => $_SESSION['usuario_id'],
    ':tipo_id'    => $tipo_id,
    ':latitud'    => $latitud,
    ':longitud'   => $longitud,
    ':notas'      => $notas !== '' ? $notas : null,
  ]);
  $id = (int)db()->lastInsertId();
  respuesta_json(['ok' => true, 'id' => $id], 201);
}

respuesta_json(['error' => 'Metodo no permitido.'], 405);
