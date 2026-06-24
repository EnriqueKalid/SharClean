<?php
/**
 * SCRIPT TEMPORAL DE RESETEO DE CONTRASEÑA
 * ─────────────────────────────────────────
 * 1. Sube este archivo al servidor.
 * 2. Ábrelo en el navegador UNA VEZ.
 * 3. BÓRRALO INMEDIATAMENTE después de usarlo.
 */

declare(strict_types=1);

// ── Configuración ──────────────────────────────────────────────────
$USERNAME     = 'sharifmtz';          // Usuario a resetear
$NEW_PASSWORD = 'Sharclean2026!';     // Contraseña temporal — cámbiala en el admin después

// Cargar config de BD
require_once __DIR__ . '/db/config.php';

// ── Acción ─────────────────────────────────────────────────────────
$errors = [];
$done   = false;

try {
    $db   = getDB();
    $hash = password_hash($NEW_PASSWORD, PASSWORD_DEFAULT);
    $u    = $db->real_escape_string($USERNAME);
    $h    = $db->real_escape_string($hash);

    $ok = $db->query(
        "UPDATE admin_usuarios SET password_hash='$h', activo=1 WHERE username='$u'"
    );

    if (!$ok || $db->affected_rows < 1) {
        $errors[] = $db->error ?: "Usuario '$USERNAME' no encontrado en admin_usuarios.";
    } else {
        $done = true;
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reset contraseña – Temporal</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 520px; margin: 80px auto; padding: 24px; }
    .ok  { background: #dcfce7; border: 1px solid #86efac; color: #15803d; padding: 20px; border-radius: 10px; }
    .err { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 20px; border-radius: 10px; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 15px; }
    strong { display: block; font-size: 18px; margin-bottom: 10px; }
    .warn { background: #fef9c3; border: 1px solid #fde047; color: #713f12; padding: 14px 18px; border-radius: 8px; margin-top: 16px; font-size: 13px; }
  </style>
</head>
<body>

<?php if ($done): ?>
  <div class="ok">
    <strong>✓ Contraseña restablecida</strong>
    Usuario: <code><?= htmlspecialchars($USERNAME) ?></code><br>
    Nueva contraseña temporal: <code><?= htmlspecialchars($NEW_PASSWORD) ?></code><br><br>
    Accede al admin y cámbiala de inmediato en <b>Usuarios → Editar</b>.
  </div>
  <div class="warn">
    ⚠️ <strong>IMPORTANTE:</strong> Elimina este archivo del servidor ahora mismo.<br>
    Ruta: <code><?= htmlspecialchars(__FILE__) ?></code>
  </div>
<?php else: ?>
  <div class="err">
    <strong>✗ Error al resetear</strong>
    <?php foreach ($errors as $e): ?>
      <p><?= htmlspecialchars($e) ?></p>
    <?php endforeach ?>
  </div>
<?php endif ?>

</body>
</html>
