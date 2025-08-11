<?php

namespace Controllers;

use Exception;
use Model\ActiveRecord;
use Model\Aplicacion;
use Model\Usuario;
use Model\AvanceDiario;
use Model\InactividadDiaria;
use Model\Comentario;
use MVC\Router;

class AplicacionController extends ActiveRecord {

    public static function renderizarPagina(Router $router) {
        $router->render('aplicaciones/index', []);
    }

public static function buscarAPI() {
    try {
 
        $aplicaciones = Aplicacion::fetchArray("
            SELECT a.apl_id, a.apl_nombre, a.apl_estado, a.apl_fecha_inicio,
                   a.apl_responsable, a.apl_situacion,
                   u.usu_nombre as responsable_nombre, u.usu_grado
            FROM aplicacion a
            LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
            WHERE a.apl_situacion = 1
            ORDER BY a.apl_estado, a.apl_fecha_inicio DESC
        ");

        // Agregar datos por defecto
        foreach ($aplicaciones as &$app) {
            $app['ultimo_avance'] = null;
            $app['porcentaje_actual'] = 0;
            $app['semaforo'] = 'AMBAR'; 
        }

        http_response_code(200);
        echo json_encode([
            'codigo' => 1,
            'mensaje' => count($aplicaciones) > 0 ? 'Aplicaciones obtenidas correctamente' : 'No hay aplicaciones registradas',
            'data' => $aplicaciones
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'codigo' => 0,
            'mensaje' => 'Error al obtener las aplicaciones',
            'detalle' => $e->getMessage(),
        ]);
    }
}

    public static function guardarAPI()
    {
        getHeadersApi();

        // Sanitizar nombre
        $_POST['apl_nombre'] = htmlspecialchars($_POST['apl_nombre']);

        $cantidad_nombre = strlen($_POST['apl_nombre']);

        if ($cantidad_nombre < 3) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre de la aplicación debe tener al menos 3 caracteres'
            ]);
            return;
        }

        if ($cantidad_nombre > 120) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre de la aplicación no puede tener más de 120 caracteres'
            ]);
            return;
        }

        // Validar fecha de inicio
        if (empty($_POST['apl_fecha_inicio'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La fecha de inicio es obligatoria'
            ]);
            return;
        }

        // Descripción (opcional)
        if (!empty($_POST['apl_descripcion'])) {
            $_POST['apl_descripcion'] = htmlspecialchars($_POST['apl_descripcion']);
            
            if (strlen($_POST['apl_descripcion']) > 500) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'La descripción no puede tener más de 500 caracteres'
                ]);
                return;
            }
        }

        // Validar fechas
        if (!empty($_POST['apl_fecha_fin']) && !empty($_POST['apl_fecha_inicio'])) {
            if (strtotime($_POST['apl_fecha_fin']) < strtotime($_POST['apl_fecha_inicio'])) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'La fecha de fin debe ser posterior a la fecha de inicio'
                ]);
                return;
            }
        }

        // Validar porcentaje objetivo
        if (!empty($_POST['apl_porcentaje_objetivo'])) {
            $porcentaje = filter_var($_POST['apl_porcentaje_objetivo'], FILTER_SANITIZE_NUMBER_INT);
            if ($porcentaje < 0 || $porcentaje > 100) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'El porcentaje objetivo debe estar entre 0 y 100'
                ]);
                return;
            }
        }

        try {
            $data = new Aplicacion([
                'apl_nombre' => $_POST['apl_nombre'],
                'apl_descripcion' => $_POST['apl_descripcion'] ?? '',
                'apl_fecha_inicio' => $_POST['apl_fecha_inicio'],
                'apl_fecha_fin' => $_POST['apl_fecha_fin'] ?? null,
                'apl_porcentaje_objetivo' => $_POST['apl_porcentaje_objetivo'] ?? null,
                'apl_estado' => $_POST['apl_estado'] ?? 'EN_PLANIFICACION',
                'apl_responsable' => $_POST['apl_responsable'] ?? null,
                'apl_creado_en' => date('Y-m-d H:i:s')
            ]);

            $crear = $data->crear();

            if ($crear['resultado']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => 'La aplicación ha sido registrada correctamente'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Error al crear la aplicación'
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

    public static function obtenerParaSelectAPI() {
        try {
            $data = Aplicacion::fetchArray("
                SELECT apl_id, apl_nombre, apl_estado 
                FROM aplicacion 
                WHERE apl_situacion = 1 
                AND apl_estado IN ('EN_PLANIFICACION', 'EN_PROGRESO')
                ORDER BY apl_nombre
            ");

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Aplicaciones para select obtenidas correctamente' : 'No hay aplicaciones activas',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener las aplicaciones',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerDetalleAPI() {
        try {
            $apl_id = filter_var($_GET['apl_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$apl_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de aplicación requerido'
                ]);
                return;
            }

            // Obtener datos básicos de la aplicación
            $aplicacion = Aplicacion::fetchFirst("
                SELECT a.*, u.usu_nombre as responsable_nombre, u.usu_grado, u.usu_email
                FROM aplicacion a
                LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                WHERE a.apl_id = $apl_id AND a.apl_situacion = 1
            ");

            if (!$aplicacion) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Aplicación no encontrada'
                ]);
                return;
            }

            // Obtener estadísticas básicas
            $estadisticas = [
                'total_avances' => 0,
                'ultimo_porcentaje' => 0,
                'dias_activo' => 0
            ];
            
            // Obtener últimos avances
            $ultimos_avances = AvanceDiario::fetchArray("
                SELECT ava_fecha, ava_porcentaje, ava_resumen, ava_bloqueadores
                FROM avance_diario
                WHERE ava_apl_id = ? AND ava_situacion = 1
                ORDER BY ava_fecha DESC
                LIMIT 10
            ", [$apl_id]);
            
            // Obtener comentarios recientes (simplificado)
            $comentarios_recientes = [];
            
            // Calcular semáforo simple
            $hoy = date('Y-m-d');
            $tiene_reporte = AvanceDiario::fetchArray("
                SELECT COUNT(*) as total
                FROM avance_diario 
                WHERE ava_apl_id = ? AND ava_fecha = ? AND ava_situacion = 1
            ", [$apl_id, $hoy]);
            
            $semaforo = (($tiene_reporte[0]['total'] ?? 0) > 0) ? 'VERDE' : 'AMBAR';

            $data = [
                'aplicacion' => $aplicacion,
                'estadisticas' => $estadisticas,
                'ultimos_avances' => $ultimos_avances,
                'comentarios_recientes' => $comentarios_recientes,
                'semaforo' => $semaforo
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Detalle de aplicación obtenido correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener el detalle de la aplicación',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function modificarAPI()
    {
        getHeadersApi();

        $id = $_POST['apl_id'];
        
        // Sanitizar nombre
        $_POST['apl_nombre'] = htmlspecialchars($_POST['apl_nombre']);

        $cantidad_nombre = strlen($_POST['apl_nombre']);

        if ($cantidad_nombre < 3) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre de la aplicación debe tener al menos 3 caracteres'
            ]);
            return;
        }

        if ($cantidad_nombre > 120) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre de la aplicación no puede tener más de 120 caracteres'
            ]);
            return;
        }

        // Validar fecha de inicio
        if (empty($_POST['apl_fecha_inicio'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La fecha de inicio es obligatoria'
            ]);
            return;
        }

        // Descripción (opcional)
        if (!empty($_POST['apl_descripcion'])) {
            $_POST['apl_descripcion'] = htmlspecialchars($_POST['apl_descripcion']);
            
            if (strlen($_POST['apl_descripcion']) > 500) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'La descripción no puede tener más de 500 caracteres'
                ]);
                return;
            }
        }

        try {
            $data = Aplicacion::find($id);
            
            if (!$data) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Aplicación no encontrada'
                ]);
                return;
            }

            $data->sincronizar([
                'apl_nombre' => $_POST['apl_nombre'],
                'apl_descripcion' => $_POST['apl_descripcion'] ?? '',
                'apl_fecha_inicio' => $_POST['apl_fecha_inicio'],
                'apl_fecha_fin' => $_POST['apl_fecha_fin'] ?? null,
                'apl_porcentaje_objetivo' => $_POST['apl_porcentaje_objetivo'] ?? null,
                'apl_estado' => $_POST['apl_estado'] ?? $data->apl_estado,
                'apl_responsable' => $_POST['apl_responsable'] ?? $data->apl_responsable
            ]);
            
            $resultado = $data->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'La aplicación ha sido modificada exitosamente'
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
            $id = filter_var($_GET['apl_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de aplicación requerido'
                ]);
                return;
            }

            $aplicacion = Aplicacion::find($id);
            
            if (!$aplicacion) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Aplicación no encontrada'
                ]);
                return;
            }

            // Verificar si se puede eliminar
            if (!$aplicacion->puedeEliminar()) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'No se puede eliminar la aplicación porque tiene avances o comentarios asociados'
                ]);
                return;
            }

            // Soft delete
            $aplicacion->sincronizar(['apl_situacion' => 0]);
            $resultado = $aplicacion->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'La aplicación ha sido eliminada correctamente'
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

    public static function cambiarEstadoAPI()
    {
        getHeadersApi();

        try {
            $id = $_POST['apl_id'];
            $nuevo_estado = $_POST['apl_estado'];

            $estados_validos = ['EN_PLANIFICACION', 'EN_PROGRESO', 'PAUSADO', 'CERRADO'];
            
            if (!in_array($nuevo_estado, $estados_validos)) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Estado no válido. Debe ser: ' . implode(', ', $estados_validos)
                ]);
                return;
            }

            $aplicacion = Aplicacion::find($id);
            
            if (!$aplicacion) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Aplicación no encontrada'
                ]);
                return;
            }

            $aplicacion->sincronizar(['apl_estado' => $nuevo_estado]);
            
            // Si se cierra, establecer fecha de fin si no existe
            if ($nuevo_estado === 'CERRADO' && !$aplicacion->apl_fecha_fin) {
                $aplicacion->sincronizar(['apl_fecha_fin' => date('Y-m-d')]);
            }

            $resultado = $aplicacion->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'El estado de la aplicación ha sido cambiado exitosamente'
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al cambiar el estado',
                'detalle' => $e->getMessage(),
            ]);
        }
    }
}