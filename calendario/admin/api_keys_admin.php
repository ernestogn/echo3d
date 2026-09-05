<?php
/**
 * Panel de Administración de API Keys
 *
 * Interfaz web para gestionar las API keys de la API pública de eventos.
 * Permite crear, editar, activar/desactivar y monitorear el uso de las keys.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once '../api/api_keys_config.php';

// Verificar autenticación de administrador
if (!estaAutenticado()) {
    header('Location: ../login.php');
    exit;
}

// Solo administradores pueden acceder
if (!esAdmin()) {
    header('Location: ../index.php');
    exit;
}

// NOTA: Las keys se generan automáticamente en la tabla HTML más abajo
// No necesitamos hacerlo aquí para evitar problemas de headers

// Procesar acciones POST
$mensaje = '';
$tipo_mensaje = '';

// Crear nueva API key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    switch ($_POST['accion']) {
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            $limite = intval($_POST['limite'] ?? 1000);
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (empty($nombre)) {
                $mensaje = 'El nombre es obligatorio';
                $tipo_mensaje = 'error';
            } elseif (isset($API_KEYS[$nombre])) {
                $mensaje = 'Ya existe una API key con ese nombre';
                $tipo_mensaje = 'error';
            } else {
                // Generar nueva key
                require_once '../api/generate_api_key.php';
                $api_key = generarApiKey();
                $hash = generarHashApiKey($api_key);

                // Agregar al array global
                global $API_KEYS;
                $API_KEYS[$nombre] = [
                    'hash' => $hash,
                    'activa' => true,
                    'limite_diario' => $limite,
                    'descripcion' => $descripcion,
                    'fecha_creacion' => date('Y-m-d'),
                    'creada_por' => $_SESSION['usuario_id']
                ];

                // Guardar en archivo
                if (agregarKeyAConfiguracion($nombre, $hash, $limite, $descripcion)) {
                    $mensaje = "API key creada exitosamente. <strong>Guarda esta key:</strong> <code>{$api_key}</code>";
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al guardar la configuración';
                    $tipo_mensaje = 'error';
                }
            }
            break;

        case 'editar':
            $nombre = $_POST['nombre_editar'] ?? '';
            $limite = intval($_POST['limite_editar'] ?? 1000);
            $descripcion = trim($_POST['descripcion_editar'] ?? '');
            $activa = isset($_POST['activa_editar']);

            if (!isset($API_KEYS[$nombre])) {
                $mensaje = 'API key no encontrada';
                $tipo_mensaje = 'error';
            } else {
                global $API_KEYS;
                $API_KEYS[$nombre]['limite_diario'] = $limite;
                $API_KEYS[$nombre]['descripcion'] = $descripcion;
                $API_KEYS[$nombre]['activa'] = $activa;

                // Guardar cambios en archivo
                if (guardarConfiguracionAPI()) {
                    $mensaje = 'API key actualizada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al guardar los cambios';
                    $tipo_mensaje = 'error';
                }
            }
            break;

        case 'eliminar':
            $nombre = $_POST['nombre_eliminar'] ?? '';

            if (!isset($API_KEYS[$nombre])) {
                $mensaje = 'API key no encontrada';
                $tipo_mensaje = 'error';
            } else {
                global $API_KEYS;
                unset($API_KEYS[$nombre]);

                // Eliminar también de la base de datos si existe
                try {
                    $stmt = $pdo->prepare("DELETE FROM api_keys WHERE nombre = ?");
                    $stmt->execute([$nombre]);
                } catch (Exception $e) {
                    // Si falla la BD, continuar con el archivo
                }

                // Guardar cambios en archivo
                if (guardarConfiguracionAPI()) {
                    $mensaje = 'API key eliminada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al eliminar la API key del archivo de configuración';
                    $tipo_mensaje = 'error';
                }
            }
            break;

        case 'regenerar':
            $nombre = $_POST['nombre_regenerar'] ?? '';

            if (!isset($API_KEYS[$nombre])) {
                $mensaje = 'API key no encontrada';
                $tipo_mensaje = 'error';
            } else {
                // Generar nueva API key
                require_once '../api/generate_api_key.php';
                $nueva_api_key = generarApiKey();
                $nuevo_hash = generarHashApiKey($nueva_api_key);

                // Obtener datos actuales de la key
                $datos_actuales = $API_KEYS[$nombre];
                $limite = $datos_actuales['limite_diario'] ?? 1000;
                $descripcion = $datos_actuales['descripcion'] ?? '';

                // Actualizar en BD (esto reemplazará la key existente)
                if (agregarKeyAConfiguracion($nombre, $nuevo_hash, $limite, $descripcion, null, $nueva_api_key)) {
                    $mensaje = "API key regenerada exitosamente. <strong>Nueva key:</strong> <code>{$nueva_api_key}</code><br>";
                    $mensaje .= "<strong>IMPORTANTE:</strong> Actualiza a tus clientes con la nueva API key.";
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al regenerar la API key';
                    $tipo_mensaje = 'error';
                }
            }
            break;

        case 'mostrar_completa':
            $nombre = $_POST['nombre_mostrar'] ?? '';

            if (!isset($API_KEYS[$nombre])) {
                $mensaje = 'API key no encontrada';
                $tipo_mensaje = 'error';
            } else {
                // Como administrador, intentar "recuperar" la key completa
                // Nota: Las keys antiguas no se pueden recuperar realmente por seguridad
                // Pero como administrador del sistema, tiene derecho a ver todas las keys

                // Para las keys existentes sin api_key_plana, generamos una nueva
                // y la guardamos como la "versión oficial" para el administrador
                $datos_actuales = $API_KEYS[$nombre];
                $hash_actual = $datos_actuales['hash'];
                $limite = $datos_actuales['limite_diario'] ?? 1000;
                $descripcion = $datos_actuales['descripcion'] ?? '';

                // Generar una nueva key que reemplazará a la existente
                require_once '../api/generate_api_key.php';
                $nueva_api_key = generarApiKey();
                $nuevo_hash = generarHashApiKey($nueva_api_key);

                // Actualizar la BD con la nueva key completa
                if (agregarKeyAConfiguracion($nombre, $nuevo_hash, $limite, $descripcion, null, $nueva_api_key)) {
                    $mensaje = "API key mostrada exitosamente. <strong>Key completa:</strong> <code>{$nueva_api_key}</code><br>";
                    $mensaje .= "<strong>NOTA:</strong> Esta es ahora la versión oficial de la API key.";
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al mostrar la API key completa';
                    $tipo_mensaje = 'error';
                }
            }
            break;
    }
}



/**
 * Recuperar API key existente para administrador
 * Como administrador root, intentar recuperar keys existentes
 */
function recuperarApiKeyExistente($nombre) {
    global $API_KEYS;

    // Para keys existentes, no se pueden recuperar por seguridad criptográfica
    // El hash HMAC-SHA256 es irreversible sin la key original
    // Como administrador, mostrar mensaje explicativo
    return false; // No se puede recuperar
}

/**
 * Obtener logs de API para una key específica
 */
function obtenerLogsAPI($api_key_nombre, $dias = 7) {
    $logs = [];
    $log_dir = '../logs';

    if (!is_dir($log_dir)) {
        return $logs;
    }

    for ($i = 0; $i < $dias; $i++) {
        $fecha = date('Y-m-d', strtotime("-{$i} days"));
        $log_file = $log_dir . '/api_access_' . $fecha . '.log';

        if (file_exists($log_file)) {
            $contenido = file_get_contents($log_file);
            $lineas = explode("\n", $contenido);

            foreach ($lineas as $linea) {
                if (strpos($linea, "API_KEY: {$api_key_nombre} |") !== false) {
                    $logs[] = $linea;
                }
            }
        }
    }

    return array_slice($logs, -50); // Últimos 50 logs
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de API Keys - Sistema de Eventos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-active { color: #28a745; }
        .status-inactive { color: #dc3545; }
        .api-key { font-family: monospace; font-size: 0.9em; }
        .logs-container { max-height: 300px; overflow-y: auto; }
        .log-entry { font-family: monospace; font-size: 0.8em; padding: 2px 0; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="../../Admin/index.php">Admin</a></li>
                        <li class="breadcrumb-item active">API Keys Calendario</li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-key"></i> Administración de API Keys</h1>
                    <a href="../api/generate_api_key.php" class="btn btn-primary" target="_blank">
                        <i class="fas fa-plus"></i> Nueva API Key
                    </a>
                </div>

                <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_mensaje === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <?= $mensaje ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-key"></i> Total Keys</h5>
                                <h2><?= count($API_KEYS) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-check-circle"></i> Activas</h5>
                                <h2><?= count(array_filter($API_KEYS, fn($k) => $k['activa'])) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-pause-circle"></i> Inactivas</h5>
                                <h2><?= count(array_filter($API_KEYS, fn($k) => !$k['activa'])) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-clock"></i> Rate Limit</h5>
                                <h6>Default: <?= $API_CONFIG['rate_limit_default'] ?>/hora</h6>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de API Keys -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> API Keys Existentes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($API_KEYS)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No hay API keys configuradas aún.
                            <a href="../api/generate_api_key.php" class="btn btn-primary btn-sm ms-2" target="_blank">
                                Crear primera API key
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th>Límite/Hora</th>
                                        <th>API Key</th>
                                        <th>Descripción</th>
                                        <th>Fecha Creación</th>
                                        <th>Último Uso</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($API_KEYS as $nombre => $datos): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($nombre) ?></strong>
                                            <br>
                                            <small class="text-muted api-key">
                                                Hash: <?= substr($datos['hash'], 0, 16) ?>...
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($datos['activa']): ?>
                                                <span class="badge bg-success status-active">
                                                    <i class="fas fa-check-circle"></i> Activa
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger status-inactive">
                                                    <i class="fas fa-times-circle"></i> Inactiva
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <i class="fas fa-clock"></i> <?= $datos['limite_diario'] ?? 1000 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            // Obtener API key completa desde BD
                                            $stmt = $pdo->prepare("SELECT id, api_key_plana FROM api_keys WHERE nombre = ?");
                                            $stmt->execute([$nombre]);
                                            $api_key_data = $stmt->fetch();

                                            if ($api_key_data && $api_key_data['api_key_plana']) {
                                                // Key disponible - mostrar completa
                                                echo '<code class="text-break" style="font-size: 0.8em; word-break: break-all;">' . htmlspecialchars($api_key_data['api_key_plana']) . '</code>';
                                                echo '<br><button class="btn btn-sm btn-outline-secondary mt-1" onclick="copiarAlPortapapeles(\'' . $api_key_data['api_key_plana'] . '\')">';
                                                echo '<i class="fas fa-copy"></i> Copiar</button>';
                                            } else {
                                                // Key antigua - no recuperable por seguridad
                                                echo '<em class="text-warning">Key antigua no visible</em>';
                                                echo '<br><small class="text-muted">Usa el generador para nueva versión</small>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($datos['descripcion'] ?: 'Sin descripción') ?>
                                        </td>
                                        <td>
                                            <?= $datos['fecha_creacion'] ?? 'N/A' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $logs = obtenerLogsAPI($nombre, 1);
                                            if (!empty($logs)) {
                                                $ultimo_log = explode(' ', $logs[count($logs)-1])[0] ?? '';
                                                echo $ultimo_log;
                                            } else {
                                                echo '<em class="text-muted">Nunca</em>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                        data-bs-toggle="modal" data-bs-target="#modalEditarKey"
                                                        onclick="editarKey('<?= htmlspecialchars($nombre) ?>', '<?= $datos['limite_diario'] ?? 1000 ?>', '<?= htmlspecialchars($datos['descripcion'] ?? '') ?>', <?= $datos['activa'] ? 'true' : 'false' ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-info"
                                                        data-bs-toggle="modal" data-bs-target="#modalLogsKey"
                                                        onclick="verLogs('<?= htmlspecialchars($nombre) ?>')">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal" data-bs-target="#modalEliminarKey"
                                                        onclick="eliminarKey('<?= htmlspecialchars($nombre) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Crear API Key -->
    <div class="modal fade" id="modalCrearKey" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Crear Nueva API Key</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="crear">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del Cliente *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required
                                   placeholder="ej: app_movil, sitio_web, cliente_x">
                            <div class="form-text">Solo letras, números, guiones y guiones bajos</div>
                        </div>
                        <div class="mb-3">
                            <label for="limite" class="form-label">Límite de Requests por Hora</label>
                            <input type="number" class="form-control" id="limite" name="limite" value="1000" min="1" max="100000">
                            <div class="form-text">Máximo 100,000 requests por hora</div>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                                      placeholder="Descripción del cliente o uso de la API key"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear API Key</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar API Key -->
    <div class="modal fade" id="modalEditarKey" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar API Key</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" id="nombre_editar" name="nombre_editar">
                        <div class="mb-3">
                            <label class="form-label">Nombre del Cliente</label>
                            <input type="text" class="form-control" id="nombre_editar_display" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="limite_editar" class="form-label">Límite de Requests por Hora</label>
                            <input type="number" class="form-control" id="limite_editar" name="limite_editar" min="1" max="100000">
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_editar" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion_editar" name="descripcion_editar" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="activa_editar" name="activa_editar">
                                <label class="form-check-label" for="activa_editar">
                                    API Key Activa
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar API Key -->
    <div class="modal fade" id="modalEliminarKey" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Eliminar API Key</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" id="nombre_eliminar" name="nombre_eliminar">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            ¿Estás seguro de que quieres eliminar la API key <strong id="nombre_eliminar_display"></strong>?
                        </div>
                        <p class="text-muted">Esta acción no se puede deshacer. Los clientes que usen esta key perderán acceso inmediato.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar API Key</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ver Logs -->
    <div class="modal fade" id="modalLogsKey" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-history"></i> Logs de Uso - <span id="logs_key_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="logs_container" class="logs-container">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para copiar al portapapeles
        async function copiarAlPortapapeles(texto) {
            try {
                await navigator.clipboard.writeText(texto);
                // Mostrar feedback temporal
                const btn = event.target;
                if (btn) {
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-success');

                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-secondary');
                    }, 2000);
                } else {
                    alert('API Key copiada al portapapeles correctamente');
                }
            } catch (err) {
                // Fallback para navegadores antiguos
                const textArea = document.createElement('textarea');
                textArea.value = texto;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);

                alert('API Key copiada al portapapeles correctamente');
            }
        }

        function editarKey(nombre, limite, descripcion, activa) {
            document.getElementById('nombre_editar').value = nombre;
            document.getElementById('nombre_editar_display').value = nombre;
            document.getElementById('limite_editar').value = limite;
            document.getElementById('descripcion_editar').value = descripcion;
            document.getElementById('activa_editar').checked = activa;
        }

        function eliminarKey(nombre) {
            document.getElementById('nombre_eliminar').value = nombre;
            document.getElementById('nombre_eliminar_display').textContent = nombre;
        }

        function mostrarKeyInmediato(nombre) {
            // Como administrador del sistema, generar nueva key inmediatamente sin confirmaciones
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const accionInput = document.createElement('input');
            accionInput.type = 'hidden';
            accionInput.name = 'accion';
            accionInput.value = 'mostrar_completa';

            const nombreInput = document.createElement('input');
            nombreInput.type = 'hidden';
            nombreInput.name = 'nombre_mostrar';
            nombreInput.value = nombre;

            form.appendChild(accionInput);
            form.appendChild(nombreInput);
            document.body.appendChild(form);

            form.submit();
        }

        function regenerarKey(nombre) {
            if (confirm(`¿Regenerar API key para "${nombre}"?\n\nEsto creará una nueva API key y reemplazará la existente.\nLa nueva key se mostrará y guardará en la base de datos.\n\n¿Continuar?`)) {
                // Crear formulario dinámico para enviar
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';

                const accionInput = document.createElement('input');
                accionInput.type = 'hidden';
                accionInput.name = 'accion';
                accionInput.value = 'regenerar';

                const nombreInput = document.createElement('input');
                nombreInput.type = 'hidden';
                nombreInput.name = 'nombre_regenerar';
                nombreInput.value = nombre;

                form.appendChild(accionInput);
                form.appendChild(nombreInput);
                document.body.appendChild(form);

                form.submit();
            }
        }

        function verLogs(nombre) {
            document.getElementById('logs_key_name').textContent = nombre;
            document.getElementById('logs_container').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

            // Cargar logs via AJAX (simulado)
            setTimeout(() => {
                fetch(`api_keys_logs.php?key=${encodeURIComponent(nombre)}`)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('logs_container').innerHTML = html;
                    })
                    .catch(error => {
                        document.getElementById('logs_container').innerHTML = '<div class="alert alert-danger">Error al cargar logs</div>';
                    });
            }, 500);
        }
    </script>
</body>
</html>
