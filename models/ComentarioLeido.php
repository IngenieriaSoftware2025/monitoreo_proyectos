<?php

namespace Model;

use Model\ActiveRecord;

class ComentarioLeido extends ActiveRecord
{
    // Nombre de la tabla en la BD
    public static $tabla = 'comentario_leido';
    public static $idTabla = 'col_id';

    // Columnas que se van a mapear a la BD
    public static $columnasDB = [
        'col_com_id',
        'col_usu_id',
        'col_leido_en',
        'col_situacion'
    ];

    // Propiedades
    public $col_id;
    public $col_com_id;
    public $col_usu_id;
    public $col_leido_en;
    public $col_situacion;

    public function __construct($args = [])
    {
        $this->col_id = $args['col_id'] ?? null;
        $this->col_com_id = $args['col_com_id'] ?? null;
        $this->col_usu_id = $args['col_usu_id'] ?? null;
        $this->col_leido_en = $args['col_leido_en'] ?? null;
        $this->col_situacion = $args['col_situacion'] ?? 1;
    }

    // Validaciones
    public function validar()
    {
        if (!$this->col_com_id) {
            self::$alertas['error'][] = 'El comentario es obligatorio';
        }

        if (!$this->col_usu_id) {
            self::$alertas['error'][] = 'El usuario es obligatorio';
        }

        if (!$this->col_leido_en) {
            self::$alertas['error'][] = 'La fecha de lectura es obligatoria';
        }

        return self::$alertas;
    }

    // Verificar si ya existe el registro de lectura
    public static function existeLectura($com_id, $usu_id)
    {
        $query = "SELECT col_id FROM " . self::$tabla . " 
                  WHERE col_com_id = ? AND col_usu_id = ? AND col_situacion = 1";
        
        $resultado = self::$db->prepare($query);
        $resultado->execute([$com_id, $usu_id]);
        
        return $resultado->fetch();
    }

    // Registrar lectura de comentario
    public static function registrarLectura($com_id, $usu_id, $fecha_lectura = null)
    {
        // Verificar si ya existe
        $existe = self::existeLectura($com_id, $usu_id);
        if ($existe) {
            return ['resultado' => true, 'mensaje' => 'Ya estaba marcado como leído', 'id' => $existe['col_id']];
        }

        // Verificar que el comentario existe
        $query_comentario = "SELECT com_id FROM comentario WHERE com_id = ? AND com_situacion = 1";
        $comentario = self::fetchArray($query_comentario, [$com_id]);
        if (!$comentario) {
            return ['resultado' => false, 'mensaje' => 'El comentario no existe'];
        }

        // Verificar que el usuario existe
        $query_usuario = "SELECT usu_id FROM usuario WHERE usu_id = ? AND usu_activo = true AND usu_situacion = 1";
        $usuario = self::fetchArray($query_usuario, [$usu_id]);
        if (!$usuario) {
            return ['resultado' => false, 'mensaje' => 'El usuario no existe o no está activo'];
        }

        // Crear registro de lectura
        $lectura = new self([
            'col_com_id' => $com_id,
            'col_usu_id' => $usu_id,
            'col_leido_en' => $fecha_lectura ?: date('Y-m-d H:i:s')
        ]);

        $resultado = $lectura->crear();
        
        if ($resultado['resultado']) {
            return ['resultado' => true, 'mensaje' => 'Lectura registrada correctamente', 'id' => $resultado['id']];
        } else {
            return ['resultado' => false, 'mensaje' => 'Error al registrar la lectura'];
        }
    }

    // Marcar múltiples comentarios como leídos
    public static function marcarMultiplesLeidos($comentarios_ids, $usu_id)
    {
        if (empty($comentarios_ids)) {
            return ['resultado' => true, 'total' => 0];
        }

        $registrados = 0;
        $errores = [];

        foreach ($comentarios_ids as $com_id) {
            $resultado = self::registrarLectura($com_id, $usu_id);
            if ($resultado['resultado']) {
                $registrados++;
            } else {
                $errores[] = "Comentario $com_id: " . $resultado['mensaje'];
            }
        }

        return [
            'resultado' => true,
            'total_registrados' => $registrados,
            'total_intentados' => count($comentarios_ids),
            'errores' => $errores
        ];
    }

    // Obtener usuarios que han leído un comentario
    public static function obtenerUsuariosLeido($com_id)
    {
        $query = "SELECT cl.col_leido_en, u.usu_id, u.usu_nombre, u.usu_grado, u.usu_email
                  FROM " . self::$tabla . " cl
                  JOIN usuario u ON cl.col_usu_id = u.usu_id
                  WHERE cl.col_com_id = ? AND cl.col_situacion = 1 AND u.usu_situacion = 1
                  ORDER BY cl.col_leido_en ASC";
        
        return self::fetchArray($query, [$com_id]);
    }

    // Contar usuarios que han leído un comentario
    public static function contarUsuariosLeido($com_id)
    {
        $query = "SELECT COUNT(*) as total
                  FROM " . self::$tabla . " cl
                  JOIN usuario u ON cl.col_usu_id = u.usu_id
                  WHERE cl.col_com_id = ? AND cl.col_situacion = 1 AND u.usu_situacion = 1";
        
        $resultado = self::fetchArray($query, [$com_id]);
        return $resultado[0]['total'] ?? 0;
    }

    // Obtener comentarios no leídos por usuario en una aplicación
    public static function obtenerNoLeidosEnAplicacion($usu_id, $apl_id)
    {
        $query = "SELECT c.com_id, c.com_texto, c.com_creado_en, u.usu_nombre as autor
                  FROM comentario c
                  JOIN usuario u ON c.com_autor_id = u.usu_id
                  LEFT JOIN " . self::$tabla . " cl ON (c.com_id = cl.col_com_id AND cl.col_usu_id = ? AND cl.col_situacion = 1)
                  WHERE c.com_apl_id = ? AND c.com_situacion = 1 AND u.usu_situacion = 1
                  AND cl.col_id IS NULL
                  AND c.com_autor_id != ?  -- Excluir comentarios propios
                  ORDER BY c.com_creado_en DESC";
        
        return self::fetchArray($query, [$usu_id, $apl_id, $usu_id]);
    }

    // Contar comentarios no leídos por usuario en todas las aplicaciones
    public static function contarTotalNoLeidos($usu_id)
    {
        $query = "SELECT COUNT(*) as total
                  FROM comentario c
                  LEFT JOIN " . self::$tabla . " cl ON (c.com_id = cl.col_com_id AND cl.col_usu_id = ? AND cl.col_situacion = 1)
                  WHERE c.com_situacion = 1
                  AND cl.col_id IS NULL
                  AND c.com_autor_id != ?";
        
        $resultado = self::fetchArray($query, [$usu_id, $usu_id]);
        return $resultado[0]['total'] ?? 0;
    }

    // Contar comentarios no leídos por aplicación para un usuario
    public static function contarNoLeidosPorAplicacion($usu_id)
    {
        $query = "SELECT a.apl_id, a.apl_nombre, COUNT(c.com_id) as comentarios_no_leidos
                  FROM aplicacion a
                  LEFT JOIN comentario c ON a.apl_id = c.com_apl_id AND c.com_situacion = 1
                  LEFT JOIN " . self::$tabla . " cl ON (c.com_id = cl.col_com_id AND cl.col_usu_id = ? AND cl.col_situacion = 1)
                  WHERE a.apl_situacion = 1
                  AND c.com_id IS NOT NULL
                  AND cl.col_id IS NULL
                  AND c.com_autor_id != ?
                  GROUP BY a.apl_id, a.apl_nombre
                  HAVING comentarios_no_leidos > 0
                  ORDER BY comentarios_no_leidos DESC";
        
        return self::fetchArray($query, [$usu_id, $usu_id]);
    }

    // Marcar todos los comentarios de una aplicación como leídos
    public static function marcarTodosLeidosEnAplicacion($usu_id, $apl_id)
    {
        // Obtener comentarios no leídos en la aplicación
        $comentarios_no_leidos = self::obtenerNoLeidosEnAplicacion($usu_id, $apl_id);
        
        if (empty($comentarios_no_leidos)) {
            return ['resultado' => true, 'total' => 0, 'mensaje' => 'No hay comentarios pendientes'];
        }

        $comentarios_ids = array_column($comentarios_no_leidos, 'com_id');
        return self::marcarMultiplesLeidos($comentarios_ids, $usu_id);
    }

    // Obtener estadísticas de lectura por aplicación
    public static function obtenerEstadisticasLectura($apl_id, $dias = 30)
    {
        $fecha_inicio = date('Y-m-d H:i:s', strtotime("-$dias days"));
        
        $query = "SELECT 
                      COUNT(DISTINCT c.com_id) as total_comentarios,
                      COUNT(cl.col_id) as total_lecturas,
                      COUNT(DISTINCT cl.col_usu_id) as usuarios_lectores,
                      AVG(TIMESTAMPDIFF(MINUTE, c.com_creado_en, cl.col_leido_en)) as minutos_promedio_lectura
                  FROM comentario c
                  LEFT JOIN " . self::$tabla . " cl ON (c.com_id = cl.col_com_id AND cl.col_situacion = 1)
                  WHERE c.com_apl_id = ? AND c.com_creado_en >= ? AND c.com_situacion = 1";
        
        $resultado = self::fetchArray($query, [$apl_id, $fecha_inicio]);
        $stats = $resultado[0] ?? [];
        
        // Calcular porcentaje de lectura
        if ($stats && $stats['total_comentarios'] > 0) {
            $stats['porcentaje_lectura'] = round(($stats['total_lecturas'] / $stats['total_comentarios']) * 100, 2);
        } else {
            $stats['porcentaje_lectura'] = 0;
        }
        
        return $stats;
    }

    // Obtener usuarios más activos en lectura
    public static function obtenerUsuariosMasActivosLectura($dias = 30, $limite = 10)
    {
        $fecha_inicio = date('Y-m-d H:i:s', strtotime("-$dias days"));
        
        $query = "SELECT u.usu_id, u.usu_nombre, u.usu_grado,
                         COUNT(cl.col_id) as total_lecturas,
                         COUNT(DISTINCT c.com_apl_id) as aplicaciones_leidas,
                         MAX(cl.col_leido_en) as ultima_lectura
                  FROM " . self::$tabla . " cl
                  JOIN usuario u ON cl.col_usu_id = u.usu_id
                  JOIN comentario c ON cl.col_com_id = c.com_id
                  WHERE cl.col_leido_en >= ? AND cl.col_situacion = 1 
                  AND u.usu_situacion = 1 AND c.com_situacion = 1
                  GROUP BY u.usu_id, u.usu_nombre, u.usu_grado
                  ORDER BY total_lecturas DESC
                  LIMIT ?";
        
        return self::fetchArray($query, [$fecha_inicio, $limite]);
    }

    // Obtener tiempo promedio de lectura por usuario
    public static function obtenerTiempoPromedioLectura($usu_id, $dias = 30)
    {
        $fecha_inicio = date('Y-m-d H:i:s', strtotime("-$dias days"));
        
        $query = "SELECT 
                      COUNT(*) as total_lecturas,
                      AVG(TIMESTAMPDIFF(MINUTE, c.com_creado_en, cl.col_leido_en)) as minutos_promedio,
                      MIN(TIMESTAMPDIFF(MINUTE, c.com_creado_en, cl.col_leido_en)) as minutos_minimo,
                      MAX(TIMESTAMPDIFF(MINUTE, c.com_creado_en, cl.col_leido_en)) as minutos_maximo
                  FROM " . self::$tabla . " cl
                  JOIN comentario c ON cl.col_com_id = c.com_id
                  WHERE cl.col_usu_id = ? AND cl.col_leido_en >= ? 
                  AND cl.col_situacion = 1 AND c.com_situacion = 1";
        
        $resultado = self::fetchArray($query, [$usu_id, $fecha_inicio]);
        return $resultado[0] ?? [];
    }

    // Detectar comentarios con baja tasa de lectura
    public static function detectarComentariosBajaLectura($apl_id = null, $porcentaje_limite = 50)
    {
        $query = "SELECT c.com_id, c.com_texto, c.com_creado_en, 
                         autor.usu_nombre as autor_nombre,
                         a.apl_nombre,
                         COUNT(cl.col_id) as total_lecturas,
                         COUNT(DISTINCT u_activos.usu_id) as usuarios_activos_total,
                         ROUND((COUNT(cl.col_id) / COUNT(DISTINCT u_activos.usu_id)) * 100, 2) as porcentaje_lectura
                  FROM comentario c
                  JOIN usuario autor ON c.com_autor_id = autor.usu_id
                  JOIN aplicacion a ON c.com_apl_id = a.apl_id
                  CROSS JOIN usuario u_activos
                  LEFT JOIN " . self::$tabla . " cl ON (c.com_id = cl.col_com_id AND cl.col_situacion = 1)
                  WHERE c.com_creado_en >= CURRENT - 7 UNITS DAY
                  AND c.com_situacion = 1 AND a.apl_situacion = 1
                  AND u_activos.usu_activo = true AND u_activos.usu_situacion = 1
                  AND u_activos.usu_id != c.com_autor_id"; // Excluir al autor
        
        if ($apl_id) {
            $query .= " AND c.com_apl_id = ?";
        }
        
        $query .= " GROUP BY c.com_id, c.com_texto, c.com_creado_en, autor.usu_nombre, a.apl_nombre
                   HAVING porcentaje_lectura < ?
                   ORDER BY porcentaje_lectura ASC";
        
        $params = $apl_id ? [$apl_id, $porcentaje_limite] : [$porcentaje_limite];
        
        return self::fetchArray($query, $params);
    }

    // Crear registro de lectura con validaciones
    public function crearConValidaciones()
    {
        // Validaciones básicas
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que no exista ya el registro
        $existe = self::existeLectura($this->col_com_id, $this->col_usu_id);
        if ($existe) {
            return ['resultado' => true, 'mensaje' => 'Ya estaba marcado como leído', 'id' => $existe['col_id']];
        }

        // Establecer fecha de lectura si no existe
        if (!$this->col_leido_en) {
            $this->col_leido_en = date('Y-m-d H:i:s');
        }

        // Crear el registro
        $resultado = $this->crear();
        
        if ($resultado['resultado']) {
            return ['resultado' => true, 'id' => $resultado['id']];
        } else {
            self::$alertas['error'][] = 'Error al registrar la lectura';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Obtener información completa del registro de lectura
    public function obtenerCompleto()
    {
        $query = "SELECT cl.*, c.com_texto, c.com_creado_en as comentario_creado,
                         u.usu_nombre, u.usu_grado, a.apl_nombre,
                         autor.usu_nombre as autor_comentario
                  FROM " . self::$tabla . " cl
                  JOIN comentario c ON cl.col_com_id = c.com_id
                  JOIN usuario u ON cl.col_usu_id = u.usu_id
                  JOIN aplicacion a ON c.com_apl_id = a.apl_id
                  JOIN usuario autor ON c.com_autor_id = autor.usu_id
                  WHERE cl.col_id = ?";
        
        $resultado = self::fetchArray($query, [$this->col_id]);
        return $resultado[0] ?? null;
    }

    // Desmarcar como leído (soft delete)
    public function desmarcarLeido()
    {
        $this->col_situacion = 0;
        return $this->actualizar();
    }

    // Generar reporte de engagement de comentarios
    public static function generarReporteEngagement($fecha_desde, $fecha_hasta)
    {
        $query = "SELECT a.apl_nombre,
                         COUNT(DISTINCT c.com_id) as total_comentarios,
                         COUNT(cl.col_id) as total_lecturas,
                         COUNT(DISTINCT c.com_autor_id) as autores_unicos,
                         COUNT(DISTINCT cl.col_usu_id) as lectores_unicos,
                         ROUND(AVG(TIMESTAMPDIFF(HOUR, c.com_creado_en, cl.col_leido_en)), 2) as horas_promedio_lectura,
                         ROUND((COUNT(cl.col_id) / COUNT(DISTINCT c.com_id)) * 100, 2) as porcentaje_engagement
                  FROM aplicacion a
                  LEFT JOIN comentario c ON (a.apl_id = c.com_apl_id 
                      AND c.com_creado_en BETWEEN ? AND ? AND c.com_situacion = 1)
                  LEFT JOIN " . self::$tabla . " cl ON (c.com_id = cl.col_com_id AND cl.col_situacion = 1)
                  WHERE a.apl_situacion = 1
                  GROUP BY a.apl_id, a.apl_nombre
                  ORDER BY porcentaje_engagement DESC";
        
        return self::fetchArray($query, [$fecha_desde, $fecha_hasta]);
    }
}