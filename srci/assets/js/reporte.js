// reporte.js - Logica del modal de reporte de nueva incidencia

let tipoSeleccionado = null;
let coordSeleccionada = null;
let miniMapa = null;
let miniMarcador = null;
let barriosCargados = [];
let poligonosBarrios = [];

// Cargar poligonos de barrios (para pre-seleccionar el barrio segun el punto)
fetch('/srci/api/poligonos.php')
  .then((r) => r.json())
  .then((poligonos) => { poligonosBarrios = Array.isArray(poligonos) ? poligonos : []; })
  .catch(() => { poligonosBarrios = []; });

// Ray casting: barrio que contiene el punto (misma logica que barrio_por_punto en PHP)
function barrioPorPunto(lat, lng) {
  for (const p of poligonosBarrios) {
    for (const anillo of p.anillos) {
      let dentro = false;
      for (let i = 0, j = anillo.length - 1; i < anillo.length; j = i++) {
        const xi = anillo[i][0], yi = anillo[i][1]; // x = lat, y = lng
        const xj = anillo[j][0], yj = anillo[j][1];
        if (((yi > lng) !== (yj > lng)) && (lat < (xj - xi) * (lng - yi) / (yj - yi) + xi)) {
          dentro = !dentro;
        }
      }
      if (dentro) return p;
    }
  }
  return null;
}

// Pre-selecciona el barrio segun el punto marcado (el usuario lo puede cambiar)
function preseleccionarBarrio(lat, lng) {
  const hit = barrioPorPunto(lat, lng);
  if (hit) barrioSelect.value = hit.nombre;
}

const modalFondo     = document.getElementById('modal-reporte');
const btnAbrir       = document.getElementById('btn-abrir-reporte');
const btnCerrar      = document.getElementById('btn-cerrar-modal');
const pasoTipo       = document.getElementById('paso-tipo');
const pasoDetalles   = document.getElementById('paso-detalles');
const grillaTipos    = document.getElementById('grilla-tipos');
const btnSiguiente   = document.getElementById('btn-siguiente-tipo');
const btnVolver      = document.getElementById('btn-volver');
const btnEnviar      = document.getElementById('btn-enviar-reporte');
const infoTipo       = document.getElementById('info-tipo-seleccionado');
const coordTexto     = document.getElementById('coord-texto');
const notasReporte   = document.getElementById('notas-reporte');
const fotoInput      = document.getElementById('foto-input');
const vistaPreviaMapa = document.getElementById('vista-previa-foto');
const mensajeDiv     = document.getElementById('mensaje-reporte');
const modalCaja      = modalFondo.querySelector('.modal-caja');
const encabezadoModal = modalFondo.querySelector('.modal-encabezado');
const barrioSelect   = document.getElementById('barrio-reporte');
const listaBarrios   = document.getElementById('lista-barrios');
const direccionInput = document.getElementById('direccion-reporte');
const gravedadSelect = document.getElementById('gravedad-reporte');
const familiasInput  = document.getElementById('familias-reporte');
const servicioSelect = document.getElementById('servicio-reporte');
const responsableSelect = document.getElementById('responsable-reporte');
const calleCheck     = document.getElementById('calle-intransitable');
const contactoInput  = document.getElementById('contacto-reporte');

// Cargar tipos de incidencia y construir la grilla
async function cargarTipos() {
  try {
    const resp  = await fetch('/srci/api/tipos.php');
    const tipos = await resp.json();

    tipos.forEach((tipo) => {
      const tarjeta = document.createElement('button');
      tarjeta.type  = 'button';
      tarjeta.classList.add('tipo-tarjeta');
      tarjeta.dataset.id     = tipo.id;
      tarjeta.dataset.nombre = tipo.nombre;
      tarjeta.dataset.icono  = tipo.icono || '';
      tarjeta.setAttribute('role', 'radio');
      tarjeta.setAttribute('aria-checked', 'false');
      tarjeta.innerHTML = `
        <span class="tipo-tarjeta-icono" aria-hidden="true">${tipo.icono || '📋'}</span>
        <span class="tipo-tarjeta-nombre">${tipo.nombre}</span>
      `;
      tarjeta.addEventListener('click', () => seleccionarTipo(tarjeta));
      grillaTipos.appendChild(tarjeta);
    });
  } catch (err) {
    console.error('Error cargando tipos:', err);
  }
}

// Cargar barrios y llenar el datalist (buscable)
async function cargarBarrios() {
  try {
    const resp    = await fetch('/srci/api/barrios.php');
    const barrios = await resp.json();
    barriosCargados = barrios;

    listaBarrios.innerHTML = '';
    barrios.forEach((b) => {
      const opcion = document.createElement('option');
      opcion.value = b.nombre;
      listaBarrios.appendChild(opcion);
    });
  } catch (err) {
    console.error('Error cargando barrios:', err);
  }
}

// Resuelve un barrio a partir del texto buscado (exacto o unica coincidencia)
function encontrarBarrio(texto) {
  if (texto === '') return null;
  const t = texto.toLowerCase();
  const exacto = barriosCargados.find((b) => b.nombre.toLowerCase() === t);
  if (exacto) return exacto;
  const coincidencias = barriosCargados.filter((b) => b.nombre.toLowerCase().includes(t));
  return coincidencias.length === 1 ? coincidencias[0] : null;
}

function seleccionarTipo(tarjeta) {
  grillaTipos.querySelectorAll('.tipo-tarjeta').forEach((t) => {
    t.classList.remove('seleccionado');
    t.setAttribute('aria-checked', 'false');
  });
  tarjeta.classList.add('seleccionado');
  tarjeta.setAttribute('aria-checked', 'true');
  tipoSeleccionado = {
    id:     tarjeta.dataset.id,
    nombre: tarjeta.dataset.nombre,
    icono:  tarjeta.dataset.icono,
  };
  btnSiguiente.disabled = false;
}

function abrirModal() {
  resetearModal();
  modalFondo.classList.add('visible');
  document.body.style.overflow = 'hidden';
}

// Abrir el modal con una ubicacion ya marcada (click en el mapa principal)
function abrirConCoordenada(latlng) {
  abrirModal();
  coordSeleccionada = latlng;
}
window.abrirReporteConCoordenada = abrirConCoordenada;

function cerrarModal() {
  modalFondo.classList.remove('visible');
  document.body.style.overflow = '';
  modalCaja.style.transform  = '';
  modalCaja.style.transition = '';
  if (typeof window.removerMarcadorSeleccion === 'function') {
    window.removerMarcadorSeleccion();
  }
}

function resetearModal() {
  tipoSeleccionado  = null;
  coordSeleccionada = null;
  pasoTipo.style.display     = '';
  pasoDetalles.style.display = 'none';
  btnSiguiente.disabled = true;
  grillaTipos.querySelectorAll('.tipo-tarjeta').forEach((t) => {
    t.classList.remove('seleccionado');
    t.setAttribute('aria-checked', 'false');
  });
  notasReporte.value = '';
  fotoInput.value    = '';
  vistaPreviaMapa.style.display = 'none';
  vistaPreviaMapa.src           = '';
  mensajeDiv.style.display      = 'none';
  coordTexto.textContent = 'Toca el mapa para marcar el lugar exacto';
  barrioSelect.value   = '';
  direccionInput.value = '';
  gravedadSelect.value = '';
  familiasInput.value  = '';
  servicioSelect.value = '';
  responsableSelect.value = '';
  calleCheck.checked   = false;
  contactoInput.value  = '';
  if (miniMarcador) { miniMarcador.remove(); miniMarcador = null; }
}

function mostrarPasoDetalles() {
  pasoTipo.style.display     = 'none';
  pasoDetalles.style.display = '';
  infoTipo.textContent       = (tipoSeleccionado.icono || '') + ' ' + tipoSeleccionado.nombre;

  // Inicializar mini-mapa si no existe aun
  if (!miniMapa) {
    const centro = window.mapaLeaflet ? window.mapaLeaflet.getCenter() : L.latLng(-32.48262351713079, -58.24455570742029);
    miniMapa = L.map('mini-mapa', { zoomControl: true }).setView(centro, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
      maxZoom: 19,
    }).addTo(miniMapa);

    miniMapa.on('click', (e) => {
      coordSeleccionada = e.latlng;
      coordTexto.textContent = `Lat: ${e.latlng.lat.toFixed(6)}, Lng: ${e.latlng.lng.toFixed(6)}`;
      if (miniMarcador) miniMarcador.remove();
      miniMarcador = L.marker(e.latlng).addTo(miniMapa);
      preseleccionarBarrio(e.latlng.lat, e.latlng.lng);
    });

    // Si el usuario hizo click en el mapa principal, precargar ese punto
    if (coordSeleccionada) {
      miniMapa.setView(coordSeleccionada, 16);
      miniMarcador = L.marker(coordSeleccionada).addTo(miniMapa);
      coordTexto.textContent = `Lat: ${coordSeleccionada.lat.toFixed(6)}, Lng: ${coordSeleccionada.lng.toFixed(6)}`;
    }
    if (coordSeleccionada) {
      preseleccionarBarrio(coordSeleccionada.lat, coordSeleccionada.lng);
    }
  } else {
    // Refrescar layout del mapa al mostrarlo
    setTimeout(() => miniMapa.invalidateSize(), 100);
  }
}

// Preview de foto
fotoInput.addEventListener('change', () => {
  const archivo = fotoInput.files[0];
  if (!archivo) return;
  const url = URL.createObjectURL(archivo);
  vistaPreviaMapa.src           = url;
  vistaPreviaMapa.style.display = 'block';
});

// Enviar reporte
btnEnviar.addEventListener('click', async () => {
  if (!coordSeleccionada) {
    mostrarMensaje('Toca el mapa para marcar la ubicación del problema.', 'error');
    return;
  }
  if (!barrioSelect.value) {
    mostrarMensaje('Elegí el barrio del problema.', 'error');
    return;
  }
  const barrioEncontrado = encontrarBarrio(barrioSelect.value.trim());
  if (!barrioEncontrado) {
    mostrarMensaje('Ese barrio no está en la lista. Elegilo del desplegable buscable.', 'error');
    return;
  }
  if (direccionInput.value.trim() === '') {
    mostrarMensaje('Escribí la dirección (calle y altura).', 'error');
    return;
  }
  if (!gravedadSelect.value) {
    mostrarMensaje('Elegí la gravedad de la situación.', 'error');
    return;
  }

  btnEnviar.disabled   = true;
  btnEnviar.innerHTML  = '<span class="spinner"></span> Enviando...';

  try {
    // 1. Crear incidencia
    const respInc = await fetch('/srci/api/reportes.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        tipo_id:             parseInt(tipoSeleccionado.id),
        latitud:             coordSeleccionada.lat,
        longitud:            coordSeleccionada.lng,
        barrio_id:           parseInt(barrioEncontrado.id),
        direccion:           direccionInput.value.trim(),
        gravedad:            gravedadSelect.value,
        familias_afectadas:  familiasInput.value === '' ? '' : familiasInput.value,
        servicio_afectado:   servicioSelect.value,
        calle_intransitable: calleCheck.checked ? 1 : 0,
        responsable_area:    responsableSelect.value,
        contacto_vecino:     contactoInput.value.trim(),
        notas:               notasReporte.value.trim(),
      }),
    });
    const textoInc = await respInc.text();
    let datosInc;
    try {
      datosInc = JSON.parse(textoInc);
    } catch (e) {
      // Respuesta no-JSON: sesion expirada (redirect) o error de servidor
      mostrarMensaje('Tu sesión expiró o hubo un error. Recargá la página e intentá de nuevo.', 'error');
      btnEnviar.disabled  = false;
      btnEnviar.innerHTML = 'Enviar reporte';
      return;
    }
    if (respInc.status === 401) {
      mostrarMensaje(datosInc.error || 'Tu sesión expiró. Redirigiendo al login...', 'error');
      setTimeout(() => { window.location.href = '/srci/login.php'; }, 2000);
      return;
    }
    if (!respInc.ok || !datosInc.ok) {
      throw new Error(datosInc.error || 'Error al guardar el reporte.');
    }

    // 2. Subir foto si hay
    if (fotoInput.files[0]) {
      const formData = new FormData();
      formData.append('incidencia_id', datosInc.id);
      formData.append('foto', fotoInput.files[0]);
      const respFoto = await fetch('/srci/api/subir_foto.php', { method: 'POST', body: formData });
      const textoFoto = await respFoto.text();
      let datosFoto = {};
      try {
        datosFoto = JSON.parse(textoFoto);
      } catch (e) {
        console.warn('Respuesta foto no-JSON:', textoFoto);
      }
      if (!respFoto.ok) {
        // No bloqueamos: mostramos advertencia pero el reporte ya se guardo
        console.warn('Advertencia al subir foto:', datosFoto.error || 'No se pudo subir la foto');
      }
    }

    mostrarMensaje('¡Reporte enviado con éxito! Gracias por tu ayuda.', 'exito');
    btnEnviar.innerHTML = '✓ Enviado';
    setTimeout(() => {
      cerrarModal();
      location.reload();
    }, 2000);
  } catch (err) {
    mostrarMensaje(err.message || 'No pudimos enviar el reporte. Probá de nuevo.', 'error');
    btnEnviar.disabled  = false;
    btnEnviar.innerHTML = 'Enviar reporte';
  }
});

function mostrarMensaje(texto, tipo) {
  mensajeDiv.className     = `mensaje mensaje-${tipo}`;
  mensajeDiv.textContent   = texto;
  mensajeDiv.style.display = 'flex';
}

// Eventos de apertura/cierre
btnAbrir.addEventListener('click', abrirModal);
btnCerrar.addEventListener('click', cerrarModal);
modalFondo.addEventListener('click', (e) => { if (e.target === modalFondo) cerrarModal(); });
btnSiguiente.addEventListener('click', mostrarPasoDetalles);
btnVolver.addEventListener('click', () => {
  pasoDetalles.style.display = 'none';
  pasoTipo.style.display     = '';
});

// Arrastrar el modal desde el encabezado
let arrastre = null;

encabezadoModal.addEventListener('pointerdown', (e) => {
  if (e.target.closest('.modal-cerrar')) return;
  arrastre = { x0: e.clientX, y0: e.clientY };
  modalCaja.style.transition = 'none';
  try { encabezadoModal.setPointerCapture(e.pointerId); } catch (err) { /* noop */ }
});

encabezadoModal.addEventListener('pointermove', (e) => {
  if (!arrastre) return;
  const dx = e.clientX - arrastre.x0;
  const dy = e.clientY - arrastre.y0;
  modalCaja.style.transform = `translate(${dx}px, ${dy}px)`;
});

function terminarArrastre(e) {
  if (!arrastre) return;
  arrastre = null;
  modalCaja.style.transition = '';
  if (encabezadoModal.hasPointerCapture && encabezadoModal.hasPointerCapture(e.pointerId)) {
    encabezadoModal.releasePointerCapture(e.pointerId);
  }
}

encabezadoModal.addEventListener('pointerup', terminarArrastre);
encabezadoModal.addEventListener('pointercancel', terminarArrastre);

// Cargar tipos y barrios al iniciar
cargarTipos();
cargarBarrios();
