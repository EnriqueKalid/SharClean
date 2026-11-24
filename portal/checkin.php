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
<title>Check-in – <?= h(portalMarca()['empresa']) ?></title>
<?php require __DIR__ . '/_head.php'; ?>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f0f9ff;min-height:100vh;color:#1e293b}
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
.cam-stage{position:relative;margin-bottom:14px;border-radius:12px;overflow:hidden}
.cam-stage video{margin-bottom:0;display:block;transform:scaleX(-1)}
.cam-oval{position:absolute;left:50%;top:48%;width:56%;aspect-ratio:3/4;max-height:86%;transform:translate(-50%,-50%);border:3px dashed rgba(255,255,255,.85);border-radius:50%;box-shadow:0 0 0 999px rgba(15,23,42,.35);pointer-events:none;transition:border-color .2s}
.cam-oval.ok{border-style:solid;border-color:#4ade80}
.cam-hint{position:absolute;left:0;right:0;bottom:10px;text-align:center;color:#fff;font-size:14px;font-weight:600;text-shadow:0 1px 3px rgba(0,0,0,.7);padding:0 12px}
.success-icon{font-size:56px;text-align:center;margin-bottom:10px}
.success-title{font-size:19px;font-weight:800;text-align:center;color:#16a34a;margin-bottom:6px}
.success-sub{font-size:14px;color:#64748b;text-align:center;line-height:1.6}
</style>
</head>
<body>

<div class="header">
  <div class="header-left">
    <img src="<?= h(portalMarca()['logo']) ?>" alt="" onerror="this.remove()">
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
      <div class="cam-stage">
        <video id="video" autoplay playsinline muted></video>
        <div class="cam-oval" id="camOval"></div>
        <div class="cam-hint" id="camHint" aria-live="polite">Acomoda tu rostro dentro del óvalo</div>
      </div>
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
  const MATCH_DIST   = 0.6;     // distancia máxima entre rostros para darlo por verificado
  const MAX_RETRIES  = 3;
  const MAX_PX       = 720;     // lado mayor de la selfie que se envía (~80 KB)
  const MODEL_WAIT   = 8000;    // ms máximos esperando el reconocimiento facial al capturar

  const $ = id => document.getElementById(id);
  const elIdle = $('step-idle'), elCamera = $('step-camera'), elReview = $('step-review'), elResult = $('step-result');
  const btnStart = $('btnStart'), btnCapture = $('btnCapture'), btnSubmit = $('btnSubmit'), btnRetry = $('btnRetry');
  const idleStatus = $('idleStatus'), faceStatus = $('faceStatus');
  const video = $('video'), canvas = $('canvas'), preview = $('preview'), refPhoto = $('refPhoto');
  const camOval = $('camOval'), camHint = $('camHint');

  if (!btnStart) return; // jornada ya completa, no hay acciones

  let stream = null, lat = null, lng = null;
  let lastBlob = null, lastFaceScore = null, retries = 0;
  let refDescriptor = null, liveTimer = null;

  const detOpts = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 });

  /* ── Reconocimiento facial: se carga en segundo plano; al capturar se espera (máx. 8 s) ── */
  const facePromise = (TIENE_REF && window.faceapi)
    ? Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri('assets/face-api/weights'),
        faceapi.nets.faceLandmark68Net.loadFromUri('assets/face-api/weights'),
        faceapi.nets.faceRecognitionNet.loadFromUri('assets/face-api/weights'),
        refPhoto.complete && refPhoto.naturalWidth ? Promise.resolve()
          : new Promise((ok, fail) => { refPhoto.addEventListener('load', ok, { once: true }); refPhoto.addEventListener('error', fail, { once: true }); }),
      ]).then(async () => {
        const det = await faceapi.detectSingleFace(refPhoto, detOpts()).withFaceLandmarks().withFaceDescriptor();
        refDescriptor = det ? det.descriptor : null;
        return refDescriptor ? true : 'sinref';      // 'sinref' = su foto de registro no tiene un rostro reconocible
      }).catch(() => false)
    : Promise.resolve(false);

  const conTiempo = (p, ms) => Promise.race([p, new Promise(r => setTimeout(() => r(false), ms))]);

  /* ── Paso 1: ubicación + cámara ── */
  btnStart.addEventListener('click', () => {
    btnStart.disabled = true;
    idleStatus.textContent = 'Obteniendo tu ubicación…';
    if (!navigator.geolocation) {
      idleStatus.textContent = 'Tu navegador no permite obtener la ubicación. Prueba con Chrome o Safari actualizados.';
      btnStart.disabled = false;
      return;
    }
    navigator.geolocation.getCurrentPosition(
      async pos => { lat = pos.coords.latitude; lng = pos.coords.longitude; await startCamera(); },
      () => {
        idleStatus.textContent = 'Necesitamos tu ubicación para registrar tu asistencia. Activa el permiso de ubicación e intenta de nuevo.';
        btnStart.disabled = false;
      },
      { enableHighAccuracy: true, timeout: 12000 }
    );
  });

  async function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      idleStatus.textContent = 'Tu navegador no permite usar la cámara aquí. Abre el portal con https:// en Chrome o Safari.';
      btnStart.disabled = false;
      elIdle.hidden = false;
      return;
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 } }, audio: false });
      video.srcObject = stream;
      elIdle.hidden = true; elReview.hidden = true; elCamera.hidden = false;
      startLiveGuide();
    } catch (e) {
      idleStatus.textContent = 'No se pudo abrir la cámara. Revisa que el permiso de cámara esté activado e intenta de nuevo.';
      btnStart.disabled = false;
      elIdle.hidden = false;
    }
  }

  function stopCamera() {
    clearInterval(liveTimer); liveTimer = null;
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  }

  /* Guía en vivo: el óvalo se pone verde cuando se ve un rostro (solo orienta; no bloquea la captura) */
  function startLiveGuide() {
    camOval.classList.remove('ok');
    camHint.textContent = 'Acomoda tu rostro dentro del óvalo';
    if (!TIENE_REF || !window.faceapi) return;
    let ocupado = false;
    liveTimer = setInterval(async () => {
      if (ocupado || !faceapi.nets.tinyFaceDetector.isLoaded || video.readyState < 2) return;
      ocupado = true;
      try {
        const d = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.4 }));
        const ok = !!d && d.box.width >= video.videoWidth * 0.2;
        camOval.classList.toggle('ok', ok);
        camHint.textContent = ok ? '¡Bien! Toca Capturar selfie'
          : (d ? 'Acércate un poco más' : 'Acomoda tu rostro dentro del óvalo, con buena luz');
      } catch (e) { /* la guía es opcional */ }
      ocupado = false;
    }, 500);
  }

  /* ── Paso 2: capturar selfie (reducida a 720 px) ── */
  btnCapture.addEventListener('click', async () => {
    const vw = video.videoWidth || 480, vh = video.videoHeight || 640;
    const k = Math.min(1, MAX_PX / Math.max(vw, vh));
    canvas.width = Math.round(vw * k); canvas.height = Math.round(vh * k);
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    lastBlob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.85));
    preview.src = URL.createObjectURL(lastBlob);

    stopCamera();
    elCamera.hidden = true; elReview.hidden = false;
    btnSubmit.disabled = true; btnRetry.disabled = false;
    lastFaceScore = null;

    if (TIENE_REF && window.faceapi) {
      faceStatus.innerHTML = '<p class="tip">Verificando que eres tú…</p>';
      const listo = await conTiempo(facePromise, MODEL_WAIT);
      if (listo === 'sinref') {
        faceStatus.innerHTML = '<p class="tip">Tu foto de registro no permite la verificación automática; pide a Recursos Humanos que la actualice. Puedes enviar tu registro, ellos lo confirmarán.</p>';
      } else if (!listo) {
        faceStatus.innerHTML = '<p class="tip">Este celular no pudo hacer la verificación automática. Puedes enviar tu registro; Recursos Humanos lo confirmará.</p>';
      } else {
        try {
          const det = await faceapi.detectSingleFace(canvas, detOpts()).withFaceLandmarks().withFaceDescriptor();
          if (!det) {
            faceStatus.innerHTML = '<div class="warn">No alcanzamos a ver tu rostro. Acércate, mira de frente a la cámara y busca un lugar con más luz. Puedes repetir la foto o enviarla así.</div>';
          } else {
            const dist = faceapi.euclideanDistance(refDescriptor, det.descriptor);
            lastFaceScore = Math.round(Math.max(0, 1 - dist) * 100);
            if (dist <= MATCH_DIST) {
              faceStatus.innerHTML = '<div class="ok">✅ ¡Listo, eres tú!</div>';
            } else if (retries < MAX_RETRIES) {
              retries++;
              faceStatus.innerHTML = `<div class="warn">La foto no se parece lo suficiente a tu foto de registro. Repítela de frente, sin lentes oscuros ni gorra (intento ${retries} de ${MAX_RETRIES}).</div>`;
            } else {
              faceStatus.innerHTML = '<div class="warn">Puedes enviar tu registro así; Recursos Humanos lo confirmará.</div>';
            }
          }
        } catch (e) {
          faceStatus.innerHTML = '<p class="tip">No se pudo hacer la verificación automática. Puedes enviar tu registro; Recursos Humanos lo confirmará.</p>';
        }
      }
    } else {
      faceStatus.innerHTML = '';
    }
    btnSubmit.disabled = false;
  });

  /* ── Repetir foto ── */
  btnRetry.addEventListener('click', async () => {
    elReview.hidden = true;
    faceStatus.innerHTML = '';
    await startCamera();
  });

  /* ── Enviar al servidor (los mismos datos si hay que reintentar) ── */
  function mostrarError(html, conReintento) {
    elReview.hidden = true; elResult.hidden = false;
    elResult.innerHTML = '<div class="error">' + html + '</div>'
      + (conReintento ? '<button class="btn btn-primary" id="btnReenviar">Intentar de nuevo</button>' : '')
      + '<button class="btn btn-outline" onclick="location.reload()">Empezar de nuevo</button>';
    const b = $('btnReenviar');
    if (b) b.addEventListener('click', enviar);
  }

  async function enviar() {
    btnSubmit.disabled = true; btnRetry.disabled = true;
    elResult.hidden = true; elReview.hidden = false;
    faceStatus.insertAdjacentHTML('beforeend', '<p class="tip" id="enviando">Enviando…</p>');
    const quitarEnviando = () => { const el = $('enviando'); if (el) el.remove(); };

    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('lat', String(lat));
    fd.append('lng', String(lng));
    fd.append('foto', lastBlob, 'selfie.jpg');
    fd.append('face_score', lastFaceScore === null ? '' : String(lastFaceScore));

    let res;
    try {
      res = await fetch('checkin_submit.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    } catch (e) {
      quitarEnviando();
      mostrarError(navigator.onLine === false
        ? 'Tu celular no tiene internet en este momento. Conéctate y vuelve a intentar.'
        : 'No se pudo conectar con el servidor. Revisa tu señal o Wi-Fi y vuelve a intentar.', true);
      return;
    }
    quitarEnviando();

    if (res.redirected && /login\.php/.test(res.url)) {
      mostrarError('Tu sesión se cerró. <a href="login.php">Vuelve a entrar</a> para registrar tu asistencia.', false);
      return;
    }
    let data = null;
    try { data = await res.json(); } catch (e) { /* no es JSON: error del servidor o del hosting */ }

    if (!data) {
      mostrarError('El sistema tuvo un problema al guardar tu registro (código ' + res.status + '). Intenta de nuevo; si continúa, avisa a Recursos Humanos.', true);
      return;
    }
    if (data.code === 'sesion') {
      mostrarError('Tu sesión se cerró. <a href="login.php">Vuelve a entrar</a> para registrar tu asistencia.', false);
      return;
    }
    if (data.type === 'success') {
      elReview.hidden = true; elResult.hidden = false;
      elResult.innerHTML = '<div class="success-icon">✅</div><div class="success-title"></div>';
      elResult.querySelector('.success-title').textContent = data.msg;
      setTimeout(() => location.reload(), 1800);
      return;
    }
    const msg = document.createElement('span');
    msg.textContent = data.msg || 'Ocurrió un error, intenta de nuevo.';
    mostrarError(msg.outerHTML, res.status >= 500);
  }
  btnSubmit.addEventListener('click', enviar);
})();
</script>
</body>
</html>
