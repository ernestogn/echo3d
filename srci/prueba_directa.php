<?php
declare(strict_types=1);

// Prueba E2E con entrega directa: UN mail por incidente + UN digest, SOLO a Ernesto.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/funciones_notificaciones_admin.php';

$st = db()->prepare("SELECT id, nombre, email FROM usuarios WHERE email = :e LIMIT 1");
$st->execute([':e' => 'ernestogn@gmail.com']);
$admin = $st->fetch();

if (!$admin) {
  echo "No se encontro el admin\n";
  exit(1);
}
echo "Destinatario UNICO: {$admin['nombre']} <{$admin['email']}>\n";

$inc = db()->query(
  "SELECT i.id, i.usuario_id, i.direccion, i.gravedad, i.estado,
          t.nombre AS tipo_nombre, t.icono AS tipo_icono,
          b.nombre AS barrio_nombre, u.nombre AS creador_nombre
   FROM incidencias i
   JOIN tipos_incidencia t ON t.id = i.tipo_id
   LEFT JOIN barrios b ON b.id = i.barrio_id
   JOIN usuarios u ON u.id = i.usuario_id
   WHERE i.aprobado = 1 AND i.oculto = 0
   ORDER BY i.id DESC LIMIT 1"
)->fetch();

if (!$inc) {
  echo "Sin incidencias publicadas\n";
  exit(1);
}
echo "Incidencia de prueba: #{$inc['id']} - {$inc['direccion']}\n";

$ok1 = enviar_email_nuevo_incidente_admin($inc, $admin);
echo 'mail() por incidente: ' . ($ok1 ? 'ACEPTADO' : 'RECHAZADO') . "\n";

// Digest: como no hay incidencias en 24h, se usa ventana de 3 dias para poder verlo
$incs = db()->query(
  "SELECT i.id, i.direccion, i.gravedad, i.estado, i.fecha_hora,
          t.nombre AS tipo_nombre, t.icono AS tipo_icono,
          b.nombre AS barrio_nombre, u.nombre AS creador_nombre
   FROM incidencias i
   JOIN tipos_incidencia t ON t.id = i.tipo_id
   LEFT JOIN barrios b ON b.id = i.barrio_id
   JOIN usuarios u ON u.id = i.usuario_id
   WHERE i.fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     AND i.aprobado = 1 AND i.oculto = 0
   ORDER BY i.fecha_hora DESC"
)->fetchAll();

$ok2 = enviar_email_digest_admin($incs, $admin);
echo 'mail() digest (' . count($incs) . ' incidencias): ' . ($ok2 ? 'ACEPTADO' : 'RECHAZADO') . "\n";

echo "QUERY_ID:" . getmypid() . "\n";
