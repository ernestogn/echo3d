<?php
/**
 * Funciones específicas para el calendario de eventos
 */

/**
 * Obtiene los tipos de evento publicados para los filtros
 * @return array Lista de tipos de evento únicos
 */
function obtenerTiposEventoCalendario() {
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT DISTINCT tipo_evento FROM eventos WHERE estado = 'publicado' ORDER BY tipo_evento");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error al obtener tipos de evento: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene las categorías activas para los filtros
 * @return array Lista de categorías con id, nombre y color_hex
 */
function obtenerCategoriasEventoCalendario() {
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT id, nombre, color_hex FROM evento_categorias WHERE activo = 1 ORDER BY nombre");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error al obtener categorías de evento: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene las empresas organizadoras que han creado eventos
 * @return array Lista de empresas con id y nombre
 */
function obtenerEmpresasOrganizadorasCalendario() {
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT DISTINCT e.id, e.nombre FROM empresas e INNER JOIN eventos ev ON e.id = ev.empresa_organizadora_id ORDER BY e.nombre");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error al obtener empresas organizadoras: " . $e->getMessage());
        return [];
    }
}

/**
 * Inicializa todas las variables necesarias para el calendario
 * @return array Array con tipos_evento, categorias y empresas
 */
function inicializarDatosCalendarioCalendario() {
    return [
        'tipos_evento' => obtenerTiposEventoCalendario(),
        'categorias' => obtenerCategoriasEventoCalendario(),
        'empresas' => obtenerEmpresasOrganizadorasCalendario()
    ];
}
?>
