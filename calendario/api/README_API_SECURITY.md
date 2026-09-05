# Sistema de Seguridad con API Keys - public_eventos.php

## 📋 Descripción General

Se ha implementado un sistema de seguridad robusto con API keys encriptadas para proteger el acceso a la API pública de eventos. El sistema incluye:

- ✅ **Autenticación mediante API keys encriptadas** con HMAC-SHA256
- ✅ **Rate limiting** (límite de peticiones por hora)
- ✅ **Registro de accesos** (logs detallados)
- ✅ **Blacklist/Whitelist de IPs**
- ✅ **Protección contra timing attacks**
- ✅ **Múltiples métodos de envío de API key**

---

## 🔑 Componentes del Sistema

### 1. `api_keys_config.php`
Archivo de configuración que almacena:
- API keys hasheadas (nunca se guardan en texto plano)
- Configuración de seguridad
- Funciones de validación y rate limiting

### 2. `public_eventos.php` (modificado)
API con validación de API keys integrada que:
- Verifica la autenticidad de las API keys
- Aplica rate limiting
- Registra todos los accesos
- Permite configurar blacklist/whitelist de IPs

### 3. `generate_api_key.php`
Script para generar nuevas API keys de forma segura:
- Genera keys aleatorias de 64 caracteres
- Crea el hash correspondiente
- Agrega automáticamente al archivo de configuración
- Solo ejecutable desde CLI o localhost

---

## 🚀 Configuración Inicial

### Paso 1: Configurar el sistema

Editar `api/api_keys_config.php` y ajustar:

```php
$API_CONFIG = [
    'requerir_api_key' => true,  // true = obligatorio, false = opcional
    'permitir_sin_key_localhost' => true,  // Permitir desarrollo local sin key
    'rate_limit_enabled' => true,  // Habilitar límite de peticiones
    'rate_limit_default' => 100,  // Límite para usuarios con API key (por hora)
    'rate_limit_ip_sin_key' => 50,  // Límite para IPs sin API key (por hora)
    'log_requests' => true,  // Registrar todas las peticiones
    'ip_whitelist' => [],  // IPs permitidas sin restricciones
    'ip_blacklist' => []   // IPs bloqueadas
];
```

### Paso 2: Cambiar el secreto de encriptación (PRODUCCIÓN)

En `api_keys_config.php`, modificar la constante:

```php
define('API_KEY_SECRET', 'tu_secreto_super_seguro_aqui_' . hash('sha256', uniqid(true)));
```

**⚠️ IMPORTANTE:** Cambiar esto en producción y mantenerlo seguro.

---

## 🔐 Generar API Keys

### Opción 1: Desde línea de comandos (recomendado)

```bash
# Sintaxis básica
php api/generate_api_key.php nombre_cliente [limite_hora] [descripcion]

# Ejemplos
php api/generate_api_key.php mi_app
php api/generate_api_key.php mi_app 5000
php api/generate_api_key.php mi_app 5000 "Aplicación móvil de eventos"
```

### Opción 2: Desde el navegador (solo localhost)

```
http://localhost/sistema_empresas/api/generate_api_key.php?nombre=mi_app&limite=5000&descripcion=Mi+App
```

### Resultado

El script generará:
- Una API key única de 64 caracteres
- El hash HMAC-SHA256 correspondiente
- Agregará automáticamente la configuración a `api_keys_config.php`
- Mostrará ejemplos de uso

**🔒 Seguridad:** La API key se muestra solo UNA vez. Guárdala de forma segura.

---

## 📡 Usar la API con API Key

### Método 1: Header Authorization (recomendado)

```bash
curl -H "Authorization: Bearer TU_API_KEY_AQUI" \
     "http://tu-dominio.com/api/public_eventos.php?action=listar"
```

**JavaScript/Fetch:**
```javascript
fetch('http://tu-dominio.com/api/public_eventos.php?action=listar', {
    headers: {
        'Authorization': 'Bearer TU_API_KEY_AQUI'
    }
})
.then(response => response.json())
.then(data => console.log(data));
```

### Método 2: Header X-API-Key

```bash
curl -H "X-API-Key: TU_API_KEY_AQUI" \
     "http://tu-dominio.com/api/public_eventos.php?action=listar"
```

**JavaScript/Fetch:**
```javascript
fetch('http://tu-dominio.com/api/public_eventos.php?action=listar', {
    headers: {
        'X-API-Key': 'TU_API_KEY_AQUI'
    }
})
.then(response => response.json())
.then(data => console.log(data));
```

### Método 3: Query Parameter (menos seguro)

```
http://tu-dominio.com/api/public_eventos.php?action=listar&api_key=TU_API_KEY_AQUI
```

**⚠️ Nota:** Este método es menos seguro ya que la key queda visible en URLs/logs.

---

## 📊 Respuestas de la API

### Respuesta exitosa (200)

```json
{
    "success": true,
    "timestamp": "2025-01-15T10:30:00-03:00",
    "data": {
        "eventos": [...],
        "paginacion": {...}
    }
}
```

### API key inválida (401)

```json
{
    "success": false,
    "timestamp": "2025-01-15T10:30:00-03:00",
    "error": {
        "error": "API key inválida",
        "mensaje": "La API key proporcionada no es válida o ha sido revocada"
    }
}
```

### API key requerida (401)

```json
{
    "success": false,
    "timestamp": "2025-01-15T10:30:00-03:00",
    "error": {
        "error": "API key requerida",
        "mensaje": "Debes proporcionar una API key válida...",
        "documentacion": "Contacta al administrador para obtener una API key"
    }
}
```

### Límite excedido (429)

```json
{
    "success": false,
    "timestamp": "2025-01-15T10:30:00-03:00",
    "error": {
        "error": "Límite excedido",
        "mensaje": "Has excedido el límite de 100 requests por hora para tu API key"
    }
}
```

### IP bloqueada (403)

```json
{
    "success": false,
    "timestamp": "2025-01-15T10:30:00-03:00",
    "error": {
        "error": "Acceso denegado",
        "mensaje": "Tu IP ha sido bloqueada"
    }
}
```

---

## 🛡️ Características de Seguridad

### 1. Encriptación de API Keys
- Las keys se almacenan hasheadas con HMAC-SHA256
- Nunca se guardan en texto plano
- Usa un secreto adicional para mayor seguridad

### 2. Rate Limiting
- Límites configurables por API key
- Límites más restrictivos para IPs sin key
- Ventana de tiempo de 1 hora
- Almacenamiento temporal de contadores

### 3. Logging de Accesos
- Registra todos los accesos (con y sin API key)
- Logs diarios en `/logs/api_access_YYYY-MM-DD.log`
- Incluye: timestamp, API key, acción, IP, user agent

### 4. Protección contra Timing Attacks
- Usa `hash_equals()` para comparaciones seguras
- Evita que atacantes detecten keys parcialmente correctas

### 5. Blacklist/Whitelist de IPs
- Bloqueo permanente de IPs maliciosas
- Acceso sin restricciones para IPs confiables

---

## 🔧 Administración de API Keys

### Revocar una API key

Editar `api/api_keys_config.php` y cambiar:

```php
'cliente_nombre' => [
    'hash' => '...',
    'activa' => false,  // Cambiar a false
    'limite_diario' => 1000,
    ...
]
```

### Cambiar límite de una API key

```php
'cliente_nombre' => [
    'hash' => '...',
    'activa' => true,
    'limite_diario' => 10000,  // Aumentar límite
    ...
]
```

### Ver logs de acceso

```bash
# Ver logs del día actual
cat logs/api_access_2025-01-15.log

# Ver últimas 50 líneas
tail -n 50 logs/api_access_2025-01-15.log

# Ver accesos de una API key específica
grep "API_KEY: nombre_cliente" logs/api_access_*.log
```

### Agregar IP a blacklist

```php
$API_CONFIG = [
    ...
    'ip_blacklist' => ['192.168.1.100', '10.0.0.50']
];
```

### Agregar IP a whitelist

```php
$API_CONFIG = [
    ...
    'ip_whitelist' => ['192.168.1.10', '10.0.0.5']
];
```

---

## 🧪 Pruebas

### Probar sin API key

```bash
curl "http://localhost/sistema_empresas/api/public_eventos.php?action=listar"
```

**Resultado esperado:**
- Si `requerir_api_key = false`: ✅ Funciona con límites restrictivos
- Si `requerir_api_key = true`: ❌ Error 401

### Probar con API key válida

```bash
curl -H "Authorization: Bearer TU_API_KEY" \
     "http://localhost/sistema_empresas/api/public_eventos.php?action=listar"
```

**Resultado esperado:** ✅ Respuesta exitosa con datos

### Probar con API key inválida

```bash
curl -H "Authorization: Bearer KEY_FALSA_123" \
     "http://localhost/sistema_empresas/api/public_eventos.php?action=listar"
```

**Resultado esperado:** ❌ Error 401 - API key inválida

### Probar rate limiting

Hacer más de 100 peticiones en menos de 1 hora:

```bash
for i in {1..150}; do
    curl -H "Authorization: Bearer TU_API_KEY" \
         "http://localhost/sistema_empresas/api/public_eventos.php?action=listar"
done
```

**Resultado esperado:** ❌ Error 429 después del límite

---

## 📁 Estructura de Archivos

```
api/
├── api_keys_config.php          # Configuración y funciones de seguridad
├── generate_api_key.php         # Generador de API keys
├── public_eventos.php           # API con seguridad integrada
└── README_API_SECURITY.md       # Esta documentación

logs/
└── api_access_YYYY-MM-DD.log    # Logs de acceso por día
```

---

## 🚨 Consideraciones de Seguridad

### En Desarrollo
- `requerir_api_key = false` está bien
- `permitir_sin_key_localhost = true` es conveniente
- Proteger `generate_api_key.php`

### En Producción
1. ✅ Cambiar `API_KEY_SECRET` a un valor único
2. ✅ Establecer `requerir_api_key = true`
3. ✅ Eliminar o proteger `generate_api_key.php`
4. ✅ Configurar HTTPS obligatorio
5. ✅ Revisar logs regularmente
6. ✅ Implementar rotación de keys periódica
7. ✅ Considerar usar Redis/Memcached para rate limiting en producción

### Protección del Generador

**Opción 1 - Eliminar:**
```bash
rm api/generate_api_key.php
```

**Opción 2 - Proteger con .htaccess:**
```apache
<Files "generate_api_key.php">
    Order Deny,Allow
    Deny from all
    Allow from 127.0.0.1
</Files>
```

**Opción 3 - Agregar autenticación básica:**
Editar el inicio del archivo para requerir contraseña.

---

## 📞 Soporte

Para obtener una API key o reportar problemas de seguridad:
- Contactar al administrador del sistema
- Email: [tu-email@dominio.com]

---

## 📝 Changelog

### Versión 1.0 (2025-01-15)
- ✅ Sistema de API keys con HMAC-SHA256
- ✅ Rate limiting por hora
- ✅ Logging de accesos
- ✅ Blacklist/Whitelist de IPs
- ✅ Múltiples métodos de autenticación
- ✅ Generador automático de keys
- ✅ Documentación completa

---

**Desarrollado para:** sistema_empresas  
**Última actualización:** 2025-12-13
