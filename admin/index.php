<?php
/**
 * Sanalia & Asociados — Panel de solicitudes
 * Acceso: /admin/  →  contraseña requerida
 */

declare(strict_types=1);

session_start();

/* ── Configuración ───────────────────────────────────────────── */

define('ADMIN_PASSWORD_PLAIN', 'Sanalia2026!'); // cambia después del primer acceso

$submissions_dir = __DIR__ . '/../storage/submissions/';
$error           = '';

/* ── Autenticación ───────────────────────────────────────────── */

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD_PLAIN) {
        $_SESSION['sanalia_admin'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $error = 'Contraseña incorrecta.';
}

$auth = !empty($_SESSION['sanalia_admin']);

/* ── Exportar CSV ────────────────────────────────────────────── */

if ($auth && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $submissions_csv = [];
    if (is_dir($submissions_dir)) {
        foreach (glob($submissions_dir . '*.json') as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (is_array($entry)) $submissions_csv[] = $entry;
            }
        }
        usort($submissions_csv, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="solicitudes-sanalia-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // BOM para que Excel abra correctamente con tildes
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Fecha', 'Nombre', 'Email', 'Teléfono', 'Línea de interés', 'Mensaje'], ';');
    foreach ($submissions_csv as $s) {
        fputcsv($out, [
            $s['fecha']    ?? '',
            $s['nombre']   ?? '',
            $s['email']    ?? '',
            $s['telefono'] ?? '',
            $s['interes']  ?? '',
            $s['mensaje']  ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

/* ── Cargar solicitudes ──────────────────────────────────────── */

$submissions = [];
if ($auth && is_dir($submissions_dir)) {
    foreach (glob($submissions_dir . '*.json') as $file) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry)) $submissions[] = $entry;
        }
    }
    usort($submissions, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
}

/* ── Mapa de interés → etiqueta ──────────────────────────────── */

$map_interes = [
    'vida'                  => 'Vida',
    'salud-persona'         => 'Salud Personal',
    'viajes'                => 'Asistencia en Viaje',
    'vehiculos'             => 'Vehículos',
    'salud'                 => 'Salud',
    'accidentes-personales' => 'Accidentes Personales',
    'internacionales'       => 'Médico Internacional',
    'riesgos-generales'     => 'Riesgos Generales',
    'mascotas'              => 'Mascotas',
    'otro'                  => 'Otro',
];

/* ── Helper: limpiar teléfono para WhatsApp ──────────────────── */

function wa_number(string $tel): string {
    $clean = preg_replace('/[^\d]/', '', $tel);
    // Si empieza con 1 y tiene 11 dígitos → ok
    // Si tiene 10 dígitos (809...) → añadir 1
    if (strlen($clean) === 10) $clean = '1' . $clean;
    return $clean;
}

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel de Solicitudes — Sanalia & Asociados</title>
<meta name="robots" content="noindex, nofollow">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy-950: #071523;
    --navy-900: #0C2036;
    --navy-800: #153350;
    --navy-700: #1E4468;
    --gold-500: #C6A15B;
    --gold-600: #A9843F;
    --silver-100: #F3F5F7;
    --silver-300: #DCE1E7;
    --silver-500: #AEB8C4;
    --ink: #0E1620;
    --paper: #F7F7F5;
    --green: #1a7f4b;
    --green-bg: #e8f5ee;
  }

  body {
    font-family: 'Inter', system-ui, sans-serif;
    background: var(--silver-100);
    color: var(--ink);
    min-height: 100vh;
  }

  /* ── Login ── */
  .login-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 2rem;
  }

  .login-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(7,21,35,.12);
    padding: 2.5rem 2rem;
    width: 100%;
    max-width: 360px;
    text-align: center;
  }

  .login-card .brand {
    font-family: 'Manrope', system-ui, sans-serif;
    font-weight: 800;
    font-size: 1.25rem;
    color: var(--navy-900);
    margin-bottom: .25rem;
  }

  .login-card .sub {
    font-size: .8rem;
    color: var(--silver-500);
    margin-bottom: 2rem;
    letter-spacing: .05em;
    text-transform: uppercase;
  }

  .login-card input[type="password"] {
    width: 100%;
    padding: .75rem 1rem;
    border: 1.5px solid var(--silver-300);
    border-radius: 8px;
    font-size: 1rem;
    outline: none;
    transition: border-color .2s;
    margin-bottom: 1rem;
  }

  .login-card input[type="password"]:focus { border-color: var(--navy-700); }

  .btn-login {
    width: 100%;
    padding: .75rem;
    background: var(--navy-900);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
  }

  .btn-login:hover { background: var(--navy-700); }

  .login-error {
    margin-top: 1rem;
    color: #c0392b;
    font-size: .875rem;
  }

  /* ── Topbar ── */
  .topbar {
    background: var(--navy-900);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .875rem 2rem;
    gap: 1rem;
    position: sticky;
    top: 0;
    z-index: 100;
  }

  .topbar .brand {
    font-family: 'Manrope', system-ui, sans-serif;
    font-weight: 800;
    font-size: 1rem;
  }

  .topbar .brand span { color: var(--gold-500); }

  .topbar-actions { display: flex; gap: .75rem; align-items: center; }

  .btn-csv {
    background: var(--gold-500);
    color: var(--navy-950);
    border: none;
    padding: .4rem .875rem;
    border-radius: 6px;
    font-size: .8rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    transition: background .2s;
  }

  .btn-csv:hover { background: var(--gold-600); }

  .btn-logout {
    background: transparent;
    border: 1.5px solid rgba(255,255,255,.3);
    color: #fff;
    padding: .4rem .875rem;
    border-radius: 6px;
    font-size: .8rem;
    cursor: pointer;
    transition: border-color .2s;
  }

  .btn-logout:hover { border-color: #fff; }

  /* ── Main ── */
  .main {
    max-width: 1280px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
  }

  /* ── Stats ── */
  .stats {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
  }

  .stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
    flex: 1;
    min-width: 140px;
    box-shadow: 0 1px 6px rgba(7,21,35,.07);
  }

  .stat-card .num {
    font-family: 'IBM Plex Mono', monospace, system-ui;
    font-size: 2rem;
    font-weight: 700;
    color: var(--navy-900);
    line-height: 1;
  }

  .stat-card .label {
    font-size: .78rem;
    color: var(--silver-500);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-top: .35rem;
  }

  /* ── Section title ── */
  .section-title {
    font-family: 'Manrope', system-ui, sans-serif;
    font-weight: 700;
    font-size: 1rem;
    color: var(--navy-900);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
  }

  .section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--silver-300);
  }

  /* ── Table ── */
  .table-wrap {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 6px rgba(7,21,35,.07);
    overflow: hidden;
  }

  table { width: 100%; border-collapse: collapse; font-size: .875rem; }

  thead { background: var(--navy-950); color: #fff; }

  thead th {
    padding: .75rem 1rem;
    text-align: left;
    font-family: 'IBM Plex Mono', monospace, system-ui;
    font-size: .72rem;
    letter-spacing: .07em;
    text-transform: uppercase;
    font-weight: 500;
    white-space: nowrap;
  }

  tbody tr {
    border-bottom: 1px solid var(--silver-100);
    cursor: pointer;
    transition: background .15s;
  }

  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: #eef2f7; }
  tbody tr.replied { background: #f0faf4; }
  tbody tr.replied:hover { background: #e3f5ea; }

  td {
    padding: .875rem 1rem;
    vertical-align: middle;
    line-height: 1.5;
  }

  td.fecha {
    font-family: 'IBM Plex Mono', monospace, system-ui;
    font-size: .72rem;
    color: var(--silver-500);
    white-space: nowrap;
  }

  td.nombre { font-weight: 600; color: var(--navy-900); }

  td.mensaje-preview {
    max-width: 220px;
    color: #666;
    font-size: .8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .badge {
    display: inline-block;
    padding: .25rem .625rem;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    background: var(--navy-800);
    color: var(--gold-500);
    white-space: nowrap;
  }

  .badge-replied {
    background: var(--green-bg);
    color: var(--green);
    font-size: .68rem;
    font-weight: 700;
    padding: .2rem .5rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: .25rem;
  }

  .empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--silver-500);
    font-size: .9rem;
  }

  /* ── Drawer ── */
  .drawer-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(7,21,35,.5);
    z-index: 200;
    backdrop-filter: blur(2px);
  }

  .drawer-overlay.open { display: block; }

  .drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 480px;
    max-width: 100vw;
    background: #fff;
    z-index: 201;
    box-shadow: -8px 0 40px rgba(7,21,35,.18);
    transform: translateX(100%);
    transition: transform .28s cubic-bezier(.16,1,.3,1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .drawer.open { transform: translateX(0); }

  .drawer-header {
    background: var(--navy-900);
    color: #fff;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-shrink: 0;
  }

  .drawer-header .d-nombre {
    font-family: 'Manrope', system-ui, sans-serif;
    font-weight: 800;
    font-size: 1.15rem;
    line-height: 1.2;
  }

  .drawer-header .d-fecha {
    font-family: 'IBM Plex Mono', monospace, system-ui;
    font-size: .72rem;
    color: var(--silver-500);
    margin-top: .25rem;
  }

  .drawer-close {
    background: rgba(255,255,255,.12);
    border: none;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background .2s;
  }

  .drawer-close:hover { background: rgba(255,255,255,.25); }

  .drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
  }

  /* ── Contact pills ── */
  .contact-pills {
    display: flex;
    flex-direction: column;
    gap: .625rem;
  }

  .contact-pill {
    display: flex;
    align-items: center;
    gap: .75rem;
    background: var(--silver-100);
    border-radius: 10px;
    padding: .75rem 1rem;
    text-decoration: none;
    color: var(--ink);
    transition: background .15s, transform .15s;
    border: 1.5px solid transparent;
  }

  .contact-pill:hover {
    background: #e8edf3;
    border-color: var(--silver-300);
    transform: translateX(2px);
  }

  .pill-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
  }

  .pill-icon.wa { background: #25d366; color: #fff; }
  .pill-icon.em { background: var(--navy-800); color: var(--gold-500); }

  .pill-info { flex: 1; min-width: 0; }

  .pill-label {
    font-size: .7rem;
    color: var(--silver-500);
    text-transform: uppercase;
    letter-spacing: .05em;
    font-weight: 600;
  }

  .pill-value {
    font-size: .9rem;
    font-weight: 600;
    color: var(--navy-900);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .pill-action {
    font-size: .72rem;
    font-weight: 700;
    padding: .3rem .7rem;
    border-radius: 6px;
    white-space: nowrap;
  }

  .pill-action.wa-btn {
    background: #25d366;
    color: #fff;
  }

  .pill-action.em-btn {
    background: var(--navy-900);
    color: #fff;
  }

  /* ── Interés ── */
  .d-interes {
    display: flex;
    align-items: center;
    gap: .75rem;
  }

  .d-interes .label {
    font-size: .78rem;
    color: var(--silver-500);
    text-transform: uppercase;
    letter-spacing: .05em;
  }

  /* ── Mensaje ── */
  .d-mensaje-wrap {
    background: var(--silver-100);
    border-radius: 10px;
    padding: 1rem 1.25rem;
    border-left: 3px solid var(--gold-500);
  }

  .d-mensaje-label {
    font-size: .72rem;
    color: var(--silver-500);
    text-transform: uppercase;
    letter-spacing: .05em;
    font-weight: 600;
    margin-bottom: .5rem;
  }

  .d-mensaje-text {
    font-size: .9rem;
    line-height: 1.7;
    color: var(--ink);
    white-space: pre-wrap;
  }

  /* ── Drawer footer ── */
  .drawer-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--silver-300);
    display: flex;
    gap: .75rem;
    flex-shrink: 0;
  }

  .btn-mark {
    flex: 1;
    padding: .7rem;
    border: 1.5px solid var(--silver-300);
    border-radius: 8px;
    background: #fff;
    color: var(--navy-900);
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
  }

  .btn-mark:hover {
    background: var(--green-bg);
    border-color: var(--green);
    color: var(--green);
  }

  .btn-mark.active {
    background: var(--green-bg);
    border-color: var(--green);
    color: var(--green);
  }

  /* ── Responsive ── */
  @media (max-width: 768px) {
    .topbar { padding: .75rem 1rem; }
    .topbar .brand { font-size: .85rem; }
    .main { padding: 1.25rem 1rem; }
    table { font-size: .8rem; }
    thead th, td { padding: .625rem .75rem; }
    td.mensaje-preview { display: none; }
    .drawer { width: 100vw; }
  }
</style>
</head>
<body>

<?php if (!$auth): ?>

<div class="login-wrap">
  <div class="login-card">
    <div class="brand">Sanalia &amp; Asociados</div>
    <div class="sub">Panel de Solicitudes</div>
    <form method="POST" autocomplete="off">
      <input type="password" name="password" placeholder="Contraseña" autofocus required>
      <button type="submit" class="btn-login">Entrar</button>
    </form>
    <?php if ($error): ?>
      <p class="login-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>

<div class="topbar">
  <div class="brand">
    Sanalia &amp; Asociados &mdash; <span>Solicitudes</span>
  </div>
  <div class="topbar-actions">
    <a href="?export=csv" class="btn-csv">
      &#8595; Exportar Excel
    </a>
    <form method="POST" style="margin:0">
      <button type="submit" name="logout" class="btn-logout">Cerrar sesión</button>
    </form>
  </div>
</div>

<div class="main">

  <?php
    $total      = count($submissions);
    $mes_actual = date('Y-m');
    $hoy_str    = date('Y-m-d');
    $este_mes   = 0;
    $hoy        = 0;
    foreach ($submissions as $s) {
      if (str_starts_with($s['fecha'], $mes_actual)) $este_mes++;
      if (str_starts_with($s['fecha'], $hoy_str))    $hoy++;
    }
  ?>

  <div class="stats">
    <div class="stat-card">
      <div class="num"><?= $total ?></div>
      <div class="label">Total solicitudes</div>
    </div>
    <div class="stat-card">
      <div class="num"><?= $este_mes ?></div>
      <div class="label">Este mes</div>
    </div>
    <div class="stat-card">
      <div class="num"><?= $hoy ?></div>
      <div class="label">Hoy</div>
    </div>
  </div>

  <div class="section-title">Todas las solicitudes — clic para ver detalle</div>

  <div class="table-wrap">
    <?php if (empty($submissions)): ?>
      <div class="empty">No hay solicitudes registradas aún.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Nombre</th>
          <th>Teléfono</th>
          <th>Interés</th>
          <th>Mensaje</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $i => $s):
          $wa_num   = wa_number($s['telefono'] ?? '');
          $interes_label = $map_interes[$s['interes'] ?? ''] ?? ($s['interes'] ?? '');
          $wa_msg   = urlencode("Hola {$s['nombre']}, somos Sanalia & Asociados. Te contactamos sobre tu solicitud de {$interes_label}. ¿Tienes disponibilidad para conversar?");
          $em_sub   = urlencode("Re: Tu solicitud de {$interes_label} — Sanalia & Asociados");
          $em_body  = urlencode("Estimado/a {$s['nombre']},\n\nGracias por contactar a Sanalia & Asociados.\n\nHemos revisado tu solicitud sobre {$interes_label} y con gusto te atendemos.\n\nQuedamos a tu disposición.\n\nAtentamente,\nSanalia & Asociados, S.R.L.\n(809) 362-4357 | info@sanaliayasociados.com");
        ?>
        <tr
          class="row-item"
          data-index="<?= $i ?>"
          data-fecha="<?= htmlspecialchars($s['fecha'] ?? '') ?>"
          data-nombre="<?= htmlspecialchars($s['nombre'] ?? '') ?>"
          data-email="<?= htmlspecialchars($s['email'] ?? '') ?>"
          data-telefono="<?= htmlspecialchars($s['telefono'] ?? '') ?>"
          data-interes="<?= htmlspecialchars($interes_label) ?>"
          data-mensaje="<?= htmlspecialchars($s['mensaje'] ?? '') ?>"
          data-wa="https://wa.me/<?= $wa_num ?>?text=<?= $wa_msg ?>"
          data-mailto="mailto:<?= htmlspecialchars($s['email'] ?? '') ?>?subject=<?= $em_sub ?>&body=<?= $em_body ?>"
        >
          <td class="fecha"><?= htmlspecialchars($s['fecha'] ?? '') ?></td>
          <td class="nombre"><?= htmlspecialchars($s['nombre'] ?? '') ?></td>
          <td style="font-family:monospace;font-size:.82rem;white-space:nowrap"><?= htmlspecialchars($s['telefono'] ?? '') ?></td>
          <td><span class="badge"><?= htmlspecialchars($interes_label) ?></span></td>
          <td class="mensaje-preview"><?= htmlspecialchars(mb_substr($s['mensaje'] ?? '', 0, 80)) ?>…</td>
          <td class="estado-cell"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>

<!-- Drawer overlay -->
<div class="drawer-overlay" id="drawerOverlay"></div>

<!-- Drawer panel -->
<div class="drawer" id="drawer">
  <div class="drawer-header">
    <div>
      <div class="d-nombre" id="dNombre"></div>
      <div class="d-fecha" id="dFecha"></div>
    </div>
    <button class="drawer-close" id="drawerClose" aria-label="Cerrar">✕</button>
  </div>

  <div class="drawer-body">
    <!-- Contacto -->
    <div class="contact-pills">
      <a class="contact-pill" id="dWaLink" href="#" target="_blank" rel="noopener">
        <div class="pill-icon wa">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </div>
        <div class="pill-info">
          <div class="pill-label">WhatsApp</div>
          <div class="pill-value" id="dTelefono"></div>
        </div>
        <span class="pill-action wa-btn">Responder</span>
      </a>

      <a class="contact-pill" id="dMailLink" href="#" target="_blank">
        <div class="pill-icon em">✉</div>
        <div class="pill-info">
          <div class="pill-label">Correo electrónico</div>
          <div class="pill-value" id="dEmail"></div>
        </div>
        <span class="pill-action em-btn">Responder</span>
      </a>
    </div>

    <!-- Interés -->
    <div class="d-interes">
      <span class="label">Línea de interés:</span>
      <span class="badge" id="dInteres"></span>
    </div>

    <!-- Mensaje -->
    <div class="d-mensaje-wrap">
      <div class="d-mensaje-label">Mensaje</div>
      <div class="d-mensaje-text" id="dMensaje"></div>
    </div>
  </div>

  <div class="drawer-footer">
    <button class="btn-mark" id="btnMark">✓ Marcar como respondido</button>
  </div>
</div>

<script>
(function () {
  /* ── Estado de respondidos (localStorage) ── */
  const STORAGE_KEY = 'sanalia_replied';

  function getReplied() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); }
    catch { return {}; }
  }

  function setReplied(idx, val) {
    const data = getReplied();
    data[idx] = val;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  }

  /* ── Aplicar estado al cargar ── */
  const replied = getReplied();
  document.querySelectorAll('.row-item').forEach(function (row) {
    const idx = row.dataset.index;
    if (replied[idx]) {
      row.classList.add('replied');
      row.querySelector('.estado-cell').innerHTML =
        '<span class="badge-replied">✓ Respondido</span>';
    }
  });

  /* ── Drawer ── */
  const overlay    = document.getElementById('drawerOverlay');
  const drawer     = document.getElementById('drawer');
  const btnClose   = document.getElementById('drawerClose');
  const btnMark    = document.getElementById('btnMark');
  let currentIdx   = null;

  function openDrawer(row) {
    currentIdx = row.dataset.index;
    document.getElementById('dNombre').textContent   = row.dataset.nombre;
    document.getElementById('dFecha').textContent    = row.dataset.fecha;
    document.getElementById('dTelefono').textContent = row.dataset.telefono;
    document.getElementById('dEmail').textContent    = row.dataset.email;
    document.getElementById('dInteres').textContent  = row.dataset.interes;
    document.getElementById('dMensaje').textContent  = row.dataset.mensaje;
    document.getElementById('dWaLink').href          = row.dataset.wa;
    document.getElementById('dMailLink').href        = row.dataset.mailto;

    // Estado del botón marcar
    const isReplied = !!getReplied()[currentIdx];
    btnMark.textContent = isReplied ? '✓ Respondido' : '✓ Marcar como respondido';
    btnMark.classList.toggle('active', isReplied);

    overlay.classList.add('open');
    drawer.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    overlay.classList.remove('open');
    drawer.classList.remove('open');
    document.body.style.overflow = '';
    currentIdx = null;
  }

  document.querySelectorAll('.row-item').forEach(function (row) {
    row.addEventListener('click', function () { openDrawer(row); });
  });

  overlay.addEventListener('click', closeDrawer);
  btnClose.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDrawer();
  });

  /* ── Marcar respondido ── */
  btnMark.addEventListener('click', function () {
    if (currentIdx === null) return;
    const data    = getReplied();
    const nowOn   = !data[currentIdx];
    setReplied(currentIdx, nowOn);

    // Actualizar fila en tabla
    const row = document.querySelector('.row-item[data-index="' + currentIdx + '"]');
    if (row) {
      row.classList.toggle('replied', nowOn);
      row.querySelector('.estado-cell').innerHTML = nowOn
        ? '<span class="badge-replied">✓ Respondido</span>'
        : '';
    }

    btnMark.textContent = nowOn ? '✓ Respondido' : '✓ Marcar como respondido';
    btnMark.classList.toggle('active', nowOn);
  });
})();
</script>

<?php endif; ?>

</body>
</html>
