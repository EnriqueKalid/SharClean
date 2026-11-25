/* ==========================================================================
   Fecha en palabras junto a cada <input type="date"> de captura.

   El selector de fecha del navegador se MUESTRA en el orden del idioma de la
   computadora (en una PC en inglés: mm/dd/aaaa). Quien escribe "05/03/1990"
   pensando en día/mes guarda el 3 de mayo sin darse cuenta. Esta leyenda
   ("5 de marzo de 1990") hace visible la fecha real que se va a guardar,
   sin depender de la configuración regional.

   · Solo en formularios de captura (POST). Los filtros (formularios GET) no.
   · Para excluir un campo: data-sin-leyenda.
   ========================================================================== */
(function () {
  const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

  function enPalabras(v) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || '');
    return m ? `${Number(m[3])} de ${MESES[Number(m[2]) - 1]} de ${m[1]}` : '';
  }

  function preparar(inp) {
    if (inp.dataset.leyenda || inp.hasAttribute('data-sin-leyenda')) return;
    const form = inp.form || inp.closest('form');
    if (form && (form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
    inp.dataset.leyenda = '1';
    const out = document.createElement('small');
    out.className = 'fecha-legible';
    out.setAttribute('aria-live', 'polite');
    out.style.cssText = 'display:block;margin-top:4px;font-size:12px;font-weight:600;color:#0369a1;';
    inp.insertAdjacentElement('afterend', out);
    const pintar = () => {
      const t = enPalabras(inp.value);
      out.textContent = t ? '📅 ' + t : '';
    };
    inp.addEventListener('input', pintar);
    inp.addEventListener('change', pintar);
    pintar();
  }

  function iniciar() {
    document.querySelectorAll('input[type="date"]').forEach(preparar);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();
})();
