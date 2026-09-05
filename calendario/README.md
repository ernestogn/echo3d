# 📅 Módulo Calendario - Sistema de Eventos

Módulo autocontenido para la gestión y visualización de eventos con API pública segura.

## 📁 Estructura del Módulo

```
calendario/
├── 📄 calendario.php              # Interfaz principal del calendario
├── 📄 tabla_eventos.php           # Vista de tabla de eventos
├── 📄 calendario.css              # Estilos del calendario
├── 📄 calendario.js               # Lógica JavaScript
├── 📄 api_eventos.php             # API interna del calendario
├── 📄 funciones_calendario.php    # Utilidades y funciones auxiliares
│
├── 📁 api/                        # APIs públicas del calendario
│   ├── 📄 public_eventos.php      # API pública con autenticación HMAC
│   ├── 📄 api_keys_config.php     # Configuración de API keys
│   ├── 📄 generate_api_key.php    # Generador de API keys
│   └── 📄 README.md               # Documentación de la API
│
└── 📁 admin/                      # Paneles de administración
    ├── 📄 api_keys_admin.php      # Gestión de API keys
    └── 📄 api_keys_logs.php       # Endpoint de logs AJAX
```

## 🚀 Características

### 📅 Calendario Interactivo
- ✅ Vista mensual, semanal y diaria
- ✅ Eventos diferenciados por duración
- ✅ Colores únicos por categorías
- ✅ Eventos de 1 día vs múltiples días
- ✅ Navegación intuitiva

### 🗂️ Vista de Tabla
- ✅ Lista completa de eventos
- ✅ Filtros por fecha, tipo, empresa
- ✅ Paginación automática
- ✅ Información detallada

### 🔐 API Pública Segura
- ✅ Autenticación HMAC-SHA256
- ✅ Rate limiting automático
- ✅ Logs de auditoría completos
- ✅ Múltiples endpoints RESTful

### 🛠️ Panel de Administración
- ✅ CRUD completo de API keys
- ✅ Monitoreo de uso en tiempo real
- ✅ Estadísticas detalladas
- ✅ Gestión de límites y permisos

## 🔧 Instalación y Configuración

### 1. Dependencias del Sistema
```php
// Requiere configuración global del sistema
require_once '../config.php';
require_once '../auth.php';
```

### 2. Base de Datos
El módulo utiliza las siguientes tablas:
- `eventos` - Eventos principales
- `eventos_participantes` - Participantes
- `eventos_categorias` - Categorías
- `eventos_categoria_relacion` - Relaciones evento-categoría

### 3. Permisos
- ✅ Lectura pública (API)
- ✅ Administración (usuarios autenticados)
- ✅ Logs de auditoría

## 📡 API Endpoints

### Eventos Públicos
```
GET /calendario/api/public_eventos.php?action=listar
GET /calendario/api/public_eventos.php?action=detalle&id={evento_id}
GET /calendario/api/public_eventos.php?action=calendario
GET /calendario/api/public_eventos.php?action=categorias
GET /calendario/api/public_eventos.php?action=empresas
```

### Generador de API Keys
```
GET /calendario/api/generate_api_key.php
POST /calendario/api/generate_api_key.php?nombre={cliente}&limite={1000}
```

### Panel de Administración
```
GET /calendario/admin/api_keys_admin.php
POST /calendario/admin/api_keys_admin.php (CRUD operations)
```

## 🔐 Seguridad

### Autenticación API
- ✅ **HMAC-SHA256** con salt único
- ✅ **Rate limiting** por IP y API key
- ✅ **Validación de requests**
- ✅ **Logs de auditoría**

### Headers Soportados
```http
Authorization: Bearer TU_API_KEY
X-API-Key: TU_API_KEY
?api_key=TU_API_KEY
```

### Rate Limiting
- ✅ **Por defecto:** 1000 requests/hora por API key
- ✅ **Sin key:** 50 requests/hora por IP
- ✅ **Configurable** por API key

## 🎨 Personalización

### Estilos CSS
```css
/* calendario.css - Personalizar colores y diseño */
.evento-un-dia { /* Eventos de 1 día */ }
.evento-multiple { /* Eventos de múltiples días */ }
.categoria-feria { /* Color por categoría */ }
```

### Funciones JavaScript
```javascript
// calendario.js - Extender funcionalidad
function onEventoClick(evento) {
    // Lógica personalizada al hacer click
}
```

## 📊 Estadísticas y Monitoreo

### Logs de API
- ✅ **Archivo diario:** `logs/api_access_YYYY-MM-DD.log`
- ✅ **Formato:** `[timestamp] API_KEY: nombre | ACTION: listar | IP: 127.0.0.1`
- ✅ **Rotación automática**

### Métricas Disponibles
- ✅ **Requests por hora** por API key
- ✅ **IPs más activas**
- ✅ **Endpoints más usados**
- ✅ **Tasa de error**

## 🚀 Migración Independiente

Este módulo está diseñado para ser **completamente independiente**:

### ✅ Lo que incluye:
- APIs públicas y privadas
- Panel de administración completo
- Sistema de autenticación
- Logs y auditoría
- Configuraciones específicas

### ✅ Lo que necesita del sistema principal:
- Conexión a base de datos (`$pdo`)
- Sistema de autenticación (`estaAutenticado()`, `esAdmin()`)
- Configuración global (`config.php`)

### 📦 Para migrar como módulo independiente:

1. **Copiar carpeta completa:** `calendario/`
2. **Adaptar includes:** Cambiar rutas a `../` por rutas absolutas
3. **Configurar BD:** Crear tablas necesarias
4. **Implementar auth:** Crear sistema de autenticación mínimo
5. **¡Listo!** Módulo completamente funcional

## 📝 Desarrollo

### Arquitectura Modular
- ✅ **Separación de responsabilidades**
- ✅ **APIs autocontenidas**
- ✅ **Reutilización de código**
- ✅ **Mantenibilidad**

### Mejores Prácticas
- ✅ **Validación de entrada**
- ✅ **Sanitización de datos**
- ✅ **Manejo de errores**
- ✅ **Logs detallados**

## 🐛 Solución de Problemas

### Error: "API key inválida"
- Verificar que la key esté activa
- Comprobar formato HMAC-SHA256
- Revisar logs de auditoría

### Error: "Límite excedido"
- Aumentar límite en configuración
- Implementar cache/distributed rate limiting
- Revisar logs de rate limiting

### Error: "Acceso denegado"
- Verificar permisos de administrador
- Comprobar sesión activa
- Revisar configuración de auth

## 📄 Licencia

Este módulo es parte del Sistema de Gestión Empresarial.
Para uso independiente, contactar al administrador del sistema.

---

**📧 Soporte:** Contactar al equipo de desarrollo
**📚 Documentación:** Ver `api/README.md` para detalles técnicos
