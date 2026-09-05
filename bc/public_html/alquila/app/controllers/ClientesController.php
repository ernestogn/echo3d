<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/ClienteModel.php';

/**
 * ClientesController
 */
class ClientesController extends BaseController {

    private ClienteModel $model;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->model = new ClienteModel($pdo);
    }

    public function index(?int $id = null): void {
        $this->requireRole('clientes');
        $busqueda = trim($_GET['q'] ?? '');
        $this->render('tenant/clientes/index', [
            'pageTitle'  => 'Clientes',
            'activePage' => 'clientes',
            'clientes'   => $this->model->listar($busqueda),
            'busqueda'   => $busqueda,
            'flash'      => $this->getFlash(),
            'tenant'     => $this->tenant,
        ]);
    }

    public function crear(?int $id = null): void {
        $this->requireRole('clientes');
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $res = $this->model->crear($_POST);
            if ($res['success']) {
                auditLog($this->pdo, 'create', 'carp_clientes', $res['id'], null, $_POST);
                $this->flash('success', 'Cliente creado.');
                $this->redirect('clientes');
            } else { $error = $res['message']; }
        }
        $this->render('tenant/clientes/formulario', [
            'pageTitle' => 'Nuevo Cliente', 'activePage'=>'clientes',
            'error'    => $error, 'datos' => $_POST ?? [], 'cliente' => null, 'tenant'=>$this->tenant,
        ]);
    }

    public function editar(?int $id = null): void {
        $this->requireRole('clientes');
        $cliente = $id ? $this->model->findById($id) : null;
        if (!$cliente) { $this->redirect('clientes'); }
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $antes = $cliente;
            $res   = $this->model->actualizar($id, $_POST);
            if ($res['success']) {
                auditLog($this->pdo,'update','carp_clientes',$id,$antes,$_POST);
                $this->flash('success','Cliente actualizado.');
                $this->redirect('clientes');
            } else { $error = $res['message']; }
        }
        $this->render('tenant/clientes/formulario', [
            'pageTitle' => 'Editar Cliente', 'activePage'=>'clientes',
            'error'=>$error,'datos'=>$cliente,'cliente'=>$cliente,'tenant'=>$this->tenant,
        ]);
    }

    public function eliminar(?int $id = null): void {
        $this->requireRole('clientes');
        if (!$id || $_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('clientes');
        $this->verifyCsrf();
        $antes = $this->model->findById($id);
        $res   = $this->model->eliminar($id);
        if ($res['success']) {
            auditLog($this->pdo,'delete','carp_clientes',$id,$antes,null);
            $this->flash('success','Cliente eliminado.');
        } else {
            $this->flash('error',$res['message']);
        }
        $this->redirect('clientes');
    }
}
