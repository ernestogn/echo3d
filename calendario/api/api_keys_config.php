<?php
/**
 * Configuración de API Keys para public_eventos.php
 *
 * Este archivo almacena las API keys hasheadas para acceso a la API pública.
 * Las keys se generan con el script generate_api_key.php
 */

// API Keys hasheadas (usar generate_api_key.php para generar nuevas)
// Formato: 'nombre_cliente' => ['hash' => 'hash_de_la_key', 'activa' => true, 'limite_diario' => 1000]
$API_KEYS = [
    // API keys se cargan dinámicamente desde la base de datos
];

// Configuración de seguridad
$API_CONFIG = [
    'requerir_api_key' => true,
    'permitir_sin_key_localhost' => true,
    'rate_limit_enabled' => true,
    'rate_limit_default' => 100,
    'rate_limit_ip_sin_key' => 50,
    'log_requests' => true,
    'ip_whitelist' => [],
    'ip_blacklist' => []
];

// Algoritmo de hash (usar HMAC-SHA256 para las keys)
define('API_KEY_SECRET', 'cambiar_este_secreto_en_produccion_' . hash('sha256', __FILE__));

// FUNCIONES DEL SISTEMA - NO MODIFICAR MANUALMENTE

function verificarApiKey($api_key) {
    global $API_KEYS;
    if (empty($api_key)) return false;
    $hash_recibido = hash_hmac('sha256', $api_key, API_KEY_SECRET);
    foreach ($API_KEYS as $nombre => $datos) {
        if (!$datos['activa']) continue;
        if (hash_equals($datos['hash'], $hash_recibido)) {
            return [
                'nombre' => $nombre,
                'limite_diario' => $datos['limite_diario'] ?? 1000,
                'descripcion' => $datos['descripcion'] ?? '',
                'valida' => true
            ];
        }
    }
    return false;
}

function verificarRateLimit($identificador, $limite = 100) {
    global $API_CONFIG;
    if (!$API_CONFIG['rate_limit_enabled']) return true;

    $cache_dir = sys_get_temp_dir() . '/api_rate_limit';
    if (!is_dir($cache_dir)) @mkdir($cache_dir, 0777, true);

    $hash_id = md5($identificador);
    $archivo = $cache_dir . '/' . $hash_id . '.json';
    $hora_actual = time();
    $ventana_tiempo = 3600;

    $datos = [];
    if (file_exists($archivo)) {
        $contenido = @file_get_contents($archivo);
        if ($contenido) $datos = json_decode($contenido, true) ?: [];
    }

    $datos['requests'] = array_filter($datos['requests'] ?? [], function($timestamp) use ($hora_actual, $ventana_tiempo) {
        return ($hora_actual - $timestamp) < $ventana_tiempo;
    });

    if (count($datos['requests']) >= $limite) return false;

    $datos['requests'][] = $hora_actual;
    @file_put_contents($archivo, json_encode($datos));
    return true;
}

function registrarAccesoAPI($api_key_nombre, $action, $ip) {
    global $API_CONFIG;
    if (!$API_CONFIG['log_requests']) return;

    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) @mkdir($log_dir, 0777, true);

    $log_file = $log_dir . '/api_access_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $log_entry = sprintf("[%s] API_KEY: %s | ACTION: %s | IP: %s | UA: %s\n",
        $timestamp, $api_key_nombre, $action, $ip, substr($user_agent, 0, 100));

    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function agregarKeyAConfiguracion($nombre, $hash, $limite = 1000, $descripcion = '', $producto_id = null, $api_key_plana = null) {
    global $pdo, $API_KEYS;

    try {
        // Si no se especifica producto, usar "Calendario" por defecto
        if (!$producto_id) {
            $stmt = $pdo->prepare("SELECT id FROM productos WHERE nombre = ? AND activo = 1");
            $stmt->execute(['Calendario']);
            $producto_id = $stmt->fetch()['id'] ?? null;

            if (!$producto_id) {
                throw new Exception("Producto 'Calendario' no encontrado");
            }
        }

        $usuario_actual = function_exists('obtenerUsuarioActual') ? obtenerUsuarioActual() : null;
        $creada_por = $usuario_actual ? $usuario_actual['id'] : 'generador_api';

        // Insertar en base de datos
        $stmt = $pdo->prepare("
            INSERT INTO api_keys (producto_id, nombre, hash, api_key_plana, limite_diario, descripcion, creada_por)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            hash = VALUES(hash),
            api_key_plana = VALUES(api_key_plana),
            limite_diario = VALUES(limite_diario),
            descripcion = VALUES(descripcion),
            ultima_modificacion = CURRENT_TIMESTAMP
        ");

        $resultado = $stmt->execute([$producto_id, $nombre, $hash, $api_key_plana, $limite, $descripcion, $creada_por]);

        if ($resultado) {
            // Obtener el ID de la API key insertada/actualizada
            $api_key_id = $pdo->lastInsertId();
            if (!$api_key_id) {
                // Si fue un UPDATE, buscar el ID existente
                $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE nombre = ? AND producto_id = ?");
                $stmt->execute([$nombre, $producto_id]);
                $api_key_id = $stmt->fetch()['id'];
            }

            // Por ahora no guardamos tokens temporales (requiere tabla adicional)

            // Actualizar el array en memoria
            $API_KEYS[$nombre] = [
                'id' => $api_key_id,
                'hash' => $hash,
                'activa' => true,
                'limite_diario' => $limite,
                'descripcion' => $descripcion,
                'fecha_creacion' => date('Y-m-d'),
                'creada_por' => $creada_por,
                'producto_id' => $producto_id
            ];
        }

        return $resultado;

    } catch (Exception $e) {
        error_log("Error guardando API key en BD: " . $e->getMessage());
        return false;
    }
}

function guardarApiKeyTemporal($api_key_id, $api_key_plana, $duracion_horas = 24) {
    global $pdo;

    try {
        // Generar token único para acceso
        $token_acceso = bin2hex(random_bytes(32));

        // Calcular expiración (por defecto 24 horas)
        $expiracion = date('Y-m-d H:i:s', strtotime("+{$duracion_horas} hours"));

        // Limpiar tokens expirados
        $pdo->exec("DELETE FROM api_keys_temporales WHERE expiracion < NOW()");

        // Insertar token temporal
        $stmt = $pdo->prepare("
            INSERT INTO api_keys_temporales (api_key_id, api_key_plana, token_acceso, expiracion, ip_generador, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt->execute([$api_key_id, $api_key_plana, $token_acceso, $expiracion, $ip, $user_agent]);

        return $token_acceso;

    } catch (Exception $e) {
        error_log("Error guardando API key temporal: " . $e->getMessage());
        return false;
    }
}

function obtenerApiKeyTemporal($token) {
    global $pdo;

    try {
        // Limpiar tokens expirados
        $pdo->exec("DELETE FROM api_keys_temporales WHERE expiracion < NOW()");

        // Buscar token válido
        $stmt = $pdo->prepare("
            SELECT akt.*, ak.nombre, ak.descripcion, p.nombre as producto_nombre
            FROM api_keys_temporales akt
            JOIN api_keys ak ON akt.api_key_id = ak.id
            JOIN productos p ON ak.producto_id = p.id
            WHERE akt.token_acceso = ? AND akt.expiracion > NOW()
        ");

        $stmt->execute([$token]);
        return $stmt->fetch();

    } catch (Exception $e) {
        error_log("Error obteniendo API key temporal: " . $e->getMessage());
        return false;
    }
}

function guardarConfiguracionAPI() {
    global $API_KEYS, $API_CONFIG;

    // Crear archivo JSON separado para almacenar la configuración dinámica
    $config_data = [
        'api_keys' => $API_KEYS,
        'api_config' => $API_CONFIG,
        'timestamp' => time()
    ];

    $config_file = __DIR__ . '/api_keys_data.json';
    return file_put_contents($config_file, json_encode($config_data, JSON_PRETTY_PRINT)) !== false;
}

function cargarConfiguracionAPI() {
    global $pdo, $API_KEYS, $API_CONFIG;

    try {
        // Cargar productos activos
        $stmt = $pdo->query("SELECT id, nombre FROM productos WHERE activo = 1");
        $productos = [];
        while ($row = $stmt->fetch()) {
            $productos[$row['id']] = $row['nombre'];
        }

        // Cargar API keys activas
        $stmt = $pdo->prepare("
            SELECT ak.*, p.nombre as producto_nombre
            FROM api_keys ak
            JOIN productos p ON ak.producto_id = p.id
            WHERE ak.activa = 1
        ");
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $API_KEYS[$row['nombre']] = [
                'hash' => $row['hash'],
                'activa' => (bool)$row['activa'],
                'limite_diario' => (int)$row['limite_diario'],
                'descripcion' => $row['descripcion'],
                'fecha_creacion' => $row['fecha_creacion'],
                'creada_por' => $row['creada_por'],
                'producto_id' => $row['producto_id'],
                'producto_nombre' => $row['producto_nombre']
            ];
        }

    } catch (Exception $e) {
        error_log("Error cargando configuración API desde BD: " . $e->getMessage());
        // Si falla la BD, mantener valores por defecto
    }
}

// Cargar configuración dinámica al inicio
cargarConfiguracionAPI();

function generarApiKey() {
    return bin2hex(random_bytes(32));
}

function generarHashApiKey($api_key) {
    return hash_hmac('sha256', $api_key, API_KEY_SECRET);
}

return ['API_KEYS' => $API_KEYS, 'API_CONFIG' => $API_CONFIG];
