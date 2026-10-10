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

  $gravedades_validas = ['baja', 'media', 'alta', 'critica'];

  // Las ocultas no salen ni en el mapa ni en la lista
  $donde[] = 'i.oculto = 0';

  if (!empty($_GET['tipo_id'])) {
    $donde[]              = 'i.tipo_id = :tipo_id';
    $params[':tipo_id']   = (int)$_GET['tipo_id'];
  }
  if (!empty($_GET['estado'])) {
    $donde[]              = 'i.estado = :estado';
    $params[':estado']    = $_GET['estado'];
  }
  if (!empty($_GET['barrio_id'])) {
    $donde[]              = 'i.barrio_id = :barrio_id';
    $params[':barrio_id'] = (int)$_GET['barrio_id'];
  }
  if (!empty($_GET['gravedad']) && in_array($_GET['gravedad'], $gravedades_validas, true)) {
    $donde[]              = 'i.gravedad = :gravedad';
    $params[':gravedad']  = $_GET['gravedad'];
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
  $sql = "SELECT i.id, i.tipo_id, i.usuario_id, i.latitud, i.longitud, i.estado, i.fecha_hora, i.fecha_resolucion, i.notas,
                 i.direccion, i.gravedad, i.familias_afectadas, i.servicio_afectado,
                 i.calle_intransitable, i.responsable_area, i.contacto_vecino,
                 i.barrio_id, b.nombre AS barrio_nombre,
                 t.nombre AS tipo_nombre, t.icono AS tipo_icono, t.clave AS tipo_clave,
                 u.nombre AS usuario_nombre,
                 (SELECT ruta_archivo FROM fotos WHERE incidencia_id = i.id ORDER BY id LIMIT 1) AS foto
          FROM incidencias i
          JOIN tipos_incidencia t ON t.id = i.tipo_id
          JOIN usuarios u         ON u.id = i.usuario_id
          LEFT JOIN barrios b     ON b.id = i.barrio_id
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

  $gravedades_validas   = ['baja', 'media', 'alta', 'critica'];
  $servicios_validos    = ['Luz', 'Agua', 'Luz y agua', 'Ninguno', 'Vialidad', 'Otro'];
  $responsables_validos = ['Cooperativa electrica', 'Cooperativa eléctrica', 'Defensa Civil', 'Municipio', 'Provincia', 'Ninguno', 'Otro'];

  $tipo_id            = (int)($datos['tipo_id'] ?? 0);
  $latitud            = (float)($datos['latitud'] ?? 0);
  $longitud           = (float)($datos['longitud'] ?? 0);
  $barrio_id          = (int)($datos['barrio_id'] ?? 0);
  $direccion          = trim($datos['direccion'] ?? '');
  $gravedad           = $datos['gravedad'] ?? '';
  $notas              = trim($datos['notas'] ?? '');

  // Opcionales
  $familias           = ($datos['familias_afectadas'] ?? '') !== '' ? (int)$datos['familias_afectadas'] : null;
  $servicio           = trim($datos['servicio_afectado'] ?? '');
  $calleIntransitable = isset($datos['calle_intransitable']) ? (!empty($datos['calle_intransitable']) ? 1 : 0) : null;
  $responsable        = trim($datos['responsable_area'] ?? '');
  $contacto           = trim($datos['contacto_vecino'] ?? '');

  if ($tipo_id === 0 || $latitud === 0.0 || $longitud === 0.0 || $barrio_id === 0 || $direccion === '' || !in_array($gravedad, $gravedades_validas, true)) {
    respuesta_json(['error' => 'Faltan datos obligatorios (barrio, dirección y gravedad son obligatorios).'], 400);
  }
  if ($servicio !== '' && !in_array($servicio, $servicios_validos, true)) {
    respuesta_json(['error' => 'Servicio afectado no valido.'], 400);
  }
  if ($responsable !== '' && !in_array($responsable, $responsables_validos, true)) {
    respuesta_json(['error' => 'Responsable / area no valido.'], 400);
  }

  // Verificar que el tipo exista y este activo
  $st = db()->prepare('SELECT id FROM tipos_incidencia WHERE id = :id AND activo = 1');
  $st->execute([':id' => $tipo_id]);
  if (!$st->fetch()) {
    respuesta_json(['error' => 'Tipo de incidencia no valido.'], 400);
  }

  // Verificar que el barrio exista y este activo
  $st = db()->prepare('SELECT id FROM barrios WHERE id = :id AND activo = 1');
  $st->execute([':id' => $barrio_id]);
  if (!$st->fetch()) {
    respuesta_json(['error' => 'Barrio no valido.'], 400);
  }

  try {
    $stmt = db()->prepare(
      'INSERT INTO incidencias (usuario_id, tipo_id, latitud, longitud, barrio_id, direccion,
                                gravedad, familias_afectadas, servicio_afectado, calle_intransitable,
                                responsable_area, contacto_vecino, notas)
       VALUES (:usuario_id, :tipo_id, :latitud, :longitud, :barrio_id, :direccion,
               :gravedad, :familias, :servicio, :calle, :responsable, :contacto, :notas)'
    );
    $stmt->execute([
      ':usuario_id'  => $_SESSION['usuario_id'],
      ':tipo_id'     => $tipo_id,
      ':latitud'     => $latitud,
      ':longitud'    => $longitud,
      ':barrio_id'   => $barrio_id,
      ':direccion'   => mb_substr($direccion, 0, 255),
      ':gravedad'    => $gravedad,
      ':familias'    => $familias,
      ':servicio'    => $servicio !== '' ? $servicio : null,
      ':calle'       => $calleIntransitable,
      ':responsable' => $responsable !== '' ? $responsable : null,
      ':contacto'    => $contacto !== '' ? mb_substr($contacto, 0, 120) : null,
      ':notas'       => $notas !== '' ? $notas : null,
    ]);
  } catch (PDOException $e) {
    // Codigo 1452: FK rota (usuario borrado o sesion vieja)
    if (($e->errorInfo[1] ?? 0) === 1452) {
      respuesta_json(['error' => 'Tu sesion ya no es valida. Volve a iniciar sesion.', 'relogin' => true], 401);
    }
    respuesta_json(['error' => 'No pudimos guardar el reporte.'], 500);
  }
  $id = (int)db()->lastInsertId();

  // Log de auditoria: quien cargo la incidencia
  registrar_auditoria((int)$_SESSION['usuario_id'], 'crear', $id, 'Cargo una incidencia');

  // Espejo en Google Sheets: fila nueva en la planilla de relevamiento
  $st_tipo   = db()->prepare('SELECT nombre FROM tipos_incidencia WHERE id = :id');
  $st_barrio = db()->prepare('SELECT nombre FROM barrios WHERE id = :id');
  enviar_a_sheets([
    'accion'           => 'reporte',
    'id'               => $id,
    'fecha_hora'       => date('Y-m-d H:i:s'),
    'tipo'             => $st_tipo->execute([':id' => $tipo_id]) ? ($st_tipo->fetchColumn() ?: '') : '',
    'barrio'           => $st_barrio->execute([':id' => $barrio_id]) ? ($st_barrio->fetchColumn() ?: '') : '',
    'direccion'        => $direccion,
    'gravedad'         => $gravedad,
    'familias'         => $familias,
    'servicio'         => $servicio,
    'calle'            => $calleIntransitable,
    'estado'           => 'pendiente',
    'responsable'      => $responsable,
    'contacto'         => $contacto,
    'notas'            => $notas,
    'relevado_por'     => $_SESSION['nombre'] ?? '',
    'fecha_resolucion' => null,
    'foto'             => null,
  ]);

  respuesta_json(['ok' => true, 'id' => $id], 201);
}

respuesta_json(['error' => 'Metodo no permitido.'], 405);
