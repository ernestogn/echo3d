<?php
// Acceso por magic link: valida el token firmado y loguea sin PIN.
// Publico: la autenticacion es el token mismo (HMAC + vencimiento).
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funciones.php';

iniciar_sesion();

$token = trim($_GET['t'] ?? '');
$uid   = $token !== '' ? validar_magic_token($token) : null;

// Pagina de error simple (mismo estilo que login)
function acceso_error(string $titulo, string $mensaje): never
{
  http_response_code(403);
  echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
     . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
     . '<title>Acceso — SRCI</title>'
     . '<link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261010e"></head>'
     . '<body><div class="login-pantalla"><div class="login-caja"><div class="tarjeta" style="text-align:center;">'
     . '<div style="font-size:44px;line-height:1;">🔒</div>'
     . '<h1 style="font-size:1.15rem;margin:var(--espacio-md) 0;">' . esc($titulo) . '</h1>'
     . '<p style="color:var(--color-texto-suave);line-height:1.7;">' . esc($mensaje) . '</p>'
     . '<a href="/srci/login.php" class="boton boton-primario boton-bloque" style="margin-top:var(--espacio-lg);">Ir a ingresar</a>'
     . '</div></div></div></body></html>';
  exit;
}

if ($uid === null) {
  acceso_error('Enlace no válido', 'Este enlace de acceso es inválido o ya expiró. Pedile a un organizador que te envíe uno nuevo.');
}

$stmt = db()->prepare('SELECT id, nombre, rol, activo FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $uid]);
$usuario = $stmt->fetch();

if (!$usuario || !$usuario['activo']) {
  acceso_error('Cuenta inactiva', 'Tu cuenta todavía no fue activada. Avisale a un organizador y te habilitamos el acceso.');
}

session_regenerate_id(true);
$_SESSION['usuario_id'] = (int)$usuario['id'];
$_SESSION['nombre']     = $usuario['nombre'];
$_SESSION['rol']        = $usuario['rol'];

registrar_auditoria((int)$usuario['id'], 'login_link', null, 'Ingreso por magic link');

header('Location: /srci/index.php');
exit;
