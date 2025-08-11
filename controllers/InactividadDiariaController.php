<?php

namespace Controllers;

use Exception;
use Model\ActiveRecord;
use Model\InactividadDiaria;
use Model\AvanceDiario;
use Model\Aplicacion;
use Model\Usuario;
use MVC\Router;

class InactividadDiariaController extends ActiveRecord {

    public static function renderizarPagina(Router $router) {
        $router->render('inactividad/index', []);
    }

    public static function buscarAPI() {
        try {
            $usuario_id = $_GET['usuario_id'] ?? null;
            $aplicacion_id = $_GET['aplicacion_id'] ?? null;
            $fecha = $_GET['fecha'] ?? null;
            $tipo = $_GET['tipo'] ?? null;

            $query = "
                SELECT ina.*, a.apl_nombre, u.usu_nombre, u.usu_grado
                FROM inactividad_diaria ina
                JOIN aplicacion a ON ina.ina_apl_id = a.apl_id
                JOIN usuario u ON ina.ina_usu_id = u.usu_id
                WHERE ina.ina_situacion = 1 
                AND a.apl_situacion = 1 
                AND u.usu_situacion = 1
            ";

            $params = [];

            if ($usuario_id) {
                $query .= " AND ina.ina_usu_id = ?";
                $params[] = $usuario_id;
            }

            if ($aplicacion_id) {
                $query .= " AND ina.ina_apl_id = ?";
                $params[] = $aplicacion_id;
            }

            if ($fecha) {
                $query .= " AND ina.ina_fecha = ?";
                $params[] = $fecha;
            }

            if ($tipo) {
                $query .= " AND ina.ina_tipo = ?";
                $params[] = $tipo;
            }

            $query .= " ORDER BY ina.ina_fecha DESC, ina.ina_creado_en DESC";

            $data = InactividadDiaria::fetchArray($query, $params);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Registros de inactividad obtenidos correctamente' : 'No hay registros de inactividad',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener los registros de inactividad',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function guardarAPI()
    {
        getHeadersApi();

        // Validar aplicación
        if (empty($_POST['ina_apl_id'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La aplicación es obligatoria'
            ]);
            return;
        }

        // Validar usuario
        if (empty($_POST['ina_usu_id'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El usuario es obligatorio'
            ]);
            return;
        }

        // Validar fecha
        if (empty($_POST['ina_fecha'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La fecha es obligatoria'
            ]);
            return;
        }

        // Validar que la fecha no sea futura
        if ($_POST['ina_fecha'] > date('Y-m-d')) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'No se puede registrar inactividad para fechas futuras'
            ]);
            return;
        }

        // Validar motivo
        $motivo = trim($_POST['ina_motivo'] ?? '');
        if (strlen($motivo) < 10) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El motivo es obligatorio y debe tener al menos 10 caracteres'
            ]);
            return;
        }

        if (strlen($motivo) > 500) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El motivo no puede tener más de 500 caracteres'
            ]);
            return;
        }

        // Validar tipo
        if (empty($_POST['ina_tipo'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El tipo de inactividad es obligatorio'
            ]);
            return;
        }

        $tipos_validos = InactividadDiaria::TIPOS_VALIDOS;
        if (!in_array($_POST['ina_tipo'], $tipos_validos)) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El tipo debe ser uno de: ' . implode(', ', $tipos_validos)
            ]);
            return;
        }

        // Verificar que no exista inactividad para esta fecha
        $existe = InactividadDiaria::existeInactividadDiaria(
            $_POST['ina_apl_id'], 
            $_POST['ina_usu_id'], 
            $_POST['ina_fecha']
        );

        if ($existe) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Ya existe un registro de inactividad para esta fecha en esta aplicación'
            ]);
            return;
        }

        // Verificar que no haya avance en la misma fecha
        $hay_avance = InactividadDiaria::hayAvanceEnFecha(
            $_POST['ina_apl_id'], 
            $_POST['ina_usu_id'], 
            $_POST['ina_fecha']
        );

        if ($hay_avance) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'No se puede registrar inactividad porque ya hay un avance registrado para esta fecha'
            ]);
            return;
        }

        // Verificar que la aplicación existe y está activa
        $aplicacion = Aplicacion::fetchFirst("SELECT apl_estado FROM aplicacion WHERE apl_id = " . $_POST['ina_apl_id'] . " AND apl_situacion = 1");
        
        if (!$aplicacion) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La aplicación no existe o no está activa'
            ]);
            return;
        }

        // Verificar que el usuario existe y está activo
        $usuario = Usuario::fetchFirst("SELECT usu_activo FROM usuario WHERE usu_id = " . $_POST['ina_usu_id'] . " AND usu_situacion = 1");
        
        if (!$usuario || !$usuario['usu_activo']) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El usuario no existe o no está activo'
            ]);
            return;
        }

        try {
            $data = new InactividadDiaria([
                'ina_apl_id' => $_POST['ina_apl_id'],
                'ina_usu_id' => $_POST['ina_usu_id'],
                'ina_fecha' => $_POST['ina_fecha'],
                'ina_motivo' => htmlspecialchars($motivo),
                'ina_tipo' => $_POST['ina_tipo'],
                'ina_creado_en' => date('Y-m-d H:i:s')
            ]);

            $crear = $data->crear();

            if ($crear['resultado']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => 'El registro de inactividad ha sido guardado correctamente'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Error al crear el registro de inactividad'
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

        $id = $_POST['ina_id'];

        // Validar motivo
        $motivo = trim($_POST['ina_motivo'] ?? '');
        if (strlen($motivo) < 10) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El motivo es obligatorio y debe tener al menos 10 caracteres'
            ]);
            return;
        }

        if (strlen($motivo) > 500) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El motivo no puede tener más de 500 caracteres'
            ]);
            return;
        }

        // Validar tipo
        if (empty($_POST['ina_tipo'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El tipo de inactividad es obligatorio'
            ]);
            return;
        }

        $tipos_validos = InactividadDiaria::TIPOS_VALIDOS;
        if (!in_array($_POST['ina_tipo'], $tipos_validos)) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El tipo debe ser uno de: ' . implode(', ', $tipos_validos)
            ]);
            return;
        }

        try {
            $data = InactividadDiaria::find($id);
            
            if (!$data) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Registro de inactividad no encontrado'
                ]);
                return;
            }

            $data->sincronizar([
                'ina_motivo' => htmlspecialchars($motivo),
                'ina_tipo' => $_POST['ina_tipo']
            ]);
            
            $resultado = $data->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'El registro de inactividad ha sido modificado exitosamente'
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
            $id = filter_var($_GET['ina_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de registro requerido'
                ]);
                return;
            }

            $registro = InactividadDiaria::find($id);
            
            if (!$registro) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Registro de inactividad no encontrado'
                ]);
                return;
            }

            // Verificar si se puede eliminar (solo el mismo día)
            if (!$registro->puedeEliminar()) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Solo se puede eliminar el registro el mismo día de creación'
                ]);
                return;
            }

            // Soft delete
            $registro->sincronizar(['ina_situacion' => 0]);
            $resultado = $registro->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'El registro de inactividad ha sido eliminado correctamente'
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

    public static function obtenerTiposValidosAPI() {
        try {
            $tipos = InactividadDiaria::TIPOS_VALIDOS;
            
            $data = [];
            foreach ($tipos as $tipo) {
                $data[] = [
                    'valor' => $tipo,
                    'texto' => ucwords(strtolower(str_replace('_', ' ', $tipo)))
                ];
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Tipos de inactividad obtenidos correctamente',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener los tipos',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerEstadisticasAPI() {
        try {
            $usuario_id = $_GET['usuario_id'] ?? null;
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            if ($usuario_id) {
                $data = InactividadDiaria::obtenerEstadisticasUsuario($usuario_id, 30);
            } else {
                $data = InactividadDiaria::obtenerEstadisticasGenerales($fecha_desde, $fecha_hasta);
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Estadísticas obtenidas correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener estadísticas',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function detectarPatronesSospechososAPI() {
        try {
            $dias_limite = filter_var($_GET['dias_limite'], FILTER_SANITIZE_NUMBER_INT) ?: 7;

            $data = InactividadDiaria::detectarPatronesSospechosos($dias_limite);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Patrones sospechosos detectados' : 'No se detectaron patrones sospechosos',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al detectar patrones',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function calcularImpactoSemaforoAPI() {
        try {
            $aplicacion_id = filter_var($_GET['aplicacion_id'], FILTER_SANITIZE_NUMBER_INT);
            $fecha = $_GET['fecha'] ?? date('Y-m-d');

            if (!$aplicacion_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de aplicación requerido'
                ]);
                return;
            }

            $data = InactividadDiaria::calcularImpactoSemaforo($aplicacion_id, $fecha);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Impacto en semáforo calculado correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al calcular impacto',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerPorFechaAPI() {
        try {
            $fecha = $_GET['fecha'] ?? date('Y-m-d');

            $data = InactividadDiaria::obtenerPorFecha($fecha);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Registros de inactividad obtenidos para la fecha' : 'No hay registros para esta fecha',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener registros por fecha',
                'detalle' => $e->getMessage(),
            ]);
        }
    }
}