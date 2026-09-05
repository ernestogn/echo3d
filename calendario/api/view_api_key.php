<?php
/**
 * Visualizador de API Keys Temporales
 *
 * Permite acceder a las API keys generadas durante un tiempo limitado
 * URL: view_api_key.php?token=TOKEN_DE_ACCESO
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/api_keys_config.php';

// Obtener token de la URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    die("Token de acceso requerido");
}

// Obtener información de la API key temporal
$info_key = obtenerApiKeyTemporal($token);

if (!$info_key) {
    http_response_code(404);
    die("Token inválido o expirado");
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Key - <?= htmlspecialchars($info_key['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .key-container { max-width: 800px; margin: 2rem auto; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .api-key-display { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 20px; margin: 20px 0; }
        .warning-banner { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 10px; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container key-container">
        <div class="row">
            <div class="col-12">
                <div class="text-center mb-4">
                    <i class="fas fa-key fa-3x text-primary mb-3"></i>
                    <h1 class="h2">API Key Generada</h1>
                    <p class="text-muted">Acceso temporal a tu API key</p>
                </div>

                <!-- Información de expiración -->
                <div class="warning-banner">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-clock fa-2x me-3"></i>
                        <div>
                            <h5 class="mb-1">¡Acceso Temporal!</h5>
                            <p class="mb-0">
                                Esta página expirará automáticamente en <strong>24 horas</strong>.<br>
                                <small>Expira: <?= date('d/m/Y H:i:s', strtotime($info_key['expiracion'])) ?></small>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Información de la API Key -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-shield-alt"></i>
                            API Key para <?= htmlspecialchars($info_key['producto_nombre']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-user-tag"></i> Información del Cliente</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Nombre:</strong></td>
                                        <td><?= htmlspecialchars($info_key['nombre']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Producto:</strong></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($info_key['producto_nombre']) ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Descripción:</strong></td>
                                        <td><?= htmlspecialchars($info_key['descripcion'] ?: 'Sin descripción') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Generada:</strong></td>
                                        <td><?= date('d/m/Y H:i', strtotime($info_key['fecha_creacion'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-server"></i> Configuración Técnica</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Estado:</strong></td>
                                        <td><span class="badge bg-success">Activa</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Límite/Hora:</strong></td>
                                        <td><span class="badge bg-info">1000 requests</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Hash:</strong></td>
                                        <td><code class="small text-break"><?= substr($info_key['api_key'], 0, 32) ?>...</code></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Key Completa -->
                <div class="card mb-4 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-key"></i>
                            Tu API Key Completa
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Importante:</strong> Copia esta API key y guárdala en un lugar seguro.
                            Esta es la única vez que podrás verla completa.
                        </div>

                        <div class="api-key-display">
                            <div class="text-center">
                                <h6>API Key:</h6>
                                <code class="fs-5 text-break" style="word-break: break-all;">
                                    <?= $info_key['api_key_plana'] ?>
                                </code>
                                <br><br>
                                <button class="btn btn-light btn-lg" onclick="copiarAlPortapapeles('<?= $info_key['api_key_plana'] ?>')">
                                    <i class="fas fa-copy"></i> Copiar al Portapapeles
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ejemplos de uso -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-code"></i>
                            Ejemplos de Uso
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6>Header Authorization:</h6>
                                <code class="d-block small bg-light p-2 rounded">
curl -H "Authorization: Bearer <?= substr($info_key['api_key_plana'], 0, 20) ?>..." \<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"http://tu-dominio.com/calendario/api/public_eventos.php?action=listar"
                                </code>
                            </div>
                            <div class="col-md-4">
                                <h6>Header X-API-Key:</h6>
                                <code class="d-block small bg-light p-2 rounded">
curl -H "X-API-Key: <?= substr($info_key['api_key_plana'], 0, 20) ?>..." \<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"http://tu-dominio.com/calendario/api/public_eventos.php?action=listar"
                                </code>
                            </div>
                            <div class="col-md-4">
                                <h6>Query Parameter:</h6>
                                <code class="d-block small bg-light p-2 rounded">
http://tu-dominio.com/calendario/api/public_eventos.php?action=listar&api_key=<?= substr($info_key['api_key_plana'], 0, 20) ?>...
                                </code>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enlaces útiles -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-external-link-alt"></i>
                            Enlaces Útiles
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <a href="generate_api_key.php" class="btn btn-outline-primary btn-sm w-100 mb-2" target="_blank">
                                    <i class="fas fa-plus-circle"></i> Generar Más API Keys
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="../admin/api_keys_admin.php" class="btn btn-outline-success btn-sm w-100 mb-2" target="_blank">
                                    <i class="fas fa-cogs"></i> Panel de Administración
                                </a>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <a href="README.md" class="btn btn-outline-info btn-sm w-100 mb-2" target="_blank">
                                    <i class="fas fa-book"></i> Documentación API
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="public_eventos.php?action=listar" class="btn btn-outline-warning btn-sm w-100 mb-2" target="_blank">
                                    <i class="fas fa-api"></i> Probar API
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
                    btn.classList.remove('btn-light');
                    btn.classList.add('btn-success');

                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-light');
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

        // Auto-refresh cada 5 minutos para verificar expiración
        setInterval(() => {
            // Verificar si la página ha expirado (comprobar si el token sigue siendo válido)
            fetch(window.location.href, { method: 'HEAD' })
                .then(response => {
                    if (response.status === 404) {
                        alert('Esta página ha expirado. La API key ya no está disponible.');
                        window.close();
                    }
                })
                .catch(() => {
                    // Ignorar errores de red
                });
        }, 300000); // 5 minutos
    </script>
</body>
</html>
