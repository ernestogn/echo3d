<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/UsuarioModel.php';

/**
 * UsuariosController — CRUD de usuarios dentro del tenant
 * Solo accesible para admins
 */
class UsuariosController extends BaseController {

    private UsuarioModel $model;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->model = new UsuarioModel($pdo);
    }

    public function index(?int $id = null): void {
        $this->requireRole('usuarios');
        $tid = $this->tenantId();
        $this->render('tenant/usuarios/index', [
            'pageTitle'  => 'Usuarios',
            'activePage' => 'usuarios',
            'usuarios'   => $this->model->listarPorTenant($tid),
            'flash'      => $this->getFlash(),
            'tenant'     => $this->tenant,
        ]);
    }

    public function crear(?int $id = null): void {
        $this->requireRole('usuarios');
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $tid = $this->tenantId();
            $res = $this->model->crear(
                $tid,
                trim($_POST['nombre'] ?? ''),
                trim($_POST['email']  ?? ''),
                $_POST['password']    ?? '',
                $_POST['rol']         ?? ROL_OPERADOR
            );
            if ($res['success']) {
                auditLog($this->pdo,'create','usuarios',$res['id'],null,[
                    'nombre'=>$_POST['nombre'],'email'=>$_POST['email'],'rol'=>$_POST['rol']
                ]);
                $this->flash('success','Usuario creado correctamente.');
                $this->redirect('usuarios');
            } else { $error = $res['message']; }
        }
        $this->render('tenant/usuarios/formulario', [
            'pageTitle'=>'Nuevo Usuario','activePage'=>'usuarios',
            'error'=>$error,'datos'=>$_POST??[],'usuario'=>null,'tenant'=>$this->tenant,
            'roles' => $this->getRolesDisponibles(),
        ]);
    }

    public function editar(?int $id = null): void {
        $this->requireRole('usuarios');
        $tid = $this->tenantId();
        $usuario = $id ? $this->model->findById($id, $tid) : null;
        if (!$usuario) { $this->redirect('usuarios'); }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $antes = $usuario;
            $res = $this->model->actualizar(
                $id, $tid,
                trim($_POST['nombre'] ?? ''),
                trim($_POST['email']  ?? ''),
                $_POST['rol']    ?? ROL_OPERADOR,
                (int)($_POST['activo'] ?? 1)
            );
            if ($res['success']) {
                // Actualizar password si se proveyó una nueva
                if (!empty($_POST['nuevo_password'])) {
                    $this->model->actualizarPassword($id, $tid, $_POST['nuevo_password']);
                }
                auditLog($this->pdo,'update','usuarios',$id,$antes,$_POST);
                $this->flash('success','Usuario actualizado.');
                $this->redirect('usuarios');
            } else { $error = $res['message']; }
        }
        $this->render('tenant/usuarios/formulario', [
            'pageTitle'=>'Editar Usuario','activePage'=>'usuarios',
            'error'=>$error,'datos'=>$usuario,'usuario'=>$usuario,'tenant'=>$this->tenant,
            'roles' => $this->getRolesDisponibles(),
        ]);
    }

    public function eliminar(?int $id = null): void {
        $this->requireRole('usuarios');
        if (!$id || $_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('usuarios');
        $this->verifyCsrf();
        $tid  = $this->tenantId();
        $actual = (int)($_SESSION['usuario_id'] ?? 0);
        $antes = $this->model->findById($id, $tid);
        $res = $this->model->eliminar($id, $tid, $actual);
        if ($res['success']) {
            auditLog($this->pdo,'delete','usuarios',$id,$antes,null);
            $this->flash('success','Usuario eliminado.');
        } else {
            $this->flash('error',$res['message']);
        }
        $this->redirect('usuarios');
    }

    private function getRolesDisponibles(): array {
        return [
            ROL_ADMIN    => 'Admin',
            ROL_OPERADOR => 'Operador',
            ROL_TECNICO  => 'Técnico',
            ROL_CLIENTE  => 'Cliente',
        ];
    }
}
