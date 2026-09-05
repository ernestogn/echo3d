<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/EmpleadoModel.php';
require_once APP_ROOT . '/app/models/UsuarioModel.php';

/**
 * EmpleadosController — Gestión de personal y operarios
 */
class EmpleadosController extends BaseController {

    private EmpleadoModel $model;
    private UsuarioModel  $usuarioModel;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->model        = new EmpleadoModel($pdo);
        $this->usuarioModel = new UsuarioModel($pdo);
    }

    public function index(?int $id = null): void {
        $this->requireRole('empleados');
        $filtros = ['activo' => $_GET['activo'] ?? null];
        $empleados = $this->model->listar($filtros);

        $this->render('tenant/empleados/index', [
            'pageTitle'  => 'Personal y Empleados',
            'activePage' => 'empleados',
            'empleados'  => $empleados,
            'flash'      => $this->getFlash(),
            'tenant'     => $this->tenant
        ]);
    }

    public function crear(?int $id = null): void {
        $this->requireRole('empleados');
        $usuarios = $this->usuarioModel->listarPorTenant($this->tenantId());

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->model->crear($_POST);
            $this->flash('success', 'Empleado creado correctamente.');
            $this->redirect('empleados');
        }

        $this->render('tenant/empleados/formulario', [
            'pageTitle'  => 'Nuevo Empleado',
            'activePage' => 'empleados',
            'usuarios'   => $usuarios,
            'empleado'   => null,
            'datos'      => [],
            'tenant'     => $this->tenant
        ]);
    }

    public function editar(?int $id = null): void {
        $this->requireRole('empleados');
        $empleado = $id ? $this->model->findById($id) : null;
        if (!$empleado) $this->redirect('empleados');

        $usuarios = $this->usuarioModel->listarPorTenant($this->tenantId());

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->model->actualizar($id, $_POST);
            $this->flash('success', 'Empleado actualizado.');
            $this->redirect('empleados');
        }

        $this->render('tenant/empleados/formulario', [
            'pageTitle'  => 'Editar Empleado: ' . e($empleado['nombre']),
            'activePage' => 'empleados',
            'usuarios'   => $usuarios,
            'empleado'   => $empleado,
            'datos'      => $empleado,
            'tenant'     => $this->tenant
        ]);
    }
}
