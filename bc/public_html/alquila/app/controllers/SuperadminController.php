<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/TenantModel.php';
require_once APP_ROOT . '/app/models/UsuarioModel.php';

/**
 * SuperadminController — Panel de administración del sistema
 * Solo accesible para rol superadmin
 */
class SuperadminController extends BaseController {

    private TenantModel  $tenantModel;
    private UsuarioModel $usuarioModel;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->tenantModel  = new TenantModel($pdo);
        $this->usuarioModel = new UsuarioModel($pdo);
        $this->loginUrl     = rtrim(BASE_URL, '/') . '/superadmin/login';
    }

    public function index(?int $id = null): void {
        $this->dashboard($id);
    }

    public function dashboard(?int $id = null): void {
        $this->requireRole('superadmin');

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM tenants");
        $totalTenants = $stmt->fetchColumn();
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM carp_eventos");
        $totalEventos = $stmt->fetchColumn();
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol != 'superadmin'");
        $totalUsuarios = $stmt->fetchColumn();
        $tenants = $this->tenantModel->listarTodos();

        $this->render('superadmin/dashboard', [
            'pageTitle'    => 'Dashboard',
            'activePage'   => 'dashboard',
            'totalTenants' => $totalTenants,
            'totalEventos' => $totalEventos,
            'totalUsuarios'=> $totalUsuarios,
            'tenants'      => $tenants,
            'flash'        => $this->getFlash(),
        ], 'superadmin');
    }

    // ── Tenants ───────────────────────────────────────────────────────────────

    public function tenants(?int $id = null): void {
        $this->requireRole('superadmin');
        $this->render('superadmin/tenants/index', [
            'pageTitle'  => 'Empresas',
            'activePage' => 'tenants',
            'tenants'    => $this->tenantModel->listarTodos(),
            'flash'      => $this->getFlash(),
        ], 'superadmin');
    }

    public function crearTenant(?int $id = null): void {
        $this->requireRole('superadmin');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $resultado = $this->tenantModel->crear($_POST);
            if ($resultado['success']) {
                // Crear usuario admin inicial
                $adminEmail = trim($_POST['admin_email'] ?? '');
                $adminPass  = $_POST['admin_password'] ?? '';
                $adminNombre= trim($_POST['admin_nombre'] ?? 'Administrador');
                if ($adminEmail && $adminPass) {
                    $this->usuarioModel->crear(
                        $resultado['id'], $adminNombre, $adminEmail, $adminPass, ROL_ADMIN
                    );
                }
                auditLog($this->pdo, 'create', 'tenants', $resultado['id'], null, $_POST);
                $this->flash('success', 'Empresa "' . e($_POST['nombre']) . '" creada correctamente.');
                $this->redirectSA('tenants');
            } else {
                $this->render('superadmin/tenants/crear', [
                    'pageTitle' => 'Crear Empresa',
                    'activePage'=> 'tenants',
                    'error'     => $resultado['message'],
                    'datos'     => $_POST,
                ], 'superadmin');
                return;
            }
        }

        $this->render('superadmin/tenants/crear', [
            'pageTitle'  => 'Crear Empresa',
            'activePage' => 'tenants',
            'datos'      => [],
        ], 'superadmin');
    }

    public function editarTenant(?int $id = null): void {
        $this->requireRole('superadmin');
        $tenant = $id ? $this->tenantModel->findById($id) : null;
        if (!$tenant) { $this->redirectSA('tenants'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $antes     = $tenant;
            $resultado = $this->tenantModel->actualizar($id, $_POST);
            if ($resultado['success']) {
                auditLog($this->pdo, 'update', 'tenants', $id, $antes, $_POST);
                $this->flash('success', 'Empresa actualizada.');
                $this->redirectSA('tenants');
            } else {
                $this->render('superadmin/tenants/editar', [
                    'pageTitle' => 'Editar Empresa',
                    'activePage'=> 'tenants',
                    'error'     => $resultado['message'],
                    'tenant'    => array_merge($tenant, $_POST),
                ], 'superadmin');
                return;
            }
        }

        $this->render('superadmin/tenants/editar', [
            'pageTitle'  => 'Editar Empresa: ' . e($tenant['nombre']),
            'activePage' => 'tenants',
            'tenant'     => $tenant,
        ], 'superadmin');
    }

    public function suspenderTenant(?int $id = null): void {
        $this->requireRole('superadmin');
        if (!$id) $this->redirectSA('tenants');
        $this->verifyCsrf();
        $tenant = $this->tenantModel->findById($id);
        $nuevoEstado = ($tenant['estado'] === 'activo') ? 'suspendido' : 'activo';
        $this->tenantModel->cambiarEstado($id, $nuevoEstado);
        auditLog($this->pdo, 'estado_cambio', 'tenants', $id, ['estado' => $tenant['estado']], ['estado' => $nuevoEstado]);
        $this->flash('success', 'Estado de empresa actualizado.');
        $this->redirectSA('tenants');
    }

    // ── Login del superadmin (sin tenant) ─────────────────────────────────────

    public function login(?int $id = null): void {
        AuthMiddleware::skipAuth();
        if (!empty($_SESSION['usuario_id']) && $_SESSION['usuario_rol'] === ROL_SUPERADMIN) {
            $this->redirectSA('dashboard');
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $model    = new UsuarioModel($this->pdo);
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $usuario  = $model->findByEmailAndTenant($email, null);

            if (!$usuario || !password_verify($password, $usuario['password']) || $usuario['rol'] !== ROL_SUPERADMIN) {
                $error = 'Credenciales de superadmin incorrectas.';
            } else {
                session_regenerate_id(true);
                $_SESSION['usuario_id']        = $usuario['id'];
                $_SESSION['usuario_nombre']    = $usuario['nombre'];
                $_SESSION['usuario_rol']       = ROL_SUPERADMIN;
                $_SESSION['usuario_autenticado'] = true;
                $_SESSION['last_activity']     = time();
                $model->actualizarUltimoLogin($usuario['id']);
                $this->redirectSA('dashboard');
            }
        }

        $this->render('auth/login', [
            'error'     => $error,
            'flash'     => $this->getFlash(),
            'pageTitle' => 'Superadmin Login',
            'tenant'    => ['nombre' => 'Sistema Alquila'],
        ], 'none');
    }

    public function logout(?int $id = null): void {
        session_unset();
        session_destroy();
        header('Location: ' . rtrim(BASE_URL, '/') . '/superadmin/login');
        exit;
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function redirectSA(string $path): never {
        header('Location: ' . rtrim(BASE_URL, '/') . '/superadmin/' . $path);
        exit;
    }
}
