-- ============================================================
-- Sharclean – Admin 2.0 (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- Requiere migrate_usuarios_v1.sql
--   · Foto de perfil del usuario admin (se muestra en el menú lateral)
--   · Registro de intentos de login fallidos (bloqueo anti fuerza bruta)
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `sharclea_dbapp`;

ALTER TABLE `admin_usuarios`
  ADD COLUMN IF NOT EXISTS `foto` VARCHAR(255) NULL
    COMMENT 'Archivo en uploads/usuarios/ — servido solo por admin/usuario_foto.php' AFTER `email`;

CREATE TABLE IF NOT EXISTS `admin_login_intentos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`         VARCHAR(45)  NOT NULL,
  `username`   VARCHAR(60)  NOT NULL DEFAULT '',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ip_fecha`   (`ip`, `created_at`),
  INDEX `idx_user_fecha` (`username`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Intentos fallidos de login del panel admin';
