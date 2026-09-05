<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/TrasladoModel.php';
require_once APP_ROOT . '/app/models/EventoModel.php';
require_once APP_ROOT . '/app/models/EmpleadoModel.php';

/**
 * TrasladosController — Gestión de logística y transporte
 */
class TrasladosController extends BaseController {

    private TrasladoModel $model;
    private EventoModel   $eventoModel;
    private EmpleadoModel $empleadoModel;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->model         = new TrasladoModel($pdo);
        $this->eventoModel   = new EventoModel($pdo);
        $this->empleadoModel = new EmpleadoModel($pdo);
    }

    public function index(?int $id = null): void {
        $this->requireAuth();
        $filtros = [
            'evento_id' => $_GET['evento_id'] ?? '',
            'fecha'     => $_GET['fecha']     ?? ''
        ];
        $traslados = $this->model->listar($filtros);

        $this->render('tenant/traslados/index', [
            'pageTitle'  => 'Logística y Traslados',
            'activePage' => 'traslados',
            'traslados'  => $traslados,
            'filtros'    => $filtros,
            'flash'      => $this->getFlash(),
            'tenant'     => $this->tenant
        ]);
    }

    public function crear(?int $id = null): void {
        $this->requireAuth();
        $eventos   = $this->eventoModel->listar(['estado_not' => 'cancelado']);
        $empleados = $this->empleadoModel->listar(['activo' => 1]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->model->crear($_POST);
            $this->flash('success', 'Traslado programado correctamente.');
            $this->redirect('traslados');
        }

        $this->render('tenant/traslados/formulario', [
            'pageTitle'  => 'Programar Traslado',
            'activePage' => 'traslados',
            'eventos'    => $eventos,
            'empleados'  => $empleados,
            'traslado'   => null,
            'datos'      => ['evento_id' => $_GET['evento_id'] ?? ''],
            'tenant'     => $this->tenant
        ]);
    }

    public function editar(?int $id = null): void {
        $this->requireAuth();
        $traslado = $id ? $this->model->findById($id) : null;
        if (!$traslado) $this->redirect('traslados');

        $eventos   = $this->eventoModel->listar(['estado_not' => 'cancelado']);
        $empleados = $this->empleadoModel->listar(['activo' => 1]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->model->actualizar($id, $_POST);
            $this->flash('success', 'Traslado actualizado.');
            $this->redirect('traslados');
        }

        $this->render('tenant/traslados/formulario', [
            'pageTitle'  => 'Editar Traslado #' . $id,
            'activePage' => 'traslados',
            'eventos'    => $eventos,
            'empleados'  => $empleados,
            'traslado'   => $traslado,
            'datos'      => $traslado,
            'tenant'     => $this->tenant
        ]);
    }

    public function eliminar(?int $id = null): void {
        $this->requireRole('admin');
        if ($id && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->model->eliminar($id);
            $this->flash('success', 'Traslado eliminado.');
        }
        $this->redirect('traslados');
    }
}
