<?php

namespace Controllers;

use Exception;
use Model\ActiveRecord;
use Model\Usuario;
use Model\Rol;
use MVC\Router;

class UsuarioController extends ActiveRecord {

    public static function renderizarPagina(Router $router) {
        $router->render('usuarios/index', []);
    }

    public static function buscarAPI() {
        try {
            //: Usar || en lugar de CONCAT para Informix
            $data = Usuario::fetchArray("
                SELECT u.*, r.rol_nombre,
                       CASE 
                           WHEN u.usu_grado IS NOT NULL AND u.usu_grado != '' 
                           THEN u.usu_grado || ' ' || u.usu_nombre
                           ELSE u.usu_nombre
                       END as nombre_completo
                FROM usuario u
                LEFT JOIN rol r ON u.usu_rol_id = r.rol_id
                WHERE u.usu_situacion = 1
                ORDER BY u.usu_nombre
            ");

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Usuarios obtenidos correctamente' : 'No hay usuarios registrados',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener los usuarios',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function guardarAPI()
    {
        getHeadersApi();

        // Sanitizar nombre
        $_POST['usu_nombre'] = htmlspecialchars($_POST['usu_nombre']);

        $cantidad_nombre = strlen($_POST['usu_nombre']);

        if ($cantidad_nombre < 3) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre del usuario debe tener al menos 3 caracteres'
            ]);
            return;
        }

        if ($cantidad_nombre > 120) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre del usuario no puede tener más de 120 caracteres'
            ]);
            return;
        }

        // Validar email (opcional)
        if (!empty($_POST['usu_email'])) {
            $_POST['usu_email'] = filter_var($_POST['usu_email'], FILTER_SANITIZE_EMAIL);

            if (!filter_var($_POST['usu_email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'El correo electrónico no tiene un formato válido'
                ]);
                return;
            }

            if (strlen($_POST['usu_email']) > 120) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'El email no puede tener más de 120 caracteres'
                ]);
                return;
            }

            // Verificar que no exista otro usuario con el mismo email
            $existe_email = Usuario::fetchFirst("
                SELECT usu_id FROM usuario 
                WHERE UPPER(usu_email) = UPPER('" . $_POST['usu_email'] . "') 
                AND usu_situacion = 1
            ");

            if ($existe_email) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Ya existe un usuario con este email'
                ]);
                return;
            }
        }

        // Validar rol
        if (empty($_POST['usu_rol_id'])) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El rol es obligatorio'
            ]);
            return;
        }

        // Verificar que el rol existe
        $rol_existe = Rol::fetchFirst("SELECT rol_id FROM rol WHERE rol_id = " . $_POST['usu_rol_id'] . " AND rol_situacion = 1");
        if (!$rol_existe) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El rol seleccionado no existe'
            ]);
            return;
        }

        // Validar grado (opcional)
        if (!empty($_POST['usu_grado'])) {
            $_POST['usu_grado'] = htmlspecialchars($_POST['usu_grado']);
            
            if (strlen($_POST['usu_grado']) > 50) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'El grado no puede tener más de 50 caracteres'
                ]);
                return;
            }
        }

        try {
            $data = new Usuario([
                'usu_nombre' => $_POST['usu_nombre'],
                'usu_grado' => $_POST['usu_grado'] ?? '',
                'usu_email' => $_POST['usu_email'] ?? '',
                'usu_rol_id' => $_POST['usu_rol_id'],
                'usu_activo' => $_POST['usu_activo'] ?? true
            ]);

            $crear = $data->crear();

            if ($crear['resultado']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => 'El usuario ha sido registrado correctamente'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Error al crear el usuario'
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
            $solo_activos = $_GET['solo_activos'] ?? true;

            //: Usar || en lugar de CONCAT
            $query = "
                SELECT u.usu_id, u.usu_nombre, u.usu_grado, u.usu_email, r.rol_nombre,
                       CASE 
                           WHEN u.usu_grado IS NOT NULL AND u.usu_grado != '' 
                           THEN u.usu_grado || ' ' || u.usu_nombre
                           ELSE u.usu_nombre
                       END as nombre_completo
                FROM usuario u
                LEFT JOIN rol r ON u.usu_rol_id = r.rol_id
                WHERE u.usu_situacion = 1
            ";

            if ($solo_activos) {
                $query .= " AND u.usu_activo = true";
            }

            $query .= " ORDER BY u.usu_nombre";

            $data = Usuario::fetchArray($query);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Usuarios para select obtenidos correctamente' : 'No hay usuarios activos',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener los usuarios',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerPorRolAPI() {
        try {
            $rol_id = filter_var($_GET['rol_id'], FILTER_SANITIZE_NUMBER_INT);
            $solo_activos = $_GET['solo_activos'] ?? true;

            if (!$rol_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de rol requerido'
                ]);
                return;
            }

            $query = "
                SELECT u.*, r.rol_nombre
                FROM usuario u
                JOIN rol r ON u.usu_rol_id = r.rol_id
                WHERE u.usu_rol_id = $rol_id AND u.usu_situacion = 1
            ";

            if ($solo_activos) {
                $query .= " AND u.usu_activo = true";
            }

            $query .= " ORDER BY u.usu_nombre";

            $data = Usuario::fetchArray($query);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Usuarios por rol obtenidos correctamente' : 'No hay usuarios para este rol',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener usuarios por rol',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function obtenerDetalleAPI() {
        try {
            $usu_id = filter_var($_GET['usu_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$usu_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de usuario requerido'
                ]);
                return;
            }

            // Obtener datos básicos del usuario
            $usuario = Usuario::fetchFirst("
                SELECT u.*, r.rol_nombre
                FROM usuario u
                LEFT JOIN rol r ON u.usu_rol_id = r.rol_id
                WHERE u.usu_id = $usu_id AND u.usu_situacion = 1
            ");

            if (!$usuario) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Usuario no encontrado'
                ]);
                return;
            }

            // Obtener estadísticas del usuario
            $obj_usuario = new Usuario(['usu_id' => $usu_id]);
            $estadisticas = $obj_usuario->obtenerEstadisticas();
            
            // Obtener aplicaciones como responsable
            $aplicaciones_responsable = $obj_usuario->obtenerAplicacionesResponsable();
            
            // Obtener aplicaciones con avances
            $aplicaciones_con_avances = $obj_usuario->obtenerAplicacionesConAvances();
            
            // Obtener avances recientes
            $avances_recientes = $obj_usuario->obtenerAvancesRecientes(10);

            $data = [
                'usuario' => $usuario,
                'estadisticas' => $estadisticas,
                'aplicaciones_responsable' => $aplicaciones_responsable,
                'aplicaciones_con_avances' => $aplicaciones_con_avances,
                'avances_recientes' => $avances_recientes
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Detalle de usuario obtenido correctamente',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener el detalle del usuario',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function modificarAPI()
    {
        getHeadersApi();

        $id = $_POST['usu_id'];
        
        // Sanitizar nombre
        $_POST['usu_nombre'] = htmlspecialchars($_POST['usu_nombre']);

        $cantidad_nombre = strlen($_POST['usu_nombre']);

        if ($cantidad_nombre < 3) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre del usuario debe tener al menos 3 caracteres'
            ]);
            return;
        }

        if ($cantidad_nombre > 120) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'El nombre del usuario no puede tener más de 120 caracteres'
            ]);
            return;
        }

        // Validar email (opcional)
        if (!empty($_POST['usu_email'])) {
            $_POST['usu_email'] = filter_var($_POST['usu_email'], FILTER_SANITIZE_EMAIL);

            if (!filter_var($_POST['usu_email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'El correo electrónico no tiene un formato válido'
                ]);
                return;
            }

            // Verificar que no exista otro usuario con el mismo email
            $existe_email = Usuario::fetchFirst("
                SELECT usu_id FROM usuario 
                WHERE UPPER(usu_email) = UPPER('" . $_POST['usu_email'] . "') 
                AND usu_id != $id
                AND usu_situacion = 1
            ");

            if ($existe_email) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Ya existe otro usuario con este email'
                ]);
                return;
            }
        }

        try {
            $data = Usuario::find($id);
            
            if (!$data) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Usuario no encontrado'
                ]);
                return;
            }

            $data->sincronizar([
                'usu_nombre' => $_POST['usu_nombre'],
                'usu_grado' => $_POST['usu_grado'] ?? '',
                'usu_email' => $_POST['usu_email'] ?? '',
                'usu_rol_id' => $_POST['usu_rol_id'] ?? $data->usu_rol_id,
                'usu_activo' => $_POST['usu_activo'] ?? $data->usu_activo
            ]);
            
            $resultado = $data->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'El usuario ha sido modificado exitosamente'
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

    public static function activarDesactivarAPI()
    {
        getHeadersApi();

        try {
            $id = $_POST['usu_id'];
            $activo = filter_var($_POST['usu_activo'], FILTER_VALIDATE_BOOLEAN);

            $usuario = Usuario::find($id);
            
            if (!$usuario) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Usuario no encontrado'
                ]);
                return;
            }

            $usuario->sincronizar(['usu_activo' => $activo]);
            $resultado = $usuario->actualizar();

            $mensaje = $activo ? 'activado' : 'desactivado';

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => "El usuario ha sido $mensaje exitosamente"
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al cambiar el estado del usuario',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function eliminarAPI()
    {
        try {
            $id = filter_var($_GET['usu_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de usuario requerido'
                ]);
                return;
            }

            $usuario = Usuario::find($id);
            
            if (!$usuario) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Usuario no encontrado'
                ]);
                return;
            }

            // Verificar si se puede eliminar
            if (!$usuario->puedeEliminar()) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'No se puede eliminar el usuario porque es responsable de aplicaciones o tiene avances registrados'
                ]);
                return;
            }

            // Soft delete
            $usuario->sincronizar(['usu_situacion' => 0]);
            $resultado = $usuario->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'El usuario ha sido eliminado correctamente'
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

    public static function verificarPuedeSerResponsableAPI() {
        try {
            $usu_id = filter_var($_GET['usu_id'], FILTER_SANITIZE_NUMBER_INT);

            if (!$usu_id) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'ID de usuario requerido'
                ]);
                return;
            }

            $usuario = new Usuario(['usu_id' => $usu_id]);
            $puede_ser_responsable = $usuario->puedeSerResponsable();

            $data = [
                'usuario_id' => $usu_id,
                'puede_ser_responsable' => $puede_ser_responsable
            ];

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => $puede_ser_responsable ? 'El usuario puede ser responsable' : 'El usuario no puede ser responsable',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al verificar permisos',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function buscarPorNombreAPI() {
        try {
            $termino = isset($_GET['termino']) ? htmlspecialchars($_GET['termino']) : '';
            
            if (strlen($termino) < 2) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'El término de búsqueda debe tener al menos 2 caracteres'
                ]);
                return;
            }

            //: Usar || en lugar de CONCAT
            $query = "
                SELECT u.usu_id, u.usu_nombre, u.usu_grado, u.usu_email, r.rol_nombre,
                       CASE 
                           WHEN u.usu_grado IS NOT NULL AND u.usu_grado != '' 
                           THEN u.usu_grado || ' ' || u.usu_nombre
                           ELSE u.usu_nombre
                       END as nombre_completo
                FROM usuario u
                LEFT JOIN rol r ON u.usu_rol_id = r.rol_id
                WHERE u.usu_situacion = 1 
                AND u.usu_activo = true
                AND (UPPER(u.usu_nombre) LIKE UPPER('%$termino%') 
                     OR UPPER(u.usu_email) LIKE UPPER('%$termino%')
                     OR UPPER(u.usu_grado) LIKE UPPER('%$termino%'))
                ORDER BY u.usu_nombre
                LIMIT 20
            ";

            $data = Usuario::fetchArray($query);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => count($data) > 0 ? 'Usuarios encontrados' : 'No se encontraron usuarios',
                'data' => $data
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al buscar usuarios',
                'detalle' => $e->getMessage(),
            ]);
        }
    }
}