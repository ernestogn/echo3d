<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/EventoModel.php';

/**
 * ApiController — Endpoints JSON
 * Uso: /[slug]/api/[recurso]
 */
class ApiController extends BaseController {

    public function index(?int $id = null): void {
        $this->json(['status' => 'ok', 'app' => APP_NAME]);
    }

    /**
     * GET /[slug]/api/eventos
     * Devuelve eventos para FullCalendar.js
     */
    public function eventos(?int $id = null): void {
        $this->requireAuth();
        $model  = new EventoModel($this->pdo);
        $data   = $model->paraCalendario();
        $this->json($data);
    }

    /**
     * POST /[slug]/api/check-disponibilidad
     * Body: fecha_montaje, fecha_desmontaje, equipos[id]=cantidad
     */
    public function checkDisponibilidad(?int $id = null): void {
        $this->requireAuth();
        $model   = new EventoModel($this->pdo);
        $equipos = [];
        if (!empty($_POST['equipos']) && is_array($_POST['equipos'])) {
            foreach ($_POST['equipos'] as $eid => $cantidad) {
                if ((int)$cantidad > 0) $equipos[(int)$eid] = (int)$cantidad;
            }
        }
        $conflictos = $model->verificarDisponibilidad(
            $equipos,
            $_POST['fecha_montaje']    ?? '',
            $_POST['fecha_desmontaje'] ?? '',
            !empty($_POST['evento_id']) ? (int)$_POST['evento_id'] : null
        );
        $this->json(['ok' => empty($conflictos), 'conflictos' => $conflictos]);
    }
}
