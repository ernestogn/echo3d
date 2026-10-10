<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Conteo de incidencias sin coordenadas (importadas de la planilla)
requiere_sesion();

$n = (int)db()->query('SELECT COUNT(*) FROM incidencias WHERE oculto = 0 AND (latitud IS NULL OR longitud IS NULL)')->fetchColumn();

respuesta_json(['sin_ubicacion' => $n]);
