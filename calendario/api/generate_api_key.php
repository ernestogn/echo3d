<?php
/**
 * Generador de API Keys
 * 
 * Este script genera nuevas API keys seguras para acceder a public_eventos.php
 * 
 * USO:
 * 1. Ejecutar desde línea de comandos: php generate_api_key.php [nombre_cliente] [limite_diario]
 * 2. Ejecutar desde navegador: generate_api_key.php?nombre=cliente&limite=1000
 * 
 * IMPORTANTE: En producción, proteger este archivo o eliminarlo después de generar las keys necesarias
 */

// Solo permitir ejecución desde CLI o localhost
$is_cli = php_sapi_name() === 'cli';
$is_localhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);

if (!$is_cli && !$is_localhost) {
    http_response_code(403);
    die("Acceso denegado. Este script solo puede ejecutarse desde CLI o localhost.");
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/api_keys_config.php';







/**
 * Muestra el uso del script
 */
function mostrarUso() {
    global $is_cli;

    if ($is_cli) {
        echo "\n=== Generador de API Keys ===\n\n";
        echo "USO:\n";
        echo "  php generate_api_key.php <nombre_cliente> [limite_diario] [descripcion]\n\n";
        echo "EJEMPLOS:\n";
        echo "  php generate_api_key.php mi_aplicacion\n";
        echo "  php generate_api_key.php mi_aplicacion 5000\n";
        echo "  php generate_api_key.php mi_aplicacion 5000 \"App móvil de eventos\"\n\n";
    } else {
        echo "<h2>Generador de API Keys</h2>";
        echo "<p><strong>USO:</strong></p>";
        echo "<ul>";
        echo "<li>generate_api_key.php?nombre=cliente</li>";
        echo "<li>generate_api_key.php?nombre=cliente&limite=5000</li>";
        echo "<li>generate_api_key.php?nombre=cliente&limite=5000&descripcion=Mi+App</li>";
        echo "</ul>";
    }
}

/**
 * Muestra la interfaz web completa para generar API keys
 */
function mostrarInterfazWeb($errores = [], $api_generada = false, $datos_api = null) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de API Keys - Sistema de Eventos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .generator-container { max-width: 800px; margin: 2rem auto; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-generate { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; }
        .btn-generate:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.15); }
        .form-floating > label { color: #6c757d; }
        .alert { border-radius: 10px; border: none; }
        .api-key-result { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 10px; padding: 20px; margin: 20px 0; }
        .code-display { background: rgba(255,255,255,0.2); padding: 15px; border-radius: 8px; margin: 10px 0; font-family: 'Courier New', monospace; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container generator-container">
        <div class="row">
            <div class="col-12">
                <div class="text-center mb-4">
                    <i class="fas fa-key fa-3x text-primary mb-3"></i>
                    <h1 class="h2">Generador de API Keys</h1>
                    <p class="text-muted">Crea API keys seguras para acceder a la API pública de eventos</p>
                </div>

                <!-- Mensajes de error -->
                <?php if (!empty($errores)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Errores encontrados:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errores as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- API Key generada -->
                <?php if ($api_generada && $datos_api): ?>
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-check-circle"></i> API Key Generada Exitosamente</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <i class="fas fa-key"></i> <strong>¡API Key creada!</strong>
                            <?php if ($datos_api['guardado']): ?>
                            La configuración se guardó automáticamente.
                            <?php else: ?>
                            <strong>Nota:</strong> La configuración no se pudo guardar automáticamente. Copia la información abajo.
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-user-tag"></i> Información del Cliente</h6>
                                <p><strong>Nombre:</strong> <?= htmlspecialchars($datos_api['nombre']) ?></p>
                                <p><strong>Límite por hora:</strong> <?= $datos_api['limite'] ?> requests</p>
                                <?php if ($datos_api['descripcion']): ?>
                                <p><strong>Descripción:</strong> <?= htmlspecialchars($datos_api['descripcion']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-shield-alt"></i> API Key (Guárdala Seguro)</h6>
                                <div class="bg-light p-3 rounded border">
                                    <code class="text-break fs-6" style="word-break: break-all;">
                                        <?= $datos_api['api_key'] ?>
                                    </code>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary mt-2" onclick="copiarAlPortapapeles('<?= $datos_api['api_key'] ?>')">
                                    <i class="fas fa-copy"></i> Copiar
                                </button>
                            </div>
                        </div>

                        <?php if (!$datos_api['guardado']): ?>
                        <div class="alert alert-warning mt-3">
                            <h6><i class="fas fa-exclamation-triangle"></i> Configuración Manual Requerida</h6>
                            <p>Agrega esta entrada al array <code>$API_KEYS</code> en <code>api_keys_config.php</code>:</p>
                            <pre class="bg-dark text-light p-3 rounded"><code>'<?= htmlspecialchars($datos_api['nombre']) ?>' => [
    'hash' => '<?= $datos_api['hash'] ?>',
    'activa' => true,
    'limite_diario' => <?= $datos_api['limite'] ?>,
    'descripcion' => '<?= htmlspecialchars($datos_api['descripcion']) ?>',
    'fecha_creacion' => '<?= date('Y-m-d') ?>',
    'creada_por' => 'generador_api'
],</code></pre>
                        </div>
                        <?php endif; ?>

                        <?php if ($datos_api['token_acceso']): ?>
                        <div class="alert alert-success mt-3">
                            <h6><i class="fas fa-link"></i> Enlace de Recuperación Temporal</h6>
                            <p>Si pierdes esta API key, puedes acceder a ella durante 24 horas usando este enlace:</p>
                            <div class="bg-light p-2 rounded border">
                                <code class="small text-break">
                                    <?php
                                    $base_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
                                    echo $base_url . "/view_api_key.php?token=" . $datos_api['token_acceso'];
                                    ?>
                                </code>
                            </div>
                            <div class="mt-2">
                                <a href="view_api_key.php?token=<?= $datos_api['token_acceso'] ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> Abrir Página de Recuperación
                                </a>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-clock"></i> Este enlace expirará en 24 horas
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-info mt-3">
                            <h6><i class="fas fa-code"></i> Ejemplos de Uso</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Header Authorization:</strong>
                                    <code class="d-block small mt-1">Authorization: Bearer <?= substr($datos_api['api_key'], 0, 20) ?>...</code>
                                </div>
                                <div class="col-md-4">
                                    <strong>Header X-API-Key:</strong>
                                    <code class="d-block small mt-1">X-API-Key: <?= substr($datos_api['api_key'], 0, 20) ?>...</code>
                                </div>
                                <div class="col-md-4">
                                    <strong>Query Parameter:</strong>
                                    <code class="d-block small mt-1">?api_key=<?= substr($datos_api['api_key'], 0, 20) ?>...</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Formulario de generación -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Generar Nueva API Key</h5>
                    </div>
                    <div class="card-body">
                        <form id="formGenerarKey" method="get" action="">
                            <input type="hidden" name="generar" value="1">

                            <div class="row g-3">
                            <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="nombre" name="nombre" required
                                               placeholder="Nombre del cliente" pattern="[a-zA-Z0-9\s\-_&().,]+">
                                        <label for="nombre">
                                            <i class="fas fa-user-tag"></i> Nombre del Cliente *
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Letras, números, espacios, guiones, guiones bajos, & ( ) . ,
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="limite" name="limite" value="1000" min="1" max="100000">
                                        <label for="limite">
                                            <i class="fas fa-clock"></i> Límite por Hora
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Requests permitidos por hora (1-100,000)
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="descripcion" name="descripcion"
                                               placeholder="Descripción opcional">
                                        <label for="descripcion">
                                            <i class="fas fa-comment"></i> Descripción
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Descripción opcional del cliente
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-generate btn-lg text-white">
                                    <i class="fas fa-magic"></i> Generar API Key
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Información del sistema -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                <h6>Seguridad</h6>
                                <small class="text-muted">HMAC-SHA256 + Salt único</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                <h6>Rate Limiting</h6>
                                <small class="text-muted">Control de frecuencia automática</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Keys existentes -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> API Keys Existentes</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        global $API_KEYS;
                        if (empty($API_KEYS)):
                        ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>No hay API keys configuradas aún</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th>Límite/Hora</th>
                                        <th>Fecha Creación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($API_KEYS as $nombre => $datos): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($nombre) ?></strong>
                                            <?php if ($datos['descripcion']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($datos['descripcion']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $datos['activa'] ? 'success' : 'secondary' ?>">
                                                <i class="fas fa-<?= $datos['activa'] ? 'check-circle' : 'pause-circle' ?>"></i>
                                                <?= $datos['activa'] ? 'Activa' : 'Inactiva' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= $datos['limite_diario'] ?? 1000 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $datos['fecha_creacion'] ?? 'N/A' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Enlaces útiles -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-external-link-alt"></i> Enlaces Útiles</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <a href="../admin/api_keys_admin.php" class="btn btn-outline-primary btn-sm w-100 mb-2" target="_blank">
                                    <i class="fas fa-cogs"></i> Panel de Administración
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="README.md" class="btn btn-outline-info btn-sm w-100 mb-2" target="_blank">
                                    <i class="fas fa-book"></i> Documentación API
                                </a>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <a href="../calendario.php" class="btn btn-outline-success btn-sm w-100 mb-2" target="_blank">
                                    <i class="fas fa-calendar"></i> Ver Calendario
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
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-success');

                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 2000);
            } catch (err) {
                // Fallback para navegadores antiguos
                const textArea = document.createElement('textarea');
                textArea.value = texto;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);

                alert('API Key copiada al portapapeles');
            }
        }

        // Validación del formulario
        document.getElementById('formGenerarKey').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();

            // Validar nombre
            if (!/^[a-zA-Z0-9\s\-_&().,]+$/.test(nombre)) {
                e.preventDefault();
                alert('El nombre solo puede contener letras, números, espacios, guiones, guiones bajos, & ( ) . ,');
                return false;
            }

            // Mostrar indicador de carga
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            btn.disabled = true;

            // Revertir después de 10 segundos por si acaso
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 10000);
        });

        // Limpiar formulario después de generar API key
        <?php if ($api_generada): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Resetear formulario después de 3 segundos
            setTimeout(() => {
                document.getElementById('formGenerarKey').reset();
            }, 3000);
        });
        <?php endif; ?>
    </script>
</body>
</html>
    <?php
}

// Procesar parámetros
$nombre = null;
$limite = 1000;
$descripcion = '';
$errores = [];
$api_generada = false;
$datos_api = null;

if ($is_cli) {
    // Parámetros desde CLI
    if ($argc < 2) {
        mostrarUso();
        exit(1);
    }

    $nombre = $argv[1] ?? null;
    $limite = intval($argv[2] ?? 1000);
    $descripcion = $argv[3] ?? '';

} else {
    // Parámetros desde GET
    $nombre = $_GET['nombre'] ?? $_GET['name'] ?? null;
    $limite = intval($_GET['limite'] ?? $_GET['limit'] ?? 1000);
    $descripcion = $_GET['descripcion'] ?? $_GET['description'] ?? '';

    // Si se envió el formulario (parámetro 'generar' presente), procesar
    if (isset($_GET['generar'])) {
        // Validar y procesar
        if (empty(trim($nombre))) {
            $errores[] = 'El nombre del cliente es obligatorio';
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-_&().,]+$/', $nombre)) {
            $errores[] = 'El nombre contiene caracteres no permitidos';
        }

        if ($limite < 1 || $limite > 100000) {
            $errores[] = 'El límite debe estar entre 1 y 100,000';
        }

        // Verificar que no exista ya
        global $API_KEYS;
        if (isset($API_KEYS[$nombre])) {
            $errores[] = 'Ya existe una API key con ese nombre';
        }

        // Si no hay errores, generar la API key
        if (empty($errores)) {
            $api_key = generarApiKey();
            $hash = generarHashApiKey($api_key);

            // Guardar directamente en base de datos
            $guardado = agregarKeyAConfiguracion($nombre, $hash, $limite, $descripcion, null, $api_key);

            $datos_api = [
                'nombre' => $nombre,
                'api_key' => $api_key,
                'hash' => $hash,
                'limite' => $limite,
                'descripcion' => $descripcion,
                'guardado' => $guardado
            ];

            $api_generada = true;
        }
    }

    // Mostrar interfaz web (siempre, con o sin resultados)
    mostrarInterfazWeb($errores, $api_generada, $datos_api);
    exit(0);
}
