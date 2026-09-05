<?php
/**
 * RoleMiddleware
 * Verifica que el usuario tenga el/los rol(es) requeridos para acceder.
 */
class RoleMiddleware {

    /**
     * Permisos por módulo.
     * Key: modulo. Value: array de roles permitidos.
     * Si el array está vacío, cualquier autenticado puede acceder.
     */
    private static array $permisos = [
        'dashboard'   => [ROL_ADMIN, ROL_OPERADOR, ROL_TECNICO, ROL_CLIENTE],
        'eventos'     => [ROL_ADMIN, ROL_OPERADOR],
        'clientes'    => [ROL_ADMIN, ROL_OPERADOR],
        'inventario'  => [ROL_ADMIN, ROL_OPERADOR],
        'pagos'       => [ROL_ADMIN, ROL_OPERADOR],
        'traslados'   => [ROL_ADMIN, ROL_OPERADOR],
        'empleados'   => [ROL_ADMIN],
        'incidencias' => [ROL_ADMIN, ROL_OPERADOR, ROL_TECNICO],
        'gastos'      => [ROL_ADMIN],
        'reportes'    => [ROL_ADMIN],
        'usuarios'    => [ROL_ADMIN],
        'api'         => [], // acceso por token de sesión
        'superadmin'  => [ROL_SUPERADMIN],
        'tareas'      => [ROL_TECNICO],
        'portal'      => [ROL_CLIENTE],
    ];

    /**
     * Verificar que el usuario actual tiene permiso para el módulo indicado.
     * Redirige con 403 si no tiene permiso.
     *
     * @param string $modulo  Nombre del módulo (ej: 'eventos')
     * @param string $loginUrl URL de login para redirigir si no autenticado
     */
    public static function check(string $modulo, string $loginUrl): void {
        $rolUsuario = $_SESSION['usuario_rol'] ?? null;

        if (!$rolUsuario) {
            header('Location: ' . $loginUrl);
            exit;
        }

        // Superadmin tiene acceso total excepto al portal del cliente
        if ($rolUsuario === ROL_SUPERADMIN && $modulo !== 'portal') {
            return;
        }

        $rolesPermitidos = self::$permisos[$modulo] ?? [];

        // Si la lista está vacía, cualquier usuario autenticado puede acceder
        if (empty($rolesPermitidos)) {
            return;
        }

        if (!in_array($rolUsuario, $rolesPermitidos, true)) {
            http_response_code(403);
            require APP_ROOT . '/app/views/errors/403.php';
            exit;
        }
    }

    /**
     * Verificar si el usuario actual tiene un rol específico.
     * Útil para mostrar/ocultar elementos en vistas.
     */
    public static function tieneRol(string|array $roles): bool {
        $rolUsuario = $_SESSION['usuario_rol'] ?? null;
        if (!$rolUsuario) return false;

        if (is_string($roles)) {
            return $rolUsuario === $roles;
        }
        return in_array($rolUsuario, $roles, true);
    }

    public static function esSuperadmin(): bool {
        return ($_SESSION['usuario_rol'] ?? '') === ROL_SUPERADMIN;
    }

    public static function esAdmin(): bool {
        return in_array($_SESSION['usuario_rol'] ?? '', [ROL_SUPERADMIN, ROL_ADMIN], true);
    }
}
