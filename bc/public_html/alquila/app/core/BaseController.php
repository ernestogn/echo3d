<?php
/**
 * Controlador base — todos los controladores extienden este
 */
abstract class BaseController {

    protected PDO    $pdo;
    protected ?array $tenant;
    protected ?string $tenantSlug;
    protected string  $loginUrl;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        $this->pdo       = $pdo;
        $this->tenant    = $tenant;
        $this->tenantSlug = $tenantSlug;
        $this->loginUrl  = $tenantSlug
            ? rtrim(BASE_URL, '/') . '/' . $tenantSlug . '/login'
            : rtrim(BASE_URL, '/') . '/login';
    }

    /**
     * Requerir autenticación para el método actual
     */
    protected function requireAuth(): void {
        AuthMiddleware::check($this->loginUrl);
    }

    /**
     * Requerir un rol específico
     */
    protected function requireRole(string $modulo): void {
        $this->requireAuth();
        RoleMiddleware::check($modulo, $this->loginUrl);
    }

    /**
     * Renderizar una vista con variables
     *
     * @param string $viewPath  Ruta relativa desde app/views/ (ej: 'tenant/eventos/index')
     * @param array  $data      Variables para la vista
     * @param string $layout    Layout a usar ('admin','operador','tecnico','cliente','superadmin','none')
     */
    protected function render(string $viewPath, array $data = [], string $layout = 'admin'): void {
        extract($data, EXTR_SKIP);

        $viewFile = APP_ROOT . '/app/views/' . $viewPath . '.php';
        if (!file_exists($viewFile)) {
            http_response_code(500);
            die('Vista no encontrada: ' . htmlspecialchars($viewPath));
        }

        // Capturar contenido de la vista
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === 'none') {
            echo $content;
            return;
        }

        $layoutFile = APP_ROOT . '/app/views/layouts/' . $layout . '.php';
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /**
     * JSON response (para endpoints AJAX/API)
     */
    protected function json(mixed $data, int $status = 200): never {
        jsonResponse($data, $status);
    }

    /**
     * Redirigir
     */
    protected function redirect(string $path): never {
        $base = rtrim(BASE_URL, '/') . '/';
        $slug = $this->tenantSlug ? ($this->tenantSlug . '/') : '';
        redirect($base . $slug . ltrim($path, '/'));
    }

    /**
     * Flash de éxito/error a la siguiente request
     */
    protected function flash(string $tipo, string $mensaje): void {
        $_SESSION['flash'][$tipo] = $mensaje;
    }

    /**
     * Obtener y limpiar mensajes flash
     */
    protected function getFlash(): array {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    /**
     * Verificar y obtener tenant_id de sesión
     */
    protected function tenantId(): int {
        return tenantId();
    }

    /**
     * Verificar CSRF
     */
    protected function verifyCsrf(): void {
        verificarCsrf();
    }
}
