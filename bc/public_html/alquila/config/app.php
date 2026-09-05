<?php
/**
 * Configuración principal de la aplicación
 * Sistema de Gestión de Carpas y Eventos
 */

define('APP_NAME', 'Alquila — Gestión de Eventos');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', dirname(__DIR__));          // /alquila
define('STORAGE_PATH', APP_ROOT . '/storage'); // fuera del public

// Zona horaria por defecto (se sobreescribe por tenant)
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Detectar base URL dinámica
function getAlquilaBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Override producción
    if (strpos($host, 'echo3dlaser.com.ar') !== false) {
        return 'https://echo3dlaser.com.ar/alquila/public/';
    }

    return $protocol . $host . '/echo3d/bc/public_html/alquila/public/';
}

define('BASE_URL', getAlquilaBaseUrl());

// Roles del sistema
define('ROL_SUPERADMIN', 'superadmin');
define('ROL_ADMIN',      'admin');
define('ROL_OPERADOR',   'operador');
define('ROL_TECNICO',    'tecnico');
define('ROL_CLIENTE',    'cliente');

// Timeout de sesión en segundos (2 horas)
define('SESSION_TIMEOUT', 7200);

// Máximo de intentos de login antes de bloqueo temporal
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_BLOCK_MINUTES', 15);

// Tamaños máximos para uploads (bytes)
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_MIMES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
