<?php

namespace Model;

use Model\ActiveRecord;

class Rol extends ActiveRecord
{
    // Nombre de la tabla en la BD
    public static $tabla = 'rol';
    public static $idTabla = 'rol_id';

    // Columnas que se van a mapear a la BD
    public static $columnasDB = [
        'rol_nombre',
        'rol_situacion'
    ];

    // Propiedades
    public $rol_id;
    public $rol_nombre;
    public $rol_situacion;

    public function __construct($args = [])
    {
        $this->rol_id = $args['rol_id'] ?? null;
        $this->rol_nombre = $args['rol_nombre'] ?? '';
        $this->rol_situacion = $args['rol_situacion'] ?? 1;
    }

    // Validaciones
    public function validar()
    {
        if (!$this->rol_nombre || strlen(trim($this->rol_nombre)) < 2) {
            self::$alertas['error'][] = 'El nombre del rol debe tener al menos 2 caracteres';
        }

        if (strlen($this->rol_nombre) > 30) {
            self::$alertas['error'][] = 'El nombre del rol no puede tener más de 30 caracteres';
        }

        return self::$alertas;
    }

    // Verificar si ya existe un rol con el mismo nombre
    public static function existeRolNombre($nombre, $excluir_id = null)
    {
        $query = "SELECT rol_id FROM " . self::$tabla . " 
                  WHERE UPPER(rol_nombre) = UPPER(?) ";
        $params = [trim($nombre)];
        
        if ($excluir_id) {
            $query .= " AND rol_id != ?";
            $params[] = $excluir_id;
        }

        $resultado = self::$db->prepare($query);
        $resultado->execute($params);
        
        return $resultado->fetch();
    }

    // Obtener roles para dropdown/select
    public static function obtenerRolesParaSelect()
    {
        $query = "SELECT rol_id, rol_nombre 
                  FROM " . self::$tabla . " 
                  WHERE rol_situacion = 1
                  ORDER BY rol_nombre";
        
        return self::fetchArray($query);
    }

    // Obtener usuarios por rol
    public function obtenerUsuarios()
    {
        $query = "SELECT u.* 
                  FROM usuario u 
                  WHERE u.usu_rol_id = ? AND u.usu_activo = true AND u.usu_situacion = 1
                  ORDER BY u.usu_nombre";
        
        return self::fetchArray($query, [$this->rol_id]);
    }

    // Contar usuarios activos en este rol
    public function contarUsuarios()
    {
        $query = "SELECT COUNT(*) as total 
                  FROM usuario 
                  WHERE usu_rol_id = ? AND usu_activo = true AND usu_situacion = 1";
        
        $resultado = self::fetchArray($query, [$this->rol_id]);
        return $resultado[0]['total'] ?? 0;
    }

    // Crear rol con validaciones completas
    public function crearConValidaciones()
    {
        // Validar
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no exista rol con el mismo nombre
        $existe = self::existeRolNombre($this->rol_nombre);
        if ($existe) {
            self::$alertas['error'][] = 'Ya existe un rol con este nombre';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Crear el rol
        $resultado = $this->crear();
        
        if ($resultado['resultado']) {
            return ['resultado' => true, 'id' => $resultado['id']];
        } else {
            self::$alertas['error'][] = 'Error al crear el rol';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Actualizar rol con validaciones
    public function actualizarConValidaciones()
    {
        // Validar
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no exista otro rol con el mismo nombre
        $existe = self::existeRolNombre($this->rol_nombre, $this->rol_id);
        if ($existe) {
            self::$alertas['error'][] = 'Ya existe otro rol con este nombre';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Actualizar el rol
        $resultado = $this->actualizar();
        
        if ($resultado['resultado']) {
            return ['resultado' => true];
        } else {
            self::$alertas['error'][] = 'Error al actualizar el rol';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Verificar si el rol se puede eliminar (no tiene usuarios asignados)
    public function puedeEliminar()
    {
        $total_usuarios = $this->contarUsuarios();
        return $total_usuarios == 0;
    }
}