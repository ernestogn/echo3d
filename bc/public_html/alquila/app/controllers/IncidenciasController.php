<?php
require_once APP_ROOT . '/app/core/BaseController.php';
require_once APP_ROOT . '/app/models/IncidenciaModel.php';
require_once APP_ROOT . '/app/models/EventoModel.php';

/**
 * IncidenciasController — Gestión de problemas/daños
 */
class IncidenciasController extends BaseController {

    private IncidenciaModel $model;
    private EventoModel     $eventoModel;

    public function __construct(PDO $pdo, ?array $tenant, ?string $tenantSlug) {
        parent::__construct($pdo, $tenant, $tenantSlug);
        $this->model       = new IncidenciaModel($pdo);
        $this->eventoModel = new EventoModel($pdo);
    }

    public function index(?int $id = null): void {
        $this->requireAuth();
        $filtros = [
            'estado'    => $_GET['estado']    ?? '',
            'evento_id' => $_GET['evento_id'] ?? ''
        ];
        $incidencias = $this->model->listar($filtros);

        $this->render('tenant/incidencias/index', [
            'pageTitle'   => 'Incidencias',
            'activePage'  => 'incidencias',
            'incidencias' => $incidencias,
            'filtros'     => $filtros,
            'flash'       => $this->getFlash()
        ]);
    }

    public function crear(?int $id = null): void {
        $this->requireAuth();
        $eventos = $this->eventoModel->listar(['estado_not' => 'cancelado']); // Solo eventos reales

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $incId = $this->model->crear($_POST);
            
            // Manejar fotos
            if (!empty($_FILES['fotos']['name'][0])) {
                $fotosGuardadas = [];
                foreach ($_FILES['fotos']['name'] as $i => $name) {
                    $fileArr = [
                        'name'     => $_FILES['fotos']['name'][$i],
                        'type'     => $_FILES['fotos']['type'][$i],
                        'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                        'error'    => $_FILES['fotos']['error'][$i],
                        'size'     => $_FILES['fotos']['size'][$i]
                    ];
                    $res = subirImagen($fileArr, 'incidencias');
                    if ($res['success']) {
                        $fotosGuardadas[] = $res['path'];
                    }
                }
                if ($fotosGuardadas) {
                    $this->model->guardarFotos($incId, $fotosGuardadas);
                }
            }

            $this->flash('success', 'Incidencia registrada correctamente.');
            $this->redirect('incidencias');
        }

        $this->render('tenant/incidencias/formulario', [
            'pageTitle'  => 'Reportar Incidencia',
            'activePage' => 'incidencias',
            'eventos'    => $eventos,
            'incidencia' => null,
            'datos'      => ['evento_id' => $_GET['evento_id'] ?? '']
        ]);
    }

    public function editar(?int $id = null): void {
        $this->requireAuth();
        $incidencia = $id ? $this->model->findById($id) : null;
        if (!$incidencia) $this->redirect('incidencias');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->model->actualizar($id, $_POST);
            
            // Más fotos
            if (!empty($_FILES['fotos']['name'][0])) {
                // Similar al crear... (podría refactorizarse)
            }

            $this->flash('success', 'Incidencia actualizada.');
            $this->redirect('incidencias');
        }

        $this->render('tenant/incidencias/formulario', [
            'pageTitle'  => 'Gestionar Incidencia #' . $id,
            'activePage' => 'incidencias',
            'incidencia' => $incidencia,
            'datos'      => $incidencia
        ]);
    }
}
