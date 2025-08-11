<?php

namespace Model;

use Model\ActiveRecord;

class Aplicacion extends ActiveRecord
{
    // Nombre de la tabla en la BD
    public static $tabla = 'aplicacion';
    public static $idTabla = 'apl_id';

    // Columnas que se van a mapear a la BD
    public static $columnasDB = [
        'apl_nombre',
        'apl_descripcion',
        'apl_fecha_inicio',
        'apl_fecha_fin',
        'apl_porcentaje_objetivo',
        'apl_estado',
        'apl_responsable',
        'apl_creado_en',
        'apl_situacion'
    ];

    // Propiedades
    public $apl_id;
    public $apl_nombre;
    public $apl_descripcion;
    public $apl_fecha_inicio;
    public $apl_fecha_fin;
    public $apl_porcentaje_objetivo;
    public $apl_estado;
    public $apl_responsable;
    public $apl_creado_en;
    public $apl_situacion;

    public function __construct($args = [])
    {
        $this->apl_id = $args['apl_id'] ?? null;
        $this->apl_nombre = $args['apl_nombre'] ?? '';
        $this->apl_descripcion = $args['apl_descripcion'] ?? '';
        $this->apl_fecha_inicio = $args['apl_fecha_inicio'] ?? null;
        $this->apl_fecha_fin = $args['apl_fecha_fin'] ?? null;
        $this->apl_porcentaje_objetivo = $args['apl_porcentaje_objetivo'] ?? null;
        $this->apl_estado = $args['apl_estado'] ?? 'EN_PLANIFICACION';
        $this->apl_responsable = $args['apl_responsable'] ?? null;
        $this->apl_creado_en = $args['apl_creado_en'] ?? null;
        $this->apl_situacion = $args['apl_situacion'] ?? 1;
    }

    // Validaciones
    public function validar()
    {
        if (!$this->apl_nombre || strlen(trim($this->apl_nombre)) < 3) {
            self::$alertas['error'][] = 'El nombre de la aplicación debe tener al menos 3 caracteres';
        }

        if (strlen($this->apl_nombre) > 120) {
            self::$alertas['error'][] = 'El nombre de la aplicación no puede tener más de 120 caracteres';
        }

        if (!$this->apl_fecha_inicio) {
            self::$alertas['error'][] = 'La fecha de inicio es obligatoria';
        }

        if ($this->apl_fecha_fin && $this->apl_fecha_inicio) {
            if (strtotime($this->apl_fecha_fin) < strtotime($this->apl_fecha_inicio)) {
                self::$alertas['error'][] = 'La fecha de fin debe ser posterior a la fecha de inicio';
            }
        }

        if ($this->apl_porcentaje_objetivo !== null) {
            if ($this->apl_porcentaje_objetivo < 0 || $this->apl_porcentaje_objetivo > 100) {
                self::$alertas['error'][] = 'El porcentaje objetivo debe estar entre 0 y 100';
            }
        }

        $estados_validos = ['EN_PLANIFICACION', 'EN_PROGRESO', 'PAUSADO', 'CERRADO'];
        if ($this->apl_estado && !in_array($this->apl_estado, $estados_validos)) {
            self::$alertas['error'][] = 'El estado debe ser uno de: ' . implode(', ', $estados_validos);
        }

        if ($this->apl_descripcion && strlen($this->apl_descripcion) > 500) {
            self::$alertas['error'][] = 'La descripción no puede tener más de 500 caracteres';
        }

        return self::$alertas;
    }

    // Verificar si ya existe una aplicación con el mismo nombre
    public static function existeNombreAplicacion($nombre, $excluir_id = null)
    {
        $query = "SELECT apl_id FROM " . self::$tabla . " 
                  WHERE UPPER(apl_nombre) = UPPER(?) AND apl_situacion = 1";
        $params = [trim($nombre)];
        
        if ($excluir_id) {
            $query .= " AND apl_id != ?";
            $params[] = $excluir_id;
        }

        $resultado = self::$db->prepare($query);
        $resultado->execute($params);
        
        return $resultado->fetch();
    }

    // Obtener aplicaciones para dropdown/select
    public static function obtenerAplicacionesParaSelect($solo_activas = true)
    {
        $query = "SELECT a.apl_id, a.apl_nombre, a.apl_estado, u.usu_nombre as responsable_nombre
                  FROM " . self::$tabla . " a
                  LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                  WHERE a.apl_situacion = 1";
        
        if ($solo_activas) {
            $query .= " AND a.apl_estado IN ('EN_PLANIFICACION', 'EN_PROGRESO')";
        }
        
        $query .= " ORDER BY a.apl_nombre";
        
        return self::fetchArray($query);
    }

    // Obtener aplicaciones por responsable
    public static function obtenerPorResponsable($responsable_id)
    {
        $query = "SELECT a.*, u.usu_nombre as responsable_nombre
                  FROM " . self::$tabla . " a
                  LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                  WHERE a.apl_responsable = ? AND a.apl_situacion = 1
                  ORDER BY a.apl_fecha_inicio DESC";
        
        return self::fetchArray($query, [$responsable_id]);
    }

    // Obtener aplicaciones por estado
    public static function obtenerPorEstado($estado)
    {
        $query = "SELECT a.*, u.usu_nombre as responsable_nombre
                  FROM " . self::$tabla . " a
                  LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                  WHERE a.apl_estado = ? AND a.apl_situacion = 1
                  ORDER BY a.apl_fecha_inicio DESC";
        
        return self::fetchArray($query, [$estado]);
    }

    // Obtener información completa con responsable
    public function obtenerConResponsable()
    {
        $query = "SELECT a.*, u.usu_nombre as responsable_nombre, u.usu_grado, u.usu_email,
                         r.rol_nombre
                  FROM " . self::$tabla . " a
                  LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                  LEFT JOIN rol r ON u.usu_rol_id = r.rol_id
                  WHERE a.apl_id = ?";
        
        $resultado = self::fetchArray($query, [$this->apl_id]);
        return $resultado[0] ?? null;
    }

    // Obtener último avance de la aplicación
    public function obtenerUltimoAvance()
    {
        $query = "SELECT av.*, u.usu_nombre
                  FROM avance_diario av
                  JOIN usuario u ON av.ava_usu_id = u.usu_id
                  WHERE av.ava_apl_id = ? AND av.ava_situacion = 1
                  ORDER BY av.ava_fecha DESC, av.ava_creado_en DESC
                  LIMIT 1";
        
        $resultado = self::fetchArray($query, [$this->apl_id]);
        return $resultado[0] ?? null;
    }

    // Obtener todos los avances de la aplicación
    public function obtenerAvances($limite = null)
    {
        $query = "SELECT av.*, u.usu_nombre
                  FROM avance_diario av
                  JOIN usuario u ON av.ava_usu_id = u.usu_id
                  WHERE av.ava_apl_id = ? AND av.ava_situacion = 1
                  ORDER BY av.ava_fecha DESC, av.ava_creado_en DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$this->apl_id]);
    }

    // Calcular porcentaje promedio actual
    public function calcularPorcentajePromedio()
    {
        $query = "SELECT AVG(ava_porcentaje) as promedio
                  FROM avance_diario
                  WHERE ava_apl_id = ? AND ava_situacion = 1
                  AND ava_fecha = (
                      SELECT MAX(ava_fecha) 
                      FROM avance_diario 
                      WHERE ava_apl_id = ? AND ava_situacion = 1
                  )";
        
        $resultado = self::fetchArray($query, [$this->apl_id, $this->apl_id]);
        return round($resultado[0]['promedio'] ?? 0, 2);
    }

    // Obtener estadísticas de la aplicación
    public function obtenerEstadisticas()
    {
        $stats = [
            'total_avances' => 0,
            'porcentaje_actual' => 0,
            'dias_trabajados' => 0,
            'ultimo_avance' => null,
            'desarrolladores_activos' => 0,
            'total_comentarios' => 0,
            'total_visitas' => 0,
            'dias_inactividad' => 0
        ];

        // Total de avances
        $query = "SELECT COUNT(*) as total FROM avance_diario 
                  WHERE ava_apl_id = ? AND ava_situacion = 1";
        $resultado = self::fetchArray($query, [$this->apl_id]);
        $stats['total_avances'] = $resultado[0]['total'] ?? 0;

        // Porcentaje actual (promedio del último día)
        $stats['porcentaje_actual'] = $this->calcularPorcentajePromedio();

        // Días trabajados únicos
        $query = "SELECT COUNT(DISTINCT ava_fecha) as dias 
                  FROM avance_diario 
                  WHERE ava_apl_id = ? AND ava_situacion = 1";
        $resultado = self::fetchArray($query, [$this->apl_id]);
        $stats['dias_trabajados'] = $resultado[0]['dias'] ?? 0;

        // Último avance
        $ultimo_avance = $this->obtenerUltimoAvance();
        $stats['ultimo_avance'] = $ultimo_avance['ava_fecha'] ?? null;

        // Desarrolladores activos (que han registrado avances)
        $query = "SELECT COUNT(DISTINCT ava_usu_id) as desarrolladores
                  FROM avance_diario 
                  WHERE ava_apl_id = ? AND ava_situacion = 1";
        $resultado = self::fetchArray($query, [$this->apl_id]);
        $stats['desarrolladores_activos'] = $resultado[0]['desarrolladores'] ?? 0;

        // Total comentarios
        $query = "SELECT COUNT(*) as total FROM comentario 
                  WHERE com_apl_id = ? AND com_situacion = 1";
        $resultado = self::fetchArray($query, [$this->apl_id]);
        $stats['total_comentarios'] = $resultado[0]['total'] ?? 0;

        // Total visitas
        $query = "SELECT COUNT(*) as total FROM visita 
                  WHERE vis_apl_id = ? AND vis_situacion = 1";
        $resultado = self::fetchArray($query, [$this->apl_id]);
        $stats['total_visitas'] = $resultado[0]['total'] ?? 0;

        // Días de inactividad
        if ($stats['ultimo_avance']) {
            $dias_inactividad = (strtotime(date('Y-m-d')) - strtotime($stats['ultimo_avance'])) / (60 * 60 * 24);
            $stats['dias_inactividad'] = max(0, floor($dias_inactividad));
        }

        return $stats;
    }

    // Obtener aplicaciones que requieren atención
    public static function obtenerRequierenAtencion($dias_limite = 7)
    {
        $query = "SELECT a.*, u.usu_nombre as responsable_nombre,
                         COALESCE(ultimo_avance.fecha_ultimo, a.apl_fecha_inicio) as fecha_ultimo_avance,
                         CASE 
                             WHEN ultimo_avance.fecha_ultimo IS NULL THEN 
                                 CASE WHEN a.apl_fecha_inicio < CURRENT - ? UNITS DAY THEN 1 ELSE 0 END
                             ELSE 
                                 CASE WHEN ultimo_avance.fecha_ultimo < CURRENT - ? UNITS DAY THEN 1 ELSE 0 END
                         END as requiere_atencion
                  FROM " . self::$tabla . " a
                  LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                  LEFT JOIN (
                      SELECT ava_apl_id, MAX(ava_fecha) as fecha_ultimo
                      FROM avance_diario 
                      WHERE ava_situacion = 1
                      GROUP BY ava_apl_id
                  ) ultimo_avance ON a.apl_id = ultimo_avance.ava_apl_id
                  WHERE a.apl_estado IN ('EN_PROGRESO', 'EN_PLANIFICACION') 
                  AND a.apl_situacion = 1
                  HAVING requiere_atencion = 1
                  ORDER BY fecha_ultimo_avance";
        
        return self::fetchArray($query, [$dias_limite, $dias_limite]);
    }

    // Obtener comentarios de la aplicación
    public function obtenerComentarios($limite = null)
    {
        $query = "SELECT c.*, u.usu_nombre as autor_nombre
                  FROM comentario c
                  JOIN usuario u ON c.com_autor_id = u.usu_id
                  WHERE c.com_apl_id = ? AND c.com_situacion = 1
                  ORDER BY c.com_creado_en DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$this->apl_id]);
    }

    // Obtener visitas de la aplicación
    public function obtenerVisitas($limite = null)
    {
        $query = "SELECT v.*, u.usu_nombre as creado_por_nombre
                  FROM visita v
                  LEFT JOIN usuario u ON v.vis_creado_por = u.usu_id
                  WHERE v.vis_apl_id = ? AND v.vis_situacion = 1
                  ORDER BY v.vis_fecha DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$this->apl_id]);
    }

    // Crear aplicación con validaciones completas
    public function crearConValidaciones()
    {
        // Validar
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no exista aplicación con el mismo nombre
        $existe = self::existeNombreAplicacion($this->apl_nombre);
        if ($existe) {
            self::$alertas['error'][] = 'Ya existe una aplicación con este nombre';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que el responsable existe y está activo
        if ($this->apl_responsable) {
            $query = "SELECT usu_id FROM usuario 
                      WHERE usu_id = ? AND usu_activo = true AND usu_situacion = 1";
            $resultado = self::$db->prepare($query);
            $resultado->execute([$this->apl_responsable]);
            if (!$resultado->fetch()) {
                self::$alertas['error'][] = 'El usuario responsable no existe o no está activo';
                return ['resultado' => false, 'alertas' => self::$alertas];
            }
        }

        // Establecer fecha de creación si no existe
        if (!$this->apl_creado_en) {
            $this->apl_creado_en = date('Y-m-d H:i:s');
        }

        // Crear la aplicación
        $resultado = $this->crear();
        
        if ($resultado['resultado']) {
            return ['resultado' => true, 'id' => $resultado['id']];
        } else {
            self::$alertas['error'][] = 'Error al crear la aplicación';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Actualizar aplicación con validaciones
    public function actualizarConValidaciones()
    {
        // Validar
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no exista otra aplicación con el mismo nombre
        $existe = self::existeNombreAplicacion($this->apl_nombre, $this->apl_id);
        if ($existe) {
            self::$alertas['error'][] = 'Ya existe otra aplicación con este nombre';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Actualizar la aplicación
        $resultado = $this->actualizar();
        
        if ($resultado['resultado']) {
            return ['resultado' => true];
        } else {
            self::$alertas['error'][] = 'Error al actualizar la aplicación';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Cambiar estado de la aplicación
    public function cambiarEstado($nuevo_estado, $motivo = '')
    {
        $estados_validos = ['EN_PLANIFICACION', 'EN_PROGRESO', 'PAUSADO', 'CERRADO'];
        
        if (!in_array($nuevo_estado, $estados_validos)) {
            self::$alertas['error'][] = 'Estado no válido';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        $estado_anterior = $this->apl_estado;
        $this->apl_estado = $nuevo_estado;

        // Si se cierra, establecer fecha de fin si no existe
        if ($nuevo_estado === 'CERRADO' && !$this->apl_fecha_fin) {
            $this->apl_fecha_fin = date('Y-m-d');
        }

        $resultado = $this->actualizar();
        
        if ($resultado['resultado']) {
            return ['resultado' => true];
        } else {
            self::$alertas['error'][] = 'Error al cambiar el estado';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Verificar si la aplicación se puede eliminar
    public function puedeEliminar()
    {
        // Verificar si tiene avances
        $query = "SELECT COUNT(*) as total FROM avance_diario WHERE ava_apl_id = ?";
        $resultado = self::fetchArray($query, [$this->apl_id]);
        if ($resultado && $resultado[0]['total'] > 0) {
            return false;
        }

        // Verificar si tiene comentarios
        $query = "SELECT COUNT(*) as total FROM comentario WHERE com_apl_id = ?";
        $resultado = self::fetchArray($query, [$this->apl_id]);
        if ($resultado && $resultado[0]['total'] > 0) {
            return false;
        }

        return true;
    }

    // Soft delete - cambiar situación a 0
    public function eliminarLogico()
    {
        $this->apl_situacion = 0;
        return $this->actualizar();
    }
}