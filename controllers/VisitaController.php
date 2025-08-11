<?php

namespace Controllers;

use Exception;
use Model\ActiveRecord;
use Model\Visita;
use Model\Aplicacion;
use Model\Usuario;
use MVC\Router;

class VisitaController extends ActiveRecord {

    public static function renderizarPagina(Router $router) {
        $router->render('visitas/index', []);
    }

    public static function buscarAPI() {
        try {
            $aplicacion_id = $_GET['aplicacion_id'] ?? null;
            $creador_id = $_GET['creador_id'] ?? null;
            $fecha_desde = $_GET['fecha_desde'] ?? null;
            $fecha_hasta = $_GET['fecha_hasta'] ?? null;
            $limite = filter_var($_GET['limite'], FILTER_SANITIZE_NUMBER_INT) ?: null;

            if ($aplicacion_id) {
                $data = Visita::obtenerPorAplicacion($aplicacion_id, $limite);
            } elseif ($creador_id) {
                $data = Visita::obtenerPorCreador($creador_id, $limite);
            } elseif ($fecha_desde || $fecha_hasta) {
                $fecha_desde = $fecha_desde ?: date('Y-m-d', strtotime('-30 days'));
                $fecha_hasta = $fecha_hasta ?: date('Y-m-d');
                $data = Visita::obtenerPorFechas($fecha_desde, $fecha_hasta);
            } else {
                // Obtener todas las visitas recientes
                $query = "
                    SELECT v.*, a.apl_nombre, u.usu_nombre as creado_por_nombre, u.usu_grado,
                           resp.usu_nombre as responsable_nombre
                    FROM visita v
                    JOIN aplicacion a ON v.vis_apl_id = a.apl_id
                    LEFT JOIN usuario u ON v.vis_creado_por = u.usu_id
                    LEFT JOIN usuario resp ON a.apl_responsable = resp.usu_id
                    WHERE v.vis_situacion = 1 AND a.apl_situacion = 1
                    ORDER BY v.vis_fecha DESC
                ";
                
                if ($limite) {
                    $query .= " LIMIT " . intval($limite);
                }
                
                $data = Visita::fetchArray($query);
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Visitas obtenidas correctamente' : 'No hay visitas registradas',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener las visitas',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function guardarAPI()
    {
        getHeadersApi();

        // Validar aplicación
        if (empty($_POST['vis_apl_id'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La aplicación es obligatoria'
            ]);
            return;
        }

        // Validar fecha y hora
        if (empty($_POST['vis_fecha'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La fecha y hora de la visita es obligatoria'
            ]);
            return;
        }

        // Validar formato de fecha y hora
        $fecha_hora = $_POST['vis_fecha'];
        $formatos_validos = ['Y-m-d H:i:s', 'Y-m-d H:i'];
        $fecha_valida = false;
        
        foreach ($formatos_validos as $formato) {
            $d = \DateTime::createFromFormat($formato, $fecha_hora);
            if ($d && $d->format($formato) === $fecha_hora) {
                $fecha_valida = true;
                break;
            }
        }

        if (!$fecha_valida) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El formato de fecha y hora no es válido (YYYY-MM-DD HH:MM)'
            ]);
            return;
        }

        // Validar longitud de campos
        $campos_longitud = [
            'vis_quien' => ['valor' => $_POST['vis_quien'] ?? '', 'max' => 150],
            'vis_motivo' => ['valor' => $_POST['vis_motivo'] ?? '', 'max' => 400],
            'vis_procedimiento' => ['valor' => $_POST['vis_procedimiento'] ?? '', 'max' => 400],
            'vis_solucion' => ['valor' => $_POST['vis_solucion'] ?? '', 'max' => 400],
            'vis_observacion' => ['valor' => $_POST['vis_observacion'] ?? '', 'max' => 800]
        ];

        foreach ($campos_longitud as $campo => $config) {
            if (strlen($config['valor']) > $config['max']) {
                $nombre_campo = str_replace('vis_', '', $campo);
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => "El campo {$nombre_campo} no puede tener más de {$config['max']} caracteres"
                ]);
                return;
            }
        }

        // Validar conformidad + observación
        $conformidad = $_POST['vis_conformidad'] ?? null;
        if ($conformidad === 'false' || $conformidad === '0') {
            $conformidad = false;
        } elseif ($conformidad === 'true' || $conformidad === '1') {
            $conformidad = true;
        } else {
            $conformidad = null;
        }

        if ($conformidad === false && empty(trim($_POST['vis_observacion'] ?? ''))) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Si la conformidad es "No", debe especificar las observaciones'
            ]);
            return;
        }

        // Verificar que la aplicación existe y está activa
        $aplicacion = Aplicacion::fetchFirst("SELECT apl_estado FROM aplicacion WHERE apl_id = " . $_POST['vis_apl_id'] . " AND apl_situacion = 1");
        
        if (!$aplicacion) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La aplicación no existe o no está activa'
            ]);
            return;
        }

        // Verificar que el usuario creador existe (si se especifica)
        if (!empty($_POST['vis_creado_por'])) {
            $usuario = Usuario::fetchFirst("SELECT usu_activo FROM usuario WHERE usu_id = " . $_POST['vis_creado_por'] . " AND usu_situacion = 1");
            
            if (!$usuario || !$usuario['usu_activo']) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'El usuario creador no existe o no está activo'
                ]);
                return;
            }
        }

        try {
            // Normalizar fecha de visita
            if (strlen($fecha_hora) == 16) {
                $fecha_hora .= ':00';
            }

            $data = new Visita([
                'vis_apl_id' => $_POST['vis_apl_id'],
                'vis_fecha' => $fecha_hora,
                'vis_quien' => htmlspecialchars($_POST['vis_quien'] ?? ''),
                'vis_motivo' => htmlspecialchars($_POST['vis_motivo'] ?? ''),
                'vis_procedimiento' => htmlspecialchars($_POST['vis_procedimiento'] ?? ''),
                'vis_solucion' => htmlspecialchars($_POST['vis_solucion'] ?? ''),
                'vis_observacion' => htmlspecialchars($_POST['vis_observacion'] ?? ''),
                'vis_conformidad' => $conformidad,
                'vis_creado_por' => $_POST['vis_creado_por'] ?? null,
                'vis_creado_en' => date('Y-m-d H:i:s')
            ]);

            $crear = $data->crear();

            if ($crear['resultado']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => 'La visita ha sido registrada correctamente'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Error al crear la visita'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al guardar',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function modificarAPI()
    {
        getHeadersApi();

        $id = $_POST['vis_id'];

        // Validar fecha y hora
        if (empty($_POST['vis_fecha'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La fecha y hora de la visita es obligatoria'
            ]);
            return;
        }

        // Validar conformidad + observación
        $conformidad = $_POST['vis_conformidad'] ?? null;
        if ($conformidad === 'false' || $conformidad === '0') {
            $conformidad = false;
        } elseif ($conformidad === 'true' || $conformidad === '1') {
            $conformidad = true;
        } else {
            $conformidad = null;
        }

        if ($conformidad === false && empty(trim($_POST['vis_observacion'] ?? ''))) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Si la conformidad es "No", debe especificar las observaciones'
            ]);
            return;
        }

        try {
            $data = Visita::find($id);
            
            if (!$data) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Visita no encontrada'
                ]);
                return;
            }

            // Normalizar fecha de visita
            $fecha_hora = $_POST['vis_fecha'];
            if (strlen($fecha_hora) == 16) {
                $fecha_hora .= ':00';
            }

            $data->sincronizar([
                'vis_fecha' => $fecha_hora,
                'vis_quien' => htmlspecialchars($_POST['vis_quien'] ?? ''),
                'vis_motivo' => htmlspecialchars($_POST['vis_motivo'] ?? ''),
                'vis_procedimiento' => htmlspecialchars($_POST['vis_procedimiento'] ?? ''),
                'vis_solucion' => htmlspecialchars($_POST['vis_solucion'] ?? ''),
                'vis_observacion' => htmlspecialchars($_POST['vis_observacion'] ?? ''),
                'vis_conformidad' => $conformidad
            ]);
            
            $resultado = $data->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'La visita ha sido modificada exitosamente'
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al guardar',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function eliminarAPI()
    {
        try {
            $id = filter_var($_GET['vis_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de visita requerido'
                ]);
                return;
            }

            $visita = Visita::find($id);
            
            if (!$visita) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Visita no encontrada'
                ]);
                return;
            }

            // Verificar si se puede eliminar (solo el mismo día)
            if (!$visita->puedeEliminar()) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Solo se puede eliminar la visita el mismo día de creación'
                ]);
                return;
            }

            // Soft delete
            $visita->sincronizar(['vis_situacion' => 0]);
            $resultado = $visita->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'La visita ha sido eliminada correctamente'
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al eliminar',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerSinConformidadAPI() {
        try {
            $data = Visita::obtenerSinConformidad();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Visitas sin conformidad obtenidas' : 'No hay visitas sin conformidad',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener visitas sin conformidad',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerEstadisticasConformidadAPI() {
        try {
            $aplicacion_id = filter_var($_GET['aplicacion_id'], FILTER_SANITIZE_NUMBER_INT);
            $dias = filter_var($_GET['dias'], FILTER_SANITIZE_NUMBER_INT) ?: 30;

            if (!$aplicacion_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de aplicación requerido'
                ]);
                return;
            }

            $data = Visita::calcularConformidadAplicacion($aplicacion_id, $dias);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Estadísticas de conformidad obtenidas correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener estadísticas de conformidad',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerEstadisticasGeneralesAPI() {
        try {
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            $data = Visita::obtenerEstadisticasGenerales($fecha_desde, $fecha_hasta);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Estadísticas generales obtenidas correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener estadísticas generales',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerTimelineAPI() {
        try {
            $aplicacion_id = filter_var($_GET['aplicacion_id'], FILTER_SANITIZE_NUMBER_INT);
            $limite = filter_var($_GET['limite'], FILTER_SANITIZE_NUMBER_INT) ?: 20;

            if (!$aplicacion_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de aplicación requerido'
                ]);
                return;
            }

            $data = Visita::obtenerTimelineAplicacion($aplicacion_id, $limite);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Timeline obtenido correctamente' : 'No hay actividad en el timeline',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener timeline',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function detectarProblemasConformidadAPI() {
        try {
            $dias_limite = filter_var($_GET['dias_limite'], FILTER_SANITIZE_NUMBER_INT) ?: 7;

            $data = Visita::detectarProblemasConformidad($dias_limite);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Problemas de conformidad detectados' : 'No se detectaron problemas de conformidad',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al detectar problemas de conformidad',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function marcarComoAtendidaAPI()
    {
        getHeadersApi();

        try {
            $id = $_POST['vis_id'];
            $conformidad = $_POST['vis_conformidad'] ?? null;
            $observacion_adicional = trim($_POST['observacion_adicional'] ?? '');

            if ($conformidad === 'false' || $conformidad === '0') {
                $conformidad = false;
            } elseif ($conformidad === 'true' || $conformidad === '1') {
                $conformidad = true;
            } else {
                $conformidad = null;
            }

            $visita = Visita::find($id);
            
            if (!$visita) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Visita no encontrada'
                ]);
                return;
            }

            $resultado = $visita->marcarComoAtendida($conformidad, $observacion_adicional);

            if ($resultado['resultado']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => 'La visita ha sido marcada como atendida'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Error al marcar como atendida'
                ]);
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al marcar como atendida',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerMotivosComunes() {
        try {
            $motivos = Visita::MOTIVOS_COMUNES;
            
            $data = [];
            foreach ($motivos as $motivo) {
                $data[] = [
                    'valor' => $motivo,
                    'texto' => ucwords(strtolower(str_replace('_', ' ', $motivo)))
                ];
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Motivos comunes obtenidos correctamente',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener los motivos',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function generarReporteConformidadAPI() {
        try {
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            $data = Visita::generarReporteConformidad($fecha_desde, $fecha_hasta);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Reporte de conformidad generado correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al generar reporte de conformidad',
                'detalle' => $e->getMessage(),
            ]);
        }
    }
}