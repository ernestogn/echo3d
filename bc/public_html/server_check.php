<?php
/**
 * Script de diagnóstico de servidor
 * Verifica compatibilidad con frameworks modernos
 * IMPORTANTE: Eliminar después de usar por seguridad
 */

// Configuración de seguridad básica (opcional)
$password = 'mipassword123'; // Cambia esto
if (isset($_GET['pass']) && $_GET['pass'] !== $password) {
    die('Acceso denegado');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico del Servidor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .section {
            margin-bottom: 30px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }
        .section-header {
            background: #f5f5f5;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 1.2em;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-body {
            padding: 20px;
        }
        .badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .badge.success { background: #4caf50; color: white; }
        .badge.warning { background: #ff9800; color: white; }
        .badge.error { background: #f44336; color: white; }
        .badge.info { background: #2196f3; color: white; }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #f9f9f9;
            font-weight: 600;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .framework-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .framework-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .framework-card h3 {
            margin-bottom: 15px;
            color: #667eea;
        }
        .requirement {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .requirement:last-child {
            border-bottom: none;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert.info { background: #e3f2fd; border-left: 4px solid #2196f3; }
        .alert.warning { background: #fff3e0; border-left: 4px solid #ff9800; }
        .alert.success { background: #e8f5e9; border-left: 4px solid #4caf50; }
        .alert.error { background: #ffebee; border-left: 4px solid #f44336; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Diagnóstico del Servidor</h1>
            <p>Análisis de compatibilidad con frameworks modernos</p>
        </div>
        
        <div class="content">
            <?php
            // ============================================
            // FUNCIONES AUXILIARES
            // ============================================
            
            function checkVersion($current, $required) {
                return version_compare($current, $required, '>=');
            }
            
            function getBadge($status) {
                if ($status === true) return '<span class="badge success">✓ Compatible</span>';
                if ($status === false) return '<span class="badge error">✗ No compatible</span>';
                return '<span class="badge warning">⚠ Verificar</span>';
            }
            
            function formatBytes($bytes) {
                $units = ['B', 'KB', 'MB', 'GB'];
                $bytes = max($bytes, 0);
                $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
                $pow = min($pow, count($units) - 1);
                $bytes /= (1 << (10 * $pow));
                return round($bytes, 2) . ' ' . $units[$pow];
            }
            
            // ============================================
            // RECOPILACIÓN DE INFORMACIÓN
            // ============================================
            
            $phpVersion = phpversion();
            $extensions = get_loaded_extensions();
            sort($extensions);
            
            // Información del servidor
            $serverInfo = [
                'Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido',
                'Sistema Operativo' => PHP_OS,
                'Arquitectura' => php_uname('m'),
                'Versión PHP' => $phpVersion,
                'SAPI' => php_sapi_name(),
                'Usuario PHP' => get_current_user(),
            ];
            
            // Configuración PHP importante
            $phpConfig = [
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time') . 's',
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_input_vars' => ini_get('max_input_vars'),
                'display_errors' => ini_get('display_errors') ? 'On' : 'Off',
                'error_reporting' => ini_get('error_reporting'),
                'open_basedir' => ini_get('open_basedir') ?: 'Sin restricción',
                'disable_functions' => ini_get('disable_functions') ?: 'Ninguna',
            ];
            
            // Verificar Composer
            $composerInstalled = false;
            $composerVersion = 'No instalado';
            if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
                $composerCheck = @shell_exec('composer --version 2>&1');
                if ($composerCheck && strpos($composerCheck, 'Composer') !== false) {
                    $composerInstalled = true;
                    preg_match('/Composer version ([0-9.]+)/', $composerCheck, $matches);
                    $composerVersion = $matches[1] ?? 'Instalado';
                }
            }
            
            // Verificar Node.js y npm
            $nodeInstalled = false;
            $nodeVersion = 'No instalado';
            $npmVersion = 'No instalado';
            if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
                $nodeCheck = @shell_exec('node --version 2>&1');
                if ($nodeCheck && strpos($nodeCheck, 'v') !== false) {
                    $nodeInstalled = true;
                    $nodeVersion = trim($nodeCheck);
                }
                
                $npmCheck = @shell_exec('npm --version 2>&1');
                if ($npmCheck) {
                    $npmVersion = trim($npmCheck);
                }
            }
            
            // ============================================
            // COMPATIBILIDAD CON FRAMEWORKS
            // ============================================
            
            $frameworks = [
                'Laravel 11' => [
                    'PHP' => ['required' => '8.2', 'current' => $phpVersion],
                    'Extensions' => [
                        'Ctype' => in_array('ctype', $extensions),
                        'cURL' => in_array('curl', $extensions),
                        'DOM' => in_array('dom', $extensions),
                        'Fileinfo' => in_array('fileinfo', $extensions),
                        'Filter' => in_array('filter', $extensions),
                        'Hash' => in_array('hash', $extensions),
                        'Mbstring' => in_array('mbstring', $extensions),
                        'OpenSSL' => in_array('openssl', $extensions),
                        'PCRE' => in_array('pcre', $extensions),
                        'PDO' => in_array('PDO', $extensions),
                        'Session' => in_array('session', $extensions),
                        'Tokenizer' => in_array('tokenizer', $extensions),
                        'XML' => in_array('xml', $extensions),
                    ],
                    'Composer' => $composerInstalled,
                    'Node.js' => $nodeInstalled,
                ],
                'Laravel 10' => [
                    'PHP' => ['required' => '8.1', 'current' => $phpVersion],
                    'Extensions' => [
                        'Ctype' => in_array('ctype', $extensions),
                        'cURL' => in_array('curl', $extensions),
                        'DOM' => in_array('dom', $extensions),
                        'Fileinfo' => in_array('fileinfo', $extensions),
                        'Filter' => in_array('filter', $extensions),
                        'Hash' => in_array('hash', $extensions),
                        'Mbstring' => in_array('mbstring', $extensions),
                        'OpenSSL' => in_array('openssl', $extensions),
                        'PCRE' => in_array('pcre', $extensions),
                        'PDO' => in_array('PDO', $extensions),
                        'Session' => in_array('session', $extensions),
                        'Tokenizer' => in_array('tokenizer', $extensions),
                        'XML' => in_array('xml', $extensions),
                    ],
                    'Composer' => $composerInstalled,
                    'Node.js' => $nodeInstalled,
                ],
                'Symfony 7' => [
                    'PHP' => ['required' => '8.2', 'current' => $phpVersion],
                    'Extensions' => [
                        'Ctype' => in_array('ctype', $extensions),
                        'iconv' => in_array('iconv', $extensions),
                        'PCRE' => in_array('pcre', $extensions),
                        'Session' => in_array('session', $extensions),
                        'SimpleXML' => in_array('simplexml', $extensions),
                        'Tokenizer' => in_array('tokenizer', $extensions),
                    ],
                    'Composer' => $composerInstalled,
                ],
                'CodeIgniter 4' => [
                    'PHP' => ['required' => '7.4', 'current' => $phpVersion],
                    'Extensions' => [
                        'intl' => in_array('intl', $extensions),
                        'mbstring' => in_array('mbstring', $extensions),
                        'json' => in_array('json', $extensions),
                    ],
                    'Composer' => $composerInstalled,
                ],
            ];
            
            // ============================================
            // MOSTRAR RESULTADOS
            // ============================================
            ?>
            
            <!-- Información General -->
            <div class="section">
                <div class="section-header">
                    🖥️ Información del Servidor
                </div>
                <div class="section-body">
                    <table>
                        <?php foreach ($serverInfo as $key => $value): ?>
                        <tr>
                            <th style="width: 30%"><?= $key ?></th>
                            <td><?= htmlspecialchars($value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            
            <!-- Configuración PHP -->
            <div class="section">
                <div class="section-header">
                    ⚙️ Configuración PHP
                </div>
                <div class="section-body">
                    <table>
                        <?php foreach ($phpConfig as $key => $value): ?>
                        <tr>
                            <th style="width: 30%"><?= $key ?></th>
                            <td><?= htmlspecialchars($value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            
            <!-- Herramientas de Desarrollo -->
            <div class="section">
                <div class="section-header">
                    🛠️ Herramientas de Desarrollo
                </div>
                <div class="section-body">
                    <table>
                        <tr>
                            <th style="width: 30%">Composer</th>
                            <td>
                                <?= $composerInstalled ? 
                                    '<span class="badge success">✓ Instalado</span> ' . htmlspecialchars($composerVersion) : 
                                    '<span class="badge error">✗ No instalado</span>' ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Node.js</th>
                            <td>
                                <?= $nodeInstalled ? 
                                    '<span class="badge success">✓ Instalado</span> ' . htmlspecialchars($nodeVersion) : 
                                    '<span class="badge error">✗ No instalado</span>' ?>
                            </td>
                        </tr>
                        <tr>
                            <th>npm</th>
                            <td>
                                <?= $npmVersion !== 'No instalado' ? 
                                    '<span class="badge success">✓ Instalado</span> ' . htmlspecialchars($npmVersion) : 
                                    '<span class="badge error">✗ No instalado</span>' ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Compatibilidad con Frameworks -->
            <div class="section">
                <div class="section-header">
                    🚀 Compatibilidad con Frameworks PHP
                </div>
                <div class="section-body">
                    <?php foreach ($frameworks as $frameworkName => $requirements): ?>
                        <?php
                        $compatible = true;
                        $warnings = [];
                        
                        // Verificar PHP
                        $phpOk = checkVersion($requirements['PHP']['current'], $requirements['PHP']['required']);
                        if (!$phpOk) {
                            $compatible = false;
                            $warnings[] = "PHP {$requirements['PHP']['required']}+ requerido";
                        }
                        
                        // Verificar extensiones
                        $missingExtensions = [];
                        foreach ($requirements['Extensions'] as $ext => $installed) {
                            if (!$installed) {
                                $compatible = false;
                                $missingExtensions[] = $ext;
                            }
                        }
                        
                        // Verificar Composer
                        if (isset($requirements['Composer']) && !$requirements['Composer']) {
                            $warnings[] = "Composer no detectado";
                        }
                        
                        // Verificar Node.js
                        if (isset($requirements['Node.js']) && !$requirements['Node.js']) {
                            $warnings[] = "Node.js no detectado (necesario para assets)";
                        }
                        ?>
                        
                        <div class="framework-card">
                            <h3>
                                <?= $frameworkName ?>
                                <?= $compatible && empty($warnings) ? 
                                    '<span class="badge success">✓ Compatible</span>' : 
                                    ($compatible ? '<span class="badge warning">⚠ Compatible con advertencias</span>' : '<span class="badge error">✗ No compatible</span>') ?>
                            </h3>
                            
                            <div class="requirement">
                                <span><strong>PHP:</strong> <?= $requirements['PHP']['required'] ?>+</span>
                                <span><?= $phpOk ? 
                                    '<span class="badge success">✓ ' . $phpVersion . '</span>' : 
                                    '<span class="badge error">✗ ' . $phpVersion . '</span>' ?>
                                </span>
                            </div>
                            
                            <?php if (isset($requirements['Composer'])): ?>
                            <div class="requirement">
                                <span><strong>Composer:</strong></span>
                                <span><?= $requirements['Composer'] ? 
                                    '<span class="badge success">✓ Instalado</span>' : 
                                    '<span class="badge error">✗ No instalado</span>' ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($requirements['Node.js'])): ?>
                            <div class="requirement">
                                <span><strong>Node.js:</strong> (opcional para desarrollo)</span>
                                <span><?= $requirements['Node.js'] ? 
                                    '<span class="badge success">✓ Instalado</span>' : 
                                    '<span class="badge warning">⚠ No instalado</span>' ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="requirement">
                                <span><strong>Extensiones PHP:</strong></span>
                                <span>
                                    <?php if (empty($missingExtensions)): ?>
                                        <span class="badge success">✓ Todas instaladas</span>
                                    <?php else: ?>
                                        <span class="badge error">✗ Faltan: <?= implode(', ', $missingExtensions) ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($warnings)): ?>
                            <div class="alert warning" style="margin-top: 15px;">
                                <strong>⚠ Advertencias:</strong><br>
                                <?= implode('<br>', $warnings) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Extensiones PHP Instaladas -->
            <div class="section">
                <div class="section-header">
                    📦 Extensiones PHP Instaladas (<?= count($extensions) ?>)
                </div>
                <div class="section-body">
                    <div style="column-count: 3; column-gap: 20px;">
                        <?php foreach ($extensions as $ext): ?>
                            <div style="padding: 5px 0;">✓ <?= htmlspecialchars($ext) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recomendaciones -->
            <div class="section">
                <div class="section-header">
                    💡 Recomendaciones
                </div>
                <div class="section-body">
                    <?php
                    $recommendations = [];
                    
                    if (!$composerInstalled) {
                        $recommendations[] = [
                            'type' => 'error',
                            'title' => 'Composer no instalado',
                            'message' => 'Composer es ESENCIAL para cualquier framework PHP moderno. Contacta a tu proveedor de hosting para instalarlo.'
                        ];
                    }
                    
                    if (!$nodeInstalled) {
                        $recommendations[] = [
                            'type' => 'warning',
                            'title' => 'Node.js no instalado',
                            'message' => 'Node.js es necesario para compilar assets (CSS, JS) en Laravel, Vue, React, etc. No es crítico pero muy recomendado.'
                        ];
                    }
                    
                    if (version_compare($phpVersion, '8.1', '<')) {
                        $recommendations[] = [
                            'type' => 'error',
                            'title' => 'Versión de PHP antigua',
                            'message' => 'Tu versión de PHP (' . $phpVersion . ') es antigua. Laravel 10+ requiere PHP 8.1+. Considera actualizar.'
                        ];
                    }
                    
                    if (ini_get('memory_limit') !== '-1' && (int)ini_get('memory_limit') < 256) {
                        $recommendations[] = [
                            'type' => 'warning',
                            'title' => 'Memoria limitada',
                            'message' => 'memory_limit es ' . ini_get('memory_limit') . '. Se recomienda al menos 256M para frameworks modernos.'
                        ];
                    }
                    
                    if (!in_array('zip', $extensions)) {
                        $recommendations[] = [
                            'type' => 'warning',
                            'title' => 'Extensión ZIP faltante',
                            'message' => 'La extensión ZIP es útil para Composer. Considera instalarla.'
                        ];
                    }
                    
                    if (empty($recommendations)) {
                        echo '<div class="alert success"><strong>✓ ¡Excelente!</strong> Tu servidor parece estar bien configurado para frameworks modernos.</div>';
                    } else {
                        foreach ($recommendations as $rec) {
                            echo '<div class="alert ' . $rec['type'] . '">';
                            echo '<strong>' . $rec['title'] . '</strong><br>';
                            echo $rec['message'];
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
            
            <!-- Información de Seguridad -->
            <div class="alert error">
                <strong>⚠️ IMPORTANTE - SEGURIDAD:</strong><br>
                Este archivo muestra información sensible del servidor. <strong>ELIMÍNALO después de usarlo</strong> o protégelo con contraseña fuerte.
            </div>
        </div>
    </div>
</body>
</html>
