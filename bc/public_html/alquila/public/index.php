<?php
/**
 * FRONT CONTROLLER — Sistema de Gestión de Carpas y Eventos
 * Toda request pasa por aquí.
 * Flujo: config → middlewares → router → controller → vista
 */

// ── Autoload básico (sin composer) ──────────────────────────────────────────
spl_autoload_register(function (string $class) {
    $base = dirname(__DIR__) . '/app/';
    $path = $base . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

// ── Configuración ────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/Helpers.php';

// ── Sesión segura ────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    session_start();
}

// ── Headers anti-caché ───────────────────────────────────────────────────────
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
}

// ── Parsear URL ──────────────────────────────────────────────────────────────
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
// Quitar la parte base de la URL (ej: /alquila/public)
$base_path   = parse_url(BASE_URL, PHP_URL_PATH);
$request_path = '/' . ltrim(str_replace($base_path, '', parse_url($request_uri, PHP_URL_PATH)), '/');
$segments    = array_values(array_filter(explode('/', trim($request_path, '/'))));

// ── Determinar slug del tenant ───────────────────────────────────────────────
// URL esperada: /[slug]/[modulo]/[accion]/[id]
// Rutas especiales sin slug: /superadmin, /install
$special_routes = ['superadmin', 'install'];
$slug           = null;

if (!empty($segments) && !in_array($segments[0], $special_routes)) {
    $slug            = strtolower(preg_replace('/[^a-z0-9\-]/', '', $segments[0] ?? ''));
    $route_segments  = array_slice($segments, 1); // móodulo, acción, id
} else {
    $route_segments  = $segments;
}

// ── Middlewares ──────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/app/middleware/TenantMiddleware.php';
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/app/middleware/RoleMiddleware.php';

$tenant = null;
if ($slug !== null) {
    $tenant = TenantMiddleware::resolve($pdo, $slug);
}

// ── Router ───────────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/app/core/Router.php';

$router = new Router($pdo, $tenant, $route_segments, $slug);
$router->dispatch();
