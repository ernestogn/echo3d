<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = db();
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN telefono VARCHAR(20) NULL AFTER activo");
    echo "Columna 'telefono' agregada con éxito.";
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "La columna 'telefono' ya existe.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
