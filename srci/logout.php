<?php
require_once __DIR__ . '/includes/auth.php';
iniciar_sesion();
$_SESSION = [];
session_destroy();

// Borrar la cookie de sesion
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', [
    'expires'  => time() - 42000,
    'path'     => $params['path'],
    'secure'   => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'],
  ]);
}

header('Location: /srci/login.php');
exit;
