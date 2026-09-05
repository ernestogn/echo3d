<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/EventoModel.php';
require_once APP_ROOT . '/app/models/EmpleadoModel.php';
require_once APP_ROOT . '/app/models/IncidenciaModel.php';

/**
 * TareasController — Portal para Técnicos/Operarios
 */
class TareasController extends BaseController {

    private EventoModel     $eventoModel;
    private EmpleadoModel   $empleadoModel;
    private IncidenciaModel $incidenciaModel;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->eventoModel     = new EventoModel($pdo);
        $this->empleadoModel   = new EmpleadoModel($pdo);
        $this->incidenciaModel = new IncidenciaModel($pdo);
    }

    /**
     * Listado de eventos asignados al técnico actual
     */
    public function index(?int $id = null): void {
        $this->requireAuth();
        // Buscar el ID de empleado vinculado al usuario actual
        $uid = $_SESSION['usuario_id'];
        $stmt = $this->pdo->prepare("SELECT id FROM carp_empleados WHERE usuario_id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$uid, $this->tenantId()]);
        $empl = $stmt->fetch();

        if (!$empl) {
            $this->render('tecnico/sin_vincular', ['pageTitle' => 'Error de Acceso'], 'none');
            return;
        }

        // Listar eventos donde el técnico está asignado
        $stmt = $this->pdo->prepare(
            "SELECT e.*, ee.funcion, ee.estado_pago as mi_estado_pago
             FROM carp_eventos e
             INNER JOIN carp_evento_empleados ee ON ee.evento_id = e.id
             WHERE ee.empleado_id = ? AND e.tenant_id = ?
             AND e.estado NOT IN ('cancelado', 'finalizado', 'cerrado')
             ORDER BY e.fecha_montaje ASC"
        );
        $stmt->execute([$empl['id'], $this->tenantId()]);
        $tareas = $stmt->fetchAll();

        $this->render('tecnico/agenda', [
            'pageTitle'  => 'Mi Agenda de Tareas',
            'activePage' => 'tareas',
            'tareas'     => $tareas,
            'empleado'   => $empl
        ], 'none'); // Usar layout simple o ninguno para móviles
    }

    public function detalle(int $id): void {
        $this->requireAuth();
        $evento = $this->eventoModel->findById($id);
        if (!$evento) $this->redirect('tareas');

        // Equipos asignados
        $equipos = $this->eventoModel->getEquiposAsignados($id);

        $this->render('tecnico/detalle', [
            'pageTitle' => 'Detalle de Tarea',
            'e'         => $evento,
            'equipos'   => $equipos
        ], 'none');
    }
}
