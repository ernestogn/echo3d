<?php
require_once __DIR__ . '/../db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['error' => 'Método no permitido'], 405);
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');

if ($nombre === '' || $mensaje === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['error' => 'Completá nombre, email válido y mensaje'], 422);
}

$st = db()->prepare('INSERT INTO mensajes_contacto (nombre, email, telefono, mensaje, ip) VALUES (?, ?, ?, ?, ?)');
$st->execute([$nombre, $email, $telefono ?: null, $mensaje, $_SERVER['REMOTE_ADDR'] ?? null]);

notificar_contacto([
    'nombre' => $nombre,
    'email' => $email,
    'telefono' => $telefono,
    'mensaje' => $mensaje,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
]);

json_out(['ok' => true, 'message' => '¡Mensaje recibido! Te respondemos a la brevedad.']);