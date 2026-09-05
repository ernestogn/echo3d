<?php
/**
 * Conexión PDO a la base de datos
 * Reutiliza las mismas credenciales del sistema de encuestas
 */

$host_http = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_production = strpos($host_http, 'echo3dlaser.com.ar') !== false;

if ($is_production) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'c2801037_data');
    define('DB_USER', 'c2801037_data');
    define('DB_PASS', 'zeTIze34pu');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'encuesdata');
    define('DB_USER', 'root');
    define('DB_PASS', 'hG7!yW4&zPnD6uR$');
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // No exponer detalles en producción
    error_log('DB Connection Error [alquila]: ' . $e->getMessage());
    http_response_code(503);
    die(json_encode(['error' => 'Error de conexión a la base de datos.']));
}
