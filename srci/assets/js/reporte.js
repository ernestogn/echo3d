// reporte.js - Logica del modal de reporte de nueva incidencia

let tipoSeleccionado = null;
let coordSeleccionada = null;
let poligonosBarrios = [];

// Cargar poligonos de barrios (para pre-seleccionar el barrio segun el punto)
let poligonosListos = fetch('/srci/api/poligonos.php')
  .then((r) => r.json())
  .then((poligonos) => { poligonosBarrios = Array.isArray(poligonos) ? poligonos : []; })
  .catch(() => { poligonosBarrios = []; });

// Area aproximada de un anillo (shoelace, en grados^2) para resolver solapes:
// cuando varios poligonos contienen el punto gana el mas chico (el barrio
// interno que se ve "arriba", ej: Las Mandarinas dentro de Villa Itape).
function areaAnillo(anillo) {
  let area = 0;
  for (let i = 0, j = anillo.length - 1; i < anillo.length; j = i++) {
    area += (anillo[j][0] - anillo[i][0]) * (anillo[j][1] + anillo[i][1]);
  }
  return Math.abs(area / 2);
}

// Ray casting: barrio que contiene el punto (misma logica que barrio_por_punto en PHP).
// Con poligonos superpuestos gana el de MENOR area (el interno/visible).
function barrioPorPunto(lat, lng) {
  let ganador = null;
  let areaGanadora = Infinity;
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
      if (dentro) {
        const area = areaAnillo(anillo);
        if (area < areaGanadora) {
          areaGanadora = area;
          ganador = p;
        }
      }
    }
  }
  return ganador;
}

// Pre-selecciona el barrio segun el punto marcado (el usuario lo puede cambiar).
// Espera a que los poligonos esten cargados si llego el click antes.
function preseleccionarBarrio(lat, lng) {
  poligonosListos.then(() => {
    const hit = barrioPorPunto(lat, lng);
    if (hit) barrioSelect.value = hit.nombre;
    autocompletarContacto();
  });
}

// Autocompleta "Contacto vecino / referente" con los referentes del barrio
// elegido (tabla referentes_barrios, via api/barrios.php). No pisa lo que la
// persona escribio a mano: solo reemplaza si el valor actual fue autocompletado.
let contactoAutoPrevio = false;

function autocompletarContacto() {
  const nombre = barrioSelect.value.trim();
  if (nombre === '') return;
  const hit = barriosCargados.find((b) => b.nombre === nombre);
  if (!hit || !hit.referentes) {
    // Sin referente asignado: se ve el motivo en el placeholder
    contactoInput.placeholder = 'Sin referente asignado para ' + nombre;
    return;
  }
  contactoInput.placeholder = 'Nombre o teléfono';
  if (contactoInput.value.trim() === '' || contactoAutoPrevio) {
    contactoInput.value = hit.referentes;
    contactoAutoPrevio = true;
  }
}

const modalFondo     = document.getElementById('modal-reporte');
const btnCerrar      = document.getElementById('btn-cerrar-modal');
const pasoTipo       = document.getElementById('paso-tipo');
const pasoDetalles   = document.getElementById('paso-detalles');
const pasoUbicacion  = document.getElementById('paso-ubicacion');
const grillaTipos    = document.getElementById('grilla-tipos');
const btnSiguienteUbicacion = document.getElementById('btn-siguiente-ubicacion');
const btnVolver      = document.getElementById('btn-volver');
const btnVolverTipo  = document.getElementById('btn-volver-tipo');
const btnEnviar      = document.getElementById('btn-enviar-reporte');
const infoTipo       = document.getElementById('info-tipo-seleccionado');
const coordTexto     = document.getElementById('coord-texto');
const notasReporte   = document.getElementById('notas-reporte');
const fotoInput      = document.getElementById('foto-input');
const vistaPreviaMapa = document.getElementById('vista-previa-foto');
const mensajeDiv     = document.getElementById('mensaje-reporte');
const modalCaja      = document.getElementById('panel-reporte');
const barrioSelect   = document.getElementById('barrio-reporte');
const listaBarrios   = document.getElementById('lista-barrios');
const direccionInput = document.getElementById('direccion-reporte');
const gravedadSelect = document.getElementById('gravedad-reporte');
const familiasInput  = document.getElementById('familias-reporte');
const servicioSelect = document.getElementById('servicio-reporte');
const responsableSelect = document.getElementById('responsable-reporte');
const calleCheck     = document.getElementById('calle-intransitable');
const contactoInput  = document.getElementById('contacto-reporte');
const categoriasReporte = document.getElementById('categorias-reporte');

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

// Cargar categorias de incidencia (etiquetas, multiseleccion) y construir los chips.
// Solo aparece el selector si index.php lo renderizo: los admins ven el catalogo
// completo; los usuarios regulares solo ven las marcadas "visible a usuarios".
async function cargarCategorias() {
  if (!categoriasReporte) return;
  try {
    const urlCat = window.SRCI_ES_ADMIN
      ? '/srci/api/categorias.php'
      : '/srci/api/categorias.php?visibles=1';
    const resp = await fetch(urlCat);
    const cats = await resp.json();
    categoriasReporte.innerHTML = '';
    cats.forEach((cat) => {
      const label = document.createElement('label');
      label.className = 'chip-categoria';
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.value = cat.id;
      input.dataset.clave = cat.clave;
      const span = document.createElement('span');
      span.textContent = (cat.icono ? cat.icono + ' ' : '') + cat.nombre;
      if (cat.color) { label.style.setProperty('--cat-color', cat.color); }
      input.addEventListener('change', () => {
        label.classList.toggle('activa', input.checked);
      });
      label.appendChild(input);
      label.appendChild(span);
      categoriasReporte.appendChild(label);
    });
    resetearCategorias();
  } catch (err) {
    console.error('Error cargando categorias:', err);
  }
}

// Marca por defecto la categoria "Climaticos" (o la primera, si no esta) y desmarca el resto
function resetearCategorias() {
  if (!categoriasReporte) return;
  const inputs = categoriasReporte.querySelectorAll('input[type=checkbox]');
  if (!inputs.length) return;
  const porDefault = categoriasReporte.querySelector('input[data-clave="climaticos"]') || inputs[0];
  inputs.forEach((input) => {
    input.checked = input === porDefault;
    input.parentElement.classList.toggle('activa', input.checked);
  });
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
  // Avanza solo al paso de datos: nada de botones intermedios
  infoTipo.textContent = (tipoSeleccionado.icono || '') + ' ' + tipoSeleccionado.nombre;
  mostrarPaso('detalles');
}

function abrirModal(latlng = null) {
  resetearModal();
  // Punto precargado desde el mapa principal: barrio detectado y direccion
  // geocodificada al instante, sin volver a marcar nada.
  if (latlng) {
    colocarPunto(latlng);
  }
  // Visibilidad por estilos inline: el panel esta anclado con geometria inline
  // y no depende de ninguna clase de la hoja de estilos.
  ajustarPanelAVista();
  actualizarTag();
  modalFondo.style.opacity = '1';
  modalFondo.style.pointerEvents = 'all';
  modalCaja.style.transform = 'translateY(0)';
  document.body.style.overflow = 'hidden';
}

// Ajusta el panel al viewport VISUAL (lo que realmente se ve en pantalla),
// inmune a zoom de pagina, teclado o layouts expandidos por contenido ancho.
function ajustarPanelAVista() {
  const vv = window.visualViewport;
  if (!vv) return; // navegadores viejos: quedan los estilos inline base (100%)
  modalCaja.style.width = Math.round(vv.width) + 'px';
  modalCaja.style.height = Math.round(vv.height) + 'px';
  modalCaja.style.left = Math.round(vv.offsetLeft) + 'px';
  modalCaja.style.top = Math.round(vv.offsetTop) + 'px';
}
if (window.visualViewport) {
  window.visualViewport.addEventListener('resize', ajustarPanelAVista);
  window.visualViewport.addEventListener('scroll', ajustarPanelAVista);
}

// Etiqueta de build (diagnostico de cache/viewport)
function actualizarTag() {
  const tag = document.getElementById('build-tag');
  if (!tag) return;
  tag.textContent = 'v20261026';
}

// Abrir el modal con una ubicacion ya marcada (click en el mapa principal)
function abrirConCoordenada(latlng) {
  abrirModal(latlng);
}
window.abrirReporteConCoordenada = abrirConCoordenada;

function cerrarModal() {
  modalFondo.style.opacity = '0';
  modalFondo.style.pointerEvents = 'none';
  modalCaja.style.transform = 'translateY(100%)';
  document.body.style.overflow = '';
  if (typeof window.removerMarcadorSeleccion === 'function') {
    window.removerMarcadorSeleccion();
  }
}

function resetearModal() {
  tipoSeleccionado  = null;
  coordSeleccionada = null;
  mostrarPaso('ubicacion');
  btnSiguienteUbicacion.disabled = true;
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
  if (geoPendiente) { geoPendiente.abort(); geoPendiente = null; }
  const cajaSugerencia = document.getElementById('sugerencia-direccion');
  if (cajaSugerencia) { cajaSugerencia.style.display = 'none'; cajaSugerencia.innerHTML = ''; }
  gravedadSelect.value = '';
  document.querySelectorAll('#botonera-gravedad .btn-gravedad').forEach((x) => x.classList.remove('seleccionado'));
  familiasInput.value  = '';
  servicioSelect.value = '';
  responsableSelect.value = '';
  calleCheck.checked   = false;
  contactoInput.value  = '';
  contactoAutoPrevio = false;
  resetearCategorias();
}

// Navegacion entre los 3 pasos del modal: ubicacion -> tipo -> detalles
function mostrarPaso(nombre) {
  pasoUbicacion.style.display = nombre === 'ubicacion' ? '' : 'none';
  pasoTipo.style.display      = nombre === 'tipo' ? '' : 'none';
  pasoDetalles.style.display  = nombre === 'detalles' ? '' : 'none';

  if (nombre === 'detalles') {
    infoTipo.textContent = tipoSeleccionado ? (tipoSeleccionado.icono || '') + ' ' + tipoSeleccionado.nombre : '';
  }
}

// El boton Siguiente del paso de ubicacion exige punto + barrio + direccion
function actualizarBtnUbicacion() {
  btnSiguienteUbicacion.disabled = !(coordSeleccionada && barrioSelect.value.trim() !== '' && direccionInput.value.trim() !== '');
}

// Coloca el punto seleccionado en el mapa principal: coordenadas, barrio y direccion sugerida
function colocarPunto(latlng, opciones = {}) {
  coordSeleccionada = latlng;
  coordTexto.textContent = `📍 ${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
  preseleccionarBarrio(latlng.lat, latlng.lng);
  sugerirDireccion(latlng.lat, latlng.lng);
  actualizarBtnUbicacion();
}

// --- Geocodificacion inversa: punto -> direccion (Nominatim via api/geocode.php).
// Si el campo esta vacio, autocompleta; si ya tiene texto, sugiere un boton. ---
let geoPendiente = null;

function sugerirDireccion(lat, lon) {
  const caja = document.getElementById('sugerencia-direccion');
  if (!caja) return;
  if (geoPendiente) geoPendiente.abort();
  caja.style.display = '';
  caja.innerHTML = '<span style="color:var(--color-texto-suave);font-size:.85rem;">🔎 Buscando dirección...</span>';
  geoPendiente = fetch(`/srci/api/geocode.php?lat=${lat}&lon=${lon}`);
  geoPendiente
    .then((r) => r.json())
    .then((d) => {
      geoPendiente = null;
      if (!d || !d.ok || !d.direccion) {
        caja.style.display = 'none';
        caja.innerHTML = '';
        return;
      }
      if (direccionInput.value.trim() === '') {
        direccionInput.value = d.direccion;
        caja.style.display = 'none';
        caja.innerHTML = '';
        actualizarBtnUbicacion();
        return;
      }
      caja.innerHTML = '';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'boton boton-secundario boton-sm';
      btn.textContent = '📍 Usar dirección detectada: ' + d.direccion;
      btn.addEventListener('click', () => {
        direccionInput.value = d.direccion;
        caja.style.display = 'none';
        caja.innerHTML = '';
        actualizarBtnUbicacion();
      });
      caja.appendChild(btn);
    })
    .catch(() => {
      geoPendiente = null;
      caja.style.display = 'none';
      caja.innerHTML = '';
    });
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
  if (!['baja', 'media', 'alta', 'critica'].includes(gravedadSelect.value)) {
    mostrarMensaje('Elegí la gravedad de la situación.', 'error');
    return;
  }

  btnEnviar.disabled   = true;
  btnEnviar.innerHTML  = '<span class="spinner"></span> Enviando...';

  const categoriasElegidas = categoriasReporte
    ? Array.from(
        categoriasReporte.querySelectorAll('input[type=checkbox]:checked')
      ).map((input) => parseInt(input.value))
    : [];

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
        categorias:          categoriasElegidas,
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
  // El mensaje vive arriba del paso: scrollea hasta el para que siempre se vea
  mensajeDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Eventos de apertura/cierre y navegacion entre pasos
// (El reporte inicia tocando el mapa principal -> abrirReporteConCoordenada)
btnCerrar.addEventListener('click', cerrarModal);
modalFondo.addEventListener('click', (e) => { if (e.target === modalFondo) cerrarModal(); });
btnSiguienteUbicacion.addEventListener('click', () => mostrarPaso('tipo'));
btnVolverTipo.addEventListener('click', () => mostrarPaso('ubicacion'));
btnVolver.addEventListener('click', () => mostrarPaso('tipo'));
barrioSelect.addEventListener('input', () => {
  actualizarBtnUbicacion();
  autocompletarContacto();
});
direccionInput.addEventListener('input', actualizarBtnUbicacion);
// Si la persona escribe el contacto a mano, dejamos de autocompletarlo
contactoInput.addEventListener('input', () => { contactoAutoPrevio = false; });

// Elegir/corregir la ubicacion: cierra el modal para tocar el mapa principal
// (al tocarlo, el modal vuelve a abrirse con el punto, barrio y direccion).
const btnElegirMapa = document.getElementById('btn-elegir-mapa');
btnElegirMapa.addEventListener('click', () => {
  cerrarModal();
  if (typeof window.avisoMapa === 'function') {
    window.avisoMapa('📍 Tocá el mapa para marcar el lugar de la incidencia', 6);
  }
});

// Botonera de gravedad: 4 niveles de color en lugar de select
document.querySelectorAll('#botonera-gravedad .btn-gravedad').forEach((btn) => {
  btn.addEventListener('click', () => {
    gravedadSelect.value = btn.dataset.valor || '';
    document.querySelectorAll('#botonera-gravedad .btn-gravedad').forEach((x) => {
      x.classList.toggle('seleccionado', x === btn);
    });
  });
});

// (El arrastre del modal por el encabezado fue ELIMINADO: el modal ahora es
// pantalla completa en todos los dispositivos, anclado con position:fixed.)

// Cargar tipos y barrios al iniciar
cargarTipos();
cargarBarrios();
cargarCategorias();
