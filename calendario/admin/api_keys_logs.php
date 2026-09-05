<?php
/**
 * Endpoint AJAX para cargar logs de API keys
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once '../api/api_keys_config.php';

// Verificar autenticación de administrador
if (!estaAutenticado()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Acceso denegado</div>';
    exit;
}

if (!esAdmin()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Acceso denegado</div>';
    exit;
}

$key_name = $_GET['key'] ?? '';

if (empty($key_name)) {
    echo '<div class="alert alert-warning">Nombre de API key requerido</div>';
    exit;
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

    return array_reverse($logs); // Más recientes primero
}

$logs = obtenerLogsAPI($key_name, 7);

if (empty($logs)) {
    echo '<div class="alert alert-info">';
    echo '<i class="fas fa-info-circle"></i> No se encontraron logs para esta API key en los últimos 7 días.';
    echo '</div>';
    exit;
}

echo '<div class="mb-2">';
echo '<small class="text-muted">Mostrando ' . count($logs) . ' registros de los últimos 7 días</small>';
echo '</div>';

echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px; background: #f8f9fa;">';

foreach ($logs as $log) {
    // Parsear el log entry
    // Formato esperado: [2025-12-13 16:02:24] API_KEY: test_client | ACTION: categorias | IP: 127.0.0.1 | UA: Test API Consumer/1.0

    if (preg_match('/^\[([^\]]+)\]\s+API_KEY:\s+([^|]+)\s+\|\s+ACTION:\s+([^|]+)\s+\|\s+IP:\s+([^|]+)\s+\|\s+UA:\s+(.+)$/', $log, $matches)) {
        $timestamp = $matches[1];
        $api_key = $matches[2];
        $action = $matches[3];
        $ip = $matches[4];
        $user_agent = $matches[5];

        // Determinar icono según acción
        $icon_class = 'fas fa-question-circle text-secondary';
        $action_color = 'secondary';

        switch (strtolower($action)) {
            case 'categorias':
                $icon_class = 'fas fa-tags text-info';
                $action_color = 'info';
                break;
            case 'listar':
                $icon_class = 'fas fa-list text-primary';
                $action_color = 'primary';
                break;
            case 'detalle':
                $icon_class = 'fas fa-eye text-success';
                $action_color = 'success';
                break;
            case 'calendario':
                $icon_class = 'fas fa-calendar text-warning';
                $action_color = 'warning';
                break;
            case 'empresas':
                $icon_class = 'fas fa-building text-info';
                $action_color = 'info';
                break;
        }

        echo '<div class="log-entry mb-2 p-2 border-start border-3 border-' . $action_color . ' bg-white rounded">';
        echo '<div class="d-flex justify-content-between align-items-start">';
        echo '<div class="flex-grow-1">';
        echo '<div class="d-flex align-items-center mb-1">';
        echo '<i class="' . $icon_class . ' me-2"></i>';
        echo '<strong class="text-' . $action_color . '">' . htmlspecialchars($action) . '</strong>';
        echo '<small class="text-muted ms-2">' . htmlspecialchars($timestamp) . '</small>';
        echo '</div>';
        echo '<div class="small text-muted">';
        echo '<i class="fas fa-globe-americas"></i> IP: ' . htmlspecialchars($ip) . '<br>';
        echo '<i class="fas fa-desktop"></i> UA: ' . htmlspecialchars(substr($user_agent, 0, 50)) . (strlen($user_agent) > 50 ? '...' : '');
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    } else {
        // Log con formato no esperado
        echo '<div class="log-entry mb-2 p-2 bg-light rounded">';
        echo '<small class="text-muted font-monospace">' . htmlspecialchars($log) . '</small>';
        echo '</div>';
    }
}

echo '</div>';
?>
