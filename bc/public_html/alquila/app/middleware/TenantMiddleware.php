<?php
/**
 * TenantMiddleware
 * Resuelve el tenant por slug de URL, valida que esté activo,
 * e inyecta tenant_id en la sesión.
 */
class TenantMiddleware {

    /**
     * Busca el tenant por slug y lo valida.
     * Si el tenant no existe o está suspendido, finaliza con error.
     *
     * @return array Datos del tenant
     */
    public static function resolve(PDO $pdo, string $slug): array {
        if (empty($slug) || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
            self::tenantError('Dirección no válida.');
        }

        $stmt = $pdo->prepare(
            "SELECT id, nombre, slug, estado, moneda, zona_horaria, logo_path, email_contacto
             FROM tenants WHERE slug = ? LIMIT 1"
        );
        $stmt->execute([$slug]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            self::tenantError('Empresa no encontrada.');
        }

        if ($tenant['estado'] === 'suspendido') {
            http_response_code(403);
            $nombre = htmlspecialchars($tenant['nombre']);
            die("
                <html><head><title>Cuenta suspendida</title>
                <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
                </head><body class='d-flex align-items-center justify-content-center min-vh-100 bg-light'>
                <div class='text-center'>
                    <h2 class='text-danger'>Cuenta suspendida</h2>
                    <p>La cuenta de <strong>{$nombre}</strong> está suspendida.</p>
                    <p>Por favor contacte al administrador.</p>
                </div></body></html>"
            );
        }

        // Inyectar en sesión
        $_SESSION['tenant_id']    = $tenant['id'];
        $_SESSION['tenant_slug']  = $tenant['slug'];
        $_SESSION['tenant_nombre']= $tenant['nombre'];

        // Zona horaria del tenant
        if (!empty($tenant['zona_horaria'])) {
            date_default_timezone_set($tenant['zona_horaria']);
        }

        return $tenant;
    }

    private static function tenantError(string $msg): never {
        http_response_code(404);
        die("<html><head><title>Error</title>
             <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
             </head><body class='d-flex align-items-center justify-content-center min-vh-100 bg-light'>
             <div class='text-center'>
                 <h2>404 — " . htmlspecialchars($msg) . "</h2>
                 <a href='/'>Volver al inicio</a>
             </div></body></html>");
    }
}
