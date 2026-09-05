<?php
/**
 * Router simple — Sistema de Gestión de Carpas y Eventos
 * Parsea la URL y despacha a Controller@method
 */
class Router {

    private PDO   $pdo;
    private ?array $tenant;
    private array  $segments;
    private ?string $tenantSlug;

    /** Mapa de rutas: [modulo => ControllerClass] */
    private array $routes = [
        ''           => 'controllers\DashboardController',
        'dashboard'  => 'controllers\DashboardController',
        'login'      => 'controllers\AuthController',
        'logout'     => 'controllers\AuthController',
        'eventos'    => 'controllers\EventosController',
        'clientes'   => 'controllers\ClientesController',
        'inventario' => 'controllers\InventarioController',
        'pagos'      => 'controllers\PagosController',
        'traslados'  => 'controllers\TrasladosController',
        'empleados'  => 'controllers\EmpleadosController',
        'incidencias'=> 'controllers\IncidenciasController',
        'gastos'     => 'controllers\GastosController',
        'usuarios'   => 'controllers\UsuariosController',
        'api'        => 'controllers\ApiController',
        'superadmin' => 'controllers\SuperadminController',
        'tareas'     => 'controllers\TareasController',
        'portal'     => 'controllers\PortalController',
    ];

    public function __construct(PDO $pdo, ?array $tenant, array $segments, ?string $tenantSlug) {
        $this->pdo        = $pdo;
        $this->tenant     = $tenant;
        $this->segments   = $segments;
        $this->tenantSlug = $tenantSlug;
    }

    public function dispatch(): void {
        $modulo = strtolower($this->segments[0] ?? '');
        $accion = strtolower($this->segments[1] ?? 'index');
        $id     = isset($this->segments[2]) ? (int)$this->segments[2] : null;

        $controllerClass = $this->routes[$modulo] ?? null;

        if (!$controllerClass) {
            $this->notFound();
            return;
        }

        $fullClass = $controllerClass;
        if (!class_exists($fullClass)) {
            $this->notFound();
            return;
        }

        $controller = new $fullClass($this->pdo, $this->tenant, $this->tenantSlug);

        // Convertir acción kebab-case a camelCase: "mis-eventos" → "misEventos"
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $accion))));

        if (!method_exists($controller, $method)) {
            $this->notFound();
            return;
        }

        $controller->$method($id);
    }

    private function notFound(): void {
        http_response_code(404);
        require APP_ROOT . '/app/views/errors/404.php';
    }
}
