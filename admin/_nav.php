<?php
/**
 * _nav.php – Menú lateral + barra superior compartidos del panel admin (Admin 2.0).
 * Variables esperadas (definidas antes del include):
 *   $navTitle    (string) — título del módulo, ej: '👥 Empleados'
 *   $activeModule (string) — slug del módulo activo, ej: 'empleados'
 * Funciones requeridas (de auth.php): hasPermiso(), adminUser(), generateCsrfToken()
 * Funciones requeridas (de app_config.php): appConfig()
 * Estilos: assets/admin.css (enlazado en el <head> de cada página)
 */
if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
$_u     = adminUser();
$_mod   = $activeModule ?? '';
$_logo  = '../' . appConfig('sistema_logo', 'images/logo.png');
$_brand = appConfig('sistema_color', '#0284C7');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $_brand)) $_brand = '#0284C7';   // va dentro de <style>: solo hex válido
$_title = trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', (string)($navTitle ?? 'Admin')));

preg_match_all('/(?<!\p{L})\p{L}/u', $_u['nombre'], $_m);
$_initials = implode('', array_slice($_m[0], 0, 2));
$_initials = function_exists('mb_strtoupper') ? mb_strtoupper($_initials) : strtoupper($_initials);

$_ico = [
    'dashboard'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
    'panel'         => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    'empleados'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'asistencia'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/>',
    'vacaciones'    => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
    'nomina'        => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
    'finiquitos'    => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
    'reportes'      => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>',
    'configuracion' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    'usuarios'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    'logout'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
    'menu'          => '<path d="M3 6h18M3 12h18M3 18h18"/>',
    'collapse'      => '<path d="m11 17-5-5 5-5M18 17l-5-5 5-5"/>',
];
$_svg = fn(string $k): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $_ico[$k] . '</svg>';

// [slug, archivo, etiqueta, permiso requerido (null = todos)]
$_groups = [
    'General' => [
        ['dashboard', 'dashboard.php', 'Inicio', null],
        ['panel',     'index.php',     'Panel',  'panel'],
    ],
    'Recursos humanos' => [
        ['empleados',  'empleados.php',  'Empleados',  'empleados'],
        ['asistencia', 'asistencia.php', 'Asistencia', 'asistencia'],
        ['vacaciones', 'vacaciones.php', 'Vacaciones', 'vacaciones'],
        ['nomina',     'nomina.php',     'Nómina',     'nomina'],
        ['finiquitos', 'finiquitos.php', 'Finiquitos', 'finiquitos'],
        ['reportes',   'reportes.php',   'Reportes',   'reportes'],
    ],
    'Sistema' => [
        ['configuracion', 'configuracion.php', 'Configuración', 'configuracion'],
        ['usuarios',      'usuarios.php',      'Usuarios',      'usuarios'],
    ],
];
?>
<style>:root{--brand:<?= $_brand ?>}</style>
<script>try{if(localStorage.getItem('sb-collapsed')==='1')document.documentElement.classList.add('sb-collapsed')}catch(e){}</script>

<aside class="sb" id="sb" aria-label="Menú principal">
  <a class="sb-user" href="mi_perfil.php" title="Mi perfil">
    <?php if ($_u['foto'] !== '' && $_u['id'] > 0): ?>
      <img class="sb-avatar" src="usuario_foto.php?id=<?= $_u['id'] ?>&amp;v=<?= substr(md5($_u['foto']), 0, 8) ?>" alt="">
    <?php else: ?>
      <span class="sb-avatar sb-avatar-initials"><?= h($_initials ?: 'A') ?></span>
    <?php endif ?>
    <span class="sb-user-text">
      <strong><?= h($_u['nombre']) ?></strong>
      <small><?= h($_u['perfil'] !== '' ? $_u['perfil'] : 'Administrador') ?></small>
    </span>
  </a>

  <nav class="sb-nav">
    <?php foreach ($_groups as $_gLabel => $_items):
      $_visible = array_filter($_items, fn($i) => $i[3] === null || hasPermiso($i[3]));
      if (!$_visible) continue; ?>
      <div class="sb-group"><?= h($_gLabel) ?></div>
      <?php foreach ($_visible as [$_slug, $_href, $_label]): ?>
        <a href="<?= $_href ?>" class="sb-link<?= $_mod === $_slug ? ' active' : '' ?>" title="<?= h($_label) ?>"
           <?= $_mod === $_slug ? 'aria-current="page"' : '' ?>>
          <?= $_svg($_slug) ?><span><?= h($_label) ?></span>
        </a>
      <?php endforeach ?>
    <?php endforeach ?>
  </nav>

  <div class="sb-foot">
    <form method="post" action="logout.php">
      <input type="hidden" name="csrf_token" value="<?= h(generateCsrfToken()) ?>">
      <button type="submit" class="sb-link sb-logout" title="Cerrar sesión"><?= $_svg('logout') ?><span>Cerrar sesión</span></button>
    </form>
    <div class="sb-brand">
      <img src="<?= h($_logo) ?>" alt="" onerror="this.remove()">
      <span><?= h(appConfig('sistema_nombre', 'Admin')) ?></span>
      <button type="button" class="sb-collapse" id="sb-collapse" title="Contraer menú" aria-label="Contraer menú"><?= $_svg('collapse') ?></button>
    </div>
  </div>
</aside>
<div class="sb-backdrop" id="sb-backdrop"></div>

<header class="topbar">
  <button type="button" class="topbar-menu" id="sb-open" aria-label="Abrir menú" aria-controls="sb"><?= $_svg('menu') ?></button>
  <h1><?= h($_title) ?></h1>
</header>

<script>
(function () {
  const root = document.documentElement;
  document.getElementById('sb-collapse').addEventListener('click', function () {
    const on = root.classList.toggle('sb-collapsed');
    try { localStorage.setItem('sb-collapsed', on ? '1' : '0'); } catch (e) {}
  });
  const open  = () => root.classList.add('sb-open');
  const close = () => root.classList.remove('sb-open');
  document.getElementById('sb-open').addEventListener('click', open);
  document.getElementById('sb-backdrop').addEventListener('click', close);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

  // Tablas anchas: envolver en un contenedor con scroll horizontal, salvo las que ya tienen uno
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.body table').forEach(function (t) {
      for (let el = t.parentElement; el && !el.classList.contains('body'); el = el.parentElement) {
        const ox = getComputedStyle(el).overflowX;
        if (ox === 'auto' || ox === 'scroll') return;
      }
      const wrap = document.createElement('div');
      wrap.className = 'tbl-scroll';
      t.parentNode.insertBefore(wrap, t);
      wrap.appendChild(t);
    });
  });
})();
</script>
