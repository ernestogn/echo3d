<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/EventoModel.php';
require_once APP_ROOT . '/app/models/PagoModel.php';

/**
 * DashboardController — Panel principal del tenant
 */
class DashboardController extends BaseController {

    public function index(?int $id = null): void {
        $this->requireAuth();
        $rol = $_SESSION['usuario_rol'] ?? '';

        // Redirigir técnicos y clientes a su vista específica
        if ($rol === ROL_TECNICO) {
            $this->redirect('tareas');
        }
        if ($rol === ROL_CLIENTE) {
            $this->redirect('portal/mi-evento');
        }

        $eventoModel = new EventoModel($this->pdo);
        $pagoModel   = new PagoModel($this->pdo);
        $tid         = $this->tenantId();

        // KPIs
        $stmtKpi = $this->pdo->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado NOT IN ('cancelado','cerrado') THEN 1 ELSE 0 END) as activos,
                SUM(CASE WHEN estado='en_curso' THEN 1 ELSE 0 END) as en_curso,
                SUM(CASE WHEN MONTH(fecha_inicio_evento)=MONTH(NOW()) AND YEAR(fecha_inicio_evento)=YEAR(NOW()) THEN 1 ELSE 0 END) as este_mes
             FROM carp_eventos WHERE tenant_id=?"
        );
        $stmtKpi->execute([$tid]);
        $kpis = $stmtKpi->fetch();

        // Próximos eventos (7 días)
        $stmtProximos = $this->pdo->prepare(
            "SELECT e.*, c.nombre as cliente_nombre,
                    COALESCE(SUM(p.monto),0) as cobrado,
                    (e.precio_total - COALESCE(SUM(p.monto),0)) as saldo
             FROM carp_eventos e
             LEFT JOIN carp_clientes c ON c.id=e.cliente_id AND c.tenant_id=e.tenant_id
             LEFT JOIN carp_pagos p ON p.evento_id=e.id AND p.tenant_id=e.tenant_id
             WHERE e.tenant_id=? AND e.estado NOT IN ('cancelado','cerrado')
               AND e.fecha_inicio_evento BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 7 DAY)
             GROUP BY e.id ORDER BY e.fecha_inicio_evento LIMIT 5"
        );
        $stmtProximos->execute([$tid]);
        $proximosEventos = $stmtProximos->fetchAll();

        // Incidencias abiertas
        $stmtInc = $this->pdo->prepare(
            "SELECT COUNT(*) FROM carp_incidencias WHERE tenant_id=? AND estado='abierta'"
        );
        $stmtInc->execute([$tid]);
        $incidenciasAbiertas = $stmtInc->fetchColumn();

        // Alertas de saldo pendiente
        $alertasSaldo = $pagoModel->eventosPendientesPago(
            $this->tenant['alerta_saldo_dias'] ?? 3
        );

        $this->render('tenant/dashboard/index', [
            'pageTitle'         => 'Dashboard',
            'activePage'        => 'dashboard',
            'kpis'              => $kpis,
            'proximosEventos'   => $proximosEventos,
            'incidenciasAbiertas'=> $incidenciasAbiertas,
            'alertasSaldo'      => $alertasSaldo,
            'flash'             => $this->getFlash(),
            'tenant'            => $this->tenant,
        ]);
    }
}
