<?php
require_once __DIR__ . '/includes/db.php';
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
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261015">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
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
      <a href="/srci/perfil.php" class="nav-perfil">👤 <?= $nombre_usuario ?></a>
      <a href="/srci/logout.php" class="boton boton-secundario boton-sm">Salir</a>
    </div>
  </div>
</nav>

<!-- Mapa -->
<div class="contenedor-mapa">
  <div id="filtros-mapa" role="toolbar" aria-label="Filtros del mapa"></div>
  <div id="mapa" role="main" aria-label="Mapa de incidencias"></div>
</div>
<script>
  const SRCI_USUARIO_ID = <?= (int)($_SESSION['usuario_id'] ?? 0) ?>;
  window.SRCI_ES_ADMIN = <?= $es_admin ? 'true' : 'false' ?>;
  window.SRCI_MOSTRAR_CATEGORIAS = <?= ($es_admin || hay_categorias_visibles()) ? 'true' : 'false' ?>;
</script>

<!-- Modal de reporte: pantalla completa con geometria INLINE (universal, a prueba de caches) -->
<!-- El reporte inicia SIEMPRE tocando el mapa principal (no hay boton flotante) -->
<div id="modal-reporte" role="dialog" aria-modal="true" aria-labelledby="modal-titulo"
     style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:1000;background:rgba(15,23,42,.45);opacity:0;pointer-events:none;transition:opacity .2s;">
  <div id="panel-reporte"
       style="position:fixed;top:0;left:0;width:100%;height:100%;background:#ffffff;margin:0;padding:16px;overflow-y:auto;overflow-x:hidden;transform:translateY(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);box-sizing:border-box;">
    <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e2e8f0;margin:-16px -16px 16px;padding:8px 16px;position:sticky;top:0;background:#ffffff;z-index:5;">
      <h2 id="modal-titulo" style="font-size:1.15rem;font-weight:600;color:#334155;">Nueva incidencia <span id="build-tag" style="font-size:.62rem;font-weight:400;color:#94a3b8;vertical-align:super;" title="Build actual">v20261026</span></h2>
      <button id="btn-cerrar-modal" aria-label="Cerrar" style="background:none;border:none;font-size:1.5rem;line-height:1;color:#94a3b8;cursor:pointer;padding:4px;">×</button>
    </div>

    <!-- Paso 1: ubicacion (punto, barrio y direccion detectados) -->
    <div id="paso-ubicacion">
      <div style="font-size:.8rem;font-weight:600;color:var(--color-texto-suave);margin-bottom:var(--espacio-md);">Paso 1 de 3 · Ubicación</div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <p id="coord-texto" style="font-size:.95rem;color:var(--color-texto-suave);margin:0 0 var(--espacio-sm);">
          Tocá el mapa para marcar el lugar exacto
        </p>
        <button type="button" class="boton boton-secundario boton-bloque" id="btn-elegir-mapa">🗺️ Elegir / corregir la ubicación en el mapa</button>
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="barrio-reporte">Barrio *</label>
        <input type="text" id="barrio-reporte" list="lista-barrios" placeholder="Escribí para buscar..." autocomplete="off" required>
        <datalist id="lista-barrios"></datalist>
      </div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label for="direccion-reporte">Dirección / calle y altura *</label>
        <input type="text" id="direccion-reporte" maxlength="255" placeholder="Ej: Urquiza 1234">
        <div id="sugerencia-direccion" style="display:none;margin-top:6px;"></div>
      </div>

      <button class="boton boton-primario boton-bloque" id="btn-siguiente-ubicacion" disabled>
        Siguiente →
      </button>
    </div>

    <!-- Paso 2: tipo de incidencia (avanza solo al tocar un icono) -->
    <div id="paso-tipo" style="display:none;">
      <div style="font-size:.8rem;font-weight:600;color:var(--color-texto-suave);margin-bottom:var(--espacio-md);">Paso 2 de 3 · Tipo de incidencia</div>

      <button type="button" class="boton boton-secundario boton-sm" id="btn-volver-tipo" style="margin-bottom:var(--espacio-md);">
        ← Volver
      </button>

      <p style="color:var(--color-texto-suave);margin-bottom:var(--espacio-md);font-size:1rem;">
        ¿Qué problema encontraste? (tocá un icono y seguimos)
      </p>
      <div class="grilla-tipos" id="grilla-tipos" role="radiogroup" aria-label="Tipo de incidencia">
        <!-- Se llena con JS -->
      </div>
    </div>

    <!-- Paso 3: gravedad y detalles (alineado con la planilla de relevamiento) -->
    <div id="paso-detalles" style="display:none;">
      <div style="font-size:.8rem;font-weight:600;color:var(--color-texto-suave);margin-bottom:var(--espacio-md);">Paso 3 de 3 · Gravedad y datos</div>

      <div id="mensaje-reporte" style="margin-bottom:var(--espacio-md);display:none;"></div>

      <button type="button" class="boton boton-secundario boton-sm" id="btn-volver" style="margin-bottom:var(--espacio-md);">
        ← Volver
      </button>

      <div id="info-tipo-seleccionado" style="margin-bottom:var(--espacio-md);padding:var(--espacio-xs) var(--espacio-sm);background:rgba(79,142,247,.08);border-radius:var(--radio-md);font-weight:600;font-size:.85rem;"></div>

      <div class="campo" style="margin-bottom:var(--espacio-md);">
        <label>Gravedad *</label>
        <input type="hidden" id="gravedad-reporte" value="">
        <div class="botonera-gravedad" id="botonera-gravedad" role="radiogroup" aria-label="Gravedad de la incidencia">
          <button type="button" class="btn-gravedad g-baja" data-valor="baja"><span class="punto-gravedad" aria-hidden="true"></span><span>Baja</span></button>
          <button type="button" class="btn-gravedad g-media" data-valor="media"><span class="punto-gravedad" aria-hidden="true"></span><span>Media</span></button>
          <button type="button" class="btn-gravedad g-alta" data-valor="alta"><span class="punto-gravedad" aria-hidden="true"></span><span>Alta</span></button>
          <button type="button" class="btn-gravedad g-critica" data-valor="critica"><span class="punto-gravedad" aria-hidden="true"></span><span>Crítica</span></button>
        </div>
      </div>

      <?php if ($es_admin || hay_categorias_visibles()): ?>
      <div class="campo campo-ancho" style="margin-bottom:var(--espacio-md);">
        <label>Categorías <span style="font-weight:400;color:var(--color-texto-suave);">(podés elegir varias)</span></label>
        <div id="categorias-reporte" class="chips-categoria"></div>
      </div>
      <?php endif; ?>

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
    </div>

  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="/srci/assets/js/mapa.js?v=20261021"></script>
<script src="/srci/assets/js/reporte.js?v=20261026"></script>
</body>
</html>
