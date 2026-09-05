<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/funciones_calendario.php';

// Verificar autenticación y permisos de administrador
require_once __DIR__ . '/../auth_check.php';
if (!esAdmin()) {
    header('Location: ../index.php');
    exit;
}

// Inicializar datos del calendario
$datosCalendario = inicializarDatosCalendarioCalendario();
$tipos_evento = $datosCalendario['tipos_evento'];
$categorias = $datosCalendario['categorias'];
$empresas = $datosCalendario['empresas'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Eventos - Sistema de Empresas</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css" rel="stylesheet">

    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="calendario.css">
</head>
<body>
<?php include_once __DIR__ . '/../header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2 mb-0"><i class="bi bi-calendar-event-fill text-primary"></i> Calendario de Eventos</h1>
                    <p class="text-muted mb-0">Gestión de eventos empresariales</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Botones Minimalista -->
    <div class="row">
        <div class="col-12">
            <div class="button-bar">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <button type="button" class="btn-minimal" id="btnNuevo">
                            <i class="bi bi-plus-circle"></i> Nuevo Evento
                        </button>
                        <button type="button" class="btn-minimal" id="btnImportar">
                            <i class="bi bi-upload"></i> Importar JSON
                        </button>
                        <button type="button" class="btn-minimal" id="btnConvertirTexto">
                            <i class="bi bi-magic"></i> Convertir Texto
                        </button>
                        <a href="admin/api_keys_admin.php" class="btn-minimal" target="_blank">
                            <i class="bi bi-key"></i> API Keys
                        </a>
                    </div>
                    <div class="text-muted small">
                        Solo administradores
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Calendario -->
    <div class="row">
        <div class="col-12">
            <div class="calendar-container">
                <div id="calendario"></div>
            </div>
        </div>
    </div>

    <!-- Barra de Estado -->
    <div class="row">
        <div class="col-12">
            <div class="status-bar">
                <div class="d-flex flex-wrap align-items-center" id="statusInfo">
                    <div class="status-item">
                        <span class="status-dot bg-primary"></span>
                        <span id="totalEventos">0</span> eventos totales
                    </div>
                    <div class="status-item">
                        <span class="status-dot bg-success"></span>
                        <span id="eventosHoy">0</span> eventos hoy
                    </div>
                    <div class="status-item">
                        <span class="status-dot bg-warning"></span>
                        <span id="eventosSemana">0</span> eventos esta semana
                    </div>
                    <div class="status-item">
                        <span class="status-dot bg-info"></span>
                        Última actualización: <span id="ultimaActualizacion">-</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario Nuevo Evento -->
    <div class="row">
        <div class="col-12">
            <div class="form-section" id="formNuevoEvento">
                <h4><i class="bi bi-plus-circle"></i> Crear Nuevo Evento</h4>
                <form id="formularioCrearEvento" class="mt-3">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Título del Evento *</label>
                            <input type="text" name="titulo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo de Evento *</label>
                            <select name="tipo_evento" id="tipo_evento" class="form-select" required>
                                <option value="">Cargando tipos...</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Empresa Organizadora</label>
                            <div class="position-relative">
                                <input type="text" id="busquedaEmpresaEvento" class="form-control" placeholder="Buscar empresa..." autocomplete="off">
                                <input type="hidden" name="empresa_organizadora_id" id="empresaEventoSeleccionada">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe el evento..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha y Hora de Inicio *</label>
                            <input type="datetime-local" name="fecha_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha y Hora de Fin</label>
                            <input type="datetime-local" name="fecha_fin" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ubicación</label>
                            <input type="text" name="ubicacion" class="form-control" placeholder="Dirección o lugar del evento">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Capacidad Máxima</label>
                            <input type="number" name="capacidad_maxima" class="form-control" placeholder="0 = sin límite">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Costo de Participación</label>
                            <input type="number" name="costo_participacion" class="form-control" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Categorías</label>
                            <div id="categoriasEvento">
                                <!-- Se carga dinámicamente -->
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-secondary me-2" id="btnCancelarNuevo">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Crear Evento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulario Importar JSON -->
    <div class="row">
        <div class="col-12">
            <div class="form-section" id="formImportarJSON">
                <h4><i class="bi bi-upload"></i> Importar Eventos desde JSON</h4>
                <form id="formularioImportarEventos" class="mt-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Archivo JSON</label>
                            <input type="file" id="archivoImportacion" class="form-control" accept=".json">
                            <div class="form-text">
                                Selecciona un archivo JSON con eventos para importar
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">O pega el JSON directamente</label>
                            <textarea id="jsonImportacion" class="form-control" rows="8" placeholder='Pega aquí el JSON de eventos...'></textarea>
                        </div>
                    </div>
                    <div id="previewImportacion" class="mt-3" style="display: none;">
                        <h6>Vista previa de la importación:</h6>
                        <div id="contenidoPreview" class="border rounded p-3 bg-light">
                            <!-- Contenido se carga aquí -->
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-secondary me-2" id="btnCancelarImportar">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btnImportar" disabled>
                            <i class="bi bi-upload"></i> Importar Eventos
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulario Convertir Texto a JSON -->
    <div class="row">
        <div class="col-12">
            <div class="form-section" id="formConvertirTexto">
                <h4><i class="bi bi-magic"></i> Convertir Texto de Anuncio a JSON</h4>
                <div class="mt-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Texto del Anuncio</label>
                            <textarea id="textoAnuncio" class="form-control" rows="8" placeholder="Pega aquí el texto del anuncio del evento..."></textarea>
                            <div class="form-text">
                                Pega el texto completo del anuncio para convertirlo a formato JSON
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-info" id="btnGenerarPrompt">
                                <i class="bi bi-robot"></i> Generar Prompt para Chatbot
                            </button>
                        </div>
                        <div class="col-12" id="promptGeneradoContainer" style="display: none;">
                            <label class="form-label">Prompt Generado (cópialo y pégalo en tu chatbot)</label>
                            <textarea id="promptGenerado" class="form-control" rows="12" readonly></textarea>
                            <div class="d-flex justify-content-end mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCopiarPrompt">
                                    <i class="bi bi-clipboard"></i> Copiar al Portapapeles
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">JSON Resultante del Chatbot</label>
                            <textarea id="jsonConvertido" class="form-control" rows="8" placeholder='Pega aquí el JSON que te devolvió el chatbot...'></textarea>
                            <div class="form-text">
                                Una vez que el chatbot te devuelva el JSON, pégalo aquí para importarlo
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-secondary me-2" id="btnCancelarConvertir">Cancelar</button>
                        <button type="button" class="btn btn-success" id="btnImportarConvertido" disabled>
                            <i class="bi bi-check-circle"></i> Importar Evento
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Evento -->
<div class="modal fade" id="modalEditarEvento" tabindex="-1" aria-labelledby="modalEditarEventoLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarEventoLabel">
                    <i class="bi bi-pencil-square"></i> Editar Evento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formularioEditarEvento">
                    <input type="hidden" name="evento_id" id="edit_evento_id">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Título del Evento *</label>
                            <input type="text" name="titulo" id="edit_titulo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo de Evento *</label>
                            <select name="tipo_evento" id="edit_tipo_evento" class="form-select" required>
                                <option value="">Cargando tipos...</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Empresa Organizadora</label>
                            <div class="position-relative">
                                <input type="text" id="edit_busqueda_empresa" class="form-control" placeholder="Buscar empresa..." autocomplete="off">
                                <input type="hidden" name="empresa_organizadora_id" id="edit_empresa_seleccionada">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="3" placeholder="Describe el evento..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha y Hora de Inicio *</label>
                            <input type="datetime-local" name="fecha_inicio" id="edit_fecha_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha y Hora de Fin</label>
                            <input type="datetime-local" name="fecha_fin" id="edit_fecha_fin" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ubicación</label>
                            <input type="text" name="ubicacion" id="edit_ubicacion" class="form-control" placeholder="Dirección o lugar del evento">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Capacidad Máxima</label>
                            <input type="number" name="capacidad_maxima" id="edit_capacidad_maxima" class="form-control" placeholder="0 = sin límite">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Costo de Participación</label>
                            <input type="number" name="costo_participacion" id="edit_costo_participacion" class="form-control" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Categorías</label>
                            <div id="edit_categorias_evento">
                                <!-- Se carga dinámicamente -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="btnGuardarEdicion">
                    <i class="bi bi-check-circle"></i> Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/locales/es.js"></script>

<!-- JavaScript del calendario -->
<script src="calendario.js"></script>
</body>
</html>
