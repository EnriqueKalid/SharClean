-- ============================================================
-- Sharclean – Módulo Configuración (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- ============================================================

USE `sharclea_dbapp`;

CREATE TABLE IF NOT EXISTS `config` (
  `clave`    VARCHAR(100) NOT NULL,
  `valor`    TEXT         DEFAULT NULL,
  `grupo`    VARCHAR(40)  NOT NULL DEFAULT 'general',
  `etiqueta` VARCHAR(120) NOT NULL DEFAULT '',
  `tipo`     ENUM('text','email','tel','color','password','textarea','boolean','url') NOT NULL DEFAULT 'text',
  `orden`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuración global del sistema';

INSERT IGNORE INTO `config` (`clave`, `valor`, `grupo`, `etiqueta`, `tipo`, `orden`) VALUES
-- Empresa
('empresa_nombre',          'Sharclean',                          'empresa', 'Nombre de la empresa',        'text',  1),
('empresa_giro',            'Limpieza Profesional',				  'empresa', 'Giro / descripción corta',   'text',  2),
('empresa_rfc',             '',                                   'empresa', 'RFC',                         'text',  3),
('empresa_telefono',        '+52 55-8945-4415',                   'empresa', 'Teléfono',         'tel',   4),
('empresa_whatsapp',        '+52 55-8945-4415',                   'empresa', 'WhatsApp',         'tel',   5),
('empresa_email_info',      'informacion@sharclean.com.mx',       'empresa', 'Email de información',        'email', 6),
('empresa_email_comercial', 'comercializacion@sharclean.com.mx',  'empresa', 'Email comercial / cotizaciones','email',7),
('empresa_direccion',       '',                                   'empresa', 'Dirección',                   'textarea',8),
('empresa_ciudad',          'Ciudad de México',                   'empresa', 'Ciudad / Estado',             'text',  9),
-- Correo
('correo_from_name',        'Sharclean',                          'correo',  'Nombre remitente (From)',     'text',  1),
('correo_from_address',     'noreply@sharclean.com.mx',           'correo',  'Dirección remitente (From)',  'email', 2),
-- Sistema
('sistema_nombre',          'SISIAdmin',                              'sistema', 'Nombre del sistema',          'text',  1),
('sistema_color',           '#0284C7',                            'sistema', 'Color primario',              'color', 2),
('sistema_logo',            'images/logo.png',                    'sistema', 'Logo (ruta relativa a raíz)', 'text',  3);