<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/GastoModel.php';
require_once APP_ROOT . '/app/models/EventoModel.php';

/**
 * GastosController — Finanzas y egresos
 */
class GastosController extends BaseController {

    private GastoModel  $model;
    private EventoModel $eventoModel;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->model       = new GastoModel($pdo);
        $this->eventoModel = new EventoModel($pdo);
    }

    public function index(?int $id = null): void {
        $this->requireRole('admin');
        $filtros = [
            'categoria'   => $_GET['categoria']   ?? '',
            'evento_id'   => $_GET['evento_id']   ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? ''
        ];
        $gastos = $this->model->listar($filtros);

        $this->render('tenant/gastos/index', [
            'pageTitle'  => 'Gestión de Gastos',
            'activePage' => 'gastos',
            'gastos'     => $gastos,
            'filtros'    => $filtros,
            'flash'      => $this->getFlash(),
            'tenant'     => $this->tenant
        ]);
    }

    public function crear(?int $id = null): void {
        $this->requireRole('admin');
        $eventos = $this->eventoModel->listar(['estado_not' => 'cancelado']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->model->crear($_POST);
            $this->flash('success', 'Gasto registrado correctamente.');
            $this->redirect('gastos');
        }

        $this->render('tenant/gastos/formulario', [
            'pageTitle'  => 'Registrar Gasto',
            'activePage' => 'gastos',
            'eventos'    => $eventos,
            'gasto'      => null,
            'datos'      => ['evento_id' => $_GET['evento_id'] ?? ''],
            'tenant'     => $this->tenant
        ]);
    }

    public function editar(?int $id = null): void {
        $this->requireRole('admin');
        $gasto = $id ? $this->model->findById($id) : null;
        if (!$gasto) $this->redirect('gastos');

        $eventos = $this->eventoModel->listar(['estado_not' => 'cancelado']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->model->actualizar($id, $_POST);
            $this->flash('success', 'Gasto actualizado.');
            $this->redirect('gastos');
        }

        $this->render('tenant/gastos/formulario', [
            'pageTitle'  => 'Editar Gasto',
            'activePage' => 'gastos',
            'eventos'    => $eventos,
            'gasto'      => $gasto,
            'datos'      => $gasto,
            'tenant'     => $this->tenant
        ]);
    }

    public function eliminar(?int $id = null): void {
        $this->requireRole('admin');
        if ($id && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->model->eliminar($id);
            $this->flash('success', 'Gasto eliminado.');
        }
        $this->redirect('gastos');
    }
}
