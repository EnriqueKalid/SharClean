-- ============================================================
-- Sharclean – Catálogo de Códigos Postales (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- Reemplaza la consulta a copomex.com por una tabla local.
--
-- La tabla se crea vacía: los datos se cargan desde el admin
-- (Configuración → Códigos postales) subiendo el archivo oficial
-- de SEPOMEX. El catálogo NO se incluye en el repositorio porque
-- su licencia prohíbe la redistribución a terceros.
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `sharclea_dbapp`;

CREATE TABLE IF NOT EXISTS `codigos_postales` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cp`                CHAR(5)      NOT NULL,
  `asentamiento`      VARCHAR(150) NOT NULL,
  `tipo_asentamiento` VARCHAR(60)  NOT NULL DEFAULT '',
  `municipio`         VARCHAR(120) NOT NULL DEFAULT '',
  `estado`            VARCHAR(60)  NOT NULL DEFAULT '',
  `ciudad`            VARCHAR(120) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  INDEX `idx_cp` (`cp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo Nacional de Códigos Postales (SEPOMEX)';

-- Fecha de la última importación (grupo 'interno': no se muestra en Configuración)
INSERT IGNORE INTO `config` (`clave`, `valor`, `grupo`, `etiqueta`, `tipo`, `orden`) VALUES
('cp_catalogo_importado', '', 'interno', 'Última importación del catálogo de C.P.', 'text', 0);
