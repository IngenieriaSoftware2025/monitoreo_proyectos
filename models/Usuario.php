<?php

namespace Model;

use Model\ActiveRecord;

class Usuario extends ActiveRecord
{
    // Nombre de la tabla en la BD
    public static $tabla = 'usuario';
    public static $idTabla = 'usu_id';

    // Columnas que se van a mapear a la BD
    public static $columnasDB = [
        'usu_nombre',
        'usu_grado',
        'usu_email',
        'usu_rol_id',
        'usu_activo',
        'usu_situacion'
    ];

    // Propiedades
    public $usu_id;
    public $usu_nombre;
    public $usu_grado;
    public $usu_email;
    public $usu_rol_id;
    public $usu_activo;
    public $usu_situacion;

    public function __construct($args = [])
    {
        $this->usu_id = $args['usu_id'] ?? null;
        $this->usu_nombre = $args['usu_nombre'] ?? '';
        $this->usu_grado = $args['usu_grado'] ?? '';
        $this->usu_email = $args['usu_email'] ?? '';
        $this->usu_rol_id = $args['usu_rol_id'] ?? null;
        $this->usu_activo = $args['usu_activo'] ?? true;
        $this->usu_situacion = $args['usu_situacion'] ?? 1;
    }

    // Validaciones
    public function validar()
    {
        if (!$this->usu_nombre || strlen(trim($this->usu_nombre)) < 3) {
            self::$alertas['error'][] = 'El nombre del usuario debe tener al menos 3 caracteres';
        }

        if (strlen($this->usu_nombre) > 120) {
            self::$alertas['error'][] = 'El nombre del usuario no puede tener más de 120 caracteres';
        }

        if ($this->usu_email && !filter_var($this->usu_email, FILTER_VALIDATE_EMAIL)) {
            self::$alertas['error'][] = 'El email no tiene un formato válido';
        }

        if (strlen($this->usu_email) > 120) {
            self::$alertas['error'][] = 'El email no puede tener más de 120 caracteres';
        }

        if (!$this->usu_rol_id) {
            self::$alertas['error'][] = 'El rol es obligatorio';
        }

        if ($this->usu_grado && strlen($this->usu_grado) > 50) {
            self::$alertas['error'][] = 'El grado no puede tener más de 50 caracteres';
        }

        return self::$alertas;
    }

    // Verificar si ya existe un usuario con el mismo email
    public static function existeEmail($email, $excluir_id = null)
    {
        if (!$email) return false;
        
        $query = "SELECT usu_id FROM " . self::$tabla . " 
                  WHERE UPPER(usu_email) = UPPER(?) ";
        $params = [trim($email)];
        
        if ($excluir_id) {
            $query .= " AND usu_id != ?";
            $params[] = $excluir_id;
        }

        $resultado = self::$db->prepare($query);
        $resultado->execute($params);
        
        return $resultado->fetch();
    }

    // Obtener usuarios activos para dropdown/select
    public static function obtenerUsuariosParaSelect($solo_activos = true)
    {
        $query = "SELECT u.usu_id, u.usu_nombre, u.usu_grado, u.usu_email, r.rol_nombre,
                         CASE 
                             WHEN u.usu_grado IS NOT NULL AND u.usu_grado != '' 
                             THEN CONCAT(u.usu_grado, ' ', u.usu_nombre)
                             ELSE u.usu_nombre
                         END as nombre_completo
                  FROM " . self::$tabla . " u
                  LEFT JOIN rol r ON u.usu_rol_id = r.rol_id";
        
        if ($solo_activos) {
            $query .= " WHERE u.usu_activo = true";
        }
        
        $query .= " ORDER BY u.usu_nombre";
        
        return self::fetchArray($query);
    }

    // Obtener usuarios por rol
    public static function obtenerUsuariosPorRol($rol_id, $solo_activos = true)
    {
        $query = "SELECT u.*, r.rol_nombre
                  FROM " . self::$tabla . " u
                  JOIN rol r ON u.usu_rol_id = r.rol_id
                  WHERE u.usu_rol_id = ?";
        
        $params = [$rol_id];
        
        if ($solo_activos) {
            $query .= " AND u.usu_activo = true";
        }
        
        $query .= " ORDER BY u.usu_nombre";
        
        return self::fetchArray($query, $params);
    }

    // Obtener información completa del usuario con rol
    public function obtenerConRol()
    {
        $query = "SELECT u.*, r.rol_nombre
                  FROM " . self::$tabla . " u
                  LEFT JOIN rol r ON u.usu_rol_id = r.rol_id
                  WHERE u.usu_id = ?";
        
        $resultado = self::fetchArray($query, [$this->usu_id]);
        return $resultado[0] ?? null;
    }

    // Obtener aplicaciones donde el usuario es responsable
    public function obtenerAplicacionesResponsable()
    {
        $query = "SELECT a.*
                  FROM aplicacion a
                  WHERE a.apl_responsable = ?
                  ORDER BY a.apl_fecha_inicio DESC";
        
        return self::fetchArray($query, [$this->usu_id]);
    }

    // Obtener aplicaciones donde el usuario tiene avances registrados
    public function obtenerAplicacionesConAvances()
    {
        $query = "SELECT DISTINCT a.*, 
                         MAX(av.ava_fecha) as ultimo_avance,
                         AVG(av.ava_porcentaje) as porcentaje_promedio
                  FROM aplicacion a
                  JOIN avance_diario av ON a.apl_id = av.ava_apl_id
                  WHERE av.ava_usu_id = ?
                  GROUP BY a.apl_id
                  ORDER BY ultimo_avance DESC";
        
        return self::fetchArray($query, [$this->usu_id]);
    }

    // Obtener estadísticas del usuario
    public function obtenerEstadisticas()
    {
        $stats = [
            'aplicaciones_responsable' => 0,
            'aplicaciones_con_avances' => 0,
            'total_avances' => 0,
            'promedio_porcentaje' => 0,
            'ultimo_avance' => null,
            'aplicaciones_activas' => 0,
            'comentarios_realizados' => 0
        ];

        // Aplicaciones como responsable
        $query = "SELECT COUNT(*) as total FROM aplicacion WHERE apl_responsable = ?";
        $resultado = self::fetchArray($query, [$this->usu_id]);
        $stats['aplicaciones_responsable'] = $resultado[0]['total'] ?? 0;

        // Aplicaciones activas como responsable
        $query = "SELECT COUNT(*) as total FROM aplicacion 
                  WHERE apl_responsable = ? AND apl_estado IN ('EN_PROGRESO', 'EN_PLANIFICACION')";
        $resultado = self::fetchArray($query, [$this->usu_id]);
        $stats['aplicaciones_activas'] = $resultado[0]['total'] ?? 0;

        // Estadísticas de avances
        $query = "SELECT COUNT(*) as total_avances,
                         COUNT(DISTINCT ava_apl_id) as aplicaciones_con_avances,
                         AVG(ava_porcentaje) as promedio_porcentaje,
                         MAX(ava_fecha) as ultimo_avance
                  FROM avance_diario 
                  WHERE ava_usu_id = ?";
        $resultado = self::fetchArray($query, [$this->usu_id]);
        if ($resultado && $resultado[0]) {
            $stats['total_avances'] = $resultado[0]['total_avances'] ?? 0;
            $stats['aplicaciones_con_avances'] = $resultado[0]['aplicaciones_con_avances'] ?? 0;
            $stats['promedio_porcentaje'] = round($resultado[0]['promedio_porcentaje'] ?? 0, 2);
            $stats['ultimo_avance'] = $resultado[0]['ultimo_avance'];
        }

        // Comentarios realizados
        $query = "SELECT COUNT(*) as total FROM comentario WHERE com_autor_id = ?";
        $resultado = self::fetchArray($query, [$this->usu_id]);
        $stats['comentarios_realizados'] = $resultado[0]['total'] ?? 0;

        return $stats;
    }

    // Verificar si el usuario puede ser responsable de aplicaciones
    public function puedeSerResponsable()
    {
        // Obtener información del rol
        $query = "SELECT rol_nombre FROM rol WHERE rol_id = ?";
        $resultado = self::fetchArray($query, [$this->usu_rol_id]);
        
        if ($resultado && $resultado[0]) {
            $rol = strtoupper($resultado[0]['rol_nombre']);
            // Definir roles que pueden ser responsables
            $roles_responsables = ['DESARROLLADOR', 'JEFE', 'COORDINADOR', 'ADMINISTRADOR'];
            
            foreach ($roles_responsables as $rol_valido) {
                if (strpos($rol, $rol_valido) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    // Obtener avances recientes del usuario
    public function obtenerAvancesRecientes($limite = 10)
    {
        $query = "SELECT av.*, a.apl_nombre
                  FROM avance_diario av
                  JOIN aplicacion a ON av.ava_apl_id = a.apl_id
                  WHERE av.ava_usu_id = ?
                  ORDER BY av.ava_fecha DESC, av.ava_creado_en DESC
                  LIMIT ?";
        
        return self::fetchArray($query, [$this->usu_id, $limite]);
    }

    // Crear usuario con validaciones completas
    public function crearConValidaciones()
    {
        // Validar
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no exista usuario con el mismo email
        if ($this->usu_email) {
            $existe = self::existeEmail($this->usu_email);
            if ($existe) {
                self::$alertas['error'][] = 'Ya existe un usuario con este email';
                return ['resultado' => false, 'alertas' => self::$alertas];
            }
        }

        // Verificar que el rol existe
        $query = "SELECT rol_id FROM rol WHERE rol_id = ?";
        $resultado = self::$db->prepare($query);
        $resultado->execute([$this->usu_rol_id]);
        if (!$resultado->fetch()) {
            self::$alertas['error'][] = 'El rol seleccionado no existe';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Crear el usuario
        $resultado = $this->crear();
        
        if ($resultado['resultado']) {
            return ['resultado' => true, 'id' => $resultado['id']];
        } else {
            self::$alertas['error'][] = 'Error al crear el usuario';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Actualizar usuario con validaciones
    public function actualizarConValidaciones()
    {
        // Validar
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no exista otro usuario con el mismo email
        if ($this->usu_email) {
            $existe = self::existeEmail($this->usu_email, $this->usu_id);
            if ($existe) {
                self::$alertas['error'][] = 'Ya existe otro usuario con este email';
                return ['resultado' => false, 'alertas' => self::$alertas];
            }
        }

        // Actualizar el usuario
        $resultado = $this->actualizar();
        
        if ($resultado['resultado']) {
            return ['resultado' => true];
        } else {
            self::$alertas['error'][] = 'Error al actualizar el usuario';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Desactivar usuario (en lugar de eliminar)
    public function desactivar()
    {
        $this->usu_activo = false;
        return $this->actualizar();
    }

    // Activar usuario
    public function activar()
    {
        $this->usu_activo = true;
        return $this->actualizar();
    }

    // Verificar si el usuario se puede eliminar
    public function puedeEliminar()
    {
        // Verificar si es responsable de alguna aplicación
        $query = "SELECT COUNT(*) as total FROM aplicacion WHERE apl_responsable = ?";
        $resultado = self::fetchArray($query, [$this->usu_id]);
        if ($resultado && $resultado[0]['total'] > 0) {
            return false;
        }

        // Verificar si tiene avances registrados
        $query = "SELECT COUNT(*) as total FROM avance_diario WHERE ava_usu_id = ?";
        $resultado = self::fetchArray($query, [$this->usu_id]);
        if ($resultado && $resultado[0]['total'] > 0) {
            return false;
        }

        return true;
    }
}