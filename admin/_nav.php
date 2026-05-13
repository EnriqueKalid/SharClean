<?php
/**
 * _nav.php – Barra de navegación compartida del panel admin.
 * Variables esperadas (definidas antes del include):
 *   $navTitle    (string) — título del módulo, ej: '👥 Empleados'
 *   $activeModule (string) — slug del módulo activo, ej: 'empleados'
 * Funciones requeridas (de auth.php): hasPermiso(), adminUser()
 * Funciones requeridas (de app_config.php): appConfig()
 */
if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
$_u    = adminUser();
$_logo = '../' . appConfig('sistema_logo', 'images/logo.png');
$_mod  = $activeModule ?? '';
?>
<style>
.nav-user{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;font-size:12px;color:rgba(255,255,255,.85);background:rgba(255,255,255,.12);border-radius:6px;border:1px solid rgba(255,255,255,.2)}
.nav-user strong{font-weight:700;color:#fff}
</style>
<header class="admin-header">
  <div class="admin-brand">
    <img src="<?= h($_logo) ?>" alt="" onerror="this.style.display='none'">
    <h1><?= $navTitle ?? 'Admin' ?></h1>
  </div>
  <div class="nav-links">
    <?php if (hasPermiso('panel')): ?>
    <a href="index.php"        <?= $_mod==='panel'        ?'class="active"':'' ?>>Panel</a>
    <?php endif ?>
    <?php if (hasPermiso('empleados')): ?>
    <a href="empleados.php"    <?= $_mod==='empleados'    ?'class="active"':'' ?>>👥 Empleados</a>
    <?php endif ?>
    <?php if (hasPermiso('asistencia')): ?>
    <a href="asistencia.php"   <?= $_mod==='asistencia'   ?'class="active"':'' ?>>📅 Asistencia</a>
    <?php endif ?>
    <?php if (hasPermiso('nomina')): ?>
    <a href="nomina.php"       <?= $_mod==='nomina'       ?'class="active"':'' ?>>📋 Nómina</a>
    <?php endif ?>
    <?php if (hasPermiso('vacaciones')): ?>
    <a href="vacaciones.php"   <?= $_mod==='vacaciones'   ?'class="active"':'' ?>>🏖 Vacaciones</a>
    <?php endif ?>
    <?php if (hasPermiso('finiquitos')): ?>
    <a href="finiquitos.php"   <?= $_mod==='finiquitos'   ?'class="active"':'' ?>>💼 Finiquitos</a>
    <?php endif ?>
    <?php if (hasPermiso('reportes')): ?>
    <a href="reportes.php"     <?= $_mod==='reportes'     ?'class="active"':'' ?>>📊 Reportes</a>
    <?php endif ?>
    <?php if (hasPermiso('configuracion')): ?>
    <a href="configuracion.php" <?= $_mod==='configuracion'?'class="active"':'' ?>>⚙️ Config</a>
    <?php endif ?>
    <?php if (hasPermiso('usuarios')): ?>
    <a href="usuarios.php"     <?= $_mod==='usuarios'     ?'class="active"':'' ?>>👤 Usuarios</a>
    <?php endif ?>
    <?php if ($_u['id'] > 0): ?>
    <span class="nav-user">👤 <strong><?= h($_u['nombre']) ?></strong></span>
    <?php endif ?>
    <a href="logout.php">Cerrar sesión</a>
  </div>
</header>
