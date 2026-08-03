<?php
/**
 * Sanalia & Asociados — Panel de solicitudes
 * Acceso: /admin/  →  contraseña requerida
 */

declare(strict_types=1);

session_start();

define('ADMIN_PASSWORD_PLAIN', 'Sanalia2026!');

$submissions_dir = __DIR__ . '/../storage/submissions/';
$status_file     = __DIR__ . '/../storage/status.json';
$error           = '';

/* ── Auth ──────────────────────────────────────────────────────── */

if (isset($_POST['logout'])) { session_destroy(); header('Location: ' . $_SERVER['PHP_SELF']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD_PLAIN) {
        $_SESSION['sanalia_admin'] = true;
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Contraseña incorrecta.';
}

$auth = !empty($_SESSION['sanalia_admin']);

/* ── CSV Export ─────────────────────────────────────────────────── */

if ($auth && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = [];
    if (is_dir($submissions_dir)) {
        foreach (glob($submissions_dir . '*.json') as $f) {
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
                $e = json_decode($l, true);
                if (is_array($e)) $rows[] = $e;
            }
        }
        usort($rows, fn($a,$b) => strcmp($b['fecha'], $a['fecha']));
    }
    $statuses = [];
    if (file_exists($status_file)) $statuses = json_decode(file_get_contents($status_file), true) ?: [];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="solicitudes-sanalia-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Fecha','Nombre','Email','Teléfono','Interés','Etapa','Notas','Mensaje'], ';');
    foreach ($rows as $s) {
        $key = md5(($s['fecha'] ?? '') . ($s['email'] ?? ''));
        $st  = $statuses[$key] ?? [];
        fputcsv($out, [
            $s['fecha'] ?? '', $s['nombre'] ?? '', $s['email'] ?? '',
            $s['telefono'] ?? '', $s['interes'] ?? '',
            $st['etapa'] ?? 'nuevo', $st['notas'] ?? '', $s['mensaje'] ?? '',
        ], ';');
    }
    fclose($out); exit;
}

/* ── Cargar datos ───────────────────────────────────────────────── */

$submissions = [];
$statuses    = [];

if ($auth) {
    if (is_dir($submissions_dir)) {
        foreach (glob($submissions_dir . '*.json') as $f) {
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
                $e = json_decode($l, true);
                if (is_array($e)) $submissions[] = $e;
            }
        }
        usort($submissions, fn($a,$b) => strcmp($b['fecha'], $a['fecha']));
    }
    if (file_exists($status_file)) {
        $statuses = json_decode(file_get_contents($status_file), true) ?: [];
    }
}

/* ── Helpers ────────────────────────────────────────────────────── */

$map_interes = [
    'vida' => 'Vida', 'salud-persona' => 'Salud Personal',
    'viajes' => 'Asistencia en Viaje', 'vehiculos' => 'Vehículos',
    'salud' => 'Salud', 'accidentes-personales' => 'Accidentes Personales',
    'internacionales' => 'Médico Internacional', 'riesgos-generales' => 'Riesgos Generales',
    'mascotas' => 'Mascotas', 'otro' => 'Otro',
];

$etapa_labels = [
    'nuevo' => 'Nuevo', 'contactado' => 'Contactado',
    'cotizacion' => 'Cotización', 'emitido' => 'Emitido', 'perdido' => 'Perdido',
];

function wa_number(string $tel): string {
    $clean = preg_replace('/[^\d]/', '', $tel);
    if (strlen($clean) === 10) $clean = '1' . $clean;
    return $clean;
}

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel CRM — Sanalia & Asociados</title>
<meta name="robots" content="noindex, nofollow">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --navy-950:#071523; --navy-900:#0C2036; --navy-800:#153350; --navy-700:#1E4468;
  --gold-500:#C6A15B; --gold-600:#A9843F;
  --silver-100:#F3F5F7; --silver-300:#DCE1E7; --silver-500:#AEB8C4;
  --ink:#0E1620; --paper:#F7F7F5;
  --e-nuevo:#1E4468; --e-contactado:#d97706; --e-cotizacion:#7c3aed;
  --e-emitido:#1a7f4b; --e-perdido:#6b7280;
  --e-nuevo-bg:#e8f0f8; --e-contactado-bg:#fef3c7; --e-cotizacion-bg:#ede9fe;
  --e-emitido-bg:#e8f5ee; --e-perdido-bg:#f3f4f6;
}

body { font-family:'Inter',system-ui,sans-serif; background:var(--silver-100); color:var(--ink); min-height:100vh; }

/* ── Login ── */
.login-wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:2rem; }
.login-card { background:#fff; border-radius:12px; box-shadow:0 4px 24px rgba(7,21,35,.12); padding:2.5rem 2rem; width:100%; max-width:360px; text-align:center; }
.login-card .brand { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.25rem; color:var(--navy-900); margin-bottom:.25rem; }
.login-card .sub { font-size:.8rem; color:var(--silver-500); margin-bottom:2rem; letter-spacing:.05em; text-transform:uppercase; }
.login-card input[type="password"] { width:100%; padding:.75rem 1rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:1rem; outline:none; transition:border-color .2s; margin-bottom:1rem; }
.login-card input[type="password"]:focus { border-color:var(--navy-700); }
.btn-login { width:100%; padding:.75rem; background:var(--navy-900); color:#fff; border:none; border-radius:8px; font-size:1rem; font-weight:600; cursor:pointer; transition:background .2s; }
.btn-login:hover { background:var(--navy-700); }
.login-error { margin-top:1rem; color:#c0392b; font-size:.875rem; }

/* ── Topbar ── */
.topbar { background:var(--navy-900); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:.875rem 2rem; gap:1rem; position:sticky; top:0; z-index:100; }
.topbar .brand { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1rem; }
.topbar .brand span { color:var(--gold-500); }
.topbar-actions { display:flex; gap:.75rem; align-items:center; }
.btn-csv { background:var(--gold-500); color:var(--navy-950); border:none; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:background .2s; }
.btn-csv:hover { background:var(--gold-600); }
.btn-logout { background:transparent; border:1.5px solid rgba(255,255,255,.3); color:#fff; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; cursor:pointer; transition:border-color .2s; }
.btn-logout:hover { border-color:#fff; }

/* ── Main ── */
.main { max-width:1280px; margin:0 auto; padding:2rem 1.5rem; }

/* ── Stats ── */
.stats { display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
.stat-card { background:#fff; border-radius:10px; padding:1.25rem 1.5rem; flex:1; min-width:120px; box-shadow:0 1px 6px rgba(7,21,35,.07); }
.stat-card .num { font-family:'IBM Plex Mono',monospace,system-ui; font-size:1.875rem; font-weight:700; color:var(--navy-900); line-height:1; }
.stat-card .label { font-size:.75rem; color:var(--silver-500); text-transform:uppercase; letter-spacing:.05em; margin-top:.35rem; }

/* ── Pipeline bar ── */
.pipeline-bar { display:flex; gap:.5rem; margin-bottom:1.5rem; flex-wrap:wrap; }
.pipe-btn { padding:.45rem 1rem; border-radius:999px; border:2px solid transparent; font-size:.78rem; font-weight:700; cursor:pointer; transition:all .18s; background:#fff; color:var(--silver-500); }
.pipe-btn:hover { border-color:var(--silver-300); color:var(--ink); }
.pipe-btn.active { color:#fff; border-color:transparent; }
.pipe-btn[data-f="all"].active { background:var(--navy-900); }
.pipe-btn[data-f="nuevo"].active { background:var(--e-nuevo); }
.pipe-btn[data-f="contactado"].active { background:var(--e-contactado); }
.pipe-btn[data-f="cotizacion"].active { background:var(--e-cotizacion); }
.pipe-btn[data-f="emitido"].active { background:var(--e-emitido); }
.pipe-btn[data-f="perdido"].active { background:var(--e-perdido); }

/* ── Search bar ── */
.search-bar { display:flex; gap:.75rem; margin-bottom:1.25rem; align-items:center; flex-wrap:wrap; }
.search-input { flex:1; min-width:200px; padding:.6rem 1rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.875rem; outline:none; background:#fff; transition:border-color .2s; }
.search-input:focus { border-color:var(--navy-700); }
.filter-select { padding:.6rem .875rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.8rem; outline:none; background:#fff; color:var(--ink); cursor:pointer; }
.filter-select:focus { border-color:var(--navy-700); }
.result-count { font-size:.8rem; color:var(--silver-500); margin-left:auto; white-space:nowrap; }

/* ── Table ── */
.table-wrap { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(7,21,35,.07); overflow:hidden; }
table { width:100%; border-collapse:collapse; font-size:.875rem; }
thead { background:var(--navy-950); color:#fff; }
thead th { padding:.75rem 1rem; text-align:left; font-family:'IBM Plex Mono',monospace,system-ui; font-size:.7rem; letter-spacing:.07em; text-transform:uppercase; font-weight:500; white-space:nowrap; }
tbody tr { border-bottom:1px solid var(--silver-100); cursor:pointer; transition:background .15s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:#eef2f7; }
tbody tr.hidden-row { display:none; }
td { padding:.8rem 1rem; vertical-align:middle; line-height:1.5; }
td.fecha { font-family:'IBM Plex Mono',monospace,system-ui; font-size:.72rem; color:var(--silver-500); white-space:nowrap; }
td.nombre { font-weight:600; color:var(--navy-900); }
td.mensaje-preview { max-width:200px; color:#666; font-size:.8rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.empty { text-align:center; padding:4rem 2rem; color:var(--silver-500); font-size:.9rem; }
.no-results { text-align:center; padding:3rem 2rem; color:var(--silver-500); font-size:.875rem; display:none; }

/* ── Etapa badge ── */
.badge-etapa {
  display:inline-block; padding:.22rem .65rem; border-radius:999px;
  font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
  white-space:nowrap;
}
.badge-etapa.nuevo      { background:var(--e-nuevo-bg);      color:var(--e-nuevo); }
.badge-etapa.contactado { background:var(--e-contactado-bg); color:var(--e-contactado); }
.badge-etapa.cotizacion { background:var(--e-cotizacion-bg); color:var(--e-cotizacion); }
.badge-etapa.emitido    { background:var(--e-emitido-bg);    color:var(--e-emitido); }
.badge-etapa.perdido    { background:var(--e-perdido-bg);    color:var(--e-perdido); }

/* ── Interés badge ── */
.badge-int { display:inline-block; padding:.22rem .6rem; border-radius:999px; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; background:var(--navy-800); color:var(--gold-500); white-space:nowrap; }

/* ── Lead frío ── */
.frio-dot { display:inline-block; width:7px; height:7px; border-radius:50%; background:#ef4444; margin-right:.4rem; vertical-align:middle; animation:pulse 1.4s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ── Drawer ── */
.drawer-overlay { display:none; position:fixed; inset:0; background:rgba(7,21,35,.5); z-index:200; backdrop-filter:blur(2px); }
.drawer-overlay.open { display:block; }
.drawer { position:fixed; top:0; right:0; bottom:0; width:500px; max-width:100vw; background:#fff; z-index:201; box-shadow:-8px 0 40px rgba(7,21,35,.18); transform:translateX(100%); transition:transform .28s cubic-bezier(.16,1,.3,1); display:flex; flex-direction:column; overflow:hidden; }
.drawer.open { transform:translateX(0); }

.drawer-header { background:var(--navy-900); color:#fff; padding:1.25rem 1.5rem; display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-shrink:0; }
.drawer-header .d-nombre { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.1rem; line-height:1.2; }
.drawer-header .d-fecha { font-family:'IBM Plex Mono',monospace,system-ui; font-size:.72rem; color:var(--silver-500); margin-top:.25rem; }
.drawer-close { background:rgba(255,255,255,.12); border:none; color:#fff; width:32px; height:32px; border-radius:50%; cursor:pointer; font-size:1rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:background .2s; }
.drawer-close:hover { background:rgba(255,255,255,.25); }

.drawer-body { flex:1; overflow-y:auto; padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:1.25rem; }

/* ── Pipeline selector en drawer ── */
.d-section-label { font-size:.72rem; color:var(--silver-500); text-transform:uppercase; letter-spacing:.05em; font-weight:600; margin-bottom:.5rem; }

.etapa-pills { display:flex; gap:.5rem; flex-wrap:wrap; }
.etapa-pill { padding:.35rem .875rem; border-radius:999px; border:2px solid var(--silver-300); font-size:.75rem; font-weight:700; cursor:pointer; background:#fff; color:var(--silver-500); transition:all .18s; }
.etapa-pill:hover { border-color:var(--silver-500); color:var(--ink); }
.etapa-pill.sel-nuevo      { background:var(--e-nuevo);      color:#fff; border-color:var(--e-nuevo); }
.etapa-pill.sel-contactado { background:var(--e-contactado); color:#fff; border-color:var(--e-contactado); }
.etapa-pill.sel-cotizacion { background:var(--e-cotizacion); color:#fff; border-color:var(--e-cotizacion); }
.etapa-pill.sel-emitido    { background:var(--e-emitido);    color:#fff; border-color:var(--e-emitido); }
.etapa-pill.sel-perdido    { background:var(--e-perdido);    color:#fff; border-color:var(--e-perdido); }

/* ── Notas ── */
.notas-wrap textarea { width:100%; padding:.75rem 1rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.875rem; font-family:inherit; resize:vertical; min-height:90px; outline:none; transition:border-color .2s; }
.notas-wrap textarea:focus { border-color:var(--navy-700); }

.btn-save-status { width:100%; padding:.7rem; background:var(--navy-900); color:#fff; border:none; border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; transition:background .2s; display:flex; align-items:center; justify-content:center; gap:.5rem; }
.btn-save-status:hover { background:var(--navy-700); }
.btn-save-status.saved { background:var(--e-emitido); }

/* ── Contacto pills ── */
.contact-pills { display:flex; flex-direction:column; gap:.625rem; }
.contact-pill { display:flex; align-items:center; gap:.75rem; background:var(--silver-100); border-radius:10px; padding:.75rem 1rem; text-decoration:none; color:var(--ink); transition:background .15s, transform .15s; border:1.5px solid transparent; }
.contact-pill:hover { background:#e8edf3; border-color:var(--silver-300); transform:translateX(2px); }
.pill-icon { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
.pill-icon.wa { background:#25d366; color:#fff; }
.pill-icon.em { background:var(--navy-800); color:var(--gold-500); }
.pill-info { flex:1; min-width:0; }
.pill-label { font-size:.7rem; color:var(--silver-500); text-transform:uppercase; letter-spacing:.05em; font-weight:600; }
.pill-value { font-size:.875rem; font-weight:600; color:var(--navy-900); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pill-action { font-size:.72rem; font-weight:700; padding:.3rem .7rem; border-radius:6px; white-space:nowrap; }
.pill-action.wa-btn { background:#25d366; color:#fff; }
.pill-action.em-btn { background:var(--navy-900); color:#fff; }

/* ── Mensaje ── */
.d-mensaje-wrap { background:var(--silver-100); border-radius:10px; padding:1rem 1.25rem; border-left:3px solid var(--gold-500); }
.d-mensaje-label { font-size:.72rem; color:var(--silver-500); text-transform:uppercase; letter-spacing:.05em; font-weight:600; margin-bottom:.5rem; }
.d-mensaje-text { font-size:.875rem; line-height:1.7; color:var(--ink); white-space:pre-wrap; }

/* ── Responsive ── */
@media (max-width:768px) {
  .topbar { padding:.75rem 1rem; }
  .topbar .brand { font-size:.85rem; }
  .main { padding:1.25rem 1rem; }
  table { font-size:.8rem; }
  thead th, td { padding:.6rem .75rem; }
  td.mensaje-preview { display:none; }
  .drawer { width:100vw; }
  .result-count { display:none; }
}
</style>
</head>
<body>

<?php if (!$auth): ?>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand">Sanalia &amp; Asociados</div>
    <div class="sub">Panel CRM</div>
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
  <div class="brand">Sanalia &amp; Asociados &mdash; <span>CRM</span></div>
  <div style="display:flex;gap:.25rem">
    <a href="dashboard.php" style="padding:.4rem .875rem;border-radius:6px;font-size:.82rem;font-weight:600;text-decoration:none;color:rgba(255,255,255,.6)">Dashboard</a>
    <a href="index.php" style="padding:.4rem .875rem;border-radius:6px;font-size:.82rem;font-weight:600;text-decoration:none;color:#fff;background:rgba(255,255,255,.15)">Solicitudes</a>
    <a href="clients.php" style="padding:.4rem .875rem;border-radius:6px;font-size:.82rem;font-weight:600;text-decoration:none;color:rgba(255,255,255,.6)">Clientes</a>
    <a href="finances.php" style="padding:.4rem .875rem;border-radius:6px;font-size:.82rem;font-weight:600;text-decoration:none;color:rgba(255,255,255,.6)">Finanzas</a>
    <a href="calendar.php" style="padding:.4rem .875rem;border-radius:6px;font-size:.82rem;font-weight:600;text-decoration:none;color:rgba(255,255,255,.6)">Calendario</a>
  </div>
  <div class="topbar-actions">
    <a href="?export=csv" class="btn-csv">↓ Excel</a>
    <form method="POST" style="margin:0">
      <button type="submit" name="logout" class="btn-logout">Salir</button>
    </form>
  </div>
</div>

<div class="main">

<?php
  $total = count($submissions);
  $mes   = date('Y-m');
  $hoy   = date('Y-m-d');
  $cnt_mes = $cnt_hoy = $cnt_frio = 0;
  $cnt_etapa = ['nuevo'=>0,'contactado'=>0,'cotizacion'=>0,'emitido'=>0,'perdido'=>0];
  $now_ts = time();

  foreach ($submissions as $s) {
    if (str_starts_with($s['fecha'], $mes))  $cnt_mes++;
    if (str_starts_with($s['fecha'], $hoy))  $cnt_hoy++;
    $key   = md5(($s['fecha']??'').($s['email']??''));
    $etapa = $statuses[$key]['etapa'] ?? 'nuevo';
    $cnt_etapa[$etapa] = ($cnt_etapa[$etapa] ?? 0) + 1;
    $ts = strtotime($s['fecha'] ?? '');
    if ($etapa === 'nuevo' && ($now_ts - $ts) > 86400) $cnt_frio++;
  }
?>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card"><div class="num"><?= $total ?></div><div class="label">Total leads</div></div>
    <div class="stat-card"><div class="num"><?= $cnt_mes ?></div><div class="label">Este mes</div></div>
    <div class="stat-card"><div class="num"><?= $cnt_hoy ?></div><div class="label">Hoy</div></div>
    <div class="stat-card"><div class="num"><?= $cnt_etapa['emitido'] ?></div><div class="label">Emitidos</div></div>
    <?php if ($cnt_frio > 0): ?>
    <div class="stat-card" style="border-left:3px solid #ef4444">
      <div class="num" style="color:#ef4444"><?= $cnt_frio ?></div>
      <div class="label">Sin contactar &gt;24h</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Pipeline filter -->
  <div class="pipeline-bar">
    <button class="pipe-btn active" data-f="all">Todos (<?= $total ?>)</button>
    <button class="pipe-btn" data-f="nuevo">Nuevo (<?= $cnt_etapa['nuevo'] ?>)</button>
    <button class="pipe-btn" data-f="contactado">Contactado (<?= $cnt_etapa['contactado'] ?>)</button>
    <button class="pipe-btn" data-f="cotizacion">Cotización (<?= $cnt_etapa['cotizacion'] ?>)</button>
    <button class="pipe-btn" data-f="emitido">Emitido (<?= $cnt_etapa['emitido'] ?>)</button>
    <button class="pipe-btn" data-f="perdido">Perdido (<?= $cnt_etapa['perdido'] ?>)</button>
  </div>

  <!-- Search -->
  <div class="search-bar">
    <input type="text" class="search-input" id="searchInput" placeholder="Buscar por nombre, email o teléfono…">
    <select class="filter-select" id="filterInteres">
      <option value="">Todas las líneas</option>
      <?php foreach ($map_interes as $k => $v): ?>
      <option value="<?= $k ?>"><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <span class="result-count" id="resultCount"><?= $total ?> leads</span>
  </div>

  <!-- Table -->
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
          <th>Etapa</th>
          <th>Mensaje</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <?php foreach ($submissions as $i => $s):
          $key    = md5(($s['fecha']??'').($s['email']??''));
          $st     = $statuses[$key] ?? [];
          $etapa  = $st['etapa'] ?? 'nuevo';
          $notas  = $st['notas'] ?? '';
          $interes_label = $map_interes[$s['interes'] ?? ''] ?? ($s['interes'] ?? '');
          $ts     = strtotime($s['fecha'] ?? '');
          $is_frio = ($etapa === 'nuevo' && ($now_ts - $ts) > 86400);
          $wa_num = wa_number($s['telefono'] ?? '');
          $wa_msg = urlencode("Hola {$s['nombre']}, somos Sanalia & Asociados. Te contactamos sobre tu solicitud de {$interes_label}. ¿Tienes disponibilidad para conversar?");
          $em_sub = urlencode("Re: Tu solicitud de {$interes_label} — Sanalia & Asociados");
          $em_body = urlencode("Estimado/a {$s['nombre']},\n\nGracias por contactar a Sanalia & Asociados.\n\nHemos revisado tu solicitud sobre {$interes_label} y con gusto te atendemos.\n\nQuedamos a tu disposición.\n\nAtentamente,\nSanalia & Asociados, S.R.L.\n(809) 362-4357 | info@sanaliayasociados.com");
        ?>
        <tr class="row-item"
          data-key="<?= $key ?>"
          data-etapa="<?= $etapa ?>"
          data-interes-key="<?= htmlspecialchars($s['interes'] ?? '') ?>"
          data-search="<?= htmlspecialchars(strtolower(($s['nombre']??'').' '.($s['email']??'').' '.($s['telefono']??''))) ?>"
          data-fecha="<?= htmlspecialchars($s['fecha'] ?? '') ?>"
          data-nombre="<?= htmlspecialchars($s['nombre'] ?? '') ?>"
          data-email="<?= htmlspecialchars($s['email'] ?? '') ?>"
          data-telefono="<?= htmlspecialchars($s['telefono'] ?? '') ?>"
          data-interes-label="<?= htmlspecialchars($interes_label) ?>"
          data-mensaje="<?= htmlspecialchars($s['mensaje'] ?? '') ?>"
          data-notas="<?= htmlspecialchars($notas) ?>"
          data-wa="https://wa.me/<?= $wa_num ?>?text=<?= $wa_msg ?>"
          data-mailto="mailto:<?= htmlspecialchars($s['email'] ?? '') ?>?subject=<?= $em_sub ?>&body=<?= $em_body ?>"
        >
          <td class="fecha"><?= htmlspecialchars($s['fecha'] ?? '') ?></td>
          <td class="nombre">
            <?php if ($is_frio): ?><span class="frio-dot" title="Sin contactar más de 24h"></span><?php endif; ?>
            <?= htmlspecialchars($s['nombre'] ?? '') ?>
          </td>
          <td style="font-family:monospace;font-size:.82rem;white-space:nowrap"><?= htmlspecialchars($s['telefono'] ?? '') ?></td>
          <td><span class="badge-int"><?= htmlspecialchars($interes_label) ?></span></td>
          <td class="etapa-cell"><span class="badge-etapa <?= $etapa ?>"><?= $etapa_labels[$etapa] ?? $etapa ?></span></td>
          <td class="mensaje-preview"><?= htmlspecialchars(mb_substr($s['mensaje'] ?? '', 0, 80)) ?>…</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="no-results" id="noResults">No se encontraron leads con ese filtro.</div>
    <?php endif; ?>
  </div>

</div><!-- /.main -->

<!-- Drawer overlay -->
<div class="drawer-overlay" id="drawerOverlay"></div>

<!-- Drawer -->
<div class="drawer" id="drawer">
  <div class="drawer-header">
    <div>
      <div class="d-nombre" id="dNombre"></div>
      <div class="d-fecha" id="dFecha"></div>
    </div>
    <button class="drawer-close" id="drawerClose">✕</button>
  </div>

  <div class="drawer-body">

    <!-- Pipeline -->
    <div>
      <div class="d-section-label">Etapa del lead</div>
      <div class="etapa-pills">
        <button class="etapa-pill" data-e="nuevo">Nuevo</button>
        <button class="etapa-pill" data-e="contactado">Contactado</button>
        <button class="etapa-pill" data-e="cotizacion">Cotización</button>
        <button class="etapa-pill" data-e="emitido">Emitido ✓</button>
        <button class="etapa-pill" data-e="perdido">Perdido</button>
      </div>
    </div>

    <!-- Notas -->
    <div class="notas-wrap">
      <div class="d-section-label">Notas internas</div>
      <textarea id="dNotas" placeholder="Ej: Cliente pidió cotización mañana, llamar a las 10am…"></textarea>
    </div>

    <!-- Guardar -->
    <button class="btn-save-status" id="btnSaveStatus">Guardar cambios</button>

    <!-- Separador -->
    <hr style="border:none;border-top:1px solid var(--silver-300)">

    <!-- Contacto -->
    <div>
      <div class="d-section-label">Contactar</div>
      <div class="contact-pills">
        <a class="contact-pill" id="dWaLink" href="#" target="_blank" rel="noopener">
          <div class="pill-icon wa">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </div>
          <div class="pill-info">
            <div class="pill-label">WhatsApp</div>
            <div class="pill-value" id="dTelefono"></div>
          </div>
          <span class="pill-action wa-btn">Abrir chat</span>
        </a>
        <a class="contact-pill" id="dMailLink" href="#" target="_blank">
          <div class="pill-icon em">✉</div>
          <div class="pill-info">
            <div class="pill-label">Email</div>
            <div class="pill-value" id="dEmail"></div>
          </div>
          <span class="pill-action em-btn">Redactar</span>
        </a>
      </div>
    </div>

    <!-- Interés -->
    <div style="display:flex;align-items:center;gap:.75rem">
      <span style="font-size:.78rem;color:var(--silver-500);text-transform:uppercase;letter-spacing:.05em">Línea:</span>
      <span class="badge-int" id="dInteres"></span>
    </div>

    <!-- Mensaje -->
    <div class="d-mensaje-wrap">
      <div class="d-mensaje-label">Mensaje del cliente</div>
      <div class="d-mensaje-text" id="dMensaje"></div>
    </div>

  </div>
</div>

<script>
(function () {
  /* ── Filtros ── */
  const rows       = Array.from(document.querySelectorAll('.row-item'));
  const searchEl   = document.getElementById('searchInput');
  const interesEl  = document.getElementById('filterInteres');
  const countEl    = document.getElementById('resultCount');
  const noResults  = document.getElementById('noResults');
  let activeEtapa  = 'all';

  function applyFilters() {
    const q  = (searchEl.value || '').toLowerCase().trim();
    const fi = interesEl.value;
    let visible = 0;
    rows.forEach(function(r) {
      const matchEtapa   = activeEtapa === 'all' || r.dataset.etapa === activeEtapa;
      const matchSearch  = !q || r.dataset.search.includes(q);
      const matchInteres = !fi || r.dataset.interesKey === fi;
      const show = matchEtapa && matchSearch && matchInteres;
      r.classList.toggle('hidden-row', !show);
      if (show) visible++;
    });
    if (countEl) countEl.textContent = visible + ' leads';
    if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
  }

  document.querySelectorAll('.pipe-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.pipe-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeEtapa = btn.dataset.f;
      applyFilters();
    });
  });

  searchEl.addEventListener('input', applyFilters);
  interesEl.addEventListener('change', applyFilters);

  /* ── Drawer ── */
  const overlay  = document.getElementById('drawerOverlay');
  const drawer   = document.getElementById('drawer');
  const btnClose = document.getElementById('drawerClose');
  const btnSave  = document.getElementById('btnSaveStatus');
  const notasEl  = document.getElementById('dNotas');
  let currentRow = null;
  let currentEtapa = 'nuevo';

  function openDrawer(row) {
    currentRow   = row;
    currentEtapa = row.dataset.etapa || 'nuevo';

    document.getElementById('dNombre').textContent   = row.dataset.nombre;
    document.getElementById('dFecha').textContent    = row.dataset.fecha;
    document.getElementById('dTelefono').textContent = row.dataset.telefono;
    document.getElementById('dEmail').textContent    = row.dataset.email;
    document.getElementById('dInteres').textContent  = row.dataset.interesLabel;
    document.getElementById('dMensaje').textContent  = row.dataset.mensaje;
    document.getElementById('dWaLink').href          = row.dataset.wa;
    document.getElementById('dMailLink').href        = row.dataset.mailto;
    notasEl.value = row.dataset.notas || '';

    updateEtapaPills(currentEtapa);
    btnSave.textContent = 'Guardar cambios';
    btnSave.classList.remove('saved');

    overlay.classList.add('open');
    drawer.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    overlay.classList.remove('open');
    drawer.classList.remove('open');
    document.body.style.overflow = '';
    currentRow = null;
  }

  rows.forEach(function(row) {
    row.addEventListener('click', function() { openDrawer(row); });
  });

  overlay.addEventListener('click', closeDrawer);
  btnClose.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

  /* ── Etapa pills en drawer ── */
  function updateEtapaPills(etapa) {
    document.querySelectorAll('.etapa-pill').forEach(function(p) {
      p.className = 'etapa-pill';
      if (p.dataset.e === etapa) p.classList.add('sel-' + etapa);
    });
  }

  document.querySelectorAll('.etapa-pill').forEach(function(p) {
    p.addEventListener('click', function() {
      currentEtapa = p.dataset.e;
      updateEtapaPills(currentEtapa);
    });
  });

  /* ── Guardar etapa + notas ── */
  btnSave.addEventListener('click', function() {
    if (!currentRow) return;
    const key   = currentRow.dataset.key;
    const notas = notasEl.value;

    btnSave.textContent = 'Guardando…';
    btnSave.disabled    = true;

    const fd = new FormData();
    fd.append('key',   key);
    fd.append('etapa', currentEtapa);
    fd.append('notas', notas);

    fetch('save.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          // Actualizar fila en tabla
          currentRow.dataset.etapa = currentEtapa;
          currentRow.dataset.notas = notas;
          const etapaLabels = {nuevo:'Nuevo',contactado:'Contactado',cotizacion:'Cotización',emitido:'Emitido ✓',perdido:'Perdido'};
          const cell = currentRow.querySelector('.etapa-cell');
          if (cell) cell.innerHTML = '<span class="badge-etapa ' + currentEtapa + '">' + (etapaLabels[currentEtapa] || currentEtapa) + '</span>';

          btnSave.textContent = '✓ Guardado';
          btnSave.classList.add('saved');

          // Re-aplicar filtros por si cambió de etapa
          applyFilters();

          setTimeout(function() {
            btnSave.textContent = 'Guardar cambios';
            btnSave.classList.remove('saved');
            btnSave.disabled = false;
          }, 2000);
        } else {
          btnSave.textContent = 'Error al guardar';
          btnSave.disabled = false;
        }
      })
      .catch(function() {
        btnSave.textContent = 'Error de conexión';
        btnSave.disabled = false;
      });
  });

})();
</script>

<?php endif; ?>
</body>
</html>
