<?php

namespace Controllers;

use Exception;
use Model\ActiveRecord;
use Model\AvanceDiario;
use Model\Aplicacion;
use Model\Usuario;
use MVC\Router;

class AvanceDiarioController extends ActiveRecord {

    public static function renderizarPagina(Router $router) {
        $router->render('avances/index', []);
    }

    public static function buscarAPI() {
        try {
            $usuario_id = $_GET['usuario_id'] ?? null;
            $aplicacion_id = $_GET['aplicacion_id'] ?? null;
            $fecha = $_GET['fecha'] ?? null;

            $query = "
                SELECT av.*, a.apl_nombre, u.usu_nombre, u.usu_grado
                FROM avance_diario av
                JOIN aplicacion a ON av.ava_apl_id = a.apl_id
                JOIN usuario u ON av.ava_usu_id = u.usu_id
                WHERE av.ava_situacion = 1 
                AND a.apl_situacion = 1 
                AND u.usu_situacion = 1
            ";

            $params = [];

            if ($usuario_id) {
                $query .= " AND av.ava_usu_id = ?";
                $params[] = $usuario_id;
            }

            if ($aplicacion_id) {
                $query .= " AND av.ava_apl_id = ?";
                $params[] = $aplicacion_id;
            }

            if ($fecha) {
                $query .= " AND av.ava_fecha = ?";
                $params[] = $fecha;
            }

            $query .= " ORDER BY av.ava_fecha DESC, av.ava_creado_en DESC";

            $data = AvanceDiario::fetchArray($query, $params);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Avances obtenidos correctamente' : 'No hay avances registrados',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener los avances',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function guardarAPI()
    {
        getHeadersApi();

        // Validar aplicación
        if (empty($_POST['ava_apl_id'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La aplicación es obligatoria'
            ]);
            return;
        }

        // Validar usuario
        if (empty($_POST['ava_usu_id'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El usuario es obligatorio'
            ]);
            return;
        }

        // Validar fecha
        if (empty($_POST['ava_fecha'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La fecha es obligatoria'
            ]);
            return;
        }

        // Validar porcentaje
        $porcentaje = filter_var($_POST['ava_porcentaje'], FILTER_SANITIZE_NUMBER_INT);
        
        if ($porcentaje < 0 || $porcentaje > 100) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El porcentaje debe estar entre 0 y 100'
            ]);
            return;
        }

        // Validar longitud de campos de texto
        if (!empty($_POST['ava_resumen']) && strlen($_POST['ava_resumen']) > 800) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El resumen no puede tener más de 800 caracteres'
            ]);
            return;
        }

        if (!empty($_POST['ava_bloqueadores']) && strlen($_POST['ava_bloqueadores']) > 400) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Los bloqueadores no pueden tener más de 400 caracteres'
            ]);
            return;
        }

        if (!empty($_POST['ava_justificacion']) && strlen($_POST['ava_justificacion']) > 800) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La justificación no puede tener más de 800 caracteres'
            ]);
            return;
        }

        // Verificar si ya existe un avance para esta fecha
        $existe = AvanceDiario::existeAvanceDiario(
            $_POST['ava_apl_id'], 
            $_POST['ava_usu_id'], 
            $_POST['ava_fecha']
        );

        if ($existe) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Ya existe un avance registrado para esta fecha en esta aplicación'
            ]);
            return;
        }

        // Validar regla de monotonía
        $ultimo_avance = AvanceDiario::obtenerUltimoAvance($_POST['ava_apl_id'], $_POST['ava_usu_id']);
        
        if ($ultimo_avance && $porcentaje < $ultimo_avance['ava_porcentaje']) {
            if (empty(trim($_POST['ava_justificacion']))) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'El porcentaje no puede ser menor al anterior (' . $ultimo_avance['ava_porcentaje'] . '%) sin justificación'
                ]);
                return;
            }
        }

        // Verificar que la aplicación esté en progreso
        $aplicacion = Aplicacion::fetchFirst("SELECT apl_estado FROM aplicacion WHERE apl_id = " . $_POST['ava_apl_id'] . " AND apl_situacion = 1");
        
        if (!$aplicacion || $aplicacion['apl_estado'] !== 'EN_PROGRESO') {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Solo se pueden registrar avances en aplicaciones en progreso'
            ]);
            return;
        }

        try {
            $data = new AvanceDiario([
                'ava_apl_id' => $_POST['ava_apl_id'],
                'ava_usu_id' => $_POST['ava_usu_id'],
                'ava_fecha' => $_POST['ava_fecha'],
                'ava_porcentaje' => $porcentaje,
                'ava_resumen' => htmlspecialchars($_POST['ava_resumen'] ?? ''),
                'ava_bloqueadores' => htmlspecialchars($_POST['ava_bloqueadores'] ?? ''),
                'ava_justificacion' => htmlspecialchars($_POST['ava_justificacion'] ?? ''),
                'ava_creado_en' => date('Y-m-d H:i:s')
            ]);

            $crear = $data->crear();

            if ($crear['resultado']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => 'El avance diario ha sido registrado correctamente'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Error al crear el avance diario'
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

        $id = $_POST['ava_id'];

        // Validar porcentaje
        $porcentaje = filter_var($_POST['ava_porcentaje'], FILTER_SANITIZE_NUMBER_INT);
        
        if ($porcentaje < 0 || $porcentaje > 100) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El porcentaje debe estar entre 0 y 100'
            ]);
            return;
        }

        try {
            $data = AvanceDiario::find($id);
            
            if (!$data) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Avance no encontrado'
                ]);
                return;
            }

            // Verificar si se puede editar (antes de las 18:00 del mismo día)
            if (!$data->puedeEditar()) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'No se puede editar este avance. Solo se puede editar el mismo día antes de las 18:00'
                ]);
                return;
            }

            // Validar regla de monotonía
            $ultimo_avance = AvanceDiario::fetchFirst("
                SELECT * FROM avance_diario 
                WHERE ava_apl_id = " . $data->ava_apl_id . " 
                AND ava_usu_id = " . $data->ava_usu_id . " 
                AND ava_id != " . $data->ava_id . " 
                AND ava_situacion = 1
                ORDER BY ava_fecha DESC, ava_creado_en DESC 
                LIMIT 1
            ");

            if ($ultimo_avance && $porcentaje < $ultimo_avance['ava_porcentaje']) {
                if (empty(trim($_POST['ava_justificacion']))) {
                    http_response_code(400);
                    echo json_encode([
                        'codigo' => 0,
                        'mensaje' => 'El porcentaje no puede ser menor al anterior (' . $ultimo_avance['ava_porcentaje'] . '%) sin justificación'
                    ]);
                    return;
                }
            }

            $data->sincronizar([
                'ava_porcentaje' => $porcentaje,
                'ava_resumen' => htmlspecialchars($_POST['ava_resumen'] ?? ''),
                'ava_bloqueadores' => htmlspecialchars($_POST['ava_bloqueadores'] ?? ''),
                'ava_justificacion' => htmlspecialchars($_POST['ava_justificacion'] ?? '')
            ]);
            
            $resultado = $data->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'El avance diario ha sido modificado exitosamente'
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

    public static function verificarReporteHoyAPI() {
        try {
            $usuario_id = filter_var($_GET['usuario_id'], FILTER_SANITIZE_NUMBER_INT);
            $aplicacion_id = filter_var($_GET['aplicacion_id'], FILTER_SANITIZE_NUMBER_INT);
            $fecha = $_GET['fecha'] ?? date('Y-m-d');

            if (!$usuario_id || !$aplicacion_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Usuario y aplicación son requeridos'
                ]);
                return;
            }

            $existe = AvanceDiario::existeAvanceDiario($aplicacion_id, $usuario_id, $fecha);

            $data = [
                'tiene_reporte' => $existe ? true : false,
                'fecha' => $fecha
            ];

            if ($existe) {
                $avance = AvanceDiario::fetchFirst("
                    SELECT * FROM avance_diario 
                    WHERE ava_apl_id = $aplicacion_id 
                    AND ava_usu_id = $usuario_id 
                    AND ava_fecha = '$fecha' 
                    AND ava_situacion = 1
                ");
                $data['avance'] = $avance;
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => $existe ? 'Ya tiene reporte para esta fecha' : 'No tiene reporte para esta fecha',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al verificar el reporte',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerTendenciaAPI() {
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

            $data = AvanceDiario::obtenerTendencia($aplicacion_id, $dias);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Tendencia obtenida correctamente' : 'No hay datos de tendencia',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener la tendencia',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerSemaforoAPI() {
        try {
            $aplicacion_id = filter_var($_GET['aplicacion_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$aplicacion_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de aplicación requerido'
                ]);
                return;
            }

            $semaforo = AvanceDiario::calcularSemaforoAplicacion($aplicacion_id);

            $data = [
                'semaforo' => $semaforo,
                'aplicacion_id' => $aplicacion_id,
                'fecha_calculo' => date('Y-m-d H:i:s')
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Semáforo calculado correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al calcular el semáforo',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerAplicacionesSinReporteAPI() {
        try {
            $fecha = $_GET['fecha'] ?? date('Y-m-d');

            $data = AvanceDiario::obtenerAplicacionesSinReporte($fecha);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Aplicaciones sin reporte obtenidas' : 'Todas las aplicaciones tienen reporte',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener aplicaciones sin reporte',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerEstadisticasUsuarioAPI() {
        try {
            $usuario_id = filter_var($_GET['usuario_id'], FILTER_SANITIZE_NUMBER_INT);
            $dias = filter_var($_GET['dias'], FILTER_SANITIZE_NUMBER_INT) ?: 30;

            if (!$usuario_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de usuario requerido'
                ]);
                return;
            }

            $data = AvanceDiario::obtenerEstadisticasUsuario($usuario_id, $dias);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Estadísticas de usuario obtenidas correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener estadísticas del usuario',
                'detalle' => $e->getMessage(),
            ]);
        }
    }
}