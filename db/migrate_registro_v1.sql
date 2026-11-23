-- ============================================================
-- Sharclean RRHH – Autorregistro de empleados (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- Requiere migrate_checkin_v1.sql y migrate_config_v1.sql
--
--   · El empleado ya dado de alta por RRHH activa su cuenta desde la
--     liga general (portal/registro.php): se identifica con su número de
--     empleado + teléfono o correo de su ficha, captura sus datos, su selfie
--     de referencia y su contraseña. Queda 'pendiente' hasta que RRHH
--     aprueba la selfie desde Empleados.
--   · intentos_acceso: límite de intentos por IP / por cuenta en el portal
--     (antes vivía en la sesión y se evadía borrando la cookie).
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `sharclea_dbapp`;

ALTER TABLE `empleados`
  ADD COLUMN IF NOT EXISTS `registro_estado` VARCHAR(12) NULL
    COMMENT 'NULL = gestionado por RRHH; pendiente | aprobado | rechazado (autorregistro)' AFTER `portal_activo`,
  ADD COLUMN IF NOT EXISTS `registro_fecha` DATETIME NULL AFTER `registro_estado`,
  ADD COLUMN IF NOT EXISTS `registro_revisado_por` INT UNSIGNED NULL
    COMMENT 'admin_usuarios.id que aprobó/rechazó' AFTER `registro_fecha`;

CREATE TABLE IF NOT EXISTS `intentos_acceso` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave`      VARCHAR(80)  NOT NULL COMMENT 'ej. ip:1.2.3.4:portal-login, cuenta:EMP-0001, ip:…:registro',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_clave_fecha` (`clave`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Intentos fallidos en el portal del empleado (anti fuerza bruta)';

-- Liga general de registro: apagada hasta que RRHH la active desde Empleados
INSERT IGNORE INTO `config` (`clave`, `valor`, `grupo`, `etiqueta`, `tipo`, `orden`) VALUES
('registro_activo', '0', 'interno', 'Liga de autorregistro de empleados activa', 'text', 0),
('registro_clave',  '',  'interno', 'Clave de la liga de autorregistro (rotable)', 'text', 0);
