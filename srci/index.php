<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funciones.php';

requiere_sesion();
$nombre_usuario = esc((string)($_SESSION['nombre'] ?? ''));
$es_admin       = es_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mapa de Incidencias — SRCI</title>
  <meta name="description" content="Reporta incidencias urbanas en el mapa.">
  <link rel="stylesheet" href="/srci/assets/css/estilos.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="manifest" href="/srci/manifest.json">
</head>
<body>

<!-- Navegacion -->
<nav class="nav-principal" role="navigation">
  <div class="nav-contenido">
    <a href="/srci/index.php" class="nav-logo">
      <span class="nav-logo-icono" aria-hidden="true">🗺️</span>
      SRCI
    </a>
    <div class="nav-enlaces">
      <a href="/srci/index.php"       class="nav-enlace activo">Mapa</a>
      <a href="/srci/incidencias.php" class="nav-enlace">Listado</a>
      <?php if ($es_admin): ?>
        <a href="/srci/admin/reportes.php" class="nav-enlace">Admin</a>
      <?php endif; ?>
    </div>
    <div class="nav-usuario">
      <span>👤 <?= $nombre_usuario ?></span>
      <a href="/srci/logout.php" class="boton boton-secundario boton-sm">Salir</a>
    </div>
  </div>
</nav>

<!-- Mapa -->
<div id="mapa" role="main" aria-label="Mapa de incidencias"></div>

<!-- Boton flotante: reportar -->
<button class="fab-reportar" id="btn-abrir-reporte" type="button" aria-haspopup="dialog">
  <span aria-hidden="true">＋</span>
  Reportar incidencia
</button>

<!-- Modal de reporte -->
<div class="modal-fondo" id="modal-reporte" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
  <div class="modal-caja">
    <div class="modal-encabezado">
      <h2 id="modal-titulo">Nueva incidencia</h2>
      <button class="modal-cerrar" id="btn-cerrar-modal" aria-label="Cerrar">×</button>
    </div>

    <!-- Paso 1: elegir tipo -->
    <div id="paso-tipo">
      <p style="color:var(--color-texto-suave);margin-bottom:var(--espacio-md);font-size:.9rem;">
        ¿Qué problema encontraste?
      </p>
      <div class="grilla-tipos" id="grilla-tipos" role="radiogroup" aria-label="Tipo de incidencia">
        <!-- Se llena con JS -->
      </div>
      <button class="boton boton-primario boton-bloque" id="btn-siguiente-tipo" disabled>
        Siguiente →
      </button>
    </div>

    <!-- Paso 2: ubicacion y detalles -->
    <div id="paso-detalles" style="display:none;">
      <button type="button" class="boton boton-secundario boton-sm" id="btn-volver" style="margin-bottom:var(--espacio-lg);">
        ← Volver
      </button>

      <div id="info-tipo-seleccionado" style="margin-bottom:var(--espacio-lg);padding:var(--espacio-md);background:rgba(79,142,247,.1);border-radius:var(--radio-md);font-weight:600;"></div>

      <div class="campo" style="margin-bottom:var(--espacio-lg);">
        <label>Ubicación del problema</label>
        <div id="mini-mapa" style="height:200px;border-radius:var(--radio-md);overflow:hidden;border:1.5px solid var(--color-borde);"></div>
        <p id="coord-texto" style="font-size:.8rem;color:var(--color-texto-suave);margin-top:4px;">
          Tocá el mapa para marcar el lugar exacto
        </p>
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-lg);">
        <label for="notas-reporte">Descripción opcional</label>
        <textarea id="notas-reporte" placeholder="Contá algo más sobre el problema..." maxlength="1000"></textarea>
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-lg);">
        <label>Foto (opcional)</label>
        <div class="zona-foto" id="zona-foto">
          <input type="file" id="foto-input" accept="image/jpeg,image/png,image/webp" aria-label="Seleccionar foto">
          <div class="zona-foto-texto">
            <span style="font-size:2rem;">📷</span><br>
            Tocá para agregar una foto
          </div>
          <img id="vista-previa-foto" class="vista-previa-foto" alt="Vista previa de la foto seleccionada">
        </div>
      </div>

      <button class="boton boton-primario boton-bloque" id="btn-enviar-reporte">
        Enviar reporte
      </button>

      <div id="mensaje-reporte" style="margin-top:var(--espacio-md);display:none;"></div>
    </div>

  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/srci/assets/js/mapa.js"></script>
<script src="/srci/assets/js/reporte.js"></script>
<script>
  // Registrar Service Worker para soporte PWA/offline
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/srci/sw.js').catch(() => {});
  }
</script>
</body>
</html>
