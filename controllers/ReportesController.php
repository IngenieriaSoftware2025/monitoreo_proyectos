<?php

namespace Controllers;

use Exception;
use Model\ActiveRecord;
use Model\Aplicacion;
use Model\Usuario;
use Model\AvanceDiario;
use Model\Comentario;
use Model\Visita;
use Model\InactividadDiaria;
use MVC\Router;

class ReportesController extends ActiveRecord {

    public static function renderizarPagina(Router $router) {
        $router->render('reportes/index', []);
    }

    // Reporte de cumplimiento de reportes diarios
    public static function obtenerCumplimientoReportesAPI() {
        try {
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
            $usuario_id = filter_var($_GET['usuario_id'], FILTER_SANITIZE_NUMBER_INT) ?: null;

            $query = "
                SELECT 
                    u.usu_id,
                    u.usu_nombre,
                    u.usu_grado,
                    r.rol_nombre,
                    COUNT(DISTINCT av.ava_fecha) as dias_con_reporte,
                    COUNT(DISTINCT CASE 
                        WHEN a.apl_estado = 'EN_PROGRESO' 
                        THEN DATE(av.ava_fecha) 
                    END) as dias_debio_reportar,
                    COUNT(DISTINCT a.apl_id) as aplicaciones_asignadas,
                    AVG(av.ava_porcentaje) as porcentaje_promedio,
                    MAX(av.ava_fecha) as ultimo_reporte,
                    COUNT(CASE WHEN av.ava_bloqueadores IS NOT NULL AND av.ava_bloqueadores != '' THEN 1 END) as reportes_con_bloqueadores,
                    COUNT(CASE WHEN av.ava_justificacion IS NOT NULL AND av.ava_justificacion != '' THEN 1 END) as reportes_con_justificacion
                FROM usuario u
                LEFT JOIN rol r ON u.usu_rol_id = r.rol_id
                LEFT JOIN aplicacion a ON u.usu_id = a.apl_responsable AND a.apl_situacion = 1
                LEFT JOIN avance_diario av ON (a.apl_id = av.ava_apl_id AND u.usu_id = av.ava_usu_id)
                    AND av.ava_fecha BETWEEN ? AND ? AND av.ava_situacion = 1
                WHERE u.usu_situacion = 1 AND u.usu_activo = true
            ";

            $params = [$fecha_desde, $fecha_hasta];

            if ($usuario_id) {
                $query .= " AND u.usu_id = ?";
                $params[] = $usuario_id;
            }

            $query .= "
                GROUP BY u.usu_id, u.usu_nombre, u.usu_grado, r.rol_nombre
                ORDER BY u.usu_nombre
            ";

            $data = self::fetchArray($query, $params);

            // Calcular métricas adicionales
            $dias_habiles = self::calcularDiasHabiles($fecha_desde, $fecha_hasta);
            
            foreach ($data as &$usuario) {
                $dias_esperados = $usuario['aplicaciones_asignadas'] > 0 ? $dias_habiles : 0;
                $usuario['dias_habiles_periodo'] = $dias_habiles;
                $usuario['porcentaje_cumplimiento'] = $dias_esperados > 0 
                    ? round(($usuario['dias_con_reporte'] / $dias_esperados) * 100, 2) 
                    : 0;
                $usuario['porcentaje_promedio'] = round($usuario['porcentaje_promedio'] ?? 0, 2);
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Reporte de cumplimiento obtenido correctamente',
                'data' => $data,
                'parametros' => [
                    'fecha_desde' => $fecha_desde,
                    'fecha_hasta' => $fecha_hasta,
                    'dias_habiles' => $dias_habiles
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener reporte de cumplimiento',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Reporte de velocidad de avance por aplicación
    public static function obtenerVelocidadAvanceAPI() {
        try {
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
            $aplicacion_id = filter_var($_GET['aplicacion_id'], FILTER_SANITIZE_NUMBER_INT) ?: null;

            $query = "
                SELECT 
                    a.apl_id,
                    a.apl_nombre,
                    a.apl_estado,
                    a.apl_porcentaje_objetivo,
                    u.usu_nombre as responsable_nombre,
                    MIN(av.ava_porcentaje) as porcentaje_inicial,
                    MAX(av.ava_porcentaje) as porcentaje_final,
                    MAX(av.ava_porcentaje) - MIN(av.ava_porcentaje) as avance_total,
                    COUNT(DISTINCT av.ava_fecha) as dias_trabajados,
                    COUNT(av.ava_id) as total_reportes,
                    AVG(av.ava_porcentaje) as porcentaje_promedio,
                    MIN(av.ava_fecha) as fecha_inicio_periodo,
                    MAX(av.ava_fecha) as fecha_fin_periodo,
                    COUNT(CASE WHEN av.ava_bloqueadores IS NOT NULL AND av.ava_bloqueadores != '' THEN 1 END) as dias_con_bloqueadores
                FROM aplicacion a
                LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                LEFT JOIN avance_diario av ON a.apl_id = av.ava_apl_id
                    AND av.ava_fecha BETWEEN ? AND ? AND av.ava_situacion = 1
                WHERE a.apl_situacion = 1
            ";

            $params = [$fecha_desde, $fecha_hasta];

            if ($aplicacion_id) {
                $query .= " AND a.apl_id = ?";
                $params[] = $aplicacion_id;
            }

            $query .= "
                GROUP BY a.apl_id, a.apl_nombre, a.apl_estado, a.apl_porcentaje_objetivo, u.usu_nombre
                HAVING total_reportes > 0
                ORDER BY avance_total DESC
            ";

            $data = self::fetchArray($query, $params);

            // Calcular métricas adicionales
            foreach ($data as &$app) {
                $app['velocidad_diaria'] = $app['dias_trabajados'] > 0 
                    ? round($app['avance_total'] / $app['dias_trabajados'], 2) 
                    : 0;
                
                $app['porcentaje_bloqueadores'] = $app['total_reportes'] > 0 
                    ? round(($app['dias_con_bloqueadores'] / $app['total_reportes']) * 100, 2) 
                    : 0;
                
                $app['porcentaje_promedio'] = round($app['porcentaje_promedio'] ?? 0, 2);
                
                // Calcular ETA (tiempo estimado para completar)
                if ($app['velocidad_diaria'] > 0 && $app['apl_porcentaje_objetivo']) {
                    $porcentaje_restante = $app['apl_porcentaje_objetivo'] - $app['porcentaje_final'];
                    if ($porcentaje_restante > 0) {
                        $dias_estimados = ceil($porcentaje_restante / $app['velocidad_diaria']);
                        $app['eta_dias'] = $dias_estimados;
                        $app['eta_fecha'] = date('Y-m-d', strtotime("+$dias_estimados days"));
                    } else {
                        $app['eta_dias'] = 0;
                        $app['eta_fecha'] = 'Completado';
                    }
                } else {
                    $app['eta_dias'] = null;
                    $app['eta_fecha'] = 'No calculable';
                }
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Reporte de velocidad de avance obtenido correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener reporte de velocidad',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Reporte de conformidad de visitas
    public static function obtenerConformidadVisitasAPI() {
        try {
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            $data = Visita::generarReporteConformidad($fecha_desde, $fecha_hasta);

            // Agregar estadísticas generales
            $estadisticas_generales = Visita::obtenerEstadisticasGenerales($fecha_desde, $fecha_hasta);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Reporte de conformidad obtenido correctamente',
                'data' => $data,
                'estadisticas_generales' => $estadisticas_generales
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener reporte de conformidad',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Reporte de actividad de comentarios
    public static function obtenerActividadComentariosAPI() {
        try {
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            $data = Comentario::generarResumenActividad($fecha_desde, $fecha_hasta);

            // Agregar top usuarios más activos
            $usuarios_activos = self::fetchArray("
                SELECT 
                    u.usu_nombre,
                    u.usu_grado,
                    COUNT(c.com_id) as total_comentarios,
                    COUNT(DISTINCT c.com_apl_id) as aplicaciones_comentadas,
                    AVG(LENGTH(c.com_texto)) as longitud_promedio,
                    MAX(c.com_creado_en) as ultimo_comentario
                FROM usuario u
                JOIN comentario c ON u.usu_id = c.com_autor_id
                WHERE c.com_creado_en BETWEEN ? AND ? AND c.com_situacion = 1
                GROUP BY u.usu_id, u.usu_nombre, u.usu_grado
                ORDER BY total_comentarios DESC
                LIMIT 10
            ", [$fecha_desde, $fecha_hasta]);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Reporte de actividad de comentarios obtenido correctamente',
                'data' => [
                    'por_aplicacion' => $data,
                    'usuarios_mas_activos' => $usuarios_activos
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener reporte de actividad de comentarios',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Reporte de patrones de inactividad
    public static function obtenerPatronesInactividadAPI() {
        try {
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            // Estadísticas por tipo de inactividad
            $por_tipo = InactividadDiaria::obtenerEstadisticasGenerales($fecha_desde, $fecha_hasta);

            // Usuarios con más días de inactividad
            $usuarios_inactivos = self::fetchArray("
                SELECT 
                    u.usu_nombre,
                    u.usu_grado,
                    r.rol_nombre,
                    COUNT(DISTINCT ina.ina_fecha) as dias_inactivos,
                    COUNT(DISTINCT ina.ina_apl_id) as aplicaciones_afectadas,
                    GROUP_CONCAT(DISTINCT ina.ina_tipo) as tipos_inactividad,
                    MAX(ina.ina_fecha) as ultima_inactividad
                FROM usuario u
                LEFT JOIN rol r ON u.usu_rol_id = r.rol_id
                JOIN inactividad_diaria ina ON u.usu_id = ina.ina_usu_id
                WHERE ina.ina_fecha BETWEEN ? AND ? AND ina.ina_situacion = 1
                GROUP BY u.usu_id, u.usu_nombre, u.usu_grado, r.rol_nombre
                ORDER BY dias_inactivos DESC
                LIMIT 20
            ", [$fecha_desde, $fecha_hasta]);

            // Aplicaciones más afectadas por inactividad
            $apps_afectadas = self::fetchArray("
                SELECT 
                    a.apl_nombre,
                    a.apl_estado,
                    u.usu_nombre as responsable_nombre,
                    COUNT(DISTINCT ina.ina_fecha) as dias_inactividad,
                    COUNT(DISTINCT ina.ina_usu_id) as usuarios_inactivos,
                    GROUP_CONCAT(DISTINCT ina.ina_tipo) as tipos_inactividad
                FROM aplicacion a
                LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                JOIN inactividad_diaria ina ON a.apl_id = ina.ina_apl_id
                WHERE ina.ina_fecha BETWEEN ? AND ? AND ina.ina_situacion = 1
                GROUP BY a.apl_id, a.apl_nombre, a.apl_estado, u.usu_nombre
                ORDER BY dias_inactividad DESC
                LIMIT 15
            ", [$fecha_desde, $fecha_hasta]);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Reporte de patrones de inactividad obtenido correctamente',
                'data' => [
                    'por_tipo' => $por_tipo,
                    'usuarios_mas_inactivos' => $usuarios_inactivos,
                    'aplicaciones_mas_afectadas' => $apps_afectadas
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener reporte de patrones de inactividad',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Reporte ejecutivo - resumen general
    public static function obtenerReporteEjecutivoAPI() {
        try {
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            // KPIs principales
            $kpis = self::calcularKPIsPrincipales($fecha_desde, $fecha_hasta);

            // Top 5 aplicaciones por velocidad
            $top_velocidad = self::fetchArray("
                SELECT 
                    a.apl_nombre,
                    u.usu_nombre as responsable,
                    (MAX(av.ava_porcentaje) - MIN(av.ava_porcentaje)) / COUNT(DISTINCT av.ava_fecha) as velocidad_diaria,
                    MAX(av.ava_porcentaje) as porcentaje_actual
                FROM aplicacion a
                LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                JOIN avance_diario av ON a.apl_id = av.ava_apl_id
                WHERE av.ava_fecha BETWEEN ? AND ? AND av.ava_situacion = 1
                GROUP BY a.apl_id, a.apl_nombre, u.usu_nombre
                HAVING COUNT(DISTINCT av.ava_fecha) > 5
                ORDER BY velocidad_diaria DESC
                LIMIT 5
            ", [$fecha_desde, $fecha_hasta]);

            // Top 5 aplicaciones que requieren atención
            $requieren_atencion = self::fetchArray("
                SELECT 
                    a.apl_nombre,
                    u.usu_nombre as responsable,
                    COALESCE(ultimo_avance.ava_fecha, a.apl_fecha_inicio) as ultimo_reporte,
                    DATEDIFF(CURDATE(), COALESCE(ultimo_avance.ava_fecha, a.apl_fecha_inicio)) as dias_sin_reporte,
                    bloqueadores.total_bloqueadores,
                    visitas_nc.total_no_conformes
                FROM aplicacion a
                LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                LEFT JOIN (
                    SELECT ava_apl_id, MAX(ava_fecha) as ava_fecha
                    FROM avance_diario 
                    WHERE ava_situacion = 1
                    GROUP BY ava_apl_id
                ) ultimo_avance ON a.apl_id = ultimo_avance.ava_apl_id
                LEFT JOIN (
                    SELECT ava_apl_id, COUNT(*) as total_bloqueadores
                    FROM avance_diario 
                    WHERE ava_bloqueadores IS NOT NULL AND ava_bloqueadores != ''
                    AND ava_fecha BETWEEN ? AND ? AND ava_situacion = 1
                    GROUP BY ava_apl_id
                ) bloqueadores ON a.apl_id = bloqueadores.ava_apl_id
                LEFT JOIN (
                    SELECT vis_apl_id, COUNT(*) as total_no_conformes
                    FROM visita 
                    WHERE vis_conformidad = 0 
                    AND vis_fecha BETWEEN ? AND ? AND vis_situacion = 1
                    GROUP BY vis_apl_id
                ) visitas_nc ON a.apl_id = visitas_nc.vis_apl_id
                WHERE a.apl_estado IN ('EN_PROGRESO', 'EN_PLANIFICACION') AND a.apl_situacion = 1
                AND (
                    DATEDIFF(CURDATE(), COALESCE(ultimo_avance.ava_fecha, a.apl_fecha_inicio)) >= 2
                    OR bloqueadores.total_bloqueadores > 0
                    OR visitas_nc.total_no_conformes > 0
                )
                ORDER BY dias_sin_reporte DESC, bloqueadores.total_bloqueadores DESC
                LIMIT 5
            ", [$fecha_desde, $fecha_hasta, $fecha_desde, $fecha_hasta]);

            // Tendencia de productividad (últimos 7 días)
            $tendencia_productividad = self::fetchArray("
                SELECT 
                    av.ava_fecha,
                    COUNT(DISTINCT av.ava_apl_id) as apps_reportaron,
                    COUNT(av.ava_id) as total_reportes,
                    AVG(av.ava_porcentaje) as porcentaje_promedio,
                    COUNT(CASE WHEN av.ava_bloqueadores IS NOT NULL AND av.ava_bloqueadores != '' THEN 1 END) as reportes_con_bloqueadores
                FROM avance_diario av
                WHERE av.ava_fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND av.ava_situacion = 1
                GROUP BY av.ava_fecha
                ORDER BY av.ava_fecha
            ");

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Reporte ejecutivo obtenido correctamente',
                'data' => [
                    'kpis' => $kpis,
                    'top_velocidad' => $top_velocidad,
                    'requieren_atencion' => $requieren_atencion,
                    'tendencia_productividad' => $tendencia_productividad,
                    'parametros' => [
                        'fecha_desde' => $fecha_desde,
                        'fecha_hasta' => $fecha_hasta,
                        'fecha_generacion' => date('Y-m-d H:i:s')
                    ]
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener reporte ejecutivo',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Reporte de lead time de bloqueadores
    public static function obtenerLeadTimeBloqueadoresAPI() {
        try {
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            $data = self::fetchArray("
                SELECT 
                    a.apl_nombre,
                    u.usu_nombre as responsable,
                    av1.ava_fecha as fecha_inicio_bloqueo,
                    av1.ava_bloqueadores as descripcion_bloqueador,
                    MIN(av2.ava_fecha) as fecha_resolucion,
                    DATEDIFF(MIN(av2.ava_fecha), av1.ava_fecha) as dias_resolucion,
                    av1.ava_porcentaje as porcentaje_al_bloquear,
                    MIN(av2.ava_porcentaje) as porcentaje_al_resolver
                FROM avance_diario av1
                JOIN aplicacion a ON av1.ava_apl_id = a.apl_id
                LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                LEFT JOIN avance_diario av2 ON (
                    av1.ava_apl_id = av2.ava_apl_id 
                    AND av2.ava_fecha > av1.ava_fecha
                    AND (av2.ava_bloqueadores IS NULL OR av2.ava_bloqueadores = '')
                    AND av2.ava_situacion = 1
                )
                WHERE av1.ava_bloqueadores IS NOT NULL 
                AND av1.ava_bloqueadores != ''
                AND av1.ava_fecha BETWEEN ? AND ?
                AND av1.ava_situacion = 1
                GROUP BY av1.ava_id, a.apl_nombre, u.usu_nombre, av1.ava_fecha, av1.ava_bloqueadores, av1.ava_porcentaje
                HAVING fecha_resolucion IS NOT NULL
                ORDER BY dias_resolucion DESC
            ", [$fecha_desde, $fecha_hasta]);

            // Calcular estadísticas
            $estadisticas = [
                'total_bloqueadores' => count($data),
                'promedio_dias_resolucion' => 0,
                'mediana_dias_resolucion' => 0,
                'bloqueador_mas_largo' => null,
                'bloqueadores_sin_resolver' => 0
            ];

            if (!empty($data)) {
                $dias_resolucion = array_column($data, 'dias_resolucion');
                $estadisticas['promedio_dias_resolucion'] = round(array_sum($dias_resolucion) / count($dias_resolucion), 2);
                
                sort($dias_resolucion);
                $count = count($dias_resolucion);
                $estadisticas['mediana_dias_resolucion'] = $count % 2 == 0 
                    ? ($dias_resolucion[$count/2 - 1] + $dias_resolucion[$count/2]) / 2
                    : $dias_resolucion[floor($count/2)];
                
                $estadisticas['bloqueador_mas_largo'] = max($dias_resolucion);
            }

            // Bloqueadores sin resolver
            $sin_resolver = self::fetchArray("
                SELECT COUNT(*) as total
                FROM avance_diario av1
                WHERE av1.ava_bloqueadores IS NOT NULL 
                AND av1.ava_bloqueadores != ''
                AND av1.ava_fecha BETWEEN ? AND ?
                AND av1.ava_situacion = 1
                AND NOT EXISTS (
                    SELECT 1 FROM avance_diario av2 
                    WHERE av2.ava_apl_id = av1.ava_apl_id 
                    AND av2.ava_fecha > av1.ava_fecha
                    AND (av2.ava_bloqueadores IS NULL OR av2.ava_bloqueadores = '')
                    AND av2.ava_situacion = 1
                )
            ", [$fecha_desde, $fecha_hasta]);
            
            $estadisticas['bloqueadores_sin_resolver'] = $sin_resolver[0]['total'] ?? 0;

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Reporte de lead time de bloqueadores obtenido correctamente',
                'data' => $data,
                'estadisticas' => $estadisticas
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener reporte de lead time de bloqueadores',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Función auxiliar para calcular días hábiles
    private static function calcularDiasHabiles($fecha_desde, $fecha_hasta) {
        $dias = 0;
        $fecha_actual = strtotime($fecha_desde);
        $fecha_fin = strtotime($fecha_hasta);

        while ($fecha_actual <= $fecha_fin) {
            $dia_semana = date('N', $fecha_actual);
            if ($dia_semana < 6) { // Lunes=1 a Viernes=5
                $dias++;
            }
            $fecha_actual = strtotime('+1 day', $fecha_actual);
        }

        return $dias;
    }

    // Función auxiliar para calcular KPIs principales
    private static function calcularKPIsPrincipales($fecha_desde, $fecha_hasta) {
        $kpis = [];

        // Total de aplicaciones activas
        $apps_activas = self::fetchArray("
            SELECT COUNT(*) as total FROM aplicacion 
            WHERE apl_estado IN ('EN_PROGRESO', 'EN_PLANIFICACION') AND apl_situacion = 1
        ");
        $kpis['aplicaciones_activas'] = $apps_activas[0]['total'] ?? 0;

        // Cumplimiento de reportes
        $reportes_esperados = self::fetchArray("
            SELECT COUNT(DISTINCT CONCAT(a.apl_id, '_', dates.fecha)) as total
            FROM aplicacion a
            CROSS JOIN (
                SELECT DATE_ADD(?, INTERVAL seq.seq DAY) as fecha
                FROM (
                    SELECT 0 as seq UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
                ) seq
                WHERE DATE_ADD(?, INTERVAL seq.seq DAY) <= ?
                AND WEEKDAY(DATE_ADD(?, INTERVAL seq.seq DAY)) < 5
            ) dates
            WHERE a.apl_estado = 'EN_PROGRESO' AND a.apl_situacion = 1
        ", [$fecha_desde, $fecha_desde, $fecha_hasta, $fecha_desde]);

        $reportes_reales = self::fetchArray("
            SELECT COUNT(*) as total FROM avance_diario av
            JOIN aplicacion a ON av.ava_apl_id = a.apl_id
            WHERE av.ava_fecha BETWEEN ? AND ? 
            AND av.ava_situacion = 1 AND a.apl_estado = 'EN_PROGRESO'
        ", [$fecha_desde, $fecha_hasta]);

        $esperados = $reportes_esperados[0]['total'] ?? 0;
        $reales = $reportes_reales[0]['total'] ?? 0;
        $kpis['cumplimiento_reportes'] = $esperados > 0 ? round(($reales / $esperados) * 100, 2) : 0;

        // Promedio de avance general
        $avance_promedio = self::fetchArray("
            SELECT AVG(ava_porcentaje) as promedio
            FROM avance_diario av
            WHERE av.ava_fecha BETWEEN ? AND ? AND av.ava_situacion = 1
        ", [$fecha_desde, $fecha_hasta]);
        $kpis['avance_promedio'] = round($avance_promedio[0]['promedio'] ?? 0, 2);

        // Porcentaje de conformidad de visitas
        $conformidad = self::fetchArray("
            SELECT 
                COUNT(*) as total_visitas,
                COUNT(CASE WHEN vis_conformidad = 1 THEN 1 END) as conformes
            FROM visita 
            WHERE vis_fecha BETWEEN ? AND ? AND vis_situacion = 1
        ", [$fecha_desde, $fecha_hasta]);
        
        $total_visitas = $conformidad[0]['total_visitas'] ?? 0;
        $conformes = $conformidad[0]['conformes'] ?? 0;
        $kpis['porcentaje_conformidad'] = $total_visitas > 0 ? round(($conformes / $total_visitas) * 100, 2) : 0;

        return $kpis;
    }
}