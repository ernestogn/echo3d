<?php
declare(strict_types=1);

define('MAX_FOTO_BYTES', 5 * 1024 * 1024); // 5 MB
define('UPLOADS_DIR', __DIR__ . '/../uploads/');
define('UPLOADS_URL', '/srci/uploads/');

// MIME types permitidos para fotos
const MIME_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];

// Sanitiza texto para salida HTML
function esc(string $valor): string
{
  return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

// Formatea fecha en formato legible en espanol
function fecha_legible(string $fecha_hora): string
{
  $ts = strtotime($fecha_hora);
  $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  return date('d', $ts) . ' ' . $meses[(int)date('m', $ts) - 1] . ' ' . date('Y H:i', $ts);
}

// Genera un PIN numerico de 4 digitos como string
function generar_pin(): string
{
  return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

// Devuelve el nombre legible del estado de incidencia
function nombre_estado(string $estado): string
{
  return match($estado) {
    'pendiente'  => 'Pendiente',
    'en_proceso' => 'En gestión',
    'resuelto'   => 'Resuelto',
    default      => 'Desconocido',
  };
}

// Devuelve clase CSS segun estado
function clase_estado(string $estado): string
{
  return match($estado) {
    'pendiente'  => 'estado-pendiente',
    'en_proceso' => 'estado-en-proceso',
    'resuelto'   => 'estado-resuelto',
    default      => '',
  };
}

// Devuelve el nombre legible de la gravedad
function nombre_gravedad(?string $gravedad): string
{
  return match($gravedad) {
    'baja'    => 'Baja',
    'media'   => 'Media',
    'alta'    => 'Alta',
    'critica' => 'Crítica',
    default   => 'Sin dato',
  };
}

// Devuelve clase CSS segun gravedad
function clase_gravedad(?string $gravedad): string
{
  return match($gravedad) {
    'baja'    => 'gravedad-baja',
    'media'   => 'gravedad-media',
    'alta'    => 'gravedad-alta',
    'critica' => 'gravedad-critica',
    default   => '',
  };
}

// Valida y guarda una foto subida
// Devuelve la ruta relativa o lanza Exception
function guardar_foto(array $archivo): string
{
  if ($archivo['error'] !== UPLOAD_ERR_OK) {
    throw new RuntimeException('Error al recibir el archivo.');
  }
  if ($archivo['size'] > MAX_FOTO_BYTES) {
    throw new RuntimeException('La foto supera el limite de 5 MB.');
  }

  // Verificar MIME real con finfo (no confiar en $_FILES['type'])
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($archivo['tmp_name']);
  if (!in_array($mime, MIME_PERMITIDOS, true)) {
    throw new RuntimeException('Solo se aceptan imagenes JPG, PNG o WEBP.');
  }

  $extension  = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
  };
  $nombre_seguro = bin2hex(random_bytes(16)) . '.' . $extension;
  $ruta_destino  = UPLOADS_DIR . $nombre_seguro;

  if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
    throw new RuntimeException('No pudimos guardar la foto. Proba de nuevo.');
  }

  return $nombre_seguro;
}

// Envia un email simple con el PIN al usuario
function enviar_pin_por_email(string $email, string $nombre, string $pin): bool
{
  $asunto  = '=?UTF-8?B?' . base64_encode('Tu PIN de acceso — SRCI') . '?=';
  $cuerpo  = "Hola {$nombre},\n\nTu PIN de acceso al Sistema de Reporte Ciudadano es:\n\n"
           . "  {$pin}\n\n"
           . "Ingresa con tu email y este PIN en: https://echo3dlaser.com.ar/srci/\n"
           . "Si no pediste este PIN, ignora este mensaje.\n";
  $headers = implode("\r\n", [
    'From: SRCI <admin@echo3dlaser.com.ar>',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
  ]);
  return @mail($email, $asunto, base64_encode($cuerpo), $headers);
}
