/**
 * Sanalia & Asociados — main.js
 * Vanilla JS. No jQuery, no framework.
 */

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
