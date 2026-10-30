<?php
declare(strict_types=1);

// Duracion de la sesion: 90 dias, renovada en cada request (sliding).
// La sesion permanece viva hasta logout explicito (logout.php).
define('SESION_DURACION', 60 * 60 * 24 * 90);

// Configurar cookie segura antes de iniciar sesion
function iniciar_sesion(): void
{
  if (session_status() === PHP_SESSION_NONE) {
    // GC del server no mata la sesion por inactividad durante 90 dias
    @ini_set('session.gc_maxlifetime', (string)SESION_DURACION);
    session_set_cookie_params([
      'lifetime' => SESION_DURACION,
      'path'     => '/',
      'secure'   => isset($_SERVER['HTTPS']),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    session_start();
  }
}

// Renueva la expiracion de la cookie en cada request con sesion activa
function renovar_sesion(): void
{
  if (empty($_SESSION)) {
    return;
  }
  $params = session_get_cookie_params();
  setcookie(session_name(), session_id(), [
    'expires'  => time() + SESION_DURACION,
    'path'     => $params['path'],
    'secure'   => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'],
  ]);
}

// Verifica si hay sesion activa, redirige al login si no
function requiere_sesion(): void
{
  iniciar_sesion();
  if (empty($_SESSION['usuario_id'])) {
    header('Location: /srci/login.php');
    exit;
  }
  // Sesion viva mientras se use: renueva la cookie en cada request
  renovar_sesion();
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

// --- Anti fuerza bruta en el login (el PIN de 4 digitos tiene solo 10.000 combinaciones) ---
// Contador de intentos fallidos en archivos de tmp (sin tocar el esquema de DB,
// mismo patron de cache-en-archivos que el geocode). Dos llaves:
//   email|IP -> 5 fallos en 15 min  => bloqueo 15 min (ataque a una cuenta)
//   ip|IP    -> 30 fallos en 15 min => bloqueo 15 min (spray de muchos emails desde una IP)
// Si tmp no es escribible, la proteccion degrada sin romper el login.

define('LOGIN_MAX_FALLOS_EMAIL', 5);
define('LOGIN_MAX_FALLOS_IP', 30);
define('LOGIN_VENTANA_SEG', 15 * 60);
define('LOGIN_BLOQUEO_SEG', 15 * 60);

function _archivo_login_intentos(string $clave): string
{
  return sys_get_temp_dir() . '/srci-login-' . sha1($clave) . '.json';
}

function _leer_login_estado(string $ruta): array
{
  $datos = json_decode((string)@file_get_contents($ruta), true);
  return is_array($datos) ? $datos : ['fallos' => 0, 'ventana' => 0, 'bloqueado_hasta' => 0];
}

// Escritura atomica: flock evita que requests simultaneos pisen el contador
function _guardar_login_estado(string $ruta, array $datos): void
{
  $fp = @fopen($ruta, 'c');
  if ($fp === false) {
    return;
  }
  @flock($fp, LOCK_EX);
  @ftruncate($fp, 0);
  @fwrite($fp, json_encode($datos));
  @flock($fp, LOCK_UN);
  @fclose($fp);
}

// True si esa IP (sola o junto al email) esta bloqueada por fallos recientes
function login_bloqueado(string $ip, string $email): bool
{
  $ahora = time();
  foreach ([$email . '|' . $ip, 'ip|' . $ip] as $clave) {
    $estado = _leer_login_estado(_archivo_login_intentos($clave));
    if ((int)($estado['bloqueado_hasta'] ?? 0) > $ahora) {
      return true;
    }
  }
  return false;
}

// Registra un intento fallido; al llegar al maximo de su ventana activa el bloqueo.
// La activacion del bloqueo se registra en auditoria (usuario NULL: atacante sin sesion).
function login_registrar_fallo(string $ip, string $email): void
{
  $ahora   = time();
  $maximos = [LOGIN_MAX_FALLOS_EMAIL, LOGIN_MAX_FALLOS_IP];
  foreach ([$email . '|' . $ip, 'ip|' . $ip] as $i => $clave) {
    $ruta   = _archivo_login_intentos($clave);
    $estado = _leer_login_estado($ruta);
    if ($ahora - (int)($estado['ventana'] ?? 0) > LOGIN_VENTANA_SEG) {
      $estado = ['fallos' => 0, 'ventana' => $ahora, 'bloqueado_hasta' => 0];
    }
    $estado['fallos'] = (int)($estado['fallos'] ?? 0) + 1;
    $prev_bloqueo = (int)($estado['bloqueado_hasta'] ?? 0);
    if ($estado['fallos'] >= $maximos[$i] && $prev_bloqueo <= $ahora) {
      $estado['bloqueado_hasta'] = $ahora + LOGIN_BLOQUEO_SEG;
      // Auditar solo la transicion a bloqueado (no cada fallo extra de la ventana)
      $que = $i === 0
        ? 'email ' . mb_substr($email, 0, 100) . ' — IP ' . $ip
        : 'por IP (spray de emails) — IP ' . $ip;
      registrar_auditoria(
        null,
        'bloqueo_login',
        null,
        'Bloqueo anti fuerza bruta: ' . $que . ' — ' . (int)$estado['fallos'] . ' fallos en '
        . (int)(LOGIN_VENTANA_SEG / 60) . ' min. Bloqueado por ' . (int)(LOGIN_BLOQUEO_SEG / 60) . ' minutos.'
      );
    }
    _guardar_login_estado($ruta, $estado);
  }
  _limpiar_login_intentos_viejos();
}

// Audita un intento rechazado por bloqueo activo, como maximo 1 fila por
// minuto y combinacion email+IP (evita que el atacante llene la tabla)
function login_loguear_rechazo(string $ip, string $email): void
{
  $ruta   = _archivo_login_intentos($email . '|' . $ip);
  $estado = _leer_login_estado($ruta);
  $ahora  = time();
  if ($ahora - (int)($estado['ultimo_rechazo'] ?? 0) < 60) {
    return;
  }
  $estado['ultimo_rechazo'] = $ahora;
  _guardar_login_estado($ruta, $estado);
  registrar_auditoria(
    null,
    'acceso_bloqueado',
    null,
    'Intento de login rechazado (bloqueo activo) — email ' . mb_substr($email, 0, 100) . ' — IP ' . $ip
  );
}

// Login correcto: limpia el contador de esa combinacion email+IP.
// El contador global por IP se mantiene (puede haber ataque a otros emails).
function login_registrar_exito(string $ip, string $email): void
{
  @unlink(_archivo_login_intentos($email . '|' . $ip));
}

// GC: en el 1% de los requests borra archivos de intentos con mas de 1 dia
function _limpiar_login_intentos_viejos(): void
{
  if (random_int(1, 100) !== 1) {
    return;
  }
  foreach (glob(sys_get_temp_dir() . '/srci-login-*.json') ?: [] as $ruta) {
    if ((int)@filemtime($ruta) < time() - 86400) {
      @unlink($ruta);
    }
  }
}
