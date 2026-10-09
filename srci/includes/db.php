<?php
declare(strict_types=1);

// Credenciales de conexion al SRCI - ajustar en produccion
define('SRCI_DB_HOST', 'localhost');
define('SRCI_DB_NAME', 'srci');
define('SRCI_DB_USER', 'srci_user');
define('SRCI_DB_PASS', 'srci_pass');

// Cargar configuracion local si existe (sobreescribe las constantes de arriba)
if (is_file(__DIR__ . '/config.local.php')) {
  require __DIR__ . '/config.local.php';
}

function db(): PDO
{
  static $pdo = null;
  if ($pdo === null) {
    $pdo = new PDO(
      'mysql:host=' . SRCI_DB_HOST . ';dbname=' . SRCI_DB_NAME . ';charset=utf8mb4',
      SRCI_DB_USER,
      SRCI_DB_PASS,
      [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
      ]
    );
  }
  return $pdo;
}

function respuesta_json(mixed $datos, int $codigo = 200): never
{
  http_response_code($codigo);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
