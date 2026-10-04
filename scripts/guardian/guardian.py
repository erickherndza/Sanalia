#!/usr/bin/env python3
"""
guardian.py — revisa el sitio EN VIVO y falla (exit 1) si algo ya corregido se rompió.
Plantilla de la skill plan-seo (nacida en erickhernandezarias.net, 2026-09-25): convierte
cada arreglo SEO/GEO en una comprobación permanente que corre sola (GitHub Actions diario
y tras cada push; GitHub envía correo si falla).

Todo lo específico del proyecto vive en guardian.json (misma carpeta). Stdlib + curl.
Uso: python3 guardian.py [ruta/guardian.json]

Cubre:
  1. Acceso      — robots.txt sin bloqueo total, sitemap sin duplicados ni hosts ajenos,
                   cada URL del sitemap responde 200 SIN redirect.
  2. Indexación  — sin noindex, canonical = su propia URL.
  3. Contenido   — meta description < 155, JSON-LD válido, sección Fuentes con <a href>,
                   script de medición cargado exactamente 1 vez.
  4. Plataforma  — cabeceras de seguridad (sin duplicados ni obsoletas), archivos ocultos
                   bloqueados, redirects en 1 salto, rutas privadas protegidas, endpoints
                   de formulario vivos, rutas clave iguales para curl y para un navegador.
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from html import unescape

CFG_PATH = sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(__file__), "guardian.json")
CFG = json.load(open(CFG_PATH, encoding="utf-8"))
SITIO = CFG["sitio"].rstrip("/")
DOMINIO = SITIO.split("://", 1)[1].removeprefix("www.")
UA = "Mozilla/5.0 (compatible; plan-seo-guardian/1.0)"
NAVEGADOR = ["-A", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) "
             "Chrome/140.0 Safari/537.36", "-H", "Accept: text/html,application/xhtml+xml,*/*;q=0.8",
             "-H", "Sec-Fetch-Mode: navigate", "-H", "Sec-Fetch-Dest: document"]

errores: list[str] = []
avisos: list[str] = []


def curl(url: str, head: bool = False, extra: list | None = None) -> tuple[int, str, str, str]:
    """Una petición sin seguir redirects → (status, redirect, cabeceras, cuerpo)."""
    time.sleep(CFG.get("pausa_segundos", 0))  # ritmo de rastreador educado (GoDaddy desafía las ráfagas)
    args = ["curl", "-s", "--max-time", "30", "-w", "\n__META__%{http_code} %{redirect_url}"]
    args += extra if extra else ["-A", UA]
    args += ["-I"] if head else ["-D", "-"]
    out = subprocess.run(args + [url], capture_output=True, text=True, errors="replace").stdout
    resto, _, meta = out.rpartition("\n__META__")
    code, _, redirect = meta.partition(" ")
    cabeceras, _, cuerpo = resto.partition("\n\n")
    return int(code or 0), redirect.strip(), cabeceras, cuerpo


# Sanalia (GoDaddy, 2026-10-04): tras ~25 peticiones seguidas el hosting responde CUALQUIER URL
# —robots.txt, sitemap, CSS— con 200 y una página "One moment, please... verifying". Un 200 no
# basta: si el cuerpo es ese desafío, el rastreador indexa/lee basura.
DESAFIO = re.compile(r"Please wait while your request is being verified|<title>\s*One moment, please", re.I)


def es_desafio(cuerpo: str) -> bool:
    return bool(DESAFIO.search(cuerpo[:20000]))


def cabecera(cabeceras: str, nombre: str) -> list[str]:
    return [m.group(1).strip() for m in re.finditer(rf"^{nombre}:\s*(.*)$", cabeceras, re.I | re.M)]


def redirect_chain(url: str) -> tuple[int, str]:
    time.sleep(CFG.get("pausa_segundos", 0))
    out = subprocess.run(["curl", "-s", "-o", "/dev/null", "-A", UA, "-L", "--max-time", "30",
                          "-w", "%{num_redirects} %{url_effective}", url],
                         capture_output=True, text=True).stdout.split(" ", 1)
    return int(out[0] or 0), (out[1].strip() if len(out) > 1 else "")


# ── 1. robots.txt y sitemap ────────────────────────────────────────────────
def check_robots_y_sitemap() -> list[str]:
    code, _, _, robots = curl(f"{SITIO}/robots.txt")
    if code != 200:
        errores.append(f"robots.txt responde {code}")
    elif es_desafio(robots):
        errores.append("robots.txt entrega la página anti-bots del hosting (200 con HTML) — rastreadores bloqueados")
    elif re.search(r"^User-agent:\s*\*\s*\n(?:.*\n)*?Disallow:\s*/\s*$", robots, re.I | re.M) and \
            not re.search(r"^Allow:\s*/\s*$", robots, re.I | re.M):
        errores.append("robots.txt bloquea todo el sitio (Disallow: /)")
    for bot in CFG.get("bots_ia", ["GPTBot", "ClaudeBot", "PerplexityBot", "OAI-SearchBot"]):
        c, _, _, cuerpo = curl(SITIO + "/", extra=["-A", f"Mozilla/5.0 (compatible; {bot}/1.0)"])
        if c != 200:
            errores.append(f"el bot {bot} recibe {c} en la home (¿WAF / Bot Fight Mode?)")
        elif es_desafio(cuerpo):
            errores.append(f"el bot {bot} recibe la página anti-bots del hosting en la home")

    code, _, _, xml = curl(SITIO + CFG.get("sitemap", "/sitemap.xml"))
    if code != 200:
        errores.append(f"sitemap responde {code}")
        return []
    locs = [unescape(l).strip() for l in re.findall(r"<loc>([^<]+)</loc>", xml)]
    if not locs:
        errores.append("sitemap sin URLs" + (" (entrega la página anti-bots del hosting)" if es_desafio(xml) else ""))
        return []
    if "<sitemapindex" in xml:
        hijos = locs
        locs = []
        for h in hijos[:20]:
            _, _, _, x = curl(h)
            locs += [unescape(l).strip() for l in re.findall(r"<loc>([^<]+)</loc>", x)]
    repetidas = sorted({l for l in locs if locs.count(l) > 1})
    if repetidas:
        errores.append(f"sitemap con {len(repetidas)} URLs repetidas (ej. {repetidas[0]})")
    fuera = [l for l in locs if not l.startswith(SITIO + "/") and l != SITIO]
    if fuera:
        errores.append(f"sitemap con {len(fuera)} URLs fuera de {SITIO} (ej. {fuera[0]}) — ¿www / http?")
    return list(dict.fromkeys(locs))[: CFG.get("max_paginas", 400)]


# ── 2 y 3. Cada página del sitemap ─────────────────────────────────────────
def check_pagina(url: str) -> list[str]:
    fallos: list[str] = []
    code, redirect, cab, html = curl(url)
    if code != 200:
        return [f"{url} responde {code}" + (f" → {redirect}" if redirect else "") + " (el sitemap solo debe listar URLs finales 200)"]
    ruta = url.removeprefix(SITIO) or "/"
    if es_desafio(html):
        return [f"{ruta}: entrega la página anti-bots del hosting en vez del contenido"]
    if re.search(r'<meta[^>]+name=["\']robots["\'][^>]+noindex', html, re.I) or \
            "noindex" in " ".join(cabecera(cab, "x-robots-tag")).lower():
        fallos.append(f"{ruta}: tiene noindex pero está en el sitemap")
    canon = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)', html, re.I) or \
        re.search(r'<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical', html, re.I)
    if not canon:
        fallos.append(f"{ruta}: sin canonical")
    elif unescape(canon.group(1)).rstrip("/") != url.rstrip("/"):
        fallos.append(f"{ruta}: canonical apunta a {canon.group(1)}")
    desc = re.search(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)', html, re.I)
    if not desc:
        fallos.append(f"{ruta}: sin meta description")
    elif len(unescape(desc.group(1))) >= CFG.get("max_meta_description", 155):
        fallos.append(f"{ruta}: meta description de {len(unescape(desc.group(1)))} caracteres")
    for i, bloque in enumerate(re.findall(r'<script[^>]+application/ld\+json[^>]*>(.*?)</script>', html, re.S | re.I), 1):
        try:
            json.loads(bloque)
        except json.JSONDecodeError as e:
            fallos.append(f"{ruta}: JSON-LD #{i} inválido ({e.msg})")
    patron = CFG.get("script_medicion")  # ej. "assets/js/tracking.js" o "gtm.js?id=GTM-XXXX"
    if patron:
        n = len(re.findall(r'<script[^>]+src=["\'][^"\']*' + re.escape(patron), html))
        if n != 1:
            fallos.append(f"{ruta}: carga {patron} {n} veces (debe ser 1)")
    fuentes = re.search(r"<h2[^>]*>\s*(Fuentes|Sources|Referencias)\s*</h2>(.*?)(<h2|</article>|</main>)", html, re.S | re.I)
    if fuentes and "<a " not in fuentes.group(2):
        fallos.append(f"{ruta}: sección {fuentes.group(1)} sin enlace <a href> a la fuente")
    if "location.replace" in html and "hreflang" in html:
        avisos.append(f"{ruta}: location.replace inline — revisar que no redirija a Googlebot por idioma")
    return fallos


# ── 4. Plataforma ──────────────────────────────────────────────────────────
def check_plataforma() -> None:
    obligatorias = CFG.get("cabeceras_obligatorias", ["strict-transport-security", "x-content-type-options"])
    for ruta in CFG.get("rutas_clave", ["/"]):
        _, _, cab, _ = curl(SITIO + ruta, head=True)
        for c in obligatorias:
            if not cabecera(cab, c):
                errores.append(f"{ruta}: falta la cabecera {c}")
        for c in ["x-frame-options", "referrer-policy", "x-content-type-options",
                  "strict-transport-security", "content-security-policy"]:
            if len(cabecera(cab, c)) > 1:
                errores.append(f"{ruta}: cabecera {c} duplicada")
        for c in ["expect-ct", "x-xss-protection"]:
            if cabecera(cab, c):
                errores.append(f"{ruta}: cabecera obsoleta {c}")
        # Misma respuesta para curl y para una navegación real (CDN que tratan distinto las navegaciones)
        c1, _, _, _ = curl(SITIO + ruta, head=True)
        c2, _, _, _ = curl(SITIO + ruta, head=True, extra=NAVEGADOR)
        if c1 != c2:
            errores.append(f"{ruta}: curl recibe {c1} pero un navegador recibe {c2}")

    for oculto in CFG.get("archivos_ocultos", ["/.git/config", "/.env", "/.DS_Store", "/.htaccess"]):
        code, _, cab, cuerpo = curl(SITIO + oculto)
        # Un 200 que es una página HTML es el soft-404 (se reporta aparte), no el archivo expuesto
        es_html = "text/html" in " ".join(cabecera(cab, "content-type")).lower() and "<html" in cuerpo[:500].lower()
        if code not in (403, 404, 410) and not (code == 200 and es_html):
            errores.append(f"{oculto} responde {code} y entrega el archivo (debe ser 403/404)")

    code, _, _, _ = curl(SITIO + "/plan-seo-ruta-que-no-existe-" + "x" * 6)
    if code != 404:
        errores.append(f"una URL inexistente responde {code} (debe ser 404 real, no soft-404)")

    for ruta, esperado in CFG.get("rutas_privadas", {}).items():  # ej. {"/admin/": 302}
        for extra in (None, NAVEGADOR):
            code, _, _, _ = curl(SITIO + ruta, head=True, extra=extra)
            if code != esperado:
                quien = "navegador" if extra else "curl"
                errores.append(f"{ruta} ({quien}) responde {code}, se esperaba {esperado}")

    for ruta in CFG.get("endpoints_formulario", []):  # ej. ["/mail.php"] — GET debe dar 4xx≠404
        for extra in (None, NAVEGADOR):
            code, _, _, _ = curl(SITIO + ruta, head=True, extra=extra)
            if code in (0, 404) or code >= 500:
                errores.append(f"{ruta} responde {code} (el formulario no llega a su servidor)")

    raiz = SITIO.split("://", 1)[1]
    esperados = {f"http://{DOMINIO}/": f"{SITIO}/", f"https://{DOMINIO}/": f"{SITIO}/",
                 f"http://{raiz}/": f"{SITIO}/"}
    esperados.update({SITIO + o: SITIO + d for o, d in CFG.get("redirects", {}).items()})
    for origen, destino in esperados.items():
        if origen.rstrip("/") == destino.rstrip("/"):
            continue
        saltos, final = redirect_chain(origen)
        if saltos == 0 and es_desafio(curl(origen)[3]):
            errores.append(f"{origen}: el hosting entrega la página anti-bots en vez del redirect")
        elif final.rstrip("/") != destino.rstrip("/"):
            errores.append(f"{origen} termina en {final}, debería terminar en {destino}")
        elif saltos > 1:
            errores.append(f"{origen} llega a {destino} en {saltos} saltos (debe ser 1)")

    if CFG.get("llms_txt", True):
        code, _, cab, cuerpo = curl(f"{SITIO}/llms.txt")
        if code != 200 or len(cuerpo) < 200:
            errores.append(f"/llms.txt responde {code} ({len(cuerpo)} bytes)")
        elif "utf-8" not in " ".join(cabecera(cab, "content-type")).lower():
            errores.append("/llms.txt sin charset utf-8 (acentos rotos en lectores de IA)")

    caa = subprocess.run(["dig", "+short", "CAA", DOMINIO], capture_output=True, text=True).stdout
    if '" ' in caa:
        errores.append(f"registro CAA con espacio delante del valor: {caa.strip().splitlines()[0]}")
    if CFG.get("dnssec", False):
        ds = subprocess.run(["dig", "+short", "DS", DOMINIO], capture_output=True, text=True).stdout.strip()
        if not ds:
            avisos.append("DNSSEC sin registro DS en el registrador")


def main() -> int:
    urls = check_robots_y_sitemap()
    with ThreadPoolExecutor(max_workers=CFG.get("hilos", 8)) as pool:
        for fallos in pool.map(check_pagina, urls):
            errores.extend(fallos)
    check_plataforma()
    print(f"Guardián — {SITIO} — {len(urls)} páginas del sitemap revisadas\n")
    for a in avisos:
        print(f"  AVISO  {a}")
    for e in errores:
        print(f"  ERROR  {e}")
    if errores:
        print(f"\n✗ {len(errores)} error(es). Algo que ya estaba corregido se volvió a romper.")
        return 1
    print("\n✓ Todo en orden.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
