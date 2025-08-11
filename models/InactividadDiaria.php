<?php

namespace Model;

use Model\ActiveRecord;

class InactividadDiaria extends ActiveRecord
{
    // Nombre de la tabla en la BD
    public static $tabla = 'inactividad_diaria';
    public static $idTabla = 'ina_id';

    // Columnas que se van a mapear a la BD
    public static $columnasDB = [
        'ina_apl_id',
        'ina_usu_id',
        'ina_fecha',
        'ina_motivo',
        'ina_tipo',
        'ina_creado_en',
        'ina_situacion'
    ];

    // Propiedades
    public $ina_id;
    public $ina_apl_id;
    public $ina_usu_id;
    public $ina_fecha;
    public $ina_motivo;
    public $ina_tipo;
    public $ina_creado_en;
    public $ina_situacion;

    // Tipos de inactividad válidos
    const TIPOS_VALIDOS = [
        'LICENCIA',
        'FALLA_TECNICA',
        'BLOQUEADOR_EXTERNO',
        'VISITA',
        'ESPERA_APROBACION',
        'CAPACITACION',
        'REUNION',
        'OTRO'
    ];

    // Tipos que requieren aprobación de gerente
    const TIPOS_REQUIEREN_APROBACION = [
        'LICENCIA',
        'CAPACITACION'
    ];

    // Tipos que afectan el semáforo diferente
    const TIPOS_CRITICOS = [
        'BLOQUEADOR_EXTERNO',
        'FALLA_TECNICA'
    ];

    public function __construct($args = [])
    {
        $this->ina_id = $args['ina_id'] ?? null;
        $this->ina_apl_id = $args['ina_apl_id'] ?? null;
        $this->ina_usu_id = $args['ina_usu_id'] ?? null;
        $this->ina_fecha = $args['ina_fecha'] ?? date('Y-m-d');
        $this->ina_motivo = $args['ina_motivo'] ?? '';
        $this->ina_tipo = $args['ina_tipo'] ?? '';
        $this->ina_creado_en = $args['ina_creado_en'] ?? null;
        $this->ina_situacion = $args['ina_situacion'] ?? 1;
    }

    // Validaciones
    public function validar()
    {
        if (!$this->ina_apl_id) {
            self::$alertas['error'][] = 'La aplicación es obligatoria';
        }

        if (!$this->ina_usu_id) {
            self::$alertas['error'][] = 'El usuario es obligatorio';
        }

        if (!$this->ina_fecha) {
            self::$alertas['error'][] = 'La fecha es obligatoria';
        }

        if (!$this->ina_motivo || strlen(trim($this->ina_motivo)) < 10) {
            self::$alertas['error'][] = 'El motivo es obligatorio y debe tener al menos 10 caracteres';
        }

        if (strlen($this->ina_motivo) > 500) {
            self::$alertas['error'][] = 'El motivo no puede tener más de 500 caracteres';
        }

        if (!$this->ina_tipo) {
            self::$alertas['error'][] = 'El tipo de inactividad es obligatorio';
        }

        if ($this->ina_tipo && !in_array($this->ina_tipo, self::TIPOS_VALIDOS)) {
            self::$alertas['error'][] = 'El tipo debe ser uno de: ' . implode(', ', self::TIPOS_VALIDOS);
        }

        if (strlen($this->ina_tipo) > 50) {
            self::$alertas['error'][] = 'El tipo no puede tener más de 50 caracteres';
        }

        // Validar que la fecha no sea futura
        if ($this->ina_fecha > date('Y-m-d')) {
            self::$alertas['error'][] = 'No se puede registrar inactividad para fechas futuras';
        }

        return self::$alertas;
    }

    // Verificar si ya existe inactividad para esta app/usuario/fecha
    public static function existeInactividadDiaria($apl_id, $usu_id, $fecha, $excluir_id = null)
    {
        $query = "SELECT ina_id FROM " . self::$tabla . " 
                  WHERE ina_apl_id = ? AND ina_usu_id = ? AND ina_fecha = ? AND ina_situacion = 1";
        $params = [$apl_id, $usu_id, $fecha];
        
        if ($excluir_id) {
            $query .= " AND ina_id != ?";
            $params[] = $excluir_id;
        }

        $resultado = self::$db->prepare($query);
        $resultado->execute($params);
        
        return $resultado->fetch();
    }

    // Verificar si hay avance registrado para la misma fecha
    public static function hayAvanceEnFecha($apl_id, $usu_id, $fecha)
    {
        $query = "SELECT ava_id FROM avance_diario 
                  WHERE ava_apl_id = ? AND ava_usu_id = ? AND ava_fecha = ? AND ava_situacion = 1";
        
        $resultado = self::$db->prepare($query);
        $resultado->execute([$apl_id, $usu_id, $fecha]);
        
        return $resultado->fetch();
    }

    // Obtener inactividades por aplicación
    public static function obtenerPorAplicacion($apl_id, $limite = null)
    {
        $query = "SELECT ina.*, u.usu_nombre, u.usu_grado
                  FROM " . self::$tabla . " ina
                  JOIN usuario u ON ina.ina_usu_id = u.usu_id
                  WHERE ina.ina_apl_id = ? AND ina.ina_situacion = 1
                  ORDER BY ina.ina_fecha DESC, ina.ina_creado_en DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$apl_id]);
    }

    // Obtener inactividades por usuario
    public static function obtenerPorUsuario($usu_id, $limite = null)
    {
        $query = "SELECT ina.*, a.apl_nombre, a.apl_estado
                  FROM " . self::$tabla . " ina
                  JOIN aplicacion a ON ina.ina_apl_id = a.apl_id
                  WHERE ina.ina_usu_id = ? AND ina.ina_situacion = 1 AND a.apl_situacion = 1
                  ORDER BY ina.ina_fecha DESC, ina.ina_creado_en DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$usu_id]);
    }

    // Obtener inactividades por fecha
    public static function obtenerPorFecha($fecha)
    {
        $query = "SELECT ina.*, a.apl_nombre, u.usu_nombre, u.usu_grado
                  FROM " . self::$tabla . " ina
                  JOIN aplicacion a ON ina.ina_apl_id = a.apl_id
                  JOIN usuario u ON ina.ina_usu_id = u.usu_id
                  WHERE ina.ina_fecha = ? AND ina.ina_situacion = 1 
                  AND a.apl_situacion = 1 AND u.usu_situacion = 1
                  ORDER BY ina.ina_tipo, a.apl_nombre, u.usu_nombre";
        
        return self::fetchArray($query, [$fecha]);
    }

    // Obtener inactividades por tipo
    public static function obtenerPorTipo($tipo, $fecha_desde = null, $fecha_hasta = null)
    {
        $query = "SELECT ina.*, a.apl_nombre, u.usu_nombre, u.usu_grado
                  FROM " . self::$tabla . " ina
                  JOIN aplicacion a ON ina.ina_apl_id = a.apl_id
                  JOIN usuario u ON ina.ina_usu_id = u.usu_id
                  WHERE ina.ina_tipo = ? AND ina.ina_situacion = 1 
                  AND a.apl_situacion = 1 AND u.usu_situacion = 1";
        
        $params = [$tipo];
        
        if ($fecha_desde) {
            $query .= " AND ina.ina_fecha >= ?";
            $params[] = $fecha_desde;
        }
        
        if ($fecha_hasta) {
            $query .= " AND ina.ina_fecha <= ?";
            $params[] = $fecha_hasta;
        }
        
        $query .= " ORDER BY ina.ina_fecha DESC";
        
        return self::fetchArray($query, $params);
    }

    // Obtener estadísticas de inactividad por usuario
    public static function obtenerEstadisticasUsuario($usu_id, $dias = 30)
    {
        $fecha_inicio = date('Y-m-d', strtotime("-$dias days"));
        
        $query = "SELECT 
                      COUNT(*) as total_inactividades,
                      COUNT(DISTINCT ina_apl_id) as aplicaciones_afectadas,
                      COUNT(DISTINCT ina_fecha) as dias_inactivos,
                      ina_tipo,
                      COUNT(*) as cantidad_por_tipo
                  FROM " . self::$tabla . "
                  WHERE ina_usu_id = ? AND ina_fecha >= ? AND ina_situacion = 1
                  GROUP BY ina_tipo
                  ORDER BY cantidad_por_tipo DESC";
        
        return self::fetchArray($query, [$usu_id, $fecha_inicio]);
    }

    // Obtener estadísticas generales de inactividad
    public static function obtenerEstadisticasGenerales($fecha_desde = null, $fecha_hasta = null)
    {
        $fecha_desde = $fecha_desde ?: date('Y-m-d', strtotime('-30 days'));
        $fecha_hasta = $fecha_hasta ?: date('Y-m-d');
        
        $query = "SELECT 
                      ina_tipo,
                      COUNT(*) as total_registros,
                      COUNT(DISTINCT ina_usu_id) as usuarios_afectados,
                      COUNT(DISTINCT ina_apl_id) as aplicaciones_afectadas,
                      COUNT(DISTINCT ina_fecha) as dias_con_inactividad
                  FROM " . self::$tabla . "
                  WHERE ina_fecha BETWEEN ? AND ? AND ina_situacion = 1
                  GROUP BY ina_tipo
                  ORDER BY total_registros DESC";
        
        return self::fetchArray($query, [$fecha_desde, $fecha_hasta]);
    }

    // Detectar patrones de inactividad sospechosos
    public static function detectarPatronesSospechosos($dias_limite = 7)
    {
        $fecha_inicio = date('Y-m-d', strtotime("-$dias_limite days"));
        
        $query = "SELECT ina_usu_id, u.usu_nombre, 
                         COUNT(*) as dias_inactivos,
                         COUNT(DISTINCT ina_apl_id) as apps_afectadas,
                         GROUP_CONCAT(DISTINCT ina_tipo) as tipos_inactividad
                  FROM " . self::$tabla . " ina
                  JOIN usuario u ON ina.ina_usu_id = u.usu_id
                  WHERE ina.ina_fecha >= ? AND ina.ina_situacion = 1
                  GROUP BY ina_usu_id, u.usu_nombre
                  HAVING dias_inactivos >= ?
                  ORDER BY dias_inactivos DESC";
        
        return self::fetchArray($query, [$fecha_inicio, $dias_limite]);
    }

    // Verificar si el tipo requiere aprobación
    public function requiereAprobacion()
    {
        return in_array($this->ina_tipo, self::TIPOS_REQUIEREN_APROBACION);
    }

    // Verificar si el tipo es crítico
    public function esTipoCritico()
    {
        return in_array($this->ina_tipo, self::TIPOS_CRITICOS);
    }

    // Obtener información completa de la inactividad
    public function obtenerCompleto()
    {
        $query = "SELECT ina.*, a.apl_nombre, a.apl_estado, u.usu_nombre, u.usu_grado, r.rol_nombre
                  FROM " . self::$tabla . " ina
                  JOIN aplicacion a ON ina.ina_apl_id = a.apl_id
                  JOIN usuario u ON ina.ina_usu_id = u.usu_id
                  LEFT JOIN rol r ON u.usu_rol_id = r.rol_id
                  WHERE ina.ina_id = ?";
        
        $resultado = self::fetchArray($query, [$this->ina_id]);
        return $resultado[0] ?? null;
    }

    // Calcular impacto en el semáforo de la aplicación
    public static function calcularImpactoSemaforo($apl_id, $fecha = null)
    {
        if (!$fecha) {
            $fecha = date('Y-m-d');
        }

        // Obtener inactividades del día
        $query = "SELECT ina_tipo, COUNT(*) as cantidad
                  FROM " . self::$tabla . "
                  WHERE ina_apl_id = ? AND ina_fecha = ? AND ina_situacion = 1
                  GROUP BY ina_tipo";
        
        $inactividades = self::fetchArray($query, [$apl_id, $fecha]);
        
        $impacto = [
            'tiene_inactividad' => count($inactividades) > 0,
            'tipos_criticos' => 0,
            'tipos_normales' => 0,
            'total_registros' => 0,
            'justifica_ausencia_reporte' => false
        ];

        foreach ($inactividades as $inact) {
            $impacto['total_registros'] += $inact['cantidad'];
            
            if (in_array($inact['ina_tipo'], self::TIPOS_CRITICOS)) {
                $impacto['tipos_criticos'] += $inact['cantidad'];
            } else {
                $impacto['tipos_normales'] += $inact['cantidad'];
            }
        }

        // Si hay inactividad justificada, puede justificar la ausencia de reporte
        $impacto['justifica_ausencia_reporte'] = $impacto['total_registros'] > 0;

        return $impacto;
    }

    // Crear inactividad con validaciones completas
    public function crearConValidaciones()
    {
        // Validaciones básicas
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no exista inactividad para esta fecha
        $existe = self::existeInactividadDiaria($this->ina_apl_id, $this->ina_usu_id, $this->ina_fecha);
        if ($existe) {
            self::$alertas['error'][] = 'Ya existe un registro de inactividad para esta fecha';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no haya avance en la misma fecha
        $hay_avance = self::hayAvanceEnFecha($this->ina_apl_id, $this->ina_usu_id, $this->ina_fecha);
        if ($hay_avance) {
            self::$alertas['error'][] = 'No se puede registrar inactividad porque ya hay un avance registrado para esta fecha';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que la aplicación existe y está activa
        $query = "SELECT apl_estado FROM aplicacion WHERE apl_id = ? AND apl_situacion = 1";
        $resultado = self::fetchArray($query, [$this->ina_apl_id]);
        if (!$resultado) {
            self::$alertas['error'][] = 'La aplicación no existe o no está activa';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que el usuario existe y está activo
        $query = "SELECT usu_activo FROM usuario WHERE usu_id = ? AND usu_situacion = 1";
        $resultado = self::fetchArray($query, [$this->ina_usu_id]);
        if (!$resultado || !$resultado[0]['usu_activo']) {
            self::$alertas['error'][] = 'El usuario no existe o no está activo';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Establecer fecha de creación
        if (!$this->ina_creado_en) {
            $this->ina_creado_en = date('Y-m-d H:i:s');
        }

        // Crear la inactividad
        $resultado = $this->crear();
        
        if ($resultado['resultado']) {
            return ['resultado' => true, 'id' => $resultado['id']];
        } else {
            self::$alertas['error'][] = 'Error al crear el registro de inactividad';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Actualizar inactividad con validaciones
    public function actualizarConValidaciones()
    {
        // Validaciones básicas
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no exista otra inactividad para esta fecha
        $existe = self::existeInactividadDiaria($this->ina_apl_id, $this->ina_usu_id, $this->ina_fecha, $this->ina_id);
        if ($existe) {
            self::$alertas['error'][] = 'Ya existe otro registro de inactividad para esta fecha';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Actualizar la inactividad
        $resultado = $this->actualizar();
        
        if ($resultado['resultado']) {
            return ['resultado' => true];
        } else {
            self::$alertas['error'][] = 'Error al actualizar el registro de inactividad';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Obtener días consecutivos de inactividad para un usuario/aplicación
    public static function obtenerDiasConsecutivos($apl_id, $usu_id, $fecha_referencia = null)
    {
        if (!$fecha_referencia) {
            $fecha_referencia = date('Y-m-d');
        }

        $query = "SELECT COUNT(*) as dias_consecutivos
                  FROM " . self::$tabla . "
                  WHERE ina_apl_id = ? AND ina_usu_id = ? 
                  AND ina_fecha <= ? AND ina_situacion = 1
                  AND ina_fecha >= (
                      SELECT COALESCE(MAX(ava_fecha), ?) 
                      FROM avance_diario 
                      WHERE ava_apl_id = ? AND ava_usu_id = ? AND ava_situacion = 1
                  )";
        
        $fecha_inicio_proyecto = date('Y-m-d', strtotime('-30 days', strtotime($fecha_referencia)));
        
        $resultado = self::fetchArray($query, [
            $apl_id, $usu_id, $fecha_referencia, 
            $fecha_inicio_proyecto, $apl_id, $usu_id
        ]);
        
        return $resultado[0]['dias_consecutivos'] ?? 0;
    }

    // Verificar si se puede eliminar
    public function puedeEliminar()
    {
        // Solo se puede eliminar el mismo día de creación
        $fecha_creacion = date('Y-m-d', strtotime($this->ina_creado_en));
        $fecha_actual = date('Y-m-d');
        
        return $fecha_creacion === $fecha_actual;
    }

    // Soft delete
    public function eliminarLogico()
    {
        $this->ina_situacion = 0;
        return $this->actualizar();
    }
}