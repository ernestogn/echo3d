<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/InventarioModel.php';

/**
 * InventarioController
 */
class InventarioController extends BaseController {

    private InventarioModel $model;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->model = new InventarioModel($pdo);
    }

    public function index(?int $id = null): void {
        $this->requireRole('inventario');
        $this->render('tenant/inventario/index', [
            'pageTitle'  => 'Inventario de Equipos',
            'activePage' => 'inventario',
            'equipos'    => $this->model->listar(),
            'flash'      => $this->getFlash(),
            'tenant'     => $this->tenant,
        ]);
    }

    public function crear(?int $id = null): void {
        $this->requireRole('inventario');
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $datos = $_POST;
            // Manejar foto
            if (!empty($_FILES['foto']['name'])) {
                $res = subirImagen($_FILES['foto'], 'equipos');
                if ($res['success']) {
                    $datos['foto_path'] = $res['path'];
                } else {
                    $error = $res['message'];
                }
            }
            if (!$error) {
                $res = $this->model->crear($datos);
                if ($res['success']) {
                    auditLog($this->pdo,'create','carp_inventario_equipos',$res['id'],null,$datos);
                    $this->flash('success','Equipo creado.');
                    $this->redirect('inventario');
                } else { $error = $res['message']; }
            }
        }
        $this->render('tenant/inventario/formulario', [
            'pageTitle'=>'Nuevo Equipo','activePage'=>'inventario',
            'error'=>$error,'datos'=>$_POST??[],'equipo'=>null,'tenant'=>$this->tenant,
        ]);
    }

    public function editar(?int $id = null): void {
        $this->requireRole('inventario');
        $equipo = $id ? $this->model->findById($id) : null;
        if (!$equipo) $this->redirect('inventario');
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $datos = $_POST;
            $datos['foto_path'] = $equipo['foto_path']; // mantener foto existente
            if (!empty($_FILES['foto']['name'])) {
                $res = subirImagen($_FILES['foto'], 'equipos');
                if ($res['success']) $datos['foto_path'] = $res['path'];
                else $error = $res['message'];
            }
            if (!$error) {
                $res = $this->model->actualizar($id, $datos);
                if ($res['success']) {
                    auditLog($this->pdo,'update','carp_inventario_equipos',$id,$equipo,$datos);
                    $this->flash('success','Equipo actualizado.');
                    $this->redirect('inventario');
                } else { $error = $res['message']; }
            }
        }
        $this->render('tenant/inventario/formulario', [
            'pageTitle'=>'Editar Equipo','activePage'=>'inventario',
            'error'=>$error,'datos'=>$equipo,'equipo'=>$equipo,'tenant'=>$this->tenant,
        ]);
    }

    public function eliminar(?int $id = null): void {
        $this->requireRole('inventario');
        if (!$id || $_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('inventario');
        $this->verifyCsrf();
        $antes = $this->model->findById($id);
        $res   = $this->model->eliminar($id);
        if ($res['success']) {
            auditLog($this->pdo,'delete','carp_inventario_equipos',$id,$antes,null);
            $this->flash('success','Equipo eliminado.');
        } else {
            $this->flash('error',$res['message']);
        }
        $this->redirect('inventario');
    }
}
