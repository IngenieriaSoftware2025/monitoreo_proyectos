CREATE DATABASE monitoreo WITH LOG;

-- ==============================
-- ROLES Y USUARIOS
-- ==============================

-- Tabla: rol
CREATE TABLE rol (
    rol_id      SERIAL PRIMARY KEY,
    rol_nombre  VARCHAR(30) NOT NULL,
    rol_situacion SMALLINT DEFAULT 1
);

-- Tabla: usuario
CREATE TABLE usuario (
    usu_id      SERIAL PRIMARY KEY,
    usu_nombre  VARCHAR(120) NOT NULL,
    usu_grado   VARCHAR(50),
    usu_email   VARCHAR(120),
    usu_rol_id  INT NOT NULL,
    usu_activo  BOOLEAN,
    usu_situacion SMALLINT DEFAULT 1,
    FOREIGN KEY (usu_rol_id) REFERENCES rol(rol_id)
);

-- ==============================
-- APLICACIONES / PROYECTOS
-- ==============================

-- Tabla: aplicacion
CREATE TABLE aplicacion (
    apl_id           SERIAL PRIMARY KEY,
    apl_nombre       VARCHAR(120) NOT NULL,
    apl_descripcion  LVARCHAR(500),
    apl_fecha_inicio DATE NOT NULL,
    apl_fecha_fin    DATE,
    apl_porcentaje_objetivo SMALLINT,
    apl_estado       VARCHAR(20),   -- EN_PLANIFICACION, EN_PROGRESO, PAUSADO, CERRADO
    apl_responsable  INT,           -- usuario (desarrollador principal)
    apl_creado_en    DATETIME YEAR TO SECOND,
    apl_situacion    SMALLINT DEFAULT 1,
    FOREIGN KEY (apl_responsable) REFERENCES usuario(usu_id)
);

-- ==============================
-- AVANCE DIARIO (DESARROLLADOR)
-- ==============================

-- Tabla: avance_diario
CREATE TABLE avance_diario (
    ava_id           SERIAL PRIMARY KEY,
    ava_apl_id       INT NOT NULL,
    ava_usu_id       INT NOT NULL,
    ava_fecha        DATE NOT NULL,
    ava_porcentaje   SMALLINT NOT NULL,
    ava_resumen      LVARCHAR(800),
    ava_bloqueadores LVARCHAR(400),
    ava_justificacion LVARCHAR(800), -- si el % baja
    ava_creado_en    DATETIME YEAR TO SECOND,
    ava_situacion    SMALLINT DEFAULT 1,
    FOREIGN KEY (ava_apl_id) REFERENCES aplicacion(apl_id),
    FOREIGN KEY (ava_usu_id) REFERENCES usuario(usu_id),
    UNIQUE (ava_apl_id, ava_usu_id, ava_fecha)
);

-- ==============================
-- INACTIVIDAD / NO AVANCE
-- ==============================

-- Tabla: inactividad_diaria
CREATE TABLE inactividad_diaria (
    ina_id        SERIAL PRIMARY KEY,
    ina_apl_id    INT NOT NULL,
    ina_usu_id    INT NOT NULL,
    ina_fecha     DATE NOT NULL,
    ina_motivo    LVARCHAR(500) NOT NULL,
    ina_tipo      VARCHAR(50), -- LICENCIA, FALLA_TECNICA, BLOQUEADOR_EXTERNO, VISITA, ESPERA_APROBACION
    ina_creado_en DATETIME YEAR TO SECOND,
    ina_situacion SMALLINT DEFAULT 1,
    FOREIGN KEY (ina_apl_id) REFERENCES aplicacion(apl_id),
    FOREIGN KEY (ina_usu_id) REFERENCES usuario(usu_id),
    UNIQUE (ina_apl_id, ina_usu_id, ina_fecha)
);

-- ==============================
-- VISITAS / FEEDBACK
-- ==============================

-- Tabla: visita
CREATE TABLE visita (
    vis_id            SERIAL PRIMARY KEY,
    vis_apl_id        INT NOT NULL,
    vis_fecha         DATETIME YEAR TO MINUTE NOT NULL,
    vis_quien         VARCHAR(150),
    vis_motivo        LVARCHAR(400),
    vis_procedimiento LVARCHAR(400),
    vis_solucion      LVARCHAR(400),
    vis_observacion   LVARCHAR(800),
    vis_conformidad   BOOLEAN,
    vis_creado_por    INT,
    vis_creado_en     DATETIME YEAR TO SECOND,
    vis_situacion     SMALLINT DEFAULT 1,
    FOREIGN KEY (vis_apl_id) REFERENCES aplicacion(apl_id),
    FOREIGN KEY (vis_creado_por) REFERENCES usuario(usu_id)
);

-- ==============================
-- COMENTARIOS + LECTURAS
-- ==============================

-- Tabla: comentario
CREATE TABLE comentario (
    com_id        SERIAL PRIMARY KEY,
    com_apl_id    INT NOT NULL,
    com_autor_id  INT NOT NULL,
    com_texto     LVARCHAR(1200) NOT NULL,
    com_creado_en DATETIME YEAR TO SECOND,
    com_situacion SMALLINT DEFAULT 1,
    FOREIGN KEY (com_apl_id) REFERENCES aplicacion(apl_id),
    FOREIGN KEY (com_autor_id) REFERENCES usuario(usu_id)
);

-- Tabla: comentario_leido
CREATE TABLE comentario_leido (
    col_id       SERIAL PRIMARY KEY,
    col_com_id   INT NOT NULL,
    col_usu_id   INT NOT NULL,
    col_leido_en DATETIME YEAR TO SECOND,
    col_situacion SMALLINT DEFAULT 1,
    FOREIGN KEY (col_com_id) REFERENCES comentario(com_id),
    FOREIGN KEY (col_usu_id) REFERENCES usuario(usu_id),
    UNIQUE (col_com_id, col_usu_id)
);

-- ==============================
-- ROL
-- ==============================
INSERT INTO rol (rol_nombre) VALUES ('GERENTE');
INSERT INTO rol (rol_nombre) VALUES ('SUBGERENTE');
INSERT INTO rol (rol_nombre) VALUES ('DESARROLLADOR');

-- ==============================
-- USUARIO
-- ==============================
INSERT INTO usuario (usu_nombre, usu_grado, usu_email, usu_rol_id, usu_activo)
VALUES ('Carlos Pérez', 'Mayor', 'cperez@ejercito.gob.gt', 1, 't');

INSERT INTO usuario (usu_nombre, usu_grado, usu_email, usu_rol_id, usu_activo)
VALUES ('Luis Gómez', 'Capitán', 'lgomez@ejercito.gob.gt', 2, 't');

INSERT INTO usuario (usu_nombre, usu_grado, usu_email, usu_rol_id, usu_activo)
VALUES ('Ana López', 'Sargento', 'alopez@ejercito.gob.gt', 3, 't');

-- ==============================
-- APLICACION
-- ==============================
INSERT INTO aplicacion (apl_nombre, apl_descripcion, apl_fecha_inicio, apl_fecha_fin,
                        apl_porcentaje_objetivo, apl_estado, apl_responsable, apl_creado_en)
VALUES ('Sistema de Inventario', 'Control de repuestos y equipos', '2025-08-01', NULL,
        100, 'EN_PROGRESO', 3, CURRENT);

INSERT INTO aplicacion (apl_nombre, apl_descripcion, apl_fecha_inicio, apl_fecha_fin,
                        apl_porcentaje_objetivo, apl_estado, apl_responsable, apl_creado_en)
VALUES ('Plataforma de Reportes', 'Generación automática de reportes', '2025-07-15', NULL,
        100, 'EN_PROGRESO', 3, CURRENT);

INSERT INTO aplicacion (apl_nombre, apl_descripcion, apl_fecha_inicio, apl_fecha_fin,
                        apl_porcentaje_objetivo, apl_estado, apl_responsable, apl_creado_en)
VALUES ('Sistema de Seguridad', 'Monitoreo de cámaras y alarmas', '2025-06-20', NULL,
        100, 'PAUSADO', 3, CURRENT);

-- ==============================
-- AVANCE_DIARIO
-- ==============================
INSERT INTO avance_diario (ava_apl_id, ava_usu_id, ava_fecha, ava_porcentaje, ava_resumen, ava_bloqueadores, ava_creado_en)
VALUES (1, 3, TODAY, 20, 'Se configuró la base de datos y tablas iniciales', NULL, CURRENT);

INSERT INTO avance_diario (ava_apl_id, ava_usu_id, ava_fecha, ava_porcentaje, ava_resumen, ava_bloqueadores, ava_creado_en)
VALUES (2, 3, TODAY, 10, 'Diseño de la estructura de reportes', 'Falta acceso a datos históricos', CURRENT);

INSERT INTO avance_diario (ava_apl_id, ava_usu_id, ava_fecha, ava_porcentaje, ava_resumen, ava_bloqueadores, ava_creado_en)
VALUES (1, 3, TODAY - 1 UNITS DAY, 15, 'Creación de vistas SQL para reportes', NULL, CURRENT);

-- ==============================
-- INACTIVIDAD_DIARIA
-- ==============================
INSERT INTO inactividad_diaria (ina_apl_id, ina_usu_id, ina_fecha, ina_motivo, ina_tipo, ina_creado_en)
VALUES (1, 3, TODAY - 2 UNITS DAY, 'Esperando revisión del gerente', 'ESPERA_APROBACION', CURRENT);

INSERT INTO inactividad_diaria (ina_apl_id, ina_usu_id, ina_fecha, ina_motivo, ina_tipo, ina_creado_en)
VALUES (2, 3, TODAY - 3 UNITS DAY, 'Servidor fuera de línea', 'FALLA_TECNICA', CURRENT);

INSERT INTO inactividad_diaria (ina_apl_id, ina_usu_id, ina_fecha, ina_motivo, ina_tipo, ina_creado_en)
VALUES (3, 3, TODAY - 1 UNITS DAY, 'Capacitación obligatoria', 'LICENCIA', CURRENT);

-- ==============================
-- VISITA
-- ==============================
INSERT INTO visita (vis_apl_id, vis_fecha, vis_quien, vis_motivo, vis_procedimiento, vis_solucion, vis_observacion, vis_conformidad, vis_creado_por, vis_creado_en)
VALUES (1, CURRENT, 'Coronel Martínez', 'Revisión de avances', 'Entrevista con desarrollador', 'Ajustar interfaz', 'Observaciones menores', 't', 1, CURRENT);

INSERT INTO visita (vis_apl_id, vis_fecha, vis_quien, vis_motivo, vis_procedimiento, vis_solucion, vis_observacion, vis_conformidad, vis_creado_por, vis_creado_en)
VALUES (2, CURRENT, 'Mayor López', 'Evaluación inicial', 'Prueba de carga', 'Optimizar consultas', 'Problemas de lentitud', 'f', 2, CURRENT);

INSERT INTO visita (vis_apl_id, vis_fecha, vis_quien, vis_motivo, vis_procedimiento, vis_solucion, vis_observacion, vis_conformidad, vis_creado_por, vis_creado_en)
VALUES (3, CURRENT, 'Capitán Ramírez', 'Revisión de seguridad', 'Chequeo de logs', 'Instalar parches', 'Actualización pendiente', 't', 1, CURRENT);

-- ==============================
-- COMENTARIO
-- ==============================
INSERT INTO comentario (com_apl_id, com_autor_id, com_texto, com_creado_en)
VALUES (1, 1, 'Por favor priorizar módulo de inventario', CURRENT);

INSERT INTO comentario (com_apl_id, com_autor_id, com_texto, com_creado_en)
VALUES (1, 3, 'Entendido, trabajando en ello', CURRENT);

INSERT INTO comentario (com_apl_id, com_autor_id, com_texto, com_creado_en)
VALUES (2, 2, 'Revisar error en el generador de reportes', CURRENT);

-- ==============================
-- COMENTARIO_LEIDO
-- ==============================
INSERT INTO comentario_leido (col_com_id, col_usu_id, col_leido_en)
VALUES (1, 3, CURRENT);

INSERT INTO comentario_leido (col_com_id, col_usu_id, col_leido_en)
VALUES (2, 1, CURRENT);

INSERT INTO comentario_leido (col_com_id, col_usu_id, col_leido_en)
VALUES (3, 3, CURRENT);

