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

class DashboardController extends ActiveRecord {

    public static function renderizarPagina(Router $router) {
        $router->render('dashboard/index', []);
    }

    // Dashboard ejecutivo para Gerente/Subgerente
    public static function obtenerDashboardEjecutivo() {
        self::obtenerDashboardEjecutivoAPI();
    }

    public static function obtenerDashboardEjecutivoAPI() {
        try {
            // Obtener todas las aplicaciones con información de avance
            $aplicaciones = self::obtenerAplicacionesConEstado();
            
            // Obtener alertas críticas
            $alertas = self::obtenerAlertasCriticas();
            
            // Obtener estadísticas generales
            $estadisticas = self::obtenerEstadisticasGenerales();
            
            // Obtener tendencias recientes
            $tendencias = self::obtenerTendenciasRecientes();

            $data = [
                'aplicaciones' => $aplicaciones,
                'alertas' => $alertas,
                'estadisticas' => $estadisticas,
                'tendencias' => $tendencias,
                'fecha_actualizacion' => date('Y-m-d H:i:s')
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Dashboard ejecutivo obtenido correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener el dashboard ejecutivo',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Dashboard específico para desarrolladores
    public static function obtenerDashboardDesarrollador() {
        self::obtenerDashboardDesarrolladorAPI();
    }

    public static function obtenerDashboardDesarrolladorAPI() {
        try {
            $usuario_id = filter_var($_GET['usuario_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$usuario_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de usuario requerido'
                ]);
                return;
            }

            //  Simplificar consulta para evitar subconsultas complejas
            $aplicaciones_responsable = Aplicacion::fetchArray("
                SELECT a.apl_id, a.apl_nombre, a.apl_estado, a.apl_fecha_inicio,
                       a.apl_responsable, a.apl_situacion
                FROM aplicacion a
                WHERE a.apl_responsable = ? AND a.apl_situacion = 1
                ORDER BY a.apl_estado, a.apl_fecha_inicio DESC
            ", [$usuario_id]);

            // Obtener último avance para cada aplicación por separado
            foreach ($aplicaciones_responsable as &$app) {
                // Obtener último avance
                $ultimo_avance = AvanceDiario::fetchArray("
                    SELECT ava_fecha, ava_porcentaje
                    FROM avance_diario
                    WHERE ava_apl_id = ? AND ava_situacion = 1
                    ORDER BY ava_fecha DESC
                    LIMIT 1
                ", [$app['apl_id']]);

                $app['ultimo_avance'] = $ultimo_avance[0]['ava_fecha'] ?? null;
                $app['porcentaje_actual'] = $ultimo_avance[0]['ava_porcentaje'] ?? 0;
                $app['semaforo'] = self::calcularSemaforoBasico($app['apl_id']);
                $app['puede_reportar_hoy'] = !self::existeAvanceDiario($app['apl_id'], $usuario_id, date('Y-m-d'));
            }

            // Comentarios no leídos (simulado por ahora)
            $comentarios_no_leidos = [];

            // Estadísticas del desarrollador
            $estadisticas_usuario = [
                'aplicaciones_responsable' => count($aplicaciones_responsable),
                'total_avances' => 0,
                'ultimo_avance' => null
            ];

            $data = [
                'aplicaciones' => $aplicaciones_responsable,
                'comentarios_no_leidos' => $comentarios_no_leidos,
                'menciones' => [],
                'estadisticas' => $estadisticas_usuario,
                'avances_recientes' => [],
                'fecha_actualizacion' => date('Y-m-d H:i:s')
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Dashboard de desarrollador obtenido correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener el dashboard de desarrollador',
                'detalle' => $e->getMessage(),
            ]);
        }
    }


public static function obtenerMetricasGeneralesAPI() {
    try {
        // Conteo por estado de aplicaciones
        $estados = Aplicacion::fetchArray("
            SELECT apl_estado, COUNT(*) as total
            FROM aplicacion 
            WHERE apl_situacion = 1 
            GROUP BY apl_estado
        ");

        $total_aplicaciones = 0;
        $en_progreso = 0;
        
        foreach ($estados as $estado) {
            $total_aplicaciones += $estado['total'];
            if ($estado['apl_estado'] === 'EN_PROGRESO') {
                $en_progreso = $estado['total'];
            }
        }

       
        $hoy = date('m/d/Y'); // 
        $avances_hoy = AvanceDiario::fetchArray("
            SELECT COUNT(*) as total_reportes
            FROM avance_diario 
            WHERE ava_fecha = '$hoy' AND ava_situacion = 1
        ");
        
        $reportes_hoy = $avances_hoy[0]['total_reportes'] ?? 0;


        $alertas_criticas = 0;

        $data = [
            'total_aplicaciones' => $total_aplicaciones,
            'en_progreso' => $en_progreso,
            'reportes_hoy' => $reportes_hoy,
            'alertas_criticas' => $alertas_criticas,
            'estados_aplicaciones' => $estados
        ];

        http_response_code(200);
        echo json_encode([
            'codigo' => 1,
            'mensaje' => 'Métricas obtenidas correctamente',
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'codigo' => 0,
            'mensaje' => 'Error al obtener métricas',
            'detalle' => $e->getMessage()
        ]);
    }
}
    //  Obtener aplicaciones con su estado actual
    private static function obtenerAplicacionesConEstado() {
        // Primera consulta: obtener aplicaciones con usuario
        $aplicaciones = Aplicacion::fetchArray("
            SELECT a.apl_id, a.apl_nombre, a.apl_estado, a.apl_fecha_inicio, 
                   a.apl_responsable, a.apl_situacion,
                   u.usu_nombre as responsable_nombre, u.usu_grado,
                   CASE 
                       WHEN u.usu_grado IS NOT NULL AND u.usu_grado != '' 
                       THEN u.usu_grado || ' ' || u.usu_nombre
                       ELSE u.usu_nombre
                   END as nombre_completo
            FROM aplicacion a
            LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
            WHERE a.apl_situacion = 1
            ORDER BY a.apl_estado, a.apl_fecha_inicio DESC
        ");

        // Obtener información adicional para cada aplicación
        foreach ($aplicaciones as &$app) {
            // Último avance
            $ultimo_avance = AvanceDiario::fetchArray("
                SELECT ava_fecha, ava_porcentaje
                FROM avance_diario
                WHERE ava_apl_id = ? AND ava_situacion = 1
                ORDER BY ava_fecha DESC
                LIMIT 1
            ", [$app['apl_id']]);

            $app['ultimo_avance'] = $ultimo_avance[0]['ava_fecha'] ?? null;
            $app['porcentaje_actual'] = $ultimo_avance[0]['ava_porcentaje'] ?? 0;

            // Última visita
            $ultima_visita = Visita::fetchArray("
                SELECT vis_fecha, vis_conformidad
                FROM visita
                WHERE vis_apl_id = ? AND vis_situacion = 1
                ORDER BY vis_fecha DESC
                LIMIT 1
            ", [$app['apl_id']]);

            $app['ultima_visita'] = $ultima_visita[0]['vis_fecha'] ?? null;
            $app['ultima_conformidad'] = $ultima_visita[0]['vis_conformidad'] ?? null;

            $app['semaforo'] = self::calcularSemaforoBasico($app['apl_id']);
            $app['comentarios_no_leidos'] = 0;
            
            // Calcular días sin avance
            if ($app['ultimo_avance']) {
                $dias_sin_avance = (strtotime(date('Y-m-d')) - strtotime($app['ultimo_avance'])) / (60 * 60 * 24);
                $app['dias_sin_avance'] = max(0, floor($dias_sin_avance));
            } else {
                $app['dias_sin_avance'] = null;
            }
        }

        return $aplicaciones;
    }

    // Obtener alertas críticas del sistema
    private static function obtenerAlertasCriticas() {
        $alertas = [];

        // Apps sin reporte hoy - Consulta simplificada
        $apps_sin_reporte = Aplicacion::fetchArray("
            SELECT a.apl_id, a.apl_nombre, u.usu_nombre as responsable_nombre
            FROM aplicacion a
            LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
            WHERE a.apl_estado = 'EN_PROGRESO' AND a.apl_situacion = 1
            ORDER BY a.apl_nombre
        ");

        // Verificar cuáles no tienen reporte hoy
        foreach ($apps_sin_reporte as $app) {
            $tiene_reporte = AvanceDiario::fetchArray("
                SELECT COUNT(*) as total
                FROM avance_diario
                WHERE ava_apl_id = ? AND ava_fecha = ? AND ava_situacion = 1
            ", [$app['apl_id'], date('Y-m-d')]);

            if (($tiene_reporte[0]['total'] ?? 0) == 0) {
                $alertas[] = [
                    'tipo' => 'SIN_REPORTE',
                    'nivel' => 'AMBAR',
                    'aplicacion_id' => $app['apl_id'],
                    'aplicacion_nombre' => $app['apl_nombre'],
                    'mensaje' => "App '" . $app['apl_nombre'] . "' no tiene reporte hoy",
                    'responsable' => $app['responsable_nombre']
                ];
            }
        }

        // Ordenar por nivel de criticidad
        usort($alertas, function($a, $b) {
            $niveles = ['ROJO' => 3, 'AMBAR' => 2, 'VERDE' => 1];
            return ($niveles[$b['nivel']] ?? 0) - ($niveles[$a['nivel']] ?? 0);
        });

        return array_slice($alertas, 0, 10);
    }

    // Obtener estadísticas generales del sistema
    private static function obtenerEstadisticasGenerales() {
        $stats = [];

        // Conteo por estado de aplicaciones
        $estados = Aplicacion::fetchArray("
            SELECT apl_estado, COUNT(*) as total
            FROM aplicacion 
            WHERE apl_situacion = 1 
            GROUP BY apl_estado
        ");
        $stats['estados_aplicaciones'] = $estados;

        // Estadísticas de avances hoy
        $avances_hoy = AvanceDiario::fetchArray("
            SELECT 
                COUNT(*) as total_reportes,
                COUNT(DISTINCT ava_apl_id) as apps_con_reporte,
                AVG(ava_porcentaje) as porcentaje_promedio
            FROM avance_diario 
            WHERE ava_fecha = ? AND ava_situacion = 1
        ", [date('Y-m-d')]);
        $stats['avances_hoy'] = $avances_hoy[0] ?? [];

        return $stats;
    }

    // Obtener tendencias de los últimos 14 días
    private static function obtenerTendenciasRecientes() {
        $tendencias = [];
        
        // Tendencia de reportes diarios
        $reportes_diarios = AvanceDiario::fetchArray("
            SELECT 
                ava_fecha,
                COUNT(*) as total_reportes,
                COUNT(DISTINCT ava_apl_id) as apps_reportaron,
                AVG(ava_porcentaje) as porcentaje_promedio
            FROM avance_diario 
            WHERE ava_fecha >= ? AND ava_situacion = 1
            GROUP BY ava_fecha
            ORDER BY ava_fecha DESC
        ", [date('Y-m-d', strtotime('-14 days'))]);
        $tendencias['reportes_diarios'] = $reportes_diarios;

        return $tendencias;
    }

    // API para obtener métricas específicas
    public static function obtenerMetricasAPI() {
        try {
            $tipo = $_GET['tipo'] ?? 'general';
            $dias = filter_var($_GET['dias'], FILTER_SANITIZE_NUMBER_INT) ?: 30;

            $data = [];

            switch ($tipo) {
                case 'cumplimiento':
                    $data = self::obtenerMetricasCumplimiento($dias);
                    break;
                case 'velocidad':
                    $data = self::obtenerMetricasVelocidad($dias);
                    break;
                default:
                    $data = self::obtenerMetricasGenerales($dias);
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Métricas obtenidas correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener métricas',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerTendenciasAPI() {
        try {
            $dias = filter_var($_GET['dias'], FILTER_SANITIZE_NUMBER_INT) ?: 14;
            $fecha_inicio = date('Y-m-d', strtotime("-$dias days"));
            
            // Tendencia de reportes diarios
            $reportes_diarios = AvanceDiario::fetchArray("
                SELECT 
                    ava_fecha,
                    COUNT(*) as total_reportes,
                    COUNT(DISTINCT ava_apl_id) as apps_reportaron,
                    AVG(ava_porcentaje) as porcentaje_promedio
                FROM avance_diario 
                WHERE ava_fecha >= ? AND ava_situacion = 1
                GROUP BY ava_fecha
                ORDER BY ava_fecha ASC
            ", [$fecha_inicio]);

            $data = [
                'reportes_diarios' => $reportes_diarios,
                'periodo' => $dias
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Tendencias obtenidas correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener tendencias',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // NUEVA API para alertas críticas
    public static function obtenerAlertasCriticasAPI() {
        try {
            $alertas = self::obtenerAlertasCriticas();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Alertas obtenidas correctamente',
                'data' => $alertas
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener alertas',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    //: Métodos auxiliares simplificados
    private static function obtenerMetricasCumplimiento($dias) {
        $fecha_inicio = date('Y-m-d', strtotime("-$dias days"));
        
        // Simplificar consulta para Informix - obtener usuarios primero
        $usuarios = Usuario::fetchArray("
            SELECT usu_id, usu_nombre
            FROM usuario
            WHERE usu_situacion = 1 AND usu_activo = 1
        ");

        $resultados = [];
        foreach ($usuarios as $usuario) {
            $reportes = AvanceDiario::fetchArray("
                SELECT COUNT(DISTINCT ava_fecha) as dias_con_reporte
                FROM avance_diario
                WHERE ava_usu_id = ? AND ava_fecha >= ? AND ava_situacion = 1
            ", [$usuario['usu_id'], $fecha_inicio]);

            $dias_con_reporte = $reportes[0]['dias_con_reporte'] ?? 0;
            $porcentaje_cumplimiento = round(($dias_con_reporte / $dias) * 100, 2);

            $resultados[] = [
                'usu_nombre' => $usuario['usu_nombre'],
                'dias_con_reporte' => $dias_con_reporte,
                'porcentaje_cumplimiento' => $porcentaje_cumplimiento
            ];
        }

        // Ordenar por porcentaje
        usort($resultados, function($a, $b) {
            return $b['porcentaje_cumplimiento'] - $a['porcentaje_cumplimiento'];
        });

        return $resultados;
    }

    private static function obtenerMetricasVelocidad($dias) {
        $fecha_inicio = date('Y-m-d', strtotime("-$dias days"));
        
        // Consulta simplificada para Informix
        $aplicaciones = Aplicacion::fetchArray("
            SELECT a.apl_id, a.apl_nombre
            FROM aplicacion a
            WHERE a.apl_situacion = 1
        ");

        $resultados = [];
        foreach ($aplicaciones as $app) {
            $avances = AvanceDiario::fetchArray("
                SELECT 
                    MIN(ava_porcentaje) as porcentaje_inicial,
                    MAX(ava_porcentaje) as porcentaje_final,
                    COUNT(DISTINCT ava_fecha) as dias_trabajados
                FROM avance_diario
                WHERE ava_apl_id = ? AND ava_fecha >= ? AND ava_situacion = 1
            ", [$app['apl_id'], $fecha_inicio]);

            if ($avances && $avances[0]['dias_trabajados'] > 1) {
                $inicial = $avances[0]['porcentaje_inicial'] ?? 0;
                $final = $avances[0]['porcentaje_final'] ?? 0;
                $dias = $avances[0]['dias_trabajados'] ?? 1;
                $puntos_por_dia = round(($final - $inicial) / $dias, 2);

                $resultados[] = [
                    'apl_nombre' => $app['apl_nombre'],
                    'porcentaje_inicial' => $inicial,
                    'porcentaje_final' => $final,
                    'dias_trabajados' => $dias,
                    'puntos_por_dia' => $puntos_por_dia
                ];
            }
        }

        // Ordenar por puntos por día
        usort($resultados, function($a, $b) {
            return $b['puntos_por_dia'] - $a['puntos_por_dia'];
        });

        return $resultados;
    }

    private static function obtenerMetricasGenerales($dias) {
        return [
            'cumplimiento' => self::obtenerMetricasCumplimiento($dias),
            'velocidad' => self::obtenerMetricasVelocidad($dias)
        ];
    }

    // Métodos auxiliares simplificados
    private static function calcularSemaforoBasico($apl_id) {
        $hoy = date('Y-m-d');
        
        $tiene_reporte = AvanceDiario::fetchArray("
            SELECT COUNT(*) as total
            FROM avance_diario 
            WHERE ava_apl_id = ? AND ava_fecha = ? AND ava_situacion = 1
        ", [$apl_id, $hoy]);
        
        if (($tiene_reporte[0]['total'] ?? 0) > 0) {
            return 'VERDE';
        } else {
            return 'AMBAR';
        }
    }

    private static function existeAvanceDiario($apl_id, $usu_id, $fecha) {
        $resultado = AvanceDiario::fetchArray("
            SELECT COUNT(*) as total
            FROM avance_diario 
            WHERE ava_apl_id = ? AND ava_usu_id = ? AND ava_fecha = ? AND ava_situacion = 1
        ", [$apl_id, $usu_id, $fecha]);
        
        return ($resultado[0]['total'] ?? 0) > 0;
    }
}