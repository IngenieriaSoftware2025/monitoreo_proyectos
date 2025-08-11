<?php

namespace Model;

use Model\ActiveRecord;

class Visita extends ActiveRecord
{
    // Nombre de la tabla en la BD
    public static $tabla = 'visita';
    public static $idTabla = 'vis_id';

    // Columnas que se van a mapear a la BD
    public static $columnasDB = [
        'vis_apl_id',
        'vis_fecha',
        'vis_quien',
        'vis_motivo',
        'vis_procedimiento',
        'vis_solucion',
        'vis_observacion',
        'vis_conformidad',
        'vis_creado_por',
        'vis_creado_en',
        'vis_situacion'
    ];

    // Propiedades
    public $vis_id;
    public $vis_apl_id;
    public $vis_fecha;
    public $vis_quien;
    public $vis_motivo;
    public $vis_procedimiento;
    public $vis_solucion;
    public $vis_observacion;
    public $vis_conformidad;
    public $vis_creado_por;
    public $vis_creado_en;
    public $vis_situacion;

    // Estados de conformidad
    const CONFORMIDAD_SI = true;
    const CONFORMIDAD_NO = false;

    // Tipos de motivos comunes
    const MOTIVOS_COMUNES = [
        'REVISION_AVANCE',
        'VALIDACION_FUNCIONALIDAD',
        'RESOLUCION_BLOQUEADORES',
        'CAMBIO_REQUERIMIENTOS',
        'SOPORTE_TECNICO',
        'CAPACITACION',
        'AUDITORIA',
        'OTRO'
    ];

    public function __construct($args = [])
    {
        $this->vis_id = $args['vis_id'] ?? null;
        $this->vis_apl_id = $args['vis_apl_id'] ?? null;
        $this->vis_fecha = $args['vis_fecha'] ?? date('Y-m-d H:i');
        $this->vis_quien = $args['vis_quien'] ?? '';
        $this->vis_motivo = $args['vis_motivo'] ?? '';
        $this->vis_procedimiento = $args['vis_procedimiento'] ?? '';
        $this->vis_solucion = $args['vis_solucion'] ?? '';
        $this->vis_observacion = $args['vis_observacion'] ?? '';
        $this->vis_conformidad = $args['vis_conformidad'] ?? null;
        $this->vis_creado_por = $args['vis_creado_por'] ?? null;
        $this->vis_creado_en = $args['vis_creado_en'] ?? null;
        $this->vis_situacion = $args['vis_situacion'] ?? 1;
    }

    // Validaciones
    public function validar()
    {
        if (!$this->vis_apl_id) {
            self::$alertas['error'][] = 'La aplicación es obligatoria';
        }

        if (!$this->vis_fecha) {
            self::$alertas['error'][] = 'La fecha y hora de la visita es obligatoria';
        }

        // Validar formato de fecha y hora
        if ($this->vis_fecha && !$this->validarFormatoFechaHora($this->vis_fecha)) {
            self::$alertas['error'][] = 'El formato de fecha y hora no es válido (YYYY-MM-DD HH:MM)';
        }

        if (strlen($this->vis_quien) > 150) {
            self::$alertas['error'][] = 'El campo "quien" no puede tener más de 150 caracteres';
        }

        if (strlen($this->vis_motivo) > 400) {
            self::$alertas['error'][] = 'El motivo no puede tener más de 400 caracteres';
        }

        if (strlen($this->vis_procedimiento) > 400) {
            self::$alertas['error'][] = 'El procedimiento no puede tener más de 400 caracteres';
        }

        if (strlen($this->vis_solucion) > 400) {
            self::$alertas['error'][] = 'La solución no puede tener más de 400 caracteres';
        }

        if (strlen($this->vis_observacion) > 800) {
            self::$alertas['error'][] = 'La observación no puede tener más de 800 caracteres';
        }

        // Validar que si hay conformidad=false, debe haber observación
        if ($this->vis_conformidad === false && empty(trim($this->vis_observacion))) {
            self::$alertas['error'][] = 'Si la conformidad es "No", debe especificar las observaciones';
        }

        return self::$alertas;
    }

    // Validar formato de fecha y hora
    private function validarFormatoFechaHora($fecha)
    {
        $formatos = ['Y-m-d H:i:s', 'Y-m-d H:i'];
        
        foreach ($formatos as $formato) {
            $d = \DateTime::createFromFormat($formato, $fecha);
            if ($d && $d->format($formato) === $fecha) {
                return true;
            }
        }
        
        return false;
    }

    // Obtener visitas por aplicación
    public static function obtenerPorAplicacion($apl_id, $limite = null)
    {
        $query = "SELECT v.*, u.usu_nombre as creado_por_nombre, u.usu_grado
                  FROM " . self::$tabla . " v
                  LEFT JOIN usuario u ON v.vis_creado_por = u.usu_id
                  WHERE v.vis_apl_id = ? AND v.vis_situacion = 1
                  ORDER BY v.vis_fecha DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$apl_id]);
    }

    // Obtener visitas por usuario creador
    public static function obtenerPorCreador($usu_id, $limite = null)
    {
        $query = "SELECT v.*, a.apl_nombre, a.apl_estado
                  FROM " . self::$tabla . " v
                  JOIN aplicacion a ON v.vis_apl_id = a.apl_id
                  WHERE v.vis_creado_por = ? AND v.vis_situacion = 1 AND a.apl_situacion = 1
                  ORDER BY v.vis_fecha DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$usu_id]);
    }

    // Obtener visitas por rango de fechas
    public static function obtenerPorFechas($fecha_desde, $fecha_hasta = null)
    {
        if (!$fecha_hasta) {
            $fecha_hasta = date('Y-m-d 23:59:59');
        }

        $query = "SELECT v.*, a.apl_nombre, u.usu_nombre as creado_por_nombre
                  FROM " . self::$tabla . " v
                  JOIN aplicacion a ON v.vis_apl_id = a.apl_id
                  LEFT JOIN usuario u ON v.vis_creado_por = u.usu_id
                  WHERE v.vis_fecha BETWEEN ? AND ? 
                  AND v.vis_situacion = 1 AND a.apl_situacion = 1
                  ORDER BY v.vis_fecha DESC";
        
        return self::fetchArray($query, [$fecha_desde, $fecha_hasta]);
    }

    // Obtener visitas sin conformidad (alertas críticas)
    public static function obtenerSinConformidad()
    {
        $query = "SELECT v.*, a.apl_nombre, u.usu_nombre as creado_por_nombre,
                         resp.usu_nombre as responsable_nombre
                  FROM " . self::$tabla . " v
                  JOIN aplicacion a ON v.vis_apl_id = a.apl_id
                  LEFT JOIN usuario u ON v.vis_creado_por = u.usu_id
                  LEFT JOIN usuario resp ON a.apl_responsable = resp.usu_id
                  WHERE v.vis_conformidad = 0 AND v.vis_situacion = 1 
                  AND a.apl_situacion = 1
                  ORDER BY v.vis_fecha DESC";
        
        return self::fetchArray($query);
    }

    // Obtener últimas visitas de una aplicación
    public static function obtenerUltimasVisitas($apl_id, $limite = 5)
    {
        return self::obtenerPorAplicacion($apl_id, $limite);
    }

    // Obtener información completa de la visita
    public function obtenerCompleto()
    {
        $query = "SELECT v.*, a.apl_nombre, a.apl_estado, a.apl_responsable,
                         u.usu_nombre as creado_por_nombre, u.usu_grado,
                         resp.usu_nombre as responsable_nombre
                  FROM " . self::$tabla . " v
                  JOIN aplicacion a ON v.vis_apl_id = a.apl_id
                  LEFT JOIN usuario u ON v.vis_creado_por = u.usu_id
                  LEFT JOIN usuario resp ON a.apl_responsable = resp.usu_id
                  WHERE v.vis_id = ?";
        
        $resultado = self::fetchArray($query, [$this->vis_id]);
        return $resultado[0] ?? null;
    }

    // Calcular estadísticas de conformidad por aplicación
    public static function calcularConformidadAplicacion($apl_id, $dias = 30)
    {
        $fecha_inicio = date('Y-m-d H:i:s', strtotime("-$dias days"));
        
        $query = "SELECT 
                      COUNT(*) as total_visitas,
                      COUNT(CASE WHEN vis_conformidad = 1 THEN 1 END) as visitas_conformes,
                      COUNT(CASE WHEN vis_conformidad = 0 THEN 1 END) as visitas_no_conformes,
                      COUNT(CASE WHEN vis_conformidad IS NULL THEN 1 END) as visitas_sin_calificar,
                      MAX(vis_fecha) as ultima_visita
                  FROM " . self::$tabla . "
                  WHERE vis_apl_id = ? AND vis_fecha >= ? AND vis_situacion = 1";
        
        $resultado = self::fetchArray($query, [$apl_id, $fecha_inicio]);
        $stats = $resultado[0] ?? [];
        
        // Calcular porcentajes
        if ($stats && $stats['total_visitas'] > 0) {
            $stats['porcentaje_conformidad'] = round(($stats['visitas_conformes'] / $stats['total_visitas']) * 100, 2);
        } else {
            $stats['porcentaje_conformidad'] = 0;
        }
        
        return $stats;
    }

    // Obtener estadísticas generales de visitas
    public static function obtenerEstadisticasGenerales($fecha_desde = null, $fecha_hasta = null)
    {
        $fecha_desde = $fecha_desde ?: date('Y-m-d H:i:s', strtotime('-30 days'));
        $fecha_hasta = $fecha_hasta ?: date('Y-m-d H:i:s');
        
        $query = "SELECT 
                      COUNT(*) as total_visitas,
                      COUNT(DISTINCT vis_apl_id) as aplicaciones_visitadas,
                      COUNT(DISTINCT vis_creado_por) as usuarios_visitantes,
                      COUNT(CASE WHEN vis_conformidad = 1 THEN 1 END) as conformes,
                      COUNT(CASE WHEN vis_conformidad = 0 THEN 1 END) as no_conformes,
                      COUNT(CASE WHEN vis_conformidad IS NULL THEN 1 END) as sin_calificar,
                      AVG(CASE WHEN vis_conformidad IS NOT NULL THEN vis_conformidad END) as promedio_conformidad
                  FROM " . self::$tabla . "
                  WHERE vis_fecha BETWEEN ? AND ? AND vis_situacion = 1";
        
        $resultado = self::fetchArray($query, [$fecha_desde, $fecha_hasta]);
        return $resultado[0] ?? [];
    }

    // Obtener timeline de una aplicación (visitas + comentarios)
    public static function obtenerTimelineAplicacion($apl_id, $limite = 20)
    {
        $query = "SELECT 'VISITA' as tipo, vis_id as id, vis_fecha as fecha, 
                         vis_quien as autor, vis_motivo as contenido, 
                         vis_conformidad, vis_creado_por, vis_creado_en
                  FROM " . self::$tabla . "
                  WHERE vis_apl_id = ? AND vis_situacion = 1
                  
                  UNION ALL
                  
                  SELECT 'COMENTARIO' as tipo, com_id as id, com_creado_en as fecha,
                         u.usu_nombre as autor, com_texto as contenido,
                         NULL as vis_conformidad, com_autor_id as vis_creado_por, com_creado_en
                  FROM comentario c
                  JOIN usuario u ON c.com_autor_id = u.usu_id
                  WHERE c.com_apl_id = ? AND c.com_situacion = 1
                  
                  ORDER BY fecha DESC
                  LIMIT ?";
        
        return self::fetchArray($query, [$apl_id, $apl_id, $limite]);
    }

    // Detectar aplicaciones con problemas de conformidad
    public static function detectarProblemasConformidad($dias_limite = 7)
    {
        $fecha_inicio = date('Y-m-d H:i:s', strtotime("-$dias_limite days"));
        
        $query = "SELECT a.apl_id, a.apl_nombre, 
                         COUNT(v.vis_id) as visitas_recientes,
                         COUNT(CASE WHEN v.vis_conformidad = 0 THEN 1 END) as no_conformes,
                         MAX(v.vis_fecha) as ultima_visita_no_conforme,
                         resp.usu_nombre as responsable_nombre
                  FROM aplicacion a
                  LEFT JOIN " . self::$tabla . " v ON (a.apl_id = v.vis_apl_id AND v.vis_fecha >= ? AND v.vis_situacion = 1)
                  LEFT JOIN usuario resp ON a.apl_responsable = resp.usu_id
                  WHERE a.apl_estado IN ('EN_PROGRESO', 'EN_PLANIFICACION') AND a.apl_situacion = 1
                  GROUP BY a.apl_id, a.apl_nombre, resp.usu_nombre
                  HAVING no_conformes > 0
                  ORDER BY no_conformes DESC, ultima_visita_no_conforme DESC";
        
        return self::fetchArray($query, [$fecha_inicio]);
    }

    // Verificar si afecta al semáforo de la aplicación
    public function afectaSemaforo()
    {
        return $this->vis_conformidad === false;
    }

    // Obtener nivel de alerta basado en conformidad
    public function getNivelAlerta()
    {
        if ($this->vis_conformidad === false) {
            return 'CRITICA';
        } elseif ($this->vis_conformidad === null) {
            return 'PENDIENTE';
        } else {
            return 'NORMAL';
        }
    }

    // Marcar como atendida (cambiar conformidad)
    public function marcarComoAtendida($conformidad, $observacion_adicional = '')
    {
        $this->vis_conformidad = $conformidad;
        
        if ($observacion_adicional) {
            $this->vis_observacion = trim($this->vis_observacion . "\n\n--- ACTUALIZACIÓN ---\n" . $observacion_adicional);
        }
        
        return $this->actualizar();
    }

    // Crear visita con validaciones completas
    public function crearConValidaciones()
    {
        // Validaciones básicas
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que la aplicación existe y está activa
        $query = "SELECT apl_estado FROM aplicacion WHERE apl_id = ? AND apl_situacion = 1";
        $resultado = self::fetchArray($query, [$this->vis_apl_id]);
        if (!$resultado) {
            self::$alertas['error'][] = 'La aplicación no existe o no está activa';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que el usuario creador existe (si se especifica)
        if ($this->vis_creado_por) {
            $query = "SELECT usu_activo FROM usuario WHERE usu_id = ? AND usu_situacion = 1";
            $resultado = self::fetchArray($query, [$this->vis_creado_por]);
            if (!$resultado || !$resultado[0]['usu_activo']) {
                self::$alertas['error'][] = 'El usuario creador no existe o no está activo';
                return ['resultado' => false, 'alertas' => self::$alertas];
            }
        }

        // Establecer fecha de creación
        if (!$this->vis_creado_en) {
            $this->vis_creado_en = date('Y-m-d H:i:s');
        }

        // Normalizar fecha de visita
        if ($this->vis_fecha && strlen($this->vis_fecha) == 16) {
            // Si solo tiene minutos, agregar segundos
            $this->vis_fecha .= ':00';
        }

        // Crear la visita
        $resultado = $this->crear();
        
        if ($resultado['resultado']) {
            return ['resultado' => true, 'id' => $resultado['id']];
        } else {
            self::$alertas['error'][] = 'Error al crear la visita';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Actualizar visita con validaciones
    public function actualizarConValidaciones()
    {
        // Validaciones básicas
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Normalizar fecha de visita
        if ($this->vis_fecha && strlen($this->vis_fecha) == 16) {
            $this->vis_fecha .= ':00';
        }

        // Actualizar la visita
        $resultado = $this->actualizar();
        
        if ($resultado['resultado']) {
            return ['resultado' => true];
        } else {
            self::$alertas['error'][] = 'Error al actualizar la visita';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Verificar si se puede eliminar
    public function puedeEliminar()
    {
        // Solo se puede eliminar si es del mismo día o si el usuario es gerente
        $fecha_visita = date('Y-m-d', strtotime($this->vis_fecha));
        $fecha_actual = date('Y-m-d');
        
        return $fecha_visita === $fecha_actual;
    }

    // Soft delete
    public function eliminarLogico()
    {
        $this->vis_situacion = 0;
        return $this->actualizar();
    }

    // Generar reporte de conformidad
    public static function generarReporteConformidad($fecha_desde, $fecha_hasta)
    {
        $query = "SELECT a.apl_nombre, a.apl_estado,
                         COUNT(v.vis_id) as total_visitas,
                         COUNT(CASE WHEN v.vis_conformidad = 1 THEN 1 END) as conformes,
                         COUNT(CASE WHEN v.vis_conformidad = 0 THEN 1 END) as no_conformes,
                         ROUND(AVG(CASE WHEN v.vis_conformidad IS NOT NULL THEN v.vis_conformidad END) * 100, 2) as porcentaje_conformidad,
                         MAX(v.vis_fecha) as ultima_visita
                  FROM aplicacion a
                  LEFT JOIN " . self::$tabla . " v ON (a.apl_id = v.vis_apl_id 
                      AND v.vis_fecha BETWEEN ? AND ? AND v.vis_situacion = 1)
                  WHERE a.apl_situacion = 1
                  GROUP BY a.apl_id, a.apl_nombre, a.apl_estado
                  ORDER BY porcentaje_conformidad ASC, total_visitas DESC";
        
        return self::fetchArray($query, [$fecha_desde, $fecha_hasta]);
    }
}