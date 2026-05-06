<?php
require_once __DIR__ . '/db/csrf.php';
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sharclean – Servicios profesionales de limpieza, mantenimiento y conservación de inmuebles en México. Limpieza de oficinas, vidrios, alfombras, fumigación, plomería e instalaciones eléctricas.">
  <meta name="keywords" content="limpieza profesional, mantenimiento de inmuebles, limpieza de oficinas, lavado de alfombras, limpieza de vidrios, fumigación, plomería, instalaciones eléctricas, México">
  <meta name="author" content="Sharclean">
  <meta name="robots" content="index, follow">
  <meta property="og:title" content="Sharclean – Limpieza y Mantenimiento Profesional">
  <meta property="og:description" content="Transformamos y conservamos sus espacios con los más altos estándares de calidad. Más de 10 años de experiencia en México.">
  <meta property="og:image" content="images/banner.jpg">
  <meta property="og:type" content="website">
  <meta name="theme-color" content="#0284C7">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <title>Sharclean – Limpieza y Mantenimiento Profesional en México</title>
  <link rel="icon" href="images/favicon.ico" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/main.css">
</head>
<body>

<!-- ══════════════════════════════════════════════════════════
     NAVBAR
══════════════════════════════════════════════════════════ -->
<nav class="navbar transparent" role="navigation" aria-label="Navegación principal">
  <div class="container">
    <div class="navbar__inner">

      <a href="#inicio" class="navbar__logo" aria-label="Sharclean inicio">
        <img src="images/logo.png" alt="Sharclean" class="navbar__logo-img">
      </a>

      <div class="navbar__nav" role="menubar">
        <a href="#inicio"     class="navbar__link active" role="menuitem">Inicio</a>
        <a href="#servicios"  class="navbar__link" role="menuitem">Servicios</a>
        <a href="#nosotros"   class="navbar__link" role="menuitem">Nosotros</a>
        <a href="#cotizacion" class="navbar__link" role="menuitem">Cotización</a>
        <a href="#vacantes"   class="navbar__link" role="menuitem">Vacantes</a>
        <a href="#contacto"   class="navbar__link" role="menuitem">Contacto</a>
      </div>

      <div class="navbar__actions">
        <a href="#cotizacion" class="btn btn-primary btn--sm navbar__cta">
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

<!-- Mobile Menu -->
<div class="navbar__mobile-menu" role="menu" aria-label="Menú móvil">
  <a href="#inicio"     class="navbar__mobile-link" role="menuitem">Inicio</a>
  <a href="#servicios"  class="navbar__mobile-link" role="menuitem">Servicios</a>
  <a href="#nosotros"   class="navbar__mobile-link" role="menuitem">Nosotros</a>
  <a href="#cotizacion" class="navbar__mobile-link" role="menuitem">Cotización</a>
  <a href="#vacantes"   class="navbar__mobile-link" role="menuitem">Vacantes</a>
  <a href="#contacto"   class="navbar__mobile-link" role="menuitem">Contacto</a>
  <a href="#cotizacion" class="btn btn-primary btn--full navbar__mobile-cta">Solicitar servicio</a>
</div>


<!-- ══════════════════════════════════════════════════════════
     HERO
══════════════════════════════════════════════════════════ -->
<section id="inicio" class="hero">
  <div class="hero__bg" role="img" aria-label="Equipo de limpieza profesional Sharclean">
    <div class="hero__slide" style="background-image:url('images/servicio1_limpieza.jpg')"></div>
    <div class="hero__slide" style="background-image:url('images/servicio2_pulido.jpg')"></div>
    <div class="hero__slide" style="background-image:url('images/servicio3_ventana.jpg')"></div>
    <div class="hero__slide" style="background-image:url('images/servicio4_alfombra.jpg')"></div>
    <div class="hero__slide" style="background-image:url('images/servicio5_superficies.jpg')"></div>
    <div class="hero__slide" style="background-image:url('images/servicios7-jardineria.jpg')"></div>
  </div>
  <div class="hero__overlay" aria-hidden="true"></div>

  <div class="container">
    <div class="hero__content">
      <div class="hero__badge">
        <span class="hero__badge-dot"></span>
        Servicio especializado en limpieza.
      </div>

      <h1 class="hero__title">
        La solución ideal a cada<br>
        <span>necesidad de limpieza</span>
      </h1>

      <p class="hero__subtitle">
        Ofrecemos soluciones integrales en limpieza para empresas e inmuebles. Calidad certificada REPSE, personal capacitado y resultados que superan expectativas.
      </p>

      <div class="hero__cta">
        <a href="#cotizacion" class="btn btn-primary btn--lg">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
          Cotizar ahora
        </a>
        <a href="#servicios" class="btn btn-secondary btn--lg">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Conocer servicios
        </a>
      </div>

      <div class="hero__stats">
        <div>
          <div class="hero__stat-value" data-count="130" data-prefix="+">+130</div>
          <div class="hero__stat-label">Clientes activos</div>
        </div>
        <div>
          <div class="hero__stat-value" data-count="10" data-prefix="+">+10</div>
          <div class="hero__stat-label">Años de experiencia</div>
        </div>
        <div>
          <div class="hero__stat-value" data-count="98" data-suffix="%">98%</div>
          <div class="hero__stat-label">Índice de satisfacción</div>
        </div>
        <div>
          <div class="hero__stat-value" data-count="250" data-prefix="+">+250</div>
          <div class="hero__stat-label">Empleados certificados</div>
        </div>
      </div>
    </div>
    <img src="images/Repse.png" alt="Certificación REPSE – Sharclean" class="hero__repse-img">
  </div>

  <a href="#servicios" class="hero__scroll" aria-label="Ver servicios">
    <span>Explorar</span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
  </a>
</section>


<!-- ══════════════════════════════════════════════════════════
     SERVICES
══════════════════════════════════════════════════════════ -->
<section id="servicios" class="services section">
  <div class="container">
    <div class="text-center reveal">
      <span class="section-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Nuestros Servicios
      </span>
      <h2 class="section-title">Servicio <span>Especializado</span> en limpieza</h2>
      <p class="section-subtitle">Desde la limpieza diaria hasta la limpieza profunda, cubrimos todas las necesidades de su inmueble con profesionalismo y tecnología de punta.</p>
    </div>

    <!-- ── Por Contrato ── -->
    <div class="services__ribbon reveal">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span class="services__ribbon-text">Por Contrato</span>
    </div>
    <div class="services__grid">

      <!-- Limpieza de Oficinas -->
      <article class="service-card reveal reveal-delay-1">
        <div class="service-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building-icon lucide-building">
            <path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M12 6h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M16 6h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/><path d="M8 6h.01"/><path d="M9 22v-3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/><rect x="4" y="2" width="16" height="20" rx="2"/>
          </svg>
        </div>
        <h3 class="service-card__title">Limpieza de Oficinas</h3>
        <p class="service-card__text">Asignación de personal capacitado para dar mantenimiento a sus espacios de trabajo impecables con rutinas de limpieza profesional y organización adaptadas a sus horarios.</p>
        <a href="#cotizacion" class="service-card__link">
          Solicitar servicio
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </article>

      <!-- Limpieza Industrial -->
      <article class="service-card reveal reveal-delay-2">
        <div class="service-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-factory-icon lucide-factory"><path d="M12 16h.01"/><path d="M16 16h.01"/><path d="M3 19a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.5a.5.5 0 0 0-.769-.422l-4.462 2.844A.5.5 0 0 1 15 10.5v-2a.5.5 0 0 0-.769-.422L9.77 10.922A.5.5 0 0 1 9 10.5V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"/><path d="M8 16h.01"/>
          </svg>          
        </div>
        <h3 class="service-card__title">Limpieza <br>Industrial</h3>
        <p class="service-card__text">Asignación de personal calificado para realizar limpieza profunda de plantas, naves industriales y bodegas con equipos de alta presión, aspirado industrial y productos químicos certificados.</p>
        <a href="#cotizacion" class="service-card__link">
          Solicitar servicio
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </article>

      <!-- Limpieza Comercial -->
      <article class="service-card reveal reveal-delay-3">
        <div class="service-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-store-icon lucide-store">
            <path d="M15 21v-5a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v5"/><path d="M17.774 10.31a1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.451 0 1.12 1.12 0 0 0-1.548 0 2.5 2.5 0 0 1-3.452 0 1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.77-3.248l2.889-4.184A2 2 0 0 1 7 2h10a2 2 0 0 1 1.653.873l2.895 4.192a2.5 2.5 0 0 1-3.774 3.244"/><path d="M4 10.95V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8.05"/>
          </svg>
        </div>
        <h3 class="service-card__title">Limpieza Comercial</h3>
        <p class="service-card__text">Asignación de personal dedicado para brindar servicio continuo a tiendas, plazas y centros comerciales. Pisos, vitrinas, baños y áreas comunes siempre presentables para sus clientes.</p>
        <a href="#cotizacion" class="service-card__link">
          Solicitar servicio
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </article>

      <!-- Limpieza Residencial -->
      <article class="service-card reveal reveal-delay-2">
        <div class="service-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trees-icon lucide-trees"><path d="M10 10v.2A3 3 0 0 1 8.9 16H5a3 3 0 0 1-1-5.8V10a3 3 0 0 1 6 0Z"/><path d="M7 16v6"/><path d="M13 19v3"/><path d="M12 19h8.3a1 1 0 0 0 .7-1.7L18 14h.3a1 1 0 0 0 .7-1.7L16 9h.2a1 1 0 0 0 .8-1.7L13 3l-1.4 1.5"/>
          </svg>                  
        </div>
        <h3 class="service-card__title">Limpieza Residencial</h3>
        <p class="service-card__text">Asignación de personal capacitado para cuidar las áreas comunes de su condominio con el mismo profesionalismo que a una empresa. Limpieza de jardines, canchas, pasillos y salones con personal de confianza y verificado.</p>
        <a href="#cotizacion" class="service-card__link">
          Solicitar servicio
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </article>

      <!-- Limpieza de Gimnasios -->
      <article class="service-card reveal reveal-delay-1">
        <div class="service-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-dumbbell-icon lucide-dumbbell">
            <path d="M17.596 12.768a2 2 0 1 0 2.829-2.829l-1.768-1.767a2 2 0 0 0 2.828-2.829l-2.828-2.828a2 2 0 0 0-2.829 2.828l-1.767-1.768a2 2 0 1 0-2.829 2.829z"/><path d="m2.5 21.5 1.4-1.4"/><path d="m20.1 3.9 1.4-1.4"/><path d="M5.343 21.485a2 2 0 1 0 2.829-2.828l1.767 1.768a2 2 0 1 0 2.829-2.829l-6.364-6.364a2 2 0 1 0-2.829 2.829l1.768 1.767a2 2 0 0 0-2.828 2.829z"/><path d="m9.6 14.4 4.8-4.8"/>
          </svg>          
        </div>
        <h3 class="service-card__title">Limpieza de Gimnasios</h3>
        <p class="service-card__text">Asignación de personal certificado para limpiar y cuidar equipos, vestidores, albercas y áreas de entrenamiento con protocolos de higiene rigurosos para la salud de sus usuarios.</p>
        <a href="#cotizacion" class="service-card__link">
          Solicitar servicio
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </article>

    </div>

    <!-- ── Por Evento ── -->
    <div class="services__ribbon reveal">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span class="services__ribbon-text">Por Evento</span>
    </div>
    <div class="services__grid">

      <!-- Lavado de Alfombras -->
      <article class="service-card reveal reveal-delay-1">
        <div class="service-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shell-icon lucide-shell">
            <path d="M14 11a2 2 0 1 1-4 0 4 4 0 0 1 8 0 6 6 0 0 1-12 0 8 8 0 0 1 16 0 10 10 0 1 1-20 0 11.93 11.93 0 0 1 2.42-7.22 2 2 0 1 1 3.16 2.44"/>
          </svg>
        </div>
        <h3 class="service-card__title">Lavado de Alfombras</h3>
        <p class="service-card__text">Eliminamos manchas, bacterias y ácaros con equipos de inyección/succión con productos especializados que prolongan la vida de sus alfombras.</p>
        <a href="#cotizacion" class="service-card__link">
          Solicitar servicio
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </article>

      <!-- Lavado Correctivo -->
      <article class="service-card reveal reveal-delay-2">
        <div class="service-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bubbles-icon lucide-bubbles">
            <path d="M7.001 15.085A1.5 1.5 0 0 1 9 16.5"/><circle cx="18.5" cy="8.5" r="3.5"/><circle cx="7.5" cy="16.5" r="5.5"/><circle cx="7.5" cy="4.5" r="2.5"/>
          </svg>
        </div>
        <h3 class="service-card__title">Lavado <br>Correctivo</h3>
        <p class="service-card__text">Lavado profundo de grandes superficies de concreto, mármol y loseta. Removemos incrustaciones y devolvemos el aspecto original con maquinaria industrial especializada.</p>
        <a href="#cotizacion" class="service-card__link">
          Solicitar servicio
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </article>

      <!-- Limpieza en Alturas -->
      <article class="service-card reveal reveal-delay-3">
        <div class="service-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2-icon lucide-building-2">
            <path d="M10 12h4"/><path d="M10 8h4"/><path d="M14 21v-3a2 2 0 0 0-4 0v3"/><path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"/><path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/>
          </svg>
        </div>
        <h3 class="service-card__title">Limpieza en Alturas</h3>
        <p class="service-card__text">Limpieza de fachadas, vidrios y estructuras exteriores en altura con rapelistas certificados y equipo de elevación, asegurando resultados sin riesgo.</p>
        <a href="#cotizacion" class="service-card__link">
          Solicitar servicio
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </article>

      <!-- Pulido y Encerado -->
      <article class="service-card reveal reveal-delay-1">
        <div class="service-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-brush-cleaning-icon lucide-brush-cleaning">
            <path d="m16 22-1-4"/><path d="M19 14a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2h-3a1 1 0 0 1-1-1V4a2 2 0 0 0-4 0v5a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2v1a1 1 0 0 0 1 1"/><path d="M19 14H5l-1.973 6.767A1 1 0 0 0 4 22h16a1 1 0 0 0 .973-1.233z"/><path d="m8 22 1-4"/>
          </svg>
        </div>
        <h3 class="service-card__title">Pulido & Encerado <br>de Pisos</h3>
        <p class="service-card__text">Cristalización, pulido y encerado de pisos para restaurar el brillo y proteger la superficie. Resultados visibles desde la primera sesión.</p>
        <a href="#cotizacion" class="service-card__link">
          Solicitar servicio
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </article>

      <!-- Eventos Sociales -->
      <article class="service-card reveal reveal-delay-1">
        <div class="service-card__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-brush-cleaning-icon lucide-brush-cleaning">
            <path d="m16 22-1-4"/><path d="M19 14a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2h-3a1 1 0 0 1-1-1V4a2 2 0 0 0-4 0v5a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2v1a1 1 0 0 0 1 1"/><path d="M19 14H5l-1.973 6.767A1 1 0 0 0 4 22h16a1 1 0 0 0 .973-1.233z"/><path d="m8 22 1-4"/>
          </svg>
        </div>
        <h3 class="service-card__title">Eventos <br>Sociales</h3>
        <p class="service-card__text">Asignación de personal eventual para atender tus espacios durante eventos sociales, culturales y de entretenimiento.</p>
        <a href="#cotizacion" class="service-card__link">
          Solicitar servicio
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </article>

    </div>
  </div>
</section>


<!-- ══════════════════════════════════════════════════════════
     ABOUT
══════════════════════════════════════════════════════════ -->
<section id="nosotros" class="about section">
  <div class="container">
    <div class="about__grid">

      <div class="about__image-wrap reveal-left">
        <div class="about__carousel">
          <img src="images/Elevador.jpeg"  alt="Elevador Sharclean"       class="about__carousel-img" loading="lazy">
          <img src="images/Equipo.jpeg"    alt="Equipo Sharclean"         class="about__carousel-img" loading="lazy">
          <img src="images/Nosotros.jpeg"  alt="Nosotros Sharclean"       class="about__carousel-img" loading="lazy">
          <img src="images/Personal.jpeg"   alt="Personal Sharclean"       class="about__carousel-img" loading="lazy">
          <img src="images/Pulidora.jpeg"  alt="Pulidora Sharclean"       class="about__carousel-img" loading="lazy">
          <img src="images/Seguridad.jpeg" alt="Seguridad Sharclean"      class="about__carousel-img" loading="lazy">
          <img src="images/Supervisor.jpeg" alt="Supervisor Sharclean"      class="about__carousel-img" loading="lazy">

        </div>
        <div class="about__image-badge">
          <div class="about__image-badge-value">10+</div>
          <div class="about__image-badge-label">Años de confianza</div>
        </div>
      </div>

      <div class="about__text reveal-right">
        <span class="section-badge">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
          Quiénes somos
        </span>
        <h2 class="section-title">Expertos en <span>Limpieza y Mantenimiento</span> Profesional</h2>
        <p style="color: var(--gray); line-height: 1.8; margin-bottom: 1.5rem;">
          Sharclean es una empresa mexicana con más de 10 años de experiencia especializada en el servicio profesional de limpieza integral a inmuebles. Trabajamos con los más altos estándares de calidad, utilizando tecnología de punta y personal altamente capacitado. Contamos con acreditacion REPSE.        </p>
        <p style="color: var(--gray); line-height: 1.8; margin-bottom: 2rem;">
          Nuestro compromiso es ofrecer soluciones confiables, seguras y eficientes que ayuden a nuestros clientes a mantener sus espacios en óptimas condiciones, generando ambientes de trabajo saludables y productivos.
        </p>

        <div class="about__stats">
          <div class="about__stat">
            <div class="about__stat-value" data-count="130" data-prefix="+">+130</div>
            <div class="about__stat-label">Clientes activos</div>
          </div>
          <div class="about__stat">
            <div class="about__stat-value" data-count="10" data-prefix="+">10+</div>
            <div class="about__stat-label">Años de experiencia</div>
          </div>
          <div class="about__stat">
            <div class="about__stat-value" data-count="98" data-suffix="%">98%</div>
            <div class="about__stat-label">Satisfacción</div>
          </div>
          <div class="about__stat">
            <div class="about__stat-value" data-count="250" data-prefix="+">+250</div>
            <div class="about__stat-label">Empleados</div>
          </div>
        </div>

        <div class="about__features">
          <div class="about__feature">
            <div class="about__feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span class="about__feature-text">Personal certificado y con verificación de antecedentes</span>
          </div>
          <div class="about__feature">
            <div class="about__feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span class="about__feature-text">Productos ecológicos y biodegradables de alta eficacia</span>
          </div>
          <div class="about__feature">
            <div class="about__feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span class="about__feature-text">Equipo y maquinaria aptos para limpieza industrial de última generación</span>
          </div>
          <div class="about__feature">
            <div class="about__feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span class="about__feature-text">Cobertura en CDMX, EDOMEX, San Luis Potosí, Querétaro, Puebla, Morelos, Toluca, Veracruz y Pachuca</span>
          </div>
          <div class="about__feature">
            <div class="about__feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span class="about__feature-text">Cumplimiento legal y certificación REPSE</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ══════════════════════════════════════════════════════════
     QUOTE FORM
══════════════════════════════════════════════════════════ -->
<section id="cotizacion" class="quote section--lg">
  <div class="container quote__inner">

    <div class="quote__header reveal">
      <span class="section-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Cotización Gratuita
      </span>
      <h2 class="section-title">Solicite su Cotización <span>Sin Costo</span></h2>
      <p class="section-subtitle">Complete el formulario y un asesor le contactará en menos de 24 horas con una propuesta personalizada para sus necesidades.</p>
    </div>

    <div class="quote__form reveal">
      <form id="quoteForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-grid">

          <div class="form-group">
            <label class="form-label" for="quote-name">Nombre completo *</label>
            <input class="form-input" type="text" id="quote-name" name="quote-name" placeholder="Ej. Juan Pérez García" required autocomplete="name">
          </div>

          <div class="form-group">
            <label class="form-label" for="quote-company">Empresa / Organización</label>
            <input class="form-input" type="text" id="quote-company" name="quote-company" placeholder="Nombre de su empresa" autocomplete="organization">
          </div>

          <div class="form-group">
            <label class="form-label" for="quote-phone">Teléfono *</label>
            <input class="form-input" type="tel" id="quote-phone" name="quote-phone" placeholder="+52 55 0000 0000" required autocomplete="tel">
          </div>

          <div class="form-group">
            <label class="form-label" for="quote-email">Correo electrónico *</label>
            <input class="form-input" type="email" id="quote-email" name="quote-email" placeholder="correo@empresa.com" required autocomplete="email">
          </div>

          <div class="form-group form-group--full">
            <label class="form-label" for="quote-service">Tipo de servicio requerido *</label>
            <select class="form-select" id="quote-service" name="quote-service" required>
              <option value="">— Seleccione un servicio —</option>
              <optgroup label="Por Contrato">
                <option value="Limpieza de Oficinas">Limpieza de Oficinas</option>
                <option value="Limpieza Industrial">Limpieza Industrial</option>
                <option value="Limpieza Comercial">Limpieza Comercial</option>
                <option value="Limpieza de Gimnasios">Limpieza de Gimnasios</option>
                <option value="Limpieza Residencial">Limpieza Residencial</option>
              </optgroup>
              <optgroup label="Por Evento">
                <option value="Lavado de Alfombras">Lavado de Alfombras</option>
                <option value="Lavado Correctivo">Lavado Correctivo</option>
                <option value="Limpieza en Alturas">Limpieza en Alturas</option>
                <option value="Pulido y Encerado">Pulido y Encerado</option>
              </optgroup>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="quote-description">Descripción del inmueble</label>
            <input class="form-input" type="text" id="quote-description" name="quote-description" placeholder="Oficina, bodega, edificio, casa...">
          </div>

          <div class="form-group">
            <label class="form-label" for="quote-area">Superficie aproximada (m²)</label>
            <input class="form-input" type="number" id="quote-area" name="quote-area" placeholder="Ej. 250" min="1">
          </div>

          <div class="form-group form-group--full">
            <label class="form-label" for="quote-message">Información adicional o comentarios</label>
            <textarea class="form-textarea" id="quote-message" name="quote-message" rows="4" placeholder="Cuéntenos sobre sus necesidades específicas, frecuencia requerida, horarios disponibles..."></textarea>
          </div>

          <div class="form-group form-group--full">
            <button type="submit" class="btn btn-accent btn--lg btn--full">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Solicitar cotización gratis
            </button>
            <p style="font-size:0.78rem; color:rgba(255,255,255,0.45); text-align:center; margin-top:0.75rem;">
              Al enviar acepta nuestra política de privacidad. Sin compromiso de contratación.
            </p>
            <div class="form-msg"></div>
          </div>

        </div>
      </form>
    </div>

  </div>
</section>


<!-- ══════════════════════════════════════════════════════════
     CLIENTS
══════════════════════════════════════════════════════════ -->
<section class="clients">
  <div class="container">
    <p class="clients__title">Empresas que confían en nosotros</p>
    <div class="clients__grid">
      <img src="images/ADM400.png" alt="ADM Malta Cleyton" class="clients__logo" loading="lazy">
      <img src="images/SmartFit400.png" alt="Smart Fit" class="clients__logo" loading="lazy">      
      <img src="images/Mitikah400.png" alt="Mitikah" class="clients__logo" loading="lazy">
      <img src="images/Inbursa400.png" alt="Inbursa" class="clients__logo" loading="lazy">      
    </div>
  </div>
</section>


<!-- ══════════════════════════════════════════════════════════
     TESTIMONIALS
══════════════════════════════════════════════════════════ -->
<section class="testimonials section">
  <div class="container">
    <div class="text-center reveal">
      <span class="section-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        Testimonios
      </span>
      <h2 class="section-title">Lo que dicen nuestros <span>Clientes</span></h2>
      <p class="section-subtitle">La satisfacción de nuestros clientes es el mejor reflejo de nuestro trabajo y compromiso con la excelencia.</p>
    </div>

    <div class="testimonials__slider reveal">
      <div class="testimonials__track">

        <div class="testimonial-card">
          <div class="testimonial-card__inner">
            <div class="testimonial-card__quote">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
            </div>
            <div class="testimonial-card__stars">★★★★★</div>
            <p class="testimonial-card__text">"Excelente servicio. El equipo de Sharclean transformó completamente nuestras oficinas. Puntuales, profesionales y con resultados impresionantes. Lo recomiendo ampliamente."</p>
            <div class="testimonial-card__author">
              <img src="images/testi-1.jpg" alt="María González" class="testimonial-card__avatar" loading="lazy">
              <div>
                <div class="testimonial-card__name">María González</div>
                <div class="testimonial-card__role">Gerente de Administración – Corporativo Azteca</div>
              </div>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <div class="testimonial-card__inner">
            <div class="testimonial-card__quote">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
            </div>
            <div class="testimonial-card__stars">★★★★★</div>
            <p class="testimonial-card__text">"Llevamos 3 años con Sharclean para el mantenimiento de nuestro edificio de 15 pisos. Su servicio de limpieza de vidrios exteriores es de primer nivel. Siempre cumplen."</p>
            <div class="testimonial-card__author">
              <img src="images/testi-2.jpg" alt="Carlos Ramírez" class="testimonial-card__avatar" loading="lazy">
              <div>
                <div class="testimonial-card__name">Carlos Ramírez</div>
                <div class="testimonial-card__role">Director de Operaciones – Grupo Inmobiliario Norte</div>
              </div>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <div class="testimonial-card__inner">
            <div class="testimonial-card__quote">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
            </div>
            <div class="testimonial-card__stars">★★★★★</div>
            <p class="testimonial-card__text">"Contratamos el servicio de fumigación y mantenimiento preventivo. El proceso fue limpio, rápido y sin interrumpir nuestra operación. El equipo muy profesional."</p>
            <div class="testimonial-card__author">
              <img src="images/testi-3.jpg" alt="Ana Martínez" class="testimonial-card__avatar" loading="lazy">
              <div>
                <div class="testimonial-card__name">Ana Martínez</div>
                <div class="testimonial-card__role">Coordinadora de Facilities – Logística Express</div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="testimonials__controls">
        <button class="testimonials__btn testimonials__btn--prev" aria-label="Anterior">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <div class="testimonials__dots">
          <span class="testimonials__dot active"></span>
          <span class="testimonials__dot"></span>
          <span class="testimonials__dot"></span>
        </div>
        <button class="testimonials__btn testimonials__btn--next" aria-label="Siguiente">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>
  </div>
</section>


<!-- ══════════════════════════════════════════════════════════
     VACANTES PREVIEW
══════════════════════════════════════════════════════════ -->
<section id="vacantes" class="vacantes-preview section">
  <div class="container">
    <div class="vacantes-preview__inner">
      <div class="vacantes-preview__text reveal-left">
        <span class="section-badge">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Únete al Equipo
        </span>
        <h2 class="section-title">¿Buscas <span style="color:#86EFAC;">Trabajo</span>?</h2>
        <p class="section-subtitle" style="margin-inline:0;">Somos una empresa en crecimiento que valora a su personal. Ofrecemos salario competitivo, prestaciones de ley, capacitación constante y un excelente ambiente de trabajo.</p>
      </div>
      <div class="vacantes-preview__actions reveal-right">
        <a href="vacantes.php" class="btn btn--lg" style="background:white;color:var(--secondary-dark);font-weight:700;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
          Ver vacantes disponibles
        </a>
        <a href="https://wa.me/5215500000000?text=Hola%20Sharclean%2C%20me%20interesa%20trabajar%20con%20ustedes"
           class="vacantes-preview__wa" target="_blank" rel="noopener noreferrer">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          ¿Deseas trabajar con nosotros? Escríbenos por WhatsApp
        </a>
      </div>
    </div>
  </div>
</section>


<!-- ══════════════════════════════════════════════════════════
     CONTACT
══════════════════════════════════════════════════════════ -->
<section id="contacto" class="contact section">
  <div class="container">
    <div class="text-center reveal">
      <span class="section-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.4 10.72 19.79 19.79 0 01.34 2.1 2 2 0 012.33 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
        Contáctenos
      </span>
      <h2 class="section-title">Estamos <span>Aquí para Ayudarle</span></h2>
      <p class="section-subtitle">Comuníquese con nosotros por el canal de su preferencia. Respondemos en menos de 2 horas en horario de atención.</p>
    </div>

    <div class="contact__grid">

      <div class="contact__info reveal-left">
        <div class="contact__info-item">
          <div class="contact__info-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </div>
          <div>
            <div class="contact__info-label">Dirección</div>
            <div class="contact__info-value">Oficina central: Ixtapaluca, Edo. Méx., México<br>Oficina reclutamiento: Polanco, CDMX, México</div>
          </div>
        </div>
        <div class="contact__info-item">
          <div class="contact__info-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.4 10.72 19.79 19.79 0 01.34 2.1 2 2 0 012.33 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
            </svg>
          </div>
          <div>
            <div class="contact__info-label">Teléfono</div>
            <div class="contact__info-value">+55-8945-4415 </div>
          </div>
        </div>
        <div class="contact__info-item">
          <div class="contact__info-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </div>
          <div>
            <div class="contact__info-label">Correo electrónico</div>
            <div class="contact__info-value">informacion@sharclean.com.mx</div>
          </div>
        </div>
        <div class="contact__info-item">
          <div class="contact__info-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div>
            <div class="contact__info-label">Horario de atención</div>
            <div class="contact__info-value">Lunes – Viernes: 9:00 am – 6:00 pm</div>
          </div>
        </div>
      </div>

      <div class="contact__form-wrap reveal-right">
        <h3 class="section-title" style="font-size:1.6rem;margin-bottom:1.5rem;">Envíenos un mensaje</h3>
        <form id="contactForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-grid" style="grid-template-columns:1fr;">

            <div class="form-group">
              <label class="form-label form-label--light" for="contact-name">Nombre completo *</label>
              <input class="form-input form-input--light" type="text" id="contact-name" name="contact-name" placeholder="Su nombre completo" required autocomplete="name">
            </div>

            <div class="form-group">
              <label class="form-label form-label--light" for="contact-email">Correo electrónico *</label>
              <input class="form-input form-input--light" type="email" id="contact-email" name="contact-email" placeholder="correo@ejemplo.com" required autocomplete="email">
            </div>

            <div class="form-group">
              <label class="form-label form-label--light" for="contact-message">Mensaje *</label>
              <textarea class="form-textarea form-textarea--light" id="contact-message" name="contact-message" rows="5" placeholder="¿En qué podemos ayudarle?" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn--full">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Enviar mensaje
            </button>
            <div class="form-msg"></div>

          </div>
        </form>
      </div>

    </div>
  </div>
</section>


<!-- ══════════════════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════════════════ -->
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
          <li><a href="#servicios" class="footer__link">Limpieza de Oficinas</a></li>
          <li><a href="#servicios" class="footer__link">Limpieza Industrial</a></li>
          <li><a href="#servicios" class="footer__link">Limpieza Comercial</a></li>
          <li><a href="#servicios" class="footer__link">Limpieza de Gimnasios</a></li>
          <li><a href="#servicios" class="footer__link">Limpieza Residencial</a></li>
          <li><a href="#servicios" class="footer__link">Lavado de Alfombras</a></li>
          <li><a href="#servicios" class="footer__link">Lavado Correctivo</a></li>
          <li><a href="#servicios" class="footer__link">Limpieza en Alturas</a></li>
          <li><a href="#servicios" class="footer__link">Pulido y Encerado</a></li>
        </ul>
      </div>

      <div>
        <h4 class="footer__col-title">Empresa</h4>
        <ul class="footer__links">
          <li><a href="#nosotros" class="footer__link">Quiénes somos</a></li>
          <li><a href="#cotizacion" class="footer__link">Solicitar cotización</a></li>
          <li><a href="vacantes.php" class="footer__link">Vacantes</a></li>
          <li><a href="#contacto" class="footer__link">Contacto</a></li>
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
      <p class="footer__copy">&copy; <?php echo date('Y'); ?> Sharclean. Todos los derechos reservados.</p><a href="http://www.sisia.com.mx" target="_blank">SISIA</a></p>
      <div class="footer__bottom-links">
        <a href="#" class="footer__bottom-link" id="open-privacy">Aviso de privacidad</a>
        <a href="#" class="footer__bottom-link" id="open-terms">Términos de servicio</a>
      </div>
    </div>
  </div>
</footer>


<?php include __DIR__ . '/partials/legal-modals.php'; ?>

<!-- ══════════════════════════════════════════════════════════
     WHATSAPP FLOATING BUTTON
══════════════════════════════════════════════════════════ -->
<div class="whatsapp-float" role="complementary" aria-label="Botón de WhatsApp">
  <div class="whatsapp-float__pulse" aria-hidden="true"></div>
  <a href="https://wa.me/5215500000000?text=Hola%20Sharclean%2C%20me%20gustar%C3%ADa%20obtener%20m%C3%A1s%20informaci%C3%B3n%20sobre%20sus%20servicios."
     class="whatsapp-float__btn"
     target="_blank" rel="noopener noreferrer"
     aria-label="Contáctenos por WhatsApp">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
  </a>
  <span class="whatsapp-float__tooltip">Contáctanos</span>
</div>


<!-- ══════════════════════════════════════════════════════════
     CHATBOT WIDGET
══════════════════════════════════════════════════════════ -->
<div class="chatbot" role="complementary" aria-label="Chat de ayuda">

  <div class="chatbot__panel" id="chatbotPanel" role="dialog" aria-label="Asistente virtual Sharclean" aria-modal="true">
    <div class="chatbot__header">
      <div class="chatbot__header-avatar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a2 2 0 012 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 017 7H3a7 7 0 017-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 012-2z"/><path d="M5 14v5a2 2 0 002 2h10a2 2 0 002-2v-5"/><circle cx="9" cy="17" r="1"/><circle cx="15" cy="17" r="1"/></svg>
      </div>
      <div class="chatbot__header-info">
        <div class="chatbot__header-name">Asistente Sharclean</div>
        <div class="chatbot__header-status">En línea ahora</div>
      </div>
      <button class="chatbot__close" aria-label="Cerrar chat">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="chatbot__messages" id="chatbotMessages" role="log" aria-live="polite">
      <div class="chatbot__msg chatbot__msg--bot">
        <div class="chatbot__msg-bubble">¡Hola! Soy el asistente virtual de Sharclean. ¿En qué puedo ayudarle hoy? 😊</div>
        <span class="chatbot__msg-time">Ahora</span>
      </div>
      <div class="chatbot__typing" id="chatbotTyping">
        <span></span><span></span><span></span>
      </div>
    </div>

    <div class="chatbot__suggestions" id="chatbotSuggestions"></div>

    <div class="chatbot__input-row">
      <input
        class="chatbot__input"
        id="chatbotInput"
        type="text"
        placeholder="Escribe tu pregunta..."
        maxlength="300"
        aria-label="Mensaje para el asistente">
      <button class="chatbot__send" id="chatbotSend" aria-label="Enviar mensaje">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      </button>
    </div>
  </div>

  <button class="chatbot__toggle" id="chatbotToggle" aria-label="Abrir asistente virtual" aria-expanded="false">
    <svg id="chatbotIconOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
    </svg>
    <span class="chatbot__badge">1</span>
  </button>

</div>


<script src="js/main.js"></script>
</body>
</html>
