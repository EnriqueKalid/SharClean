-- Migración: agrega clave empresa_email_reclutamiento a la tabla config
-- Ejecutar UNA sola vez contra sharclea_dbapp

INSERT IGNORE INTO `config` (`clave`, `valor`, `grupo`, `etiqueta`, `tipo`, `orden`) VALUES
('empresa_email_reclutamiento', 'reclutamiento@sharclean.com.mx', 'empresa', 'Email de reclutamiento (candidatos chatbot)', 'email', 8);
