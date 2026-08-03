/**
 * Sanalia & Asociados — main.js
 * Vanilla JS. No jQuery, no framework.
 */

/* ── Google Analytics 4 (GA4) ───────────────────────────────── */
(function () {
  var GA_ID = 'G-EQFB5LTZM1';
  var s = document.createElement('script');
  s.async = true;
  s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
  document.head.appendChild(s);
  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', GA_ID);
})();

/* ── Meta Pixel ─────────────────────────────────────────────── */
(function () {
  var META_ID = '2723659471487616';
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window,document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', META_ID);
  fbq('track', 'PageView');
})();

/* ── Capturar parámetros de atribución al llegar al sitio ───── */
(function () {
  const params = new URLSearchParams(window.location.search);
  ['fbclid','gclid','utm_source','utm_campaign','utm_medium'].forEach(key => {
    const val = params.get(key);
    if (val) sessionStorage.setItem(key, val);
  });
})();

/* ── Utility ────────────────────────────────────────────────── */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

/* ── Mobile Drawer ──────────────────────────────────────────── */
(function initDrawer() {
  const toggle   = $('#menu-toggle');
  const drawer   = $('#nav-drawer');
  const backdrop = $('#drawer-backdrop');
  const close    = $('#drawer-close');
  if (!toggle || !drawer) return;

  function open() {
    drawer.classList.add('is-open');
    backdrop.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    toggle.setAttribute('aria-expanded', 'true');
  }
  function shut() {
    drawer.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    document.body.style.overflow = '';
    toggle.setAttribute('aria-expanded', 'false');
  }

  toggle.addEventListener('click', open);
  close?.addEventListener('click', shut);
  backdrop?.addEventListener('click', shut);
  $$('a', drawer).forEach(a => a.addEventListener('click', shut));

  // Trap focus inside drawer when open (a11y)
  drawer.addEventListener('keydown', e => {
    if (e.key === 'Escape') shut();
  });
})();

/* ── Hero Slider ────────────────────────────────────────────── */
(function initSlider() {
  const slides   = $$('.slide');
  const dots     = $$('.slide-dot');
  const prevBtn  = $('.slide-prev');
  const nextBtn  = $('.slide-next');
  if (!slides.length) return;

  let current = 0;
  let timer   = null;

  function goTo(idx) {
    slides[current].classList.remove('is-active');
    dots[current]?.classList.remove('is-active');
    dots[current]?.setAttribute('aria-selected', 'false');
    current = (idx + slides.length) % slides.length;
    slides[current].classList.add('is-active');
    dots[current]?.classList.add('is-active');
    dots[current]?.setAttribute('aria-selected', 'true');
  }

  function startAuto() {
    clearInterval(timer);
    timer = setInterval(() => goTo(current + 1), 6500);
  }

  dots.forEach((dot, i) => {
    dot.addEventListener('click', () => { goTo(i); startAuto(); });
  });

  prevBtn?.addEventListener('click', () => { goTo(current - 1); startAuto(); });
  nextBtn?.addEventListener('click', () => { goTo(current + 1); startAuto(); });

  document.addEventListener('visibilitychange', () => {
    document.hidden ? clearInterval(timer) : startAuto();
  });

  // Swipe support for mobile
  let touchStartX = 0;
  const sliderEl  = $('.hero');
  sliderEl?.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
  sliderEl?.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 50) { goTo(current + (dx < 0 ? 1 : -1)); startAuto(); }
  }, { passive: true });

  startAuto();
})();

/* ── Animated Counters ──────────────────────────────────────── */
(function initCounters() {
  const counters = $$('[data-count]');
  if (!counters.length) return;

  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el     = entry.target;
      const target = parseInt(el.dataset.count, 10);
      const duration = 1800;
      const step   = target / (duration / 16);
      let cur      = 0;
      const tick   = setInterval(() => {
        cur += step;
        if (cur >= target) { cur = target; clearInterval(tick); }
        el.textContent = Math.floor(cur);
      }, 16);
      io.unobserve(el);
    });
  }, { threshold: 0.5 });

  counters.forEach(c => io.observe(c));
})();

/* ── Sticky Header Shadow ───────────────────────────────────── */
(function initStickyHeader() {
  const header = $('#site-header');
  if (!header) return;
  window.addEventListener('scroll', () => {
    header.classList.toggle('scrolled', window.scrollY > 20);
  }, { passive: true });
})();

/* ── Back to Top ────────────────────────────────────────────── */
(function initBackTop() {
  const btn = $('#back-top');
  if (!btn) return;
  window.addEventListener('scroll', () => {
    btn.classList.toggle('is-visible', window.scrollY > 400);
  }, { passive: true });
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
})();

/* ── Site Search ────────────────────────────────────────────── */
(function initSearch() {
  // Build overlay dynamically so it only needs to live in main.js
  const overlay = document.createElement('div');
  overlay.id = 'search-overlay';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-label', 'Búsqueda');
  overlay.innerHTML =
    '<button id="search-close" aria-label="Cerrar búsqueda">✕</button>' +
    '<div id="search-wrap">' +
      '<input id="search-input" type="search" placeholder="Buscar en Sanalia…" autocomplete="off" aria-label="Buscar">' +
      '<div id="search-results" role="listbox" aria-label="Resultados de búsqueda"></div>' +
    '</div>';
  document.body.appendChild(overlay);

  // Detect base path (root vs servicios/ vs blog/)
  const p = window.location.pathname;
  const base = (p.includes('/servicios/') || p.includes('/blog/')) ? '../' : '';

  const PAGES = [
    { title:'Inicio',               desc:'Correduría de seguros en Santo Domingo',          url: base+'index.html',                          tags:'inicio home sanalia seguros' },
    { title:'Nosotros',             desc:'Historia, misión y valores de Sanalia',            url: base+'nosotros.html',                       tags:'nosotros quienes somos historia mision valores equipo' },
    { title:'Servicios',            desc:'Todos los seguros que ofrecemos',                  url: base+'servicios/index.html',                tags:'servicios catalogo' },
    { title:'Seguro de Vida',       desc:'Protección financiera para tu familia',            url: base+'servicios/vida.html',                 tags:'vida fallecimiento familia beneficiarios' },
    { title:'Seguro de Salud',      desc:'Cobertura médica para ti y tu familia',            url: base+'servicios/salud.html',                tags:'salud medico hospital ars cobertura enfermedad' },
    { title:'Seguro de Vehículos',  desc:'Cobertura para tu auto o flota',                  url: base+'servicios/vehiculos.html',            tags:'vehiculos auto carro flota colision robo' },
    { title:'Seguro de Viajes',     desc:'Cobertura internacional para tus viajes',          url: base+'servicios/viajes.html',               tags:'viajes internacional cancelacion equipaje exterior' },
    { title:'Seguros Internacionales', desc:'Coberturas con redes globales',                 url: base+'servicios/internacionales.html',      tags:'internacional global expatriado activos exterior' },
    { title:'Accidentes Personales',desc:'Cobertura ante accidentes e invalidez',            url: base+'servicios/accidentes-personales.html',tags:'accidentes personales invalidez lesion' },
    { title:'Riesgos Generales',    desc:'13 coberturas para empresas y proyectos',          url: base+'riesgos-generales.html',              tags:'riesgos generales empresa incendio fianzas maquinaria responsabilidad' },
    { title:'Seguro de Mascotas',   desc:'Protección veterinaria para tu mascota',           url: base+'mascotas.html',                       tags:'mascotas perro gato veterinario' },
    { title:'Contacto',             desc:'Contáctanos para una cotización sin costo',        url: base+'contacto.html',                       tags:'contacto cotizar whatsapp telefono oficina' },
    { title:'Blog',                 desc:'Artículos sobre seguros en República Dominicana',  url: base+'blog/index.html',                     tags:'blog articulos noticias informacion' },
  ];

  const input   = $('#search-input');
  const results = $('#search-results');
  const closeBtn= $('#search-close');

  function open() {
    overlay.classList.add('is-open');
    setTimeout(() => input.focus(), 50);
    document.body.style.overflow = 'hidden';
  }
  function close() {
    overlay.classList.remove('is-open');
    input.value = '';
    results.innerHTML = '';
    document.body.style.overflow = '';
  }
  function search(q) {
    q = q.toLowerCase().trim();
    if (!q) { results.innerHTML = ''; return; }
    const hits = PAGES.filter(pg =>
      pg.title.toLowerCase().includes(q) ||
      pg.desc.toLowerCase().includes(q) ||
      pg.tags.toLowerCase().includes(q)
    );
    results.innerHTML = hits.length
      ? hits.map(pg =>
          '<a class="sr-item" href="' + pg.url + '">' +
            '<span class="sr-item-title">' + pg.title + '</span>' +
            '<span class="sr-item-desc">'  + pg.desc  + '</span>' +
          '</a>'
        ).join('')
      : '<p style="color:rgba(255,255,255,.45);padding:12px 20px;margin:0;font-size:13px;">Sin resultados para esa búsqueda.</p>';
  }

  input.addEventListener('input', e => search(e.target.value));
  closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && overlay.classList.contains('is-open')) close(); });

  $$('.nav-search-btn').forEach(btn => btn.addEventListener('click', open));
})();

/* ── WhatsApp FAB — dual number picker ──────────────────────── */
(function initWaFab() {
  const btn  = $('#wa-fab');
  const menu = $('#wa-fab-menu');
  if (!btn || !menu) return;

  function open() {
    menu.classList.add('is-open');
    btn.setAttribute('aria-expanded', 'true');
  }
  function close() {
    menu.classList.remove('is-open');
    btn.setAttribute('aria-expanded', 'false');
  }
  function toggle() {
    menu.classList.contains('is-open') ? close() : open();
  }

  btn.addEventListener('click', e => { e.stopPropagation(); toggle(); });
  document.addEventListener('click', close);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
})();

/* ── Contact Form (placeholder for Phase 3) ─────────────────── */
(function initContactForm() {
  const form = $('#contact-form');
  if (!form) return;

  // Inline validation on blur
  form.querySelectorAll('[required]').forEach(field => {
    field.addEventListener('blur', () => validateField(field));
    field.addEventListener('input', () => clearError(field));
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const valid = validateForm(form);
    if (!valid) return;

    // Check honeypot
    const honey = form.querySelector('[name="campo_control"]');
    if (honey?.value) return; // bot detected — silent discard

    // Inyectar parámetros de atribución publicitaria
    const params = new URLSearchParams(window.location.search);
    ['fbclid','gclid','utm_source','utm_campaign','utm_medium'].forEach(key => {
      const val = params.get(key) || sessionStorage.getItem(key) || '';
      if (val) { const h = document.createElement('input'); h.type='hidden'; h.name=key; h.value=val; form.appendChild(h); }
    });
    // GA4 Client ID desde cookie _ga
    const gaCookie = document.cookie.split(';').map(c=>c.trim()).find(c=>c.startsWith('_ga='));
    if (gaCookie) {
      const gaVal = gaCookie.split('=')[1]?.split('.').slice(2).join('.') || '';
      if (gaVal) { const h = document.createElement('input'); h.type='hidden'; h.name='ga_client_id'; h.value=gaVal; form.appendChild(h); }
    }

    const btn = form.querySelector('[type="submit"]');
    btn.disabled  = true;
    btn.textContent = 'Enviando…';

    try {
      const res  = await fetch('/api/contact.php', {
        method: 'POST',
        body: new FormData(form),
      });
      const data = await res.json();

      if (data.ok) {
        form.reset();
        showFormMessage(form, '¡Mensaje enviado! Te contactaremos pronto.', 'success');

        // ── Tracking: GA4 + Meta Pixel + GTM ──────────────────
        const interes = (new FormData(form)).get('interes') || '';
        const utmSource = new URLSearchParams(window.location.search).get('utm_source') || 'directo';

        // GA4 directo
        if (typeof gtag === 'function') {
          gtag('event', 'generate_lead', {
            lead_source:  utmSource,
            lead_interes: interes,
          });
        }

        // Meta Pixel
        if (typeof fbq === 'function') {
          fbq('track', 'Lead', {
            content_name: interes || 'general',
          });
        }

        // GTM dataLayer (activa las etiquetas de GTM configuradas)
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
          event:        'form_lead_enviado',
          lead_interes: interes,
          lead_fuente:  utmSource,
        });
        // ── Fin tracking ───────────────────────────────────────
      } else {
        if (data.errors) applyServerErrors(form, data.errors);
        else showFormMessage(form, 'Ocurrió un error. Inténtalo de nuevo.', 'error');
      }
    } catch {
      showFormMessage(form, 'Error de conexión. Inténtalo de nuevo.', 'error');
    } finally {
      btn.disabled    = false;
      btn.textContent = 'Enviar Mensaje';
    }
  });

  function validateField(field) {
    const v = field.value.trim();
    if (!v) return setError(field, 'Este campo es requerido');
    if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))
      return setError(field, 'Email inválido');
    if (field.name === 'nombre' && v.length < 3)
      return setError(field, 'Mínimo 3 caracteres');
    if (field.name === 'mensaje' && v.length < 15)
      return setError(field, 'Mínimo 15 caracteres');
    clearError(field);
    return true;
  }

  function validateForm(form) {
    return [...form.querySelectorAll('[required]')]
      .map(f => validateField(f)).every(Boolean);
  }

  function setError(field, msg) {
    field.classList.add('is-error');
    let err = field.parentNode.querySelector('.field-error');
    if (!err) { err = document.createElement('span'); err.className = 'field-error'; field.parentNode.appendChild(err); }
    err.textContent = msg;
    return false;
  }

  function clearError(field) {
    field.classList.remove('is-error');
    field.parentNode.querySelector('.field-error')?.remove();
  }

  function applyServerErrors(form, errors) {
    Object.entries(errors).forEach(([name, msg]) => {
      const f = form.querySelector(`[name="${name}"]`);
      if (f) setError(f, msg);
    });
  }

  function showFormMessage(form, text, type) {
    let el = form.querySelector('.form-msg');
    if (!el) { el = document.createElement('p'); el.className = 'form-msg'; form.appendChild(el); }
    el.textContent = text;
    el.className   = `form-msg form-msg--${type}`;
  }
})();
