// mapa.js - Mapa Leaflet principal con marcadores de incidencias

const ICONOS_COLOR = {
  pendiente:  '#f59e0b',
  en_proceso: '#4f8ef7',
  resuelto:   '#22c55e',
};

// Inicializar mapa centrado en la ciudad objetivo
const mapa = L.map('mapa', { zoomControl: true }).setView([-32.48262351713079, -58.24455570742029], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  maxZoom: 19,
}).addTo(mapa);

// Icono SVG personalizado segun estado
function crearIcono(estado) {
  const color = ICONOS_COLOR[estado] || '#94a3b8';
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="0 0 28 36">
    <path d="M14 0C6.268 0 0 6.268 0 14c0 9.817 14 22 14 22S28 23.817 28 14C28 6.268 21.732 0 14 0z"
          fill="${color}" stroke="rgba(0,0,0,.25)" stroke-width="1.5"/>
    <circle cx="14" cy="14" r="5" fill="white" opacity=".85"/>
  </svg>`;
  return L.divIcon({
    html: svg,
    className: '',
    iconSize: [28, 36],
    iconAnchor: [14, 36],
    popupAnchor: [0, -36],
  });
}

// Cargar y mostrar incidencias en el mapa
async function cargarIncidencias() {
  try {
    const resp = await fetch('/srci/api/reportes.php');
    if (!resp.ok) throw new Error('Error al cargar incidencias.');
    const incidencias = await resp.json();

    incidencias.forEach((inc) => {
      const marcador = L.marker([inc.latitud, inc.longitud], { icon: crearIcono(inc.estado) });
      const foto     = inc.foto
        ? `<img src="/srci/uploads/${inc.foto}" style="width:100%;border-radius:6px;margin-top:6px;max-height:120px;object-fit:cover;" alt="Foto de la incidencia">`
        : '';

      marcador.bindPopup(`
        <div class="popup-titulo">${inc.tipo_icono || ''} ${inc.tipo_nombre}</div>
        <div class="popup-meta">${inc.fecha_hora} · ${inc.usuario_nombre}</div>
        <div class="popup-meta"><span class="estado-badge estado-${inc.estado}">${inc.estado.replace('_',' ')}</span></div>
        ${inc.notas ? `<div style="margin-top:4px;font-size:.82rem;">${inc.notas}</div>` : ''}
        ${foto}
        <div class="popup-enlace" style="margin-top:6px;"><a href="/srci/detalle.php?id=${inc.id}">Ver detalle →</a></div>
      `);
      marcador.addTo(mapa);
    });
  } catch (err) {
    console.error('Error cargando incidencias:', err);
  }
}

// Icono del punto elegido con click en el mapa
function crearIconoSeleccion() {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44">
    <circle cx="22" cy="22" r="17" fill="none" stroke="#4f46e5" stroke-width="3" opacity=".85"/>
    <circle cx="22" cy="22" r="7"  fill="#4f46e5"/>
    <circle cx="22" cy="22" r="3"  fill="#ffffff"/>
  </svg>`;
  return L.divIcon({ html: svg, className: '', iconSize: [44, 44], iconAnchor: [22, 22] });
}

let marcadorSeleccion = null;

// Click sobre el mapa: abrir reporte con esa ubicacion precargada
mapa.on('click', (e) => {
  if (marcadorSeleccion) marcadorSeleccion.remove();
  marcadorSeleccion = L.marker(e.latlng, { icon: crearIconoSeleccion() }).addTo(mapa);
  if (typeof window.abrirReporteConCoordenada === 'function') {
    window.abrirReporteConCoordenada(e.latlng);
  }
});

function removerMarcadorSeleccion() {
  if (marcadorSeleccion) { marcadorSeleccion.remove(); marcadorSeleccion = null; }
}

cargarIncidencias();

// Exponer mapa globalmente para que reporte.js lo use
window.mapaLeaflet = mapa;
window.removerMarcadorSeleccion = removerMarcadorSeleccion;
