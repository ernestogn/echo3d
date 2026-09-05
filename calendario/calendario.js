// JavaScript para el calendario de eventos

// Variables globales
let calendario;
let filtrosActuales = {
    tipo: 'todos',
    empresa: 'todas'
};

document.addEventListener('DOMContentLoaded', function() {
    inicializarCalendario();
    configurarEventos();
    cargarTiposEvento();
    cargarCategoriasEvento();
    actualizarStatusBar();
});

// Inicializar calendario
function inicializarCalendario() {
    const calendarEl = document.getElementById('calendario');

    calendario = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        editable: true,  // Habilitar edición (drag & drop)
        headerToolbar: {
            left: 'prevMonthButton,nextMonthButton,todayButton',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            month: 'Mes',
            week: 'Semana',
            day: 'Día',
            list: 'Lista'
        },
        customButtons: {
            prevMonthButton: {
                text: '<',
                click: function() {
                    calendario.prev();
                }
            },
            nextMonthButton: {
                text: '>',
                click: function() {
                    calendario.next();
                }
            },
            todayButton: {
                text: 'Hoy',
                click: function() {
                    calendario.today();
                }
            }
        },
        height: 'auto',
        events: function(fetchInfo, successCallback, failureCallback) {
            cargarEventosCalendario(fetchInfo, successCallback, failureCallback);
        },
        eventClick: function(info) {
            // Para administradores, abrir modal de edición directamente
            abrirModalEdicion(info.event.id);
        },
        eventDrop: function(info) {
            // Drag & drop para cambiar fecha del evento
            actualizarFechaEvento(info.event.id, info.event.start, info.event.end);
        },
        eventMouseEnter: function(info) {
            // Tooltip simple
            if (info.event.extendedProps.ubicacion) {
                info.el.title = `${info.event.title}\n📍 ${info.event.extendedProps.ubicacion}`;
            }
        }
    });

    calendario.render();
}

// Cargar eventos del calendario
function cargarEventosCalendario(fetchInfo, successCallback, failureCallback) {
    console.log('🔄 INICIANDO CARGA DE EVENTOS:', {
        start: fetchInfo.start.toISOString().split('T')[0],
        end: fetchInfo.end.toISOString().split('T')[0]
    });

    const params = new URLSearchParams({
        action: 'calendario',
        start: fetchInfo.start.toISOString().split('T')[0],
        end: fetchInfo.end.toISOString().split('T')[0]
    });

    fetch('api_eventos.php?' + params.toString())
        .then(response => {
            console.log('📡 RESPUESTA HTTP:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('📦 DATOS RECIBIDOS:', data.length + ' eventos');

            // Mostrar todos los títulos de eventos para debugging
            data.forEach((evento, index) => {
                console.log(`🎫 Evento ${index + 1}: "${evento.title}" (ID: ${evento.id})`);
            });

            // DEBUGGING AVANZADO: Análisis completo del evento problemático
            const eventoFeria = data.find(e => e.title && e.title.includes('Feria Internacional de Tecnología'));
            const otrosEventos = data.filter(e => e !== eventoFeria).slice(0, 2); // Primeros 2 otros

            if (eventoFeria) {
                console.log('🔍 EVENTO PROBLEMÁTICO - Feria Internacional:', eventoFeria);
                console.log('🔍 Propiedades únicas:', {
                    id: eventoFeria.id,
                    titulo: eventoFeria.title,
                    start: eventoFeria.start,
                    end: eventoFeria.end,
                    extendedProps: eventoFeria.extendedProps,
                    backgroundColor: eventoFeria.backgroundColor,
                    borderColor: eventoFeria.borderColor,
                    textColor: eventoFeria.textColor
                });
            }

            // Comparar con otros eventos
            otrosEventos.forEach((evento, index) => {
                console.log(`📊 EVENTO DE COMPARACIÓN ${index + 1}:`, {
                    id: evento.id,
                    titulo: evento.title,
                    start: evento.start,
                    end: evento.end,
                    extendedProps: evento.extendedProps,
                    backgroundColor: evento.backgroundColor,
                    borderColor: evento.borderColor,
                    textColor: evento.textColor
                });
            });

            // Análisis detallado de diferencias
            if (eventoFeria && otrosEventos.length > 0) {
                console.log('🔬 ANÁLISIS DETALLADO DEL EVENTO FERIA:');
                console.log('📅 DURACIÓN:', {
                    feria: {
                        inicio: eventoFeria.start,
                        fin: eventoFeria.end,
                        duracion_dias: Math.ceil((new Date(eventoFeria.end) - new Date(eventoFeria.start)) / (1000 * 60 * 60 * 24))
                    },
                    comparacion1: {
                        inicio: otrosEventos[0].start,
                        fin: otrosEventos[0].end,
                        duracion_horas: Math.round((new Date(otrosEventos[0].end) - new Date(otrosEventos[0].start)) / (1000 * 60 * 60))
                    }
                });

                // Comparar extendedProps
                console.log('🔧 EXTENDED PROPS - FERIA:', eventoFeria.extendedProps);
                console.log('🔧 EXTENDED PROPS - COMPARACIÓN:', otrosEventos[0].extendedProps);

                // Comparar colores
                console.log('🎨 COLORES:', {
                    feria: {
                        backgroundColor: eventoFeria.backgroundColor,
                        borderColor: eventoFeria.borderColor,
                        textColor: eventoFeria.textColor
                    },
                    comparacion: {
                        backgroundColor: otrosEventos[0].backgroundColor,
                        borderColor: otrosEventos[0].borderColor,
                        textColor: otrosEventos[0].textColor
                    }
                });

                // Diferencias completas
                const diferencias = {};
                const eventoComparacion = otrosEventos[0];

                Object.keys(eventoFeria).forEach(key => {
                    if (JSON.stringify(eventoFeria[key]) !== JSON.stringify(eventoComparacion[key])) {
                        diferencias[key] = {
                            feria: eventoFeria[key],
                            comparacion: eventoComparacion[key]
                        };
                    }
                });

                console.log('⚡ DIFERENCIAS COMPLETAS:', diferencias);
                console.log('🏷️ RESUMEN: La Feria dura', Math.ceil((new Date(eventoFeria.end) - new Date(eventoFeria.start)) / (1000 * 60 * 60 * 24)), 'días, mientras que otros eventos duran horas');
            }

            successCallback(data);
            actualizarStatusBar();
        })
        .catch(error => {
            console.error('Error al cargar eventos:', error);
            failureCallback(error);
        });
}

// Configurar eventos de la interfaz
function configurarEventos() {
    // Botones principales
    document.getElementById('btnNuevo').addEventListener('click', mostrarFormularioNuevo);
    document.getElementById('btnImportar').addEventListener('click', mostrarFormularioImportar);
    document.getElementById('btnConvertirTexto').addEventListener('click', mostrarFormularioConvertir);

    // Botones de cancelar
    document.getElementById('btnCancelarNuevo').addEventListener('click', ocultarFormularios);
    document.getElementById('btnCancelarImportar').addEventListener('click', ocultarFormularios);
    document.getElementById('btnCancelarConvertir').addEventListener('click', ocultarFormularios);

    // Formularios
    document.getElementById('formularioCrearEvento').addEventListener('submit', crearEvento);

    // Importación
    configurarImportacion();

    // Conversión de texto
    configurarConversionTexto();
}

// Mostrar/ocultar formularios
function mostrarFormularioNuevo() {
    ocultarFormularios();
    document.getElementById('formNuevoEvento').classList.add('active');
    document.getElementById('btnNuevo').classList.add('active');

    // Scroll al formulario
    document.getElementById('formNuevoEvento').scrollIntoView({ behavior: 'smooth' });
}

function mostrarFormularioImportar() {
    ocultarFormularios();
    document.getElementById('formImportarJSON').classList.add('active');
    document.getElementById('btnImportar').classList.add('active');

    // Scroll al formulario
    document.getElementById('formImportarJSON').scrollIntoView({ behavior: 'smooth' });
}

function mostrarFormularioConvertir() {
    ocultarFormularios();
    document.getElementById('formConvertirTexto').classList.add('active');
    document.getElementById('btnConvertirTexto').classList.add('active');

    // Scroll al formulario
    document.getElementById('formConvertirTexto').scrollIntoView({ behavior: 'smooth' });
}

function ocultarFormularios() {
    document.querySelectorAll('.form-section').forEach(form => form.classList.remove('active'));
    document.querySelectorAll('.btn-minimal').forEach(btn => btn.classList.remove('active'));
}

// Actualizar barra de estado
function actualizarStatusBar() {
    // Obtener estadísticas básicas
    fetch('api_eventos.php?action=calendario&start=' + new Date().toISOString().split('T')[0] + '&end=' + new Date(Date.now() + 365*24*60*60*1000).toISOString().split('T')[0])
        .then(response => response.json())
        .then(data => {
            const totalEventos = data.length;
            const hoy = new Date().toISOString().split('T')[0];
            const eventosHoy = data.filter(event => event.start.startsWith(hoy)).length;

            // Contar eventos de esta semana
            const inicioSemana = new Date();
            inicioSemana.setDate(inicioSemana.getDate() - inicioSemana.getDay());
            const finSemana = new Date(inicioSemana);
            finSemana.setDate(finSemana.getDate() + 6);

            const eventosSemana = data.filter(event => {
                const fechaEvento = new Date(event.start);
                return fechaEvento >= inicioSemana && fechaEvento <= finSemana;
            }).length;

            // Actualizar display
            document.getElementById('totalEventos').textContent = totalEventos;
            document.getElementById('eventosHoy').textContent = eventosHoy;
            document.getElementById('eventosSemana').textContent = eventosSemana;
            document.getElementById('ultimaActualizacion').textContent = new Date().toLocaleTimeString('es-ES');
        })
        .catch(error => {
            console.error('Error al actualizar status:', error);
        });
}

// Crear evento
function crearEvento(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const categoriasSeleccionadas = [];
    document.querySelectorAll('#categoriasEvento input[type="checkbox"]:checked').forEach(checkbox => {
        categoriasSeleccionadas.push(checkbox.value);
    });
    formData.append('categorias', JSON.stringify(categoriasSeleccionadas));

    fetch('api_eventos.php?action=crear', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            calendario.refetchEvents();
            ocultarFormularios();
            e.target.reset();
            actualizarStatusBar();
        } else {
            alert('Error al crear evento: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar la solicitud');
    });
}

// Editar evento (simplificado - funcionalidad básica por ahora)
function editarEvento(eventoId) {
    // Por ahora, mostrar mensaje - funcionalidad completa puede implementarse después
    alert(`Funcionalidad de edición para evento ID: ${eventoId}\n\nEsta funcionalidad puede implementarse posteriormente si es necesaria.`);
}

// Actualizar fecha del evento (drag & drop)
function actualizarFechaEvento(eventoId, nuevaFechaInicio, nuevaFechaFin) {
    const datos = {
        evento_id: eventoId,
        fecha_inicio: nuevaFechaInicio.toISOString(),
        fecha_fin: nuevaFechaFin ? nuevaFechaFin.toISOString() : null
    };

    fetch('api_eventos.php?action=actualizar_fecha', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensaje de éxito discreto
            mostrarMensajeExito('Fecha del evento actualizada');
            actualizarStatusBar();
        } else {
            // Revertir el cambio en el calendario
            calendario.refetchEvents();
            alert('Error al actualizar fecha: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error al actualizar fecha:', error);
        // Revertir el cambio en el calendario
        calendario.refetchEvents();
        alert('Error de conexión al actualizar fecha');
    });
}

// Cargar tipos de evento
function cargarTiposEvento() {
    fetch('api_eventos.php?action=tipos_evento')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('tipo_evento');

            if (data.tipos && data.tipos.length > 0) {
                select.innerHTML = '<option value="">Seleccionar tipo</option>';

                data.tipos.forEach(tipo => {
                    const option = document.createElement('option');
                    option.value = tipo.nombre;
                    option.textContent = `${tipo.nombre.charAt(0).toUpperCase() + tipo.nombre.slice(1)}`;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">No hay tipos disponibles</option>';
            }
        })
        .catch(error => {
            console.error('Error al cargar tipos de evento:', error);
            const select = document.getElementById('tipo_evento');
            select.innerHTML = '<option value="">Error al cargar tipos</option>';
        });
}

// Cargar categorías de evento
function cargarCategoriasEvento() {
    fetch('api_eventos.php?action=categorias')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('categoriasEvento');

            if (data.categorias && data.categorias.length > 0) {
                const ordenDeseado = [
                    'agro', 'arte', 'avicultura', 'cambios_corporativos', 'capacitacion', 'capacitaciones',
                    'comunidad', 'conferencias', 'educación', 'ferias', 'investigacion', 'lanzamientos',
                    'networking', 'oficios', 'reuniones', 'tecnologia'
                ];

                const ordenMap = {};
                ordenDeseado.forEach((nombre, index) => {
                    ordenMap[nombre] = index;
                });

                data.categorias.sort((a, b) => {
                    const ordenA = ordenMap[a.nombre] !== undefined ? ordenMap[a.nombre] : 999;
                    const ordenB = ordenMap[b.nombre] !== undefined ? ordenMap[b.nombre] : 999;
                    return ordenA - ordenB;
                });

                let html = '<div class="row g-2">';
                data.categorias.forEach(categoria => {
                    html += `
                        <div class="col-6 col-sm-4 col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="${categoria.id}" id="cat_${categoria.id}">
                                <label class="form-check-label" for="cat_${categoria.id}" style="font-size: 0.85em;">
                                    <span class="badge me-1" style="background-color: ${categoria.color_hex}; width: 8px; height: 8px; border-radius: 50%; display: inline-block;"></span>
                                    ${categoria.nombre}
                                </label>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<small class="text-muted">No hay categorías disponibles</small>';
            }
        })
        .catch(error => {
            console.error('Error al cargar categorías:', error);
        });
}

// Configurar búsqueda de empresa
function configurarBusquedaEmpresa() {
    const busquedaInput = document.getElementById('busquedaEmpresaEvento');
    const empresaSeleccionada = document.getElementById('empresaEventoSeleccionada');
    let timeoutId;

    busquedaInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(timeoutId);

        if (query.length < 2) {
            return;
        }

        timeoutId = setTimeout(() => {
            buscarEmpresasEvento(query);
        }, 300);
    });
}

// Buscar empresas para el evento
function buscarEmpresasEvento(query) {
    fetch(`../../buscar_empresas.php?q=${encodeURIComponent(query)}&limite=5`)
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                mostrarSugerenciasEmpresas(data);
            }
        })
        .catch(error => {
            console.error('Error al buscar empresas:', error);
        });
}

// Mostrar sugerencias de empresas
function mostrarSugerenciasEmpresas(empresas) {
    const inputElement = document.getElementById('busquedaEmpresaEvento');
    const empresaSeleccionada = document.getElementById('empresaEventoSeleccionada');

    if (window.sugerenciasDropdown) {
        window.sugerenciasDropdown.remove();
    }

    const dropdown = document.createElement('div');
    dropdown.id = 'sugerenciasEmpresas';
    dropdown.className = 'sugerencias-empresas';

    empresas.forEach(emp => {
        const item = document.createElement('div');
        item.className = 'sugerencia-item';
        item.innerHTML = `${emp.nombre} ${emp.ciudad ? '- ' + emp.ciudad : ''}`;
        item.addEventListener('click', () => seleccionarEmpresaEvento(emp.id, emp.nombre));
        dropdown.appendChild(item);
    });

    inputElement.parentNode.style.position = 'relative';
    inputElement.parentNode.appendChild(dropdown);
    window.sugerenciasDropdown = dropdown;

    // Ocultar al hacer clic fuera
    document.addEventListener('click', function hideDropdown(e) {
        if (!inputElement.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.remove();
            document.removeEventListener('click', hideDropdown);
        }
    });
}

// Seleccionar empresa
function seleccionarEmpresaEvento(id, nombre) {
    document.getElementById('busquedaEmpresaEvento').value = nombre;
    document.getElementById('empresaEventoSeleccionada').value = id;

    if (window.sugerenciasDropdown) {
        window.sugerenciasDropdown.remove();
    }
}

// Configurar importación
function configurarImportacion() {
    const archivoInput = document.getElementById('archivoImportacion');
    const jsonTextarea = document.getElementById('jsonImportacion');
    const btnImportar = document.getElementById('btnImportar');
    const previewDiv = document.getElementById('previewImportacion');
    const contenidoPreview = document.getElementById('contenidoPreview');

    // Función para procesar JSON y mostrar preview
    function procesarJsonParaPreview(jsonData, fuente) {
        try {
            let previewHtml = `<div class="alert alert-success"><i class="bi bi-check-circle"></i> JSON válido (${fuente})</div>`;

            if (jsonData.eventos) {
                // Múltiples eventos
                previewHtml += `<p><strong>${jsonData.eventos.length} eventos encontrados</strong></p>`;
                previewHtml += '<div class="list-group list-group-flush">';

                jsonData.eventos.slice(0, 3).forEach((evento, index) => {
                    previewHtml += `
                        <div class="list-group-item">
                            <strong>${evento.titulo}</strong><br>
                            <small class="text-muted">${evento.fecha_inicio} - ${evento.ubicacion || 'Sin ubicación'}</small>
                        </div>
                    `;
                });

                if (jsonData.eventos.length > 3) {
                    previewHtml += `<div class="list-group-item"><em>... y ${jsonData.eventos.length - 3} eventos más</em></div>`;
                }

                previewHtml += '</div>';
            } else if (jsonData.titulo) {
                // Evento individual
                previewHtml += `
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">${jsonData.titulo}</h6>
                            <p class="card-text">${jsonData.descripcion || 'Sin descripción'}</p>
                            <small class="text-muted">
                                ${jsonData.fecha_inicio} - ${jsonData.ubicacion || 'Sin ubicación'}
                            </small>
                        </div>
                    </div>
                `;
            } else {
                throw new Error('Formato de JSON no reconocido');
            }

            contenidoPreview.innerHTML = previewHtml;
            previewDiv.style.display = 'block';
            btnImportar.disabled = false;
            window.datosImportacion = jsonData;

        } catch (error) {
            contenidoPreview.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error: ${error.message}</div>`;
            previewDiv.style.display = 'block';
            btnImportar.disabled = true;
            window.datosImportacion = null;
        }
    }

    // Configurar input de archivo
    archivoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                alert('Por favor selecciona un archivo JSON válido');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const jsonData = JSON.parse(e.target.result);
                procesarJsonParaPreview(jsonData, 'archivo');
            };
            reader.readAsText(file);
        } else {
            previewDiv.style.display = 'none';
            btnImportar.disabled = true;
            window.datosImportacion = null;
        }
    });

    // Configurar textarea de JSON
    jsonTextarea.addEventListener('input', function() {
        const jsonText = this.value.trim();
        if (jsonText) {
            try {
                const jsonData = JSON.parse(jsonText);
                procesarJsonParaPreview(jsonData, 'texto pegado');
            } catch (error) {
                previewDiv.style.display = 'none';
                btnImportar.disabled = true;
                window.datosImportacion = null;
            }
        } else {
            previewDiv.style.display = 'none';
            btnImportar.disabled = true;
            window.datosImportacion = null;
        }
    });

    // Configurar botón de importación
    btnImportar.addEventListener('click', function() {
        if (window.datosImportacion) {
            importarEventosDesdeDatos(window.datosImportacion);
        }
    });
}

// Importar eventos desde datos
function importarEventosDesdeDatos(datos) {
    const btnImportar = document.getElementById('btnImportar');
    const originalText = btnImportar.innerHTML;

    btnImportar.disabled = true;
    btnImportar.innerHTML = '<i class="bi bi-hourglass-split"></i> Importando...';

    fetch('api_eventos.php?action=importar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            calendario.refetchEvents();
            ocultarFormularios();
            actualizarStatusBar();

            // Limpiar formulario
            document.getElementById('archivoImportacion').value = '';
            document.getElementById('jsonImportacion').value = '';
            document.getElementById('previewImportacion').style.display = 'none';

            alert(`Importación completada: ${data.estadisticas.creados} creados, ${data.estadisticas.actualizados} actualizados`);
        } else {
            alert('Error en la importación: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error de importación:', error);
        alert('Error de conexión durante la importación');
    })
    .finally(() => {
        btnImportar.disabled = false;
        btnImportar.innerHTML = originalText;
    });
}

// Los botones de navegación ahora están integrados en el headerToolbar de FullCalendar

// Inicializar búsqueda de empresa cuando se muestra el formulario
document.getElementById('btnNuevo').addEventListener('click', function() {
    setTimeout(configurarBusquedaEmpresa, 100);
});

// Función para abrir modal de edición
function abrirModalEdicion(eventoId) {
    // Cargar datos del evento
    fetch(`api_eventos.php?action=detalle&id=${eventoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error al cargar evento: ' + data.error);
                return;
            }

            // Llenar formulario con datos del evento
            document.getElementById('edit_evento_id').value = data.id;
            document.getElementById('edit_titulo').value = data.titulo || '';
            document.getElementById('edit_descripcion').value = data.descripcion || '';
            document.getElementById('edit_ubicacion').value = data.ubicacion || '';
            document.getElementById('edit_capacidad_maxima').value = data.capacidad_maxima || '';
            document.getElementById('edit_costo_participacion').value = data.costo_participacion || '';

            // Fechas - convertir formato
            if (data.fecha_inicio) {
                const fechaInicio = new Date(data.fecha_inicio);
                document.getElementById('edit_fecha_inicio').value = fechaInicio.toISOString().slice(0, 16);
            }

            if (data.fecha_fin) {
                const fechaFin = new Date(data.fecha_fin);
                document.getElementById('edit_fecha_fin').value = fechaFin.toISOString().slice(0, 16);
            }

            // Tipo de evento
            cargarTiposEventoEdicion(data.tipo_evento);

            // Empresa organizadora
            if (data.empresa_organizadora_nombre) {
                document.getElementById('edit_busqueda_empresa').value = data.empresa_organizadora_nombre;
                document.getElementById('edit_empresa_seleccionada').value = data.empresa_organizadora_id;
            } else {
                document.getElementById('edit_busqueda_empresa').value = '';
                document.getElementById('edit_empresa_seleccionada').value = '';
            }

            // Categorías - extraer solo los IDs
            const categoriasIds = (data.categorias_ids || []).map(cat => cat.id);
            cargarCategoriasEventoEdicion(categoriasIds);

            // Configurar búsqueda de empresa para edición
            configurarBusquedaEmpresaEdicion();

            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalEditarEvento'));
            modal.show();

            // Configurar botón de guardar
            document.getElementById('btnGuardarEdicion').onclick = function() {
                guardarCambiosEvento();
            };
        })
        .catch(error => {
            console.error('Error al cargar evento:', error);
            alert('Error al cargar los datos del evento');
        });
}

// Función para cargar tipos de evento en el modal de edición
function cargarTiposEventoEdicion(tipoSeleccionado) {
    fetch('api_eventos.php?action=tipos_evento')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('edit_tipo_evento');

            if (data.tipos && data.tipos.length > 0) {
                select.innerHTML = '<option value="">Seleccionar tipo</option>';

                data.tipos.forEach(tipo => {
                    const option = document.createElement('option');
                    option.value = tipo.nombre;
                    option.textContent = `${tipo.nombre.charAt(0).toUpperCase() + tipo.nombre.slice(1)}`;
                    if (tipo.nombre === tipoSeleccionado) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">No hay tipos disponibles</option>';
            }
        })
        .catch(error => {
            console.error('Error al cargar tipos de evento:', error);
            const select = document.getElementById('edit_tipo_evento');
            select.innerHTML = '<option value="">Error al cargar tipos</option>';
        });
}

// Función para cargar categorías en el modal de edición
function cargarCategoriasEventoEdicion(categoriasSeleccionadas) {
    fetch('api_eventos.php?action=categorias')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('edit_categorias_evento');

            if (data.categorias && data.categorias.length > 0) {
                const ordenDeseado = [
                    'agro', 'arte', 'avicultura', 'cambios_corporativos', 'capacitacion', 'capacitaciones',
                    'comunidad', 'conferencias', 'educación', 'ferias', 'investigacion', 'lanzamientos',
                    'networking', 'oficios', 'reuniones', 'tecnologia'
                ];

                const ordenMap = {};
                ordenDeseado.forEach((nombre, index) => {
                    ordenMap[nombre] = index;
                });

                data.categorias.sort((a, b) => {
                    const ordenA = ordenMap[a.nombre] !== undefined ? ordenMap[a.nombre] : 999;
                    const ordenB = ordenMap[b.nombre] !== undefined ? ordenMap[b.nombre] : 999;
                    return ordenA - ordenB;
                });

                let html = '<div class="row g-2">';
                data.categorias.forEach(categoria => {
                    const checked = categoriasSeleccionadas.includes(categoria.id) ? 'checked' : '';
                    html += `
                        <div class="col-6 col-sm-4 col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="${categoria.id}" id="edit_cat_${categoria.id}" ${checked}>
                                <label class="form-check-label" for="edit_cat_${categoria.id}" style="font-size: 0.85em;">
                                    <span class="badge me-1" style="background-color: ${categoria.color_hex}; width: 8px; height: 8px; border-radius: 50%; display: inline-block;"></span>
                                    ${categoria.nombre}
                                </label>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<small class="text-muted">No hay categorías disponibles</small>';
            }
        })
        .catch(error => {
            console.error('Error al cargar categorías:', error);
        });
}

// Función para configurar búsqueda de empresa en el modal de edición
function configurarBusquedaEmpresaEdicion() {
    const busquedaInput = document.getElementById('edit_busqueda_empresa');
    const empresaSeleccionada = document.getElementById('edit_empresa_seleccionada');
    let timeoutId;

    // Limpiar event listeners anteriores
    const newInput = busquedaInput.cloneNode(true);
    busquedaInput.parentNode.replaceChild(newInput, busquedaInput);

    newInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(timeoutId);

        if (query.length < 2) {
            return;
        }

        timeoutId = setTimeout(() => {
            buscarEmpresasEventoEdicion(query);
        }, 300);
    });
}

// Función para buscar empresas en el modal de edición
function buscarEmpresasEventoEdicion(query) {
    fetch(`../../buscar_empresas.php?q=${encodeURIComponent(query)}&limite=5`)
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                mostrarSugerenciasEmpresasEdicion(data);
            }
        })
        .catch(error => {
            console.error('Error al buscar empresas:', error);
        });
}

// Función para mostrar sugerencias de empresas en el modal de edición
function mostrarSugerenciasEmpresasEdicion(empresas) {
    const inputElement = document.getElementById('edit_busqueda_empresa');
    const empresaSeleccionada = document.getElementById('edit_empresa_seleccionada');

    if (window.sugerenciasDropdownEdicion) {
        window.sugerenciasDropdownEdicion.remove();
    }

    const dropdown = document.createElement('div');
    dropdown.id = 'sugerenciasEmpresasEdicion';
    dropdown.className = 'sugerencias-empresas';

    empresas.forEach(emp => {
        const item = document.createElement('div');
        item.className = 'sugerencia-item';
        item.innerHTML = `${emp.nombre} ${emp.ciudad ? '- ' + emp.ciudad : ''}`;
        item.addEventListener('click', () => seleccionarEmpresaEventoEdicion(emp.id, emp.nombre));
        dropdown.appendChild(item);
    });

    inputElement.parentNode.style.position = 'relative';
    inputElement.parentNode.appendChild(dropdown);
    window.sugerenciasDropdownEdicion = dropdown;

    // Ocultar al hacer clic fuera
    document.addEventListener('click', function hideDropdown(e) {
        if (!inputElement.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.remove();
            document.removeEventListener('click', hideDropdown);
        }
    });
}

// Función para seleccionar empresa en el modal de edición
function seleccionarEmpresaEventoEdicion(id, nombre) {
    document.getElementById('edit_busqueda_empresa').value = nombre;
    document.getElementById('edit_empresa_seleccionada').value = id;

    if (window.sugerenciasDropdownEdicion) {
        window.sugerenciasDropdownEdicion.remove();
    }
}

// Función para guardar cambios del evento
function guardarCambiosEvento() {
    // Recopilar datos del formulario
    const form = document.getElementById('formularioEditarEvento');
    const formData = new FormData(form);

    // Agregar categorías seleccionadas
    const categoriasSeleccionadas = [];
    document.querySelectorAll('#edit_categorias_evento input[type="checkbox"]:checked').forEach(checkbox => {
        categoriasSeleccionadas.push(checkbox.value);
    });

    // Convertir FormData a objeto para enviar como JSON
    const datos = {
        evento_id: formData.get('evento_id'),
        titulo: formData.get('titulo'),
        tipo_evento: formData.get('tipo_evento'),
        empresa_organizadora_id: formData.get('empresa_organizadora_id'),
        descripcion: formData.get('descripcion'),
        fecha_inicio: formData.get('fecha_inicio'),
        fecha_fin: formData.get('fecha_fin'),
        ubicacion: formData.get('ubicacion'),
        capacidad_maxima: formData.get('capacidad_maxima'),
        costo_participacion: formData.get('costo_participacion'),
        categorias: categoriasSeleccionadas
    };

    // Deshabilitar botón mientras se guarda
    const btnGuardar = document.getElementById('btnGuardarEdicion');
    const originalText = btnGuardar.innerHTML;
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';

    fetch('api_eventos.php?action=actualizar', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarEvento'));
            modal.hide();

            // Actualizar calendario y status
            calendario.refetchEvents();
            actualizarStatusBar();

            // Mostrar mensaje de éxito (sin alert, usando un toast o similar)
            mostrarMensajeExito('Evento actualizado correctamente');
        } else {
            alert('Error al actualizar evento: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar la solicitud');
    })
    .finally(() => {
        // Restaurar botón
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = originalText;
    });
}

// Función para mostrar mensaje de éxito (sin alert)
function mostrarMensajeExito(mensaje) {
    // Crear un toast de Bootstrap para mostrar el mensaje
    const toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
    toastContainer.innerHTML = `
        <div class="toast align-items-center text-white bg-success border-0" role="alert">
            <div class="toast-body">
                <i class="bi bi-check-circle me-2"></i>${mensaje}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    document.body.appendChild(toastContainer);

    const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
    toast.show();

    // Remover el toast después de que se oculte
    toastContainer.addEventListener('hidden.bs.toast', function() {
        toastContainer.remove();
    });
}

// Configurar conversión de texto a JSON
function configurarConversionTexto() {
    // Generar prompt
    document.getElementById('btnGenerarPrompt').addEventListener('click', generarPromptConversion);

    // Copiar prompt al portapapeles
    document.getElementById('btnCopiarPrompt').addEventListener('click', copiarPromptAlPortapapeles);

    // Importar evento convertido
    document.getElementById('btnImportarConvertido').addEventListener('click', importarEventoConvertido);

    // Validar JSON en tiempo real
    document.getElementById('jsonConvertido').addEventListener('input', function() {
        const jsonText = this.value.trim();
        const btnImportar = document.getElementById('btnImportarConvertido');

        if (jsonText) {
            try {
                JSON.parse(jsonText);
                btnImportar.disabled = false;
                btnImportar.innerHTML = '<i class="bi bi-check-circle"></i> Importar Evento';
            } catch (error) {
                btnImportar.disabled = true;
                btnImportar.innerHTML = '<i class="bi bi-exclamation-triangle"></i> JSON Inválido';
            }
        } else {
            btnImportar.disabled = true;
            btnImportar.innerHTML = '<i class="bi bi-check-circle"></i> Importar Evento';
        }
    });
}

// Generar prompt completo para chatbot
function generarPromptConversion() {
    const textoAnuncio = document.getElementById('textoAnuncio').value.trim();

    if (!textoAnuncio) {
        alert('Por favor ingresa el texto del anuncio primero');
        return;
    }

    const promptCompleto = `Convierte el siguiente texto de anuncio de evento a formato JSON para importar en el sistema de eventos. Usa esta estructura exacta:

**ESTRUCTURA JSON REQUERIDA:**
\`\`\`json
{
  "titulo": "Título del evento (requerido)",
  "descripcion": "Descripción detallada del evento",
  "tipo_evento": "feria|lanzamiento|conferencia|reunion|cambio_corporativo|capacitacion|taller|otro",
  "fecha_inicio": "YYYY-MM-DD HH:MM:SS (requerido, formato exacto)",
  "fecha_fin": "YYYY-MM-DD HH:MM:SS (opcional)",
  "ubicacion": "Dirección completa del lugar",
  "empresa_organizadora": "Nombre exacto de la empresa organizadora",
  "capacidad_maxima": número (opcional),
  "costo_participacion": número decimal (opcional),
  "enlace_registro": "URL completa (opcional)",
  "contacto_email": "email@empresa.com (opcional)",
  "contacto_telefono": "+54 XX XXXX-XXXX (opcional)",
  "categorias": ["categoria1", "categoria2"] (array de strings, opcional)
}
\`\`\`

**REGLAS IMPORTANTES:**
- titulo y fecha_inicio son OBLIGATORIOS
- fecha_inicio debe estar en formato YYYY-MM-DD HH:MM:SS
- tipo_evento debe ser uno de los valores permitidos
- categorias debe ser un array de strings (nombres de categorías)
- Todos los demás campos son opcionales pero recomendados

**TEXTO DEL ANUNCIO:**

${textoAnuncio}

**RESPUESTA ESPERADA:**
Solo devuelve el JSON válido, sin explicaciones adicionales.`;

    document.getElementById('promptGenerado').value = promptCompleto;
    document.getElementById('promptGeneradoContainer').style.display = 'block';

    // Scroll al prompt generado
    document.getElementById('promptGeneradoContainer').scrollIntoView({ behavior: 'smooth' });
}

// Copiar prompt al portapapeles
function copiarPromptAlPortapapeles() {
    const promptTextarea = document.getElementById('promptGenerado');
    const btnCopiar = document.getElementById('btnCopiarPrompt');

    promptTextarea.select();
    promptTextarea.setSelectionRange(0, 99999); // Para móviles

    try {
        document.execCommand('copy');

        // Cambiar texto del botón temporalmente
        const originalText = btnCopiar.innerHTML;
        btnCopiar.innerHTML = '<i class="bi bi-check-circle"></i> ¡Copiado!';
        btnCopiar.classList.remove('btn-outline-secondary');
        btnCopiar.classList.add('btn-success');

        setTimeout(() => {
            btnCopiar.innerHTML = originalText;
            btnCopiar.classList.remove('btn-success');
            btnCopiar.classList.add('btn-outline-secondary');
        }, 2000);

    } catch (error) {
        alert('Error al copiar al portapapeles. Selecciona el texto manualmente y copia con Ctrl+C.');
    }
}

// Importar evento convertido desde JSON
function importarEventoConvertido() {
    const jsonText = document.getElementById('jsonConvertido').value.trim();

    if (!jsonText) {
        alert('Por favor pega el JSON resultante del chatbot');
        return;
    }

    try {
        const eventoData = JSON.parse(jsonText);

        // Validar que tenga la estructura básica
        if (!eventoData.titulo || !eventoData.fecha_inicio) {
            alert('El JSON debe contener al menos "titulo" y "fecha_inicio"');
            return;
        }

        // Importar el evento
        importarEventosDesdeDatos(eventoData);

    } catch (error) {
        alert('Error al parsear el JSON: ' + error.message);
    }
}
