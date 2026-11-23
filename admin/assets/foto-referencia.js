/* ==========================================================================
   Foto de referencia del empleado (verificación facial del check-in)

   · Cámara dentro de la página (trasera por defecto: quien captura suele ser
     el supervisor con su celular; se puede cambiar a la frontal). En el
     autorregistro del portal arranca en la frontal (data-facing="user").
   · Antes de aceptar la foto la revisa face-api.js (el mismo modelo que usa el
     portal): exactamente un rostro, suficientemente grande y nítido. Así no se
     guarda una referencia con la que después ningún check-in coincidiría.
   · Se reduce a 800 px antes de subir (~100 KB). El servidor vuelve a validar.

   Marcado esperado (ver empleados.php, pestaña "Portal y foto"):
     #ref-photo[data-faceapi][data-weights], #foto_referencia (input file real),
     #ref-preview, #ref-status, #ref-cam-btn, #ref-file, dialog#cam-dialog con
     #cam-video, #cam-capture, #cam-switch, #cam-cancel, #cam-msg
   ========================================================================== */
(function () {
  const root = document.getElementById('ref-photo');
  if (!root) return;

  const $        = id => document.getElementById(id);
  const realInput = $('foto_referencia');
  const preview   = $('ref-preview');
  const status    = $('ref-status');
  const dlg       = $('cam-dialog');
  const video     = $('cam-video');
  const msg       = $('cam-msg');
  const MAX_PX    = 800;
  let stream = null;
  let facing = root.dataset.facing || 'environment';   // data-facing="user" para selfie (autorregistro)
  let faceapiReady = null;

  function setStatus(text, kind) {
    status.textContent = text;
    status.dataset.kind = kind || '';
  }

  function loadFaceApi() {
    if (faceapiReady) return faceapiReady;
    faceapiReady = new Promise((resolve, reject) => {
      if (window.faceapi) return resolve();
      const s = document.createElement('script');
      s.src = root.dataset.faceapi;
      s.onload = resolve;
      s.onerror = () => reject(new Error('faceapi'));
      document.head.appendChild(s);
    }).then(() => window.faceapi.nets.tinyFaceDetector.loadFromUri(root.dataset.weights));
    faceapiReady.catch(() => { faceapiReady = null; });
    return faceapiReady;
  }

  /** null si la foto sirve como referencia; si no, el motivo para mostrar. */
  async function revisarRostro(canvas) {
    const dets = await window.faceapi.detectAllFaces(
      canvas, new window.faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 }));
    if (dets.length === 0) return 'No se detectó un rostro. Que mire de frente a la cámara, con buena luz.';
    if (dets.length > 1)   return 'Hay más de un rostro en la foto. Debe aparecer solo el empleado.';
    if (dets[0].box.width / canvas.width < 0.2) return 'El rostro se ve muy pequeño. Acérquese más.';
    if (dets[0].score < 0.7) return 'El rostro no se ve con claridad (luz o enfoque). Intente de nuevo.';
    return null;
  }

  function aCanvas(source, w, h) {
    const scale  = Math.min(1, MAX_PX / Math.max(w, h));
    const canvas = document.createElement('canvas');
    canvas.width  = Math.round(w * scale);
    canvas.height = Math.round(h * scale);
    canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height);
    return canvas;
  }

  async function aceptar(canvas, aviso) {
    const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.88));
    const dt = new DataTransfer();
    dt.items.add(new File([blob], 'referencia.jpg', { type: 'image/jpeg' }));
    realInput.files = dt.files;
    preview.src = URL.createObjectURL(blob);
    preview.hidden = false;
    $('ref-initials')?.remove();
    setStatus(aviso || '✓ Rostro validado. Guarde los cambios para aplicar la nueva foto.', aviso ? 'warn' : 'ok');
  }

  /** Valida y acepta; si face-api no está disponible en el dispositivo, acepta con aviso. */
  async function validarYAceptar(canvas, onError) {
    try {
      await loadFaceApi();
    } catch (e) {
      await aceptar(canvas, '⚠ No se pudo validar el rostro en este dispositivo. Revise que la foto sea clara antes de guardar.');
      return true;
    }
    const motivo = await revisarRostro(canvas);
    if (motivo) { onError(motivo); return false; }
    await aceptar(canvas);
    return true;
  }

  /* ── Cámara ─────────────────────────────────────────────── */
  function detener() {
    if (stream) stream.getTracks().forEach(t => t.stop());
    stream = null;
  }

  async function iniciarStream() {
    detener();
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: facing }, width: { ideal: 1280 }, height: { ideal: 960 } },
      audio: false,
    });
    video.srcObject = stream;
    video.classList.toggle('mirror', facing === 'user');
    await video.play();
  }

  $('ref-cam-btn').addEventListener('click', async function () {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus('Este navegador no permite usar la cámara (se requiere HTTPS). Use "Subir foto".', 'err');
      return;
    }
    dlg.showModal();
    msg.textContent = 'Abriendo cámara…';
    loadFaceApi().catch(() => {});   // se precarga mientras se encuadra
    try {
      await iniciarStream();
      msg.textContent = 'Centre el rostro en el óvalo, con buena luz, y presione Capturar.';
    } catch (e) {
      msg.textContent = e && e.name === 'NotAllowedError'
        ? 'Permiso de cámara denegado. Habilítelo en el navegador o use "Subir foto".'
        : 'No se pudo abrir la cámara. Use "Subir foto".';
    }
  });

  $('cam-switch').addEventListener('click', function () {
    facing = facing === 'user' ? 'environment' : 'user';
    iniciarStream().catch(() => { msg.textContent = 'No se pudo cambiar de cámara.'; });
  });

  $('cam-capture').addEventListener('click', async function () {
    if (!video.videoWidth) return;
    const btn = this;
    btn.disabled = true;
    msg.textContent = 'Verificando rostro…';
    try {
      const ok = await validarYAceptar(aCanvas(video, video.videoWidth, video.videoHeight), m => { msg.textContent = m; });
      if (ok) dlg.close();
    } finally {
      btn.disabled = false;
    }
  });

  $('cam-cancel').addEventListener('click', () => dlg.close());
  dlg.addEventListener('close', detener);

  /* ── Subir desde archivo / galería ──────────────────────── */
  $('ref-file').addEventListener('change', async function () {
    const file = this.files && this.files[0];
    this.value = '';
    if (!file) return;
    setStatus('Verificando foto…');
    let bmp;
    try {
      bmp = await createImageBitmap(window.compressImage ? await window.compressImage(file, MAX_PX) : file);
    } catch (e) {
      setStatus('No se pudo leer la imagen. Use una foto JPG o PNG.', 'err');
      return;
    }
    await validarYAceptar(aCanvas(bmp, bmp.width, bmp.height), m => setStatus(m, 'err'));
  });
})();
