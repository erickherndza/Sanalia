# Plan SEO/GEO — www.sanaliayasociados.com — 2026-10-04

**SEO 83/100 · GEO 78/100** (medido antes de los arreglos de hoy) · 23 URLs en sitemap · 22 en vivo · 25 HTML en código
HTML estático + PHP (`api/contact.php`, `admin/`) · GoDaddy (Apache, IP 107.180.118.0, DNS domaincontrol) · deploy FTP por GitHub Actions
Medición: GTM `GTM-5VKFNVQG` (GA4 `G-EQFB5LTZM1` dentro) + Meta Pixel `2723659471487616` · Datos de tráfico (GSC/GA): **no** → prioridades estimadas sin tráfico real

## Resumen

Lo técnico y el contenido están bien encaminados: sitemap limpio, canonical correcto en todas las páginas,
FAQ visibles con `FAQPage`, `llms.txt`, 404 real, bots de IA permitidos en `robots.txt`.

**El cuello de botella hoy es el hosting.** Después de unas 25 peticiones seguidas, GoDaddy responde
**cualquier URL** (incluido `robots.txt`, `sitemap.xml` y el CSS) con **HTTP 200** y una página
"One moment, please… Please wait while your request is being verified". Con los User-Agent de GPTBot,
ClaudeBot, PerplexityBot y OAI-SearchBot se recibe esa misma página en vez del sitio. Como el status es 200,
un rastreador puede guardar ese desafío como si fuera el contenido. El arreglo está en el panel del hosting
(tarea del dueño, abajo). Cualquier mejora de contenido rinde menos mientras esto siga así.

Lo segundo es **autoridad local**: 1 reseña en Google contra 50–89 de los competidores del 3-pack (ver §19 de `CLAUDE.md`).

## Estado por módulo

| Módulo | ✅ | ❌ | N/A | Lo más grave |
|---|---|---|---|---|
| 01 Técnico | 10 | 4 → 0 | 2 (hreflang, huérfanas) | Host sin www respondía 200 sin redirigir · `/index.html` terminaba en `/index` (corregido hoy) |
| 02 GEO | 9 | 2 → 1 | 0 | Bots de IA reciben la página anti-bots del hosting (pendiente del dueño) |
| 03 Contenido | 7 | 0 | 1 | — |
| 04 Medición | 2 | 6 → 1 | 0 | GA4 cargado dos veces (page_view doble) · lead de formulario sin `interes` (corregido hoy) · falta etiqueta `generate_lead` en GTM (dueño) |
| 05 Autoridad/local | 3 | 2 | 0 | 1 reseña en GBP |
| 06 Seguridad/hosting | 6 | 5 → 2 | 1 (Cloudflare) | Desafío anti-bots con 200 · sin DMARC ni CAA |
| 07 Anuncios | — | — | todo | No hay campañas activas registradas |

## Errores — arreglados hoy (2026-10-04)

| # | Qué | Evidencia | Arreglo | Commit |
|---|---|---|---|---|
| 1 | Sitio duplicado en `https://sanaliayasociados.com` (sin www) | `curl -L` → 200, 0 redirects | `.htaccess`: http y sin-www → `https://www` en 1 salto (exento `/.well-known/`) | `2500485` |
| 2 | `/index.html` → `/index` respondía 200 (URL duplicada de la home) | `curl -L /index.html` → `/index` 200 | Regla `^(.*/)?index\.html$` → `/` | `2500485` |
| 3 | `sitemap.xml` servido como `text/plain` | regla `FilesMatch (txt\|xml\|json)` | `AddType application/xml .xml` + charset utf-8 | `2500485` |
| 4 | `README.md` público | `curl /README.md` → 200 text/markdown | `.htaccess` deniega `*.md` (salvo `index.md`/`404.md`), `*.sql`, `htaccess.txt`, `composer.json`; deploy ya no los sube | `2500485` |
| 5 | Cabecera obsoleta `X-XSS-Protection` | `curl -I /` | `Header always unset` | `2500485` |
| 6 | GA4 cargado dos veces → page_view doble | `main.js` hacía `gtag('config','G-EQFB5LTZM1')` y el contenedor GTM también tiene el Google tag de ese ID | Un solo cargador `assets/js/tracking.js` (GTM + Pixel), tras `load`, solo en `www` | `7c2bf83` |
| 7 | El evento de lead del formulario mandaba `interes` vacío | `main.js` lo leía después de `form.reset()` | Se lee antes del reset; `generate_lead {lead_method, lead_id}` + evento heredado `form_lead_enviado` | `7c2bf83` |
| 8 | Clics a `tel:` no se medían | sin listener | `tracking.js` mide `wa.me` y `tel:` como lead | `7c2bf83` |
| 9 | La copia de `erickherndza.github.io/Sanalia/` también disparaba GA4/Pixel | mismo HTML | `tracking.js` solo mide en `www.sanaliayasociados.com` | `7c2bf83` |
| 10 | CSS/JS con caché `immutable` 1 año sin versión en la URL → visitantes con CSS viejo | `style.css` sin `?v=` | `?v=20261004` en las 24 páginas | `7c2bf83` |
| 11 | 404 con CSS/logo rotos en URLs anidadas (`/servicios/xyz`) | rutas relativas `assets/...` | Rutas absolutas en `404.html` | `7c2bf83` |
| 12 | 3 H1 en la home | `auditar_codigo.py` | Slides 2 y 3 → `h2.slide-title` (mismo estilo) | `97c154a` |
| 13 | Meta description ≥155 en 7 páginas; title de home 72 car. | auditorías | Recortadas conservando el dato | `97c154a` |
| 14 | `sameAs` con 2 perfiles | `seo_geo_audit.py` | + Perfil de Negocio de Google (CID real) en 22 páginas | `8deaf35` |

## Tareas de código pendientes (Claude puede hacerlas)

| # | Tarea | Módulo | Impacto | Esfuerzo | Plantilla |
|---|---|---|---|---|---|
| 1 | Generar `sitemap.xml` con script (hoy se edita a mano) | 01 | Medio | Bajo | — |
| 2 | `/llms-full.txt` con el contenido extendido de servicios | 02 | Bajo | Bajo | `llms.txt` |
| 3 | Más H2 en forma de pregunta en páginas de servicio (6/22 páginas tienen ≥2) | 02 | Medio | Medio | — |
| 4 | Content-Security-Policy en modo Report-Only | 06 | Bajo | Medio | `htaccess-seo-seguridad.conf` |
| 5 | Retirar el `gtag generate_lead` heredado del GTM cuando exista la etiqueta nueva (ver tarea del dueño) | 04 | Medio | Bajo | — |

## Tareas del dueño (paneles, con pasos exactos)

- [ ] **GoDaddy → cPanel → Imunify360 / "Bot protection" (o soporte de GoDaddy)** — *la más importante*.
  Pedir que el desafío anti-bots **no se aplique** a `robots.txt`, `sitemap.xml`, `llms.txt` ni a los
  rastreadores verificados (Googlebot, Bingbot, GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot), y que,
  si se muestra, responda **429/503** y no 200. Texto sugerido para soporte: *"Tras ~25 peticiones el
  sitio responde 200 con 'One moment, please… Please wait while your request is being verified' a
  cualquier URL, incluido robots.txt. Necesito que los rastreadores de buscadores e IA no reciban ese
  desafío."* Verificación: el guardián diario deja de reportar "página anti-bots".
- [ ] **GTM → Etiquetas → Nueva → Evento de GA4** `generate_lead`, parámetro `lead_method = {{DLV - lead_method}}`,
  activador *Evento personalizado* `generate_lead`. Publicar. Luego **GA4 → Administrar → Eventos** → marcar
  `generate_lead` como **evento clave**. (Si hay Google Ads: etiqueta de Conversión con ID de transacción
  `{{DLV - lead_id}}`.) Revisar si la etiqueta GA4 manual `page_view` del contenedor duplica la del Google
  tag; si es así, pausarla.
- [ ] **GA4 → Flujos de datos → Definir tráfico interno**: IP de la oficina.
- [ ] **GitHub → repo Sanalia → Settings → Pages → desactivar** — la copia en `erickherndza.github.io/Sanalia/` duplica el sitio.
- [ ] **DNS (GoDaddy)**: agregar `_dmarc` TXT `v=DMARC1; p=quarantine; rua=mailto:info@sanaliayasociados.com`
  (subir a `p=reject` tras 2–4 semanas sin rechazos legítimos) y un registro **CAA** `0 issue "sectigo.com"`
  (o la CA que use el SSL de GoDaddy — confirmar antes).
- [ ] **Search Console → Sitemaps** → reenviar `sitemap.xml`; **Inspección de URL** de
  `https://sanaliayasociados.com/` → confirmar que ahora redirige a `www`.
- [ ] **Google Business** → seguir pidiendo reseñas con `https://g.page/r/CVdcam_m8-JcEBM/review` (ver §19 de `CLAUDE.md`).
- [ ] Probar el formulario real una vez tras este deploy (llega al correo y al CRM con `utm_*`/`gclid`).

## Oportunidades (no son errores)

- Titles de 4 artículos del blog >60 caracteres (solo se truncan en Google, no afecta ranking).
- H1 de la home sin la keyword ("Protege lo que más importa…"); el title ya la lleva al inicio.
- `dateModified` en el `WebPage` de páginas de servicio (los artículos ya lo tienen).
- Imágenes con `srcset`/WebP en heros (`assets/img/servicios/viajes.jpg` pesa 6.7 MB).

## No se pudo verificar

- Contenido que ven los bots de IA **reales** (desde sus IPs): solo se probó con su User-Agent desde una IP ya desafiada.
- Qué etiquetas exactas dispara el activador `form_lead_enviado` en GTM (se leyó el contenedor publicado, no el espacio de trabajo).
- Respuesta de `/admin/` (protegido o no) y de `api/contact.php` por GET: el hosting estaba devolviendo el desafío.
- Si el Googlebot **real** (IPs de Google) recibe el desafío: desde GitHub Actions, un Chromium con UA de
  Googlebot lo recibió (run 37241016034). Confirmar con Search Console → Inspección de URL → "Probar URL publicada".

## Verificado en producción tras el deploy (2026-10-04, cabeceras de navegador, 1 petición cada 3 s)

- `https://sanaliayasociados.com/`, `http://sanaliayasociados.com/servicios/vida`, `http://www…/` → `https://www…` en **1 salto** ✅
- `/index.html` → `/` · `/contacto.html` → `/contacto` (1 salto) ✅
- `sitemap.xml` `application/xml; charset=utf-8` · `llms.txt` `text/plain; charset=utf-8` · `index.md` 200 ✅
- `README.md`, `htaccess.txt`, `admin/schema.sql` → **403** ✅ · sin `X-XSS-Protection` ✅
- Home: 1 `<h1>`, `tracking.js?v=20261004` 1 vez, sin GTM pegado a mano; `tracking.js` `immutable` ✅
- Guardián desde GitHub: las **23 URLs del sitemap pasan** (canonical, meta, JSON-LD, tracking 1 vez) antes de que el hosting
  empezara a responder con el desafío ✅
- ❌ **Pendiente:** URL inexistente da **404 real** pero con el cuerpo genérico de Apache ("404 Not Found", 13 bytes),
  no `404.html` — ya pasaba antes de hoy. `/404` sí sirve la página (200). Siguiente prueba: `ErrorDocument 404 /404`
  (sin extensión) o pedir a GoDaddy si su capa delante del Apache reemplaza las páginas de error.

## Vigilancia

**Guardián diario instalado** (`.github/workflows/guardian.yml`, 8:00 a.m. RD y tras cada push;
config en `scripts/guardian/guardian.json`). Incluye un chequeo propio de Sanalia: falla si el hosting
entrega la página anti-bots. **Va a fallar hasta que se resuelva la tarea del hosting** — es el aviso correcto.
Ciclo con datos de Search Console cada ~6 semanas (módulo 05).

## Estrategia de palabras clave (2026-10-04)

Sin datos de volumen ni de SERP: OpenRush sin créditos y sin export de Search Console. Las prioridades salen
de la intención de búsqueda, no de volúmenes estimados.

### Mapa keyword → página (una keyword principal por página, sin canibalizar)

| Keyword | Intención | Página dueña | Dónde está ahora |
|---|---|---|---|
| **corredores de seguros en Santo Domingo** | comercial local | `/` (home) | title (al inicio), **H1** (etiqueta del slide 1), primer párrafo, `description` del JSON-LD, FAQ, footer de todas las páginas |
| **corredor de seguros a tu lado** | marca / posicionamiento | `/nosotros` | title, H1, meta, nombre del `AboutPage`; en la home queda como H2 enlazado a nosotros |
| **Siéntete más que seguro. Somos soluciones.** | marca (eslogan) | entidad (todo el sitio) | `slogan` en el JSON-LD `Organization` de las 22 páginas, footer visible en todas, banda de cita de la home, FAQ "¿Qué significa…?", `llms.txt` / `index.md` |
| qué es un corredor de seguros | informativa | `/blog/que-es-un-corredor-de-seguros` | ya existe → enlaza a la home con el anchor "corredores de seguros en Santo Domingo" |

### Público objetivo → contenido

- **Familias e individuos de Santo Domingo** (vida, salud, vehículo, hogar): FAQ de la home con respuestas
  directas (qué hace un corredor, cuánto cuesta, dónde y horario).
- **PyMEs y empresas** (riesgos generales, fianzas): `riesgos-generales` ya tiene FAQ. Falta un artículo de
  cluster "Cómo elegir un corredor de seguros para tu empresa en RD" que enlace a `/riesgos-generales`.
- **Juntas de condominio y propietarios** (Mi Hogar Condominio): página nueva enlazada desde la home.

### Contenido sugerido (necesita datos reales del cliente; no se inventa nada)

1. Artículo "Cómo elegir un corredor de seguros en Santo Domingo", con criterios verificables: autorización
   de la Superintendencia de Seguros, independencia, acompañamiento en reclamaciones. Enlaza a la home.
2. Casos reales de reclamaciones gestionadas, anonimizados y con permiso. Es lo que más diferencia a un
   "corredor a tu lado"; hace falta que el cliente entregue 2-3 casos.
3. Testimonios reales en `content/testimonials.json` cuando el cliente los entregue. Nunca inventados.

### Fuera del sitio (tareas del dueño)

- [ ] **Google Business → Descripción**: empezar con "Corredores de seguros en Santo Domingo…" e incluir el
  eslogan. **No** agregar keywords al *nombre* del perfil (riesgo de suspensión).
- [ ] **Reseñas**: al pedirlas, sugerir al cliente que mencione el servicio y la zona ("me ayudaron con el
  seguro del carro en Santo Domingo"). Nunca redactarlas por él.
- [ ] **Instagram / Facebook**: bio con el eslogan y el enlace `https://www.sanaliayasociados.com/`
  (con `www`, igual que el canonical).
- [ ] Directorios (PaginasAmarillas, Infoguia): categoría "Corredores de seguros", el mismo NAP y el eslogan en la descripción.
- [ ] Medir en Search Console, a las 4-6 semanas: consultas que contengan "corredor"/"corredores", y la
  posición de la home para "corredores de seguros en santo domingo".
