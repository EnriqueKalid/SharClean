/* ==========================================================================
   Foto de referencia del empleado (verificación facial del check-in)

   · Cámara dentro de la página (trasera por defecto: quien captura suele ser
     el supervisor con su celular; se puede cambiar a la frontal). En el
     autorregistro del portal arranca en la frontal (data-facing="user").
   · Guía en vivo: "Capturar" solo se activa cuando hay UN rostro, centrado y
     suficientemente grande dentro del óvalo (el óvalo se pone verde).
   · Al capturar (o subir una foto) se valida con face-api.js y el MISMO proceso
     que usa el check-in del portal: detectar el rostro y obtener su "huella"
     (descriptor). Si no se puede obtener, la foto se rechaza: con ella ningún
     check-in podría compararse.
   · Sin verificación facial disponible NO se acepta la foto: una referencia sin
     validar deja el check-in sin protección contra suplantación.
   · Se reduce a 800 px antes de subir (~100 KB). El servidor vuelve a validar.

   Marcado esperado (ver empleados.php, pestaña "Portal y foto", y portal/registro.php):
     #ref-photo[data-faceapi][data-weights], #foto_referencia (input file real),
     #ref-preview, #ref-status, #ref-cam-btn, #ref-file, dialog#cam-dialog con
     .cam-oval, #cam-video, #cam-capture, #cam-switch, #cam-cancel, #cam-msg
   ========================================================================== */
(function () {
  const root = document.getElementById('ref-photo');
  if (!root) return;

  const $         = id => document.getElementById(id);
  const realInput = $('foto_referencia');
  const preview   = $('ref-preview');
  const status    = $('ref-status');
  const dlg       = $('cam-dialog');
  const video     = $('cam-video');
  const msg       = $('cam-msg');
  const btnCap    = $('cam-capture');
  const oval      = dlg.querySelector('.cam-oval');
  const MAX_PX    = 800;
  const LOAD_WAIT = 12000;   // ms máximos esperando que cargue la verificación facial
  let stream = null, liveTimer = null;
  let facing = root.dataset.facing || 'environment';   // data-facing="user" para selfie (autorregistro)
  let detectorReady = null, recognitionReady = null;

  const SIN_VERIFICACION = 'No se pudo cargar la verificación facial. Revise la conexión a internet e intente de nuevo; si continúa, avise al administrador del sistema.';

  function setStatus(text, kind) {
    status.textContent = text;
    status.dataset.kind = kind || '';
  }
  const conTiempo = (p, ms) => Promise.race([p, new Promise((_, no) => setTimeout(() => no(new Error('tiempo')), ms))]);

  /* Detector (190 KB): para la guía del óvalo. */
  function loadDetector() {
    if (detectorReady) return detectorReady;
    detectorReady = new Promise((resolve, reject) => {
      if (window.faceapi) return resolve();
      const s = document.createElement('script');
      s.src = root.dataset.faceapi;
      s.onload = resolve;
      s.onerror = () => reject(new Error('faceapi'));
      document.head.appendChild(s);
    }).then(() => window.faceapi.nets.tinyFaceDetector.loadFromUri(root.dataset.weights));
    detectorReady.catch(e => { console.error('[foto referencia] No cargó el detector:', e); detectorReady = null; });
    return detectorReady;
  }

  /* Puntos del rostro + reconocimiento (~6 MB): para comprobar que la foto sirve de referencia. */
  function loadRecognition() {
    if (recognitionReady) return recognitionReady;
    recognitionReady = loadDetector().then(() => Promise.all([
      window.faceapi.nets.faceLandmark68Net.loadFromUri(root.dataset.weights),
      window.faceapi.nets.faceRecognitionNet.loadFromUri(root.dataset.weights),
    ]));
    recognitionReady.catch(e => { console.error('[foto referencia] No cargó el reconocimiento:', e); recognitionReady = null; });
    return recognitionReady;
  }

  /** Un solo rostro, centrado y grande. Devuelve null si está bien, o el motivo. */
  function problemaEncuadre(dets, w, h) {
    if (dets.length === 0) return 'Centre el rostro dentro del óvalo, con buena luz.';
    if (dets.length > 1)   return 'Hay más de una persona. Debe aparecer solo el empleado.';
    const b = dets[0].box, cx = (b.x + b.width / 2) / w, cy = (b.y + b.height / 2) / h;
    if (b.width / w < 0.2) return 'Acérquese un poco más.';
    if (cx < 0.25 || cx > 0.75 || cy < 0.2 || cy > 0.75) return 'Centre el rostro dentro del óvalo.';
    return null;
  }

  /** null si la foto sirve como referencia para el check-in; si no, el motivo para mostrar. */
  async function revisarRostro(canvas) {
    const fa = window.faceapi;
    const dets = await fa.detectAllFaces(canvas, new fa.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 }));
    if (dets.length === 0) return 'No se detectó un rostro. Que mire de frente a la cámara, con buena luz.';
    if (dets.length > 1)   return 'Hay más de un rostro en la foto. Debe aparecer solo el empleado.';
    if (dets[0].box.width / canvas.width < 0.2) return 'El rostro se ve muy pequeño. Acérquese más.';
    if (dets[0].score < 0.7) return 'El rostro no se ve con claridad (luz o enfoque). Intente de nuevo.';
    // Mismo proceso que el check-in (portal/checkin.php): si aquí no sale la huella, allá tampoco
    const ref = await fa.detectSingleFace(canvas, new fa.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 }))
      .withFaceLandmarks().withFaceDescriptor();
    if (!ref) return 'No se pudieron reconocer los rasgos del rostro. Sin lentes oscuros, gorra ni cubrebocas, de frente y con buena luz.';
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

  async function aceptar(canvas) {
    const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.88));
    const dt = new DataTransfer();
    dt.items.add(new File([blob], 'referencia.jpg', { type: 'image/jpeg' }));
    realInput.files = dt.files;
    preview.src = URL.createObjectURL(blob);
    preview.hidden = false;
    $('ref-initials')?.remove();
    setStatus('✓ Rostro validado: esta foto servirá para verificar los check-in. Guarde los cambios para aplicarla.', 'ok');
  }

  /** Valida con reconocimiento facial y solo entonces acepta. */
  async function validarYAceptar(canvas, onError) {
    try {
      await conTiempo(loadRecognition(), LOAD_WAIT);
    } catch (e) {
      onError(SIN_VERIFICACION);
      return false;
    }
    const motivo = await revisarRostro(canvas);
    if (motivo) { onError(motivo); return false; }
    await aceptar(canvas);
    return true;
  }

  /* ── Cámara ─────────────────────────────────────────────── */
  function detener() {
    clearInterval(liveTimer); liveTimer = null;
    if (stream) stream.getTracks().forEach(t => t.stop());
    stream = null;
  }

  async function iniciarStream() {
    if (stream) stream.getTracks().forEach(t => t.stop());
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: facing }, width: { ideal: 1280 }, height: { ideal: 960 } },
      audio: false,
    });
    video.srcObject = stream;
    video.classList.toggle('mirror', facing === 'user');
    await video.play();
  }

  /* Guía en vivo: el óvalo se pone verde y "Capturar" se activa solo con un rostro bien encuadrado */
  async function guiaEnVivo() {
    clearInterval(liveTimer); liveTimer = null;
    btnCap.disabled = true;
    oval && oval.classList.remove('ok');
    msg.textContent = 'Preparando verificación facial…';
    try {
      await conTiempo(loadDetector(), LOAD_WAIT);
    } catch (e) {
      msg.textContent = SIN_VERIFICACION;
      return;
    }
    if (!stream) return;                          // se cerró la cámara mientras cargaba
    loadRecognition().catch(() => {});           // se precarga mientras se encuadra
    let ocupado = false;
    liveTimer = setInterval(async () => {
      if (ocupado || !stream || video.readyState < 2) return;
      ocupado = true;
      try {
        const fa = window.faceapi;
        const dets = await fa.detectAllFaces(video, new fa.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }));
        const problema = problemaEncuadre(dets, video.videoWidth, video.videoHeight);
        oval && oval.classList.toggle('ok', !problema);
        btnCap.disabled = !!problema;
        msg.textContent = problema || '¡Bien! Presione Capturar.';
      } catch (e) {
        console.error('[foto referencia] Falló la detección en vivo:', e);
      }
      ocupado = false;
    }, 400);
  }

  $('ref-cam-btn').addEventListener('click', async function () {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus('Este navegador no permite usar la cámara (se requiere HTTPS). Use "Subir foto".', 'err');
      return;
    }
    dlg.showModal();
    btnCap.disabled = true;
    msg.textContent = 'Abriendo cámara…';
    loadDetector().catch(() => {});   // se precarga mientras abre la cámara
    try {
      await iniciarStream();
      guiaEnVivo();
    } catch (e) {
      msg.textContent = e && e.name === 'NotAllowedError'
        ? 'Permiso de cámara denegado. Habilítelo en el navegador o use "Subir foto".'
        : 'No se pudo abrir la cámara. Use "Subir foto".';
    }
  });

  $('cam-switch').addEventListener('click', function () {
    facing = facing === 'user' ? 'environment' : 'user';
    iniciarStream().then(guiaEnVivo).catch(() => { msg.textContent = 'No se pudo cambiar de cámara.'; });
  });

  btnCap.addEventListener('click', async function () {
    if (!video.videoWidth) return;
    clearInterval(liveTimer); liveTimer = null;
    btnCap.disabled = true;
    msg.textContent = 'Verificando rostro…';
    const ok = await validarYAceptar(aCanvas(video, video.videoWidth, video.videoHeight), m => { msg.textContent = m; });
    if (ok) { dlg.close(); return; }
    setTimeout(() => { if (stream) guiaEnVivo(); }, 2500);   // deja leer el motivo y vuelve a la guía
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
