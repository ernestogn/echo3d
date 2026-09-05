<?php
/**
 * AuthMiddleware
 * Verifica que haya una sesión activa y que no haya expirado.
 * Las rutas públicas (login) deben llamar a skipAuth().
 */
class AuthMiddleware {

    /**
     * Verificar autenticación. Redirige al login si no está autenticado.
     *
     * @param string $loginUrl URL del formulario de login del tenant
     */
    public static function check(string $loginUrl): void {
        // ¿Hay sesión activa?
        if (empty($_SESSION['usuario_id']) || empty($_SESSION['usuario_autenticado'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: ' . $loginUrl);
            exit;
        }

        // ¿Expiró la sesión por inactividad?
        $lastActivity = $_SESSION['last_activity'] ?? time();
        if ((time() - $lastActivity) > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['mensaje_error'] = 'Tu sesión expiró. Por favor volvé a iniciar sesión.';
            header('Location: ' . $loginUrl);
            exit;
        }

        // Verificar que el tenant de sesión coincide con el tenant de la URL
        if (!empty($_SESSION['tenant_id']) && isset($_SESSION['usuario_tenant_id'])) {
            if ($_SESSION['tenant_id'] !== $_SESSION['usuario_tenant_id']
                && $_SESSION['usuario_rol'] !== ROL_SUPERADMIN) {
                // Intento de acceder a otro tenant
                header('Location: ' . $loginUrl);
                exit;
            }
        }

        // Actualizar timestamp de actividad
        $_SESSION['last_activity'] = time();
    }

    /**
     * Para rutas que no requieren autenticación (login, páginas públicas).
     * No hace nada, simplemente es un marcador semántico.
     */
    public static function skipAuth(): void {
        // Sin acción
    }
}
