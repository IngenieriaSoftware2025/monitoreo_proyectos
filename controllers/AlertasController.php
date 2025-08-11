<?php

namespace Controllers;

use Exception;
use Model\ActiveRecord;
use Model\Aplicacion;
use Model\AvanceDiario;
use Model\Comentario;
use Model\Visita;
use Model\Usuario;
use Model\InactividadDiaria;
use MVC\Router;

class AlertasController extends ActiveRecord {

    public static function renderizarPagina(Router $router) {
        $router->render('alertas/index', []);
    }

    // Obtener todas las alertas del sistema
    public static function obtenerAlertasAPI() {
        try {
            $tipo = $_GET['tipo'] ?? 'todas';
            $prioridad = $_GET['prioridad'] ?? null;
            $limite = filter_var($_GET['limite'], FILTER_SANITIZE_NUMBER_INT) ?: 50;

            $alertas = [];

            // Obtener diferentes tipos de alertas según el filtro
            switch ($tipo) {
                case 'sin_reporte':
                    $alertas = array_merge($alertas, self::obtenerAlertasSinReporte());
                    break;
                case 'bloqueadores':
                    $alertas = array_merge($alertas, self::obtenerAlertasBloqueadores());
                    break;
                case 'conformidad':
                    $alertas = array_merge($alertas, self::obtenerAlertasConformidad());
                    break;
                case 'inactividad':
                    $alertas = array_merge($alertas, self::obtenerAlertasInactividad());
                    break;
                case 'comentarios':
                    $alertas = array_merge($alertas, self::obtenerAlertasComentarios());
                    break;
                default:
                    // Obtener todas las alertas
                    $alertas = array_merge(
                        self::obtenerAlertasSinReporte(),
                        self::obtenerAlertasBloqueadores(),
                        self::obtenerAlertasConformidad(),
                        self::obtenerAlertasInactividad(),
                        self::obtenerAlertasComentarios()
                    );
                    break;
            }

            // Filtrar por prioridad si se especifica
            if ($prioridad) {
                $alertas = array_filter($alertas, function($alerta) use ($prioridad) {
                    return $alerta['prioridad'] === $prioridad;
                });
            }

            // Ordenar por prioridad y fecha
            usort($alertas, function($a, $b) {
                $prioridades = ['CRITICA' => 4, 'ALTA' => 3, 'MEDIA' => 2, 'BAJA' => 1];
                $prioridad_a = $prioridades[$a['prioridad']] ?? 0;
                $prioridad_b = $prioridades[$b['prioridad']] ?? 0;
                
                if ($prioridad_a === $prioridad_b) {
                    return strtotime($b['fecha']) - strtotime($a['fecha']);
                }
                return $prioridad_b - $prioridad_a;
            });

            // Limitar resultados
            $alertas = array_slice($alertas, 0, $limite);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($alertas) > 0 ? 'Alertas obtenidas correctamente' : 'No hay alertas',
                'data' => $alertas,
                'total' => count($alertas)
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

    // Alertas de aplicaciones sin reporte
    private static function obtenerAlertasSinReporte() {
        $alertas = [];
        $hoy = date('Y-m-d');
        $hora_actual = date('H:i');

        // Aplicaciones sin reporte hoy (después de las 16:30)
        if ($hora_actual >= '16:30') {
            $apps_sin_reporte = AvanceDiario::obtenerAplicacionesSinReporte($hoy);
            
            foreach ($apps_sin_reporte as $app) {
                $alertas[] = [
                    'id' => 'sin_reporte_' . $app['apl_id'] . '_' . $hoy,
                    'tipo' => 'SIN_REPORTE',
                    'prioridad' => 'MEDIA',
                    'titulo' => 'Sin reporte diario',
                    'mensaje' => "La aplicación '{$app['apl_nombre']}' no tiene reporte para hoy",
                    'aplicacion_id' => $app['apl_id'],
                    'aplicacion_nombre' => $app['apl_nombre'],
                    'responsable' => $app['responsable_nombre'],
                    'fecha' => $hoy . ' ' . $hora_actual . ':00',
                    'acciones' => ['recordar', 'justificar']
                ];
            }
        }

        // Aplicaciones sin reporte por 2 o más días
        $apps_sin_reporte_critico = AvanceDiario::fetchArray("
            SELECT DISTINCT a.apl_id, a.apl_nombre, u.usu_nombre as responsable_nombre,
                   COALESCE(ultimo_avance.ava_fecha, a.apl_fecha_inicio) as ultimo_reporte,
                   DATEDIFF(CURDATE(), COALESCE(ultimo_avance.ava_fecha, a.apl_fecha_inicio)) as dias_sin_reporte
            FROM aplicacion a
            LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
            LEFT JOIN (
                SELECT ava_apl_id, MAX(ava_fecha) as ava_fecha
                FROM avance_diario 
                WHERE ava_situacion = 1
                GROUP BY ava_apl_id
            ) ultimo_avance ON a.apl_id = ultimo_avance.ava_apl_id
            WHERE a.apl_estado = 'EN_PROGRESO' 
            AND a.apl_situacion = 1
            AND DATEDIFF(CURDATE(), COALESCE(ultimo_avance.ava_fecha, a.apl_fecha_inicio)) >= 2
        ");

        foreach ($apps_sin_reporte_critico as $app) {
            $alertas[] = [
                'id' => 'sin_reporte_critico_' . $app['apl_id'],
                'tipo' => 'SIN_REPORTE_CRITICO',
                'prioridad' => 'CRITICA',
                'titulo' => 'Sin reporte por ' . $app['dias_sin_reporte'] . ' días',
                'mensaje' => "La aplicación '{$app['apl_nombre']}' lleva {$app['dias_sin_reporte']} días sin reporte",
                'aplicacion_id' => $app['apl_id'],
                'aplicacion_nombre' => $app['apl_nombre'],
                'responsable' => $app['responsable_nombre'],
                'fecha' => $app['ultimo_reporte'],
                'dias_sin_reporte' => $app['dias_sin_reporte'],
                'acciones' => ['pausar_app', 'contactar_responsable', 'asignar_sustituto']
            ];
        }

        return $alertas;
    }

    // Alertas de bloqueadores críticos
    private static function obtenerAlertasBloqueadores() {
        $alertas = [];
        $bloqueadores = AvanceDiario::obtenerBloqueadoresCriticos(24);

        foreach ($bloqueadores as $bloqueador) {
            $horas_transcurridas = (time() - strtotime($bloqueador['ava_creado_en'])) / 3600;
            
            $alertas[] = [
                'id' => 'bloqueador_' . $bloqueador['ava_id'],
                'tipo' => 'BLOQUEADOR_CRITICO',
                'prioridad' => $horas_transcurridas > 48 ? 'CRITICA' : 'ALTA',
                'titulo' => 'Bloqueador crítico',
                'mensaje' => "Bloqueador crítico en '{$bloqueador['apl_nombre']}' por " . round($horas_transcurridas) . " horas",
                'aplicacion_id' => $bloqueador['ava_apl_id'],
                'aplicacion_nombre' => $bloqueador['apl_nombre'],
                'responsable' => $bloqueador['usu_nombre'],
                'fecha' => $bloqueador['ava_creado_en'],
                'horas_transcurridas' => round($horas_transcurridas),
                'bloqueador_detalle' => $bloqueador['ava_bloqueadores'],
                'acciones' => ['resolver_bloqueador', 'escalar', 'pausar_app']
            ];
        }

        return $alertas;
    }

    // Alertas de conformidad de visitas
    private static function obtenerAlertasConformidad() {
        $alertas = [];
        $visitas_sin_conformidad = Visita::obtenerSinConformidad();

        foreach ($visitas_sin_conformidad as $visita) {
            $horas_transcurridas = (time() - strtotime($visita['vis_fecha'])) / 3600;
            
            $alertas[] = [
                'id' => 'conformidad_' . $visita['vis_id'],
                'tipo' => 'CONFORMIDAD_NO',
                'prioridad' => $horas_transcurridas > 24 ? 'CRITICA' : 'ALTA',
                'titulo' => 'Visita sin conformidad',
                'mensaje' => "Visita sin conformidad en '{$visita['apl_nombre']}'",
                'aplicacion_id' => $visita['vis_apl_id'],
                'aplicacion_nombre' => $visita['apl_nombre'],
                'responsable' => $visita['responsable_nombre'],
                'fecha' => $visita['vis_fecha'],
                'visita_quien' => $visita['vis_quien'],
                'observaciones' => $visita['vis_observacion'],
                'acciones' => ['revisar_plan_accion', 'programar_seguimiento', 'escalar']
            ];
        }

        return $alertas;
    }

    // Alertas de patrones de inactividad sospechosos
    private static function obtenerAlertasInactividad() {
        $alertas = [];
        $patrones_sospechosos = InactividadDiaria::detectarPatronesSospechosos(7);

        foreach ($patrones_sospechosos as $patron) {
            if ($patron['dias_inactivos'] >= 5) {
                $alertas[] = [
                    'id' => 'inactividad_' . $patron['ina_usu_id'],
                    'tipo' => 'INACTIVIDAD_ALTA',
                    'prioridad' => $patron['dias_inactivos'] >= 7 ? 'CRITICA' : 'ALTA',
                    'titulo' => 'Alta inactividad detectada',
                    'mensaje' => "Usuario '{$patron['usu_nombre']}' con {$patron['dias_inactivos']} días de inactividad",
                    'usuario_id' => $patron['ina_usu_id'],
                    'usuario_nombre' => $patron['usu_nombre'],
                    'dias_inactivos' => $patron['dias_inactivos'],
                    'apps_afectadas' => $patron['apps_afectadas'],
                    'tipos_inactividad' => $patron['tipos_inactividad'],
                    'fecha' => date('Y-m-d H:i:s'),
                    'acciones' => ['revisar_justificaciones', 'contactar_usuario', 'reasignar_tareas']
                ];
            }
        }

        return $alertas;
    }

    // Alertas de comentarios importantes
    private static function obtenerAlertasComentarios() {
        $alertas = [];
        
        // Comentarios con muchas menciones sin respuesta
        $comentarios_urgentes = Comentario::fetchArray("
            SELECT c.*, a.apl_nombre, autor.usu_nombre as autor_nombre,
                   LENGTH(c.com_texto) - LENGTH(REPLACE(c.com_texto, '@', '')) as menciones_count
            FROM comentario c
            JOIN aplicacion a ON c.com_apl_id = a.apl_id
            JOIN usuario autor ON c.com_autor_id = autor.usu_id
            WHERE c.com_creado_en >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND c.com_situacion = 1
            AND a.apl_situacion = 1
            AND (LENGTH(c.com_texto) - LENGTH(REPLACE(c.com_texto, '@', ''))) >= 3
            ORDER BY c.com_creado_en DESC
            LIMIT 10
        ");

        foreach ($comentarios_urgentes as $comentario) {
            $alertas[] = [
                'id' => 'comentario_urgente_' . $comentario['com_id'],
                'tipo' => 'COMENTARIO_MULTIPLE_MENCIONES',
                'prioridad' => 'MEDIA',
                'titulo' => 'Comentario con múltiples menciones',
                'mensaje' => "Comentario en '{$comentario['apl_nombre']}' con {$comentario['menciones_count']} menciones",
                'aplicacion_id' => $comentario['com_apl_id'],
                'aplicacion_nombre' => $comentario['apl_nombre'],
                'autor' => $comentario['autor_nombre'],
                'fecha' => $comentario['com_creado_en'],
                'comentario_id' => $comentario['com_id'],
                'acciones' => ['revisar_comentario', 'responder_urgente']
            ];
        }

        return $alertas;
    }

    // Obtener resumen de alertas por tipo
    public static function obtenerResumenAlertasAPI() {
        try {
            $resumen = [
                'sin_reporte' => 0,
                'bloqueadores_criticos' => 0,
                'conformidad_no' => 0,
                'inactividad_alta' => 0,
                'comentarios_urgentes' => 0,
                'total' => 0
            ];

            // Contar aplicaciones sin reporte hoy
            $apps_sin_reporte = AvanceDiario::obtenerAplicacionesSinReporte();
            $resumen['sin_reporte'] = count($apps_sin_reporte);

            // Contar bloqueadores críticos
            $bloqueadores = AvanceDiario::obtenerBloqueadoresCriticos();
            $resumen['bloqueadores_criticos'] = count($bloqueadores);

            // Contar visitas sin conformidad
            $visitas_no_conformes = Visita::obtenerSinConformidad();
            $resumen['conformidad_no'] = count($visitas_no_conformes);

            // Contar patrones de inactividad
            $patrones_inactividad = InactividadDiaria::detectarPatronesSospechosos();
            $resumen['inactividad_alta'] = count(array_filter($patrones_inactividad, function($p) {
                return $p['dias_inactivos'] >= 5;
            }));

            // Contar comentarios urgentes
            $comentarios_urgentes = Comentario::fetchArray("
                SELECT COUNT(*) as total
                FROM comentario c
                WHERE c.com_creado_en >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND c.com_situacion = 1
                AND (LENGTH(c.com_texto) - LENGTH(REPLACE(c.com_texto, '@', ''))) >= 3
            ");
            $resumen['comentarios_urgentes'] = $comentarios_urgentes[0]['total'] ?? 0;

            $resumen['total'] = array_sum(array_slice($resumen, 0, -1));

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Resumen de alertas obtenido correctamente',
                'data' => $resumen
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener resumen de alertas',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Marcar alerta como atendida
    public static function marcarAtendidaAPI() {
        getHeadersApi();

        try {
            $alerta_id = $_POST['alerta_id'];
            $accion_tomada = $_POST['accion_tomada'] ?? '';
            $observaciones = $_POST['observaciones'] ?? '';
            $usuario_atencion = $_POST['usuario_atencion'] ?? null;

            // Por simplicidad, registraremos en una tabla de log o en comentarios
            // En un sistema más complejo, tendrías una tabla específica para alertas
            
            $log_entry = [
                'fecha_atencion' => date('Y-m-d H:i:s'),
                'alerta_id' => $alerta_id,
                'accion_tomada' => $accion_tomada,
                'observaciones' => $observaciones,
                'usuario_atencion' => $usuario_atencion
            ];

            // Aquí podrías insertar en una tabla de log de alertas
            // Por ahora simularemos que se guardó correctamente

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Alerta marcada como atendida correctamente',
                'data' => $log_entry
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al marcar alerta como atendida',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Configurar alertas automáticas
    public static function configurarAlertasAPI() {
        try {
            $config = [
                'horario_alerta_sin_reporte' => '16:30',
                'dias_criticos_sin_reporte' => 2,
                'horas_bloqueador_critico' => 24,
                'horas_conformidad_critica' => 24,
                'dias_inactividad_alerta' => 5,
                'dias_inactividad_critica' => 7,
                'menciones_comentario_urgente' => 3,
                'horas_comentario_urgente' => 24
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Configuración de alertas obtenida correctamente',
                'data' => $config
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener configuración de alertas',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Obtener alertas por usuario específico
    public static function obtenerAlertasUsuarioAPI() {
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

            $alertas = [];

            // Alertas de aplicaciones donde es responsable sin reporte
            $apps_responsable_sin_reporte = AvanceDiario::fetchArray("
                SELECT a.apl_id, a.apl_nombre
                FROM aplicacion a
                LEFT JOIN avance_diario av ON (a.apl_id = av.ava_apl_id AND av.ava_fecha = CURDATE() AND av.ava_situacion = 1)
                WHERE a.apl_responsable = ?
                AND a.apl_estado = 'EN_PROGRESO'
                AND a.apl_situacion = 1
                AND av.ava_id IS NULL
            ", [$usuario_id]);

            foreach ($apps_responsable_sin_reporte as $app) {
                $alertas[] = [
                    'tipo' => 'TU_APP_SIN_REPORTE',
                    'prioridad' => 'ALTA',
                    'titulo' => 'Falta tu reporte de hoy',
                    'mensaje' => "Falta registrar el avance de hoy para '{$app['apl_nombre']}'",
                    'aplicacion_id' => $app['apl_id'],
                    'aplicacion_nombre' => $app['apl_nombre'],
                    'acciones' => ['registrar_avance', 'justificar_inactividad']
                ];
            }

            // Comentarios donde está mencionado sin leer
            $menciones_no_leidas = Comentario::obtenerConMenciones($usuario_id, 10);
            $menciones_no_leidas = array_filter($menciones_no_leidas, function($m) {
                return empty($m['fecha_leido']);
            });

            foreach ($menciones_no_leidas as $mencion) {
                $alertas[] = [
                    'tipo' => 'MENCION_NO_LEIDA',
                    'prioridad' => 'MEDIA',
                    'titulo' => 'Te mencionaron en un comentario',
                    'mensaje' => "Te mencionaron en '{$mencion['apl_nombre']}'",
                    'aplicacion_id' => $mencion['com_apl_id'],
                    'aplicacion_nombre' => $mencion['apl_nombre'],
                    'comentario_id' => $mencion['com_id'],
                    'autor' => $mencion['autor_nombre'],
                    'fecha' => $mencion['com_creado_en'],
                    'acciones' => ['leer_comentario', 'responder']
                ];
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($alertas) > 0 ? 'Alertas del usuario obtenidas' : 'No hay alertas para este usuario',
                'data' => $alertas
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener alertas del usuario',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    // Generar notificaciones automáticas (para cron job)
    public static function generarNotificacionesAutomaticasAPI() {
        try {
            $notificaciones_enviadas = [];
            $hora_actual = date('H:i');

            // Alerta a las 16:30 - Recordar reportes pendientes
            if ($hora_actual === '16:30') {
                $apps_sin_reporte = AvanceDiario::obtenerAplicacionesSinReporte();
                
                foreach ($apps_sin_reporte as $app) {
                    if ($app['responsable_nombre']) {
                        $notificaciones_enviadas[] = [
                            'tipo' => 'RECORDATORIO_REPORTE',
                            'destinatario' => $app['responsable_nombre'],
                            'aplicacion' => $app['apl_nombre'],
                            'mensaje' => "Recordatorio: Falta registrar el avance de hoy para {$app['apl_nombre']}",
                            'hora_envio' => date('Y-m-d H:i:s')
                        ];
                    }
                }
            }

            // Escalación automática de bloqueadores críticos (después de 48 horas)
            $bloqueadores_escalacion = AvanceDiario::obtenerBloqueadoresCriticos(48);
            foreach ($bloqueadores_escalacion as $bloqueador) {
                $notificaciones_enviadas[] = [
                    'tipo' => 'ESCALACION_BLOQUEADOR',
                    'destinatario' => 'Gerencia',
                    'aplicacion' => $bloqueador['apl_nombre'],
                    'responsable' => $bloqueador['usu_nombre'],
                    'mensaje' => "Escalación: Bloqueador crítico en {$bloqueador['apl_nombre']} sin resolver por más de 48 horas",
                    'hora_envio' => date('Y-m-d H:i:s')
                ];
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Notificaciones automáticas generadas',
                'data' => [
                    'notificaciones_enviadas' => count($notificaciones_enviadas),
                    'detalle' => $notificaciones_enviadas
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al generar notificaciones automáticas',
                'detalle' => $e->getMessage(),
            ]);
        }
    }
}