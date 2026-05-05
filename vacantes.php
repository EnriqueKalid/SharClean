<?php
require_once __DIR__ . '/db/csrf.php';
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Vacantes y oportunidades de empleo en Sharclean. Únete a nuestro equipo de limpieza y mantenimiento profesional en México.">
  <meta name="robots" content="index, follow">
  <meta property="og:title" content="Vacantes – Trabaja con Sharclean">
  <meta property="og:description" content="Únete al equipo de Sharclean. Ofrecemos salario competitivo, prestaciones de ley y un excelente ambiente de trabajo.">
  <title>Vacantes – Trabaja con Sharclean</title>
  <link rel="icon" href="images/favicon.ico" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/main.css">
</head>
<body>

<!-- ── NAVBAR ──────────────────────────────────────────────── -->
<nav class="navbar solid" role="navigation" aria-label="Navegación principal">
  <div class="container">
    <div class="navbar__inner">

      <a href="index.php" class="navbar__logo" aria-label="Sharclean inicio">
        <img src="images/logo.png" alt="Sharclean" class="navbar__logo-img">
      </a>      

      <div class="navbar__nav">
        <a href="index.php#inicio"     class="navbar__link">Inicio</a>
        <a href="index.php#servicios"  class="navbar__link">Servicios</a>
        <a href="index.php#nosotros"   class="navbar__link">Nosotros</a>
        <a href="index.php#cotizacion" class="navbar__link">Cotización</a>
        <a href="vacantes.php"         class="navbar__link active">Vacantes</a>
        <a href="index.php#contacto"   class="navbar__link">Contacto</a>
      </div>

      <div class="navbar__actions">
        <a href="index.php#cotizacion" class="btn btn-primary btn--sm navbar__cta">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.4 10.72 19.79 19.79 0 01.34 2.1 2 2 0 012.33 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
          Solicitar servicio
        </a>
        <button class="navbar__hamburger" aria-label="Abrir menú" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
      </div>

    </div>
  </div>
</nav>

<div class="navbar__mobile-menu">
  <a href="index.php#inicio"     class="navbar__mobile-link">Inicio</a>
  <a href="index.php#servicios"  class="navbar__mobile-link">Servicios</a>
  <a href="index.php#nosotros"   class="navbar__mobile-link">Nosotros</a>
  <a href="index.php#cotizacion" class="navbar__mobile-link">Cotización</a>
  <a href="vacantes.php"         class="navbar__mobile-link">Vacantes</a>
  <a href="index.php#contacto"   class="navbar__mobile-link">Contacto</a>
  <a href="index.php#cotizacion" class="btn btn-primary btn--full navbar__mobile-cta">Solicitar servicio</a>
</div>


<!-- ── PAGE HERO ───────────────────────────────────────────── -->
<section class="page-hero">
  <div class="container page-hero__content">
    <span class="section-badge" style="background:rgba(255,255,255,0.15);color:rgba(255,255,255,0.90);">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Oportunidades de Empleo
    </span>
    <h1 class="page-hero__title">Únete al Equipo <span style="color:var(--primary-light);">Sharclean</span></h1>
    <p class="page-hero__subtitle">Somos una empresa en crecimiento que valora a su personal. Salario competitivo, prestaciones de ley y un excelente ambiente de trabajo.</p>
  </div>
</section>


<!-- ── VACANTES GRID ───────────────────────────────────────── -->
<section class="section" style="background:var(--gradient-section);">
  <div class="container">

    <div class="text-center reveal" style="margin-bottom:1rem;">
      <span class="section-badge">Puestos Disponibles</span>
      <h2 class="section-title">Vacantes <span>Actuales</span></h2>
      <p class="section-subtitle">Selecciona el puesto que más se adapte a tu perfil y aplica directamente por WhatsApp o mediante nuestro formulario.</p>
    </div>

    <div class="vacantes-grid">

      <!-- Afanador – CDMX -->
      <article class="vacante-card reveal reveal-delay-1">
        <div class="vacante-card__header">
          <h3 class="vacante-card__title">Afanador</h3>
          <span class="vacante-card__badge">Activa</span>
        </div>
        <div class="vacante-card__meta">
          <span class="vacante-card__meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
            Operaciones
          </span>
          <span class="vacante-card__meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Tiempo completo
          </span>
          <span class="vacante-card__meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            CDMX
          </span>
        </div>
        <p class="vacante-card__reqs">
          <strong>Requisitos:</strong> No se requiere experiencia previa (capacitamos). Secundaria concluida mínimo. Actitud de servicio, responsabilidad y puntualidad. Disponibilidad para trabajar en distintos turnos y ubicaciones dentro de la Ciudad de México. Prestaciones de ley desde el primer día.
        </p>
        <div class="vacante-card__footer">
          <a href="https://wa.me/5215500000000?text=Hola%20Sharclean%2C%20me%20interesa%20la%20vacante%20de%20Afanador%20en%20CDMX.%20Adjunto%20mi%20informaci%C3%B3n."
             class="btn btn-green"
             target="_blank" rel="noopener noreferrer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Aplicar por WhatsApp
          </a>
        </div>
      </article>

      <!-- Afanador – EDOMEX -->
      <article class="vacante-card reveal reveal-delay-2">
        <div class="vacante-card__header">
          <h3 class="vacante-card__title">Afanador</h3>
          <span class="vacante-card__badge">Activa</span>
        </div>
        <div class="vacante-card__meta">
          <span class="vacante-card__meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
            Operaciones
          </span>
          <span class="vacante-card__meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Tiempo completo
          </span>
          <span class="vacante-card__meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Estado de México
          </span>
        </div>
        <p class="vacante-card__reqs">
          <strong>Requisitos:</strong> No se requiere experiencia previa (capacitamos). Secundaria concluida mínimo. Actitud de servicio, responsabilidad y puntualidad. Disponibilidad para trabajar en distintos turnos y ubicaciones dentro del Estado de México. Prestaciones de ley desde el primer día.
        </p>
        <div class="vacante-card__footer">
          <a href="https://wa.me/5215500000000?text=Hola%20Sharclean%2C%20me%20interesa%20la%20vacante%20de%20Afanador%20en%20Estado%20de%20M%C3%A9xico.%20Adjunto%20mi%20informaci%C3%B3n."
             class="btn btn-green"
             target="_blank" rel="noopener noreferrer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Aplicar por WhatsApp
          </a>
        </div>
      </article>

      <!-- Jardinero -->
      <article class="vacante-card reveal reveal-delay-3">
        <div class="vacante-card__header">
          <h3 class="vacante-card__title">Jardinero</h3>
          <span class="vacante-card__badge">Activa</span>
        </div>
        <div class="vacante-card__meta">
          <span class="vacante-card__meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
            Operaciones
          </span>
          <span class="vacante-card__meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Tiempo completo
          </span>
          <span class="vacante-card__meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            CDMX / EDOMEX
          </span>
        </div>
        <p class="vacante-card__reqs">
          <strong>Requisitos:</strong> Experiencia mínima de 1 año en jardinería general (poda, riego, mantenimiento de áreas verdes). Conocimiento básico de plantas y herramientas de jardinería. Disponibilidad de horario. Responsable, puntual y con actitud de servicio. Prestaciones de ley desde el primer día.
        </p>
        <div class="vacante-card__footer">
          <a href="https://wa.me/5215500000000?text=Hola%20Sharclean%2C%20me%20interesa%20la%20vacante%20de%20Jardinero.%20Adjunto%20mi%20informaci%C3%B3n."
             class="btn btn-green"
             target="_blank" rel="noopener noreferrer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Aplicar por WhatsApp
          </a>
        </div>
      </article>

    </div>
  </div>
</section>


<!-- ── APPLICATION FORM ─────────────────────────────────────── -->
<section class="apply-form-section section">
  <div class="container">

    <div class="text-center reveal" style="margin-bottom:2.5rem;">
      <span class="section-badge">Formulario de Aplicación</span>
      <h2 class="section-title">Envía tu <span>Solicitud</span></h2>
      <p class="section-subtitle">Completa el formulario y nos pondremos en contacto contigo para una entrevista. ¡Tu próximo trabajo está aquí!</p>
    </div>

    <div class="apply-form reveal">
      <form id="applyForm" novalidate enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-grid">

          <div class="form-group">
            <label class="form-label form-label--light" for="apply-name">Nombre completo *</label>
            <input class="form-input form-input--light" type="text" id="apply-name" name="apply-name" placeholder="Tu nombre completo" required autocomplete="name">
          </div>

          <div class="form-group">
            <label class="form-label form-label--light" for="apply-age">Edad *</label>
            <input class="form-input form-input--light" type="number" id="apply-age" name="apply-age" placeholder="Tu edad" min="18" max="65" required>
          </div>

          <div class="form-group">
            <label class="form-label form-label--light" for="apply-phone">Teléfono *</label>
            <input class="form-input form-input--light" type="tel" id="apply-phone" name="apply-phone" placeholder="+52 55 0000 0000" required autocomplete="tel">
          </div>

          <div class="form-group">
            <label class="form-label form-label--light" for="apply-email">Correo electrónico *</label>
            <input class="form-input form-input--light" type="email" id="apply-email" name="apply-email" placeholder="tucorreo@ejemplo.com" required autocomplete="email">
          </div>

          <div class="form-group form-group--full">
            <label class="form-label form-label--light" for="apply-position">Puesto de interés *</label>
            <select class="form-input form-input--light" id="apply-position" name="apply-position" required style="background:var(--light);border:1.5px solid rgba(15,23,42,0.10);color:var(--dark);padding:0.8rem 1rem;border-radius:var(--radius-md);">
              <option value="">— Seleccione un puesto —</option>
              <option value="Supervisor de Limpieza">Supervisor de Limpieza</option>
              <option value="Técnico en Mantenimiento">Técnico en Mantenimiento</option>
              <option value="Operador de Máquinas de Limpieza">Operador de Máquinas de Limpieza</option>
              <option value="Auxiliar de Limpieza">Auxiliar de Limpieza</option>
              <option value="Auxiliar Administrativo">Auxiliar Administrativo</option>
              <option value="Otro">Otro (especificar en experiencia)</option>
            </select>
          </div>

          <div class="form-group form-group--full">
            <label class="form-label form-label--light" for="apply-experience">Experiencia previa</label>
            <textarea class="form-textarea form-input--light" id="apply-experience" name="apply-experience" rows="4" placeholder="Describe tu experiencia laboral relevante. Si no tienes experiencia, cuéntanos por qué te interesa trabajar con nosotros..." style="background:var(--light);border:1.5px solid rgba(15,23,42,0.10);color:var(--dark);"></textarea>
          </div>

          <div class="form-group form-group--full">
            <label class="form-label form-label--light">Currículum Vitae (opcional)</label>
            <div class="file-input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
              <span class="file-input-label"><span>Haz clic aquí</span> para subir tu CV (.pdf o .doc)</span>
              <input type="file" name="apply-cv" id="apply-cv" accept=".pdf,.doc,.docx">
            </div>
          </div>

          <div class="form-group form-group--full">
            <button type="submit" class="btn btn-primary btn--lg btn--full">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Enviar mi solicitud
            </button>
            <p style="font-size:0.78rem;color:var(--gray);text-align:center;margin-top:0.75rem;">
              Tu información es tratada con total confidencialidad.
            </p>
            <div class="form-msg"></div>
          </div>

        </div>
      </form>
    </div>
  </div>
</section>


<!-- ── WHATSAPP CTA ─────────────────────────────────────────── -->
<section class="wa-cta section">
  <div class="container wa-cta__inner">
    <div class="wa-cta__icon">
      <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
      </svg>
    </div>
    <h2 class="wa-cta__title">¿Prefieres contactarnos directamente?</h2>
    <p class="wa-cta__text">Envíanos un WhatsApp con tu nombre y el puesto que te interesa. Te respondemos de inmediato.</p>
    <a href="https://wa.me/5215500000000?text=Hola%20Sharclean%2C%20me%20interesa%20trabajar%20con%20ustedes.%20Mi%20nombre%20es%20"
       class="btn btn--lg" style="background:white;color:#075E54;font-weight:700;"
       target="_blank" rel="noopener noreferrer">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="color:#25D366;">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
      </svg>
      Enviar WhatsApp ahora
    </a>
    <p style="color:rgba(255,255,255,0.60);font-size:0.85rem;margin-top:1rem;">+52 55 0000 0000 · informacion@sharclean.com.mx</p>
  </div>
</section>


<!-- ── FOOTER ───────────────────────────────────────────────── -->
<footer class="footer" role="contentinfo">
  <div class="container">
    <div class="footer__grid">

      <div class="footer__brand">
        <div class="footer__logo">
            <img src="images/logo.png" alt="Sharclean" class="navbar__logo-img">                    
        </div>
        <p class="footer__tagline">Transformamos y conservamos sus espacios con los más altos estándares de limpieza y mantenimiento profesional en México.</p>
        <div class="footer__social">
          <a href="#" class="footer__social-link" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
          </a>
          <a href="#" class="footer__social-link" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
          </a>
          <a href="https://wa.me/5215500000000" class="footer__social-link" aria-label="WhatsApp" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </a>
          <a href="mailto:informacion@sharclean.com.mx" class="footer__social-link" aria-label="Email">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </a>
        </div>
      </div>

      <div>
        <h4 class="footer__col-title">Servicios</h4>
        <ul class="footer__links">
          <li><a href="index.php#servicios" class="footer__link">Limpieza de Oficinas</a></li>
          <li><a href="index.php#servicios" class="footer__link">Limpieza Industrial</a></li>
          <li><a href="index.php#servicios" class="footer__link">Limpieza Comercial</a></li>
          <li><a href="index.php#servicios" class="footer__link">Limpieza de Gimnasios</a></li>
          <li><a href="index.php#servicios" class="footer__link">Limpieza Residencial</a></li>
          <li><a href="index.php#servicios" class="footer__link">Lavado de Alfombras</a></li>
          <li><a href="index.php#servicios" class="footer__link">Lavado Correctivo</a></li>
          <li><a href="index.php#servicios" class="footer__link">Limpieza en Alturas</a></li>
          <li><a href="index.php#servicios" class="footer__link">Pulido y Encerado</a></li>
        </ul>
      </div>

      <div>
        <h4 class="footer__col-title">Empresa</h4>
        <ul class="footer__links">
          <li><a href="index.php#nosotros" class="footer__link">Quiénes somos</a></li>
          <li><a href="index.php#cotizacion" class="footer__link">Solicitar cotización</a></li>
          <li><a href="vacantes.php" class="footer__link">Vacantes</a></li>
          <li><a href="index.php#contacto" class="footer__link">Contacto</a></li>
        </ul>
      </div>

      <div>
        <h4 class="footer__col-title">Contacto</h4>
        <ul class="footer__links">
          <li><a href="mailto:informacion@sharclean.com.mx" class="footer__link">informacion@sharclean.com.mx</a></li>
          <li><a href="tel:5589454415" class="footer__link">+52 55-8945-4415</a></li>
          <li><a href="https://wa.me/5215500000000" class="footer__link" target="_blank" rel="noopener noreferrer">WhatsApp</a></li>          
        </ul>
      </div>

    </div>

    <div class="footer__bottom">
      <p class="footer__copy">&copy; <?php echo date('Y'); ?> Sharclean. Todos los derechos reservados.</p> <a href="http://www.sisia.com.mx" target="_blank">SISIA</a></p>
      <div class="footer__bottom-links">
        <a href="#" class="footer__bottom-link" id="open-privacy">Aviso de privacidad</a>
        <a href="#" class="footer__bottom-link" id="open-terms">Términos de servicio</a>
      </div>
    </div>
  </div>
</footer>

<?php include __DIR__ . '/partials/legal-modals.php'; ?>

<!-- WhatsApp Float -->
<div class="whatsapp-float">
  <div class="whatsapp-float__pulse"></div>
  <a href="https://wa.me/5215500000000?text=Hola%20Sharclean%2C%20me%20gustar%C3%ADa%20obtener%20m%C3%A1s%20informaci%C3%B3n."
     class="whatsapp-float__btn" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
  </a>
  <span class="whatsapp-float__tooltip">Contáctanos</span>
</div>

<script src="js/main.js"></script>
</body>
</html>
