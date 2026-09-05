<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/UsuarioModel.php';

/**
 * AuthController — Login / Logout
 * Compatible con usuarios del tenant y superadmins.
 */
class AuthController extends BaseController {

    public function index(?int $id = null): void {
        $this->login($id);
    }

    public function login(?int $id = null): void {
        AuthMiddleware::skipAuth();

        if (!empty($_SESSION['usuario_id'])) {
            $this->redirectAfterLogin();
        }

        $error  = '';
        $flash  = $this->getFlash();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $email    = trim($_POST['email']    ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Completá todos los campos.';
            } else {
                $resultado = $this->procesarLogin($email, $password);
                if ($resultado['success']) {
                    $this->redirectAfterLogin();
                } else {
                    $error = $resultado['message'];
                }
            }
        }

        $this->render('auth/login', [
            'error'      => $error,
            'flash'      => $flash,
            'pageTitle'  => 'Iniciar Sesión',
            'tenant'     => $this->tenant,
        ], 'none');
    }

    public function logout(?int $id = null): void {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['mensaje_exito'] = 'Sesión cerrada correctamente.';

        $loginUrl = $this->tenantSlug
            ? rtrim(BASE_URL, '/') . '/' . $this->tenantSlug . '/login'
            : rtrim(BASE_URL, '/') . '/superadmin/login';

        header('Location: ' . $loginUrl);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function procesarLogin(string $email, string $password): array {
        $model = new UsuarioModel($this->pdo);
        $tenantId = $this->tenant['id'] ?? null;

        // Rate limiting: verificar bloqueo temporal por IP/email
        $bloqueo = $model->verificarBloqueo($email, clienteIp());
        if ($bloqueo['bloqueado']) {
            return ['success' => false, 'message' => 'Demasiados intentos. Esperá ' . $bloqueo['minutos'] . ' minutos.'];
        }

        $usuario = $model->findByEmailAndTenant($email, $tenantId);

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $model->registrarIntentoFallido($email, clienteIp());
            return ['success' => false, 'message' => 'Email o contraseña incorrectos.'];
        }

        if ($usuario['activo'] != 1) {
            return ['success' => false, 'message' => 'Usuario desactivado. Contactá al administrador.'];
        }

        // Login exitoso — limpiar intentos y regenerar sesión
        $model->limpiarIntentos($email);
        session_regenerate_id(true);

        $_SESSION['usuario_id']      = $usuario['id'];
        $_SESSION['usuario_nombre']  = $usuario['nombre'];
        $_SESSION['usuario_email']   = $usuario['email'];
        $_SESSION['usuario_rol']     = $usuario['rol'];
        $_SESSION['usuario_tenant_id'] = $usuario['tenant_id'];
        $_SESSION['usuario_autenticado'] = true;
        $_SESSION['last_activity']   = time();
        $_SESSION['login_time']      = time();

        // tenant_id ya está en sesión desde TenantMiddleware (o null para superadmin)
        if ($tenantId) {
            $_SESSION['tenant_id'] = $tenantId;
        }

        $model->actualizarUltimoLogin($usuario['id']);

        return ['success' => true];
    }

    private function redirectAfterLogin(): never {
        $rol       = $_SESSION['usuario_rol'] ?? '';
        $slug      = $this->tenantSlug;
        $base      = rtrim(BASE_URL, '/');

        $redirect = $_SESSION['redirect_after_login'] ?? null;
        unset($_SESSION['redirect_after_login']);

        if ($redirect) {
            header('Location: ' . $redirect);
            exit;
        }

        switch ($rol) {
            case ROL_SUPERADMIN:
                header('Location: ' . $base . '/superadmin/dashboard');
                break;
            case ROL_TECNICO:
                header('Location: ' . $base . '/' . $slug . '/tareas');
                break;
            case ROL_CLIENTE:
                header('Location: ' . $base . '/' . $slug . '/portal/mi-evento');
                break;
            default:
                header('Location: ' . $base . '/' . $slug . '/dashboard');
        }
        exit;
    }
}
