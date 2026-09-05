<?php
/**
 * API Pública de Eventos - Acceso sin autenticación
 *
 * Este endpoint permite acceder a eventos públicos desde aplicaciones externas.
 * No requiere autenticación pero solo muestra eventos marcados como 'publico'.
 *
 * ENDPOINTS DISPONIBLES:
 *
 * GET /api/public_eventos.php?action=listar
 * - Lista eventos públicos con filtros opcionales
 * - Parámetros: fecha_desde, fecha_hasta, tipo, empresa_id, q (búsqueda), limite, pagina
 *
 * GET /api/public_eventos.php?action=detalle&id={evento_id}
 * - Obtiene detalles completos de un evento público específico
 *
 * GET /api/public_eventos.php?action=calendario
 * - Eventos formateados para calendarios externos (FullCalendar, etc.)
 * - Parámetros: start, end
 *
 * GET /api/public_eventos.php?action=categorias
 * - Lista todas las categorías disponibles
 *
 * GET /api/public_eventos.php?action=empresas
 * - Lista empresas organizadoras con eventos públicos
 *
 * EJEMPLOS DE USO:
 *
 * 1. Obtener próximos eventos:
 * GET /api/public_eventos.php?action=listar&fecha_desde=2025-01-01&limite=10
 *
 * 2. Buscar eventos por tipo:
 * GET /api/public_eventos.php?action=listar&tipo=feria&q=navidad
 *
 * 3. Eventos para calendario:
 * GET /api/public_eventos.php?action=calendario&start=2025-01-01&end=2025-12-31
 *
 * 4. Detalles de un evento específico:
 * GET /api/public_eventos.php?action=detalle&id=123
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/api_keys_config.php';

// Función para verificar si una solicitud es válida con API key y rate limiting
function validarSolicitudPublica() {
    global $API_CONFIG;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Verificar IP en blacklist
    if (in_array($ip, $API_CONFIG['ip_blacklist'])) {
        enviarRespuesta([
            'error' => 'Acceso denegado',
            'mensaje' => 'Tu IP ha sido bloqueada'
        ], 403);
    }
    
    // Verificar IP en whitelist (acceso sin restricciones)
    if (in_array($ip, $API_CONFIG['ip_whitelist'])) {
        return true;
    }
    
    // Obtener API key del header o query parameter
    $api_key = null;
    
    // Primero intentar obtener del header Authorization
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        // Formato: "Bearer API_KEY" o simplemente "API_KEY"
        $auth = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
            $api_key = $matches[1];
        } else {
            $api_key = $auth;
        }
    }
    
    // Si no está en header, buscar en query parameter
    if (!$api_key && isset($_GET['api_key'])) {
        $api_key = $_GET['api_key'];
    }
    
    // Si no está en query parameter, buscar en header X-API-Key
    if (!$api_key && isset($headers['X-API-Key'])) {
        $api_key = $headers['X-API-Key'];
    }
    
    // Verificar si se requiere API key
    $requiere_key = $API_CONFIG['requerir_api_key'];
    
    // Permitir acceso sin key desde localhost en desarrollo
    if ($API_CONFIG['permitir_sin_key_localhost'] && ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost')) {
        $requiere_key = false;
    }
    
    // Si hay API key, validarla
    if ($api_key) {
        $key_datos = verificarApiKey($api_key);
        
        if (!$key_datos) {
            enviarRespuesta([
                'error' => 'API key inválida',
                'mensaje' => 'La API key proporcionada no es válida o ha sido revocada'
            ], 401);
        }
        
        // Verificar rate limit para esta API key
        $limite = $key_datos['limite_diario'];
        if (!verificarRateLimit('api_key_' . $key_datos['nombre'], $limite)) {
            enviarRespuesta([
                'error' => 'Límite excedido',
                'mensaje' => "Has excedido el límite de {$limite} requests por hora para tu API key"
            ], 429);
        }
        
        // Registrar acceso exitoso
        $action = $_GET['action'] ?? 'unknown';
        registrarAccesoAPI($key_datos['nombre'], $action, $ip);
        
        // Guardar datos de la key en variable global para uso posterior
        $GLOBALS['API_KEY_DATOS'] = $key_datos;
        
        return true;
        
    } else {
        // No hay API key
        if ($requiere_key) {
            enviarRespuesta([
                'error' => 'API key requerida',
                'mensaje' => 'Debes proporcionar una API key válida. Usa el header "Authorization: Bearer TU_API_KEY" o el parámetro "api_key=TU_API_KEY"',
                'documentacion' => 'Contacta al administrador para obtener una API key'
            ], 401);
        }
        
        // Aplicar rate limit más restrictivo para IPs sin API key
        $limite_sin_key = $API_CONFIG['rate_limit_ip_sin_key'];
        if (!verificarRateLimit('ip_' . $ip, $limite_sin_key)) {
            enviarRespuesta([
                'error' => 'Límite excedido',
                'mensaje' => "Has excedido el límite de {$limite_sin_key} requests por hora. Considera obtener una API key para límites más altos"
            ], 429);
        }
        
        // Registrar acceso sin key
        $action = $_GET['action'] ?? 'unknown';
        registrarAccesoAPI('sin_key', $action, $ip);
        
        // Log básico de acceso público sin key
        error_log("Acceso API pública sin key desde IP: $ip - UA: " . substr($userAgent, 0, 100));
        
        return true;
    }
}

// Función para sanitizar entrada
function sanitizarEntrada($data) {
    if (is_array($data)) {
        return array_map('sanitizarEntrada', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Función para formatear respuesta JSON consistente
function enviarRespuesta($data, $httpCode = 200) {
    http_response_code($httpCode);

    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    header('Cache-Control: no-cache, must-revalidate');

    // Agregar metadata a la respuesta
    $respuesta = [
        'success' => $httpCode < 400,
        'timestamp' => date('c')
    ];

    if ($httpCode >= 400) {
        // Si es un array, incluirlo directamente en el error
        if (is_array($data)) {
            $respuesta['error'] = $data;
        } else {
            $respuesta['error'] = [
                'mensaje' => is_string($data) ? $data : 'Error interno del servidor'
            ];
        }
    } else {
        $respuesta['data'] = $data;
    }

    echo json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Función para validar y parsear fechas
function validarFecha($fecha, $formato = 'Y-m-d') {
    $dateTime = DateTime::createFromFormat($formato, $fecha);
    return $dateTime && $dateTime->format($formato) === $fecha;
}

// Verificar solicitud básica
if (!validarSolicitudPublica()) {
    enviarRespuesta('Acceso denegado', 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

error_log("API Pública - Método: $method, Acción: $action");

// Solo permitir GET para API pública
if ($method !== 'GET') {
    enviarRespuesta('Método no permitido', 405);
}

switch ($action) {
    case 'listar':
        listarEventosPublicos();
        break;
    case 'detalle':
        detalleEventoPublico();
        break;
    case 'calendario':
        eventosCalendarioPublico();
        break;
    case 'categorias':
        listarCategoriasPublicas();
        break;
    case 'empresas':
        listarEmpresasPublicas();
        break;
    default:
        enviarRespuesta('Acción no válida', 400);
}

function listarEventosPublicos() {
    global $pdo;

    try {
        // Parámetros de consulta con sanitización
        $fecha_desde = sanitizarEntrada($_GET['fecha_desde'] ?? null);
        $fecha_hasta = sanitizarEntrada($_GET['fecha_hasta'] ?? null);
        $tipo_evento = sanitizarEntrada($_GET['tipo'] ?? null);
        $empresa_id = sanitizarEntrada($_GET['empresa_id'] ?? null);
        $busqueda = sanitizarEntrada($_GET['q'] ?? null);
        $categoria = sanitizarEntrada($_GET['categoria'] ?? null);
        $pagina = max(1, intval($_GET['pagina'] ?? 1));
        $limite = min(100, max(1, intval($_GET['limite'] ?? 20)));
        $offset = ($pagina - 1) * $limite;

        // Validar fechas si se proporcionan
        if ($fecha_desde && !validarFecha($fecha_desde)) {
            enviarRespuesta('Fecha desde inválida', 400);
        }
        if ($fecha_hasta && !validarFecha($fecha_hasta)) {
            enviarRespuesta('Fecha hasta inválida', 400);
        }

        // Construir consulta SQL
        $sql = "
            SELECT e.id, e.titulo, e.descripcion, e.fecha_inicio, e.fecha_fin,
                   e.ubicacion, e.tipo_evento, e.capacidad_maxima, e.costo_participacion,
                   e.enlace_registro, e.contacto_email, e.contacto_telefono,
                   u.nombre as creador_nombre,
                   emp.nombre as empresa_organizadora_nombre,
                   COUNT(ep.id) as total_participantes,
                   GROUP_CONCAT(DISTINCT ec.nombre) as categorias_nombres,
                   GROUP_CONCAT(DISTINCT ec.color_hex) as categorias_colores
            FROM eventos e
            LEFT JOIN usuarios u ON e.usuario_creador_id = u.id
            LEFT JOIN empresas emp ON e.empresa_organizadora_id = emp.id
            LEFT JOIN evento_participantes ep ON e.id = ep.evento_id AND ep.estado_inscripcion IN ('confirmada', 'asistio')
            LEFT JOIN evento_categoria_relacion ecr ON e.id = ecr.evento_id
            LEFT JOIN evento_categorias ec ON ecr.categoria_id = ec.id AND ec.activo = 1
            WHERE e.estado = 'publicado' AND e.visibilidad = 'publico'
        ";
        $params = [];

        // Aplicar filtros
        if ($fecha_desde) {
            $sql .= " AND e.fecha_inicio >= ?";
            $params[] = $fecha_desde;
        }

        if ($fecha_hasta) {
            $sql .= " AND e.fecha_inicio <= ?";
            $params[] = $fecha_hasta . ' 23:59:59';
        }

        if ($tipo_evento && $tipo_evento !== 'todos') {
            $sql .= " AND e.tipo_evento = ?";
            $params[] = $tipo_evento;
        }

        if ($empresa_id) {
            $sql .= " AND e.empresa_organizadora_id = ?";
            $params[] = $empresa_id;
        }

        if ($categoria) {
            $sql .= " AND ec.nombre LIKE ?";
            $params[] = "%$categoria%";
        }

        if ($busqueda) {
            $sql .= " AND (e.titulo LIKE ? OR e.descripcion LIKE ? OR e.ubicacion LIKE ?)";
            $params[] = "%$busqueda%";
            $params[] = "%$busqueda%";
            $params[] = "%$busqueda%";
        }

        $sql .= " GROUP BY e.id ORDER BY e.fecha_inicio ASC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $eventos = $stmt->fetchAll();

        // Procesar eventos para respuesta
        $eventos_procesados = [];
        foreach ($eventos as $evento) {
            // Procesar categorías
            $categorias = [];
            if ($evento['categorias_nombres']) {
                $nombres = explode(',', $evento['categorias_nombres']);
                $colores = explode(',', $evento['categorias_colores']);
                $categorias = array_map(function($nombre, $color) {
                    return ['nombre' => $nombre, 'color' => $color];
                }, $nombres, $colores);
            }

            $eventos_procesados[] = [
                'id' => $evento['id'],
                'titulo' => $evento['titulo'],
                'descripcion' => $evento['descripcion'],
                'fecha_inicio' => $evento['fecha_inicio'],
                'fecha_fin' => $evento['fecha_fin'],
                'ubicacion' => $evento['ubicacion'],
                'tipo_evento' => $evento['tipo_evento'],
                'capacidad_maxima' => $evento['capacidad_maxima'],
                'costo_participacion' => $evento['costo_participacion'],
                'enlace_registro' => $evento['enlace_registro'],
                'contacto_email' => $evento['contacto_email'],
                'contacto_telefono' => $evento['contacto_telefono'],
                'empresa_organizadora' => $evento['empresa_organizadora_nombre'],
                'total_participantes' => intval($evento['total_participantes']),
                'categorias' => $categorias,
                'fecha_creacion' => $evento['fecha_creacion'] ?? null
            ];
        }

        // Contar total para paginación
        $sql_count = "
            SELECT COUNT(DISTINCT e.id) as total
            FROM eventos e
            LEFT JOIN evento_categoria_relacion ecr ON e.id = ecr.evento_id
            LEFT JOIN evento_categorias ec ON ecr.categoria_id = ec.id AND ec.activo = 1
            WHERE e.estado = 'publicado' AND e.visibilidad = 'publico'
        ";
        $params_count = array_slice($params, 0, -2); // Remover LIMIT y OFFSET

        // Reaplicar filtros para el conteo
        if ($fecha_desde) {
            $sql_count .= " AND e.fecha_inicio >= ?";
        }
        if ($fecha_hasta) {
            $sql_count .= " AND e.fecha_inicio <= ?";
        }
        if ($tipo_evento && $tipo_evento !== 'todos') {
            $sql_count .= " AND e.tipo_evento = ?";
        }
        if ($empresa_id) {
            $sql_count .= " AND e.empresa_organizadora_id = ?";
        }
        if ($categoria) {
            $sql_count .= " AND ec.nombre LIKE ?";
        }
        if ($busqueda) {
            $sql_count .= " AND (e.titulo LIKE ? OR e.descripcion LIKE ? OR e.ubicacion LIKE ?)";
        }

        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute($params_count);
        $total = $stmt_count->fetch()['total'];

        $respuesta = [
            'eventos' => $eventos_procesados,
            'paginacion' => [
                'pagina_actual' => $pagina,
                'total_paginas' => ceil($total / $limite),
                'total_registros' => intval($total),
                'limite' => $limite,
                'tiene_mas' => ($pagina * $limite) < $total
            ],
            'filtros_aplicados' => [
                'fecha_desde' => $fecha_desde,
                'fecha_hasta' => $fecha_hasta,
                'tipo_evento' => $tipo_evento,
                'empresa_id' => $empresa_id,
                'categoria' => $categoria,
                'busqueda' => $busqueda
            ]
        ];

        enviarRespuesta($respuesta);

    } catch (Exception $e) {
        error_log("Error en listarEventosPublicos: " . $e->getMessage());
        enviarRespuesta('Error interno del servidor', 500);
    }
}

function detalleEventoPublico() {
    global $pdo;

    try {
        $evento_id = intval($_GET['id'] ?? 0);

        if (!$evento_id) {
            enviarRespuesta('ID de evento requerido', 400);
        }

        $sql = "
            SELECT e.*, u.nombre as creador_nombre, emp.nombre as empresa_organizadora_nombre,
                   COUNT(ep.id) as total_participantes,
                   COUNT(CASE WHEN ep.estado_inscripcion = 'confirmada' THEN 1 END) as participantes_confirmados,
                   GROUP_CONCAT(DISTINCT ec.nombre) as categorias_nombres,
                   GROUP_CONCAT(DISTINCT ec.color_hex) as categorias_colores
            FROM eventos e
            LEFT JOIN usuarios u ON e.usuario_creador_id = u.id
            LEFT JOIN empresas emp ON e.empresa_organizadora_id = emp.id
            LEFT JOIN evento_participantes ep ON e.id = ep.evento_id
            LEFT JOIN evento_categoria_relacion ecr ON e.id = ecr.evento_id
            LEFT JOIN evento_categorias ec ON ecr.categoria_id = ec.id AND ec.activo = 1
            WHERE e.id = ? AND e.estado = 'publicado' AND e.visibilidad = 'publico'
            GROUP BY e.id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$evento_id]);
        $evento = $stmt->fetch();

        if (!$evento) {
            enviarRespuesta('Evento no encontrado o no público', 404);
        }

        // Procesar categorías
        $categorias = [];
        if ($evento['categorias_nombres']) {
            $nombres = explode(',', $evento['categorias_nombres']);
            $colores = explode(',', $evento['categorias_colores']);
            $categorias = array_map(function($nombre, $color) {
                return ['nombre' => $nombre, 'color' => $color];
            }, $nombres, $colores);
        }

        $evento_procesado = [
            'id' => $evento['id'],
            'titulo' => $evento['titulo'],
            'descripcion' => $evento['descripcion'],
            'fecha_inicio' => $evento['fecha_inicio'],
            'fecha_fin' => $evento['fecha_fin'],
            'ubicacion' => $evento['ubicacion'],
            'tipo_evento' => $evento['tipo_evento'],
            'capacidad_maxima' => $evento['capacidad_maxima'],
            'costo_participacion' => $evento['costo_participacion'],
            'enlace_registro' => $evento['enlace_registro'],
            'contacto_email' => $evento['contacto_email'],
            'contacto_telefono' => $evento['contacto_telefono'],
            'empresa_organizadora' => $evento['empresa_organizadora_nombre'],
            'total_participantes' => intval($evento['total_participantes']),
            'participantes_confirmados' => intval($evento['participantes_confirmados']),
            'categorias' => $categorias,
            'fecha_creacion' => $evento['fecha_creacion'],
            'fecha_modificacion' => $evento['fecha_modificacion']
        ];

        enviarRespuesta($evento_procesado);

    } catch (Exception $e) {
        error_log("Error en detalleEventoPublico: " . $e->getMessage());
        enviarRespuesta('Error interno del servidor', 500);
    }
}

function eventosCalendarioPublico() {
    global $pdo;

    try {
        $fecha_desde = sanitizarEntrada($_GET['start'] ?? date('Y-m-01'));
        $fecha_hasta = sanitizarEntrada($_GET['end'] ?? date('Y-m-t'));

        if (!validarFecha($fecha_desde) || !validarFecha($fecha_hasta)) {
            enviarRespuesta('Fechas inválidas', 400);
        }

        $sql = "
            SELECT e.id, e.titulo, e.fecha_inicio, e.fecha_fin, e.tipo_evento,
                   e.ubicacion, emp.nombre as empresa_organizadora,
                   GROUP_CONCAT(DISTINCT ec.nombre) as categorias_nombres,
                   GROUP_CONCAT(DISTINCT ec.color_hex) as categorias_colores
            FROM eventos e
            LEFT JOIN empresas emp ON e.empresa_organizadora_id = emp.id
            LEFT JOIN evento_categoria_relacion ecr ON e.id = ecr.evento_id
            LEFT JOIN evento_categorias ec ON ecr.categoria_id = ec.id AND ec.activo = 1
            WHERE e.fecha_inicio >= ? AND e.fecha_inicio <= ?
            AND e.estado = 'publicado' AND e.visibilidad = 'publico'
            GROUP BY e.id ORDER BY e.fecha_inicio ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fecha_desde, $fecha_hasta]);
        $eventos = $stmt->fetchAll();

        // Formatear para calendarios externos
        $eventos_calendario = [];
        foreach ($eventos as $evento) {
            $evento_cal = [
                'id' => $evento['id'],
                'title' => $evento['titulo'],
                'start' => $evento['fecha_inicio'],
                'end' => $evento['fecha_fin'] ?: $evento['fecha_inicio'],
                'description' => $evento['ubicacion'],
                'extendedProps' => [
                    'tipo' => $evento['tipo_evento'],
                    'ubicacion' => $evento['ubicacion'],
                    'empresa' => $evento['empresa_organizadora']
                ]
            ];

            // Determinar color basado en categorías o tipo
            if ($evento['categorias_colores']) {
                $colores = explode(',', $evento['categorias_colores']);
                $evento_cal['color'] = $colores[0];
            } else {
                // Colores por defecto según tipo
                $colores_tipo = [
                    'feria' => '#28a745',
                    'lanzamiento' => '#007bff',
                    'conferencia' => '#6f42c1',
                    'reunion' => '#fd7e14',
                    'cambio_corporativo' => '#dc3545',
                    'capacitacion' => '#20c997',
                    'taller' => '#e83e8c',
                    'otro' => '#6c757d'
                ];
                $evento_cal['color'] = $colores_tipo[$evento['tipo_evento']] ?? '#6c757d';
            }

            $eventos_calendario[] = $evento_cal;
        }

        enviarRespuesta($eventos_calendario);

    } catch (Exception $e) {
        error_log("Error en eventosCalendarioPublico: " . $e->getMessage());
        enviarRespuesta('Error interno del servidor', 500);
    }
}

function listarCategoriasPublicas() {
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT id, nombre, color_hex, descripcion
            FROM evento_categorias
            WHERE activo = 1
            ORDER BY nombre ASC
        ");

        $categorias = $stmt->fetchAll();

        // Formatear respuesta
        $categorias_procesadas = array_map(function($cat) {
            return [
                'id' => $cat['id'],
                'nombre' => $cat['nombre'],
                'color' => $cat['color_hex'],
                'descripcion' => $cat['descripcion']
            ];
        }, $categorias);

        enviarRespuesta($categorias_procesadas);

    } catch (Exception $e) {
        error_log("Error en listarCategoriasPublicas: " . $e->getMessage());
        enviarRespuesta('Error interno del servidor', 500);
    }
}

function listarEmpresasPublicas() {
    global $pdo;

    try {
        $sql = "
            SELECT DISTINCT emp.id, emp.nombre, emp.ciudad, emp.provincia,
                   COUNT(e.id) as total_eventos,
                   MAX(e.fecha_inicio) as ultimo_evento
            FROM empresas emp
            JOIN eventos e ON emp.id = e.empresa_organizadora_id
            WHERE e.estado = 'publicado' AND e.visibilidad = 'publico'
            GROUP BY emp.id
            ORDER BY emp.nombre ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $empresas = $stmt->fetchAll();

        // Formatear respuesta
        $empresas_procesadas = array_map(function($emp) {
            return [
                'id' => $emp['id'],
                'nombre' => $emp['nombre'],
                'ciudad' => $emp['ciudad'],
                'provincia' => $emp['provincia'],
                'total_eventos' => intval($emp['total_eventos']),
                'ultimo_evento' => $emp['ultimo_evento']
            ];
        }, $empresas);

        enviarRespuesta($empresas_procesadas);

    } catch (Exception $e) {
        error_log("Error en listarEmpresasPublicas: " . $e->getMessage());
        enviarRespuesta('Error interno del servidor', 500);
    }
}
?>
