<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = db();
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS telefono VARCHAR(20) NULL AFTER activo");
    echo "OK: Columna telefono procesada.";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
