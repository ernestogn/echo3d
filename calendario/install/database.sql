-- Base de Datos Completa del Sistema Calendario
-- Script de instalación automática
-- Versión 1.0.0

-- ===========================================
-- TABLAS PRINCIPALES DEL SISTEMA CALENDARIO
-- ===========================================

-- Tabla de usuarios (para administración)
CREATE TABLE IF NOT EXISTS {{PREFIX}}usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'usuario') DEFAULT 'usuario',
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de empresas organizadoras
CREATE TABLE IF NOT EXISTS {{PREFIX}}empresas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    direccion TEXT,
    telefono VARCHAR(50),
    email VARCHAR(150),
    sitio_web VARCHAR(255),
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de eventos
CREATE TABLE IF NOT EXISTS {{PREFIX}}eventos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,
    ubicacion VARCHAR(255),
    tipo_evento ENUM('feria', 'conferencia', 'lanzamiento', 'reunion', 'cambio_corporativo', 'capacitacion', 'taller', 'otro') DEFAULT 'otro',
    capacidad_maxima INT,
    costo_participacion DECIMAL(10,2) DEFAULT 0,
    enlace_registro VARCHAR(500),
    contacto_email VARCHAR(150),
    contacto_telefono VARCHAR(50),
    empresa_organizadora_id INT,
    usuario_creador_id INT,
    estado ENUM('borrador', 'publicado', 'cancelado', 'finalizado') DEFAULT 'borrador',
    visibilidad ENUM('privado', 'publico') DEFAULT 'publico',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_organizadora_id) REFERENCES {{PREFIX}}empresas(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_creador_id) REFERENCES {{PREFIX}}usuarios(id) ON DELETE SET NULL,
    INDEX idx_fecha_inicio (fecha_inicio),
    INDEX idx_tipo_evento (tipo_evento),
    INDEX idx_estado (estado),
    INDEX idx_visibilidad (visibilidad),
    INDEX idx_empresa (empresa_organizadora_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de categorías de eventos
CREATE TABLE IF NOT EXISTS {{PREFIX}}evento_categorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    color_hex VARCHAR(7) DEFAULT '#007bff',
    icono VARCHAR(50),
    activo BOOLEAN DEFAULT TRUE,
    orden INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activo (activo),
    INDEX idx_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de relación eventos-categorías
CREATE TABLE IF NOT EXISTS {{PREFIX}}evento_categoria_relacion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evento_id INT NOT NULL,
    categoria_id INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES {{PREFIX}}eventos(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES {{PREFIX}}evento_categorias(id) ON DELETE CASCADE,
    UNIQUE KEY unique_evento_categoria (evento_id, categoria_id),
    INDEX idx_evento (evento_id),
    INDEX idx_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de participantes en eventos
CREATE TABLE IF NOT EXISTS {{PREFIX}}evento_participantes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evento_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(50),
    empresa VARCHAR(255),
    cargo VARCHAR(100),
    estado_inscripcion ENUM('pendiente', 'confirmada', 'cancelada', 'asistio', 'no_asistio') DEFAULT 'pendiente',
    fecha_inscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    FOREIGN KEY (evento_id) REFERENCES {{PREFIX}}eventos(id) ON DELETE CASCADE,
    INDEX idx_evento (evento_id),
    INDEX idx_email (email),
    INDEX idx_estado (estado_inscripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- SISTEMA DE API KEYS
-- ===========================================

-- Tabla de productos/sistemas
CREATE TABLE IF NOT EXISTS {{PREFIX}}productos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de API keys
CREATE TABLE IF NOT EXISTS {{PREFIX}}api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    producto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    hash VARCHAR(255) NOT NULL,
    api_key_plana VARCHAR(255), -- Para mostrar al administrador
    limite_diario INT DEFAULT 1000,
    descripcion TEXT,
    creada_por INT,
    activa BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES {{PREFIX}}productos(id) ON DELETE CASCADE,
    FOREIGN KEY (creada_por) REFERENCES {{PREFIX}}usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY unique_nombre_producto (producto_id, nombre),
    INDEX idx_activa (activa),
    INDEX idx_hash (hash(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de logs de API
CREATE TABLE IF NOT EXISTS {{PREFIX}}api_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    api_key_id INT,
    endpoint VARCHAR(255),
    method VARCHAR(10) DEFAULT 'GET',
    response_code INT,
    response_time FLOAT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (api_key_id) REFERENCES {{PREFIX}}api_keys(id) ON DELETE SET NULL,
    INDEX idx_timestamp (timestamp),
    INDEX idx_api_key (api_key_id),
    INDEX idx_endpoint (endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de tokens temporales para API keys
CREATE TABLE IF NOT EXISTS {{PREFIX}}api_keys_temporales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    api_key_id INT NOT NULL,
    api_key_plana VARCHAR(255) NOT NULL,
    token_acceso VARCHAR(255) NOT NULL UNIQUE,
    expiracion DATETIME NOT NULL,
    ip_generador VARCHAR(45),
    user_agent TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (api_key_id) REFERENCES {{PREFIX}}api_keys(id) ON DELETE CASCADE,
    INDEX idx_token (token_acceso),
    INDEX idx_expiracion (expiracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- SISTEMA DE CONFIGURACIÓN
-- ===========================================

-- Tabla de configuración del sistema
CREATE TABLE IF NOT EXISTS {{PREFIX}}configuracion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    tipo ENUM('string', 'int', 'float', 'bool', 'json') DEFAULT 'string',
    descripcion TEXT,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- DATOS INICIALES
-- ===========================================

-- Insertar producto Calendario
INSERT IGNORE INTO {{PREFIX}}productos (id, nombre, descripcion) VALUES
(1, 'Calendario', 'Sistema de gestión de eventos y calendario');

-- Insertar usuario administrador por defecto
INSERT IGNORE INTO {{PREFIX}}usuarios (nombre, email, password, rol) VALUES
('Administrador', 'admin@calendario.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insertar categorías por defecto
INSERT IGNORE INTO {{PREFIX}}evento_categorias (nombre, descripcion, color_hex, icono, orden) VALUES
('Tecnología', 'Eventos relacionados con tecnología e innovación', '#007bff', 'fas fa-laptop', 1),
('Negocios', 'Eventos empresariales y de networking', '#28a745', 'fas fa-briefcase', 2),
('Educación', 'Capacitaciones y eventos educativos', '#ffc107', 'fas fa-graduation-cap', 3),
('Entretenimiento', 'Eventos culturales y de entretenimiento', '#dc3545', 'fas fa-music', 4),
('Salud', 'Eventos relacionados con salud y bienestar', '#20c997', 'fas fa-heartbeat', 5),
('Deportes', 'Eventos deportivos y recreativos', '#6f42c1', 'fas fa-futbol', 6),
('Arte', 'Eventos artísticos y culturales', '#e83e8c', 'fas fa-palette', 7),
('Comunidad', 'Eventos comunitarios y sociales', '#fd7e14', 'fas fa-users', 8);

-- Configuración inicial del sistema
INSERT IGNORE INTO {{PREFIX}}configuracion (clave, valor, tipo, descripcion) VALUES
('sistema_version', '1.0.0', 'string', 'Versión del sistema calendario'),
('sistema_instalado', NOW(), 'string', 'Fecha de instalación del sistema'),
('api_habilitada', '1', 'bool', 'Si la API pública está habilitada'),
('api_rate_limit_default', '100', 'int', 'Límite por defecto de requests por hora'),
('api_rate_limit_sin_key', '50', 'int', 'Límite para requests sin API key'),
('email_notificaciones', '0', 'bool', 'Si las notificaciones por email están habilitadas'),
('timezone', 'America/Argentina/Buenos_Aires', 'string', 'Zona horaria del sistema'),
('debug_mode', '0', 'bool', 'Modo debug para desarrollo');

-- Crear evento de ejemplo
INSERT IGNORE INTO {{PREFIX}}eventos (titulo, descripcion, fecha_inicio, ubicacion, tipo_evento, estado, visibilidad, usuario_creador_id) VALUES
('Bienvenido al Sistema Calendario', 'Este es un evento de ejemplo para mostrar las funcionalidades del sistema. Puede ser editado o eliminado por el administrador.', DATE_ADD(NOW(), INTERVAL 7 DAY), 'Centro de Convenciones', 'conferencia', 'publicado', 'publico', 1);

-- ===========================================
-- LIMPIEZA Y OPTIMIZACIÓN
-- ===========================================

-- Limpiar tokens expirados
DELETE FROM {{PREFIX}}api_keys_temporales WHERE expiracion < NOW();

-- Crear índices adicionales para optimización
CREATE INDEX IF NOT EXISTS idx_eventos_fecha_estado ON {{PREFIX}}eventos (fecha_inicio, estado);
CREATE INDEX IF NOT EXISTS idx_eventos_visibilidad ON {{PREFIX}}eventos (visibilidad, estado);
CREATE INDEX IF NOT EXISTS idx_participantes_evento_estado ON {{PREFIX}}evento_participantes (evento_id, estado_inscripcion);

-- ===========================================
-- EVENTOS DE LIMPIEZA AUTOMÁTICA
-- ===========================================

-- Evento para limpiar tokens expirados cada hora
CREATE EVENT IF NOT EXISTS {{PREFIX}}limpiar_tokens_expirados
ON SCHEDULE EVERY 1 HOUR
DO
    DELETE FROM {{PREFIX}}api_keys_temporales WHERE expiracion < NOW();

-- Evento para limpiar logs antiguos (mantener últimos 30 días)
CREATE EVENT IF NOT EXISTS {{PREFIX}}limpiar_logs_antiguos
ON SCHEDULE EVERY 1 DAY
DO
    DELETE FROM {{PREFIX}}api_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);

COMMIT;
