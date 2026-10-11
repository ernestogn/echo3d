<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

// Categorias de incidencia activas (etiquetas independientes del tipo)
// GET /srci/api/categorias.php            — [{id, clave, nombre, icono, color}, ...] (catalogo, para admins)
// GET /srci/api/categorias.php?visibles=1 — solo las marcadas para usuarios regulares
requiere_sesion();

if (($_GET['visibles'] ?? '') === '1') {
  respuesta_json(categorias_visibles());
}

respuesta_json(categorias_activas());
