-- Migración: agrega columna form_url a vacantes (link a Google Forms por vacante)
ALTER TABLE vacantes ADD COLUMN form_url VARCHAR(500) NULL DEFAULT NULL AFTER maps_url;
