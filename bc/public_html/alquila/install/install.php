<?php
/**
 * Script de instalación — ejecutar UNA VEZ para crear las tablas del sistema
 * URL: http://localhost/echo3d/bc/public_html/alquila/install/install.php
 * IMPORTANTE: eliminar este archivo después de ejecutarlo en producción
 */

// Seguridad mínima: requiere una clave de instalación
$installKey = 'CAMBIA_ESTA_CLAVE_SEGURA_2026';
if (!isset($_GET['key']) || $_GET['key'] !== $installKey) {
    http_response_code(403);
    die('<h2>Acceso denegado. Parame la clave: ?key=...</h2>');
}

// Cargar config
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_production = strpos($host, 'echo3dlaser.com.ar') !== false;
if ($is_production) {
    $db_host = 'localhost'; $db_name = 'c2801037_data';
    $db_user = 'c2801037_data'; $db_pass = 'zeTIze34pu';
} else {
    $db_host = 'localhost'; $db_name = 'encuesdata';
    $db_user = 'root'; $db_pass = 'hG7!yW4&zPnD6uR$';
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die('<h2>Error de conexión: ' . htmlspecialchars($e->getMessage()) . '</h2>');
}

$schemaFile = dirname(__DIR__) . '/carpas_schema.sql';
if (!file_exists($schemaFile)) {
    die('<h2>Archivo schema no encontrado: ' . htmlspecialchars($schemaFile) . '</h2>');
}

$sql = file_get_contents($schemaFile);
$statements = array_filter(array_map('trim', explode(';', $sql)));
$results    = [];
$errors     = [];

foreach ($statements as $stmt) {
    if (empty($stmt) || strpos($stmt, '--') === 0 || strpos($stmt, 'SET') === 0) continue;
    try {
        $pdo->exec($stmt . ';');
        $firstLine = mb_substr($stmt, 0, 80);
        $results[] = ['ok' => true, 'sql' => $firstLine];
    } catch (PDOException $e) {
        $errors[] = ['sql' => mb_substr($stmt, 0, 80), 'error' => $e->getMessage()];
    }
}

// Crear superadmin inicial si no existe
$superAdminEmail    = 'superadmin@alquila.com';
$superAdminPassword = 'AlquilaAdmin2026!';

$stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email=? AND (tenant_id IS NULL OR rol='superadmin') LIMIT 1");
$stmtCheck->execute([$superAdminEmail]);
$superAdminCreated = false;

if (!$stmtCheck->fetch()) {
    $hash = password_hash($superAdminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    // Intentar insertar con tenant_id NULL (verificar si la columna existe)
    try {
        $pdo->prepare(
            "INSERT INTO usuarios (nombre, email, password, rol, activo, created_at)
             VALUES ('Superadmin', ?, ?, 'superadmin', 1, NOW())"
        )->execute([$superAdminEmail, $hash]);
        $superAdminCreated = true;
    } catch (PDOException $ex) {
        $errors[] = ['sql' => 'INSERT superadmin', 'error' => $ex->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalación — Sistema Alquila</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width:800px;">
    <h2 class="mb-4">⚙ Instalación — Sistema Alquila</h2>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-warning">
        <strong>Algunas sentencias tuvieron errores</strong> (puede ser normal en re-instalaciones):
        <ul class="mb-0 mt-2">
        <?php foreach ($errors as $err): ?>
            <li><code><?= htmlspecialchars($err['sql']) ?>...</code> — <?= htmlspecialchars($err['error']) ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="alert alert-success">
        <strong>✓ <?= count($results) ?> sentencias ejecutadas correctamente.</strong>
    </div>

    <?php if ($superAdminCreated): ?>
    <div class="alert alert-info">
        <h5>🔐 Superadmin creado</h5>
        <ul class="mb-0">
            <li>Email: <code><?= htmlspecialchars($superAdminEmail) ?></code></li>
            <li>Password: <code><?= htmlspecialchars($superAdminPassword) ?></code></li>
        </ul>
        <p class="mt-2 mb-0"><strong>⚠ Cambiá la contraseña inmediatamente después de ingresar.</strong></p>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary">El superadmin ya existía. No se sobreescribió.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header fw-bold">Próximos pasos</div>
        <div class="card-body">
            <ol>
                <li>Ingresá al panel superadmin: <a href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/echo3d/bc/public_html/alquila/public/?superadmin/login'; ?>">superadmin/login</a></li>
                <li>Creá el primer tenant (empresa de carpas)</li>
                <li>Configurá el email SMTP en <code>config/mail.php</code></li>
                <li><strong class="text-danger">Eliminá este archivo install.php del servidor de producción</strong></li>
            </ol>
        </div>
    </div>
</div>
</body>
</html>
