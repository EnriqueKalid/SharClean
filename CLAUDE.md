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
| `admin/vacaciones.php` | Aprobación de vacaciones/permisos + auto-asistencia |
| `admin/finiquitos.php` | Finiquitos y liquidaciones LFT con documento imprimible |
| `admin/reportes.php` | Reportes RRHH: Empleados, Asistencia, Nómina, Incidencias + export CSV |
| `admin/configuracion.php` | Configuración del sistema: Empresa, Correo, Sistema |
| `solicitar.php` | Formulario público para solicitar vacaciones/permisos |
| `apply.php` | Handler público de postulaciones web |
| `db/app_config.php` | Helper `appConfig($key)` / `setAppConfig($key, $val)` — carga tabla config con caché estático |

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
- `nomina_periodos` — períodos de nómina (semanal/quincenal)
- `nomina_lineas` — líneas de nómina por empleado
- `finiquitos` — finiquitos y liquidaciones LFT

## Migraciones ejecutadas en servidor

1. `db/migrate_rrhh_v2.sql` ✅
2. `db/migrate_asistencia_v1.sql` ✅
3. `db/migrate_vacaciones_v1.sql` ✅
4. `db/migrate_nomina_v1.sql` ✅
5. `db/migrate_finiquitos_v1.sql` ✅
6. `db/migrate_config_v1.sql` ✅
7. `db/migrate_chatbot_v1.sql` ✅
8. `db/migrate_usuarios_v1.sql` ✅
9. `db/migrate_reset_tokens_v1.sql` ✅

## Estado de módulos RRHH

| Módulo | Archivo | Estado |
|---|---|---|
| Reclutamiento (vacantes + candidatos) | `admin/index.php` tabs Vacantes y Aplicaciones | ✅ Completo |
| Expediente empleados | `admin/empleados.php` | ✅ Completo |
| Asistencia | `admin/asistencia.php` | ✅ Completo |
| Nómina | `admin/nomina.php` | ✅ Completo |
| Vacaciones y Permisos | `admin/vacaciones.php` + `solicitar.php` | ✅ Completo |
| Finiquitos y Liquidaciones (LFT) | `admin/finiquitos.php` | ✅ Completo |
| Reportes | `admin/reportes.php` | ✅ Completo |
| Configuración | `admin/configuracion.php` | ✅ Completo |
| Usuarios y Perfiles | `admin/usuarios.php` | ✅ Completo (requiere ejecutar migrate_usuarios_v1.sql) |

## Siguiente módulo a desarrollar: Configuración

Módulo de configuración del sistema para permitir la comercialización de la aplicación.
Incluirá: datos de la empresa (nombre, logo, RFC, colores), credenciales de correo SMTP,
y parámetros generales del sistema.

## Notas del módulo Nómina

- Tablas: `nomina_periodos`, `nomina_lineas`
- Tipos: semanal y quincenal
- Cálculos: IMSS obrero (2025), ISR Art.96 LISR + subsidio al empleo (proyección mensual)
- Horas extra al 200% (dobles)
- Exporta CSV con BOM UTF-8 para Excel
- Constantes fiscales (UMA, tasas IMSS, tablas ISR) están en `admin/nomina.php` — actualizar cada año

## Notas del módulo Finiquitos

- Tabla: `finiquitos`
- Cálcula finiquito (renuncia, fin contrato, despido justificado, acuerdo mutuo, defunción) y liquidación (despido injustificado)
- Componentes: días pendientes, aguinaldo prop., vacaciones prop., prima vacacional, prima antigüedad (Art. 162)
- Liquidación agrega: 3 meses SDI + 20 días/año × SDI
- SDI = salario_diario × factor_integracion; salario_diario = salario_base / 30
- Constante SM_DIARIO_2025 = 278.80 — actualizar cada año para prima antigüedad
- Vista detalle genera documento imprimible con sección de firmas
- Estados: borrador → firmado → pagado
- Checkbox opcional para marcar empleado como baja_definitiva al guardar
