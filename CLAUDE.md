# Sharclean – NewSharclean

Sitio web + panel admin para Sharclean (empresa de limpieza). PHP puro + MySQL, sin framework.
Single-tenant, solo uso interno.

## Stack
- PHP 8.x (sin mysqlnd — usar `fetchAll()` helper, NO `->fetch_all()` nativo)
- MySQL vía mysqli
- Sin Composer, sin framework
- Hosting cPanel en sharclean.com.mx

## Estructura clave

| Archivo | Rol |
|---|---|
| `admin/index.php` | Panel principal: Contactos, Cotizaciones, Aplicaciones, Vacantes |
| `admin/empleados.php` | CRUD completo de empleados |
| `admin/asistencia.php` | Módulo de asistencia (vista día, resumen mensual, incidencias) |
| `admin/login.php` | Login con credenciales desde `.env` |
| `admin/auth.php` | Guard de sesión + `getAdminDB()` |
| `admin/cv.php` | Descarga segura de CVs |
| `db/config.php` | Carga `.env`, define `getDB()` |
| `db/migrate_rrhh_v2.sql` | Migración v2: empleados, departamentos, puestos |
| `db/migrate_asistencia_v1.sql` | Migración v3: tablas asistencia e incidencias + columnas jornada en empleados |
| `apply.php` | Handler público de postulaciones web |

## Nota crítica — mysqli sin mysqlnd

El servidor NO tiene el driver `mysqlnd`, por lo que `mysqli_result::fetch_all()` no existe.
**Siempre** usar el helper local `fetchAll()` definido en cada archivo admin:

```php
function fetchAll($result): array {
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}
// Uso: $rows = fetchAll($db->query('SELECT ...'));
```

Tampoco usar `$stmt->get_result()` en prepared statements — reemplazar con query directa + `real_escape_string()` cuando los valores ya están validados contra un allowlist.

## Base de datos

Nombre en servidor: `sharclea_dbapp` (ojo: diferente al `.env` local que dice `sharclean_db`).

### Tablas existentes
- `contactos` — formulario de contacto del sitio
- `cotizaciones` — solicitudes de cotización
- `vacantes` — ofertas de empleo activas/pausadas
- `aplicaciones_empleo` — candidatos (web + registro manual)
- `aplicacion_notas` — historial de notas por candidato
- `departamentos` — catálogo (Operaciones, Limpieza, Mantenimiento, Administración, Ventas)
- `puestos` — catálogo vinculado a departamentos
- `empleados` — expediente completo (datos personales, fiscales, laborales, bancarios)
- `asistencia` — registro diario con UNIQUE(empleado_id, fecha)
- `incidencias` — faltas, permisos, vacaciones con flujo de aprobación

## Migraciones pendientes de ejecutar en servidor

Correr en orden en phpMyAdmin o consola:
1. `db/migrate_rrhh_v2.sql`
2. `db/migrate_asistencia_v1.sql`

## Estado de módulos RRHH

| Módulo | Archivo | Estado |
|---|---|---|
| Reclutamiento (vacantes + candidatos) | `admin/index.php` tabs Vacantes y Aplicaciones | ✅ Completo |
| Expediente empleados | `admin/empleados.php` | ✅ Completo |
| Asistencia | `admin/asistencia.php` | ✅ Completo (código listo, falta correr migración en servidor) |
| Nómina | — | ⬜ Pendiente |
| Finiquitos y Liquidaciones (LFT) | — | ⬜ Pendiente |
| Vacaciones y Permisos | — | ⬜ Pendiente |
| Reportes | — | ⬜ Pendiente |

## Siguiente módulo a desarrollar: Nómina

Depende de que asistencia esté funcionando (horas extra, retardos, faltas alimentan los cálculos).
Tablas necesarias: `nomina_periodos`, `nomina_empleado`, `nomina_conceptos`.
Considerar: IMSS (cuotas obrero-patronales), ISR (retención mensual), percepciones variables (horas extra al 200%).
