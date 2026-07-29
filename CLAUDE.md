# CLAUDE.md — Sanalia & Asociados, S.R.L. | Website Corporativo

## 0. Contexto del proyecto

Cliente: **Sanalia & Asociados, S.R.L.** — corredores de seguros, Santo Domingo, R.D.
Referencia de layout/estructura (NO clonar código, solo estructura y ritmo de secciones):
`https://insurshtml.websitelayout.net/index.html`

Este documento es la fuente de verdad para Claude Code durante el build. Cualquier ambigüedad se resuelve consultando este archivo antes de inventar una decisión nueva.

**Filosofía de ejecución (dev-debug-ia):** Reproducir → Aislar → Hipótesis → Verificar → Fix mínimo. Root-cause antes que parche. Una lección transferible por sesión de trabajo.

**Regla de oro de diseño:** el sitio debe seguir la *estructura y secuencia narrativa* del template de referencia (hero slider → about → contadores → servicios → cita/quote → why-us → CTA → riesgos generales → producto destacado → aliados → footer), pero con sistema de diseño **propio** de Sanalia (ver §3). No reutilizar clases, assets ni CSS del template de referencia — es un producto comercial de ThemeForest.

---

## 1. Datos de la empresa (no inventar, no modificar)

| Campo | Valor |
|---|---|
| Razón social | Sanalia & Asociados, S.R.L. |
| Tagline | Siéntete más que seguro. Somos soluciones. |
| Dirección | Calle 4ta No. 6, Ensanche Kennedy, Santo Domingo, R.D. |
| Teléfono principal | (809) 362-4357 |
| WhatsApp secundario | (829) 669-5001 |
| WhatsApp terciario | (829) 616-4585 |
| Email | info@sanaliayasociados.com |
| Instagram | @sanaliayasociados.srl |

**Líneas de seguros de persona (7):** Vida · Persona-Salud · Viajes · Vehículos · Salud · Accidentes Personales · Internacionales

**Riesgos Generales (13):** Vehículos de Motor · Incendio y Líneas Aliadas · Fianzas · Avería de Maquinaria · Responsabilidad Civil · Seguros de Propiedades · Todo Riesgo de Montaje · Todo Riesgo Equipos Electrónicos · Todo Riesgo de Construcción (Ingeniería) · Transporte de Carga · Interrupción de Negocios · Cristales y Letreros · Fidelidad 3D

**Producto destacado:** Seguro de Salud para Mascotas (cirugías, hospitalización/emergencias, exámenes de laboratorio, imágenes, tratamiento odontológico, parto, castración, asistencia funeraria, implantación de chip, consulta de rutina, vacunas, orientación veterinaria).

**Aseguradoras aliadas (20, contadas del brochure — usar tal cual, no aproximar):** Humano Seguros, Seguros Reservas, Grupo Sura, Mapfre BHD, Seguros Universal, WorldWide Medical, Creciendo Seguros, La Colonial, Atlántica Seguros, Dominicana de Seguros, SeNaSa, Atrio Seguros, La Monumental, General de Seguros, APS ARS, Bupa, Pepín Seguros, ARS Yunen, Internacional de Seguros, Coopseguros.

> Nunca fabricar testimonios de clientes con nombres ficticios. Si el cliente entrega testimonios reales, van en `content/testimonials.json`. Mientras tanto, la sección equivalente usa mensajes de marca ("Nuestro Compromiso"), no citas atribuidas a personas inventadas.

---

## 2. Stack técnico

- **Frontend:** HTML5 semántico + CSS3 (variables nativas, sin framework/preprocesador) + JS vanilla (sin jQuery).
- **Backend de formularios:** **PHP 8.x** — elegido por compatibilidad directa con hosting compartido (Banahosting, mismo proveedor que erickhernandezarias.net), sin necesidad de proceso WSGI corriendo en background.
- **Envío de correo:** PHPMailer vía Composer (SMTP autenticado contra el dominio del cliente) — NO usar `mail()` nativo de PHP como método final (alta tasa de spam-flagging); usarlo solo como fallback documentado.
- **Hosting objetivo:** compartido tipo Banahosting o similar, con soporte PHP 8+ y Composer disponible vía SSH o instalación manual del vendor.
- **Sin base de datos** en la v1 — los mensajes se envían por correo y se registran en un log plano (`storage/logs/contact.log`) para auditoría, no en tabla SQL.

Si más adelante se requiere backend en Python (para reutilizar lógica de SEOP-IA o dashboards internos), migrar el endpoint de contacto a un microservicio Flask independiente — no mezclar stacks en el mismo hosting compartido. Documentar esa migración en un CLAUDE.md nuevo cuando aplique.

---

## 3. Sistema de diseño (tokens)

**NO usar** los colores de marca personal de Erick Hernández Arias (crimson/negro) — este es el branding propio de Sanalia.

```css
:root{
  --navy-950:#071523;
  --navy-900:#0C2036;
  --navy-800:#153350;
  --navy-700:#1E4468;
  --silver-100:#F3F5F7;
  --silver-300:#DCE1E7;
  --silver-500:#AEB8C4;
  --ink:#0E1620;
  --gold-500:#C6A15B;
  --gold-600:#A9843F;
  --paper:#F7F7F5;
}
```

- **Tipografía:** Manrope (display/headings, 700–800), Inter (cuerpo, 400–600), IBM Plex Mono (cifras, eyebrows, contadores).
- **Firma visual:** el corte de escudo del logo (`clip-path: polygon(50% 0%, 100% 13%, 100% 62%, 50% 100%, 0% 62%, 0% 13%)`) se repite en íconos de servicio, badges y CTA; y el "notch" de esquina cortada (`clip-path: polygon(0 0, calc(100% - Npx) 0, 100% Npx, 100% 100%, 0 100%)`) en tarjetas y paneles.
- **Logo:** usar `assets/logo-sanalia.jpg` (o su versión SVG si el cliente la entrega) **únicamente en header y footer** de cada página — no repetir el isotipo dentro de secciones de contenido.

---

## 4. Arquitectura de páginas

```
/
├── index.html                  → Home
├── nosotros.html               → About Us — equipo, misión, alianzas
├── servicios/
│   ├── index.html              → 2 segmentos: Empresarial + Personal/Familiar + FAQ
│   ├── vida.html
│   ├── salud.html
│   ├── viajes.html
│   ├── vehiculos.html
│   ├── accidentes-personales.html
│   └── internacionales.html
├── riesgos-generales.html      → 13 coberturas + sectores + proceso 4 pasos
├── mascotas.html               → Seguro de mascotas
├── contacto.html               → Formulario + mapa Google Maps + datos
├── blog/
│   ├── index.html              → Listado de artículos (grid 2×2)
│   ├── siniestros-62800-millones.html
│   ├── impuesto-seguro-de-vida.html
│   ├── sector-seguros-crisis-2003.html
│   └── dominicanos-sin-seguro.html
├── assets/
│   ├── css/style.css
│   ├── js/main.js
│   └── img/
│       ├── losanaliafooter.png  → Header y footer
│       ├── icono.jpg            → Favicon
│       └── equipo-sanalia.webp → Foto real del equipo (panel nosotros)
├── api/
│   ├── contact.php
│   ├── vendor/
│   └── config.php.example
└── storage/logs/contact.log
```

Cada página interna reutiliza el mismo header/footer y tokens de `style.css`; el contenido específico de cada línea de seguro vive en su propio `<main>`.

---

## 5. Estado actual (ya entregado)

`index.html` fue construido con la estructura completa: hero slider (3 slides), about con paneles superpuestos, contadores animados, grid de 4 servicios, franja de cita de marca, why-choose-us, CTA banner, riesgos generales, mascotas, tarjetas de compromiso, franja de aliados y footer con declaración + columnas de contacto. Usarlo como base de layout/CSS para las páginas nuevas — no reescribir desde cero.

Pendiente: extraer `style.css` y `main.js` del `<style>`/`<script>` inline del index a archivos separados en `assets/` antes de escalar a multipágina (evita duplicar >800 líneas de CSS por archivo).

---

## 6. Formulario de contacto — especificación funcional

**Campos:** nombre completo, email, teléfono, línea de interés (select: las 7 + riesgos generales + mascotas), mensaje.

**Validación cliente (JS, `assets/js/main.js`):**
- Nombre: requerido, mínimo 3 caracteres, sin números.
- Email: requerido, patrón RFC básico.
- Teléfono: requerido, acepta formatos dominicanos `(809) 000-0000` / `809-000-0000` / `+1 809 000 0000`.
- Mensaje: requerido, mínimo 15 caracteres, máximo 800.
- Mostrar errores inline por campo, sin bloquear el submit hasta que el usuario corrija (no usar `alert()`).
- Honeypot field oculto (`campo_control`) para bots — si viene lleno, descartar silenciosamente en frontend y backend.

**Validación servidor (`api/contact.php`), NUNCA confiar solo en el JS:**
1. Verificar método `POST`; rechazar cualquier otro con 405.
2. Verificar honeypot vacío; si lleno, responder 200 genérico sin enviar correo (no delatar al bot que fue detectado).
3. Sanitizar todos los campos (`filter_var` con `FILTER_SANITIZE_*` según tipo; `FILTER_VALIDATE_EMAIL` para email).
4. Re-validar longitudes y formato de teléfono en servidor con las mismas reglas del cliente.
5. Rate limiting básico por IP (máximo 5 envíos/hora) usando un archivo de conteo en `storage/` — evitar dependencia de base de datos en v1.
6. Si todo pasa: enviar vía PHPMailer/SMTP a `info@sanaliayasociados.com`, registrar entrada en `storage/logs/contact.log` (timestamp, IP, línea de interés — nunca loguear el mensaje completo por privacidad), responder JSON `{ok:true}`.
7. Si falla validación: responder JSON `{ok:false, errors:{campo:"mensaje"}}` con status 422.
8. Todas las respuestas son JSON — el frontend hace `fetch()` a `api/contact.php`, sin recargar la página.

**Nunca:**
- Exponer credenciales SMTP en el repo (usar `config.php` fuera de control de versiones, basado en `config.php.example`).
- Enviar el correo de forma síncrona si el hosting tiene timeout corto sin manejar el error — capturar excepciones de PHPMailer y responder error controlado, no un 500 crudo.

---

## 7. Fases de ejecución

### Fase 0 — Preparación
- Extraer CSS/JS del index actual a archivos separados.
- Confirmar credenciales SMTP del dominio con el cliente (o usar SMTP de Banahosting si el correo `info@sanaliayasociados.com` vive ahí).
- Instalar PHPMailer vía Composer en `api/`.

### Fase 1 — Páginas de servicio
- Construir `servicios/index.html` + las 6 páginas individuales, reutilizando header/footer/tokens.
- Cada página de línea de seguro: hero corto (sin slider), detalle de cobertura, CTA a WhatsApp.

### Fase 2 — Nosotros, Riesgos Generales, Mascotas
- Ampliar contenido ya existente en el home a páginas dedicadas con más profundidad (sin duplicar texto ad-verbatim del home; reescribir con más detalle).

### Fase 3 — Contacto + backend PHP
- Formulario + `api/contact.php` según §6.
- Prueba de envío real end-to-end antes de dar por cerrada la fase.

### Fase 4 — QA y performance
- Validar HTML (W3C), Lighthouse (móvil) ≥ 90 en Performance/Accessibility/SEO.
- Revisar que el logo **solo** aparezca en header/footer en las 11 páginas.
- Confirmar que ningún archivo referencia assets del template de referencia (ThemeForest).

---

## 8. Validation gates (por fase)

Antes de marcar una fase como completa, correr y pegar el resultado real, no asumir:

```bash
# Fase 0
php -v                          # confirmar PHP 8.x disponible
composer show phpmailer/phpmailer

# Fase 3
curl -X POST https://sanaliayasociados.com/api/contact.php \
  -d "nombre=Prueba&email=test@test.com&telefono=8090000000&interes=vida&mensaje=Mensaje de prueba de al menos quince caracteres&campo_control="
# Esperado: {"ok":true}

curl -X POST https://sanaliayasociados.com/api/contact.php \
  -d "nombre=Bot&email=x&telefono=1&interes=vida&mensaje=corto&campo_control=spam"
# Esperado: 200 genérico, SIN correo enviado (honeypot)
```

---

## 9. Estado actual — build completado (2026-07-26)

### Lo que existe y funciona

| Componente | Estado |
|---|---|
| `index.html` — home con hero slider 3 slides | ✅ |
| `nosotros.html` — foto equipo en panel navy recortado, sin banda ancho completo, label "ASEGURADORA DE SALUD" | ✅ |
| `servicios/index.html` — 2 segmentos (Empresarial + Personal/Familiar) + FAQ 6 preguntas | ✅ |
| `servicios/vida.html`, `salud.html`, `viajes.html`, `vehiculos.html`, `accidentes-personales.html`, `internacionales.html` | ✅ |
| `riesgos-generales.html` — 13 coberturas detalladas + 10 sectores + proceso 4 pasos | ✅ |
| `mascotas.html` — seguro mascota | ✅ |
| `contacto.html` — formulario AJAX + pre-fill por ?interes= + mapa Google Maps embed | ✅ |
| `blog/index.html` — listado 4 artículos en grid 2×2 | ✅ |
| `blog/siniestros-62800-millones.html` | ✅ |
| `blog/impuesto-seguro-de-vida.html` | ✅ |
| `blog/sector-seguros-crisis-2003.html` | ✅ |
| `blog/dominicanos-sin-seguro.html` | ✅ |
| `api/contact.php` — PHP: honeypot, rate limiting, PHPMailer SMTP | ✅ |
| `api/config.php.example` + `api/composer.json` | ✅ |
| `assets/css/style.css` — sistema completo, sin framework | ✅ |
| `assets/js/main.js` — slider, validación form, AJAX, contadores | ✅ |
| Nav: "Blog" reemplaza "Mascotas" en todas las páginas | ✅ |
| CTAs de páginas de servicio pre-llenan formulario vía `?interes=` | ✅ |
| Fotos blog relevantes por tema (no stock random) | ✅ |
| Foto real equipo `equipo-sanalia.webp` en panel nosotros | ✅ |
| Repo: `https://github.com/erickherndza/Sanalia` | ✅ |
| URL pública: `https://erickherndza.github.io/Sanalia/` | ✅ |

### Imágenes

| Archivo | Uso |
|---|---|
| `assets/img/losanaliafooter.png` | Header y footer — todas las páginas |
| `assets/img/icono.jpg` | Favicon |
| `assets/img/equipo-sanalia.webp` | Panel navy de nosotros.html |

### Estructura de servicios (2 segmentos)

**Empresarial** — Fianzas, RC, Incendio y Líneas Aliadas, Transporte de Carga → link a riesgos-generales.html
**Personal/Familiar** — Asistencia en Viaje y Decesos (destacados) + Vida, Salud, Vehículos, Accidentes, Internacionales, Mascotas

### Pendiente antes de deploy en hosting real

1. **Copiar `api/config.php`** desde `config.php.example` y rellenar credenciales SMTP reales — nunca commitear al repo.
2. **Instalar PHPMailer:** `cd api && composer install` vía SSH en el servidor.
3. **Prueba end-to-end del formulario** con credenciales SMTP reales (ver §8).
4. **QA Lighthouse móvil** ≥ 90 en Performance / Accessibility / SEO.
5. **Deploy hosting** — cliente pasa IP, usuario FTP, contraseña y ruta el 2026-07-28. Subir vía FTP directo.

### Archivos fuera del repo (`.gitignore`)

```
api/config.php
api/vendor/
storage/logs/*.log
storage/rate/*.json
```

---

## 11. Reglas de contenido permanentes (cliente)

- **NUNCA mencionar nombres de aseguradoras específicas** en el sitio (WorldWide Medical, Mapfre BHD, Reservas, Humano, Universal, Bupa, etc.). Usar siempre términos genéricos: "aseguradoras líderes del mercado", "aseguradoras especializadas en cobertura internacional", "principales ARS", etc. Razón: evitar conflicto entre socios. Los nombres sí pueden mantenerse en este CLAUDE.md como referencia interna.
- **Tres números de WhatsApp activos:** (829) 669-5001, (809) 362-4357 y (829) 616-4585. El botón FAB siempre muestra los tres.

---

## 12. Sesión 2026-07-27/28 — mejoras UI y contenido

### Cambios realizados

| Cambio | Archivos afectados |
|---|---|
| WhatsApp FAB: convertido de enlace único a menú con 2 números — clic abre popup con (829) 669-5001 y (809) 362-4357 | 17 HTML + style.css + main.js |
| Fianzas (riesgos-generales.html): descripciones añadidas para Licitación, Fiel Cumplimiento, Anticipo o Avance, Judiciales y Aduanales | riesgos-generales.html |
| Nueva sección "Coberturas Principales + Tipos de Pólizas" entre intro y las 13 coberturas | riesgos-generales.html |
| Intro riesgos-generales: definición del mercado RD ("daños / patrimoniales") + nota aclaratoria sobre SRL | riesgos-generales.html |
| Coberturas Principales expandidas de 3 a 6 ramos con descripciones más precisas | riesgos-generales.html |
| WorldWide Medical y todos los nombres de aseguradoras específicas reemplazados por términos genéricos | index.html, nosotros.html, servicios/viajes.html, salud.html, vehiculos.html, internacionales.html, blog/sector-seguros-crisis-2003.html |
| Buscador: ícono lupa al lado de "Contacto" en nav desktop; overlay oscuro con búsqueda client-side entre las 13 páginas del sitio | 17 HTML + style.css + main.js |

### Arquitectura del buscador (`main.js → initSearch()`)
- Overlay creado dinámicamente vía JS (no en cada HTML)
- Base path detectado automáticamente según profundidad de URL (`/servicios/` o `/blog/` → `../`, raíz → vacío)
- 13 páginas indexadas con título, descripción y palabras clave
- Se activa: clic en lupa del nav. Se cierra: Escape, clic fuera, botón ✕

---

## 13. Sesión 2026-07-28/29 — correcciones contenido y nueva página

### Reglas permanentes confirmadas por la cliente

- **NUNCA usar números como cuantificadores** de los propios servicios de Sanalia (coberturas, aseguradoras, planes, soluciones). Esos números cambian con negociaciones. Usar siempre lenguaje descriptivo: "las principales aseguradoras del país", "coberturas especializadas", "soluciones empresariales", etc.
- **Los números en artículos del blog** (estadísticas del sector, datos históricos) sí pueden mantenerse — son hechos verificables de terceros.
- **El horario oficial** es: L–V 8:00 AM – 5:00 PM · Sábados 8:30 AM – 12:30 PM. Verificar en TODOS los archivos al hacer cualquier cambio.
- **El header debe ser idéntico en todas las páginas**: logo + nav + tel-badge + ícono FB + botón "Cotiza Ahora". Ninguna página debe tener botones de WhatsApp en el header en lugar del "Cotiza Ahora".

### Cambios realizados

| Cambio | Archivos afectados |
|---|---|
| WA (829) 616-4585 añadido a FAB (3er botón), header contacto y footer de todas las páginas | 17 HTML |
| Seguro de Vehículos movido de "Seguros de Persona" a "Riesgos Generales" en servicios/index.html | servicios/index.html |
| Horario corregido: 8:00–5:00 / 8:30–12:30 en todas las páginas | index.html, contacto.html, servicios/index.html, riesgos-generales.html, mascotas.html |
| Eliminados todos los cuantificadores numéricos: "20+ aseguradoras", "13 coberturas", "12 coberturas", "7 líneas", "13 soluciones empresariales", "12 INCLUIDAS", "Las 12 Coberturas", "Las 13 Coberturas", "12/24 meses" | múltiples páginas |
| "comparamos" → "analizamos" / "gestionamos" / "Evaluamos" en todo el sitio | múltiples páginas |
| Plan Senior eliminado de salud.html | servicios/salud.html |
| viajes.html reescrito: "Asistencia en Viaje" — solo emergencias, 28 coberturas en 3 categorías, callout dorado diferenciador | servicios/viajes.html |
| internacionales.html reescrito: "Seguro Médico Internacional" — largo plazo, doble cobertura, oncología, red global | servicios/internacionales.html |
| Header de contacto.html igualado al de inicio ("Cotiza Ahora" en lugar de dos botones WA) | contacto.html |
| Nueva página servicios/exequial.html: Cobertura Exequial (asistencia funeraria, inhumación, apoyo familiar, condiciones de carencia) | servicios/exequial.html |
| Cobertura Exequial añadida al listado y footer de servicios/index.html | servicios/index.html |
| Sección empresarial renombrada: "Seguros Corporativos y de Fianzas" → "Seguros Corporativos — Fianzas — Garantía" | servicios/index.html |
| Tiempos de respuesta sin números: "menos de 24 horas" → "con rapidez" / "a la brevedad" | vida.html, vehiculos.html, servicios/index.html, contacto.html |

### Arquitectura de páginas actualizada

```
servicios/
├── index.html
├── vida.html
├── salud.html
├── viajes.html          ← reescrito: Asistencia en Viaje (emergencias)
├── internacionales.html ← reescrito: Seguro Médico Internacional (largo plazo)
├── vehiculos.html
├── accidentes-personales.html
├── exequial.html        ← NUEVO: Cobertura Exequial
└── ...
```

### Diferenciación Viajes vs Internacional (mandato del cliente)

| | Asistencia en Viaje (`viajes.html`) | Seguro Médico Internacional (`internacionales.html`) |
|---|---|---|
| Duración | Temporal (duración del viaje) | Largo plazo / permanente |
| Tipo de evento | Solo emergencias imprevistas | También atención programada |
| Oncología / cirugía electiva | ✗ | ✓ |
| Cobertura local RD | ✗ | ✓ (100%) |
| CTA | "Cotizar mi viaje" | "Solicitar asesoría" |

---

## 10. Lecciones transferibles

**Sesión 1 (2026-07-22):** El script de reemplazo buscaba `alt="Sanalia &amp; Asociados"` (entidad HTML) pero las páginas de servicios tenían `alt="Sanalia & Asociados"` (ampersand crudo). Resultado: el reemplazo del logo de footer fallaba silenciosamente en 7 páginas. Fix mínimo: regex que acepte ambas variantes `(&amp;|&)`. Transferible a: cualquier script de batch sobre HTML — nunca asumir que las entidades son uniformes entre archivos generados en momentos distintos.

**Sesión 1 (2026-07-22):** PHP no permite declaraciones `use` dentro de bloques condicionales (`if/else`). El `use PHPMailer\...` dentro de un `else { require $vendor; }` producía un error de parse. Fix: mover el `require` al tope del archivo (con `if file_exists`) y sustituir la condición por `class_exists('PHPMailer\PHPMailer\PHPMailer')`. Transferible a: siempre cargar dependencias opcionales en el scope global del archivo, nunca dentro de un bloque.
