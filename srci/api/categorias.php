<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

// Categorias de incidencia activas (etiquetas independientes del tipo)
// GET /srci/api/categorias.php — [{id, clave, nombre, icono, color}, ...]
requiere_sesion();

respuesta_json(categorias_activas());
