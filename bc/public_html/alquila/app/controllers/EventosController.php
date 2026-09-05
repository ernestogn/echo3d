<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/EventoModel.php';
require_once APP_ROOT . '/app/models/ClienteModel.php';
require_once APP_ROOT . '/app/models/PagoModel.php';
require_once APP_ROOT . '/app/models/InventarioModel.php';

/**
 * EventosController — CRUD de eventos + asignación de equipos
 */
class EventosController extends BaseController {

    private EventoModel    $eventoModel;
    private ClienteModel   $clienteModel;
    private InventarioModel $inventarioModel;
    private PagoModel      $pagoModel;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->eventoModel     = new EventoModel($pdo);
        $this->clienteModel    = new ClienteModel($pdo);
        $this->inventarioModel = new InventarioModel($pdo);
        $this->pagoModel       = new PagoModel($pdo);
    }

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(?int $id = null): void {
        $this->requireRole('eventos');
        $filtros = [
            'estado'      => $_GET['estado']      ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
        ];
        $eventos = $this->eventoModel->listar($filtros);

        $this->render('tenant/eventos/index', [
            'pageTitle' => 'Eventos',
            'activePage'=> 'eventos',
            'eventos'   => $eventos,
            'filtros'   => $filtros,
            'estados'   => EventoModel::ESTADOS,
            'flash'     => $this->getFlash(),
            'tenant'    => $this->tenant,
        ]);
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public function crear(?int $id = null): void {
        $this->requireRole('eventos');
        $clientes = $this->clienteModel->listar();
        $equipos  = $this->inventarioModel->listar();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $resultado = $this->eventoModel->crear($_POST);

            if ($resultado['success']) {
                // Guardar equipos si se enviaron
                $equiposCantidades = $this->parsearEquipos($_POST);
                if ($equiposCantidades) {
                    $eventoInfo     = $this->eventoModel->findById($resultado['id']);
                    $resEquipos     = $this->eventoModel->guardarEquipos(
                        $resultado['id'],
                        $equiposCantidades,
                        $eventoInfo['fecha_montaje'],
                        $eventoInfo['fecha_desmontaje']
                    );
                    if (!$resEquipos['success']) {
                        $this->flash('error', $resEquipos['message']);
                    }
                }

                auditLog($this->pdo, 'create', 'carp_eventos', $resultado['id'], null, $_POST);
                $this->flash('success', 'Evento creado correctamente.');
                $this->redirect('eventos/' . $resultado['id']);
            } else {
                $this->render('tenant/eventos/formulario', [
                    'pageTitle'   => 'Nuevo Evento',
                    'activePage'  => 'eventos',
                    'error'       => $resultado['message'],
                    'datos'       => $_POST,
                    'clientes'    => $clientes,
                    'equipos'     => $equipos,
                    'evento'      => null,
                    'tenant'      => $this->tenant,
                ]);
                return;
            }
        }

        $this->render('tenant/eventos/formulario', [
            'pageTitle'  => 'Nuevo Evento',
            'activePage' => 'eventos',
            'datos'      => [],
            'clientes'   => $clientes,
            'equipos'    => $equipos,
            'evento'     => null,
            'tenant'     => $this->tenant,
        ]);
    }

    // ── Ver ───────────────────────────────────────────────────────────────────

    public function ver(?int $id = null): void {
        $this->requireRole('eventos');
        $evento = $id ? $this->eventoModel->findById($id) : null;
        if (!$evento) { $this->redirect('eventos'); }

        $equiposEvento  = $this->eventoModel->equiposDeEvento($id);
        $pagos          = $this->pagoModel->listarPorEvento($id);
        $resumenPagos   = $this->pagoModel->resumenEvento($id);
        $transiciones   = EventoModel::TRANSICIONES_VALIDAS[$evento['estado']] ?? [];

        $this->render('tenant/eventos/detalle', [
            'pageTitle'     => e($evento['nombre_evento']),
            'activePage'    => 'eventos',
            'evento'        => $evento,
            'equiposEvento' => $equiposEvento,
            'pagos'         => $pagos,
            'resumenPagos'  => $resumenPagos,
            'transiciones'  => $transiciones,
            'flash'         => $this->getFlash(),
            'tenant'        => $this->tenant,
        ]);
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function editar(?int $id = null): void {
        $this->requireRole('eventos');
        $evento = $id ? $this->eventoModel->findById($id) : null;
        if (!$evento) { $this->redirect('eventos'); }

        $clientes = $this->clienteModel->listar();
        $equipos  = $this->inventarioModel->listar();
        $equiposEvento = $this->eventoModel->equiposDeEvento($id);
        // Construir mapa equipo_id => cantidad para la vista
        $asignados = [];
        foreach ($equiposEvento as $e) { $asignados[$e['equipo_id']] = $e['cantidad']; }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $antes     = $evento;
            $resultado = $this->eventoModel->actualizar($id, $_POST);

            if ($resultado['success']) {
                $equiposCantidades = $this->parsearEquipos($_POST);
                $eventoActualizado = $this->eventoModel->findById($id);
                $resEquipos = $this->eventoModel->guardarEquipos(
                    $id, $equiposCantidades,
                    $eventoActualizado['fecha_montaje'],
                    $eventoActualizado['fecha_desmontaje']
                );
                if (!$resEquipos['success']) {
                    $this->flash('error', $resEquipos['message']);
                }
                auditLog($this->pdo, 'update', 'carp_eventos', $id, $antes, $_POST);
                $this->flash('success', 'Evento actualizado.');
                $this->redirect('eventos/' . $id);
            } else {
                $this->render('tenant/eventos/formulario', [
                    'pageTitle'  => 'Editar Evento',
                    'activePage' => 'eventos',
                    'error'      => $resultado['message'],
                    'datos'      => $_POST,
                    'clientes'   => $clientes,
                    'equipos'    => $equipos,
                    'evento'     => $evento,
                    'asignados'  => $asignados,
                    'tenant'     => $this->tenant,
                ]);
                return;
            }
        }

        $this->render('tenant/eventos/formulario', [
            'pageTitle'  => 'Editar: ' . e($evento['nombre_evento']),
            'activePage' => 'eventos',
            'datos'      => $evento,
            'clientes'   => $clientes,
            'equipos'    => $equipos,
            'evento'     => $evento,
            'asignados'  => $asignados,
            'tenant'     => $this->tenant,
        ]);
    }

    // ── Cambiar estado ────────────────────────────────────────────────────────

    public function cambiarEstado(?int $id = null): void {
        $this->requireRole('eventos');
        if (!$id || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('eventos');
        }
        $this->verifyCsrf();

        $nuevoEstado = $_POST['nuevo_estado'] ?? '';
        $motivo      = $_POST['motivo']       ?? null;

        $resultado = $this->eventoModel->cambiarEstado($id, $nuevoEstado, $motivo);
        if ($resultado['success']) {
            $this->flash('success', 'Estado actualizado a "' . $nuevoEstado . '".');
        } else {
            $this->flash('error', $resultado['message']);
        }
        $this->redirect('eventos/' . $id);
    }

    // ── API Calendario ────────────────────────────────────────────────────────

    public function calendario(?int $id = null): void {
        $this->requireRole('eventos');
        $this->render('tenant/eventos/calendario', [
            'pageTitle'  => 'Calendario de Eventos',
            'activePage' => 'eventos',
            'tenant'     => $this->tenant,
        ]);
    }

    // ── Disponibilidad (AJAX check) ───────────────────────────────────────────

    public function checkDisponibilidad(?int $id = null): void {
        $this->requireRole('eventos');
        $fechaMontaje    = $_POST['fecha_montaje']    ?? '';
        $fechaDesmontaje = $_POST['fecha_desmontaje'] ?? '';
        $equipos         = $this->parsearEquipos($_POST);
        $excluirId       = (int)($_POST['evento_id'] ?? 0) ?: null;

        $conflictos = $this->eventoModel->verificarDisponibilidad(
            $equipos, $fechaMontaje, $fechaDesmontaje, $excluirId
        );
        $this->json(['conflictos' => $conflictos, 'ok' => empty($conflictos)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parsearEquipos(array $post): array {
        // Espera: equipos[{id}] = cantidad (o equipo_ids[] + equipo_cantidades[])
        $equipos = [];
        if (!empty($post['equipos']) && is_array($post['equipos'])) {
            foreach ($post['equipos'] as $eid => $cantidad) {
                if ((int)$cantidad > 0) {
                    $equipos[(int)$eid] = (int)$cantidad;
                }
            }
        }
        return $equipos;
    }
}
