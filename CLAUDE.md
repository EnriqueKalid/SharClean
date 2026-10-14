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
| `db/asistencia_calc.php` | Helpers compartidos `calcHorasTrabajadas()`/`calcHorasExtra()`/`calcRetardo()` (admin + portal) |
| `portal/login.php` | Login de empleado para el portal de check-in (usuario+password o número de empleado+PIN) |
| `portal/checkin.php` | Pantalla de check-in/check-out: geolocalización + selfie + verificación facial en navegador |
| `portal/checkin_submit.php` | Handler POST que guarda el check-in/out en `asistencia` |
| `portal/mi_foto.php` | Sirve la foto de referencia del empleado logueado (para comparar en el navegador) |
| `admin/asistencia_foto.php` / `admin/empleado_foto.php` | Sirven selfies de check-in y foto de referencia al admin (protegidas) |
| `admin/cp_lookup.php` | Autocompleta Colonia/Ciudad/Estado desde el C.P. (proxy server-side a copomex.com, requiere `COPOMEX_TOKEN` en `.env`) |

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
- `empleados` — expediente completo (datos personales, fiscales, laborales, bancarios);
  incluye credenciales del portal de check-in (`portal_username`, `portal_password_hash`,
  `portal_pin_hash`, `portal_activo`) y `foto_referencia` para verificación facial
- `asistencia` — registro diario con UNIQUE(empleado_id, fecha); incluye columnas de
  check-in/out propio (`checkin_lat/lng`, `checkout_lat/lng`, `checkin_foto`,
  `checkout_foto`, `checkin_face_score`, `checkout_face_score`, `revision_manual`)
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
10. `db/migrate_checkin_v1.sql` ⏳ pendiente de ejecutar en servidor (`sharclea_dbapp`)

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
| Check-in de empleados (portal self-service) | `portal/*` + `admin/empleados.php` (foto/credenciales) + `admin/asistencia.php` (auditoría) | ✅ Código completo — pendiente ejecutar `migrate_checkin_v1.sql` y probar en servidor (HTTPS, cámara/GPS reales) |

## Siguiente módulo a desarrollar: Portal de vacaciones/incapacidades/permisos

Con el login de empleado ya existente (`portal/auth.php`), migrar `solicitar.php` para que
use ese login en vez de solo el número de empleado, y agregar ahí mismo las solicitudes de
vacaciones/incapacidades/permisos directamente desde el portal (hoy siguen siendo un
formulario público aparte). Fuera de ese alcance también queda el geofencing (restringir el
check-in a un radio permitido alrededor de la oficina/sitio de trabajo).

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

## Notas del módulo Check-in de empleados

- Login de empleado independiente del login admin: usuario+password (`portal_username`/
  `portal_password_hash` en `empleados`) como método estándar, PIN+número de empleado
  (`portal_pin_hash`) como alternativa para quien tenga impedimento. Lo administra el admin
  desde `admin/empleados.php`, sección ⑥ "Portal del empleado — check-in".
- Verificación facial 100% en el navegador con `face-api.js` (auto-hospedado en
  `portal/assets/face-api/`, sin llamadas a servicios de terceros): compara la selfie
  contra la `foto_referencia` del empleado. Es una validación "suave" — si no coincide tras
  3 intentos, o si el dispositivo no soporta la comparación, se deja pasar el check-in pero
  queda `revision_manual=1` para que el admin lo revise en `admin/asistencia.php`.
  El servidor nunca hace la comparación — solo recibe el score ya calculado.
- `getUserMedia` (cámara) y `navigator.geolocation` requieren contexto seguro (HTTPS o
  localhost) — probar siempre en el dominio real, no funciona en `http://`.
- Las fórmulas de horas trabajadas/extra/retardo viven en `db/asistencia_calc.php`
  (extraídas de `admin/asistencia.php` para no duplicarlas en `portal/checkin_submit.php`).
- Filas creadas por el propio empleado se guardan con `asistencia.capturado_por='Empleado'`,
  distinguibles de `'Admin'`/`'Bulk-Admin'`/`'Sistema'`.
- Selfies y foto de referencia se sirven solo vía scripts protegidos (nunca acceso directo
  a `/uploads/` — bloqueado por `uploads/.htaccess`), mismo patrón que `admin/cv.php`.
