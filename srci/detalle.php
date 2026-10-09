<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funciones.php';

requiere_sesion();

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) { header('Location: /srci/incidencias.php'); exit; }

$stmt = db()->prepare(
  'SELECT i.*, t.nombre AS tipo_nombre, t.icono AS tipo_icono, t.clave AS tipo_clave, u.nombre AS usuario_nombre, b.nombre AS barrio_nombre
   FROM incidencias i
   JOIN tipos_incidencia t ON t.id = i.tipo_id
   JOIN usuarios u         ON u.id = i.usuario_id
   LEFT JOIN barrios b     ON b.id = i.barrio_id
   WHERE i.id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$inc = $stmt->fetch();
if (!$inc) { http_response_code(404); die('Incidencia no encontrada.'); }

// Dias abiertos: desde el reporte hasta la resolucion (o hoy)
$inicio = new DateTime($inc['fecha_hora']);
$fin    = $inc['fecha_resolucion'] ? new DateTime($inc['fecha_resolucion']) : new DateTime('now');
$dias_abiertos = (int)$inicio->diff($fin)->days;

$fotos = db()->prepare('SELECT ruta_archivo FROM fotos WHERE incidencia_id = :id ORDER BY id');
$fotos->execute([':id' => $id]);
$fotos = $fotos->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Incidencia #<?= $id ?> — SRCI</title>
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261010">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>

<nav class="nav-principal">
  <div class="nav-contenido">
    <a href="/srci/index.php" class="nav-logo"><span class="nav-logo-icono">🗺️</span>SRCI</a>
    <div class="nav-enlaces">
      <a href="/srci/index.php"       class="nav-enlace">Mapa</a>
      <a href="/srci/incidencias.php" class="nav-enlace">Listado</a>
    </div>
    <div class="nav-usuario">
      <a href="/srci/logout.php" class="boton boton-secundario boton-sm">Salir</a>
    </div>
  </div>
</nav>

<main class="pagina-contenedor" style="max-width:800px;">
  <div style="margin-bottom:var(--espacio-lg);display:flex;justify-content:space-between;flex-wrap:wrap;gap:var(--espacio-md);">
    <a href="/srci/incidencias.php" style="color:var(--color-texto-suave);font-size:.9rem;">← Volver al listado</a>
    <a href="/srci/editar.php?id=<?= $id ?>" style="color:var(--color-texto-suave);font-size:.9rem;">Editar incidencia →</a>
  </div>

  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--espacio-md);margin-bottom:var(--espacio-lg);">
      <h1 style="font-size:1.375rem;">
        <?= esc($inc['tipo_icono'] ?? '') ?> <?= esc($inc['tipo_nombre']) ?>
      </h1>
      <span class="gravedad-badge <?= clase_gravedad($inc['gravedad']) ?>"><?= esc(nombre_gravedad($inc['gravedad'])) ?></span>
      <span class="estado-badge <?= clase_estado($inc['estado']) ?>"><?= esc(nombre_estado($inc['estado'])) ?></span>
    </div>

    <dl style="display:grid;grid-template-columns:140px 1fr;gap:var(--espacio-sm) var(--espacio-lg);font-size:.9rem;">
      <dt style="color:var(--color-texto-suave);font-weight:600;">ID</dt>
      <dd>#<?= (int)$inc['id'] ?></dd>
      <dt style="color:var(--color-texto-suave);font-weight:600;">Fecha</dt>
      <dd><?= esc(fecha_legible($inc['fecha_hora'])) ?></dd>
      <dt style="color:var(--color-texto-suave);font-weight:600;">Reportado por</dt>
      <dd><?= esc($inc['usuario_nombre']) ?></dd>
      <?php if (!empty($inc['barrio_nombre'])): ?>
        <dt style="color:var(--color-texto-suave);font-weight:600;">Barrio</dt>
        <dd><?= esc($inc['barrio_nombre']) ?></dd>
      <?php endif; ?>
      <?php if (!empty($inc['direccion'])): ?>
        <dt style="color:var(--color-texto-suave);font-weight:600;">Dirección</dt>
        <dd><?= esc($inc['direccion']) ?></dd>
      <?php endif; ?>
      <dt style="color:var(--color-texto-suave);font-weight:600;">Gravedad</dt>
      <dd><span class="gravedad-badge <?= clase_gravedad($inc['gravedad']) ?>"><?= esc(nombre_gravedad($inc['gravedad'])) ?></span></dd>
      <?php if ($inc['familias_afectadas'] !== null && $inc['familias_afectadas'] !== ''): ?>
        <dt style="color:var(--color-texto-suave);font-weight:600;">Familias afectadas</dt>
        <dd><?= (int)$inc['familias_afectadas'] ?></dd>
      <?php endif; ?>
      <?php if (!empty($inc['servicio_afectado'])): ?>
        <dt style="color:var(--color-texto-suave);font-weight:600;">Servicio afectado</dt>
        <dd><?= esc($inc['servicio_afectado']) ?></dd>
      <?php endif; ?>
      <?php if ($inc['calle_intransitable'] !== null && $inc['calle_intransitable'] !== ''): ?>
        <dt style="color:var(--color-texto-suave);font-weight:600;">Calle intransitable</dt>
        <dd><?= $inc['calle_intransitable'] ? 'Sí' : 'No' ?></dd>
      <?php endif; ?>
      <?php if (!empty($inc['responsable_area'])): ?>
        <dt style="color:var(--color-texto-suave);font-weight:600;">Responsable / área</dt>
        <dd><?= esc($inc['responsable_area']) ?></dd>
      <?php endif; ?>
      <?php if (!empty($inc['contacto_vecino'])): ?>
        <dt style="color:var(--color-texto-suave);font-weight:600;">Contacto vecino</dt>
        <dd><?= esc($inc['contacto_vecino']) ?></dd>
      <?php endif; ?>
      <dt style="color:var(--color-texto-suave);font-weight:600;">Coordenadas</dt>
      <dd><?= esc($inc['latitud']) ?>, <?= esc($inc['longitud']) ?></dd>
      <?php if ($inc['fecha_resolucion']): ?>
        <dt style="color:var(--color-texto-suave);font-weight:600;">Fecha de resolución</dt>
        <dd><?= esc(fecha_legible($inc['fecha_resolucion'])) ?></dd>
      <?php endif; ?>
      <dt style="color:var(--color-texto-suave);font-weight:600;">Días abiertos</dt>
      <dd><?= $dias_abiertos ?></dd>
      <?php if ($inc['notas']): ?>
        <dt style="color:var(--color-texto-suave);font-weight:600;">Descripción</dt>
        <dd><?= esc($inc['notas']) ?></dd>
      <?php endif; ?>
    </dl>

    <?php if (es_admin()): ?>
      <div style="margin-top:var(--espacio-xl);padding-top:var(--espacio-lg);border-top:1px solid var(--color-borde);">
        <label style="font-size:.875rem;font-weight:600;color:var(--color-texto-suave);text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:var(--espacio-sm);">Cambiar estado</label>
        <div style="display:flex;gap:var(--espacio-sm);flex-wrap:wrap;">
          <?php foreach (['pendiente','en_proceso','resuelto'] as $est): ?>
            <button type="button"
                    class="boton <?= $inc['estado'] === $est ? 'boton-primario' : 'boton-secundario' ?>"
                    onclick="cambiarEstado(<?= $id ?>, '<?= $est ?>')"
                    id="btn-estado-<?= $est ?>">
              <?= esc(nombre_estado($est)) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <p id="msg-estado" style="margin-top:var(--espacio-sm);font-size:.875rem;display:none;"></p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Mapa del detalle -->
  <div class="tarjeta tarjeta-sm" style="margin-bottom:var(--espacio-lg);">
    <h2 style="margin-bottom:var(--espacio-md);">Ubicación</h2>
    <div id="mapa-detalle" style="height:300px;border-radius:var(--radio-md);overflow:hidden;"></div>
  </div>

  <!-- Fotos -->
  <?php if (!empty($fotos)): ?>
    <div class="tarjeta tarjeta-sm">
      <h2 style="margin-bottom:var(--espacio-md);">Fotos</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:var(--espacio-md);">
        <?php foreach ($fotos as $foto): ?>
          <a href="/srci/uploads/<?= esc($foto['ruta_archivo']) ?>" target="_blank" rel="noopener">
            <img src="/srci/uploads/<?= esc($foto['ruta_archivo']) ?>"
                 alt="Foto de la incidencia"
                 style="width:100%;height:160px;object-fit:cover;border-radius:var(--radio-md);border:1px solid var(--color-borde);transition:opacity .2s;"
                 onmouseover="this.style.opacity='.8'"
                 onmouseout="this.style.opacity='1'">
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  const lat = <?= (float)$inc['latitud'] ?>;
  const lng = <?= (float)$inc['longitud'] ?>;
  const mapaDetalle = L.map('mapa-detalle').setView([lat, lng], 16);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
  }).addTo(mapaDetalle);
  L.marker([lat, lng]).addTo(mapaDetalle)
   .bindPopup('<?= esc(addslashes($inc['tipo_nombre'])) ?>').openPopup();

  <?php if (es_admin()): ?>
  async function cambiarEstado(id, estado) {
    try {
      const resp = await fetch('/srci/api/estado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ incidencia_id: id, estado }),
      });
      const datos = await resp.json();
      const msg = document.getElementById('msg-estado');
      if (datos.ok) {
        msg.textContent = 'Estado actualizado.';
        msg.style.color = 'var(--color-verde)';
        msg.style.display = 'block';
        document.querySelectorAll('[id^="btn-estado-"]').forEach(b => b.classList.replace('boton-primario','boton-secundario'));
        document.getElementById('btn-estado-' + estado).classList.replace('boton-secundario','boton-primario');
      } else {
        msg.textContent = datos.error || 'Error al cambiar estado.';
        msg.style.color = 'var(--color-rojo)';
        msg.style.display = 'block';
      }
    } catch(e) {
      alert('No se pudo actualizar el estado. Intenta de nuevo.');
    }
  }
  <?php endif; ?>
</script>
</body>
</html>
