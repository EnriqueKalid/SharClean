# Guía de onboarding — Usuarios y Perfiles del Admin Sharclean

Esta guía explica cómo dar de alta a un nuevo colaborador en el panel admin
(`admin/usuarios.php`), qué perfil asignarle y qué puede hacer cada uno.

## 1. Conceptos básicos

- **Usuario**: la cuenta individual de una persona (nombre, usuario, contraseña, perfil).
- **Perfil**: un rol con permisos predefinidos sobre los módulos del panel
  (Empleados, Asistencia, Nómina, Vacaciones, Finiquitos, Reportes, Configuración, Usuarios).
- Un usuario siempre pertenece a **un solo perfil**. Los permisos del perfil
  determinan qué pestañas/módulos ve esa persona al iniciar sesión.

Solo las personas con perfil **Super Administrador** o cualquier perfil con
acceso al módulo "Usuarios" pueden dar de alta nuevas cuentas
(`requirePermiso('usuarios')` en `admin/usuarios.php:4`).

## 2. Perfiles disponibles y para quién son

| Perfil | Slug | Acceso a | Para quién |
|---|---|---|---|
| **Super Administrador** | `super_admin` | Todo (`*`), incluyendo Usuarios y Configuración | Dueños/dirección. Úsalo solo para 1-2 personas de máxima confianza. |
| **Administrador** | `admin` | Todos los módulos operativos (sin Usuarios) | Gerencia general / coordinación administrativa. |
| **Recursos Humanos** | `rrhh` | Empleados, Asistencia, Vacaciones, Finiquitos, Reportes (sin Nómina) | Personal de RRHH que gestiona el expediente de empleados pero no procesa pagos. |
| **Nóminas** | `nominas` | Nómina, Reportes | Persona encargada exclusivamente de calcular y exportar nómina. |

> ⚠️ Solo un usuario `super_admin` puede asignar el perfil **Super Administrador**
> a otra cuenta — el formulario lo oculta automáticamente para cualquier otro perfil
> (`admin/usuarios.php:230-231`).

Si ninguno de estos 4 perfiles encaja con un nuevo rol del equipo (por ejemplo
"solo lectura de reportes"), no lo fuerces dentro de uno existente — dímelo y
creamos un perfil nuevo agregando una fila a `db/migrate_usuarios_v1.sql` (o un
script de alta) con el arreglo de permisos adecuado.

## 3. Pasos para dar de alta a un nuevo usuario

1. Inicia sesión en el panel con una cuenta `super_admin` (o un perfil con
   acceso a "Usuarios") y entra a **Usuarios** en el menú superior.
2. Click en **+ Nuevo usuario**.
3. Completa el formulario:
   - **Nombre completo** — nombre real de la persona (aparece en el panel y en bitácoras).
   - **Email** — opcional, para identificarla; no se usa para login.
   - **Nombre de usuario** — mínimo 3 caracteres, debe ser único. Sugerencia: usar
     `nombre.apellido` para que sea fácil de recordar y no choque con otros.
   - **Perfil** — elige uno de la tabla anterior según el rol de la persona.
   - **Contraseña** — mínimo 8 caracteres. Genera una temporal segura y compártela
     por un canal privado (no por correo abierto ni chat grupal).
   - **Usuario activo** — déjalo marcado para que pueda iniciar sesión de inmediato.
4. Click en **Guardar usuario**. La cuenta queda lista para usarse en
   `admin/login.php`.

## 4. Primer acceso de la persona nueva

1. Comparte con la persona: URL del panel (`/admin/login.php`), su nombre de
   usuario y la contraseña temporal.
2. Pídele que **cambie su contraseña en su primer ingreso**: que vaya a
   Usuarios → Editar su propia cuenta → capture una nueva contraseña en el
   campo "Nueva contraseña" (dejarlo en blanco mantiene la actual).
3. Recuérdale qué módulos verá según su perfil — así no se confunde si no
   aparece, por ejemplo, "Nómina" en su menú.

## 5. Mantenimiento de cuentas

- **Desactivar vs. eliminar**: si alguien deja de colaborar temporalmente
  (permiso, baja temporal), usa **Desactivar** — conserva el historial y se
  puede reactivar después. Usa **Eliminar** solo si la cuenta fue creada por
  error o la persona ya no volverá (esto es irreversible).
- No puedes desactivarte ni eliminarte a ti mismo (protección integrada en
  `admin/usuarios.php:72,77`), para evitar quedarte fuera del sistema.
- Revisa de vez en cuando la columna **Último acceso** en la lista de usuarios
  para detectar cuentas inactivas que convenga desactivar.
- Cada perfil muestra cuántos usuarios tiene asignados en la pestaña
  **Perfiles** — útil para auditar quién tiene acceso a qué antes de dar de
  alta a alguien más con permisos amplios.

## 6. Buenas prácticas

- Asigna siempre el **perfil con menos permisos** que cubra las funciones
  reales del puesto (principio de mínimo privilegio). Es más fácil ampliar
  permisos después que revocar accesos ya usados.
- No compartas cuentas entre varias personas — dificulta la trazabilidad de
  quién hizo qué (altas de empleados, cambios de nómina, aprobaciones de
  vacaciones, etc.).
- Limita el perfil **Super Administrador** a quienes realmente necesiten
  gestionar usuarios y configuración del sistema.
