-- ============================================================
-- Sharclean – Reset catálogo RRHH (departamentos + puestos + empleados)
-- Borra y recrea las tres tablas desde cero con el catálogo correcto.
-- NO toca: usuarios, asistencia, incidencias, nómina, finiquitos,
--          vacaciones, config, chatbot ni ninguna otra tabla.
-- ============================================================

USE `sharclea_dbapp`;

-- Quitar el FK de finiquitos explícitamente (más confiable que FOREIGN_KEY_CHECKS en phpMyAdmin)
ALTER TABLE `finiquitos` DROP FOREIGN KEY IF EXISTS `fk_fin_emp`;

DROP TABLE IF EXISTS `empleados`;
DROP TABLE IF EXISTS `puestos`;
DROP TABLE IF EXISTS `departamentos`;

-- ── departamentos ─────────────────────────────────────────────
CREATE TABLE `departamentos` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `activo` TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departamentos` (`id`, `nombre`) VALUES
  (1, 'Operaciones'),
  (2, 'Limpieza'),
  (3, 'Mantenimiento'),
  (4, 'Administración'),
  (5, 'Ventas');


-- ── puestos ───────────────────────────────────────────────────
-- IDs del sistema anterior conservados tal cual.
-- departamento_id: 1=Operaciones 2=Limpieza 3=Mantenimiento 4=Administración 5=Ventas
CREATE TABLE `puestos` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(150) NOT NULL,
  `departamento_id` INT UNSIGNED NOT NULL,
  `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  INDEX `idx_dept` (`departamento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `puestos` (`id`, `nombre`, `departamento_id`) VALUES
  (1,  'Administrador',              4),
  (2,  'Director',                   4),
  (3,  'Contador',                   4),
  (4,  'Coordinador',                1),
  (14, 'Supervisor',                 1),
  (15, 'Gerente',                    1),
  (16, 'Jardinero(a)',               3),
  (17, 'Reclutamiento',              4),
  (18, 'Plataforma',                 1),
  (19, 'Auxiliar Contable',          4),
  (20, 'Jefe de Almacén',            1),
  (21, 'Auxiliar de Almacén',        1),
  (22, 'Administración y Finanzas',  4),
  (23, 'Nóminas',                    4),
  (24, 'Operario',                   2),
  (25, 'Técnico en Alturas',         3),
  (26, 'Comodín',                    1),
  (27, 'Logística',                  1);

ALTER TABLE `puestos` AUTO_INCREMENT = 30;


-- ── empleados ─────────────────────────────────────────────────
-- Esquema completo: incluye columnas de rrhh_v2 + asistencia_v1
CREATE TABLE `empleados` (
  `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `numero_empleado`       VARCHAR(20)   NOT NULL  COMMENT 'Ej. EMP-0001',

  -- Datos personales
  `nombre`                VARCHAR(120)  NOT NULL,
  `apellido_paterno`      VARCHAR(80)   NOT NULL,
  `apellido_materno`      VARCHAR(80)   DEFAULT NULL,
  `fecha_nacimiento`      DATE          DEFAULT NULL,
  `sexo`                  ENUM('M','F','NB') DEFAULT NULL,
  `estado_civil`          ENUM('soltero','casado','union_libre','divorciado','viudo') DEFAULT NULL,

  -- Documentos oficiales
  `curp`                  VARCHAR(18)   DEFAULT NULL,
  `rfc`                   VARCHAR(13)   DEFAULT NULL,
  `nss`                   VARCHAR(11)   DEFAULT NULL  COMMENT 'NSS IMSS (11 dígitos)',

  -- Contacto
  `telefono`              VARCHAR(30)   DEFAULT NULL,
  `email`                 VARCHAR(200)  DEFAULT NULL,
  `email_corporativo`     VARCHAR(200)  DEFAULT NULL,

  -- Domicilio
  `direccion`             VARCHAR(300)  DEFAULT NULL,
  `colonia`               VARCHAR(120)  DEFAULT NULL,
  `ciudad`                VARCHAR(100)  DEFAULT 'CDMX',
  `estado_residencia`     VARCHAR(80)   DEFAULT NULL,
  `cp`                    VARCHAR(10)   DEFAULT NULL,

  -- Posición
  `departamento_id`       INT UNSIGNED  DEFAULT NULL,
  `puesto_id`             INT UNSIGNED  DEFAULT NULL,
  `puesto_nombre`         VARCHAR(150)  DEFAULT NULL  COMMENT 'Texto libre si no usa catálogo',

  -- Contrato
  `tipo_contrato`         ENUM('indeterminado','determinado','por_obra','confianza','honorarios')
                          NOT NULL DEFAULT 'indeterminado',
  `fecha_ingreso`         DATE          NOT NULL,
  `fecha_fin_contrato`    DATE          DEFAULT NULL,

  -- Salario
  `salario_base`          DECIMAL(12,2) DEFAULT NULL  COMMENT 'Salario bruto mensual en MXN',
  `periodo_pago`          ENUM('semanal','quincenal','mensual') NOT NULL DEFAULT 'quincenal',

  -- Banco
  `banco`                 VARCHAR(80)   DEFAULT NULL,
  `clabe_bancaria`        VARCHAR(18)   DEFAULT NULL,

  -- Jornada (de migrate_asistencia_v1)
  `jornada_horas`         TINYINT UNSIGNED NOT NULL DEFAULT 8   COMMENT 'Horas de jornada laboral diaria',
  `hora_entrada_esperada` TIME             NOT NULL DEFAULT '08:00:00' COMMENT 'Hora de entrada según turno',
  `tolerancia_min`        TINYINT UNSIGNED NOT NULL DEFAULT 10  COMMENT 'Minutos de tolerancia antes de marcar retardo',

  -- Estatus laboral
  `estatus`               ENUM('activo','vacaciones','incapacidad','baja_temporal','baja_definitiva')
                          NOT NULL DEFAULT 'activo',
  `fecha_baja`            DATE          DEFAULT NULL,
  `motivo_baja`           VARCHAR(300)  DEFAULT NULL,

  -- Vínculo reclutamiento
  `aplicacion_id`         INT UNSIGNED  DEFAULT NULL  COMMENT 'FK a aplicaciones_empleo',

  -- Extra
  `notas`                 TEXT          DEFAULT NULL,
  `fecha_creacion`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualiza`       DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY  `uk_numero`         (`numero_empleado`),
  INDEX       `idx_estatus`       (`estatus`),
  INDEX       `idx_departamento`  (`departamento_id`),
  INDEX       `idx_fecha_ingreso` (`fecha_ingreso`),
  INDEX       `idx_aplicacion`    (`aplicacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Expediente de empleados';


-- ── Restaurar FK de finiquitos → empleados ───────────────────
ALTER TABLE `finiquitos`
  ADD CONSTRAINT `fk_fin_emp`
  FOREIGN KEY (`empleado_id`) REFERENCES `empleados`(`id`) ON DELETE RESTRICT;


-- ── Verificación ──────────────────────────────────────────────
SELECT 'departamentos' AS tabla, COUNT(*) AS registros FROM `departamentos`
UNION ALL
SELECT 'puestos',  COUNT(*) FROM `puestos`
UNION ALL
SELECT 'empleados', COUNT(*) FROM `empleados`;

SELECT p.id, p.nombre AS puesto, d.nombre AS departamento
FROM `puestos` p
JOIN `departamentos` d ON d.id = p.departamento_id
ORDER BY p.id;
