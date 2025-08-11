<?php

namespace Model;

use Model\ActiveRecord;

class Comentario extends ActiveRecord
{
    // Nombre de la tabla en la BD
    public static $tabla = 'comentario';
    public static $idTabla = 'com_id';

    // Columnas que se van a mapear a la BD
    public static $columnasDB = [
        'com_apl_id',
        'com_autor_id',
        'com_texto',
        'com_creado_en',
        'com_situacion'
    ];

    // Propiedades
    public $com_id;
    public $com_apl_id;
    public $com_autor_id;
    public $com_texto;
    public $com_creado_en;
    public $com_situacion;

    // Constantes para menciones
    const PATRON_MENCION = '/@([a-zA-Z0-9_.-]+)/';
    const MAX_MENCIONES = 10;

    public function __construct($args = [])
    {
        $this->com_id = $args['com_id'] ?? null;
        $this->com_apl_id = $args['com_apl_id'] ?? null;
        $this->com_autor_id = $args['com_autor_id'] ?? null;
        $this->com_texto = $args['com_texto'] ?? '';
        $this->com_creado_en = $args['com_creado_en'] ?? null;
        $this->com_situacion = $args['com_situacion'] ?? 1;
    }

    // Validaciones
    public function validar()
    {
        if (!$this->com_apl_id) {
            self::$alertas['error'][] = 'La aplicación es obligatoria';
        }

        if (!$this->com_autor_id) {
            self::$alertas['error'][] = 'El autor es obligatorio';
        }

        if (!$this->com_texto || strlen(trim($this->com_texto)) < 3) {
            self::$alertas['error'][] = 'El comentario debe tener al menos 3 caracteres';
        }

        if (strlen($this->com_texto) > 1200) {
            self::$alertas['error'][] = 'El comentario no puede tener más de 1200 caracteres';
        }

        // Validar número máximo de menciones
        $menciones = $this->extraerMenciones();
        if (count($menciones) > self::MAX_MENCIONES) {
            self::$alertas['error'][] = 'No se pueden mencionar más de ' . self::MAX_MENCIONES . ' usuarios por comentario';
        }

        return self::$alertas;
    }

    // Extraer menciones del texto (@usuario)
    public function extraerMenciones()
    {
        preg_match_all(self::PATRON_MENCION, $this->com_texto, $matches);
        return array_unique($matches[1] ?? []);
    }

    // Procesar menciones y convertir a IDs de usuario
    public function procesarMenciones()
    {
        $menciones_texto = $this->extraerMenciones();
        $usuarios_mencionados = [];

        if (empty($menciones_texto)) {
            return $usuarios_mencionados;
        }

        // Buscar usuarios por nombre de usuario o email
        $placeholders = str_repeat('?,', count($menciones_texto) - 1) . '?';
        $query = "SELECT usu_id, usu_nombre, usu_email 
                  FROM usuario 
                  WHERE (usu_nombre IN ($placeholders) OR usu_email IN ($placeholders))
                  AND usu_activo = true AND usu_situacion = 1";
        
        $params = array_merge($menciones_texto, $menciones_texto);
        $resultado = self::fetchArray($query, $params);

        foreach ($resultado as $usuario) {
            $usuarios_mencionados[] = [
                'usu_id' => $usuario['usu_id'],
                'usu_nombre' => $usuario['usu_nombre'],
                'usu_email' => $usuario['usu_email']
            ];
        }

        return $usuarios_mencionados;
    }

    // Obtener comentarios por aplicación
    public static function obtenerPorAplicacion($apl_id, $limite = null)
    {
        $query = "SELECT c.*, u.usu_nombre, u.usu_grado, u.usu_email,
                         (SELECT COUNT(*) FROM comentario_leido cl 
                          WHERE cl.col_com_id = c.com_id AND cl.col_situacion = 1) as total_lecturas
                  FROM " . self::$tabla . " c
                  JOIN usuario u ON c.com_autor_id = u.usu_id
                  WHERE c.com_apl_id = ? AND c.com_situacion = 1 AND u.usu_situacion = 1
                  ORDER BY c.com_creado_en DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$apl_id]);
    }

    // Obtener comentarios por autor
    public static function obtenerPorAutor($autor_id, $limite = null)
    {
        $query = "SELECT c.*, a.apl_nombre, a.apl_estado
                  FROM " . self::$tabla . " c
                  JOIN aplicacion a ON c.com_apl_id = a.apl_id
                  WHERE c.com_autor_id = ? AND c.com_situacion = 1 AND a.apl_situacion = 1
                  ORDER BY c.com_creado_en DESC";
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, [$autor_id]);
    }

    // Obtener comentarios no leídos por usuario
    public static function obtenerNoLeidosPorUsuario($usu_id)
    {
        $query = "SELECT c.*, a.apl_nombre, autor.usu_nombre as autor_nombre
                  FROM " . self::$tabla . " c
                  JOIN aplicacion a ON c.com_apl_id = a.apl_id
                  JOIN usuario autor ON c.com_autor_id = autor.usu_id
                  LEFT JOIN comentario_leido cl ON (c.com_id = cl.col_com_id AND cl.col_usu_id = ? AND cl.col_situacion = 1)
                  WHERE c.com_situacion = 1 AND a.apl_situacion = 1
                  AND cl.col_id IS NULL
                  AND c.com_autor_id != ?  -- Excluir comentarios propios
                  ORDER BY c.com_creado_en DESC";
        
        return self::fetchArray($query, [$usu_id, $usu_id]);
    }

    // Obtener comentarios con menciones para un usuario
    public static function obtenerConMenciones($usu_id, $limite = null)
    {
        // Primero obtener el nombre/email del usuario
        $query_usuario = "SELECT usu_nombre, usu_email FROM usuario WHERE usu_id = ?";
        $usuario = self::fetchArray($query_usuario, [$usu_id]);
        
        if (!$usuario) {
            return [];
        }

        $nombre = $usuario[0]['usu_nombre'];
        $email = $usuario[0]['usu_email'];

        $query = "SELECT c.*, a.apl_nombre, autor.usu_nombre as autor_nombre,
                         cl.col_leido_en as fecha_leido
                  FROM " . self::$tabla . " c
                  JOIN aplicacion a ON c.com_apl_id = a.apl_id
                  JOIN usuario autor ON c.com_autor_id = autor.usu_id
                  LEFT JOIN comentario_leido cl ON (c.com_id = cl.col_com_id AND cl.col_usu_id = ? AND cl.col_situacion = 1)
                  WHERE c.com_situacion = 1 AND a.apl_situacion = 1
                  AND (c.com_texto LIKE ? OR c.com_texto LIKE ?)
                  AND c.com_autor_id != ?  -- Excluir comentarios propios
                  ORDER BY c.com_creado_en DESC";
        
        $params = [$usu_id, "%@$nombre%", "%@$email%", $usu_id];
        
        if ($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        return self::fetchArray($query, $params);
    }

    // Contar comentarios no leídos por aplicación para un usuario
    public static function contarNoLeidosPorAplicacion($usu_id, $apl_id)
    {
        $query = "SELECT COUNT(*) as total
                  FROM " . self::$tabla . " c
                  LEFT JOIN comentario_leido cl ON (c.com_id = cl.col_com_id AND cl.col_usu_id = ? AND cl.col_situacion = 1)
                  WHERE c.com_apl_id = ? AND c.com_situacion = 1
                  AND cl.col_id IS NULL
                  AND c.com_autor_id != ?";
        
        $resultado = self::fetchArray($query, [$usu_id, $apl_id, $usu_id]);
        return $resultado[0]['total'] ?? 0;
    }

    // Obtener estadísticas de comentarios por aplicación
    public static function obtenerEstadisticasAplicacion($apl_id, $dias = 30)
    {
        $fecha_inicio = date('Y-m-d H:i:s', strtotime("-$dias days"));
        
        $query = "SELECT 
                      COUNT(*) as total_comentarios,
                      COUNT(DISTINCT com_autor_id) as autores_unicos,
                      MAX(com_creado_en) as ultimo_comentario,
                      AVG(LENGTH(com_texto)) as longitud_promedio
                  FROM " . self::$tabla . "
                  WHERE com_apl_id = ? AND com_creado_en >= ? AND com_situacion = 1";
        
        $resultado = self::fetchArray($query, [$apl_id, $fecha_inicio]);
        return $resultado[0] ?? [];
    }

    // Obtener conversación completa de una aplicación (estilo timeline)
    public static function obtenerConversacionCompleta($apl_id, $limite = 50)
    {
        $query = "SELECT c.*, u.usu_nombre, u.usu_grado, u.usu_email,
                         (SELECT COUNT(*) FROM comentario_leido cl 
                          WHERE cl.col_com_id = c.com_id AND cl.col_situacion = 1) as total_lecturas,
                         (SELECT GROUP_CONCAT(usu2.usu_nombre)
                          FROM comentario_leido cl2
                          JOIN usuario usu2 ON cl2.col_usu_id = usu2.usu_id
                          WHERE cl2.col_com_id = c.com_id AND cl2.col_situacion = 1) as usuarios_leido
                  FROM " . self::$tabla . " c
                  JOIN usuario u ON c.com_autor_id = u.usu_id
                  WHERE c.com_apl_id = ? AND c.com_situacion = 1 AND u.usu_situacion = 1
                  ORDER BY c.com_creado_en ASC
                  LIMIT ?";
        
        return self::fetchArray($query, [$apl_id, $limite]);
    }

    // Buscar comentarios por texto
    public static function buscarPorTexto($texto_busqueda, $apl_id = null, $limite = 20)
    {
        $query = "SELECT c.*, a.apl_nombre, u.usu_nombre as autor_nombre
                  FROM " . self::$tabla . " c
                  JOIN aplicacion a ON c.com_apl_id = a.apl_id
                  JOIN usuario u ON c.com_autor_id = u.usu_id
                  WHERE c.com_texto LIKE ? AND c.com_situacion = 1 
                  AND a.apl_situacion = 1 AND u.usu_situacion = 1";
        
        $params = ["%$texto_busqueda%"];
        
        if ($apl_id) {
            $query .= " AND c.com_apl_id = ?";
            $params[] = $apl_id;
        }
        
        $query .= " ORDER BY c.com_creado_en DESC LIMIT ?";
        $params[] = $limite;
        
        return self::fetchArray($query, $params);
    }

    // Obtener información completa del comentario
    public function obtenerCompleto()
    {
        $query = "SELECT c.*, a.apl_nombre, a.apl_estado, u.usu_nombre, u.usu_grado, u.usu_email
                  FROM " . self::$tabla . " c
                  JOIN aplicacion a ON c.com_apl_id = a.apl_id
                  JOIN usuario u ON c.com_autor_id = u.usu_id
                  WHERE c.com_id = ?";
        
        $resultado = self::fetchArray($query, [$this->com_id]);
        return $resultado[0] ?? null;
    }

    // Marcar como leído por un usuario
    public function marcarComoLeido($usu_id)
    {
        // Verificar si ya está marcado como leído
        $query_verificar = "SELECT col_id FROM comentario_leido 
                           WHERE col_com_id = ? AND col_usu_id = ? AND col_situacion = 1";
        $existe = self::fetchArray($query_verificar, [$this->com_id, $usu_id]);
        
        if ($existe) {
            return ['resultado' => true, 'mensaje' => 'Ya estaba marcado como leído'];
        }

        // Insertar registro de lectura
        $query = "INSERT INTO comentario_leido (col_com_id, col_usu_id, col_leido_en, col_situacion)
                  VALUES (?, ?, ?, 1)";
        
        $resultado = self::$db->prepare($query);
        $ejecutado = $resultado->execute([$this->com_id, $usu_id, date('Y-m-d H:i:s')]);
        
        if ($ejecutado) {
            return ['resultado' => true, 'mensaje' => 'Marcado como leído'];
        } else {
            return ['resultado' => false, 'mensaje' => 'Error al marcar como leído'];
        }
    }

    // Marcar múltiples comentarios como leídos
    public static function marcarMultiplesComoLeidos($comentarios_ids, $usu_id)
    {
        if (empty($comentarios_ids)) {
            return ['resultado' => true, 'total' => 0];
        }

        $marcados = 0;
        foreach ($comentarios_ids as $com_id) {
            $comentario = new self(['com_id' => $com_id]);
            $resultado = $comentario->marcarComoLeido($usu_id);
            if ($resultado['resultado']) {
                $marcados++;
            }
        }

        return ['resultado' => true, 'total' => $marcados];
    }

    // Obtener usuarios que han leído el comentario
    public function obtenerUsuariosLeido()
    {
        $query = "SELECT cl.col_leido_en, u.usu_nombre, u.usu_grado
                  FROM comentario_leido cl
                  JOIN usuario u ON cl.col_usu_id = u.usu_id
                  WHERE cl.col_com_id = ? AND cl.col_situacion = 1 AND u.usu_situacion = 1
                  ORDER BY cl.col_leido_en DESC";
        
        return self::fetchArray($query, [$this->com_id]);
    }

    // Enviar notificaciones por menciones
    public function enviarNotificacionesMenciones()
    {
        $usuarios_mencionados = $this->procesarMenciones();
        $notificaciones_enviadas = 0;

        foreach ($usuarios_mencionados as $usuario) {
            // Aquí podrías integrar con un sistema de notificaciones
            // Por ahora, simplemente registramos que hay una mención
            $notificaciones_enviadas++;
        }

        return [
            'usuarios_mencionados' => $usuarios_mencionados,
            'notificaciones_enviadas' => $notificaciones_enviadas
        ];
    }

    // Crear comentario con validaciones completas
    public function crearConValidaciones()
    {
        // Validaciones básicas
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que la aplicación existe y está activa
        $query = "SELECT apl_estado FROM aplicacion WHERE apl_id = ? AND apl_situacion = 1";
        $resultado = self::fetchArray($query, [$this->com_apl_id]);
        if (!$resultado) {
            self::$alertas['error'][] = 'La aplicación no existe o no está activa';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Verificar que el autor existe y está activo
        $query = "SELECT usu_activo FROM usuario WHERE usu_id = ? AND usu_situacion = 1";
        $resultado = self::fetchArray($query, [$this->com_autor_id]);
        if (!$resultado || !$resultado[0]['usu_activo']) {
            self::$alertas['error'][] = 'El usuario autor no existe o no está activo';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Establecer fecha de creación
        if (!$this->com_creado_en) {
            $this->com_creado_en = date('Y-m-d H:i:s');
        }

        // Crear el comentario
        $resultado = $this->crear();
        
        if ($resultado['resultado']) {
            // Enviar notificaciones por menciones
            $this->com_id = $resultado['id'];
            $notificaciones = $this->enviarNotificacionesMenciones();
            
            return [
                'resultado' => true, 
                'id' => $resultado['id'],
                'menciones' => $notificaciones
            ];
        } else {
            self::$alertas['error'][] = 'Error al crear el comentario';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Actualizar comentario con validaciones
    public function actualizarConValidaciones()
    {
        // Solo se puede editar dentro de los primeros 5 minutos
        if ($this->com_creado_en) {
            $tiempo_limite = strtotime($this->com_creado_en) + (5 * 60); // 5 minutos
            if (time() > $tiempo_limite) {
                self::$alertas['error'][] = 'No se puede editar el comentario después de 5 minutos';
                return ['resultado' => false, 'alertas' => self::$alertas];
            }
        }

        // Validaciones básicas
        $this->validar();
        
        if (!empty(self::$alertas['error'])) {
            return ['resultado' => false, 'alertas' => self::$alertas];
        }

        // Actualizar el comentario
        $resultado = $this->actualizar();
        
        if ($resultado['resultado']) {
            // Reenviar notificaciones si hay nuevas menciones
            $notificaciones = $this->enviarNotificacionesMenciones();
            
            return [
                'resultado' => true,
                'menciones' => $notificaciones
            ];
        } else {
            self::$alertas['error'][] = 'Error al actualizar el comentario';
            return ['resultado' => false, 'alertas' => self::$alertas];
        }
    }

    // Verificar si se puede eliminar
    public function puedeEliminar()
    {
        // Solo el autor puede eliminar y solo dentro de los primeros 10 minutos
        if ($this->com_creado_en) {
            $tiempo_limite = strtotime($this->com_creado_en) + (10 * 60); // 10 minutos
            return time() <= $tiempo_limite;
        }
        
        return false;
    }

    // Soft delete
    public function eliminarLogico()
    {
        $this->com_situacion = 0;
        return $this->actualizar();
    }

    // Generar resumen de actividad de comentarios
    public static function generarResumenActividad($fecha_desde, $fecha_hasta)
    {
        $query = "SELECT a.apl_nombre,
                         COUNT(c.com_id) as total_comentarios,
                         COUNT(DISTINCT c.com_autor_id) as autores_unicos,
                         MAX(c.com_creado_en) as ultimo_comentario,
                         AVG(LENGTH(c.com_texto)) as longitud_promedio
                  FROM aplicacion a
                  LEFT JOIN " . self::$tabla . " c ON (a.apl_id = c.com_apl_id 
                      AND c.com_creado_en BETWEEN ? AND ? AND c.com_situacion = 1)
                  WHERE a.apl_situacion = 1
                  GROUP BY a.apl_id, a.apl_nombre
                  ORDER BY total_comentarios DESC";
        
        return self::fetchArray($query, [$fecha_desde, $fecha_hasta]);
    }
}