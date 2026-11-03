/* ==========================================================================
   Compresión de imágenes en el navegador antes de subirlas.
   Una foto de celular (3–8 MB, 12 MP) queda en ~100–200 KB: sube rápido con
   datos móviles y no choca con el límite de subida del servidor.
   El servidor SIEMPRE vuelve a validar y re-codificar (no se confía en esto).

   Uso:  <input type="file" accept="image/*" data-compress="1024">
         (valor = lado mayor en px). Mientras comprime, deshabilita el submit
         del formulario. También expone window.compressImage(file, maxPx).
   ========================================================================== */
(function () {
  async function compressImage(file, maxPx) {
    if (!file || !file.type.startsWith('image/')) return file;
    let bmp;
    try {
      bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });   // respeta la rotación EXIF
    } catch (e) {
      return file;   // formato que el navegador no decodifica: se manda tal cual y el servidor decide
    }
    const scale = Math.min(1, maxPx / Math.max(bmp.width, bmp.height));
    if (scale === 1 && file.size < 400 * 1024 && file.type === 'image/jpeg') return file;

    const canvas = document.createElement('canvas');
    canvas.width  = Math.round(bmp.width * scale);
    canvas.height = Math.round(bmp.height * scale);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';                       // fondo blanco para PNG con transparencia
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
    bmp.close && bmp.close();

    const blob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', 0.85));
    if (!blob || blob.size >= file.size) return file;
    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
  }
  window.compressImage = compressImage;

  document.addEventListener('change', async function (e) {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('input[type=file][data-compress]')) return;
    const file = input.files && input.files[0];
    if (!file || typeof DataTransfer === 'undefined') return;

    const submits = input.form ? input.form.querySelectorAll('[type=submit]') : [];
    submits.forEach(b => b.disabled = true);
    try {
      const out = await compressImage(file, parseInt(input.dataset.compress, 10) || 1024);
      if (out !== file) {
        const dt = new DataTransfer();
        dt.items.add(out);
        input.files = dt.files;
      }
    } finally {
      submits.forEach(b => b.disabled = false);
    }
  });
})();
