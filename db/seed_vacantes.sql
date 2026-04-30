-- ============================================================
-- Sharclean – Seed: Afanadores por ciudad + Jardinero general
-- Ejecutar: mysql -u USUARIO -p sharclean_db < db/seed_vacantes.sql
-- ============================================================

INSERT INTO `vacantes`
  (`titulo`, `area`, `tipo`, `descripcion`, `requisitos`, `ubicacion`, `activa`)
VALUES

-- ── Afanadores ───────────────────────────────────────────────

(
  'Afanador/a',
  'Operaciones',
  'Tiempo completo',
  'Realiza labores de limpieza general en instalaciones de clientes corporativos: barrido, trapeado, limpieza de baños, sanitización de áreas comunes y manejo de residuos.',
  'No se requiere experiencia previa, se capacita. Secundaria mínima. Actitud de servicio, puntualidad y responsabilidad. Prestaciones de ley desde el primer día.',
  'CDMX',
  1
),
(
  'Afanador/a',
  'Operaciones',
  'Tiempo completo',
  'Realiza labores de limpieza general en instalaciones de clientes corporativos: barrido, trapeado, limpieza de baños, sanitización de áreas comunes y manejo de residuos.',
  'No se requiere experiencia previa, se capacita. Secundaria mínima. Actitud de servicio, puntualidad y responsabilidad. Prestaciones de ley desde el primer día.',
  'Estado de México (EDOMEX)',
  1
),
(
  'Afanador/a',
  'Operaciones',
  'Tiempo completo',
  'Realiza labores de limpieza general en instalaciones de clientes corporativos: barrido, trapeado, limpieza de baños, sanitización de áreas comunes y manejo de residuos.',
  'No se requiere experiencia previa, se capacita. Secundaria mínima. Actitud de servicio, puntualidad y responsabilidad. Prestaciones de ley desde el primer día.',
  'San Luis Potosí',
  1
),
(
  'Afanador/a',
  'Operaciones',
  'Tiempo completo',
  'Realiza labores de limpieza general en instalaciones de clientes corporativos: barrido, trapeado, limpieza de baños, sanitización de áreas comunes y manejo de residuos.',
  'No se requiere experiencia previa, se capacita. Secundaria mínima. Actitud de servicio, puntualidad y responsabilidad. Prestaciones de ley desde el primer día.',
  'Querétaro',
  1
),
(
  'Afanador/a',
  'Operaciones',
  'Tiempo completo',
  'Realiza labores de limpieza general en instalaciones de clientes corporativos: barrido, trapeado, limpieza de baños, sanitización de áreas comunes y manejo de residuos.',
  'No se requiere experiencia previa, se capacita. Secundaria mínima. Actitud de servicio, puntualidad y responsabilidad. Prestaciones de ley desde el primer día.',
  'Puebla',
  1
),
(
  'Afanador/a',
  'Operaciones',
  'Tiempo completo',
  'Realiza labores de limpieza general en instalaciones de clientes corporativos: barrido, trapeado, limpieza de baños, sanitización de áreas comunes y manejo de residuos.',
  'No se requiere experiencia previa, se capacita. Secundaria mínima. Actitud de servicio, puntualidad y responsabilidad. Prestaciones de ley desde el primer día.',
  'Morelos',
  1
),
(
  'Afanador/a',
  'Operaciones',
  'Tiempo completo',
  'Realiza labores de limpieza general en instalaciones de clientes corporativos: barrido, trapeado, limpieza de baños, sanitización de áreas comunes y manejo de residuos.',
  'No se requiere experiencia previa, se capacita. Secundaria mínima. Actitud de servicio, puntualidad y responsabilidad. Prestaciones de ley desde el primer día.',
  'Toluca',
  1
),
(
  'Afanador/a',
  'Operaciones',
  'Tiempo completo',
  'Realiza labores de limpieza general en instalaciones de clientes corporativos: barrido, trapeado, limpieza de baños, sanitización de áreas comunes y manejo de residuos.',
  'No se requiere experiencia previa, se capacita. Secundaria mínima. Actitud de servicio, puntualidad y responsabilidad. Prestaciones de ley desde el primer día.',
  'Veracruz',
  1
),
(
  'Afanador/a',
  'Operaciones',
  'Tiempo completo',
  'Realiza labores de limpieza general en instalaciones de clientes corporativos: barrido, trapeado, limpieza de baños, sanitización de áreas comunes y manejo de residuos.',
  'No se requiere experiencia previa, se capacita. Secundaria mínima. Actitud de servicio, puntualidad y responsabilidad. Prestaciones de ley desde el primer día.',
  'Pachuca',
  1
),

-- ── Jardinero general ─────────────────────────────────────────

(
  'Jardinero/a',
  'Mantenimiento',
  'Tiempo completo',
  'Mantenimiento de jardines, áreas verdes y espacios exteriores en instalaciones de clientes corporativos: poda, riego, trasplante, limpieza de áreas verdes y control de plagas básico.',
  'Experiencia mínima de 6 meses en jardinería o áreas verdes. Secundaria. Conocimiento básico de plantas ornamentales y herramienta de jardinería. Disponibilidad para viajar dentro de la zona asignada.',
  'CDMX, EDOMEX, San Luis Potosí, Querétaro, Puebla, Morelos, Toluca, Veracruz y Pachuca',
  1
);