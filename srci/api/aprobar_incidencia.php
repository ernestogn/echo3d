<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

// Aprobar o rechazar un reporte pendiente de la red de colaboradores.
// Reglas:
//  - la vinculacion colaborador-propietario nace aprobada; lo que se aprueba es CADA reporte
//  - aprobar  -> aprobado = 1 (se publica)
//  - rechazar -> oculto = 1 (soft delete: nunca se borra fisico)
//  - en el modo actual la gestion es solo de admins (SRCI_COLABORADORES_SOLO_ADMIN)

requiere_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respuesta_json(['error' => 'Metodo no permitido.'], 405);
}

$datos         = json_decode(file_get_contents('php://input'), true);
$datos         = is_array($datos) ? $datos : [];
$incidencia_id = (int)($datos['incidencia_id'] ?? 0);
$accion        = (string)($datos['accion'] ?? '');
$motivo        = trim((string)($datos['motivo'] ?? ''));
if (mb_strlen($motivo) > 500) {
  $motivo = mb_substr($motivo, 0, 500);
}

// Token CSRF: por header X-CSRF-Token o dentro del cuerpo JSON
$token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($datos['csrf_token'] ?? ''));
if (!csrf_token_valido($token)) {
  respuesta_json(['error' => 'Token de seguridad invalido. Recarga la pagina e intenta de nuevo.'], 403);
}

// Quien puede aprobar/rechazar
$uid      = (int)$_SESSION['usuario_id'];
$es_admin = es_admin();
if (!puede_gestionar_aprobaciones($uid)) {
  respuesta_json(['error' => 'No tenés permiso para aprobar reportes.'], 403);
}
if (!in_array($accion, ['aprobar', 'rechazar'], true)) {
  respuesta_json(['error' => 'Accion invalida.'], 400);
}
if ($incidencia_id === 0) {
  respuesta_json(['error' => 'Incidencia invalida.'], 400);
}
// El rechazo se muestra al autor: sin motivo no tiene sentido rechazarlo.
if ($accion === 'rechazar' && $motivo === '') {
  respuesta_json(['error' => 'Tenés que escribir el motivo del rechazo: lo va a ver quien reportó.'], 400);
}

// La incidencia debe existir
$st = db()->prepare('SELECT id, usuario_id, propietario_id, aprobado, rechazado, oculto, direccion FROM incidencias WHERE id = :id LIMIT 1');
$st->execute([':id' => $incidencia_id]);
$inc = $st->fetch();
if (!$inc) {
  respuesta_json(['error' => 'Incidencia no encontrada.'], 404);
}

// Si no es admin, solo puede gestionar los reportes de su propio grupo
if (!$es_admin) {
  $grupo = ids_grupo_propietario($uid);
  if (!in_array((int)$inc['usuario_id'], $grupo, true) && !in_array((int)$inc['propietario_id'], $grupo, true)) {
    respuesta_json(['error' => 'Ese reporte no es de tu grupo.'], 403);
  }
}

// Un reporte ya publicado no se vuelve a aprobar: primero hay que ocultarlo
if ((int)$inc['aprobado'] === 1) {
  respuesta_json(['error' => 'Ese reporte ya está aprobado.'], 400);
}

if ($accion === 'aprobar') {
  db()->prepare(
    'UPDATE incidencias
        SET aprobado = 1, aprobado_en = NOW(), aprobado_por = :uid,
            rechazado = 0, rechazado_en = NULL, rechazado_por = NULL, motivo_rechazo = NULL
      WHERE id = :id'
  )->execute([':uid' => $uid, ':id' => $incidencia_id]);
  registrar_auditoria($uid, 'aprobar', $incidencia_id, 'Aprobó la publicación del reporte');
  notificar_creador_por_email($incidencia_id, 'aprobar');
  respuesta_json(['ok' => true, 'accion' => 'aprobar', 'id' => $incidencia_id]);
}

// Rechazar = ocultar (soft delete) + queda el motivo para el autor.
// El autor lo ve en su perfil con el motivo del rechazo.
db()->prepare(
  'UPDATE incidencias
      SET aprobado = 0, oculto = 1,
          rechazado = 1, rechazado_en = NOW(), rechazado_por = :uid, motivo_rechazo = :motivo
    WHERE id = :id'
)->execute([':uid' => $uid, ':motivo' => $motivo !== '' ? $motivo : null, ':id' => $incidencia_id]);

$detalle = 'Rechazó el reporte (queda oculto, no se borra)';
if ($motivo !== '') {
  $detalle .= ' — Motivo: ' . mb_substr($motivo, 0, 200);
}
registrar_auditoria($uid, 'rechazar', $incidencia_id, $detalle);
notificar_creador_por_email(
  $incidencia_id,
  'rechazar',
  $motivo !== '' ? 'Motivo del rechazo: ' . $motivo : ''
);

respuesta_json(['ok' => true, 'accion' => 'rechazar', 'id' => $incidencia_id]);
