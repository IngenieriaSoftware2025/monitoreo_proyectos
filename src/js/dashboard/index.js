import { Dropdown, Modal } from "bootstrap";
import Swal from "sweetalert2";
import DataTable from "datatables.net-bs5";
import { validarFormulario } from "../funciones";
import { lenguaje } from "../lenguaje";
import Chart from 'chart.js/auto';

// Elementos del DOM
const BtnRefrescar = document.getElementById("BtnRefrescar");
const BtnNuevaAplicacion = document.getElementById("BtnNuevaAplicacion");
const BtnExportar = document.getElementById("BtnExportar");
const filtroUsuario = document.getElementById("filtroUsuario");
const filtroEstado = document.getElementById("filtroEstado");
const filtroSemaforo = document.getElementById("filtroSemaforo");
const fechaActualizacion = document.getElementById("fechaActualizacion");

// Modales
const modalDetalleApp = new Modal(document.getElementById('modalDetalleApp'));
const modalCambiarEstado = new Modal(document.getElementById('modalCambiarEstado'));
const FormCambiarEstado = document.getElementById("FormCambiarEstado");
const BtnConfirmarCambioEstado = document.getElementById("BtnConfirmarCambioEstado");

// Variables globales
let datosAplicaciones = [];
let chartTendencia = null;

// --- FUNCIONES DE SEMÁFORO ---
const obtenerClaseSemaforo = (semaforo) => {
    switch(semaforo) {
        case 'VERDE': return 'bg-success';
        case 'AMBAR': return 'bg-warning';
        case 'ROJO': return 'bg-danger';
        default: return 'bg-secondary';
    }
};

const obtenerIconoSemaforo = (semaforo) => {
    switch(semaforo) {
        case 'VERDE': return '<i class="bi bi-circle-fill text-success"></i>';
        case 'AMBAR': return '<i class="bi bi-circle-fill text-warning"></i>';
        case 'ROJO': return '<i class="bi bi-circle-fill text-danger"></i>';
        default: return '<i class="bi bi-circle text-secondary"></i>';
    }
};

// --- CARGA DE DATOS PARA FILTROS ---
const cargarUsuarios = async () => {
    const url = "/monitoreo_desarrolladores/busca_usuario_sistema";
    try {
        const respuesta = await fetch(url);
        const datos = await respuesta.json();
        filtroUsuario.innerHTML = '<option value="">Todos los usuarios</option>';
        if (datos.codigo === 1 && datos.data) {
            datos.data.forEach(usuario => {
                const nombreCompleto = usuario.nombre_completo || `${usuario.usu_nombre}`;
                filtroUsuario.innerHTML += `<option value="${usuario.usu_id}">${nombreCompleto}</option>`;
            });
        }
    } catch (error) {
        console.error("Error al cargar usuarios:", error);
    }
};

// --- DASHBOARD DATA ---
const cargarEstadisticas = async () => {
    const url = "/monitoreo_desarrolladores/metricas_generales";
    try {
        const respuesta = await fetch(url);
        const datos = await respuesta.json();
        
        if (datos.codigo === 1) {
            const stats = datos.data;
            document.getElementById('totalAplicaciones').textContent = stats.total_aplicaciones || 0;
            document.getElementById('enProgreso').textContent = stats.en_progreso || 0;
            document.getElementById('reportesHoy').textContent = stats.reportes_hoy || 0;
            document.getElementById('alertasCriticas').textContent = stats.alertas_criticas || 0;
        }
    } catch (error) {
        console.error("Error al cargar estadísticas:", error);
    }
};

const cargarAlertas = async () => {
    const url = "/monitoreo_desarrolladores/alertas_criticas";
    try {
        const respuesta = await fetch(url);
        const datos = await respuesta.json();
        const contenedorAlertas = document.getElementById('listaAlertas');
        
        if (datos.codigo === 1 && datos.data && datos.data.length > 0) {
            contenedorAlertas.innerHTML = '';
            datos.data.forEach(alerta => {
                const alertaHTML = `
                    <div class="alert alert-${alerta.nivel === 'ROJO' ? 'danger' : 'warning'} alert-dismissible fade show" role="alert">
                        <strong>${alerta.tipo}:</strong> ${alerta.mensaje}
                        <small class="text-muted d-block">${alerta.fecha || 'Ahora'}</small>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                contenedorAlertas.innerHTML += alertaHTML;
            });
        } else {
            contenedorAlertas.innerHTML = '<div class="alert alert-success">No hay alertas críticas en este momento.</div>';
        }
    } catch (error) {
        console.error("Error al cargar alertas:", error);
        document.getElementById('listaAlertas').innerHTML = '<div class="alert alert-info">Error al cargar alertas</div>';
    }
};

// --- DATATABLE ---
const datosTabla = new DataTable("#TableAplicaciones", {
    language: lenguaje,
    pageLength: 25,
    order: [[1, 'asc']],
    columns: [
        {
            title: "Semáforo",
            data: "semaforo",
            render: (data) => obtenerIconoSemaforo(data),
            width: "80px"
        },
        { title: "Aplicación", data: "apl_nombre" },
        { 
            title: "Estado", 
            data: "apl_estado",
            render: (data) => {
                const badges = {
                    'EN_PLANIFICACION': 'bg-info',
                    'EN_PROGRESO': 'bg-success',
                    'PAUSADO': 'bg-warning',
                    'CERRADO': 'bg-secondary'
                };
                return `<span class="badge ${badges[data] || 'bg-secondary'}">${data.replace('_', ' ')}</span>`;
            }
        },
        { 
            title: "Responsable", 
            data: "responsable_nombre",
            render: (data) => data || 'Sin asignar'
        },
        { 
            title: "% Avance", 
            data: "porcentaje_actual",
            render: (data) => {
                const porcentaje = data || 0;
                const color = porcentaje >= 80 ? 'bg-success' : porcentaje >= 50 ? 'bg-warning' : 'bg-danger';
                return `
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar ${color}" role="progressbar" style="width: ${porcentaje}%">
                            ${porcentaje}%
                        </div>
                    </div>
                `;
            }
        },
        { 
            title: "Último Avance", 
            data: "ultimo_avance",
            render: (data) => {
                if (!data) return '<span class="text-muted">Sin reportes</span>';
                const fecha = new Date(data);
                const hoy = new Date();
                const diffTime = Math.abs(hoy - fecha);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays === 0) return '<span class="text-success">Hoy</span>';
                if (diffDays === 1) return '<span class="text-warning">Ayer</span>';
                if (diffDays <= 3) return `<span class="text-warning">Hace ${diffDays} días</span>`;
                return `<span class="text-danger">Hace ${diffDays} días</span>`;
            }
        },
        {
            title: "Acciones",
            data: "apl_id",
            render: (data, type, row) => {
                const puedeModificar = true; // Aquí validarías permisos del usuario
                return `
                    <div class="btn-group" role="group">
                        <button class="btn btn-info btn-sm ver-detalle" data-id="${data}" title="Ver Detalle">
                            <i class="bi bi-eye"></i>
                        </button>
                        ${puedeModificar ? `
                            <button class="btn btn-warning btn-sm cambiar-estado" data-id="${data}" title="Cambiar Estado">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        ` : ''}
                        <button class="btn btn-success btn-sm ir-aplicacion" data-id="${data}" title="Ir a Aplicación">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </button>
                    </div>
                `;
            }
        }
    ]
});

const obtenerAplicaciones = async () => {
    const url = '/monitoreo_desarrolladores/busca_aplicacion';
    try {
        const respuesta = await fetch(url);
        const datos = await respuesta.json();

        if (datos.codigo === 1) {
            datosAplicaciones = datos.data || [];
            aplicarFiltros();
            actualizarFechaActualizacion();
        } else {
            console.log('Error del servidor:', datos.mensaje);
            Swal.fire("Error", "No se pudieron cargar las aplicaciones", "error");
        }
    } catch (error) {
        console.error('Error al obtener aplicaciones:', error);
        Swal.fire("Error de Conexión", "No se pudo conectar con el servidor", "error");
    }
};

// --- FILTROS ---
const aplicarFiltros = () => {
    let datosFiltrados = [...datosAplicaciones];
    
    const usuarioSeleccionado = filtroUsuario.value;
    const estadoSeleccionado = filtroEstado.value;
    const semaforoSeleccionado = filtroSemaforo.value;
    
    if (usuarioSeleccionado) {
        datosFiltrados = datosFiltrados.filter(app => app.apl_responsable == usuarioSeleccionado);
    }
    
    if (estadoSeleccionado) {
        datosFiltrados = datosFiltrados.filter(app => app.apl_estado === estadoSeleccionado);
    }
    
    if (semaforoSeleccionado) {
        datosFiltrados = datosFiltrados.filter(app => app.semaforo === semaforoSeleccionado);
    }
    
    datosTabla.clear().draw();
    if (datosFiltrados.length > 0) {
        datosTabla.rows.add(datosFiltrados).draw();
    }
};

// --- MODALES Y DETALLES ---
const verDetalleAplicacion = async (id) => {
    const url = `/monitoreo_desarrolladores/detalle_aplicacion?apl_id=${id}`;
    try {
        const respuesta = await fetch(url);
        const datos = await respuesta.json();
        
        if (datos.codigo === 1) {
            const app = datos.data;
            
            // Información general
            document.getElementById('infoGeneral').innerHTML = `
                <p><strong>Nombre:</strong> ${app.apl_nombre}</p>
                <p><strong>Estado:</strong> <span class="badge bg-primary">${app.apl_estado}</span></p>
                <p><strong>Responsable:</strong> ${app.responsable_nombre || 'Sin asignar'}</p>
                <p><strong>Fecha Inicio:</strong> ${app.apl_fecha_inicio}</p>
                <p><strong>Fecha Fin:</strong> ${app.apl_fecha_fin || 'No definida'}</p>
                <p><strong>Descripción:</strong> ${app.apl_descripcion || 'Sin descripción'}</p>
            `;
            
            // Último avance
            if (app.ultimo_avance) {
                document.getElementById('ultimoAvance').innerHTML = `
                    <p><strong>Fecha:</strong> ${app.ultimo_avance.ava_fecha}</p>
                    <p><strong>Porcentaje:</strong> ${app.ultimo_avance.ava_porcentaje}%</p>
                    <p><strong>Resumen:</strong> ${app.ultimo_avance.ava_resumen}</p>
                    ${app.ultimo_avance.ava_bloqueadores ? `<p><strong>Bloqueadores:</strong> ${app.ultimo_avance.ava_bloqueadores}</p>` : ''}
                `;
            } else {
                document.getElementById('ultimoAvance').innerHTML = '<p class="text-muted">Sin avances registrados</p>';
            }
            
            // Cargar gráfica de tendencia
            await cargarGraficaTendencia(id);
            
            modalDetalleApp.show();
        }
    } catch (error) {
        console.error("Error al obtener detalle:", error);
        Swal.fire("Error", "No se pudo cargar el detalle de la aplicación", "error");
    }
};

const cargarGraficaTendencia = async (appId) => {
    const url = `/monitoreo_desarrolladores/tendencia_avance?apl_id=${appId}&dias=30`;
    try {
        const respuesta = await fetch(url);
        const datos = await respuesta.json();
        
        if (chartTendencia) {
            chartTendencia.destroy();
        }
        
        const ctx = document.getElementById('graficaTendencia').getContext('2d');
        
        if (datos.codigo === 1 && datos.data.length > 0) {
            const labels = datos.data.map(item => item.ava_fecha);
            const porcentajes = datos.data.map(item => item.porcentaje_max);
            
            chartTendencia = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Porcentaje de Avance',
                        data: porcentajes,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        } else {
            chartTendencia = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Sin datos',
                        data: [],
                        borderColor: 'rgb(200, 200, 200)',
                        backgroundColor: 'rgba(200, 200, 200, 0.2)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'No hay datos de tendencia disponibles'
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error("Error al cargar tendencia:", error);
    }
};

const cambiarEstadoAplicacion = (id) => {
    document.getElementById('cambiarEstado_appId').value = id;
    modalCambiarEstado.show();
};

const confirmarCambioEstado = async () => {
    if (!validarFormulario(FormCambiarEstado)) {
        Swal.fire("Campos incompletos", "Debe llenar todos los campos requeridos", "warning");
        return;
    }

    BtnConfirmarCambioEstado.disabled = true;
    const body = new FormData(FormCambiarEstado);
    const url = '/monitoreo_desarrolladores/cambiar_estado_aplicacion';
    const config = { method: 'POST', body };

    try {
        const respuesta = await fetch(url, config);
        const datos = await respuesta.json();

        if (datos.codigo === 1) {
            Swal.fire("¡Estado Cambiado!", datos.mensaje, "success");
            modalCambiarEstado.hide();
            FormCambiarEstado.reset();
            obtenerAplicaciones();
        } else {
            Swal.fire("Error", datos.mensaje, "error");
        }
    } catch (error) {
        console.log(error);
        Swal.fire("Error de Conexión", "No se pudo conectar con el servidor", "error");
    }
    BtnConfirmarCambioEstado.disabled = false;
};

// --- UTILIDADES ---
const actualizarFechaActualizacion = () => {
    const ahora = new Date();
    fechaActualizacion.textContent = `Actualizado: ${ahora.toLocaleString()}`;
};

const exportarDatos = () => {
    // Implementar exportación a Excel/CSV
    Swal.fire("Funcionalidad en desarrollo", "La exportación estará disponible próximamente", "info");
};

const irANuevaAplicacion = () => {
    window.location.href = '/monitoreo_desarrolladores/aplicaciones';
};

const irAAplicacion = (id) => {
    window.location.href = `/monitoreo_desarrolladores/aplicaciones?id=${id}`;
};

// --- EVENTOS ---
BtnRefrescar.addEventListener("click", () => {
    obtenerAplicaciones();
    cargarEstadisticas();
    cargarAlertas();
});

BtnNuevaAplicacion.addEventListener("click", irANuevaAplicacion);
BtnExportar.addEventListener("click", exportarDatos);
BtnConfirmarCambioEstado.addEventListener("click", confirmarCambioEstado);

filtroUsuario.addEventListener("change", aplicarFiltros);
filtroEstado.addEventListener("change", aplicarFiltros);
filtroSemaforo.addEventListener("change", aplicarFiltros);

datosTabla.on("click", ".ver-detalle", (e) => verDetalleAplicacion(e.currentTarget.dataset.id));
datosTabla.on("click", ".cambiar-estado", (e) => cambiarEstadoAplicacion(e.currentTarget.dataset.id));
datosTabla.on("click", ".ir-aplicacion", (e) => irAAplicacion(e.currentTarget.dataset.id));

// --- INICIALIZACIÓN ---
document.addEventListener("DOMContentLoaded", () => {
    cargarUsuarios();
    obtenerAplicaciones();
    cargarEstadisticas();
    cargarAlertas();
    
    // Auto-refresh cada 5 minutos
    setInterval(() => {
        obtenerAplicaciones();
        cargarEstadisticas();
        cargarAlertas();
    }, 300000);
});AAplicacion = (id) => {
    window.location.href = `/sistema_seguimiento/aplicaciones?id=${id}`;
};

// --- EVENTOS ---
BtnRefrescar.addEventListener("click", () => {
    obtenerAplicaciones();
    cargarEstadisticas();
    cargarAlertas();
});

BtnNuevaAplicacion.addEventListener("click", irANuevaAplicacion);
BtnExportar.addEventListener("click", exportarDatos);
BtnConfirmarCambioEstado.addEventListener("click", confirmarCambioEstado);

filtroUsuario.addEventListener("change", aplicarFiltros);
filtroEstado.addEventListener("change", aplicarFiltros);
filtroSemaforo.addEventListener("change", aplicarFiltros);

datosTabla.on("click", ".ver-detalle", (e) => verDetalleAplicacion(e.currentTarget.dataset.id));
datosTabla.on("click", ".cambiar-estado", (e) => cambiarEstadoAplicacion(e.currentTarget.dataset.id));
datosTabla.on("click", ".ir-aplicacion", (e) => irAAplicacion(e.currentTarget.dataset.id));

// --- INICIALIZACIÓN ---
document.addEventListener("DOMContentLoaded", () => {
    cargarUsuarios();
    obtenerAplicaciones();
    cargarEstadisticas();
    cargarAlertas();
    
    // Auto-refresh cada 5 minutos
    setInterval(() => {
        obtenerAplicaciones();
        cargarEstadisticas();
        cargarAlertas();
    }, 300000);
});