/* ============================================================
   Sharclean - Main JavaScript
   Vanilla JS, modular, production quality
   ============================================================ */

'use strict';

/* ── Utilities ─────────────────────────────────────────────── */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
const on = (el, ev, fn) => el && el.addEventListener(ev, fn);

function debounce(fn, ms = 150) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function formatTime() {
  const d = new Date();
  return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
}

/* ── Navbar ────────────────────────────────────────────────── */
function initNavbar() {
  const navbar = $('.navbar');
  const hamburger = $('.navbar__hamburger');
  const mobileMenu = $('.navbar__mobile-menu');
  if (!navbar) return;

  function updateNavbar() {
    if (window.scrollY > 40) {
      navbar.classList.remove('transparent');
      navbar.classList.add('solid');
    } else {
      navbar.classList.add('transparent');
      navbar.classList.remove('solid');
    }
  }

  updateNavbar();
  on(window, 'scroll', updateNavbar);

  // Hamburger toggle
  on(hamburger, 'click', () => {
    const isOpen = mobileMenu.classList.toggle('open');
    hamburger.classList.toggle('open', isOpen);
    hamburger.setAttribute('aria-expanded', isOpen);
  });

  // Close mobile menu on link click
  $$('.navbar__mobile-link').forEach(link => {
    on(link, 'click', () => {
      mobileMenu.classList.remove('open');
      hamburger.classList.remove('open');
    });
  });

  // Active link highlighting on scroll
  const sections = $$('section[id]');
  const navLinks = $$('.navbar__link[href^="#"], .navbar__mobile-link[href^="#"]');

  const sectionObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.id;
        navLinks.forEach(link => {
          link.classList.toggle('active', link.getAttribute('href') === `#${id}`);
        });
      }
    });
  }, { rootMargin: '-40% 0px -55% 0px' });

  sections.forEach(s => sectionObserver.observe(s));
}

/* ── Smooth Scroll ─────────────────────────────────────────── */
function initSmoothScroll() {
  on(document, 'click', (e) => {
    const link = e.target.closest('a[href^="#"]');
    if (!link) return;
    const target = $(link.getAttribute('href'));
    if (!target) return;
    e.preventDefault();
    const navHeight = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--nav-height')) || 72;
    const top = target.getBoundingClientRect().top + window.scrollY - navHeight;
    window.scrollTo({ top, behavior: 'smooth' });
  });
}

/* ── Scroll Reveal ─────────────────────────────────────────── */
function initScrollReveal() {
  const els = $$('.reveal, .reveal-left, .reveal-right');
  if (!els.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });

  els.forEach(el => observer.observe(el));
}

/* ── Stats Counter ─────────────────────────────────────────── */
function initStatsCounter() {
  const counters = $$('[data-count]');
  if (!counters.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const target = parseInt(el.dataset.count, 10);
      const suffix = el.dataset.suffix || '';
      const prefix = el.dataset.prefix || '';
      const duration = 1800;
      const start = performance.now();

      function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        // Ease out cubic
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = prefix + Math.floor(eased * target) + suffix;
        if (progress < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
      observer.unobserve(el);
    });
  }, { threshold: 0.5 });

  counters.forEach(el => observer.observe(el));
}

/* ── Testimonials Carousel ─────────────────────────────────── */
function initTestimonialsCarousel() {
  const slider = $('.testimonials__slider');
  if (!slider) return;

  const track   = $('.testimonials__track', slider);
  const cards   = $$('.testimonial-card', track);
  const dots    = $$('.testimonials__dot');
  const prevBtn = $('.testimonials__btn--prev');
  const nextBtn = $('.testimonials__btn--next');
  if (!cards.length) return;

  let current = 0;
  let autoplay;
  let perView = getPerView();

  function getPerView() {
    if (window.innerWidth >= 1024) return 3;
    if (window.innerWidth >= 768) return 2;
    return 1;
  }

  function maxIndex() {
    return Math.max(0, cards.length - perView);
  }

  function goTo(idx) {
    current = Math.max(0, Math.min(idx, maxIndex()));
    track.style.transform = `translateX(-${current * (100 / perView)}%)`;
    dots.forEach((d, i) => d.classList.toggle('active', i === current));
  }

  function next() { goTo(current >= maxIndex() ? 0 : current + 1); }
  function prev() { goTo(current <= 0 ? maxIndex() : current - 1); }

  on(nextBtn, 'click', () => { next(); restartAuto(); });
  on(prevBtn, 'click', () => { prev(); restartAuto(); });
  dots.forEach((d, i) => on(d, 'click', () => { goTo(i); restartAuto(); }));

  function startAuto() { autoplay = setInterval(next, 5000); }
  function restartAuto() { clearInterval(autoplay); startAuto(); }

  on(window, 'resize', debounce(() => {
    perView = getPerView();
    goTo(Math.min(current, maxIndex()));
  }));

  // Touch/swipe
  let startX = 0;
  on(slider, 'touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
  on(slider, 'touchend', e => {
    const diff = startX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) { diff > 0 ? next() : prev(); restartAuto(); }
  });

  goTo(0);
  startAuto();
}

/* ── Contact Form ──────────────────────────────────────────── */
function initContactForm() {
  const form = $('#contactForm');
  if (!form) return;

  on(form, 'submit', async (e) => {
    e.preventDefault();
    if (!validateForm(form)) return;

    const btn    = form.querySelector('[type="submit"]');
    const msg    = form.querySelector('.form-msg');
    const original = btn.innerHTML;
    btn.innerHTML = '<svg class="spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Enviando...';
    btn.disabled = true;

    try {
      const res  = await fetch('contact.php', { method: 'POST', body: new FormData(form) });
      const data = await res.json();
      showFormMsg(msg, data.type, data.msg);
      if (data.type === 'success') form.reset();
    } catch {
      showFormMsg(msg, 'error', 'Error de conexión. Intenta de nuevo.');
    } finally {
      btn.innerHTML = original;
      btn.disabled = false;
    }
  });
}

/* ── Quote Form ────────────────────────────────────────────── */
function initQuoteForm() {
  const form = $('#quoteForm');
  if (!form) return;

  on(form, 'submit', async (e) => {
    e.preventDefault();
    if (!validateForm(form)) return;

    const btn      = form.querySelector('[type="submit"]');
    const msg      = form.querySelector('.form-msg');
    const original = btn.innerHTML;
    btn.innerHTML  = '<svg class="spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Procesando...';
    btn.disabled   = true;

    try {
      const res  = await fetch('quote.php', { method: 'POST', body: new FormData(form) });
      const data = await res.json();
      showFormMsg(msg, data.type, data.msg);
      if (data.type === 'success') form.reset();
    } catch {
      showFormMsg(msg, 'error', 'Error de conexión. Intenta de nuevo.');
    } finally {
      btn.innerHTML = original;
      btn.disabled  = false;
    }
  });
}

/* ── Form Validation ───────────────────────────────────────── */
function validateForm(form) {
  let valid = true;
  $$('[required]', form).forEach(field => {
    clearFieldError(field);
    if (!field.value.trim()) {
      showFieldError(field, 'Este campo es requerido.');
      valid = false;
    } else if (field.type === 'email' && !isValidEmail(field.value)) {
      showFieldError(field, 'Ingresa un email válido.');
      valid = false;
    } else if (field.type === 'tel' && !isValidPhone(field.value)) {
      showFieldError(field, 'Ingresa un teléfono válido (mínimo 10 dígitos).');
      valid = false;
    }
  });
  return valid;
}

function showFieldError(field, msg) {
  field.classList.add('error');
  let err = field.parentNode.querySelector('.form-error');
  if (!err) {
    err = document.createElement('span');
    err.className = 'form-error';
    field.parentNode.appendChild(err);
  }
  err.textContent = msg;
}

function clearFieldError(field) {
  field.classList.remove('error');
  const err = field.parentNode.querySelector('.form-error');
  if (err) err.remove();
}

function showFormMsg(el, type, text) {
  if (!el) return;
  el.className = `form-msg ${type}`;
  el.textContent = text;
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone) {
  return /^\+?[\d\s\-().]{10,}$/.test(phone);
}

/* ── Real-time field validation feedback ──────────────────── */
function initFieldValidation() {
  document.addEventListener('blur', (e) => {
    const field = e.target;
    if (!(field instanceof Element)) return;
    if (!field.hasAttribute('required')) return;
    if (!field.matches('input, textarea, select')) return;
    clearFieldError(field);
    if (!field.value.trim()) {
      showFieldError(field, 'Este campo es requerido.');
    } else if (field.type === 'email' && !isValidEmail(field.value)) {
      showFieldError(field, 'Ingresa un email válido.');
    }
  }, true);

  on(document, 'input', (e) => {
    const field = e.target;
    if (field.classList.contains('error') && field.value.trim()) {
      clearFieldError(field);
    }
  });
}

/* ── Chatbot ───────────────────────────────────────────────── */
function initChatbot() {
  const chatbot      = $('.chatbot');
  if (!chatbot) return;

  const toggleBtn    = $('.chatbot__toggle', chatbot);
  const panel        = $('.chatbot__panel', chatbot);
  const closeBtn     = $('.chatbot__close', chatbot);
  const messages     = $('.chatbot__messages', chatbot);
  const typing       = $('.chatbot__typing', chatbot);
  const input        = $('.chatbot__input', chatbot);
  const sendBtn      = $('.chatbot__send', chatbot);
  const suggestionsEl= $('.chatbot__suggestions', chatbot);
  const badge        = $('.chatbot__badge', chatbot);

  let isOpen = false;

  function open() {
    isOpen = true;
    panel.classList.add('open');
    if (badge) badge.style.display = 'none';
    input.focus();
  }

  function close() {
    isOpen = false;
    panel.classList.remove('open');
  }

  on(toggleBtn, 'click', () => isOpen ? close() : open());
  on(closeBtn, 'click', close);

  function appendMsg(text, role = 'bot', time = formatTime()) {
    const div = document.createElement('div');
    div.className = `chatbot__msg chatbot__msg--${role}`;
    div.innerHTML = `
      <div class="chatbot__msg-bubble">${escapeHtml(text)}</div>
      <span class="chatbot__msg-time">${time}</span>`;
    messages.appendChild(div);
    scrollToBottom();
  }

  function escapeHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function scrollToBottom() {
    messages.scrollTop = messages.scrollHeight;
  }

  function showTyping() { typing && typing.classList.add('visible'); scrollToBottom(); }
  function hideTyping() { typing && typing.classList.remove('visible'); }

  function renderSuggestions(suggestions) {
    if (!suggestionsEl || !suggestions || !suggestions.length) return;
    suggestionsEl.innerHTML = '';
    suggestions.forEach(s => {
      const btn = document.createElement('button');
      btn.className = 'chatbot__suggestion';
      btn.type = 'button';
      btn.textContent = s;
      on(btn, 'click', () => sendMessage(s));
      suggestionsEl.appendChild(btn);
    });
  }

  /* ── Recruitment flow ─────────────────────────────────────── */
  const cfg = window.siteCfg || { empresa: 'Sharclean', emailInfo: 'informacion@sharclean.com.mx', telefono: '' };
  let recruitMode     = false;
  let recruitStep     = 0;
  let waitingForDias  = false;
  const recruitData   = {};

  const RECRUIT_STEPS = [
    { key: 'nombre',        type: 'text',
      prompt: 'Para empezar, escribe tu nombre completo:' },
    { key: 'estado',        type: 'choices',
      prompt: '¿En qué estado vives actualmente?',
      choices: ['Ciudad de México (CDMX)', 'Estado de México', 'Veracruz', 'Querétaro', 'San Luis Potosí', 'Otro'] },
    { key: 'edad',          type: 'text',
      prompt: '¿Qué edad tienes? (Escribe solo el número):' },
    { key: 'experiencia',   type: 'choices',
      prompt: '¿Tienes experiencia previa en limpieza?',
      choices: ['Sí, tengo más de 6 meses de experiencia', 'Sí, pero tengo menos de 6 meses', 'No tengo experiencia, pero tengo muchas ganas de aprender'] },
    { key: 'traslado',      type: 'choices',
      prompt: 'Para nosotros es importante que tu lugar de trabajo te quede cerca. ¿Cuánto tiempo haces de tu casa a la zona donde buscas empleo?',
      choices: ['Menos de 45 minutos', 'Entre 45 minutos y 1 hora y media', 'Más de 1 hora y media'] },
    { key: 'salud',         type: 'choices',
      prompt: 'En Sharclean nos preocupamos por tu seguridad, ya que el trabajo requiere esfuerzo físico. ¿Padeces alguna enfermedad crónica o lesión que debamos conocer para asignarte actividades adecuadas?',
      choices: ['Sí', 'No'] },
    { key: 'dependientes',  type: 'choices',
      prompt: '¿Tienes personas que dependan económicamente de ti? (Hijos, padres o familiares).',
      choices: ['Sí', 'No'] },
    { key: 'disponibilidad', type: 'choices',
      prompt: '¿Cuentas con disponibilidad para empezar a trabajar de inmediato?',
      choices: ['Sí, puedo empezar mañana mismo', 'Necesito unos días (especifica cuántos)', 'No por el momento'] },
    { key: 'telefono',      type: 'text',
      prompt: '¡Ya casi terminamos! ¿Cuál es tu número de teléfono? Un reclutador te contactará en las próximas 24-48 horas.' },
  ];

  function startRecruitment() {
    recruitMode = true;
    recruitStep = 0;
    for (const k in recruitData) delete recruitData[k];
    suggestionsEl.innerHTML = '';
    showTyping();
    setTimeout(() => {
      hideTyping();
      appendMsg(
        '¡Hola! Gracias por tu interés en unirte al equipo de ' + cfg.empresa + '. ' +
        'Soy tu asistente virtual y te ayudaré a postularte. Esta charla durará menos de 5 minutos. 😊',
        'bot'
      );
      advanceRecruitStep();
    }, 700);
  }

  function advanceRecruitStep() {
    if (recruitStep >= RECRUIT_STEPS.length) { submitRecruitment(); return; }
    const step = RECRUIT_STEPS[recruitStep];
    showTyping();
    setTimeout(() => {
      hideTyping();
      appendMsg(step.prompt, 'bot');
      if (step.type === 'choices') {
        setRecruitInput(false);
        renderRecruitChoices(step.choices);
      } else {
        setRecruitInput(true);
        input.placeholder =
          step.key === 'edad'     ? 'Tu edad (ej: 28)...' :
          step.key === 'telefono' ? 'Tu número de teléfono...' :
                                    'Escribe tu respuesta...';
        input.focus();
      }
    }, 750);
  }

  function setRecruitInput(visible) {
    const row = input.closest('.chatbot__input-row');
    if (row) row.style.display = visible ? '' : 'none';
  }

  function renderRecruitChoices(choices) {
    suggestionsEl.innerHTML = '';
    choices.forEach(c => {
      const btn = document.createElement('button');
      btn.className = 'chatbot__suggestion';
      btn.type = 'button';
      btn.textContent = c;
      on(btn, 'click', () => handleRecruitChoice(c));
      suggestionsEl.appendChild(btn);
    });
  }

  function handleRecruitChoice(choice) {
    const step = RECRUIT_STEPS[recruitStep];
    appendMsg(choice, 'user');
    suggestionsEl.innerHTML = '';

    if (step.key === 'estado' && choice === 'Otro') {
      endRecruitment('Por el momento no tenemos vacantes en tu zona, pero guardaremos tu contacto. ¡Muchas gracias por tu interés! 😊');
      return;
    }

    if (step.key === 'disponibilidad' && choice.startsWith('Necesito unos días')) {
      waitingForDias = true;
      showTyping();
      setTimeout(() => {
        hideTyping();
        appendMsg('¿En cuántos días aproximadamente podrías empezar? (Escribe el número):', 'bot');
        setRecruitInput(true);
        input.placeholder = 'Ej: 3, 5, 7 días...';
        input.focus();
      }, 600);
      return;
    }

    recruitData[step.key] = choice;
    recruitStep++;
    advanceRecruitStep();
  }

  function handleRecruitTextInput(msg) {
    const val = msg.trim();

    if (waitingForDias) {
      waitingForDias = false;
      appendMsg(val, 'user');
      recruitData.disponibilidad = 'Necesito ' + val + ' días';
      recruitStep++;
      advanceRecruitStep();
      return;
    }

    const step = RECRUIT_STEPS[recruitStep];

    if (step.key === 'edad') {
      const age = parseInt(val, 10);
      if (isNaN(age) || !/^\d{1,3}$/.test(val)) {
        appendMsg(val, 'user');
        showTyping();
        setTimeout(() => { hideTyping(); appendMsg('Por favor escribe solo tu edad en números (ej: 28).', 'bot'); input.focus(); }, 500);
        return;
      }
      if (age < 20 || age > 55) {
        appendMsg(val, 'user');
        endRecruitment('Agradecemos tu interés. Por ahora, el perfil solicitado para estas vacantes es de 20 a 55 años. ¡Te deseamos mucho éxito! 🙌');
        return;
      }
    }

    if (step.key === 'telefono' && !/^\+?[\d\s\-().]{8,}$/.test(val)) {
      appendMsg(val, 'user');
      showTyping();
      setTimeout(() => { hideTyping(); appendMsg('Por favor ingresa un número de teléfono válido (mínimo 8 dígitos).', 'bot'); input.focus(); }, 500);
      return;
    }

    appendMsg(val, 'user');
    recruitData[step.key] = val;
    recruitStep++;
    advanceRecruitStep();
  }

  function endRecruitment(msg) {
    recruitMode = false;
    setRecruitInput(true);
    input.placeholder = 'Escribe tu pregunta...';
    showTyping();
    setTimeout(() => {
      hideTyping();
      appendMsg(msg, 'bot');
      renderSuggestions(['¿Qué servicios ofrecen?', '¿Cómo cotizo?', '¿Zona de cobertura?']);
    }, 700);
  }

  async function submitRecruitment() {
    setRecruitInput(true);
    input.placeholder = 'Escribe tu pregunta...';
    showTyping();
    try {
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const payload  = new FormData();
      for (const [k, v] of Object.entries(recruitData)) payload.append(k, v);
      if (csrfMeta) payload.append('csrf_token', csrfMeta.content);
      const res  = await fetch('chatbot_apply.php', { method: 'POST', body: payload });
      const data = await res.json();
      hideTyping();
      if (data.type === 'success') {
        appendMsg(
          '¡Excelente, ' + recruitData.nombre + '! Has completado el primer filtro con éxito. ' +
          'Un reclutador de ' + cfg.empresa + ' se pondrá en contacto contigo a este número en las próximas 24 a 48 horas para una entrevista formal. ¡Ten un excelente día! 🎉',
          'bot'
        );
      } else {
        appendMsg('Hubo un problema al enviar tus datos. Por favor escríbenos a: ' + cfg.emailInfo, 'bot');
      }
    } catch {
      hideTyping();
      appendMsg('Error de conexión. Por favor escríbenos a: ' + cfg.emailInfo, 'bot');
    }
    recruitMode = false;
    renderSuggestions(['¿Qué servicios ofrecen?', '¿Zona de cobertura?', 'Contactar asesor']);
  }

  /* ── FAQ data ── */
  const faqs = [
    {
      keywords: ['servicio','ofrecen','hacen','realizan','trabajan','especialidad','catalogo','catálogo','lista'],
      response: 'En Sharclean ofrecemos:\n\n• Limpieza de Oficinas\n• Limpieza Industrial\n• Limpieza Comercial\n• Limpieza de Gimnasios\n• Limpieza Residencial\n• Lavado de Alfombras\n• Lavado Correctivo\n• Limpieza en Alturas\n• Pulido y Encerado\n\n¿Le gustaría más información sobre algún servicio?',
      suggestions: ['¿Cómo cotizo?','¿Cuál es su zona de cobertura?','¿Tienen contratos mensuales?'],
    },
    {
      keywords: ['oficina','escritorio','corporativo','limpieza general'],
      response: 'Nuestro servicio de limpieza de oficinas incluye:\n\n• Limpieza y desinfección de áreas de trabajo\n• Limpieza de baños y cocinas\n• Aspirado y mopeo de pisos\n• Recolección de basura\n• Sanitización de superficies de contacto\n\nAdaptamos las rutinas a su horario. ¿Desea una cotización?',
      suggestions: ['¿Cómo cotizo?','¿Tienen servicio diario?','¿Zona de cobertura?'],
    },
    {
      keywords: ['industrial','nave','planta','bodega','fabrica','fábrica'],
      response: 'Nuestro servicio de limpieza industrial incluye:\n\n• Limpieza profunda con equipos de alta presión\n• Aspirado industrial\n• Desengrasantes certificados\n• Manejo de residuos industriales\n\nAtendemos plantas, naves y bodegas de cualquier tamaño.',
      suggestions: ['¿Cómo cotizo?','¿Zona de cobertura?','¿Tienen contratos?'],
    },
    {
      keywords: ['comercial','tienda','plaza','local','negocio'],
      response: 'El servicio de limpieza comercial incluye:\n\n• Pisos y vitrinas\n• Baños y áreas comunes\n• Servicio continuo adaptado a su horario comercial\n• Personal uniformado y verificado\n\n¿Le gustaría una cotización gratuita?',
      suggestions: ['¿Cómo cotizo?','¿Tienen contratos?','¿Zona de cobertura?'],
    },
    {
      keywords: ['gimnasio','gym','vestidor','alberca','equipo deportivo'],
      response: 'Servicio especializado para gimnasios:\n\n• Desinfección de equipos de entrenamiento\n• Sanitización de vestidores y regaderas\n• Limpieza de albercas y áreas húmedas\n• Protocolos de higiene rigurosos\n\nGarantizamos ambientes seguros para sus usuarios.',
      suggestions: ['¿Cómo cotizo?','¿Tienen contratos?','Contactar asesor'],
    },
    {
      keywords: ['residencial','casa','departamento','hogar','condominio'],
      response: 'Nuestro servicio de limpieza residencial incluye:\n\n• Casas y departamentos\n• Condominios y edificios\n• Personal verificado con antecedentes\n• Productos seguros para su familia y mascotas\n\n¿Desea agendar una visita de cotización?',
      suggestions: ['¿Cómo cotizo?','¿Con qué frecuencia?','¿Zona de cobertura?'],
    },
    {
      keywords: ['vidrio','ventana','fachada','cristal','exterior','altura','rapel','alturas'],
      response: 'Servicio de limpieza en alturas:\n\n• Fachadas y ventanas exteriores\n• Personal certificado en trabajos verticales\n• Equipo de rappel y plataformas elevadoras\n• Productos especializados sin rayaduras\n\nAtendemos edificios de cualquier altura.',
      suggestions: ['¿Cuánto cuesta?','¿Cómo cotizo?','¿Zona de cobertura?'],
    },
    {
      keywords: ['alfombra','tapete','moqueta','mancha'],
      response: 'El servicio de lavado de alfombras incluye:\n\n• Extracción con agua caliente a alta presión\n• Eliminación de manchas difíciles\n• Desodorización profunda\n• Tratamiento anti-ácaros y bacterias\n• Secado rápido (2-4 horas)',
      suggestions: ['¿Cuánto tiempo tarda?','¿Cómo cotizo?','¿Trabajan en domicilio?'],
    },
    {
      keywords: ['piso','mármol','granito','loseta','pulido','pulir','cristalizado','brillo','madera','encerado','encerar'],
      response: 'El servicio de lavado correctivo y pulido incluye:\n\n• Pulido y cristalizado de mármol y granito\n• Encerado y mantenimiento de madera\n• Limpieza profunda de loseta\n• Remoción de manchas e incrustaciones\n• Aplicación de selladores protectores',
      suggestions: ['¿Cuánto tarda?','¿Cómo cotizo?','¿Qué máquinas usan?'],
    },
    {
      keywords: ['precio','costo','cuesta','cobran','tarifa','presupuesto','cotizar','cotización','cuánto','cuanto','gratis','gratuito'],
      response: 'Nuestras cotizaciones son completamente gratuitas y sin compromiso. Los precios dependen de:\n\n• Tipo de servicio requerido\n• Metros cuadrados del área\n• Frecuencia del servicio\n• Ubicación del inmueble\n\nPuede solicitar su cotización en el formulario de esta página.',
      suggestions: ['Ir al formulario de cotización','¿Tienen contratos mensuales?','¿Zona de cobertura?'],
    },
    {
      keywords: ['horario','hora','cuando','cuándo','atienden','disponible','días','semana','sábado','domingo'],
      response: 'Nuestro horario de atención es:\n\n• Lunes a Viernes: 8:00 am – 6:00 pm\n• Sábado: 9:00 am – 2:00 pm\n• Domingo: Cerrado (salvo emergencias)\n\nLos servicios pueden realizarse en el horario que mejor convenga a su empresa, incluyendo horario nocturno.',
      suggestions: ['¿Atienden emergencias?','¿Hacen servicio nocturno?','¿Cómo cotizo?'],
    },
    {
      keywords: ['zona','cobertura','ciudad','cdmx','edomex','guadalajara','monterrey','dónde','donde'],
      response: 'Actualmente ofrecemos servicios en:\n\n• Ciudad de México (todas las alcaldías)\n• Estado de México (Zona Metropolitana)\n• Guadalajara y Área Metropolitana\n• Monterrey y Área Metropolitana\n\nPara otras ciudades, consúltenos directamente.',
      suggestions: ['¿Cómo cotizo?','Contactar asesor'],
    },
    {
      keywords: ['contacto','llamar','teléfono','número','correo','email','whatsapp','asesor'],
      response: () => 'Puede contactarnos por:\n\n📧 Email: ' + cfg.emailInfo + '\n💬 WhatsApp disponible en esta página\n\nHorario de atención: Lun–Vie 8am–6pm | Sáb 9am–2pm\n\n¡Respondemos en menos de 2 horas!',
      suggestions: ['¿Cómo cotizo?','¿Cuál es su zona de cobertura?'],
    },
    {
      keywords: ['trabajo','empleo','vacante','postular','sueldo','salario','prestaciones'],
      response: () => '¡Nos alegra tu interés! En ' + cfg.empresa + ' ofrecemos:\n\n• Salario competitivo\n• Prestaciones de ley completas\n• Capacitación constante\n• Buen ambiente laboral\n\n¿Te gustaría postularte directamente aquí o prefieres ver las vacantes disponibles?',
      suggestions: ['Postularme ahora', 'Ver vacantes'],
    },
    {
      keywords: ['garantía','garantia','seguro','asegurado','certificación','calidad'],
      response: 'Ofrecemos total respaldo:\n\n✅ Garantía de satisfacción en cada servicio\n✅ Personal verificado con antecedentes\n✅ Seguro de responsabilidad civil\n✅ Supervisión de calidad\n✅ Revisión sin costo si no está satisfecho\n\nMás de 10 años construyendo confianza.',
      suggestions: ['¿Cómo cotizo?','¿Qué servicios ofrecen?'],
    },
    {
      keywords: ['emergencia','urgente','urgencia','rápido','inmediato','hoy','ahora','24'],
      response: () => '¡Sí, atendemos emergencias!\n\n📧 Email: ' + cfg.emailInfo + '\n\nEvaluamos cada caso y despachamos personal certificado en el menor tiempo posible.',
      suggestions: ['Contactar asesor','¿Cómo cotizo?'],
    },
    {
      keywords: ['contrato','mensual','quincenal','semanal','frecuencia','periódico','regular','recurrente'],
      response: 'Ofrecemos contratos con distintas frecuencias:\n\n• Servicio diario\n• Semanal\n• Quincenal\n• Mensual\n• Eventual (una sola vez)\n\nLos contratos incluyen supervisión, reportes y descuentos especiales.',
      suggestions: ['Solicitar cotización','Contactar asesor'],
    },
    {
      keywords: ['hola','buenos','buen','saludos','hi','hey'],
      response: '¡Hola! Bienvenido al chat de Sharclean. Estoy aquí para responder sus preguntas sobre nuestros servicios de limpieza y mantenimiento profesional. ¿En qué le puedo ayudar?',
      suggestions: ['¿Qué servicios ofrecen?','¿Cómo cotizo?','¿Zona de cobertura?','¿Cuáles son sus horarios?'],
    },
    {
      keywords: ['gracias','excelente','perfecto','muy bien','listo','entendido'],
      response: '¡Con mucho gusto! Si necesita más información o desea solicitar una cotización gratuita, no dude en preguntar. ¡Que tenga un excelente día!',
      suggestions: ['Solicitar cotización','Contactar asesor'],
    },
  ];

  const stopWords = new Set(['de','la','el','los','las','un','una','en','y','o','a','su','sus','me','se','te','le','mi','que','con','por','para','es','son','al']);

  function matchFaq(msg) {
    const lower = msg.toLowerCase();
    const words = lower.split(/[\s,;.!?]+/).filter(w => w && !stopWords.has(w));
    let best = null, bestScore = 0;
    for (const faq of faqs) {
      let score = 0;
      for (const kw of faq.keywords) {
        if (lower.includes(kw)) score += 2;
        for (const w of words) {
          if (w.includes(kw) || kw.includes(w)) score += 1;
        }
      }
      if (score > bestScore) { bestScore = score; best = faq; }
    }
    return bestScore >= 2 ? best : null;
  }

  function sendMessage(text) {
    const msg = (text || input.value).trim();
    if (!msg) return;

    input.value = '';

    if (recruitMode) { handleRecruitTextInput(msg); return; }

    if (msg === 'Postularme ahora') {
      appendMsg(msg, 'user');
      suggestionsEl.innerHTML = '';
      startRecruitment();
      return;
    }

    appendMsg(msg, 'user');
    suggestionsEl.innerHTML = '';
    showTyping();

    setTimeout(() => {
      hideTyping();
      const match = matchFaq(msg);
      if (match) {
        const resp = typeof match.response === 'function' ? match.response() : match.response;
        appendMsg(resp, 'bot');
        renderSuggestions(match.suggestions);
      } else {
        appendMsg('No encontré una respuesta específica para su pregunta. Para atenderle mejor:\n\n📧 ' + cfg.emailInfo + '\n\nNuestros asesores responden en menos de 2 horas.', 'bot');
        renderSuggestions(['¿Qué servicios ofrecen?','¿Cómo cotizo?','Contactar asesor']);
      }
    }, 600);
  }

  on(sendBtn, 'click', () => sendMessage());
  on(input, 'keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });

  // Initial suggestions
  renderSuggestions([
    '¿Qué servicios ofrecen?',
    '¿Cómo solicito una cotización?',
    '¿Cuál es su zona de cobertura?',
    '¿Trabajan con empresas?'
  ]);
}

/* ── Apply Form (vacantes.php) ─────────────────────────────── */
function initApplyForm() {
  const form = $('#applyForm');
  if (!form) return;

  on(form, 'submit', async (e) => {
    e.preventDefault();
    if (!validateForm(form)) return;

    const btn      = form.querySelector('[type="submit"]');
    const msg      = form.querySelector('.form-msg');
    const original = btn.innerHTML;
    btn.innerHTML  = '<svg class="spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Enviando...';
    btn.disabled   = true;

    try {
      const res  = await fetch('apply.php', { method: 'POST', body: new FormData(form) });
      const data = await res.json();
      showFormMsg(msg, data.type, data.msg);
      if (data.type === 'success') {
        form.reset();
        const fileLabel = form.querySelector('.file-input-label');
        if (fileLabel) fileLabel.innerHTML = '<span>Haz clic aquí</span> para subir tu CV (.pdf o .doc)';
      }
    } catch {
      showFormMsg(msg, 'error', 'Error de conexión. Intenta de nuevo.');
    } finally {
      btn.innerHTML = original;
      btn.disabled  = false;
    }
  });
}

/* ── Hero Carousel ─────────────────────────────────────────── */
function initHeroCarousel() {
  const bg = $('.hero__bg');
  if (!bg) return;
  const slides = $$('.hero__slide', bg);
  if (!slides.length) return;

  let current = 0;
  slides[0].classList.add('active');
  setTimeout(() => bg.classList.add('loaded'), 100);

  setInterval(() => {
    slides[current].classList.remove('active');
    current = (current + 1) % slides.length;
    slides[current].classList.add('active');
  }, 5000);
}

/* ── About Carousel ────────────────────────────────────────── */
function initAboutCarousel() {
  const imgs = $$('.about__carousel-img');
  if (!imgs.length) return;

  let current = 0;
  imgs[0].classList.add('active');

  setInterval(() => {
    imgs[current].classList.remove('active');
    current = (current + 1) % imgs.length;
    imgs[current].classList.add('active');
  }, 4000);
}

/* ── File input label update ──────────────────────────────── */
function initFileInputs() {
  $$('input[type="file"]').forEach(input => {
    on(input, 'change', () => {
      const label = input.closest('.file-input-wrap')?.querySelector('.file-input-label');
      if (label && input.files.length) {
        label.innerHTML = `<span>${input.files[0].name}</span>`;
      }
    });
  });
}

/* ── CSS spin animation for loading buttons ───────────────── */
function injectSpinStyle() {
  const style = document.createElement('style');
  style.textContent = `.spin { animation: spin 0.8s linear infinite; display: inline-block; }`;
  document.head.appendChild(style);
}

/* ── Legal Modals ──────────────────────────────────────────── */
function initLegalModals() {
  const modals = {
    'open-privacy': 'modal-privacy',
    'open-terms':   'modal-terms',
  };

  Object.entries(modals).forEach(([triggerId, modalId]) => {
    const trigger = $('#' + triggerId);
    const modal   = $('#' + modalId);
    if (!trigger || !modal) return;

    trigger.addEventListener('click', e => {
      e.preventDefault();
      modal.removeAttribute('hidden');
      document.body.style.overflow = 'hidden';
      modal.querySelector('.legalmodal__close, [data-close-modal]')?.focus();
    });
  });

  // Cerrar con cualquier elemento [data-close-modal] o con Escape
  document.addEventListener('click', e => {
    if (e.target.closest('[data-close-modal]')) {
      const modal = e.target.closest('.legalmodal');
      if (modal) closeModal(modal);
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      const open = $('.legalmodal:not([hidden])');
      if (open) closeModal(open);
    }
  });

  function closeModal(modal) {
    modal.setAttribute('hidden', '');
    document.body.style.overflow = '';
  }
}

/* ── Init All ──────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  injectSpinStyle();
  initNavbar();
  initSmoothScroll();
  initScrollReveal();
  initStatsCounter();
  initTestimonialsCarousel();
  initContactForm();
  initQuoteForm();
  initFieldValidation();
  initChatbot();
  initApplyForm();
  initHeroCarousel();
  initAboutCarousel();
  initFileInputs();
  initLegalModals();
});
