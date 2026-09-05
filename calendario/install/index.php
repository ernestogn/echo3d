<?php
/**
 * Instalador Completo del Sistema Calendario
 *
 * Instalador web completo para configurar todo el sistema calendario
 * en cualquier servidor desde cero.
 */

session_start();

// Configuración inicial
define('CALENDARIO_VERSION', '1.0.0');
define('MIN_PHP_VERSION', '7.4.0');
define('MIN_MYSQL_VERSION', '5.7.0');

// Verificar versión de PHP
if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    die("❌ Error: Requiere PHP " . MIN_PHP_VERSION . " o superior. Versión actual: " . PHP_VERSION);
}

// Verificar extensiones requeridas
$required_extensions = ['pdo', 'pdo_mysql', 'mysqli', 'json', 'mbstring', 'curl', 'gd'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    die("❌ Error: Extensiones PHP requeridas faltantes: " . implode(', ', $missing_extensions));
}

// Estado de la instalación
$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

function isStepCompleted($step_num) {
    return isset($_SESSION['install_step']) && $_SESSION['install_step'] >= $step_num;
}

function completeStep($step_num) {
    $_SESSION['install_step'] = $step_num;
}

function redirectToStep($step_num) {
    header("Location: index.php?step=" . $step_num);
    exit;
}

// Función para verificar conexión a MySQL
function testMySQLConnection($host, $user, $pass, $name = null) {
    try {
        $dsn = "mysql:host=$host;charset=utf8mb4";
        if ($name) {
            $dsn .= ";dbname=$name";
        }
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);

        if ($name) {
            // Verificar versión de MySQL
            $stmt = $pdo->query("SELECT VERSION() as version");
            $version = $stmt->fetch()['version'];

            if (version_compare($version, MIN_MYSQL_VERSION, '<')) {
                throw new Exception("Versión de MySQL insuficiente: $version. Requiere " . MIN_MYSQL_VERSION . "+");
            }
        }

        return $pdo;
    } catch (Exception $e) {
        throw new Exception("Error de conexión MySQL: " . $e->getMessage());
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1: // Verificación de requisitos
            if (isset($_POST['check_requirements'])) {
                // Verificar permisos de escritura
                $writable_dirs = ['../logs', '../cache', '../api', '../admin'];
                $unwritable_dirs = [];

                foreach ($writable_dirs as $dir) {
                    $full_path = __DIR__ . '/' . $dir;
                    if (!is_dir($full_path)) {
                        @mkdir($full_path, 0755, true);
                    }
                    if (!is_writable($full_path)) {
                        $unwritable_dirs[] = $dir;
                    }
                }

                // Verificar si ya existe una instalación
                $config_file = __DIR__ . '/../config.php';
                if (file_exists($config_file)) {
                    $error = "⚠️  Ya existe una instalación. Si desea reinstalar, elimine el archivo config.php primero.";
                } elseif (!empty($unwritable_dirs)) {
                    $error = "❌ Directorios sin permisos de escritura: " . implode(', ', $unwritable_dirs);
                } else {
                    $success = "✅ Todos los requisitos cumplidos";
                    completeStep(1);
                    redirectToStep(2);
                }
            }
            break;

        case 2: // Configuración de base de datos
            if (isset($_POST['configure_database'])) {
                $db_host = trim($_POST['db_host'] ?? '');
                $db_name = trim($_POST['db_name'] ?? '');
                $db_user = trim($_POST['db_user'] ?? '');
                $db_pass = $_POST['db_pass'] ?? '';
                $db_prefix = trim($_POST['db_prefix'] ?? 'calendario_');

                if (empty($db_host) || empty($db_name) || empty($db_user)) {
                    $error = "❌ Host, base de datos y usuario son requeridos";
                } else {
                    try {
                        // Probar conexión sin base de datos específica primero
                        $pdo = testMySQLConnection($db_host, $db_user, $db_pass);

                        // Verificar si la base de datos existe, si no, intentar crearla
                        $stmt = $pdo->query("SHOW DATABASES LIKE '$db_name'");
                        if ($stmt->rowCount() == 0) {
                            // Intentar crear la base de datos
                            $pdo->exec("CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        }

                        // Conectar a la base de datos específica
                        $pdo = testMySQLConnection($db_host, $db_user, $db_pass, $db_name);

                        // Ejecutar script SQL
                        $sql = file_get_contents(__DIR__ . '/database.sql');
                        $statements = array_filter(array_map('trim', explode(';', $sql)));

                        foreach ($statements as $statement) {
                            if (!empty($statement)) {
                                $statement = str_replace('{{PREFIX}}', $db_prefix, $statement);
                                $pdo->exec($statement);
                            }
                        }

                        // Guardar configuración de BD
                        $_SESSION['db_config'] = [
                            'host' => $db_host,
                            'name' => $db_name,
                            'user' => $db_user,
                            'pass' => $db_pass,
                            'prefix' => $db_prefix
                        ];

                        $success = "✅ Base de datos configurada correctamente con todas las tablas";
                        completeStep(2);
                        redirectToStep(3);

                    } catch (Exception $e) {
                        $error = "❌ Error de base de datos: " . $e->getMessage();
                    }
                }
            }
            break;

        case 3: // Configuración del sitio y administrador
            if (isset($_POST['configure_site'])) {
                $site_url = trim($_POST['site_url'] ?? '');
                $site_name = trim($_POST['site_name'] ?? 'Sistema Calendario');
                $admin_name = trim($_POST['admin_name'] ?? '');
                $admin_email = trim($_POST['admin_email'] ?? '');
                $admin_pass = $_POST['admin_pass'] ?? '';
                $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';

                if (empty($site_url) || empty($admin_name) || empty($admin_email) || empty($admin_pass)) {
                    $error = "❌ Todos los campos son requeridos";
                } elseif (!filter_var($site_url, FILTER_VALIDATE_URL)) {
                    $error = "❌ URL del sitio inválida";
                } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                    $error = "❌ Email del administrador inválido";
                } elseif (strlen($admin_pass) < 8) {
                    $error = "❌ La contraseña debe tener al menos 8 caracteres";
                } elseif ($admin_pass !== $admin_pass_confirm) {
                    $error = "❌ Las contraseñas no coinciden";
                } else {
                    // Guardar configuración del sitio
                    $_SESSION['site_config'] = [
                        'url' => $site_url,
                        'name' => $site_name,
                        'admin_name' => $admin_name,
                        'admin_email' => $admin_email,
                        'admin_pass' => password_hash($admin_pass, PASSWORD_DEFAULT)
                    ];

                    $success = "✅ Configuración del sitio guardada correctamente";
                    completeStep(3);
                    redirectToStep(4);
                }
            }
            break;

        case 4: // Configuración de API y opciones avanzadas
            if (isset($_POST['configure_api'])) {
                $api_enabled = isset($_POST['api_enabled']);
                $rate_limit_default = intval($_POST['rate_limit_default'] ?? 100);
                $rate_limit_no_key = intval($_POST['rate_limit_no_key'] ?? 50);
                $timezone = $_POST['timezone'] ?? 'America/Argentina/Buenos_Aires';
                $debug_mode = isset($_POST['debug_mode']);

                // Guardar configuración de API
                $_SESSION['api_config'] = [
                    'enabled' => $api_enabled,
                    'rate_limit_default' => $rate_limit_default,
                    'rate_limit_no_key' => $rate_limit_no_key,
                    'timezone' => $timezone,
                    'debug_mode' => $debug_mode
                ];

                $success = "✅ Configuración de API guardada correctamente";
                completeStep(4);
                redirectToStep(5);
            }
            break;

        case 5: // Instalación final
            if (isset($_POST['complete_installation'])) {
                try {
                    // Generar archivo de configuración final
                    $db_config = $_SESSION['db_config'];
                    $site_config = $_SESSION['site_config'];
                    $api_config = $_SESSION['api_config'];

                    $config_content = "<?php\n";
                    $config_content .= "/**\n";
                    $config_content .= " * Configuración del Sistema Calendario\n";
                    $config_content .= " * Generado automáticamente por el instalador\n";
                    $config_content .= " */\n\n";

                    $config_content .= "// Configuración de base de datos\n";
                    $config_content .= "\$db_config = [\n";
                    $config_content .= "    'host' => '" . addslashes($db_config['host']) . "',\n";
                    $config_content .= "    'name' => '" . addslashes($db_config['name']) . "',\n";
                    $config_content .= "    'user' => '" . addslashes($db_config['user']) . "',\n";
                    $config_content .= "    'pass' => '" . addslashes($db_config['pass']) . "',\n";
                    $config_content .= "    'prefix' => '" . addslashes($db_config['prefix']) . "',\n";
                    $config_content .= "];\n\n";

                    $config_content .= "// Configuración del sitio\n";
                    $config_content .= "\$site_config = [\n";
                    $config_content .= "    'url' => '" . addslashes($site_config['url']) . "',\n";
                    $config_content .= "    'name' => '" . addslashes($site_config['name']) . "',\n";
                    $config_content .= "    'admin_name' => '" . addslashes($site_config['admin_name']) . "',\n";
                    $config_content .= "    'admin_email' => '" . addslashes($site_config['admin_email']) . "',\n";
                    $config_content .= "];\n\n";

                    $config_content .= "// Configuración de API\n";
                    $config_content .= "\$api_config = [\n";
                    $config_content .= "    'enabled' => " . ($api_config['enabled'] ? 'true' : 'false') . ",\n";
                    $config_content .= "    'rate_limit_default' => " . $api_config['rate_limit_default'] . ",\n";
                    $config_content .= "    'rate_limit_no_key' => " . $api_config['rate_limit_no_key'] . ",\n";
                    $config_content .= "    'timezone' => '" . addslashes($api_config['timezone']) . "',\n";
                    $config_content .= "    'debug_mode' => " . ($api_config['debug_mode'] ? 'true' : 'false') . ",\n";
                    $config_content .= "];\n\n";

                    $config_content .= "// Configuración general\n";
                    $config_content .= "\$config = [\n";
                    $config_content .= "    'version' => '" . CALENDARIO_VERSION . "',\n";
                    $config_content .= "    'installed_at' => '" . date('Y-m-d H:i:s') . "',\n";
                    $config_content .= "    'environment' => 'production',\n";
                    $config_content .= "];\n\n";

                    $config_content .= "// Crear conexión PDO\n";
                    $config_content .= "try {\n";
                    $config_content .= "    \$pdo = new PDO(\n";
                    $config_content .= "        \"mysql:host={\$db_config['host']};dbname={\$db_config['name']};charset=utf8mb4\",\n";
                    $config_content .= "        \$db_config['user'],\n";
                    $config_content .= "        \$db_config['pass'],\n";
                    $config_content .= "        [\n";
                    $config_content .= "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
                    $config_content .= "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
                    $config_content .= "            PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8mb4\"\n";
                    $config_content .= "        ]\n";
                    $config_content .= "    );\n";
                    $config_content .= "} catch (Exception \$e) {\n";
                    $config_content .= "    die('Error de conexión a la base de datos: ' . \$e->getMessage());\n";
                    $config_content .= "}\n\n";

                    $config_content .= "// Establecer zona horaria\n";
                    $config_content .= "date_default_timezone_set('{$api_config['timezone']}');\n\n";

                    $config_content .= "// Función de utilidad para obtener configuración\n";
                    $config_content .= "function getConfig(\$key = null) {\n";
                    $config_content .= "    global \$db_config, \$site_config, \$api_config, \$config;\n";
                    $config_content .= "    \$all_config = array_merge(\$db_config, \$site_config, \$api_config, \$config);\n";
                    $config_content .= "    return \$key ? (\$all_config[\$key] ?? null) : \$all_config;\n";
                    $config_content .= "}\n\n";

                    $config_content .= "// Verificar instalación\n";
                    $config_content .= "define('CALENDARIO_INSTALLED', true);\n";
                    $config_content .= "define('CALENDARIO_VERSION', '" . CALENDARIO_VERSION . "');\n";

                    // Actualizar contraseña del administrador en la base de datos
                    $pdo = new PDO(
                        "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
                        $db_config['user'],
                        $db_config['pass']
                    );

                    $stmt = $pdo->prepare("UPDATE {$db_config['prefix']}usuarios SET password = ?, nombre = ?, email = ? WHERE rol = 'admin' LIMIT 1");
                    $stmt->execute([$site_config['admin_pass'], $site_config['admin_name'], $site_config['admin_email']]);

                    // Actualizar configuración en la base de datos
                    $stmt = $pdo->prepare("INSERT INTO {$db_config['prefix']}configuracion (clave, valor, tipo) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
                    $configs = [
                        ['sistema_version', CALENDARIO_VERSION, 'string'],
                        ['sistema_instalado', date('Y-m-d H:i:s'), 'string'],
                        ['site_url', $site_config['url'], 'string'],
                        ['site_name', $site_config['name'], 'string'],
                        ['admin_email', $site_config['admin_email'], 'string'],
                        ['api_habilitada', $api_config['enabled'] ? '1' : '0', 'bool'],
                        ['api_rate_limit_default', (string)$api_config['rate_limit_default'], 'int'],
                        ['api_rate_limit_sin_key', (string)$api_config['rate_limit_no_key'], 'int'],
                        ['timezone', $api_config['timezone'], 'string'],
                        ['debug_mode', $api_config['debug_mode'] ? '1' : '0', 'bool']
                    ];

                    foreach ($configs as $config) {
                        $stmt->execute($config);
                    }

                    // Guardar archivo de configuración
                    if (file_put_contents(__DIR__ . '/../config.php', $config_content)) {
                        // Crear archivo .htaccess básico
                        $htaccess_content = "RewriteEngine On\n\n";
                        $htaccess_content .= "# Redirigir todo al index.php del calendario\n";
                        $htaccess_content .= "RewriteRule ^(.*)$ calendario.php [QSA,L]\n\n";
                        $htaccess_content .= "# Seguridad\n";
                        $htaccess_content .= "<Files \"config.php\">\n";
                        $htaccess_content .= "    Order deny,allow\n";
                        $htaccess_content .= "    Deny from all\n";
                        $htaccess_content .= "</Files>\n";

                        file_put_contents(__DIR__ . '/../.htaccess', $htaccess_content);

                        $success = "✅ ¡Instalación completada exitosamente!";
                        completeStep(5);

                        // Limpiar archivos de instalación
                        @unlink(__DIR__ . '/index.php');
                        @unlink(__DIR__ . '/database.sql');
                        @rmdir(__DIR__);

                    } else {
                        $error = "❌ Error al guardar el archivo de configuración";
                    }

                } catch (Exception $e) {
                    $error = "❌ Error durante la instalación: " . $e->getMessage();
                }
            }
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - Sistema Calendario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .install-card { box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: none; border-radius: 15px; }
        .step-active { background: #007bff; color: white; }
        .step-completed { background: #28a745; color: white; }
        .step-indicator { width: 50px; height: 50px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
        .requirement-met { color: #28a745; }
        .requirement-failed { color: #dc3545; }
        .feature-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .feature-card { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; padding: 20px; text-align: center; }
        .feature-card i { font-size: 2em; margin-bottom: 10px; color: #fff; }
        .feature-card h5 { color: #fff; margin-bottom: 10px; }
        .feature-card p { color: rgba(255,255,255,0.8); margin: 0; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="text-center mb-5">
                    <h1 class="text-white mb-3">
                        <i class="fas fa-calendar-alt fa-2x"></i><br>
                        Sistema Calendario
                    </h1>
                    <p class="text-white-50 h4">Instalador Completo Automático</p>
                    <p class="text-white-50">Versión <?php echo CALENDARIO_VERSION; ?></p>
                </div>

                <!-- Características del sistema -->
                <?php if ($step == 1): ?>
                <div class="card install-card mb-4">
                    <div class="card-body">
                        <h4 class="card-title text-center mb-4">🚀 Características del Sistema</h4>
                        <div class="feature-list">
                            <div class="feature-card">
                                <i class="fas fa-calendar-check"></i>
                                <h5>Gestión de Eventos</h5>
                                <p>CRUD completo de eventos con categorías, participantes y estados</p>
                            </div>
                            <div class="feature-card">
                                <i class="fas fa-users"></i>
                                <h5>Participantes</h5>
                                <p>Gestión de inscripciones y seguimiento de asistencia</p>
                            </div>
                            <div class="feature-card">
                                <i class="fas fa-plug"></i>
                                <h5>API Pública</h5>
                                <p>API REST completa con autenticación y rate limiting</p>
                            </div>
                            <div class="feature-card">
                                <i class="fas fa-chart-bar"></i>
                                <h5>Panel Admin</h5>
                                <p>Interfaz completa para gestión y estadísticas</p>
                            </div>
                            <div class="feature-card">
                                <i class="fas fa-key"></i>
                                <h5>API Keys</h5>
                                <p>Sistema seguro de claves API con encriptación</p>
                            </div>
                            <div class="feature-card">
                                <i class="fas fa-mobile-alt"></i>
                                <h5>Responsive</h5>
                                <p>Diseño adaptativo para móviles y tablets</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Indicador de progreso -->
                <div class="card install-card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <?php
                            $steps = [
                                1 => ['title' => 'Requisitos', 'icon' => 'fas fa-check-circle'],
                                2 => ['title' => 'Base de Datos', 'icon' => 'fas fa-database'],
                                3 => ['title' => 'Administrador', 'icon' => 'fas fa-user-cog'],
                                4 => ['title' => 'API', 'icon' => 'fas fa-plug'],
                                5 => ['title' => 'Finalizar', 'icon' => 'fas fa-rocket']
                            ];

                            foreach ($steps as $step_num => $step_info) {
                                $classes = 'step-indicator ';
                                if ($step_num == $step) {
                                    $classes .= 'step-active';
                                } elseif (isStepCompleted($step_num)) {
                                    $classes .= 'step-completed';
                                } else {
                                    $classes .= 'bg-light text-muted';
                                }
                                echo "<div class='$classes'><i class='{$step_info['icon']}'></i></div>";
                            }
                            ?>
                        </div>
                        <div class="mt-3">
                            <?php
                            foreach ($steps as $step_num => $step_info) {
                                $active = ($step_num == $step) ? 'text-primary fw-bold' : (isStepCompleted($step_num) ? 'text-success' : 'text-muted');
                                echo "<small class='$active d-block d-md-inline'>{$step_info['title']}</small>";
                                if ($step_num < count($steps)) echo " <i class='fas fa-chevron-right mx-2 text-muted d-none d-md-inline'></i> ";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Mensajes -->
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Contenido de pasos -->
                <div class="card install-card">
                    <div class="card-body">

                        <?php if ($step == 1): ?>
                        <!-- Paso 1: Verificación de requisitos -->
                        <h3 class="card-title mb-4">
                            <i class="fas fa-check-circle text-success"></i> Verificación de Requisitos del Sistema
                        </h3>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Requisitos de Servidor:</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        PHP <?php echo MIN_PHP_VERSION; ?>+
                                        <span class="badge <?php echo version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=') ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo PHP_VERSION; ?> <?php echo version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=') ? '(OK)' : '(FALLA)'; ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        MySQL <?php echo MIN_MYSQL_VERSION; ?>+
                                        <span class="badge bg-secondary">Se verificará después</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Extensiones PHP
                                        <span class="badge <?php echo empty($missing_extensions) ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo empty($missing_extensions) ? 'Todas OK' : count($missing_extensions) . ' faltantes'; ?>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h5>Permisos de Archivos:</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <strong>Directorios que se crearán:</strong>
                                        <ul class="mt-2">
                                            <li><code>logs/</code> - Archivos de log del sistema</li>
                                            <li><code>cache/</code> - Archivos de caché</li>
                                            <li><code>admin/</code> - Archivos de administración</li>
                                            <li><code>api/</code> - Endpoints de la API</li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <?php if (!empty($missing_extensions)): ?>
                        <div class="alert alert-danger">
                            <h6>Extensiones PHP faltantes:</h6>
                            <ul class="mb-0">
                                <?php foreach ($missing_extensions as $ext): ?>
                                <li><code><?php echo $ext; ?></code></li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="mb-0 mt-2">Contacte a su administrador de servidor para instalar estas extensiones.</p>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Nota:</strong> El instalador creará automáticamente todos los archivos y directorios necesarios.
                            Asegúrese de que el servidor web tenga permisos de escritura.
                        </div>

                        <form method="post">
                            <button type="submit" name="check_requirements" class="btn btn-primary btn-lg">
                                <i class="fas fa-play"></i> Verificar y Continuar
                            </button>
                        </form>

                        <?php elseif ($step == 2): ?>
                        <!-- Paso 2: Configuración de base de datos -->
                        <h3 class="card-title mb-4">
                            <i class="fas fa-database text-primary"></i> Configuración de Base de Datos
                        </h3>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Nota:</strong> El sistema creará automáticamente la base de datos si no existe,
                            junto con todas las tablas necesarias para el funcionamiento completo del calendario.
                        </div>

                        <form method="post">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="db_host" class="form-label">
                                        <strong>Servidor MySQL *</strong>
                                    </label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                                    <div class="form-text">Generalmente localhost o 127.0.0.1</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="db_name" class="form-label">
                                        <strong>Nombre de la Base de Datos *</strong>
                                    </label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" placeholder="calendario_db" required>
                                    <div class="form-text">Se creará automáticamente si no existe</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="db_user" class="form-label">
                                        <strong>Usuario MySQL *</strong>
                                    </label>
                                    <input type="text" class="form-control" id="db_user" name="db_user" placeholder="usuario_mysql" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="db_pass" class="form-label">
                                        <strong>Contraseña MySQL</strong>
                                    </label>
                                    <input type="password" class="form-control" id="db_pass" name="db_pass" placeholder="contraseña">
                                    <div class="form-text">Dejar vacío si no tiene contraseña</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="db_prefix" class="form-label">
                                        <strong>Prefijo de Tablas</strong>
                                    </label>
                                    <input type="text" class="form-control" id="db_prefix" name="db_prefix" value="calendario_" placeholder="calendario_">
                                    <div class="form-text">Prefijo para evitar conflictos</div>
                                </div>
                            </div>

                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Importante:</strong> El usuario MySQL debe tener permisos para crear bases de datos y tablas.
                            </div>

                            <button type="submit" name="configure_database" class="btn btn-success btn-lg">
                                <i class="fas fa-database"></i> Configurar Base de Datos
                            </button>
                        </form>

                        <?php elseif ($step == 3): ?>
                        <!-- Paso 3: Configuración del sitio y administrador -->
                        <h3 class="card-title mb-4">
                            <i class="fas fa-user-cog text-warning"></i> Configuración del Sitio y Administrador
                        </h3>

                        <form method="post">
                            <h5>Información del Sitio:</h5>
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="site_url" class="form-label">
                                        <strong>URL del Sitio *</strong>
                                    </label>
                                    <input type="url" class="form-control" id="site_url" name="site_url"
                                           placeholder="https://misitio.com/calendario"
                                           value="http://<?php echo $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); ?>" required>
                                    <div class="form-text">URL completa donde estará instalado el calendario</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="site_name" class="form-label">
                                        <strong>Nombre del Sitio</strong>
                                    </label>
                                    <input type="text" class="form-control" id="site_name" name="site_name"
                                           value="Sistema Calendario" placeholder="Mi Calendario de Eventos">
                                    <div class="form-text">Nombre que aparecerá en el sistema</div>
                                </div>
                            </div>

                            <h5>Cuenta de Administrador:</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="admin_name" class="form-label">
                                        <strong>Nombre del Administrador *</strong>
                                    </label>
                                    <input type="text" class="form-control" id="admin_name" name="admin_name"
                                           placeholder="Administrador" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="admin_email" class="form-label">
                                        <strong>Email del Administrador *</strong>
                                    </label>
                                    <input type="email" class="form-control" id="admin_email" name="admin_email"
                                           placeholder="admin@misitio.com" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="admin_pass" class="form-label">
                                        <strong>Contraseña *</strong>
                                    </label>
                                    <input type="password" class="form-control" id="admin_pass" name="admin_pass"
                                           placeholder="Mínimo 8 caracteres" minlength="8" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="admin_pass_confirm" class="form-label">
                                        <strong>Confirmar Contraseña *</strong>
                                    </label>
                                    <input type="password" class="form-control" id="admin_pass_confirm" name="admin_pass_confirm"
                                           placeholder="Repetir contraseña" minlength="8" required>
                                </div>
                            </div>

                            <button type="submit" name="configure_site" class="btn btn-warning btn-lg">
                                <i class="fas fa-user-cog"></i> Configurar Sitio y Administrador
                            </button>
                        </form>

                        <?php elseif ($step == 4): ?>
                        <!-- Paso 4: Configuración de API -->
                        <h3 class="card-title mb-4">
                            <i class="fas fa-plug text-info"></i> Configuración de API y Opciones Avanzadas
                        </h3>

                        <form method="post">
                            <h5>API Pública:</h5>
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="api_enabled" name="api_enabled" checked>
                                    <label class="form-check-label" for="api_enabled">
                                        <strong>Habilitar API Pública</strong>
                                    </label>
                                </div>
                                <div class="form-text">Permite acceso externo a los eventos mediante API REST</div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="rate_limit_default" class="form-label">
                                        <strong>Límite por Hora (con API key)</strong>
                                    </label>
                                    <input type="number" class="form-control" id="rate_limit_default" name="rate_limit_default"
                                           value="1000" min="10" max="10000">
                                    <div class="form-text">Requests permitidos por hora con API key</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="rate_limit_no_key" class="form-label">
                                        <strong>Límite por Hora (sin API key)</strong>
                                    </label>
                                    <input type="number" class="form-control" id="rate_limit_no_key" name="rate_limit_no_key"
                                           value="50" min="1" max="500">
                                    <div class="form-text">Requests permitidos por hora sin API key</div>
                                </div>
                            </div>

                            <h5>Configuración Regional:</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="timezone" class="form-label">
                                        <strong>Zona Horaria</strong>
                                    </label>
                                    <select class="form-select" id="timezone" name="timezone">
                                        <option value="America/Argentina/Buenos_Aires" selected>Argentina (Buenos Aires)</option>
                                        <option value="America/Mexico_City">México (Ciudad de México)</option>
                                        <option value="America/Santiago">Chile (Santiago)</option>
                                        <option value="America/Bogota">Colombia (Bogotá)</option>
                                        <option value="America/Lima">Perú (Lima)</option>
                                        <option value="Europe/Madrid">España (Madrid)</option>
                                        <option value="Europe/London">Reino Unido (London)</option>
                                        <option value="America/New_York">Estados Unidos (Nueva York)</option>
                                        <option value="UTC">UTC</option>
                                    </select>
                                </div>
                            </div>

                            <h5>Opciones de Desarrollo:</h5>
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="debug_mode" name="debug_mode">
                                    <label class="form-check-label" for="debug_mode">
                                        <strong>Modo Debug</strong>
                                    </label>
                                </div>
                                <div class="form-text">Muestra información adicional para desarrollo (desactivar en producción)</div>
                            </div>

                            <button type="submit" name="configure_api" class="btn btn-info btn-lg">
                                <i class="fas fa-plug"></i> Configurar API y Continuar
                            </button>
                        </form>

                        <?php elseif ($step == 5): ?>
                        <!-- Paso 5: Instalación final -->
                        <h3 class="card-title mb-4">
                            <i class="fas fa-rocket text-success"></i> ¡Listo para Instalar!
                        </h3>

                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>¡Todas las configuraciones están listas!</strong>
                            El instalador procederá a crear todos los archivos necesarios y configurar el sistema completo.
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>📋 Resumen de Configuración:</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <strong>Base de datos:</strong><br>
                                        <?php echo $_SESSION['db_config']['name'] ?? 'N/A'; ?> en <?php echo $_SESSION['db_config']['host'] ?? 'N/A'; ?>
                                    </li>
                                    <li class="list-group-item">
                                        <strong>Sitio:</strong><br>
                                        <?php echo $_SESSION['site_config']['name'] ?? 'N/A'; ?> (<?php echo $_SESSION['site_config']['url'] ?? 'N/A'; ?>)
                                    </li>
                                    <li class="list-group-item">
                                        <strong>Administrador:</strong><br>
                                        <?php echo $_SESSION['site_config']['admin_name'] ?? 'N/A'; ?> (<?php echo $_SESSION['site_config']['admin_email'] ?? 'N/A'; ?>)
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h5>🔧 Lo que se instalará:</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item"><i class="fas fa-check text-success"></i> Todas las tablas de base de datos</li>
                                    <li class="list-group-item"><i class="fas fa-check text-success"></i> Archivo de configuración (config.php)</li>
                                    <li class="list-group-item"><i class="fas fa-check text-success"></i> Usuario administrador</li>
                                    <li class="list-group-item"><i class="fas fa-check text-success"></i> Datos iniciales (categorías, etc.)</li>
                                    <li class="list-group-item"><i class="fas fa-check text-success"></i> Archivo .htaccess</li>
                                </ul>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Última oportunidad:</strong> Una vez instalada, la instalación se bloqueará automáticamente.
                            Si necesita cambiar algo, cancele y reinicie el proceso.
                        </div>

                        <form method="post">
                            <button type="submit" name="complete_installation" class="btn btn-success btn-lg">
                                <i class="fas fa-rocket"></i> ¡Instalar Sistema Calendario!
                            </button>
                        </form>

                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
