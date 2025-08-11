<?php
require_once __DIR__ . '/../includes/app.php';

use MVC\Router;
use Controllers\AppController;

// Importa mis clases de Controladores
use Controllers\LoginController;
use Controllers\RegistroController;
use Controllers\AplicacionController;
use Controllers\AvanceDiarioController;
use Controllers\ComentarioController;
use Controllers\VisitaController;
use Controllers\UsuarioController;
use Controllers\RolController;
use Controllers\InactividadDiariaController;
use Controllers\DashboardController;
use Controllers\ReportesController;
use Controllers\AlertasController;

$router = new Router();
$router->setBaseURL('/' . $_ENV['APP_NAME']);

$router->get('/', [AppController::class, 'index']);

//Rutas para Login
$router->get('/login', [LoginController::class, 'index']);
$router->post('/login', [LoginController::class, 'login']);
$router->get('/inicio', [LoginController::class, 'renderInicio']);
$router->get('/logout', [LoginController::class, 'logout']);

// Rutas para el registro de usuario
$router->get('/registro', [RegistroController::class, 'mostrarPaginaRegistro']);
$router->get('/busca_usuario', [RegistroController::class, 'buscaUsuario']);
$router->post('/guarda_usuario', [RegistroController::class, 'guardarUsuario']);
$router->post('/modifica_usuario', [RegistroController::class, 'modificaUsuario']);
$router->post('/elimina_usuario', [RegistroController::class, 'eliminaUsuario']);

// // ================================
// DASHBOARD
// ================================
$router->get('/dashboard', [DashboardController::class, 'renderizarPagina']);
$router->get('/dashboard_ejecutivo', [DashboardController::class, 'obtenerDashboardEjecutivo']);
$router->get('/dashboard_desarrollador', [DashboardController::class, 'obtenerDashboardDesarrollador']);

// ================================
// APLICACIONES
// ================================
$router->get('/aplicaciones', [AplicacionController::class, 'renderizarPagina']);
$router->get('/busca_aplicacion', [AplicacionController::class, 'buscarAPI']);
$router->post('/guarda_aplicacion', [AplicacionController::class, 'guardarAPI']);
$router->post('/modifica_aplicacion', [AplicacionController::class, 'modificarAPI']);
$router->post('/elimina_aplicacion', [AplicacionController::class, 'eliminarAPI']);
$router->get('/detalle_aplicacion', [AplicacionController::class, 'obtenerDetalleAPI']);
$router->post('/cambiar_estado_aplicacion', [AplicacionController::class, 'cambiarEstadoAPI']);
$router->get('/semaforo_aplicacion', [AplicacionController::class, 'obtenerSemaforoAPI']);

// ================================
// AVANCES DIARIOS
// ================================
$router->get('/avances', [AvanceDiarioController::class, 'renderizarPagina']);
$router->get('/busca_avance', [AvanceDiarioController::class, 'buscarAPI']);
$router->post('/guarda_avance', [AvanceDiarioController::class, 'guardarAPI']);
$router->post('/modifica_avance', [AvanceDiarioController::class, 'modificarAPI']);
$router->post('/elimina_avance', [AvanceDiarioController::class, 'eliminarAPI']);
$router->get('/puede_editar_avance', [AvanceDiarioController::class, 'puedeEditarAPI']);
$router->get('/ultimo_avance', [AvanceDiarioController::class, 'obtenerUltimoAPI']);
$router->get('/tendencia_avance', [AvanceDiarioController::class, 'obtenerTendenciaAPI']);
$router->get('/apps_sin_reporte', [AvanceDiarioController::class, 'obtenerSinReporteAPI']);
$router->get('/bloqueadores_criticos', [AvanceDiarioController::class, 'obtenerBloqueadoresCriticosAPI']);

// ================================
// COMENTARIOS
// ================================
$router->get('/comentarios', [ComentarioController::class, 'renderizarPagina']);
$router->get('/busca_comentario', [ComentarioController::class, 'buscarAPI']);
$router->post('/guarda_comentario', [ComentarioController::class, 'guardarAPI']);
$router->post('/modifica_comentario', [ComentarioController::class, 'modificarAPI']);
$router->post('/elimina_comentario', [ComentarioController::class, 'eliminarAPI']);
$router->post('/marcar_leido_comentario', [ComentarioController::class, 'marcarLeidoAPI']);
$router->get('/menciones_usuario', [ComentarioController::class, 'obtenerMencionesAPI']);
$router->get('/no_leidos_usuario', [ComentarioController::class, 'obtenerNoLeidosAPI']);
$router->post('/marcar_multiple_leido', [ComentarioController::class, 'marcarMultipleLeidoAPI']);

// ================================
// VISITAS
// ================================
$router->get('/visitas', [VisitaController::class, 'renderizarPagina']);
$router->get('/busca_visita', [VisitaController::class, 'buscarAPI']);
$router->post('/guarda_visita', [VisitaController::class, 'guardarAPI']);
$router->post('/modifica_visita', [VisitaController::class, 'modificarAPI']);
$router->post('/elimina_visita', [VisitaController::class, 'eliminarAPI']);
$router->get('/visitas_sin_conformidad', [VisitaController::class, 'obtenerSinConformidadAPI']);
$router->post('/marcar_visita_atendida', [VisitaController::class, 'marcarAtendidaAPI']);
$router->get('/reporte_conformidad', [VisitaController::class, 'obtenerReporteConformidadAPI']);

// ================================
// USUARIOS
// ================================
$router->get('/usuarios', [UsuarioController::class, 'renderizarPagina']);
$router->get('/busca_usuario_sistema', [UsuarioController::class, 'buscarAPI']);
$router->post('/guarda_usuario_sistema', [UsuarioController::class, 'guardarAPI']);
$router->post('/modifica_usuario_sistema', [UsuarioController::class, 'modificarAPI']);
$router->post('/elimina_usuario_sistema', [UsuarioController::class, 'eliminarAPI']);
$router->post('/activar_usuario', [UsuarioController::class, 'activarAPI']);
$router->post('/desactivar_usuario', [UsuarioController::class, 'desactivarAPI']);
$router->get('/estadisticas_usuario', [UsuarioController::class, 'obtenerEstadisticasAPI']);

// ================================
// ROLES
// ================================
$router->get('/roles', [RolController::class, 'renderizarPagina']);
$router->get('/busca_rol', [RolController::class, 'buscarAPI']);
$router->post('/guarda_rol', [RolController::class, 'guardarAPI']);
$router->post('/modifica_rol', [RolController::class, 'modificarAPI']);
$router->post('/elimina_rol', [RolController::class, 'eliminarAPI']);
$router->get('/roles_select', [RolController::class, 'obtenerParaSelectAPI']);

// ================================
// INACTIVIDAD DIARIA
// ================================
$router->get('/inactividades', [InactividadDiariaController::class, 'renderizarPagina']);
$router->get('/busca_inactividad', [InactividadDiariaController::class, 'buscarAPI']);
$router->post('/guarda_inactividad', [InactividadDiariaController::class, 'guardarAPI']);
$router->post('/modifica_inactividad', [InactividadDiariaController::class, 'modificarAPI']);
$router->post('/elimina_inactividad', [InactividadDiariaController::class, 'eliminarAPI']);
$router->get('/patrones_inactividad', [InactividadDiariaController::class, 'obtenerPatronesAPI']);

// ================================
// REPORTES
// ================================
$router->get('/reportes', [ReportesController::class, 'renderizarPagina']);
$router->get('/reporte_cumplimiento', [ReportesController::class, 'obtenerCumplimientoReportesAPI']);
$router->get('/reporte_tendencias', [ReportesController::class, 'obtenerTendenciasAPI']);
$router->get('/reporte_productividad', [ReportesController::class, 'obtenerProductividadAPI']);
$router->get('/reporte_bloqueadores', [ReportesController::class, 'obtenerBloqueadoresAPI']);
$router->get('/reporte_comentarios', [ReportesController::class, 'obtenerEngagementComentariosAPI']);

// ================================
// ALERTAS
// ================================
$router->get('/alertas', [AlertasController::class, 'renderizarPagina']);
$router->get('/busca_alertas', [AlertasController::class, 'obtenerAlertasAPI']);
$router->get('/alertas_criticas', [AlertasController::class, 'obtenerCriticasAPI']);
$router->get('/alertas_sin_reporte', [AlertasController::class, 'obtenerSinReporteAPI']);
$router->get('/alertas_bloqueadores', [AlertasController::class, 'obtenerBloqueadoresAPI']);
$router->get('/alertas_conformidad', [AlertasController::class, 'obtenerConformidadAPI']);
$router->post('/marcar_alerta_atendida', [AlertasController::class, 'marcarAtendidaAPI']);

// ================================
// MÉTRICAS Y ESTADÍSTICAS
// ================================
$router->get('/metricas_generales', [DashboardController::class, 'obtenerMetricasGeneralesAPI']);
$router->get('/estadisticas_aplicacion', [AplicacionController::class, 'obtenerEstadisticasAPI']);
$router->get('/timeline_aplicacion', [AplicacionController::class, 'obtenerTimelineAPI']);

// Comprueba y valida las rutas, que existan y les asigna las funciones del Controlador
$router->comprobarRutas();