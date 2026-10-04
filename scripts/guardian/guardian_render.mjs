// guardian_render.mjs — lo que Google ve DESPUÉS de ejecutar JavaScript (plantilla plan-seo).
//
// curl no ejecuta JS: un auto-redirect por idioma en JavaScript escondió un sitio entero a
// Google durante dos meses (Googlebot renderiza en inglés y sin storage) sin que ninguna
// auditoría lo viera. Abre las páginas clave con Chromium como Googlebot smartphone,
// locale en-US y sin cookies, y exige: URL final = la pedida, <html lang> correcto,
// canonical renderizado = su URL, sin errores de JS ni violaciones de CSP.
//
// Uso: node guardian_render.mjs [ruta/guardian.json]   (usa "render" del json)
// Requiere: npm i playwright && npx playwright install chromium
// macOS viejo sin Chromium de Playwright: PW_CHANNEL=chrome node guardian_render.mjs
import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const cfgPath = process.argv[2] || join(dirname(fileURLToPath(import.meta.url)), 'guardian.json');
const CFG = JSON.parse(readFileSync(cfgPath, 'utf8'));
const SITIO = CFG.sitio.replace(/\/$/, '');
const PAGINAS = Object.entries(CFG.render || { '/': 'es' });
const GOOGLEBOT =
  'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) ' +
  'Chrome/126.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

const errores = [];
const browser = await chromium.launch(process.env.PW_CHANNEL ? { channel: process.env.PW_CHANNEL } : {});
try {
  for (const [ruta, idioma] of PAGINAS) {
    const ctx = await browser.newContext({ userAgent: GOOGLEBOT, locale: 'en-US', viewport: { width: 412, height: 915 } });
    const page = await ctx.newPage();
    const jsErrores = [];
    page.on('pageerror', (e) => jsErrores.push(e.message));
    page.on('console', (m) => { if (/Content Security Policy/i.test(m.text())) jsErrores.push('CSP: ' + m.text().slice(0, 160)); });
    const url = SITIO + ruta;
    try {
      await page.goto(url, { waitUntil: 'load', timeout: 45000 });
      await page.waitForTimeout(4000);
    } catch (e) {
      errores.push(`${ruta}: no cargó (${e.message.split('\n')[0]})`);
      await ctx.close();
      continue;
    }
    const final = page.url().split('#')[0];
    const lang = await page.evaluate(() => document.documentElement.lang || '');
    const canon = await page.evaluate(() => document.querySelector('link[rel="canonical"]')?.href || '');
    if (final.replace(/\/$/, '') !== url.replace(/\/$/, '')) errores.push(`${ruta}: Googlebot termina en ${final} (redirect por JavaScript)`);
    if (!lang.toLowerCase().startsWith(idioma)) errores.push(`${ruta}: <html lang="${lang}">, se esperaba ${idioma}`);
    if (canon.replace(/\/$/, '') !== url.replace(/\/$/, '')) errores.push(`${ruta}: canonical renderizado = ${canon || '(ninguno)'}`);
    for (const e of jsErrores) errores.push(`${ruta}: ${e}`);
    console.log(`  ${final.replace(/\/$/, '') === url.replace(/\/$/, '') && lang.startsWith(idioma) ? 'ok   ' : 'FALLO'} ${ruta}  lang=${lang}`);
    await ctx.close();
  }
} finally {
  await browser.close();
}
if (errores.length) {
  console.log(`\n✗ ${errores.length} error(es) en lo que ve Googlebot:`);
  for (const e of errores) console.log('  ERROR  ' + e);
  process.exit(1);
}
console.log(`\n✓ Googlebot ve las ${PAGINAS.length} páginas clave correctamente.`);
