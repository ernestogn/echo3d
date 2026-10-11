// mapa.js - Mapa Leaflet principal con marcadores de incidencias

// Color del pin segun gravedad (urgencia); el emoji del tipo va dentro
const GRAVEDAD_COLOR = {
  baja:    '#16a34a',
  media:   '#d97706',
  alta:    '#ea580c',
  critica: '#dc2626',
};

const GRAVEDAD_NOMBRE = {
  baja:    'Baja',
  media:   'Media',
  alta:    'Alta',
  critica: 'Crítica',
};

// Inicializar mapa centrado en la ciudad objetivo (zoom a la derecha para dejar lugar a los filtros)
const mapa = L.map('mapa', { zoomControl: false }).setView([-32.48262351713079, -58.24455570742029], 13);
L.control.zoom({ position: 'topright' }).addTo(mapa);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  maxZoom: 19,
}).addTo(mapa);

// Agrupamiento de marcadores (Leaflet.markercluster) SEPARADO POR BARRIO:
// cada barrio tiene su propio grupo de cluster, asi los puntos de un barrio
// nunca se fusionan con los de otro (la agrupacion queda limitada al barrio).
// El radio en px se ajusta segun zoom/densidad; a zoom 17+ se muestran sueltos.
// Si el plugin no cargo, se cae a marcadores sueltos.
const SOPORTA_CLUSTER = typeof L.markerClusterGroup === 'function';

const OPCIONES_CLUSTER = {
  maxClusterRadius: (zoom) => {
    if (zoom <= 12) return 90;
    if (zoom <= 14) return 65;
    if (zoom <= 16) return 48;
    return 35;
  },
  disableClusteringAtZoom: 17,
  spiderfyOnMaxZoom: true,
  showCoverageOnHover: false,
  removeOutsideVisibleBounds: true,
  chunkedLoading: true,
  iconCreateFunction: (cluster) => {
    const n   = cluster.getChildCount();
    const tam = n < 10 ? 40 : n < 100 ? 50 : 60;
    return L.divIcon({
      html: `<div class="cluster-incidencias" style="width:${tam}px;height:${tam}px;">${n}</div>`,
      className: '',
      iconSize: [tam, tam],
    });
  },
};

// Un grupo de cluster por barrio (clave = barrio_id, o 'sin' si no tiene)
const gruposPorBarrio = {};
function grupoDeBarrio(clave) {
  if (!SOPORTA_CLUSTER) return null;
  if (!gruposPorBarrio[clave]) {
    const grupo = L.markerClusterGroup(OPCIONES_CLUSTER);
    grupo.addTo(mapa);
    gruposPorBarrio[clave] = grupo;
  }
  return gruposPorBarrio[clave];
}

// Icono del marcador: color = gravedad, emoji = tipo, resueltas grises con ✓,
// en gestion con anillo azul
function crearIcono(inc) {
  const resuelto   = inc.estado === 'resuelto';
  const enGestion  = inc.estado === 'en_proceso';
  const color      = resuelto ? '#94a3b8' : (GRAVEDAD_COLOR[inc.gravedad] || '#94a3b8');
  const emoji      = resuelto ? '✓' : (inc.tipo_icono || '📍');
  const opacidad   = resuelto ? 0.55 : 1;
  const anillo     = enGestion
    ? '<circle cx="17" cy="17" r="13.25" fill="none" stroke="#2563eb" stroke-width="2.5"/>'
    : '';
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="34" height="44" viewBox="0 0 34 44">
    <path d="M17 0C7.611 0 0 7.611 0 17c0 11.5 17 27 17 27s17-15.5 17-27C34 7.611 26.389 0 17 0z"
          fill="${color}" fill-opacity="${opacidad}" stroke="rgba(0,0,0,.25)" stroke-width="1.5"/>
    ${anillo}
    <circle cx="17" cy="17" r="11.5" fill="white" opacity=".92"/>
    <text x="17" y="21.5" text-anchor="middle" font-size="12.5">${emoji}</text>
  </svg>`;
  return L.divIcon({
    html: svg,
    className: '',
    iconSize: [34, 44],
    iconAnchor: [17, 44],
    popupAnchor: [0, -44],
  });
}

// Cargar y mostrar incidencias en el mapa
let todosLosMarcadores = [];
let filtroTipo = null;
let filtroMios = false;

// Agrega/quita un marcador respetando el agrupamiento de su barrio (o suelto si no hay plugin)
function mostrarMarcador(reg) {
  if (reg.grupo) reg.grupo.addLayer(reg.marker);
  else if (!mapa.hasLayer(reg.marker)) reg.marker.addTo(mapa);
}
function ocultarMarcador(reg) {
  if (reg.grupo) reg.grupo.removeLayer(reg.marker);
  else if (mapa.hasLayer(reg.marker)) mapa.removeLayer(reg.marker);
}

function aplicarFiltros() {
  todosLosMarcadores.forEach((reg) => {
    const okTipo = filtroTipo === null || reg.tipoId === filtroTipo;
    const okMios = !filtroMios || reg.usuarioId === SRCI_USUARIO_ID;
    if (okTipo && okMios) {
      mostrarMarcador(reg);
    } else {
      ocultarMarcador(reg);
    }
  });
}

async function cargarIncidencias() {
  try {
    const resp = await fetch('/srci/api/reportes.php');
    if (!resp.ok) throw new Error('Error al cargar incidencias.');
    const incidencias = await resp.json();

    incidencias.forEach((inc) => {
      const marcador = L.marker([inc.latitud, inc.longitud], { icon: crearIcono(inc) });
      // Las resueltas quedan por debajo de las activas en el orden de apilado
      if (inc.estado === 'resuelto') marcador.setZIndexOffset(-600);
      const foto     = inc.foto
        ? `<img src="/srci/uploads/${inc.foto}" style="width:100%;border-radius:6px;margin-top:6px;max-height:120px;object-fit:cover;" alt="Foto de la incidencia">`
        : '';

      const badgeGravedad = inc.gravedad
        ? `<span class="gravedad-badge gravedad-${inc.gravedad}">${GRAVEDAD_NOMBRE[inc.gravedad] || inc.gravedad}</span>`
        : '';

      marcador.bindPopup(`
        <div class="popup-titulo">${inc.tipo_icono || ''} ${inc.tipo_nombre}</div>
        <div class="popup-meta">${inc.fecha_hora} · ${inc.usuario_nombre}</div>
        <div class="popup-meta">${badgeGravedad} <span class="estado-badge estado-${inc.estado}">${inc.estado.replace('_',' ')}</span></div>
        ${inc.notas ? `<div style="margin-top:4px;font-size:.82rem;">${inc.notas}</div>` : ''}
        ${foto}
        <div class="popup-enlace" style="margin-top:6px;"><a href="/srci/detalle.php?id=${inc.id}">Ver detalle →</a></div>
      `);
      const claveBarrio = (inc.barrio_id !== null && inc.barrio_id !== undefined) ? String(inc.barrio_id) : 'sin';
      todosLosMarcadores.push({
        marker: marcador,
        tipoId: inc.tipo_id,
        usuarioId: inc.usuario_id,
        grupo: grupoDeBarrio(claveBarrio),
      });
    });
    aplicarFiltros();
  } catch (err) {
    console.error('Error cargando incidencias:', err);
  }
}

// Botonera de filtros sobre el mapa: Todos, tipos y Mis reportes
let chipTodos   = null;
let chipMios    = null;
const chipsTipo = [];

function actualizarChips() {
  chipsTipo.forEach((chip) => chip.classList.toggle('activo', String(filtroTipo) === chip.dataset.tipo));
  if (chipTodos) chipTodos.classList.toggle('activo', filtroTipo === null && !filtroMios);
  if (chipMios)  chipMios.classList.toggle('activo', filtroMios);
}

async function cargarFiltros() {
  const cont = document.getElementById('filtros-mapa');
  if (!cont) return;

  chipTodos = document.createElement('button');
  chipTodos.type = 'button';
  chipTodos.className = 'chip-filtro';
  chipTodos.textContent = 'Todos';
  chipTodos.addEventListener('click', () => {
    filtroTipo = null;
    filtroMios = false;
    actualizarChips();
    aplicarFiltros();
  });
  cont.appendChild(chipTodos);

  try {
    const resp  = await fetch('/srci/api/tipos.php');
    const tipos = await resp.json();
    tipos.forEach((t) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'chip-filtro';
      chip.dataset.tipo = String(t.id);
      chip.textContent = `${t.icono || ''} ${t.nombre}`;
      chip.addEventListener('click', () => {
        filtroTipo = filtroTipo === t.id ? null : t.id;
        actualizarChips();
        aplicarFiltros();
      });
      cont.appendChild(chip);
      chipsTipo.push(chip);
    });
  } catch (err) {
    console.error('Error cargando tipos para filtros:', err);
  }

  chipMios = document.createElement('button');
  chipMios.type = 'button';
  chipMios.className = 'chip-filtro chip-mios';
  chipMios.textContent = '👤 Mis reportes';
  chipMios.addEventListener('click', () => {
    filtroMios = !filtroMios;
    actualizarChips();
    aplicarFiltros();
  });
  cont.appendChild(chipMios);

  // Chip informativo: reportes sin coordenadas (importados de la planilla)
  fetch('/srci/api/sin_ubicacion.php')
    .then((r) => r.json())
    .then((d) => {
      if (d.sin_ubicacion > 0) {
        const chip = document.createElement('span');
        chip.className = 'chip-filtro';
        chip.style.cssText = 'cursor:default;opacity:.75;';
        chip.title = 'Reportes cargados desde la planilla sin ubicación. Se ubican desde el botón Editar de cada uno.';
        chip.textContent = '📍 ' + d.sin_ubicacion + ' sin ubicación';
        cont.appendChild(chip);
      }
    })
    .catch(() => {});

  // Rueda del mouse: scrollea los chips horizontalmente
  cont.addEventListener('wheel', (e) => {
    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
      e.preventDefault();
      cont.scrollLeft += e.deltaY;
    }
  }, { passive: false });

  actualizarChips();
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

// --- Boton "Mi ubicacion": geolocaliza el dispositivo (Geolocation API) ---
let marcadorUbicacion = null;
let circuloPrecision  = null;

function crearIconoUbicacion() {
  return L.divIcon({
    html: '<div class="puntero-ubicacion"></div>',
    className: '',
    iconSize: [22, 22],
    iconAnchor: [11, 11],
  });
}

// Aviso flotante discreto sobre el mapa (se oculta solo)
function avisoMapa(texto, segundos) {
  const cont = document.querySelector('.contenedor-mapa');
  if (!cont) return;
  let aviso = document.getElementById('aviso-mapa');
  if (!aviso) {
    aviso = document.createElement('div');
    aviso.id = 'aviso-mapa';
    aviso.className = 'aviso-mapa';
    cont.appendChild(aviso);
  }
  aviso.textContent = texto;
  aviso.classList.add('visible');
  clearTimeout(aviso._timer);
  aviso._timer = setTimeout(() => aviso.classList.remove('visible'), (segundos || 4) * 1000);
}

const ESTILO_PRECISION = { color: '#2563eb', weight: 1, opacity: 0.5, fillColor: '#2563eb', fillOpacity: 0.08 };

function ubicarDispositivo(boton) {
  if (!navigator.geolocation) {
    avisoMapa('Tu dispositivo no permite obtener la ubicación.');
    return;
  }
  if (boton) boton.classList.add('buscando');
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      if (boton) boton.classList.remove('buscando');
      const ll        = L.latLng(pos.coords.latitude, pos.coords.longitude);
      const exactitud = pos.coords.accuracy || 0;

      if (!marcadorUbicacion) {
        marcadorUbicacion = L.marker(ll, { icon: crearIconoUbicacion(), interactive: false, zIndexOffset: 1000 }).addTo(mapa);
      } else {
        marcadorUbicacion.setLatLng(ll);
      }
      if (!circuloPrecision) {
        circuloPrecision = L.circle(ll, Object.assign({ radius: exactitud }, ESTILO_PRECISION)).addTo(mapa);
      } else {
        circuloPrecision.setLatLng(ll);
        circuloPrecision.setRadius(exactitud);
      }
      mapa.setView(ll, Math.max(mapa.getZoom(), 16));
    },
    (err) => {
      if (boton) boton.classList.remove('buscando');
      const mensajes = {
        1: 'No diste permiso para usar tu ubicación.',
        2: 'No se pudo obtener tu ubicación.',
        3: 'Se agotó el tiempo para obtener tu ubicación.',
      };
      avisoMapa(mensajes[err.code] || 'No se pudo obtener tu ubicación.');
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
  );
}

// Control propio arriba a la derecha (debajo del zoom)
const ControlUbicacion = L.Control.extend({
  onAdd() {
    const cont = L.DomUtil.create('div', 'leaflet-bar control-ubicacion');
    L.DomEvent.disableClickPropagation(cont);
    L.DomEvent.disableScrollPropagation(cont);
    const btn  = L.DomUtil.create('a', '', cont);
    btn.href = '#';
    btn.title = 'Mi ubicación';
    btn.setAttribute('role', 'button');
    btn.setAttribute('aria-label', 'Mi ubicación');
    btn.innerHTML = '🎯';
    L.DomEvent.on(btn, 'click', (e) => {
      L.DomEvent.stop(e);
      ubicarDispositivo(btn);
    });
    return cont;
  },
});
new ControlUbicacion({ position: 'topright' }).addTo(mapa);

cargarIncidencias();
cargarFiltros();

// Limites de barrios: ocultos por defecto, se resaltan solo al pasar el mouse
// (borde + relleno tenue + nombre). El click sobre el barrio burbujea al mapa
// (mismo flujo de reporte) y reporte.js pre-selecciona el barrio segun el punto.
fetch('/srci/api/poligonos.php')
  .then((r) => r.json())
  .then((poligonos) => {
    const base  = { color: '#4f46e5', weight: 0, opacity: 0, fillColor: '#4f46e5', fillOpacity: 0.01 };
    const hover = { weight: 2, opacity: 0.6, fillOpacity: 0.08 };
    poligonos.forEach((p) => {
      p.anillos.forEach((anillo) => {
        const poly = L.polygon(anillo, base).addTo(mapa);
        const el = poly.getElement && poly.getElement();
        if (el) { el.style.pointerEvents = 'fill'; el.style.outline = 'none'; }
        poly.bindTooltip('🏙️ ' + p.nombre, { direction: 'top', offset: [0, -4] });
        poly.on('mouseover', () => poly.setStyle(hover));
        poly.on('mouseout', () => poly.setStyle(base));
      });
    });
  })
  .catch(() => {});

// Exponer mapa globalmente para que reporte.js lo use
window.mapaLeaflet = mapa;
window.removerMarcadorSeleccion = removerMarcadorSeleccion;
