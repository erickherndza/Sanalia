/* ============================================================
   tracking.js — Sanalia & Asociados (plantilla plan-seo, adaptada 2026-10-04)

   UN solo archivo carga toda la medición, en TODAS las páginas, una sola vez:
   nada de gtag/pixel pegado a mano en el HTML (eso causó doble conteo y 84 páginas
   sin medir). Se carga DESPUÉS de window.load para no afectar LCP/TBT.

   Configurar CFG abajo. Cualquier ID vacío = ese servicio no se carga.
   Referenciar en el HTML con versión, porque se sirve con caché immutable:
     <script src="/assets/js/tracking.js?v=AAAAMMDD" defer></script>
   y subir la versión en TODAS las páginas cada vez que cambie este archivo.
   ============================================================ */
(function () {
  'use strict';

  var CFG = {
    hostProduccion: 'www.sanaliayasociados.com', // solo aquí se mide (github.io/local no contaminan datos)
    gtm: 'GTM-5VKFNVQG',                 // GA4 (G-EQFB5LTZM1) vive dentro de GTM — no cargar gtag aparte
    metaPixel: '2723659471487616',
    clarity: '',
    formAction: /\/api\/contact\.php/i   // formulario de contacto.html
  };

  if (location.hostname !== CFG.hostProduccion) {
    window.leadConversion = function () {};
    return;
  }

  /* ── Conversiones de lead ─────────────────────────────────────
     window.leadConversion(metodo): 'formulario' | 'whatsapp' | 'telefono'
     - dataLayer 'generate_lead' {lead_method, lead_id} → GTM:
         activador Evento personalizado 'generate_lead' →
         (a) GA4 evento generate_lead con parámetro lead_method (marcarlo evento clave)
         (b) Conversión de Google Ads con ID de transacción = {{lead_id}} (deduplica)
     - Meta Pixel: Lead (formulario) / Contact (WhatsApp, teléfono)
     Llamarla en el éxito del envío del formulario (no en el submit: si falla, no es lead). */
  var colaMeta = [], metaListo = false;
  function enviarMeta(m) { fbq('track', m === 'formulario' ? 'Lead' : 'Contact', { content_name: m }); }
  window.leadConversion = function (metodo) {
    var leadId = 'lead-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event: 'generate_lead', lead_method: metodo, lead_id: leadId });
    if (!CFG.metaPixel) return;
    if (metaListo) enviarMeta(metodo); else colaMeta.push(metodo);
  };

  /* Clics a WhatsApp y teléfono: en RD/LatAm son la mayoría de los leads */
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) return;
    var href = a.getAttribute('href') || '';
    if (/wa\.me\/|api\.whatsapp\.com|^whatsapp:/i.test(href)) window.leadConversion('whatsapp');
    else if (/^tel:/i.test(href)) window.leadConversion('telefono');
  }, true);

  /* ── Atribución para el CRM ───────────────────────────────────
     Guarda de qué anuncio llegó el visitante (utm_*, gclid, fbclid) 90 días y lo añade
     como campos ocultos a los formularios de lead. El backend debe guardarlos
     (campaña, gclid, fbclid, ga_client_id) para poder filtrar leads por anuncio. */
  var ATTR_KEY = 'attr_anuncio', ATTR_TTL = 90 * 864e5;
  var CAMPOS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'];
  try {
    var qs = new URLSearchParams(location.search), nueva = {}, hay = false;
    CAMPOS.forEach(function (k) { var v = qs.get(k); if (v) { nueva[k] = v.slice(0, 255); hay = true; } });
    if (hay) { nueva.ts = Date.now(); localStorage.setItem(ATTR_KEY, JSON.stringify(nueva)); }
  } catch (e) { /* storage bloqueado: el formulario funciona igual */ }

  document.addEventListener('submit', function (e) {   // fase de captura: antes del submit propio
    var form = e.target;
    if (!form || !CFG.formAction.test(form.getAttribute('action') || '')) return;
    var datos = {};
    try {
      var a = JSON.parse(localStorage.getItem(ATTR_KEY) || 'null');
      if (a && Date.now() - a.ts < ATTR_TTL) datos = a;
    } catch (err) { /* sin atribución */ }
    var ga = (document.cookie.match(/(?:^|;\s*)_ga=GA\d\.\d\.([^;]+)/) || [])[1];
    if (ga) datos.ga_client_id = ga;
    Object.keys(datos).forEach(function (k) {
      if (k === 'ts') return;
      var input = form.querySelector('input[type="hidden"][name="' + k + '"]');
      if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = k; form.appendChild(input); }
      input.value = datos[k];
    });
  }, true);

  function cargar() {
    if (CFG.gtm) {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
      var g = document.createElement('script');
      g.async = true;
      g.src = 'https://www.googletagmanager.com/gtm.js?id=' + CFG.gtm;
      document.head.appendChild(g);
    }
    if (CFG.metaPixel) {
      !function (f, b, e, v, n, t, s) {
        if (f.fbq && f._fbq) return;
        n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
        if (!f._fbq) f._fbq = n;
        n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = [];
        t = b.createElement(e); t.async = !0; t.src = v;
        s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
      }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', CFG.metaPixel);
      fbq('track', 'PageView');
      metaListo = true;
      colaMeta.splice(0).forEach(enviarMeta);
    }
    if (CFG.clarity) {
      (function (c, l, a, r, i, t, y) {
        c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
        t = l.createElement(r); t.async = 1; t.src = 'https://www.clarity.ms/tag/' + i;
        y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
      })(window, document, 'clarity', 'script', CFG.clarity);
    }
  }
  if (document.readyState === 'complete') cargar();
  else window.addEventListener('load', cargar);
})();
