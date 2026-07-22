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

Mapear el ritmo narrativo del template de referencia a un sitio multipágina:

```
/
├── index.html                  → Home (ya construido, ver §5)
├── nosotros.html                → About Us ampliado (equipo, misión, alianzas)
├── servicios/
│   ├── index.html                → Our Services (grid completo, 7 líneas)
│   ├── vida.html
│   ├── salud.html
│   ├── viajes.html
│   ├── vehiculos.html
│   ├── accidentes-personales.html
│   └── internacionales.html
├── riesgos-generales.html        → Las 13 coberturas empresariales, detalladas
├── mascotas.html                  → Producto destacado ampliado
├── contacto.html                  → Formulario + mapa + datos
├── assets/
│   ├── logo-sanalia.jpg
│   ├── css/style.css
│   ├── js/main.js
│   └── icons/ (SVG propios, no del template de referencia)
├── api/
│   ├── contact.php                → Endpoint de validación y envío
│   ├── vendor/                     → Composer (PHPMailer)
│   └── config.php.example          → Plantilla de credenciales SMTP (NO commitear config.php real)
└── storage/
    └── logs/contact.log
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

## 9. Lección transferible (a llenar al cierre de cada sesión)

> Espacio para que Claude Code documente, al final de cada sesión de trabajo, una lección de causa-raíz aprendida (no una lista de tareas completadas). Ejemplo de formato:
> **Sesión N — [fecha]:** [síntoma] era en realidad [causa raíz], no [causa asumida inicialmente]. Se corrigió con [fix mínimo]. Transferible a: [dónde más aplica este patrón].
