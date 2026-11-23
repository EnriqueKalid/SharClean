<?php
/**
 * _head.php – Metadatos comunes del portal dentro de <head>: app instalable (manifest,
 * íconos, service worker) y sin indexar. Requiere portalMarca() de _boot.php.
 */
$_m = portalMarca();
?>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?= h($_m['color']) ?>">
<link rel="manifest" href="manifest.php">
<link rel="icon" href="icono.php?s=192" type="image/png">
<link rel="apple-touch-icon" href="icono.php?s=180">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Check-in">
<script>if ('serviceWorker' in navigator) addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(() => {}));</script>
