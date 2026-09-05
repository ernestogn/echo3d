<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/PagoModel.php';
require_once APP_ROOT . '/app/models/EventoModel.php';

/**
 * PagosController
 */
class PagosController extends BaseController {

    private PagoModel  $model;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->model = new PagoModel($pdo);
    }

    public function index(?int $id = null): void {
        $this->requireRole('pagos');
        $alertas = $this->model->eventosPendientesPago(
            $this->tenant['alerta_saldo_dias'] ?? 3
        );
        $this->render('tenant/pagos/index', [
            'pageTitle'  => 'Cobros y Pagos',
            'activePage' => 'pagos',
            'alertas'    => $alertas,
            'flash'      => $this->getFlash(),
            'tenant'     => $this->tenant,
        ]);
    }

    /**
     * POST /[slug]/pagos/registrar/[evento_id]
     * Registrar un nuevo pago en un evento
     */
    public function registrar(?int $id = null): void {
        $this->requireRole('pagos');
        if (!$id || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pagos');
        }
        $this->verifyCsrf();

        $resultado = $this->model->registrar($id, $_POST);
        if ($resultado['success']) {
            $this->flash('success', 'Pago registrado correctamente.');
        } else {
            $this->flash('error', $resultado['message']);
        }
        // Redirigir al detalle del evento
        $this->redirect('eventos/' . $id);
    }

    /**
     * POST /[slug]/pagos/eliminar/[pago_id]
     * Solo admins pueden eliminar pagos
     */
    public function eliminar(?int $id = null): void {
        $this->requireRole('pagos');
        RoleMiddleware::check('empleados', $this->loginUrl); // reutilizamos permiso de solo-admin
        if (!$id || $_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('pagos');
        $this->verifyCsrf();

        // Obtener evento_id antes de borrar para redirigir
        $stmt = $this->pdo->prepare(
            "SELECT evento_id FROM carp_pagos WHERE id=? AND tenant_id=? LIMIT 1"
        );
        $stmt->execute([$id, tenantId()]);
        $pago = $stmt->fetch();

        $res = $this->model->eliminar($id);
        if ($res['success']) {
            $this->flash('success','Pago eliminado.');
        } else {
            $this->flash('error',$res['message']);
        }

        if ($pago) $this->redirect('eventos/' . $pago['evento_id']);
        else $this->redirect('pagos');
    }
}
