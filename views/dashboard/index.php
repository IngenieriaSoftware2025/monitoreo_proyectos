<div class="container-fluid mt-4">
    <!-- Header del Dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Dashboard - Seguimiento de Aplicaciones</h4>
                        <div>
                            <span class="badge bg-light text-dark" id="fechaActualizacion"></span>
                            <button type="button" id="BtnRefrescar" class="btn btn-outline-light btn-sm ms-2">
                                <i class="bi bi-arrow-clockwise"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros y Controles -->
    <div class="row mb-4">
        <div class="col-md-4">
            <label for="filtroUsuario" class="form-label">Filtrar por Usuario</label>
            <select id="filtroUsuario" class="form-select">
                <option value="">Todos los usuarios</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="filtroEstado" class="form-label">Filtrar por Estado</label>
            <select id="filtroEstado" class="form-select">
                <option value="">Todos los estados</option>
                <option value="EN_PLANIFICACION">En Planificación</option>
                <option value="EN_PROGRESO">En Progreso</option>
                <option value="PAUSADO">Pausado</option>
                <option value="CERRADO">Cerrado</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="filtroSemaforo" class="form-label">Filtrar por Semáforo</label>
            <select id="filtroSemaforo" class="form-select">
                <option value="">Todos</option>
                <option value="VERDE">Verde</option>
                <option value="AMBAR">Ámbar</option>
                <option value="ROJO">Rojo</option>
            </select>
        </div>
    </div>

    <!-- Tarjetas de Estadísticas -->
    <div class="row mb-4" id="tarjetasEstadisticas">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Aplicaciones</h6>
                            <h2 class="mb-0" id="totalAplicaciones">0</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-laptop fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">En Progreso</h6>
                            <h2 class="mb-0" id="enProgreso">0</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-play-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Reportes Hoy</h6>
                            <h2 class="mb-0" id="reportesHoy">0</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-file-text fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Alertas Críticas</h6>
                            <h2 class="mb-0" id="alertasCriticas">0</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-exclamation-triangle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas Críticas -->
    <div class="row mb-4" id="seccionAlertas">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Alertas Críticas
                    </h5>
                </div>
                <div class="card-body">
                    <div id="listaAlertas" class="alert-container">
                        <!-- Las alertas se cargarán aquí dinámicamente -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Aplicaciones -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-table me-2"></i>
                            Aplicaciones en Seguimiento
                        </h5>
                        <div>
                            <button type="button" id="BtnNuevaAplicacion" class="btn btn-success btn-sm">
                                <i class="bi bi-plus-circle me-1"></i> Nueva Aplicación
                            </button>
                            <button type="button" id="BtnExportar" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-download me-1"></i> Exportar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="TableAplicaciones" class="table table-striped table-hover table-bordered w-100"></table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Detalle de Aplicación -->
<div class="modal fade" id="modalDetalleApp" tabindex="-1" aria-labelledby="modalDetalleAppLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalleAppLabel">Detalle de Aplicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información General</h6>
                        <div id="infoGeneral"></div>
                    </div>
                    <div class="col-md-6">
                        <h6>Último Avance</h6>
                        <div id="ultimoAvance"></div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Gráfica de Tendencia (Últimos 30 días)</h6>
                        <canvas id="graficaTendencia" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" id="BtnVerDetalleCompleto" class="btn btn-primary">Ver Detalle Completo</button>
            </div>
        </div>
    </div>
</div>

    <!-- Modal para Cambiar Estado -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1" aria-labelledby="modalCambiarEstadoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCambiarEstadoLabel">Cambiar Estado de Aplicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="FormCambiarEstado">
                    <input type="hidden" id="cambiarEstado_appId" name="apl_id">
                    
                    <div class="mb-3">
                        <label for="nuevoEstado" class="form-label">Nuevo Estado</label>
                        <select id="nuevoEstado" name="nuevo_estado" class="form-select" required>
                            <option value="">Seleccione un estado...</option>
                            <option value="EN_PLANIFICACION">En Planificación</option>
                            <option value="EN_PROGRESO">En Progreso</option>
                            <option value="PAUSADO">Pausado</option>
                            <option value="CERRADO">Cerrado</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motivoCambio" class="form-label">Motivo del Cambio</label>
                        <textarea id="motivoCambio" name="motivo" class="form-control" rows="3" placeholder="Explique el motivo del cambio de estado..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="BtnConfirmarCambioEstado" class="btn btn-primary">Confirmar Cambio</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('build/js/dashboard/index.js') ?>"></script>