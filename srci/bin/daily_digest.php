<?php
declare(strict_types=1);

// Script para envío de digest diario a administradores
// Ejecutar vía cron a las 08:00 AM: 0 8 * * * php /ruta/a/srci/bin/daily_digest.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/funciones_notificaciones_admin.php';

// Solo ejecutar desde CLI
if (php_sapi_name() !== 'cli') {
    die('Este script solo puede ejecutarse desde CLI');
}

echo "[" . date('Y-m-d H:i:s') . "] Iniciando envío de digest diario...\n";

// Garantia de NUNCA mandar un digest vacio (sin novedades):
// se cuenta primero lo que hay en la ventana y si es 0 se sale sin enviar
// ni un solo mail.
try {
    $total_24h = (int) db()->query(
        "SELECT COUNT(*) FROM incidencias
         WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
           AND aprobado = 1 AND oculto = 0"
    )->fetchColumn();
} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR al consultar incidencias: " . $e->getMessage() . "\n";
    exit(1);
}

if ($total_24h === 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Sin incidencias nuevas en 24h: NO se envía digest (evita mails vacíos)\n";
    exit(0);
}

$resultado = enviar_digest_diario_admin();

if ($resultado) {
    echo "[" . date('Y-m-d H:i:s') . "] Digest diario enviado correctamente ({$total_24h} incidencia/s)\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Había {$total_24h} incidencia/s pero no se envió: sin admins activos con el digest habilitado o sin email ruteable\n";
}

exit(0);
?>
