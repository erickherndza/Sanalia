<?php
/**
 * Sanalia CRM — Pipeline de Contactos / Leads
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_auth();
$db = get_db();

/* ── Asegurar tabla leads + columnas de atribución ── */
$leads_ok = true;
try {
    $db->query("SELECT 1 FROM leads LIMIT 1");
    // Migración automática: añadir columnas de atribución si no existen
    $cols = $db->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('fbclid', $cols))
        $db->exec("ALTER TABLE leads ADD COLUMN fbclid VARCHAR(255) DEFAULT NULL AFTER fecha_proximo_contacto");
    if (!in_array('gclid', $cols))
        $db->exec("ALTER TABLE leads ADD COLUMN gclid VARCHAR(255) DEFAULT NULL AFTER fbclid");
    if (!in_array('ga_client_id', $cols))
        $db->exec("ALTER TABLE leads ADD COLUMN ga_client_id VARCHAR(100) DEFAULT NULL AFTER gclid");
} catch (Exception $e) { $leads_ok = false; }

/* ── Datos ── */
$leads = [];
if ($leads_ok) {
    $leads = $db->query(
        "SELECT * FROM leads ORDER BY
         FIELD(estado,'nuevo','contactado','seguimiento','ganado','perdido'),
         created_at DESC"
    )->fetchAll();
}

$fuentes_label = ['web'=>'Web','facebook'=>'Facebook','instagram'=>'Instagram','whatsapp'=>'WhatsApp','referido'=>'Referido','otro'=>'Otro'];
$estados_label = ['nuevo'=>'Nuevo','contactado'=>'Contactado','seguimiento'=>'Seguimiento','ganado'=>'Ganado','perdido'=>'Perdido'];
$interes_opts  = [
    'vida'=>'Seguro de Vida','salud'=>'Seguro de Salud','viajes'=>'Asistencia en Viaje',
    'vehiculos'=>'Vehículos','accidentes-personales'=>'Accidentes Personales',
    'internacionales'=>'Seguro Internacional','riesgos-generales'=>'Riesgos Generales',
    'mascotas'=>'Mascotas','exequial'=>'Cobertura Exequial','otro'=>'Otro',
];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Contactos — Sanalia CRM</title>
<meta name="robots" content="noindex, nofollow">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --navy-950:#071523; --navy-900:#0C2036; --navy-800:#153350; --navy-700:#1E4468;
  --gold-500:#C6A15B; --gold-600:#A9843F;
  --silver-100:#F3F5F7; --silver-300:#DCE1E7; --silver-500:#AEB8C4;
  --ink:#0E1620;
  --green:#1a7f4b; --green-bg:#e8f5ee;
  --orange:#d97706; --orange-bg:#fef3c7;
  --red:#dc2626; --red-bg:#fee2e2;
  --purple:#7c3aed; --purple-bg:#ede9fe;
  --blue:#1d4ed8; --blue-bg:#dbeafe;
  --cyan:#0891b2; --cyan-bg:#cffafe;
}
body { font-family:'Inter',system-ui,sans-serif; background:var(--silver-100); color:var(--ink); min-height:100vh; }
.topbar { background:var(--navy-900); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:.875rem 2rem; position:sticky; top:0; z-index:200; gap:1rem; flex-wrap:wrap; }
.topbar .brand { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1rem; white-space:nowrap; }
.topbar .brand span { color:var(--gold-500); }
.topbar-nav { display:flex; gap:.25rem; }
.nav-tab { padding:.4rem .875rem; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; color:rgba(255,255,255,.6); border:none; background:none; cursor:pointer; white-space:nowrap; }
.nav-tab:hover { color:#fff; background:rgba(255,255,255,.08); }
.nav-tab.active { color:#fff; background:rgba(255,255,255,.15); }
.btn-outline { background:transparent; border:1.5px solid rgba(255,255,255,.3); color:#fff; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; cursor:pointer; }

.main { max-width:1400px; margin:0 auto; padding:1.75rem 1.5rem; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; flex-wrap:wrap; gap:.75rem; }
.page-title { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.3rem; color:var(--navy-900); }
.btn-primary { background:var(--navy-700); color:#fff; border:none; padding:.55rem 1.1rem; border-radius:8px; font-size:.84rem; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:.4rem; }
.btn-primary:hover { background:var(--navy-800); }

/* ── Stat summary ── */
.stat-row { display:flex; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
.stat-pill { background:#fff; border-radius:8px; padding:.5rem .875rem; display:flex; align-items:center; gap:.5rem; font-size:.78rem; font-weight:600; box-shadow:0 1px 4px rgba(7,21,35,.06); cursor:pointer; border:2px solid transparent; transition:border-color .15s; }
.stat-pill:hover,.stat-pill.active { border-color:var(--navy-700); }
.stat-pill .pill-count { font-family:'IBM Plex Mono',monospace; font-size:1rem; font-weight:700; }
.pill-nuevo     { color:#1d4ed8; }
.pill-contactado { color:#d97706; }
.pill-seguimiento { color:#7c3aed; }
.pill-ganado    { color:#1a7f4b; }
.pill-perdido   { color:#dc2626; }
.pill-todos     { color:var(--navy-900); }

/* ── Toolbar ── */
.toolbar { display:flex; gap:.75rem; margin-bottom:1rem; flex-wrap:wrap; align-items:center; }
.search-box { flex:1; min-width:220px; position:relative; }
.search-box input { width:100%; padding:.5rem .875rem .5rem 2.25rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.84rem; outline:none; background:#fff; }
.search-box input:focus { border-color:var(--navy-700); }
.search-box .ico { position:absolute; left:.75rem; top:50%; transform:translateY(-50%); color:var(--silver-500); font-size:.9rem; }
.filter-sel { padding:.5rem .75rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.82rem; background:#fff; outline:none; cursor:pointer; }

/* ── Table ── */
.table-wrap { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(7,21,35,.07); overflow:hidden; }
table { width:100%; border-collapse:collapse; font-size:.83rem; }
thead { background:var(--silver-100); }
thead th { padding:.65rem 1rem; text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.06em; color:var(--silver-500); font-weight:600; white-space:nowrap; }
tbody tr { border-bottom:1px solid var(--silver-100); cursor:pointer; transition:background .1s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:var(--silver-100); }
td { padding:.7rem 1rem; vertical-align:middle; }
.td-nombre { font-weight:600; color:var(--navy-900); }
.td-tel { font-family:'IBM Plex Mono',monospace; font-size:.78rem; color:var(--silver-500); }
.empty-row td { text-align:center; padding:3rem; color:var(--silver-500); }

/* ── Badges ── */
.badge { display:inline-block; padding:.18rem .6rem; border-radius:999px; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
.badge-nuevo       { background:var(--blue-bg);   color:var(--blue); }
.badge-contactado  { background:var(--orange-bg); color:var(--orange); }
.badge-seguimiento { background:var(--purple-bg); color:var(--purple); }
.badge-ganado      { background:var(--green-bg);  color:var(--green); }
.badge-perdido     { background:var(--red-bg);    color:var(--red); }
.badge-web        { background:#f3f4f6; color:#374151; }
.badge-facebook   { background:#dbeafe; color:#1d4ed8; }
.badge-instagram  { background:var(--purple-bg); color:var(--purple); }
.badge-whatsapp   { background:var(--green-bg); color:var(--green); }
.badge-referido   { background:var(--cyan-bg); color:var(--cyan); }
.badge-otro       { background:#f3f4f6; color:#6b7280; }

/* ── Drawer ── */
.drawer-overlay { display:none; position:fixed; inset:0; background:rgba(7,21,35,.4); z-index:300; }
.drawer-overlay.open { display:block; }
.drawer { position:fixed; top:0; right:0; width:480px; max-width:100vw; height:100vh; background:#fff; box-shadow:-4px 0 24px rgba(7,21,35,.15); display:flex; flex-direction:column; z-index:301; transform:translateX(100%); transition:transform .25s ease; }
.drawer.open { transform:translateX(0); }
.drawer-header { background:var(--navy-950); color:#fff; padding:1rem 1.25rem; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.drawer-header-title { font-family:'Manrope',system-ui,sans-serif; font-weight:700; font-size:.95rem; }
.drawer-header-right { display:flex; align-items:center; gap:.5rem; }
.drawer-del-btn { background:rgba(220,38,38,.15); color:#fca5a5; border:none; border-radius:6px; width:32px; height:32px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1rem; }
.drawer-del-btn:hover { background:rgba(220,38,38,.3); }
.drawer-close { background:rgba(255,255,255,.1); color:#fff; border:none; border-radius:6px; width:32px; height:32px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.1rem; }
.drawer-close:hover { background:rgba(255,255,255,.2); }
.drawer-body { flex:1; overflow-y:auto; padding:1.25rem; }

/* ── Status pills in drawer ── */
.status-pills { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.status-pill { border:2px solid var(--silver-300); border-radius:8px; padding:.35rem .75rem; font-size:.78rem; font-weight:700; cursor:pointer; background:#fff; transition:all .15s; }
.status-pill:hover { border-color:var(--navy-700); }
.status-pill.active-nuevo       { border-color:var(--blue);   background:var(--blue-bg);   color:var(--blue); }
.status-pill.active-contactado  { border-color:var(--orange); background:var(--orange-bg); color:var(--orange); }
.status-pill.active-seguimiento { border-color:var(--purple); background:var(--purple-bg); color:var(--purple); }
.status-pill.active-ganado      { border-color:var(--green);  background:var(--green-bg);  color:var(--green); }
.status-pill.active-perdido     { border-color:var(--red);    background:var(--red-bg);    color:var(--red); }

/* ── Form fields ── */
.field-group { margin-bottom:1rem; }
.field-label { font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--silver-500); margin-bottom:.3rem; display:block; }
.field-input { width:100%; padding:.55rem .75rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.875rem; outline:none; font-family:inherit; background:#fff; }
.field-input:focus { border-color:var(--navy-700); }
textarea.field-input { min-height:80px; resize:vertical; }
select.field-input { cursor:pointer; }
.field-row { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
.form-actions { display:flex; gap:.75rem; margin-top:1.5rem; flex-wrap:wrap; }
.btn-save { background:var(--navy-700); color:#fff; border:none; padding:.6rem 1.25rem; border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; flex:1; }
.btn-save:hover { background:var(--navy-800); }
.btn-danger { background:var(--red-bg); color:var(--red); border:2px solid var(--red); padding:.55rem 1rem; border-radius:8px; font-size:.8rem; font-weight:600; cursor:pointer; }
.btn-danger:hover { background:var(--red); color:#fff; }
.btn-wa { background:var(--green-bg); color:var(--green); border:2px solid var(--green); padding:.55rem 1rem; border-radius:8px; font-size:.8rem; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:.35rem; }
.divider { height:1px; background:var(--silver-100); margin:1rem 0; }

/* ── Toast ── */
.toast { position:fixed; bottom:1.5rem; right:1.5rem; background:var(--navy-950); color:#fff; padding:.75rem 1.25rem; border-radius:10px; font-size:.84rem; font-weight:600; z-index:400; opacity:0; transform:translateY(10px); transition:all .25s; pointer-events:none; }
.toast.show { opacity:1; transform:translateY(0); }
.toast.ok   { border-left:4px solid var(--green); }
.toast.err  { border-left:4px solid var(--red); }

/* ── AI panels ── */
.ai-summary-box { background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); border-radius:10px; padding:.875rem 1rem; margin-bottom:1.25rem; }
.ai-box-label { font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.09em; color:var(--gold-500); margin-bottom:.5rem; display:flex; align-items:center; gap:.35rem; }
.ai-box-text { font-size:.84rem; color:rgba(255,255,255,.82); line-height:1.65; }
.ai-skeleton { height:12px; background:rgba(255,255,255,.12); border-radius:4px; margin-bottom:.45rem; animation:ai-pulse 1.3s ease-in-out infinite; }
@keyframes ai-pulse { 0%,100%{opacity:.35} 50%{opacity:.85} }
.ai-wa-section { border-top:1px solid var(--silver-100); padding-top:1rem; margin-top:1rem; }
.ai-wa-label { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--silver-500); margin-bottom:.75rem; display:flex; align-items:center; gap:.35rem; }
.btn-gen { background:var(--gold-500); color:var(--navy-950); border:none; padding:.5rem 1rem; border-radius:8px; font-size:.8rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:.35rem; transition:background .15s; }
.btn-gen:hover { background:var(--gold-600); }
.btn-gen:disabled { opacity:.5; cursor:not-allowed; }
.ai-wa-output { background:var(--silver-100); border-radius:10px; padding:1rem; margin-top:.875rem; }
.ai-wa-text { font-size:.875rem; line-height:1.65; color:var(--ink); white-space:pre-wrap; min-height:50px; }
.ai-wa-actions { display:flex; gap:.5rem; margin-top:.75rem; flex-wrap:wrap; align-items:center; }
.btn-copy { background:#fff; border:1.5px solid var(--silver-300); color:var(--navy-900); padding:.38rem .875rem; border-radius:7px; font-size:.78rem; font-weight:600; cursor:pointer; transition:all .15s; }
.btn-copy:hover { border-color:var(--navy-700); }
.btn-copy.copied { border-color:var(--green); color:var(--green); }

/* ── Panel de atribución ── */
.attr-box { background:var(--silver-100); border-radius:8px; padding:.75rem; display:flex; flex-direction:column; gap:.4rem; }
.attr-row { display:flex; justify-content:space-between; align-items:baseline; gap:.5rem; font-size:.78rem; }
.attr-key { color:var(--silver-500); font-weight:600; white-space:nowrap; }
.attr-val { color:var(--navy-800); font-family:'IBM Plex Mono',monospace; font-size:.72rem; word-break:break-all; text-align:right; }

/* ── Modal de status ── */
.smodal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:3000; align-items:center; justify-content:center; }
.smodal-overlay.open { display:flex; }
.smodal { background:#fff; border-radius:14px; padding:1.5rem; width:min(420px,92vw); box-shadow:0 20px 60px rgba(0,0,0,.2); }
.smodal-title { font-size:1rem; font-weight:700; color:var(--ink); margin-bottom:.25rem; }
.smodal-sub { font-size:.8rem; color:var(--silver-500); margin-bottom:1.1rem; }
.smodal label { display:block; font-size:.72rem; font-weight:700; letter-spacing:.06em; color:var(--silver-500); text-transform:uppercase; margin-bottom:.35rem; }
.smodal select, .smodal input[type=date], .smodal textarea { width:100%; padding:.55rem .75rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.875rem; color:var(--ink); background:#fff; box-sizing:border-box; margin-bottom:1rem; font-family:inherit; }
.smodal textarea { resize:vertical; min-height:70px; }
.smodal-actions { display:flex; gap:.5rem; justify-content:flex-end; margin-top:.25rem; }
.smodal-cancel { background:#fff; border:1.5px solid var(--silver-300); color:var(--navy-900); padding:.48rem 1.1rem; border-radius:8px; font-size:.83rem; font-weight:600; cursor:pointer; }
.smodal-confirm { background:var(--navy-800); color:#fff; border:none; padding:.48rem 1.25rem; border-radius:8px; font-size:.83rem; font-weight:700; cursor:pointer; }
.smodal-confirm.danger { background:#c0392b; }
.smodal-confirm:hover { opacity:.9; }

/* ── Timeline de historial ── */
.timeline-box { margin-top:1.25rem; border-top:1.5px solid var(--silver-300); padding-top:1rem; }
.timeline-title { font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--silver-500); margin-bottom:.875rem; }
.timeline-empty { font-size:.8rem; color:var(--silver-500); text-align:center; padding:.5rem 0; }
.tl-item { display:grid; grid-template-columns:2.5rem 1fr; gap:.5rem; margin-bottom:.875rem; }
.tl-dot { display:flex; flex-direction:column; align-items:center; }
.tl-circle { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; color:#fff; flex-shrink:0; }
.tl-circle.nuevo        { background:#64748b; }
.tl-circle.contactado   { background:#2563eb; }
.tl-circle.seguimiento  { background:#d97706; }
.tl-circle.ganado       { background:#16a34a; }
.tl-circle.perdido      { background:#dc2626; }
.tl-line { width:2px; background:var(--silver-300); flex:1; margin-top:.25rem; }
.tl-content { padding-bottom:.5rem; }
.tl-header { font-size:.8rem; font-weight:600; color:var(--ink); }
.tl-header span { color:var(--silver-500); font-weight:400; }
.tl-date { font-size:.72rem; color:var(--silver-500); margin-top:.1rem; }
.tl-nota { font-size:.78rem; color:var(--navy-700); background:var(--silver-100); border-radius:6px; padding:.35rem .6rem; margin-top:.4rem; }
.tl-razon { font-size:.75rem; color:#c0392b; margin-top:.2rem; font-style:italic; }

@media (max-width:768px) {
  .topbar { padding:.75rem 1rem; }
  .main { padding:1rem; }
  .drawer { width:100vw; }
  .field-row { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">Sanalia &amp; Asociados &mdash; <span>CRM</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="nav-tab">Dashboard</a>
    <a href="leads.php"     class="nav-tab active">Contactos</a>
    <a href="calendar.php"  class="nav-tab">Calendario</a>
    <a href="import.php"    class="nav-tab">Importar</a>
    <?php if (($_SESSION['user_rol'] ?? 'admin') === 'admin'): ?>
    <a href="users.php"     class="nav-tab">Usuarios</a>
    <?php endif; ?>
  </div>
  <div class="topbar-actions" style="display:flex;align-items:center;gap:.75rem">
    <?php $mins = guest_minutes_left(); if ($mins >= 0): ?>
    <span id="guestTimer" data-mins="<?= $mins ?>" style="font-size:.78rem;background:#d97706;color:#fff;padding:.25rem .7rem;border-radius:999px;font-weight:700">
      ⏱ <?= $mins ?>m restantes
    </span>
    <?php endif; ?>
    <?php if (!empty($_SESSION['user_nombre'])): ?>
    <span style="font-size:.8rem;color:rgba(255,255,255,.6)"><?= htmlspecialchars($_SESSION['user_nombre']) ?></span>
    <?php endif; ?>
    <form method="POST" action="index.php" style="margin:0">
      <button type="submit" name="logout" class="btn-outline">Salir</button>
    </form>
  </div>
</div>

<div class="main">

<?php if (!$leads_ok): ?>
<div style="background:#fef3c7;border:1.5px solid #d97706;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.875rem;display:flex;align-items:center;gap:.75rem">
  <span style="font-size:1.25rem">⚠️</span>
  <div><strong>Acción requerida:</strong> Ejecuta <code>admin/schema.sql</code> completo en phpMyAdmin para crear la tabla <code>leads</code>.</div>
</div>
<?php else: ?>

<?php
/* ── Conteos por estado ── */
$counts = ['todos' => count($leads), 'nuevo' => 0, 'contactado' => 0, 'seguimiento' => 0, 'ganado' => 0, 'perdido' => 0];
foreach ($leads as $l) { if (isset($counts[$l['estado']])) $counts[$l['estado']]++; }
?>

<div class="page-header">
  <div class="page-title">Contactos &amp; Pipeline</div>
  <button class="btn-primary" onclick="openDrawer(null)">&#43; Nuevo contacto</button>
</div>

<!-- ── Stat pills ── -->
<div class="stat-row">
  <div class="stat-pill active" data-filter="todos" onclick="filterLeads('todos',this)">
    <span class="pill-count pill-todos"><?= $counts['todos'] ?></span>
    <span>Todos</span>
  </div>
  <div class="stat-pill" data-filter="nuevo" onclick="filterLeads('nuevo',this)">
    <span class="pill-count pill-nuevo"><?= $counts['nuevo'] ?></span>
    <span>Nuevo</span>
  </div>
  <div class="stat-pill" data-filter="contactado" onclick="filterLeads('contactado',this)">
    <span class="pill-count pill-contactado"><?= $counts['contactado'] ?></span>
    <span>Contactado</span>
  </div>
  <div class="stat-pill" data-filter="seguimiento" onclick="filterLeads('seguimiento',this)">
    <span class="pill-count pill-seguimiento"><?= $counts['seguimiento'] ?></span>
    <span>Seguimiento</span>
  </div>
  <div class="stat-pill" data-filter="ganado" onclick="filterLeads('ganado',this)">
    <span class="pill-count pill-ganado"><?= $counts['ganado'] ?></span>
    <span>Ganado</span>
  </div>
  <div class="stat-pill" data-filter="perdido" onclick="filterLeads('perdido',this)">
    <span class="pill-count pill-perdido"><?= $counts['perdido'] ?></span>
    <span>Perdido</span>
  </div>
</div>

<!-- ── Toolbar ── -->
<div class="toolbar">
  <div class="search-box">
    <span class="ico">&#128269;</span>
    <input type="text" id="searchInput" placeholder="Buscar por nombre, teléfono, campaña…" oninput="applySearch()">
  </div>
  <select class="filter-sel" id="fuenteFilter" onchange="applySearch()">
    <option value="">Todas las fuentes</option>
    <option value="web">Web</option>
    <option value="facebook">Facebook</option>
    <option value="instagram">Instagram</option>
    <option value="whatsapp">WhatsApp</option>
    <option value="referido">Referido</option>
    <option value="otro">Otro</option>
  </select>
  <select class="filter-sel" id="interesFilter" onchange="applySearch()">
    <option value="">Todos los intereses</option>
    <?php foreach ($interes_opts as $k => $v): ?>
    <option value="<?= $k ?>"><?= $v ?></option>
    <?php endforeach; ?>
  </select>
</div>

<!-- ── Table ── -->
<div class="table-wrap">
  <table id="leadsTable">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Teléfono</th>
        <th>Interés</th>
        <th>Fuente</th>
        <th>Campaña</th>
        <th>Próx. contacto</th>
        <th>Estado</th>
        <th>Fecha</th>
      </tr>
    </thead>
    <tbody id="leadsBody">
    <?php if (empty($leads)): ?>
      <tr class="empty-row"><td colspan="8">Sin contactos aún. Los leads del formulario web aparecerán aquí automáticamente.</td></tr>
    <?php else: ?>
      <?php foreach ($leads as $l):
        $interes_label = $interes_opts[$l['interes']] ?? ($l['interes'] ?: '—');
        $fuente_label  = $fuentes_label[$l['fuente']] ?? $l['fuente'];
        $fecha_corta   = $l['created_at'] ? date('d/m/y', strtotime($l['created_at'])) : '—';
        $prox          = $l['fecha_proximo_contacto'] ? date('d/m/y', strtotime($l['fecha_proximo_contacto'])) : '—';
        $prox_urgent   = $l['fecha_proximo_contacto'] && $l['fecha_proximo_contacto'] <= date('Y-m-d') && $l['estado'] !== 'ganado' && $l['estado'] !== 'perdido';
      ?>
      <tr onclick="openDrawer(<?= htmlspecialchars(json_encode($l), ENT_QUOTES) ?>)"
          data-estado="<?= $l['estado'] ?>"
          data-fuente="<?= $l['fuente'] ?>"
          data-interes="<?= $l['interes'] ?>"
          data-search="<?= htmlspecialchars(mb_strtolower($l['nombre'].' '.$l['telefono'].' '.$l['campana'])) ?>">
        <td>
          <div class="td-nombre"><?= htmlspecialchars($l['nombre']) ?></div>
          <?php if ($l['email']): ?><div style="font-size:.72rem;color:var(--silver-500)"><?= htmlspecialchars($l['email']) ?></div><?php endif; ?>
        </td>
        <td class="td-tel"><?= htmlspecialchars($l['telefono'] ?: '—') ?></td>
        <td><?= htmlspecialchars($interes_label) ?></td>
        <td><span class="badge badge-<?= $l['fuente'] ?>"><?= $fuente_label ?></span></td>
        <td style="font-size:.78rem;color:var(--silver-500)"><?= htmlspecialchars($l['campana'] ?: '—') ?></td>
        <td>
          <?php if ($prox_urgent): ?>
            <span style="color:var(--red);font-weight:700;font-size:.78rem">&#9888; <?= $prox ?></span>
          <?php else: ?>
            <span style="font-size:.78rem"><?= $prox ?></span>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-<?= $l['estado'] ?>"><?= $estados_label[$l['estado']] ?></span></td>
        <td style="font-size:.72rem;color:var(--silver-500)"><?= $fecha_corta ?></td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php endif; ?>
</div><!-- /.main -->

<!-- ── Drawer ── -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="drawer">
  <div class="drawer-header">
    <div class="drawer-header-title" id="drawerTitle">Nuevo contacto</div>
    <div class="drawer-header-right">
      <button class="drawer-del-btn hidden" id="drawerDelBtn" title="Eliminar contacto" onclick="deleteLead()">&#128465;</button>
      <button class="drawer-close" onclick="closeDrawer()">&#10005;</button>
    </div>
  </div>
  <div class="drawer-body" id="drawerBody"></div>
</div>

<!-- ── Toast ── -->
<div class="toast" id="toast"></div>

<script>
const FUENTES = <?= json_encode($fuentes_label) ?>;
const ESTADOS = <?= json_encode($estados_label) ?>;
const INTERES = <?= json_encode($interes_opts) ?>;

let currentLead = null;
let activeFilter = 'todos';

/* ── Filter & search ── */
function filterLeads(estado, pill) {
  activeFilter = estado;
  document.querySelectorAll('.stat-pill').forEach(p => p.classList.remove('active'));
  pill.classList.add('active');
  applySearch();
}
function applySearch() {
  const q       = document.getElementById('searchInput').value.toLowerCase().trim();
  const fuente  = document.getElementById('fuenteFilter').value;
  const interes = document.getElementById('interesFilter').value;
  document.querySelectorAll('#leadsBody tr[data-estado]').forEach(row => {
    const matchFilter  = activeFilter === 'todos' || row.dataset.estado === activeFilter;
    const matchFuente  = !fuente  || row.dataset.fuente  === fuente;
    const matchInteres = !interes || row.dataset.interes === interes;
    const matchSearch  = !q || row.dataset.search.includes(q);
    row.style.display = (matchFilter && matchFuente && matchInteres && matchSearch) ? '' : 'none';
  });
}

/* ── Normaliza teléfono dominicano para WhatsApp ── */
function waPhone(raw) {
  const digits = (raw || '').replace(/\D/g, '');
  // Si ya empieza con 1 y tiene 11 dígitos (1-809/829-xxx-xxxx) → úsalo directo
  if (digits.length === 11 && digits.startsWith('1')) return digits;
  // Si tiene 10 dígitos (809/829-xxx-xxxx) → prefija 1
  if (digits.length === 10) return '1' + digits;
  // Cualquier otro caso → devolver tal cual
  return digits;
}

/* ── Drawer ── */
function openDrawer(lead) {
  currentLead = lead;
  const isNew = !lead;
  document.getElementById('drawerTitle').textContent = isNew ? 'Nuevo contacto' : lead.nombre;
  const delBtn = document.getElementById('drawerDelBtn');
  if (isNew) delBtn.classList.add('hidden'); else delBtn.classList.remove('hidden');

  const estado = lead ? lead.estado : 'nuevo';
  const interesVal = lead ? (lead.interes || '') : '';

  document.getElementById('drawerBody').innerHTML = `

    ${!isNew ? `
    <div class="ai-summary-box" id="aiSummaryBox">
      <div class="ai-box-label">✦ Resumen IA</div>
      <div id="aiSummaryText">
        <div class="ai-skeleton" style="width:88%"></div>
        <div class="ai-skeleton" style="width:70%"></div>
        <div class="ai-skeleton" style="width:55%"></div>
      </div>
    </div>` : ''}

    <div class="status-pills">
      ${Object.entries(ESTADOS).map(([k,v]) =>
        `<button class="status-pill${estado===k?' active-'+k:''}" data-s="${k}" onclick="quickStatus('${k}',this)">${v}</button>`
      ).join('')}
    </div>

    <form id="leadForm" onsubmit="saveLead(event)">
      <input type="hidden" name="id" value="${lead ? lead.id : ''}">

      <div class="field-row">
        <div class="field-group">
          <label class="field-label">Nombre *</label>
          <input type="text" name="nombre" class="field-input" required value="${esc(lead?.nombre)}">
        </div>
        <div class="field-group">
          <label class="field-label">Teléfono</label>
          <input type="text" name="telefono" class="field-input" value="${esc(lead?.telefono)}">
        </div>
      </div>

      <div class="field-group">
        <label class="field-label">Email</label>
        <input type="email" name="email" class="field-input" value="${esc(lead?.email)}">
      </div>

      <div class="field-row">
        <div class="field-group">
          <label class="field-label">Línea de interés</label>
          <select name="interes" class="field-input">
            <option value="">— seleccionar —</option>
            ${Object.entries(INTERES).map(([k,v]) =>
              `<option value="${k}"${interesVal===k?' selected':''}>${v}</option>`
            ).join('')}
          </select>
        </div>
        <div class="field-group">
          <label class="field-label">Fuente</label>
          <select name="fuente" class="field-input">
            ${Object.entries(FUENTES).map(([k,v]) =>
              `<option value="${k}"${(lead?.fuente||'web')===k?' selected':''}>${v}</option>`
            ).join('')}
          </select>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label">Campaña (UTM)</label>
        <input type="text" name="campana" class="field-input" value="${esc(lead?.campana)}" placeholder="ej. blackfriday-2025">
      </div>

      <div class="field-group">
        <label class="field-label">Próxima fecha de contacto</label>
        <input type="date" name="fecha_proximo_contacto" class="field-input" value="${esc(lead?.fecha_proximo_contacto)}">
      </div>

      <div class="field-group">
        <label class="field-label">Mensaje original</label>
        <textarea name="mensaje" class="field-input" rows="3">${esc(lead?.mensaje)}</textarea>
      </div>

      <div class="field-group">
        <label class="field-label">Notas internas</label>
        <textarea name="notas" class="field-input" rows="3">${esc(lead?.notas)}</textarea>
      </div>

      ${(lead?.fbclid || lead?.gclid || lead?.ga_client_id) ? `
      <div class="field-group">
        <label class="field-label">Atribución publicitaria</label>
        <div class="attr-box">
          ${lead?.fbclid ? `<div class="attr-row"><span class="attr-key">Facebook Click ID</span><span class="attr-val">${esc(lead.fbclid)}</span></div>` : ''}
          ${lead?.gclid  ? `<div class="attr-row"><span class="attr-key">Google Click ID</span><span class="attr-val">${esc(lead.gclid)}</span></div>` : ''}
          ${lead?.ga_client_id ? `<div class="attr-row"><span class="attr-key">GA4 Client ID</span><span class="attr-val">${esc(lead.ga_client_id)}</span></div>` : ''}
        </div>
      </div>` : ''}

      <input type="hidden" name="estado" id="formEstado" value="${estado}">

      <div class="divider"></div>

      <div class="form-actions">
        <button type="submit" class="btn-save">Guardar</button>
        ${lead?.telefono ? `<a href="https://wa.me/${waPhone(lead.telefono)}" target="_blank" class="btn-wa">&#128172; WhatsApp</a>` : ''}
      </div>

      ${lead ? `<div style="margin-top:1rem;text-align:center">
        <button type="button" class="btn-danger" onclick="deleteLead()">&#128465; Eliminar contacto</button>
      </div>` : ''}
    </form>

    ${!isNew ? `
    <div class="timeline-box">
      <div class="timeline-title">Historial de cambios</div>
      <div id="timelineBox"><div class="timeline-empty">Cargando…</div></div>
    </div>` : ''}

    <div class="ai-wa-section">
      <div class="ai-wa-label">✦ Generador de mensaje WhatsApp</div>
      <button class="btn-gen" id="btnGenWA" onclick="generateWAMsg()" ${isNew ? 'disabled title="Guarda el contacto primero"' : ''}>
        ✦ Generar mensaje
      </button>
      <div id="waOutput" style="display:none">
        <div class="ai-wa-output">
          <div class="ai-wa-text" id="waText"></div>
          <div class="ai-wa-actions">
            <button class="btn-copy" onclick="copyMsg(this)">Copiar</button>
            ${lead?.telefono ? `<a href="https://wa.me/${waPhone(lead.telefono)}" target="_blank" class="btn-wa">Abrir WhatsApp</a>` : ''}
          </div>
        </div>
      </div>
    </div>
  `;

  document.getElementById('drawerOverlay').classList.add('open');
  document.getElementById('drawer').classList.add('open');
  document.getElementById('drawerBody').scrollTop = 0;

  if (!isNew) {
    loadAISummary(lead);
    loadTimeline(lead.id);
  }
}

function closeDrawer() {
  document.getElementById('drawerOverlay').classList.remove('open');
  document.getElementById('drawer').classList.remove('open');
  currentLead = null;
}

function esc(v) { return v ? String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

/* ── Save lead ── */
async function saveLead(e) {
  e.preventDefault();
  const form = e.target;
  const fd   = new FormData(form);
  try {
    const r = await fetch('api.php?action=lead_save', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
      toast('Guardado', 'ok');
      closeDrawer();
      setTimeout(() => location.reload(), 600);
    } else {
      toast(d.error || 'Error al guardar', 'err');
    }
  } catch(err) { toast('Error de conexión', 'err'); }
}

/* ── Delete lead ── */
async function deleteLead() {
  if (!currentLead || !confirm(`¿Eliminar el contacto "${currentLead.nombre}"?`)) return;
  const fd = new FormData();
  fd.append('id', currentLead.id);
  try {
    const r = await fetch('api.php?action=lead_delete', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
      toast('Eliminado', 'ok');
      closeDrawer();
      setTimeout(() => location.reload(), 600);
    } else {
      toast(d.error || 'Error', 'err');
    }
  } catch(err) { toast('Error de conexión', 'err'); }
}

/* ── Toast ── */
function toast(msg, type='ok') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className   = 'toast show ' + type;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3000);
}

/* ── IA: Resumen automático del lead ── */
async function loadAISummary(lead) {
  const box = document.getElementById('aiSummaryText');
  if (!box || !lead) return;
  const dias = lead.created_at
    ? Math.max(0, Math.floor((Date.now() - new Date(lead.created_at).getTime()) / 86400000))
    : 0;
  const fd = new FormData();
  fd.append('action',                  'lead_summary');
  fd.append('nombre',                  lead.nombre  || '');
  fd.append('interes',                 lead.interes || '');
  fd.append('estado',                  lead.estado  || '');
  fd.append('fuente',                  lead.fuente  || '');
  fd.append('campana',                 lead.campana || '');
  fd.append('notas',                   lead.notas   || '');
  fd.append('mensaje',                 lead.mensaje || '');
  fd.append('fecha_proximo_contacto',  lead.fecha_proximo_contacto || '');
  fd.append('dias_sin_contacto',       String(dias));
  try {
    const r = await fetch('ai.php', { method:'POST', body:fd });
    const d = await r.json();
    if (!document.getElementById('aiSummaryText')) return; // drawer cerrado
    if (d.ok) {
      box.textContent = d.summary;
      box.style.color = '';
    } else {
      box.textContent = d.error || 'No se pudo generar el resumen.';
      box.style.color = 'rgba(255,255,255,.4)';
    }
  } catch(e) {
    if (document.getElementById('aiSummaryText'))
      box.textContent = 'Sin conexión al servicio de IA.';
  }
}

/* ── IA: Generar mensaje de WhatsApp ── */
async function generateWAMsg() {
  const btn  = document.getElementById('btnGenWA');
  const lead = currentLead;
  if (!lead) return;
  btn.disabled    = true;
  btn.textContent = 'Generando…';

  const dias = lead.created_at
    ? Math.max(0, Math.floor((Date.now() - new Date(lead.created_at).getTime()) / 86400000))
    : 0;
  const fd = new FormData();
  fd.append('action',              'whatsapp_message');
  fd.append('nombre',              lead.nombre  || '');
  fd.append('interes',             lead.interes || '');
  fd.append('estado',              lead.estado  || 'nuevo');
  fd.append('fuente',              lead.fuente  || 'web');
  fd.append('notas',               lead.notas   || '');
  fd.append('campana',             lead.campana || '');
  fd.append('dias_sin_contacto',   String(dias));

  try {
    const r = await fetch('ai.php', { method:'POST', body:fd });
    const d = await r.json();
    const out = document.getElementById('waOutput');
    const txt = document.getElementById('waText');
    if (!out) return;
    out.style.display = 'block';
    txt.textContent   = d.ok ? d.message : ('⚠ ' + (d.error || 'Error generando mensaje'));
    document.getElementById('drawerBody').scrollTop = 99999;
  } catch(e) {
    const out = document.getElementById('waOutput');
    if (out) {
      out.style.display = 'block';
      document.getElementById('waText').textContent = '⚠ Error de conexión con el servicio de IA.';
    }
  }

  btn.disabled = false;
  btn.innerHTML = '✦ Regenerar';
}

/* ── Copiar mensaje WA ── */
function copyMsg(btn) {
  const txt = document.getElementById('waText')?.textContent;
  if (!txt) return;
  navigator.clipboard.writeText(txt).then(() => {
    const orig = btn.textContent;
    btn.textContent = '✓ Copiado';
    btn.classList.add('copied');
    setTimeout(() => { btn.textContent = orig; btn.classList.remove('copied'); }, 2000);
  });
}

/* ════════════════════════════════════════════
   MODAL DE CAMBIO DE STATUS
   Uso: statusModal.open(nuevoEstado)
════════════════════════════════════════════ */
const RAZONES_PERDIDA = [
  '— seleccionar razón —',
  'Sin presupuesto',
  'Eligió otra aseguradora',
  'Sin respuesta del prospecto',
  'No calificó para el producto',
  'Perdió el interés',
  'Precio fuera de rango',
  'Otra razón',
];

const STATUS_LABELS = { nuevo:'Nuevo', contactado:'Contactado', seguimiento:'Seguimiento', ganado:'Ganado', perdido:'Perdido' };

const statusModal = (() => {
  const overlay = document.createElement('div');
  overlay.className = 'smodal-overlay';
  overlay.innerHTML = `
    <div class="smodal" id="smodalBox">
      <div class="smodal-title" id="smodalTitle"></div>
      <div class="smodal-sub"  id="smodalSub"></div>
      <div id="smodalFields"></div>
      <div class="smodal-actions">
        <button class="smodal-cancel" onclick="statusModal.close()">Cancelar</button>
        <button class="smodal-confirm" id="smodalConfirm" onclick="statusModal.confirm()">Confirmar</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);

  let _resolve = null;

  function open(nuevoEstado) {
    const estadoActual = currentLead?.estado || 'nuevo';
    const de = STATUS_LABELS[estadoActual] || estadoActual;
    const a  = STATUS_LABELS[nuevoEstado]  || nuevoEstado;

    document.getElementById('smodalTitle').textContent = `Mover a ${a}`;
    document.getElementById('smodalSub').textContent   = `${de} → ${a}`;

    const confirmBtn = document.getElementById('smodalConfirm');
    confirmBtn.className = 'smodal-confirm' + (nuevoEstado === 'perdido' ? ' danger' : '');
    confirmBtn.textContent = nuevoEstado === 'ganado' ? '🎉 Confirmar' : 'Confirmar';

    let fields = '';

    if (nuevoEstado === 'perdido') {
      fields += `
        <label>¿Por qué se perdió este lead?</label>
        <select id="smodalRazon">
          ${RAZONES_PERDIDA.map((r,i) => `<option value="${i===0?'':r}">${r}</option>`).join('')}
        </select>`;
    }

    if (['contactado','seguimiento','ganado'].includes(nuevoEstado)) {
      const hoy = new Date();
      const def = nuevoEstado === 'contactado'
        ? new Date(hoy.setDate(hoy.getDate()+3)).toISOString().slice(0,10)
        : nuevoEstado === 'seguimiento'
          ? new Date(hoy.setDate(hoy.getDate()+7)).toISOString().slice(0,10)
          : '';
      fields += `
        <label>Próxima fecha de contacto</label>
        <input type="date" id="smodalFecha" value="${def}">`;
    }

    fields += `
      <label>Nota sobre este movimiento (opcional)</label>
      <textarea id="smodalNota" placeholder="ej. Llamé y pidió cotización para la próxima semana…"></textarea>`;

    document.getElementById('smodalFields').innerHTML = fields;
    overlay.classList.add('open');

    return new Promise(res => { _resolve = res; });
  }

  function close() {
    overlay.classList.remove('open');
    if (_resolve) { _resolve(null); _resolve = null; }
  }

  async function confirm() {
    const razon = document.getElementById('smodalRazon')?.value || '';
    const fecha = document.getElementById('smodalFecha')?.value || '';
    const nota  = document.getElementById('smodalNota')?.value  || '';
    close();
    if (_resolve === null && razon !== undefined) {
      // ya fue cerrada — no hacer nada
    }
    // retornamos data al llamador original
    // (ya hicimos close que llama resolve(null), entonces usamos otro canal)
    return { razon, fecha, nota };
  }

  // Versión corregida: exponer como promesa correctamente
  function open2(nuevoEstado) {
    const estadoActual = currentLead?.estado || 'nuevo';
    const de = STATUS_LABELS[estadoActual] || estadoActual;
    const a  = STATUS_LABELS[nuevoEstado]  || nuevoEstado;

    document.getElementById('smodalTitle').textContent = `Mover a ${a}`;
    document.getElementById('smodalSub').textContent   = `${de} → ${a}`;

    const confirmBtn = document.getElementById('smodalConfirm');
    confirmBtn.className = 'smodal-confirm' + (nuevoEstado === 'perdido' ? ' danger' : '');
    confirmBtn.textContent = nuevoEstado === 'ganado' ? '🎉 Confirmar' : 'Confirmar';

    let fields = '';
    if (nuevoEstado === 'perdido') {
      fields += `<label>¿Por qué se perdió este lead?</label>
        <select id="smodalRazon">
          ${RAZONES_PERDIDA.map((r,i) => `<option value="${i===0?'':r}">${r}</option>`).join('')}
        </select>`;
    }
    if (['contactado','seguimiento','ganado'].includes(nuevoEstado)) {
      const hoy = new Date();
      const dias = nuevoEstado==='contactado' ? 3 : nuevoEstado==='seguimiento' ? 7 : 0;
      if (dias) hoy.setDate(hoy.getDate()+dias);
      const def = dias ? hoy.toISOString().slice(0,10) : '';
      fields += `<label>Próxima fecha de contacto</label>
        <input type="date" id="smodalFecha" value="${def}">`;
    }
    fields += `<label>Nota (opcional)</label>
      <textarea id="smodalNota" placeholder="ej. Pidió cotización para la próxima semana…"></textarea>`;

    document.getElementById('smodalFields').innerHTML = fields;

    return new Promise(res => {
      _resolve = res;
      overlay.classList.add('open');
      document.getElementById('smodalConfirm').onclick = () => {
        const data = {
          razon: document.getElementById('smodalRazon')?.value || '',
          fecha: document.getElementById('smodalFecha')?.value || '',
          nota:  document.getElementById('smodalNota')?.value  || '',
        };
        overlay.classList.remove('open');
        _resolve = null;
        res(data);
      };
      document.querySelector('.smodal-cancel').onclick = () => {
        overlay.classList.remove('open');
        _resolve = null;
        res(null);
      };
    });
  }

  return { open: open2 };
})();

/* ── quickStatus con modal ── */
async function quickStatus(nuevoEstado, btn) {
  if (!currentLead) {
    // Lead nuevo, solo cambiar el visual sin llamada a API
    document.querySelectorAll('.status-pill').forEach(p =>
      p.classList.remove(...['nuevo','contactado','seguimiento','ganado','perdido'].map(s=>'active-'+s)));
    btn.classList.add('active-' + nuevoEstado);
    document.getElementById('formEstado').value = nuevoEstado;
    return;
  }

  if (nuevoEstado === currentLead.estado) return; // sin cambio

  const data = await statusModal.open(nuevoEstado);
  if (!data) return; // canceló

  const fd = new FormData();
  fd.append('id',    currentLead.id);
  fd.append('estado', nuevoEstado);
  if (data.razon) fd.append('razon_perdida', data.razon);
  if (data.nota)  fd.append('nota', data.nota);
  if (data.fecha) fd.append('fecha_proximo_contacto', data.fecha);

  try {
    const r = await fetch('api.php?action=lead_status', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
      currentLead.estado = nuevoEstado;
      if (data.fecha) currentLead.fecha_proximo_contacto = data.fecha;

      // Actualizar pills visualmente
      document.querySelectorAll('.status-pill').forEach(p =>
        p.classList.remove(...['nuevo','contactado','seguimiento','ganado','perdido'].map(s=>'active-'+s)));
      btn.classList.add('active-' + nuevoEstado);
      document.getElementById('formEstado').value = nuevoEstado;

      // Actualizar campo fecha en el form si existe
      const fechaInput = document.querySelector('[name="fecha_proximo_contacto"]');
      if (fechaInput && data.fecha) fechaInput.value = data.fecha;

      toast('Estado actualizado', 'ok');
      loadTimeline(currentLead.id);
    } else {
      toast(d.error || 'Error al actualizar', 'err');
    }
  } catch(e) { toast('Error de conexión', 'err'); }
}

/* ── Timeline de historial ── */
const ESTADO_INITIALS = { nuevo:'N', contactado:'C', seguimiento:'S', ganado:'G', perdido:'P' };

async function loadTimeline(leadId) {
  const box = document.getElementById('timelineBox');
  if (!box) return;

  box.innerHTML = '<div class="timeline-empty">Cargando historial…</div>';

  try {
    const fd = new FormData();
    fd.append('id', leadId);
    const r = await fetch('api.php?action=lead_historial', { method:'POST', body:fd });
    const d = await r.json();

    if (!d.ok || !d.historial?.length) {
      box.innerHTML = '<div class="timeline-empty">Sin cambios registrados aún.</div>';
      return;
    }

    box.innerHTML = d.historial.map((h, i) => {
      const fecha = new Date(h.fecha).toLocaleString('es-DO', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' });
      const isLast = i === d.historial.length - 1;
      return `
        <div class="tl-item">
          <div class="tl-dot">
            <div class="tl-circle ${h.estado_nuevo}">${ESTADO_INITIALS[h.estado_nuevo]||'?'}</div>
            ${!isLast ? '<div class="tl-line"></div>' : ''}
          </div>
          <div class="tl-content">
            <div class="tl-header">
              ${STATUS_LABELS[h.estado_anterior]||h.estado_anterior}
              <span>→</span>
              ${STATUS_LABELS[h.estado_nuevo]||h.estado_nuevo}
            </div>
            <div class="tl-date">${fecha}</div>
            ${h.razon_perdida ? `<div class="tl-razon">Razón: ${h.razon_perdida}</div>` : ''}
            ${h.nota ? `<div class="tl-nota">${h.nota}</div>` : ''}
          </div>
        </div>`;
    }).join('');
  } catch(e) {
    box.innerHTML = '<div class="timeline-empty">Error al cargar historial.</div>';
  }
}
</script>
</body>
</html>
