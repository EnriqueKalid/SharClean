-- ============================================================
-- Sharclean – Usuarios y Perfiles del panel admin (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- Luego abrir admin/setup.php para crear el primer usuario
-- ============================================================

USE `sharclea_dbapp`;

-- ── Perfiles ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_perfiles` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(80)  NOT NULL,
  `slug`        VARCHAR(40)  NOT NULL,
  `permisos`    TEXT         NOT NULL COMMENT 'JSON array de módulos permitidos. ["*"] = todo',
  `descripcion` VARCHAR(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Usuarios ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_usuarios` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(100)  NOT NULL,
  `email`         VARCHAR(150)  DEFAULT NULL,
  `username`      VARCHAR(60)   NOT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `perfil_id`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `activo`        TINYINT(1)    NOT NULL DEFAULT 1,
  `ultimo_login`  DATETIME      DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  INDEX `idx_perfil` (`perfil_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Perfiles predeterminados ──────────────────────────────────
INSERT IGNORE INTO `admin_perfiles` (`nombre`, `slug`, `permisos`, `descripcion`) VALUES
('Super Administrador', 'super_admin',
  '["*"]',
  'Acceso total al sistema incluyendo usuarios y configuración'),
('Administrador', 'admin',
  '["panel","empleados","asistencia","nomina","vacaciones","finiquitos","reportes","configuracion"]',
  'Acceso a todos los módulos operativos'),
('Recursos Humanos', 'rrhh',
  '["panel","empleados","asistencia","vacaciones","finiquitos","reportes"]',
  'Módulos de RRHH sin nómina'),
('Nóminas', 'nominas',
  '["nomina","reportes"]',
  'Solo módulo de nómina y reportes');
