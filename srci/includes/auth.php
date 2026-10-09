<?php
declare(strict_types=1);

// Configurar cookie segura antes de iniciar sesion
function iniciar_sesion(): void
{
  if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',
      'secure'   => isset($_SERVER['HTTPS']),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    session_start();
  }
}

// Verifica si hay sesion activa, redirige al login si no
function requiere_sesion(): void
{
  iniciar_sesion();
  if (empty($_SESSION['usuario_id'])) {
    header('Location: /srci/login.php');
    exit;
  }
}

// Verifica que el usuario sea admin
function requiere_admin(): void
{
  requiere_sesion();
  if (($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    include __DIR__ . '/../error403.php';
    exit;
  }
}

// Devuelve true si hay sesion activa
function esta_logueado(): bool
{
  iniciar_sesion();
  return !empty($_SESSION['usuario_id']);
}

// Devuelve true si el usuario es admin
function es_admin(): bool
{
  return esta_logueado() && ($_SESSION['rol'] ?? '') === 'admin';
}

// Genera y almacena un token CSRF en sesion
function csrf_token(): string
{
  iniciar_sesion();
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

// Valida el token CSRF recibido por POST
function validar_csrf(): void
{
  iniciar_sesion();
  $token_recibido = $_POST['csrf_token'] ?? '';
  $token_sesion   = $_SESSION['csrf_token'] ?? '';
  if (!hash_equals($token_sesion, $token_recibido)) {
    http_response_code(403);
    die('Token de seguridad invalido. Por favor, recarga la pagina e intenta de nuevo.');
  }
}
