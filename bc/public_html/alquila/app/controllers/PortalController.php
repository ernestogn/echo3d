<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/EventoModel.php';
require_once APP_ROOT . '/app/models/PagoModel.php';

/**
 * PortalController — Portal para Clientes (Mi Evento)
 */
class PortalController extends BaseController {

    private EventoModel $eventoModel;
    private PagoModel   $pagoModel;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->eventoModel = new EventoModel($pdo);
        $this->pagoModel   = new PagoModel($pdo);
    }

    /**
     * Vista principal para el cliente
     */
    public function miEvento(?int $id = null): void {
        $this->requireRole('cliente');
        
        // El ID de usuario debería estar vinculado a un cliente
        // En este sistema simplificado, buscamos eventos que pertenezcan al cliente vinculado
        // Para simplificar, buscaremos eventos asociados al correo del usuario actual
        $email = $_SESSION['usuario_email'];
        
        $tid = $this->tenantId();
        $stmt = $this->pdo->prepare(
            "SELECT e.* FROM carp_eventos e
             INNER JOIN carp_clientes c ON c.id = e.cliente_id
             WHERE c.email = ? AND e.tenant_id = ?
             ORDER BY e.fecha_inicio_evento DESC LIMIT 1"
        );
        $stmt->execute([$email, $tid]);
        $evento = $stmt->fetch();

        if (!$evento) {
            $this->render('cliente/sin_eventos', ['pageTitle' => 'Sin Eventos'], 'none');
            return;
        }

        $pagos    = $this->pagoModel->listarPorEvento($evento['id']);
        $resumen  = $this->pagoModel->getResumenPago($evento['id']);

        $this->render('cliente/dashboard', [
            'pageTitle' => 'Mi Evento: ' . e($evento['nombre_evento']),
            'e'         => $evento,
            'pagos'     => $pagos,
            'resumen'   => $resumen,
            'tenant'    => $this->tenant
        ], 'none');
    }
}
