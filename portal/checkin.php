<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db/csrf.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$emp   = portalEmpleado();
$db    = getPortalDB();
$token = generateCsrfToken();

$today = date('Y-m-d');
$hoy   = $db->query(
    "SELECT hora_entrada, hora_salida, horas_trabajadas, horas_extra, retardo
     FROM asistencia WHERE empleado_id=" . (int)$emp['id'] . " AND fecha='$today' LIMIT 1"
)->fetch_assoc();
$db->close();

$estado = 'sin_entrada';
if ($hoy && $hoy['hora_entrada'] && $hoy['hora_salida']) {
    $estado = 'completo';
} elseif ($hoy && $hoy['hora_entrada']) {
    $estado = 'con_entrada';
}

$tieneFotoRef = !empty($emp['foto_referencia']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Check-in – Sharclean</title>
<link rel="icon" href="../images/favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:#f0f9ff;min-height:100vh;color:#1e293b}
.header{background:#0284C7;color:#fff;padding:16px 24px;display:flex;align-items:center;gap:16px;justify-content:space-between}
.header-left{display:flex;align-items:center;gap:16px}
.header img{height:36px}
.header h1{font-size:18px;font-weight:800}
.header p{font-size:13px;opacity:.8}
.header a{color:#fff;font-size:13px;opacity:.85;text-decoration:none;border:1px solid rgba(255,255,255,.4);padding:6px 12px;border-radius:7px}
.header a:hover{opacity:1;background:rgba(255,255,255,.12)}
.container{max-width:460px;margin:28px auto;padding:0 16px 60px}
.card{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:26px;margin-bottom:18px}
.card-title{font-size:19px;font-weight:800;color:#0284C7}
.card-sub{font-size:13px;color:#64748b;margin-top:2px;margin-bottom:6px}
.status-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px solid #f1f5f9;font-size:14px}
.status-row:first-of-type{border-top:none;margin-top:14px}
.status-lbl{color:#64748b}
.status-val{font-weight:700}
.badge-ok{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700}
.badge-pend{background:#fef9c3;color:#854d0e;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 20px;border-radius:12px;border:none;cursor:pointer;font-size:16px;font-weight:700;font-family:inherit;transition:.15s;width:100%}
.btn-primary{background:#0284C7;color:#fff}.btn-primary:hover{background:#0369a1}
.btn-primary:disabled{background:#93c5fd;cursor:not-allowed}
.btn-outline{background:#fff;color:#374151;border:1.5px solid #d1d5db;margin-top:10px}.btn-outline:hover{background:#f9fafb}
.error{background:#fee2e2;color:#991b1b;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600}
.warn{background:#fef9c3;color:#854d0e;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600}
.ok{background:#dcfce7;color:#166534;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600}
.tip{font-size:12px;color:#94a3b8;margin-top:12px;text-align:center;line-height:1.6}
video,#preview{width:100%;border-radius:12px;background:#0f172a;margin-bottom:14px;max-height:360px;object-fit:cover}
.success-icon{font-size:56px;text-align:center;margin-bottom:10px}
.success-title{font-size:19px;font-weight:800;text-align:center;color:#16a34a;margin-bottom:6px}
.success-sub{font-size:14px;color:#64748b;text-align:center;line-height:1.6}
</style>
</head>
<body>

<div class="header">
  <div class="header-left">
    <img src="../images/logo.png" alt="Sharclean">
    <div>
      <h1>Check-in</h1>
      <p><?= h($emp['nombre'] . ' ' . $emp['apellido_paterno']) ?> · <?= h($emp['numero_empleado']) ?></p>
    </div>
  </div>
  <a href="logout.php">Salir</a>
</div>

<div class="container">

  <div class="card">
    <div class="card-title">Hoy, <?= h(ucfirst(date('l d \d\e F', strtotime($today)))) ?></div>
    <div class="card-sub">Estatus de tu jornada</div>
    <div class="status-row">
      <span class="status-lbl">Entrada</span>
      <span class="status-val"><?= $hoy && $hoy['hora_entrada'] ? h(substr($hoy['hora_entrada'], 0, 5)) : '—' ?></span>
    </div>
    <div class="status-row">
      <span class="status-lbl">Salida</span>
      <span class="status-val"><?= $hoy && $hoy['hora_salida'] ? h(substr($hoy['hora_salida'], 0, 5)) : '—' ?></span>
    </div>
    <?php if ($hoy && $hoy['horas_trabajadas']): ?>
    <div class="status-row">
      <span class="status-lbl">Horas trabajadas</span>
      <span class="status-val"><?= number_format((float)$hoy['horas_trabajadas'], 1) ?>h</span>
    </div>
    <?php endif; ?>
    <?php if ($hoy && $hoy['retardo']): ?>
    <div class="status-row">
      <span class="status-lbl">Puntualidad</span>
      <span class="badge-pend">⏰ Retardo</span>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!$tieneFotoRef): ?>
    <div class="warn">Tu expediente no tiene foto de referencia. Pide a tu supervisor que la cargue para poder verificar tu identidad automáticamente — mientras tanto podrás marcar tu check-in y quedará marcado para revisión manual.</div>
  <?php endif; ?>

  <?php if ($estado === 'completo'): ?>
    <div class="card">
      <div class="success-icon">✅</div>
      <div class="success-title">Jornada completa</div>
      <div class="success-sub">Ya registraste tu entrada y salida de hoy. ¡Buen trabajo!</div>
    </div>
  <?php else: ?>

  <div class="card" id="actionCard">
    <div id="step-idle">
      <button id="btnStart" class="btn btn-primary">
        <?= $estado === 'con_entrada' ? '🚪 Marcar salida' : '📍 Marcar entrada' ?>
      </button>
      <div id="idleStatus" class="tip"></div>
    </div>

    <div id="step-camera" hidden>
      <video id="video" autoplay playsinline muted></video>
      <button id="btnCapture" class="btn btn-primary">📸 Capturar selfie</button>
    </div>

    <div id="step-review" hidden>
      <img id="preview" alt="Selfie capturada">
      <div id="faceStatus"></div>
      <button id="btnSubmit" class="btn btn-primary">✅ Confirmar</button>
      <button id="btnRetry" class="btn btn-outline">🔁 Repetir foto</button>
    </div>

    <div id="step-result" hidden></div>
  </div>

  <p class="tip">Necesitamos tu ubicación y una foto para registrar tu <?= $estado === 'con_entrada' ? 'salida' : 'entrada' ?> de forma segura.</p>
  <?php endif; ?>

</div>

<canvas id="canvas" hidden></canvas>
<img id="refPhoto" src="mi_foto.php" crossorigin="anonymous" hidden>

<script src="assets/face-api/face-api.min.js"></script>
<script>
(function () {
  const ACCION       = <?= json_encode($estado === 'con_entrada' ? 'salida' : 'entrada') ?>;
  const CSRF_TOKEN   = <?= json_encode($token) ?>;
  const TIENE_REF    = <?= json_encode($tieneFotoRef) ?>;
  const MATCH_DIST   = 0.6;
  const MAX_RETRIES  = 3;

  const elIdle    = document.getElementById('step-idle');
  const elCamera  = document.getElementById('step-camera');
  const elReview  = document.getElementById('step-review');
  const elResult  = document.getElementById('step-result');
  const btnStart  = document.getElementById('btnStart');
  const btnCapture= document.getElementById('btnCapture');
  const btnSubmit = document.getElementById('btnSubmit');
  const btnRetry  = document.getElementById('btnRetry');
  const idleStatus= document.getElementById('idleStatus');
  const faceStatus= document.getElementById('faceStatus');
  const video     = document.getElementById('video');
  const canvas    = document.getElementById('canvas');
  const preview   = document.getElementById('preview');
  const refPhoto  = document.getElementById('refPhoto');

  if (!btnStart) return; // jornada ya completa, no hay acciones

  let stream       = null;
  let lat = null, lng = null;
  let lastDataUrl   = null;
  let lastFaceScore = null; // null = sin validar
  let retries       = 0;
  let refDescriptor = null;
  let modelsReady   = false;

  /* ── Cargar face-api.js en segundo plano (no bloquea el flujo) ── */
  if (TIENE_REF && window.faceapi) {
    Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri('assets/face-api/weights'),
      faceapi.nets.faceLandmark68Net.loadFromUri('assets/face-api/weights'),
      faceapi.nets.faceRecognitionNet.loadFromUri('assets/face-api/weights'),
    ]).then(async () => {
      modelsReady = true;
      if (refPhoto.complete && refPhoto.naturalWidth > 0) await computeRefDescriptor();
    }).catch(() => { modelsReady = false; });

    refPhoto.addEventListener('load', async () => {
      if (modelsReady) await computeRefDescriptor();
    });
  }

  async function computeRefDescriptor() {
    try {
      const det = await faceapi.detectSingleFace(refPhoto, new faceapi.TinyFaceDetectorOptions())
        .withFaceLandmarks().withFaceDescriptor();
      if (det) refDescriptor = det.descriptor;
    } catch (e) { refDescriptor = null; }
  }

  /* ── Paso 1: ubicación + cámara ── */
  btnStart.addEventListener('click', async () => {
    btnStart.disabled = true;
    idleStatus.textContent = 'Obteniendo tu ubicación…';

    if (!navigator.geolocation) {
      idleStatus.textContent = 'Tu navegador no soporta geolocalización.';
      btnStart.disabled = false;
      return;
    }

    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        lat = pos.coords.latitude;
        lng = pos.coords.longitude;
        await startCamera();
      },
      () => {
        idleStatus.textContent = 'Necesitamos tu ubicación para marcar el check-in. Activa el permiso de ubicación e intenta de nuevo.';
        btnStart.disabled = false;
      },
      { enableHighAccuracy: true, timeout: 12000 }
    );
  });

  async function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      idleStatus.textContent = 'Tu navegador no soporta acceso a la cámara, o la página no se abrió con HTTPS.';
      btnStart.disabled = false;
      return;
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
      video.srcObject = stream;
      elIdle.hidden = true;
      elCamera.hidden = false;
    } catch (e) {
      idleStatus.textContent = 'No se pudo acceder a la cámara. Revisa los permisos e intenta de nuevo.';
      btnStart.disabled = false;
    }
  }

  function stopCamera() {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  }

  /* ── Paso 2: capturar selfie ── */
  btnCapture.addEventListener('click', async () => {
    const w = video.videoWidth || 480, h = video.videoHeight || 480;
    canvas.width = w; canvas.height = h;
    canvas.getContext('2d').drawImage(video, 0, 0, w, h);
    lastDataUrl = canvas.toDataURL('image/jpeg', 0.82);
    preview.src = lastDataUrl;

    stopCamera();
    elCamera.hidden = true;
    elReview.hidden = false;
    btnSubmit.disabled = true;
    faceStatus.innerHTML = '';

    if (TIENE_REF && modelsReady && refDescriptor) {
      faceStatus.innerHTML = '<p class="tip">Verificando que eres tú…</p>';
      try {
        const det = await faceapi.detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions())
          .withFaceLandmarks().withFaceDescriptor();
        if (!det) {
          lastFaceScore = null;
          faceStatus.innerHTML = '<div class="warn">No detectamos tu rostro con claridad. Puedes repetir la foto o continuar (quedará marcada para revisión).</div>';
        } else {
          const dist = faceapi.euclideanDistance(refDescriptor, det.descriptor);
          lastFaceScore = Math.round(Math.max(0, 1 - dist) * 100);
          if (dist <= MATCH_DIST) {
            faceStatus.innerHTML = '<div class="ok">✅ Rostro verificado.</div>';
          } else if (retries < MAX_RETRIES) {
            retries++;
            faceStatus.innerHTML = `<div class="warn">No pudimos confirmar que eres tú (intento ${retries}/${MAX_RETRIES}). Repite la foto con buena luz, de frente.</div>`;
          } else {
            faceStatus.innerHTML = '<div class="warn">No pudimos confirmar tu identidad tras varios intentos. Puedes continuar: tu check-in quedará marcado para revisión del administrador.</div>';
          }
        }
      } catch (e) {
        lastFaceScore = null;
        faceStatus.innerHTML = '<div class="tip">No se pudo verificar el rostro en este dispositivo. Tu check-in quedará marcado para revisión.</div>';
      }
    } else {
      lastFaceScore = null;
    }
    btnSubmit.disabled = false;
  });

  /* ── Repetir foto ── */
  btnRetry.addEventListener('click', async () => {
    elReview.hidden = true;
    elIdle.hidden = false;
    btnStart.disabled = false;
    idleStatus.textContent = '';
    await startCamera();
    elIdle.hidden = true;
  });

  /* ── Confirmar: enviar al servidor ── */
  btnSubmit.addEventListener('click', async () => {
    btnSubmit.disabled = true;
    btnRetry.disabled  = true;
    faceStatus.innerHTML += '<p class="tip">Enviando…</p>';

    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('lat', String(lat));
    fd.append('lng', String(lng));
    fd.append('foto', lastDataUrl);
    fd.append('face_score', lastFaceScore === null ? '' : String(lastFaceScore));

    try {
      const res  = await fetch('checkin_submit.php', { method: 'POST', body: fd });
      const data = await res.json();
      elReview.hidden = true;
      elResult.hidden = false;
      if (data.type === 'success') {
        elResult.innerHTML = `<div class="success-icon">✅</div><div class="success-title">${data.msg}</div>`;
        setTimeout(() => location.reload(), 1800);
      } else {
        elResult.innerHTML = `<div class="error">${data.msg || 'Ocurrió un error, intenta de nuevo.'}</div>
          <button class="btn btn-outline" onclick="location.reload()">Reintentar</button>`;
      }
    } catch (e) {
      elReview.hidden = true;
      elResult.hidden = false;
      elResult.innerHTML = '<div class="error">No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.</div>' +
        '<button class="btn btn-outline" onclick="location.reload()">Reintentar</button>';
    }
  });
})();
</script>
</body>
</html>
