<?php

namespace Controllers;

use Exception;
use Model\ActiveRecord;
use Model\Rol;
use Model\Usuario;
use MVC\Router;

class RolController extends ActiveRecord {

    public static function renderizarPagina(Router $router) {
        $router->render('roles/index', []);
    }

    public static function buscarAPI() {
        try {
            $data = Rol::fetchArray("
                SELECT r.*, 
                       COUNT(u.usu_id) as total_usuarios
                FROM rol r
                LEFT JOIN usuario u ON r.rol_id = u.usu_rol_id AND u.usu_situacion = 1
                WHERE r.rol_situacion = 1
                GROUP BY r.rol_id, r.rol_nombre, r.rol_situacion
                ORDER BY r.rol_nombre
            ");

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Roles obtenidos correctamente' : 'No hay roles registrados',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener los roles',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function guardarAPI()
    {
        getHeadersApi();

        // Sanitizar y validar nombre
        $nombre = trim($_POST['rol_nombre'] ?? '');
        $nombre = htmlspecialchars($nombre);

        if (strlen($nombre) < 2) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre del rol debe tener al menos 2 caracteres'
            ]);
            return;
        }

        if (strlen($nombre) > 30) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre del rol no puede tener más de 30 caracteres'
            ]);
            return;
        }

        // Verificar que no exista rol con el mismo nombre
        $existe = Rol::fetchFirst("
            SELECT rol_id FROM rol 
            WHERE UPPER(rol_nombre) = UPPER('$nombre') 
            AND rol_situacion = 1
        ");

        if ($existe) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Ya existe un rol con este nombre'
            ]);
            return;
        }

        try {
            $data = new Rol([
                'rol_nombre' => $nombre,
                'rol_situacion' => 1
            ]);

            $crear = $data->crear();

            if ($crear['resultado']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => 'El rol ha sido registrado correctamente'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Error al crear el rol'
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

        $id = $_POST['rol_id'];
        
        // Sanitizar y validar nombre
        $nombre = trim($_POST['rol_nombre'] ?? '');
        $nombre = htmlspecialchars($nombre);

        if (strlen($nombre) < 2) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre del rol debe tener al menos 2 caracteres'
            ]);
            return;
        }

        if (strlen($nombre) > 30) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre del rol no puede tener más de 30 caracteres'
            ]);
            return;
        }

        // Verificar que no exista otro rol con el mismo nombre
        $existe = Rol::fetchFirst("
            SELECT rol_id FROM rol 
            WHERE UPPER(rol_nombre) = UPPER('$nombre') 
            AND rol_id != $id
            AND rol_situacion = 1
        ");

        if ($existe) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Ya existe otro rol con este nombre'
            ]);
            return;
        }

        try {
            $data = Rol::find($id);
            
            if (!$data) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Rol no encontrado'
                ]);
                return;
            }

            $data->sincronizar([
                'rol_nombre' => $nombre
            ]);
            
            $resultado = $data->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'El rol ha sido modificado exitosamente'
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
            $id = filter_var($_GET['rol_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de rol requerido'
                ]);
                return;
            }

            $rol = Rol::find($id);
            
            if (!$rol) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Rol no encontrado'
                ]);
                return;
            }

            // Verificar si se puede eliminar (no tiene usuarios asignados)
            if (!$rol->puedeEliminar()) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'No se puede eliminar el rol porque tiene usuarios asignados'
                ]);
                return;
            }

            // Soft delete
            $rol->sincronizar(['rol_situacion' => 0]);
            $resultado = $rol->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'El rol ha sido eliminado correctamente'
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

    public static function obtenerParaSelectAPI() {
        try {
            $data = Rol::obtenerRolesParaSelect();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Roles para select obtenidos correctamente' : 'No hay roles activos',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener los roles',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerUsuariosDelRolAPI() {
        try {
            $rol_id = filter_var($_GET['rol_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$rol_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de rol requerido'
                ]);
                return;
            }

            $rol = new Rol(['rol_id' => $rol_id]);
            $data = $rol->obtenerUsuarios();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Usuarios del rol obtenidos correctamente' : 'No hay usuarios asignados a este rol',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener usuarios del rol',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function contarUsuariosAPI() {
        try {
            $rol_id = filter_var($_GET['rol_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$rol_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de rol requerido'
                ]);
                return;
            }

            $rol = new Rol(['rol_id' => $rol_id]);
            $total = $rol->contarUsuarios();

            $data = [
                'rol_id' => $rol_id,
                'total_usuarios' => $total
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Conteo de usuarios obtenido correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al contar usuarios',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerDetalleAPI() {
        try {
            $rol_id = filter_var($_GET['rol_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$rol_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de rol requerido'
                ]);
                return;
            }

            // Obtener datos básicos del rol
            $rol_datos = Rol::fetchFirst("
                SELECT r.*,
                       COUNT(u.usu_id) as total_usuarios,
                       COUNT(CASE WHEN u.usu_activo = true THEN 1 END) as usuarios_activos
                FROM rol r
                LEFT JOIN usuario u ON r.rol_id = u.usu_rol_id AND u.usu_situacion = 1
                WHERE r.rol_id = $rol_id AND r.rol_situacion = 1
                GROUP BY r.rol_id, r.rol_nombre, r.rol_situacion
            ");

            if (!$rol_datos) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Rol no encontrado'
                ]);
                return;
            }

            // Obtener usuarios del rol
            $rol = new Rol(['rol_id' => $rol_id]);
            $usuarios = $rol->obtenerUsuarios();

            // Obtener estadísticas adicionales
            $estadisticas = [
                'aplicaciones_como_responsable' => 0,
                'avances_ultimos_30_dias' => 0,
                'comentarios_ultimos_30_dias' => 0
            ];

            if ($usuarios) {
                $usuario_ids = array_column($usuarios, 'usu_id');
                $ids_string = implode(',', $usuario_ids);

                // Aplicaciones como responsable
                $apps_responsable = Usuario::fetchArray("
                    SELECT COUNT(*) as total 
                    FROM aplicacion 
                    WHERE apl_responsable IN ($ids_string) AND apl_situacion = 1
                ");
                $estadisticas['aplicaciones_como_responsable'] = $apps_responsable[0]['total'] ?? 0;

                // Avances últimos 30 días
                $avances_30d = Usuario::fetchArray("
                    SELECT COUNT(*) as total 
                    FROM avance_diario 
                    WHERE ava_usu_id IN ($ids_string) 
                    AND ava_fecha >= '" . date('Y-m-d', strtotime('-30 days')) . "'
                    AND ava_situacion = 1
                ");
                $estadisticas['avances_ultimos_30_dias'] = $avances_30d[0]['total'] ?? 0;

                // Comentarios últimos 30 días
                $comentarios_30d = Usuario::fetchArray("
                    SELECT COUNT(*) as total 
                    FROM comentario 
                    WHERE com_autor_id IN ($ids_string) 
                    AND com_creado_en >= '" . date('Y-m-d H:i:s', strtotime('-30 days')) . "'
                    AND com_situacion = 1
                ");
                $estadisticas['comentarios_ultimos_30_dias'] = $comentarios_30d[0]['total'] ?? 0;
            }

            $data = [
                'rol' => $rol_datos,
                'usuarios' => $usuarios,
                'estadisticas' => $estadisticas
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Detalle del rol obtenido correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener el detalle del rol',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function verificarPermisosCRUD() {
        try {
            // Definir qué roles pueden hacer qué acciones
            $permisos_roles = [
                'GERENTE' => [
                    'crear' => true,
                    'editar' => true,
                    'eliminar' => true,
                    'ver' => true
                ],
                'SUBGERENTE' => [
                    'crear' => true,
                    'editar' => true,
                    'eliminar' => false,
                    'ver' => true
                ],
                'DESARROLLADOR' => [
                    'crear' => false,
                    'editar' => false,
                    'eliminar' => false,
                    'ver' => true
                ],
                'ADMINISTRADOR' => [
                    'crear' => true,
                    'editar' => true,
                    'eliminar' => true,
                    'ver' => true
                ]
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Permisos de roles obtenidos correctamente',
                'data' => $permisos_roles
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener permisos',
                'detalle' => $e->getMessage(),
            ]);
        }
    }
}