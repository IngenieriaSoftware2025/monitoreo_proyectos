<?php

namespace Model;

use Model\ActiveRecord;

class AvanceDiario extends ActiveRecord
{
    // Nombre de la tabla en la BD
    public static $tabla = 'avance_diario';
    public static $idTabla = 'ava_id';

    // Columnas que se van a mapear a la BD
    public static $columnasDB = [
        'ava_apl_id',
        'ava_usu_id',
        'ava_fecha',
        'ava_porcentaje',
        'ava_resumen',
        'ava_bloqueadores',
        'ava_justificacion',
        'ava_creado_en',
        'ava_situacion'
    ];

    // Propiedades
    public $ava_id;
    public $ava_apl_id;
    public $ava_usu_id;
    public $ava_fecha;
    public $ava_porcentaje;
    public $ava_resumen;
    public $ava_bloqueadores;
    public $ava_justificacion;
    public $ava_creado_en;
    public $ava_situacion;

    // Constantes para validaciones
    const HORA_LIMITE_EDICION = '18:00:00';
    const PORCENTAJE_MIN = 0;
    const PORCENTAJE_MAX = 100;

    public function __construct($args = [])
    {
        $this->ava_id = $args['ava_id'] ?? null;
        $this->ava_apl_id = $args['ava_apl_id'] ?? null;
        $this->ava_usu_id = $args['ava_usu_id'] ?? null;
        $this->ava_fecha = $args['ava_fecha'] ?? date('Y-m-d');
        $this->ava_porcentaje = $args['ava_porcentaje'] ?? 0;
        $this->ava_resumen = $args['ava_resumen'] ?? '';
        $this->ava_bloqueadores = $args['ava_bloqueadores'] ?? '';
        $this->ava_justificacion = $args['ava_justificacion'] ?? '';
        $this->ava_creado_en = $args['ava_creado_en'] ?? null;
        $this->ava_situacion = $args['ava_situacion'] ?? 1;
    }

    // Validaciones
    public function validar()
    {
        if (!$this->ava_apl_id) {
            self::$alertas['error'][] = 'La aplicación es obligatoria';
        }

        if (!$this->ava_usu_id) {
            self::$alertas['error'][] = 'El usuario es obligatorio';
        }

        if (!$this->ava_fecha) {
            self::$alertas['error'][] = 'La fecha es obligatoria';
        }

        if ($this->ava_porcentaje < self::PORCENTAJE_MIN || $this->ava_porcentaje > self::PORCENTAJE_MAX) {
            self::$alertas['error'][] = 'El porcentaje debe estar entre ' . self::PORCENTAJE_MIN . ' y ' . self::PORCENTAJE_MAX;
        }

        if (strlen($this->ava_resumen) > 800) {
            self::$alertas['error'][] = 'El resumen no puede tener más de 800 caracteres';
        }

        if (strlen($this->ava_bloqueadores) > 400) {
            self::$alertas['error'][] = 'Los bloqueadores no pueden tener más de 400 caracteres';
        }

        if (strlen($this->ava_justificacion) > 800) {
            self::$alertas['error'][] = 'La justificación no puede tener más de 800 caracteres';
        }

        return self::$alertas;
    }

    // Verificar si ya existe un avance para esta app/usuario/fecha
    public static function existeAvanceDiario($apl_id, $usu_id, $fecha, $excluir_id = null)
    {
        $query = "SELECT ava_id FROM " . self::$tabla . " 
                  WHERE ava_apl_id = ? AND ava_usu_id = ? AND ava_fecha = ? AND ava_situacion = 1";
        $params = [$apl_id, $usu_id, $fecha];
        
        if ($excluir_id) {
            $query .= " AND ava_id != ?";
            $params[] = $excluir_id;
        }

        $resultado = self::$db->prepare($query);
        $resultado->execute($params);
        
        return $resultado->fetch();
    }

    // Obtener último avance del usuario en la aplicación
    public static function obtenerUltimoAvance($apl_id, $usu_id)
    {
        $query = "SELECT * FROM " . self::$tabla . " 
                  WHERE ava_apl_id = ? AND ava_usu_id = ? AND ava_situacion = 1
                  ORDER BY ava_fecha DESC, ava_creado_en DESC
                  LIMIT 1";
        
        $resultado = self::fetchArray($query, [$apl_id, $usu_id]);
        return $resultado[0] ?? null;
    }

    // Validar regla de monotonía (porcentaje no debe bajar)
    public function validarMonotonia()
    {
        $ultimo_avance = self::obtenerUltimoAvance($this->ava_apl_id, $this->ava_usu_id);
        
        if ($ultimo_avance && $this->ava_porcentaje < $ultimo_avance['ava_porcentaje']) {
            if (empty(trim($this->ava_justificacion))) {
                self::$alertas['error'][] = 'El porcentaje no puede ser menor al anterior (' . 
                    $ultimo_avance['ava_porcentaje'] . '%) sin justificación';
                return false;
            }
        }
        
        return true;
    }

    // Verificar si se puede editar (antes de la hora límite del mismo día)
    public function puedeEditar()
    {
        if (!$this->ava_fecha || !$this->ava_creado_en) {
            return false;
        }

        $fecha_avance = date('Y-m-d', strtotime($this->ava_fecha));
        $fecha_actual = date('Y-m-d');
        
        // Solo se puede editar el mismo día
        if ($fecha_avance !== $fecha_actual) {
            return false;
        }

        // Verificar hora límite
        $hora_actual = date('H:i:s');
        if ($hora_actual > self::HORA_LIMITE_EDICION) {
            return false;
        }

        return true;
    }

    // Obtener avances por aplicación
    public static function obtenerPorAplicacion($apl_id, $limite = null)
    {
        $query = "SELECT av.*, u.usu_nombre, u.usu_grado
                  FROM " . self::$tabla . " av
                  JOIN usuario u ON av.ava_usu_id = u.usu_id
                  WHERE av.ava_apl_id = ? AND av.ava_situacion = 1
                  ORDER BY av.ava_fecha DESC, av.ava_creado_en DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$apl_id]);
    }

    // Obtener avances por usuario
    public static function obtenerPorUsuario($usu_id, $limite = null)
    {
        $query = "SELECT av.*, a.apl_nombre, a.apl_estado
                  FROM " . self::$tabla . " av
                  JOIN aplicacion a ON av.ava_apl_id = a.apl_id
                  WHERE av.ava_usu_id = ? AND av.ava_situacion = 1 AND a.apl_situacion = 1
                  ORDER BY av.ava_fecha DESC, av.ava_creado_en DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$usu_id]);
    }

    // Obtener avances por fecha
    public static function obtenerPorFecha($fecha)
    {
        $query = "SELECT av.*, a.apl_nombre, u.usu_nombre, u.usu_grado
                  FROM " . self::$tabla . " av
                  JOIN aplicacion a ON av.ava_apl_id = a.apl_id
                  JOIN usuario u ON av.ava_usu_id = u.usu_id
                  WHERE av.ava_fecha = ? AND av.ava_situacion = 1 
                  AND a.apl_situacion = 1 AND u.usu_situacion = 1
                  ORDER BY a.apl_nombre, u.usu_nombre";
        
        return self::fetchArray($query, [$fecha]);
    }

    // Obtener aplicaciones sin reporte hoy
    public static function obtenerAplicacionesSinReporte($fecha = null)
    {
        if (!$fecha) {
            $fecha = date('Y-m-d');
        }

        $query = "SELECT DISTINCT a.apl_id, a.apl_nombre, u.usu_nombre as responsable_nombre,
                         a.apl_estado
                  FROM aplicacion a
                  LEFT JOIN usuario u ON a.apl_responsable = u.usu_id
                  LEFT JOIN avance_diario av ON (a.apl_id = av.ava_apl_id AND av.ava_fecha = ? AND av.ava_situacion = 1)
                  WHERE a.apl_estado = 'EN_PROGRESO' 
                  AND a.apl_situacion = 1
                  AND av.ava_id IS NULL
                  ORDER BY a.apl_nombre";
        
        return self::fetchArray($query, [$fecha]);
    }

    // Obtener tendencia de una aplicación (últimos N días)
    public static function obtenerTendencia($apl_id, $dias = 30)
    {
        $fecha_inicio = date('Y-m-d', strtotime("-$dias days"));
        
        $query = "SELECT ava_fecha, MAX(ava_porcentaje) as porcentaje_max,
                         AVG(ava_porcentaje) as porcentaje_promedio,
                         COUNT(*) as reportes_del_dia
                  FROM " . self::$tabla . "
                  WHERE ava_apl_id = ? AND ava_fecha >= ? AND ava_situacion = 1
                  GROUP BY ava_fecha
                  ORDER BY ava_fecha";
        
        return self::fetchArray($query, [$apl_id, $fecha_inicio]);
    }

    // Obtener estadísticas del usuario
    public static function obtenerEstadisticasUsuario($usu_id, $dias = 30)
    {
        $fecha_inicio = date('Y-m-d', strtotime("-$dias days"));
        
        $query = "SELECT 
                      COUNT(*) as total_reportes,
                      COUNT(DISTINCT ava_apl_id) as aplicaciones_trabajadas,
                      COUNT(DISTINCT ava_fecha) as dias_activos,
                      AVG(ava_porcentaje) as promedio_porcentaje,
                      MAX(ava_porcentaje) as maximo_porcentaje,
                      COUNT(CASE WHEN ava_bloqueadores IS NOT NULL AND ava_bloqueadores != '' THEN 1 END) as reportes_con_bloqueadores,
                      COUNT(CASE WHEN ava_justificacion IS NOT NULL AND ava_justificacion != '' THEN 1 END) as reportes_con_justificacion
                  FROM " . self::$tabla . "
                  WHERE ava_usu_id = ? AND ava_fecha >= ? AND ava_situacion = 1";
        
        $resultado = self::fetchArray($query, [$usu_id, $fecha_inicio]);
        return $resultado[0] ?? [];
    }

    // Detectar aplicaciones con bloqueadores críticos
    public static function obtenerBloqueadoresCriticos($horas_limite = 24)
    {
        $fecha_limite = date('Y-m-d H:i:s', strtotime("-$horas_limite hours"));
        
        $query = "SELECT av.*, a.apl_nombre, u.usu_nombre
                  FROM " . self::$tabla . " av
                  JOIN aplicacion a ON av.ava_apl_id = a.apl_id
                  JOIN usuario u ON av.ava_usu_id = u.usu_id
                  WHERE av.ava_bloqueadores IS NOT NULL 
                  AND av.ava_bloqueadores != ''
                  AND av.ava_bloqueadores LIKE '%crítico%'
                  AND av.ava_creado_en <= ?
                  AND av.ava_situacion = 1
                  AND a.apl_estado = 'EN_PROGRESO'
                  ORDER BY av.ava_creado_en";
        
        return self::fetchArray($query, [$fecha_limite]);
    }

    // Obtener semáforo de una aplicación
    public static function calcularSemaforoAplicacion($apl_id)
    {
        $hoy = date('Y-m-d');
        $ayer = date('Y-m-d', strtotime('-1 day'));
        $antier = date('Y-m-d', strtotime('-2 days'));

        // Verificar si hay reporte hoy
        $reporte_hoy = self::existeAvanceDiario($apl_id, null, $hoy);
        
        // Verificar bloqueadores críticos recientes
        $bloqueadores_criticos = self::fetchArray(
            "SELECT COUNT(*) as total FROM " . self::$tabla . "
             WHERE ava_apl_id = ? AND ava_bloqueadores LIKE '%crítico%' 
             AND ava_fecha >= ? AND ava_situacion = 1",
            [$apl_id, $ayer]
        );
        
        $tiene_bloqueadores_criticos = ($bloqueadores_criticos[0]['total'] ?? 0) > 0;

        // Contar días sin reporte
        $reportes_recientes = self::fetchArray(
            "SELECT COUNT(DISTINCT ava_fecha) as dias_con_reporte
             FROM " . self::$tabla . "
             WHERE ava_apl_id = ? AND ava_fecha IN (?, ?, ?) AND ava_situacion = 1",
            [$apl_id, $hoy, $ayer, $antier]
        );
        
        $dias_con_reporte = $reportes_recientes[0]['dias_con_reporte'] ?? 0;

        // Determinar color del semáforo
        if ($tiene_bloqueadores_criticos || $dias_con_reporte == 0) {
            return 'ROJO';
        } elseif (!$reporte_hoy || $dias_con_reporte < 2) {
            return 'AMBAR';
        } else {
            return 'VERDE';
        }
    }

    // Crear avance con validaciones completas
    public function crearConValidaciones()
    {
        // Validaciones básicas
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no exista avance para esta fecha
        $existe = self::existeAvanceDiario($this->ava_apl_id, $this->ava_usu_id, $this->ava_fecha);
        if ($existe) {
            self::$alertas['error'][] = 'Ya existe un avance registrado para esta fecha';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Validar que la aplicación esté en progreso
        $query = "SELECT apl_estado FROM aplicacion WHERE apl_id = ? AND apl_situacion = 1";
        $resultado = self::fetchArray($query, [$this->ava_apl_id]);
        if (!$resultado || $resultado[0]['apl_estado'] !== 'EN_PROGRESO') {
            self::$alertas['error'][] = 'Solo se pueden registrar avances en aplicaciones en progreso';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Validar regla de monotonía
        if (!$this->validarMonotonia()) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Establecer fecha de creación
        if (!$this->ava_creado_en) {
            $this->ava_creado_en = date('Y-m-d H:i:s');
        }

        // Crear el avance
        $resultado = $this->crear();
        
        if ($resultado['resultado']) {
            return ['resultado' => true, 'id' => $resultado['id']];
        } else {
            self::$alertas['error'][] = 'Error al crear el avance diario';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Actualizar avance con validaciones
    public function actualizarConValidaciones()
    {
        // Verificar si se puede editar
        if (!$this->puedeEditar()) {
            self::$alertas['error'][] = 'No se puede editar este avance. Solo se puede editar el mismo día antes de las ' . self::HORA_LIMITE_EDICION;
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Validaciones básicas
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Validar regla de monotonía (excluyendo el registro actual)
        $ultimo_avance = self::fetchArray(
            "SELECT * FROM " . self::$tabla . " 
             WHERE ava_apl_id = ? AND ava_usu_id = ? AND ava_id != ? AND ava_situacion = 1
             ORDER BY ava_fecha DESC, ava_creado_en DESC LIMIT 1",
            [$this->ava_apl_id, $this->ava_usu_id, $this->ava_id]
        );

        if ($ultimo_avance && $this->ava_porcentaje < $ultimo_avance[0]['ava_porcentaje']) {
            if (empty(trim($this->ava_justificacion))) {
                self::$alertas['error'][] = 'El porcentaje no puede ser menor al anterior (' . 
                    $ultimo_avance[0]['ava_porcentaje'] . '%) sin justificación';
                return ['resultado' => false, 'alertas' => self::$alertas];
            }
        }

        // Actualizar el avance
        $resultado = $this->actualizar();
        
        if ($resultado['resultado']) {
            return ['resultado' => true];
        } else {
            self::$alertas['error'][] = 'Error al actualizar el avance diario';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Obtener información completa del avance
    public function obtenerCompleto()
    {
        $query = "SELECT av.*, a.apl_nombre, u.usu_nombre, u.usu_grado
                  FROM " . self::$tabla . " av
                  JOIN aplicacion a ON av.ava_apl_id = a.apl_id
                  JOIN usuario u ON av.ava_usu_id = u.usu_id
                  WHERE av.ava_id = ?";
        
        $resultado = self::fetchArray($query, [$this->ava_id]);
        return $resultado[0] ?? null;
    }

    // Verificar cumplimiento de reporte diario por desarrollador
    public static function calcularCumplimientoReporte($usu_id, $dias = 30)
    {
        $fecha_inicio = date('Y-m-d', strtotime("-$dias days"));
        
        // Días con reporte
        $query1 = "SELECT COUNT(DISTINCT ava_fecha) as dias_con_reporte
                   FROM " . self::$tabla . " av
                   JOIN aplicacion a ON av.ava_apl_id = a.apl_id
                   WHERE av.ava_usu_id = ? AND av.ava_fecha >= ? 
                   AND av.ava_situacion = 1 AND a.apl_estado = 'EN_PROGRESO'";
        
        $resultado1 = self::fetchArray($query1, [$usu_id, $fecha_inicio]);
        $dias_con_reporte = $resultado1[0]['dias_con_reporte'] ?? 0;

        // Días hábiles totales (aproximado)
        $dias_habiles = $dias * 5 / 7; // Aproximación de días hábiles
        
        return [
            'dias_con_reporte' => $dias_con_reporte,
            'dias_habiles_aprox' => round($dias_habiles),
            'porcentaje_cumplimiento' => $dias_habiles > 0 ? round(($dias_con_reporte / $dias_habiles) * 100, 2) : 0
        ];
    }
}