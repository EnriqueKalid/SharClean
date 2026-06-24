-- ============================================================
-- Sharclean – Homologación de catálogo puestos v2
-- Reemplaza el catálogo genérico por los puestos del sistema anterior
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- ============================================================

USE `sharclea_dbapp`;

-- ── PASO 1: Preservar nombre en empleados antes de borrar el catálogo ──
-- Si el empleado tiene puesto_id pero no puesto_nombre, copiamos el texto
-- del catálogo actual para no perder la referencia.
UPDATE `empleados` e
  JOIN `puestos` p ON p.id = e.puesto_id
SET e.puesto_nombre = p.nombre
WHERE e.puesto_id IS NOT NULL
  AND (e.puesto_nombre IS NULL OR e.puesto_nombre = '');

-- ── PASO 2: Limpiar puesto_id en empleados (IDs van a cambiar) ──
UPDATE `empleados` SET `puesto_id` = NULL;

-- ── PASO 3: Vaciar catálogo y resetear AUTO_INCREMENT ──
TRUNCATE TABLE `puestos`;

-- ── PASO 4: Insertar catálogo del sistema anterior ──
-- Mantenemos los IDs originales del sistema anterior.
-- departamento_id: 1=Operaciones, 2=Limpieza, 3=Mantenimiento, 4=Administración, 5=Ventas
INSERT INTO `puestos` (`id`, `nombre`, `departamento_id`, `activo`) VALUES
  (1,  'Administrador',           4, 1),
  (2,  'Director',                4, 1),
  (3,  'Contador',                4, 1),
  (4,  'Coordinador',             1, 1),
  (14, 'Supervisor',              1, 1),
  (15, 'Gerente',                 1, 1),
  (16, 'Jardinero(a)',            3, 1),
  (17, 'Reclutamiento',           4, 1),
  (18, 'Plataforma',              1, 1),
  (19, 'Auxiliar Contable',       4, 1),
  (20, 'Jefe de Almacén',         1, 1),
  (21, 'Auxiliar de Almacén',     1, 1),
  (22, 'Administración y Finanzas', 4, 1);

-- ── PASO 5: Agregar puestos activos que no estaban en el sistema anterior ──
INSERT INTO `puestos` (`nombre`, `departamento_id`, `activo`) VALUES
  ('Nóminas',             4, 1),  -- Administración
  ('Operario',            2, 1),  -- Limpieza
  ('Técnico en Alturas',  3, 1),  -- Mantenimiento
  ('Comodín',             1, 1),  -- Operaciones
  ('Logística',           1, 1);  -- Operaciones

-- ── PASO 6: Ajustar AUTO_INCREMENT para que los siguientes no colisionen ──
ALTER TABLE `puestos` AUTO_INCREMENT = 30;

-- ── VERIFICACIÓN ──
SELECT id, nombre, d.nombre AS departamento
FROM `puestos` p
JOIN `departamentos` d ON d.id = p.departamento_id
ORDER BY p.id;
