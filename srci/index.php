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
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261010e">
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
      <a href="/srci/ayuda.php"       class="nav-enlace">Ayuda</a>
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
<div class="contenedor-mapa">
  <div id="filtros-mapa" role="toolbar" aria-label="Filtros del mapa"></div>
  <div id="mapa" role="main" aria-label="Mapa de incidencias"></div>
</div>
<script>const SRCI_USUARIO_ID = <?= (int)($_SESSION['usuario_id'] ?? 0) ?>;</script>

<!-- Boton flotante: reportar -->
<button class="fab-reportar" id="btn-abrir-reporte" type="button" aria-haspopup="dialog">
  <span aria-hidden="true">＋</span>
  Reportar incidencia
</button>

<!-- Modal de reporte -->
<div class="modal-fondo" id="modal-reporte" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
  <div class="modal-caja">
    <div class="modal-encabezado" title="Arrastrá para mover">
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

    <!-- Paso 2: ubicacion y detalles (alineado con la planilla de relevamiento) -->
    <div id="paso-detalles" style="display:none;">
      <button type="button" class="boton boton-secundario boton-sm" id="btn-volver" style="margin-bottom:var(--espacio-md);">
        ← Volver
      </button>

      <div id="info-tipo-seleccionado" style="margin-bottom:var(--espacio-md);padding:var(--espacio-xs) var(--espacio-sm);background:rgba(79,142,247,.08);border-radius:var(--radio-md);font-weight:600;font-size:.85rem;"></div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="barrio-reporte">Barrio *</label>
        <input type="text" id="barrio-reporte" list="lista-barrios" placeholder="Escribí para buscar..." autocomplete="off" required>
        <datalist id="lista-barrios"></datalist>
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="direccion-reporte">Dirección / calle y altura *</label>
        <input type="text" id="direccion-reporte" maxlength="255" placeholder="Ej: Urquiza 1234">
      </div>

      <div class="campo campo-ancho" style="margin-bottom:var(--espacio-md);">
        <label>Ubicación en el mapa</label>
        <div id="mini-mapa" style="height:140px;border-radius:var(--radio-md);overflow:hidden;border:1.5px solid var(--color-borde);"></div>
        <p id="coord-texto" style="font-size:.8rem;color:var(--color-texto-suave);margin-top:4px;">
          Tocá el mapa para marcar el lugar exacto
        </p>
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="gravedad-reporte">Gravedad *</label>
        <select id="gravedad-reporte" required>
          <option value="">Elegir...</option>
          <option value="baja">Baja</option>
          <option value="media">Media</option>
          <option value="alta">Alta</option>
          <option value="critica">Crítica</option>
        </select>
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="familias-reporte">Familias afectadas</label>
        <input type="number" id="familias-reporte" min="0" max="9999" placeholder="0">
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="servicio-reporte">Servicio afectado</label>
        <select id="servicio-reporte">
          <option value="">Sin dato</option>
          <option value="Ninguno">Ninguno</option>
          <option value="Luz">Luz</option>
          <option value="Agua">Agua</option>
          <option value="Luz y agua">Luz y agua</option>
          <option value="Vialidad">Vialidad</option>
          <option value="Otro">Otro</option>
        </select>
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="responsable-reporte">Responsable / área</label>
        <select id="responsable-reporte">
          <option value="">Sin dato</option>
          <option value="Cooperativa eléctrica">Cooperativa eléctrica</option>
          <option value="Defensa Civil">Defensa Civil</option>
          <option value="Municipio">Municipio</option>
          <option value="Provincia">Provincia</option>
          <option value="Otro">Otro</option>
        </select>
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label>Calle intransitable</label>
        <label class="check-linea">
          <input type="checkbox" id="calle-intransitable">
          Sí, la calle es intransitable
        </label>
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="contacto-reporte">Contacto vecino / referente</label>
        <input type="text" id="contacto-reporte" maxlength="120" placeholder="Nombre o teléfono">
      </div>

      <div class="campo campo-ancho" style="margin-bottom:var(--espacio-md);">
        <label for="notas-reporte">Descripción (opcional)</label>
        <textarea id="notas-reporte" placeholder="Contá algo más sobre el problema..." maxlength="1000"></textarea>
      </div>

      <div class="campo campo-ancho" style="margin-bottom:var(--espacio-md);">
        <label>Foto (opcional)</label>
        <div class="zona-foto" id="zona-foto">
          <input type="file" id="foto-input" accept="image/jpeg,image/png,image/webp" aria-label="Seleccionar foto">
          <div class="zona-foto-texto">
            <span style="font-size:1.1rem;">📷</span>
            Tocá para agregar una foto
          </div>
          <img id="vista-previa-foto" class="vista-previa-foto" alt="Vista previa de la foto seleccionada">
        </div>
      </div>

      <button class="boton boton-primario boton-bloque" id="btn-enviar-reporte" style="margin-top:var(--espacio-sm);">
        Enviar reporte
      </button>

      <div id="mensaje-reporte" style="margin-top:var(--espacio-md);display:none;"></div>
    </div>

  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/srci/assets/js/mapa.js?v=20261011d"></script>
<script src="/srci/assets/js/reporte.js?v=20261011d"></script>
<script>
  // Registrar Service Worker para soporte PWA/offline
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/srci/sw.js').catch(() => {});
  }
</script>
</body>
</html>
