<?php

namespace Controllers;

use Exception;
use Model\ActiveRecord;
use Model\Comentario;
use Model\ComentarioLeido;
use Model\Aplicacion;
use Model\Usuario;
use MVC\Router;

class ComentarioController extends ActiveRecord {

    public static function renderizarPagina(Router $router) {
        $router->render('comentarios/index', []);
    }

    public static function buscarAPI() {
        try {
            $aplicacion_id = $_GET['aplicacion_id'] ?? null;
            $autor_id = $_GET['autor_id'] ?? null;
            $limite = filter_var($_GET['limite'], FILTER_SANITIZE_NUMBER_INT) ?: null;

            if ($aplicacion_id) {
                $data = Comentario::obtenerPorAplicacion($aplicacion_id, $limite);
            } elseif ($autor_id) {
                $data = Comentario::obtenerPorAutor($autor_id, $limite);
            } else {
                // Obtener todos los comentarios recientes
                $query = "
                    SELECT c.*, a.apl_nombre, u.usu_nombre, u.usu_grado,
                           (SELECT COUNT(*) FROM comentario_leido cl 
                            WHERE cl.col_com_id = c.com_id AND cl.col_situacion = 1) as total_lecturas
                    FROM comentario c
                    JOIN aplicacion a ON c.com_apl_id = a.apl_id
                    JOIN usuario u ON c.com_autor_id = u.usu_id
                    WHERE c.com_situacion = 1 AND a.apl_situacion = 1 AND u.usu_situacion = 1
                    ORDER BY c.com_creado_en DESC
                ";
                
                if ($limite) {
                    $query .= " LIMIT " . intval($limite);
                }
                
                $data = Comentario::fetchArray($query);
            }

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Comentarios obtenidos correctamente' : 'No hay comentarios',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener los comentarios',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function guardarAPI()
    {
        getHeadersApi();

        // Validar aplicación
        if (empty($_POST['com_apl_id'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La aplicación es obligatoria'
            ]);
            return;
        }

        // Validar autor
        if (empty($_POST['com_autor_id'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El autor es obligatorio'
            ]);
            return;
        }

        // Validar texto del comentario
        $texto = trim($_POST['com_texto'] ?? '');
        if (strlen($texto) < 3) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El comentario debe tener al menos 3 caracteres'
            ]);
            return;
        }

        if (strlen($texto) > 1200) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El comentario no puede tener más de 1200 caracteres'
            ]);
            return;
        }

        // Validar número máximo de menciones
        preg_match_all(Comentario::PATRON_MENCION, $texto, $matches);
        $menciones = array_unique($matches[1] ?? []);
        
        if (count($menciones) > Comentario::MAX_MENCIONES) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'No se pueden mencionar más de ' . Comentario::MAX_MENCIONES . ' usuarios por comentario'
            ]);
            return;
        }

        // Verificar que la aplicación existe y está activa
        $aplicacion = Aplicacion::fetchFirst("SELECT apl_estado FROM aplicacion WHERE apl_id = " . $_POST['com_apl_id'] . " AND apl_situacion = 1");
        
        if (!$aplicacion) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La aplicación no existe o no está activa'
            ]);
            return;
        }

        // Verificar que el autor existe y está activo
        $usuario = Usuario::fetchFirst("SELECT usu_activo FROM usuario WHERE usu_id = " . $_POST['com_autor_id'] . " AND usu_situacion = 1");
        
        if (!$usuario || !$usuario['usu_activo']) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El usuario autor no existe o no está activo'
            ]);
            return;
        }

        try {
            $data = new Comentario([
                'com_apl_id' => $_POST['com_apl_id'],
                'com_autor_id' => $_POST['com_autor_id'],
                'com_texto' => htmlspecialchars($texto),
                'com_creado_en' => date('Y-m-d H:i:s')
            ]);

            $crear = $data->crear();

            if ($crear['resultado']) {
                // Procesar menciones
                $data->com_id = $crear['id'];
                $notificaciones = $data->enviarNotificacionesMenciones();

                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => 'El comentario ha sido guardado correctamente',
                    'data' => [
                        'comentario_id' => $crear['id'],
                        'menciones' => $notificaciones
                    ]
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Error al crear el comentario'
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

        $id = $_POST['com_id'];

        // Validar texto del comentario
        $texto = trim($_POST['com_texto'] ?? '');
        if (strlen($texto) < 3) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El comentario debe tener al menos 3 caracteres'
            ]);
            return;
        }

        if (strlen($texto) > 1200) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El comentario no puede tener más de 1200 caracteres'
            ]);
            return;
        }

        try {
            $data = Comentario::find($id);
            
            if (!$data) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Comentario no encontrado'
                ]);
                return;
            }

            // Verificar si se puede editar (dentro de los primeros 5 minutos)
            if (!$data->puedeEliminar()) { // Usamos puedeEliminar que tiene la lógica de tiempo
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'No se puede editar el comentario después de 5 minutos'
                ]);
                return;
            }

            $data->sincronizar([
                'com_texto' => htmlspecialchars($texto)
            ]);
            
            $resultado = $data->actualizar();

            // Reenviar notificaciones si hay nuevas menciones
            $notificaciones = $data->enviarNotificacionesMenciones();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'El comentario ha sido modificado exitosamente',
                'data' => [
                    'menciones' => $notificaciones
                ]
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
            $id = filter_var($_GET['com_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de comentario requerido'
                ]);
                return;
            }

            $comentario = Comentario::find($id);
            
            if (!$comentario) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Comentario no encontrado'
                ]);
                return;
            }

            // Verificar si se puede eliminar (dentro de los primeros 10 minutos)
            if (!$comentario->puedeEliminar()) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Solo se puede eliminar el comentario dentro de los primeros 10 minutos'
                ]);
                return;
            }

            // Soft delete
            $comentario->sincronizar(['com_situacion' => 0]);
            $resultado = $comentario->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'El comentario ha sido eliminado correctamente'
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

    public static function marcarComoLeidoAPI()
    {
        getHeadersApi();

        try {
            $comentario_id = $_POST['com_id'];
            $usuario_id = $_POST['usu_id'];

            if (!$comentario_id || !$usuario_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de comentario y usuario son requeridos'
                ]);
                return;
            }

            $comentario = new Comentario(['com_id' => $comentario_id]);
            $resultado = $comentario->marcarComoLeido($usuario_id);

            if ($resultado['resultado']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => $resultado['mensaje']
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => $resultado['mensaje']
                ]);
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al marcar como leído',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerConversacionAPI() {
        try {
            $aplicacion_id = filter_var($_GET['aplicacion_id'], FILTER_SANITIZE_NUMBER_INT);
            $limite = filter_var($_GET['limite'], FILTER_SANITIZE_NUMBER_INT) ?: 50;

            if (!$aplicacion_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de aplicación requerido'
                ]);
                return;
            }

            $data = Comentario::obtenerConversacionCompleta($aplicacion_id, $limite);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Conversación obtenida correctamente' : 'No hay comentarios en esta conversación',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener la conversación',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerNoLeidosAPI() {
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

            $data = Comentario::obtenerNoLeidosPorUsuario($usuario_id);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Comentarios no leídos obtenidos' : 'No hay comentarios pendientes',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener comentarios no leídos',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerConMencionesAPI() {
        try {
            $usuario_id = filter_var($_GET['usuario_id'], FILTER_SANITIZE_NUMBER_INT);
            $limite = filter_var($_GET['limite'], FILTER_SANITIZE_NUMBER_INT) ?: null;

            if (!$usuario_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de usuario requerido'
                ]);
                return;
            }

            $data = Comentario::obtenerConMenciones($usuario_id, $limite);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Comentarios con menciones obtenidos' : 'No hay menciones para este usuario',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener comentarios con menciones',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function contarNoLeidosPorAplicacionAPI() {
        try {
            $usuario_id = filter_var($_GET['usuario_id'], FILTER_SANITIZE_NUMBER_INT);
            $aplicacion_id = filter_var($_GET['aplicacion_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$usuario_id || !$aplicacion_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de usuario y aplicación son requeridos'
                ]);
                return;
            }

            $total = Comentario::contarNoLeidosPorAplicacion($usuario_id, $aplicacion_id);

            $data = [
                'aplicacion_id' => $aplicacion_id,
                'usuario_id' => $usuario_id,
                'comentarios_no_leidos' => $total
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Conteo obtenido correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al contar comentarios no leídos',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function buscarPorTextoAPI() {
        try {
            $texto_busqueda = $_GET['texto'] ?? '';
            $aplicacion_id = filter_var($_GET['aplicacion_id'], FILTER_SANITIZE_NUMBER_INT) ?: null;
            $limite = filter_var($_GET['limite'], FILTER_SANITIZE_NUMBER_INT) ?: 20;

            if (strlen($texto_busqueda) < 2) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'El texto de búsqueda debe tener al menos 2 caracteres'
                ]);
                return;
            }

            $data = Comentario::buscarPorTexto($texto_busqueda, $aplicacion_id, $limite);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Comentarios encontrados' : 'No se encontraron comentarios',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al buscar comentarios',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerEstadisticasAplicacionAPI() {
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

            $data = Comentario::obtenerEstadisticasAplicacion($aplicacion_id, $dias);

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

    public static function marcarMultiplesLeidosAPI()
    {
        getHeadersApi();

        try {
            $comentarios_ids = $_POST['comentarios_ids'] ?? [];
            $usuario_id = $_POST['usuario_id'];

            if (!$usuario_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de usuario requerido'
                ]);
                return;
            }

            if (empty($comentarios_ids) || !is_array($comentarios_ids)) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Lista de IDs de comentarios requerida'
                ]);
                return;
            }

            $resultado = Comentario::marcarMultiplesComoLeidos($comentarios_ids, $usuario_id);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Comentarios marcados como leídos exitosamente',
                'data' => $resultado
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al marcar comentarios como leídos',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function generarResumenActividadAPI() {
        try {
            $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            $data = Comentario::generarResumenActividad($fecha_desde, $fecha_hasta);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Resumen de actividad generado correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al generar resumen de actividad',
                'detalle' => $e->getMessage(),
            ]);
        }
    }