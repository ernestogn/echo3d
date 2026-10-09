// reporte.js - Logica del modal de reporte de nueva incidencia

let tipoSeleccionado = null;
let coordSeleccionada = null;
let miniMapa = null;
let miniMarcador = null;

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

function cerrarModal() {
  modalFondo.classList.remove('visible');
  document.body.style.overflow = '';
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
  if (miniMarcador) { miniMarcador.remove(); miniMarcador = null; }
}

function mostrarPasoDetalles() {
  pasoTipo.style.display     = 'none';
  pasoDetalles.style.display = '';
  infoTipo.textContent       = (tipoSeleccionado.icono || '') + ' ' + tipoSeleccionado.nombre;

  // Inicializar mini-mapa si no existe aun
  if (!miniMapa) {
    const centro = window.mapaLeaflet ? window.mapaLeaflet.getCenter() : L.latLng(-34.6037, -58.3816);
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
    });
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

  btnEnviar.disabled   = true;
  btnEnviar.innerHTML  = '<span class="spinner"></span> Enviando...';

  try {
    // 1. Crear incidencia
    const respInc = await fetch('/srci/api/reportes.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        tipo_id:  parseInt(tipoSeleccionado.id),
        latitud:  coordSeleccionada.lat,
        longitud: coordSeleccionada.lng,
        notas:    notasReporte.value.trim(),
      }),
    });
    const datosInc = await respInc.json();
    if (!respInc.ok || !datosInc.ok) {
      throw new Error(datosInc.error || 'Error al guardar el reporte.');
    }

    // 2. Subir foto si hay
    if (fotoInput.files[0]) {
      const formData = new FormData();
      formData.append('incidencia_id', datosInc.id);
      formData.append('foto', fotoInput.files[0]);
      const respFoto = await fetch('/srci/api/subir_foto.php', { method: 'POST', body: formData });
      const datosFoto = await respFoto.json();
      if (!respFoto.ok) {
        // No bloqueamos: mostramos advertencia pero el reporte ya se guardo
        console.warn('Advertencia al subir foto:', datosFoto.error);
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

// Cargar tipos al iniciar
cargarTipos();
