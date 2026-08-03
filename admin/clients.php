<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db.php';

$db = get_db();

/* ── Vencimientos: 30/60/90 días ── */
$today     = date('Y-m-d');
$in30      = date('Y-m-d', strtotime('+30 days'));
$in60      = date('Y-m-d', strtotime('+60 days'));
$in90      = date('Y-m-d', strtotime('+90 days'));

$cnt_vence30 = $db->query("SELECT COUNT(*) FROM policies WHERE estado='activa' AND fecha_vencimiento BETWEEN '$today' AND '$in30'")->fetchColumn();
$cnt_vence60 = $db->query("SELECT COUNT(*) FROM policies WHERE estado='activa' AND fecha_vencimiento BETWEEN '$today' AND '$in60'")->fetchColumn();
$cnt_vence90 = $db->query("SELECT COUNT(*) FROM policies WHERE estado='activa' AND fecha_vencimiento BETWEEN '$today' AND '$in90'")->fetchColumn();
$cnt_vencidas = $db->query("SELECT COUNT(*) FROM policies WHERE estado='activa' AND fecha_vencimiento < '$today'")->fetchColumn();
$cnt_clients = $db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$cnt_activas = $db->query("SELECT COUNT(*) FROM policies WHERE estado='activa'")->fetchColumn();

/* ── Pólizas por vencer (próximos 90 días) ── */
$vencimientos = $db->query(
    "SELECT p.*, c.nombre AS cliente_nombre, c.telefono AS cliente_tel, c.email AS cliente_email
     FROM policies p JOIN clients c ON c.id=p.client_id
     WHERE p.estado='activa' AND p.fecha_vencimiento BETWEEN '$today' AND '$in90'
     ORDER BY p.fecha_vencimiento ASC
     LIMIT 50"
)->fetchAll();

/* ── Lista de clientes con póliza más próxima ── */
$clients = $db->query(
    "SELECT c.*,
        COUNT(p.id) AS num_polizas,
        SUM(CASE WHEN p.estado='activa' THEN 1 ELSE 0 END) AS polizas_activas,
        MIN(CASE WHEN p.estado='activa' THEN p.fecha_vencimiento END) AS proximo_vencimiento
     FROM clients c
     LEFT JOIN policies p ON p.client_id = c.id
     GROUP BY c.id
     ORDER BY c.nombre ASC"
)->fetchAll();

$tipos_seguro = [
    'vida','salud','salud-persona','viajes','vehiculos',
    'accidentes-personales','internacionales','riesgos-generales','mascotas','otro'
];

function dias_para_vencer(string $fecha): int {
    return (int) ceil((strtotime($fecha) - time()) / 86400);
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Clientes — Sanalia CRM</title>
<meta name="robots" content="noindex, nofollow">
<style>
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
:root {
  --navy-950:#071523; --navy-900:#0C2036; --navy-800:#153350; --navy-700:#1E4468;
  --gold-500:#C6A15B; --gold-600:#A9843F;
  --silver-100:#F3F5F7; --silver-300:#DCE1E7; --silver-500:#AEB8C4;
  --ink:#0E1620; --green:#1a7f4b; --green-bg:#e8f5ee;
  --orange:#d97706; --orange-bg:#fef3c7;
  --red:#dc2626; --red-bg:#fee2e2;
  --purple:#7c3aed; --purple-bg:#ede9fe;
}
body { font-family:'Inter',system-ui,sans-serif; background:var(--silver-100); color:var(--ink); min-height:100vh; }

/* ── Topbar ── */
.topbar { background:var(--navy-900); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:.875rem 2rem; position:sticky; top:0; z-index:100; gap:1rem; flex-wrap:wrap; }
.topbar .brand { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1rem; }
.topbar .brand span { color:var(--gold-500); }
.topbar-nav { display:flex; gap:.25rem; }
.nav-tab { padding:.4rem .875rem; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; color:rgba(255,255,255,.6); transition:all .18s; border:none; background:none; cursor:pointer; }
.nav-tab:hover { color:#fff; background:rgba(255,255,255,.08); }
.nav-tab.active { color:#fff; background:rgba(255,255,255,.15); }
.topbar-actions { display:flex; gap:.75rem; align-items:center; }
.btn-gold { background:var(--gold-500); color:var(--navy-950); border:none; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:background .2s; }
.btn-gold:hover { background:var(--gold-600); }
.btn-outline { background:transparent; border:1.5px solid rgba(255,255,255,.3); color:#fff; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; cursor:pointer; }
.btn-outline:hover { border-color:#fff; }

/* ── Main ── */
.main { max-width:1280px; margin:0 auto; padding:2rem 1.5rem; }

/* ── Stats ── */
.stats { display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
.stat-card { background:#fff; border-radius:10px; padding:1.1rem 1.25rem; flex:1; min-width:110px; box-shadow:0 1px 6px rgba(7,21,35,.07); }
.stat-card .num { font-family:'IBM Plex Mono',monospace; font-size:1.75rem; font-weight:700; color:var(--navy-900); line-height:1; }
.stat-card .label { font-size:.72rem; color:var(--silver-500); text-transform:uppercase; letter-spacing:.05em; margin-top:.3rem; }
.stat-card.warn .num { color:var(--orange); }
.stat-card.danger .num { color:var(--red); }
.stat-card.ok .num { color:var(--green); }

/* ── Alertas vencimientos ── */
.vence-section { margin-bottom:2rem; }
.section-title { font-family:'Manrope',system-ui,sans-serif; font-weight:700; font-size:.95rem; color:var(--navy-900); margin-bottom:.875rem; display:flex; align-items:center; gap:.5rem; }
.section-title::after { content:''; flex:1; height:1px; background:var(--silver-300); }

.vence-cards { display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
.vence-card { flex:1; min-width:160px; background:#fff; border-radius:10px; padding:1rem 1.25rem; box-shadow:0 1px 6px rgba(7,21,35,.07); border-top:3px solid var(--silver-300); cursor:pointer; transition:box-shadow .2s; }
.vence-card:hover { box-shadow:0 4px 16px rgba(7,21,35,.12); }
.vence-card.v30 { border-color:var(--red); }
.vence-card.v60 { border-color:var(--orange); }
.vence-card.v90 { border-color:var(--purple); }
.vence-card .vnum { font-family:'IBM Plex Mono',monospace; font-size:1.75rem; font-weight:700; line-height:1; }
.vence-card.v30 .vnum { color:var(--red); }
.vence-card.v60 .vnum { color:var(--orange); }
.vence-card.v90 .vnum { color:var(--purple); }
.vence-card .vlabel { font-size:.72rem; color:var(--silver-500); text-transform:uppercase; letter-spacing:.05em; margin-top:.3rem; }

/* ── Tabla vencimientos ── */
.table-wrap { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(7,21,35,.07); overflow:hidden; margin-bottom:2rem; }
table { width:100%; border-collapse:collapse; font-size:.85rem; }
thead { background:var(--navy-950); color:#fff; }
thead th { padding:.7rem 1rem; text-align:left; font-family:'IBM Plex Mono',monospace; font-size:.68rem; letter-spacing:.07em; text-transform:uppercase; font-weight:500; white-space:nowrap; }
tbody tr { border-bottom:1px solid var(--silver-100); transition:background .15s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:#eef2f7; cursor:pointer; }
td { padding:.75rem 1rem; vertical-align:middle; }
td.fecha { font-family:'IBM Plex Mono',monospace; font-size:.75rem; white-space:nowrap; }
.hidden-row { display:none; }
.empty-msg { text-align:center; padding:3rem; color:var(--silver-500); font-size:.875rem; }

/* ── Badges ── */
.badge { display:inline-block; padding:.2rem .6rem; border-radius:999px; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
.badge.activa    { background:var(--green-bg);  color:var(--green); }
.badge.vencida   { background:var(--red-bg);    color:var(--red); }
.badge.cancelada { background:#f3f4f6;           color:#6b7280; }
.badge.en-renovacion { background:var(--purple-bg); color:var(--purple); }
.badge.dias-30 { background:var(--red-bg);    color:var(--red); }
.badge.dias-60 { background:var(--orange-bg); color:var(--orange); }
.badge.dias-90 { background:var(--purple-bg); color:var(--purple); }

/* ── Clients search bar ── */
.search-bar { display:flex; gap:.75rem; margin-bottom:1.25rem; align-items:center; flex-wrap:wrap; }
.search-input { flex:1; min-width:200px; padding:.6rem 1rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.875rem; outline:none; background:#fff; }
.search-input:focus { border-color:var(--navy-700); }
.result-count { font-size:.8rem; color:var(--silver-500); white-space:nowrap; }
.btn-add { background:var(--navy-900); color:#fff; border:none; padding:.6rem 1.25rem; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; }
.btn-add:hover { background:var(--navy-700); }

/* ── Drawer ── */
.drawer-overlay { display:none; position:fixed; inset:0; background:rgba(7,21,35,.5); z-index:200; backdrop-filter:blur(2px); }
.drawer-overlay.open { display:block; }
.drawer { position:fixed; top:0; right:0; bottom:0; width:560px; max-width:100vw; background:#fff; z-index:201; box-shadow:-8px 0 40px rgba(7,21,35,.18); transform:translateX(100%); transition:transform .28s cubic-bezier(.16,1,.3,1); display:flex; flex-direction:column; }
.drawer.open { transform:translateX(0); }
.drawer-header { background:var(--navy-900); color:#fff; padding:1.25rem 1.5rem; display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-shrink:0; }
.drawer-header h2 { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.05rem; }
.drawer-header .sub { font-size:.75rem; color:var(--silver-500); margin-top:.2rem; }
.drawer-close { background:rgba(255,255,255,.12); border:none; color:#fff; width:32px; height:32px; border-radius:50%; cursor:pointer; font-size:1rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.drawer-close:hover { background:rgba(255,255,255,.25); }
.drawer-del-btn { background:rgba(220,38,38,.18); border:1.5px solid rgba(220,38,38,.5); color:#fff; width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:.85rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:background .18s; }
.drawer-del-btn:hover { background:rgba(220,38,38,.4); }
.drawer-del-btn.hidden { display:none; }
.drawer-body { flex:1; overflow-y:auto; padding:1.5rem; display:flex; flex-direction:column; gap:1.25rem; }
.drawer-footer { padding:1rem 1.5rem; border-top:1px solid var(--silver-300); display:flex; gap:.75rem; flex-shrink:0; }

/* ── Formulario ── */
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:.875rem; }
.form-grid.single { grid-template-columns:1fr; }
.field { display:flex; flex-direction:column; gap:.35rem; }
.field.full { grid-column:1/-1; }
label { font-size:.75rem; font-weight:600; color:var(--navy-900); text-transform:uppercase; letter-spacing:.04em; }
input[type="text"], input[type="email"], input[type="tel"], input[type="date"],
input[type="number"], select, textarea {
  padding:.625rem .875rem; border:1.5px solid var(--silver-300); border-radius:8px;
  font-size:.875rem; font-family:inherit; outline:none; transition:border-color .2s;
  background:#fff; color:var(--ink);
}
input:focus, select:focus, textarea:focus { border-color:var(--navy-700); }
textarea { resize:vertical; min-height:72px; }
.btn-primary { flex:1; padding:.7rem; background:var(--navy-900); color:#fff; border:none; border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; transition:background .2s; }
.btn-primary:hover { background:var(--navy-700); }
.btn-primary.saved { background:var(--green); }
.btn-danger { padding:.7rem 1.25rem; background:#fff; color:var(--red); border:1.5px solid var(--red); border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; }
.btn-danger:hover { background:var(--red-bg); }
.btn-secondary { padding:.7rem 1rem; background:#fff; color:var(--navy-900); border:1.5px solid var(--silver-300); border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; }
.btn-secondary:hover { border-color:var(--navy-700); }

/* ── Pólizas lista en drawer ── */
.policies-list { display:flex; flex-direction:column; gap:.625rem; }
.policy-card { background:var(--silver-100); border-radius:10px; padding:.875rem 1rem; border-left:3px solid var(--silver-300); cursor:pointer; transition:border-color .18s; }
.policy-card:hover { border-color:var(--gold-500); }
.policy-card.activa    { border-color:var(--green); }
.policy-card.vencida   { border-color:var(--red); }
.policy-card.en-renovacion { border-color:var(--purple); }
.policy-card .p-tipo { font-weight:700; font-size:.875rem; color:var(--navy-900); }
.policy-card .p-meta { font-size:.78rem; color:var(--silver-500); margin-top:.2rem; display:flex; gap:.75rem; flex-wrap:wrap; }
.policy-card .p-vence { font-family:'IBM Plex Mono',monospace; font-size:.75rem; }
.btn-add-policy { width:100%; padding:.6rem; background:var(--silver-100); color:var(--navy-700); border:1.5px dashed var(--silver-300); border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; transition:all .18s; }
.btn-add-policy:hover { background:var(--e-nuevo-bg, #e8f0f8); border-color:var(--navy-700); }

/* ── Import modal ── */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(7,21,35,.6); z-index:300; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal { background:#fff; border-radius:14px; padding:2rem; width:100%; max-width:480px; box-shadow:0 8px 40px rgba(7,21,35,.2); }
.modal h3 { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.1rem; margin-bottom:1rem; }
.modal p { font-size:.875rem; color:#555; margin-bottom:1rem; line-height:1.6; }
.modal .hint { background:var(--silver-100); border-radius:8px; padding:.875rem; font-size:.78rem; font-family:'IBM Plex Mono',monospace; color:var(--navy-700); margin-bottom:1rem; line-height:1.8; }
.file-input { width:100%; padding:.6rem; border:1.5px dashed var(--silver-300); border-radius:8px; font-size:.875rem; margin-bottom:1rem; cursor:pointer; }
.modal-actions { display:flex; gap:.75rem; }
.import-result { margin-top:1rem; padding:.75rem 1rem; border-radius:8px; font-size:.85rem; display:none; }
.import-result.ok { background:var(--green-bg); color:var(--green); }
.import-result.err { background:var(--red-bg); color:var(--red); }

@media (max-width:768px) {
  .topbar { padding:.75rem 1rem; }
  .main { padding:1.25rem 1rem; }
  .form-grid { grid-template-columns:1fr; }
  .drawer { width:100vw; }
  td:nth-child(4), td:nth-child(5) { display:none; }
  thead th:nth-child(4), thead th:nth-child(5) { display:none; }
  .result-count { display:none; }
}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">Sanalia &amp; Asociados &mdash; <span>CRM</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="nav-tab">Dashboard</a>
    <a href="index.php"     class="nav-tab">Solicitudes</a>
    <a href="clients.php"   class="nav-tab active">Clientes</a>
    <a href="finances.php"  class="nav-tab">Finanzas</a>
    <a href="calendar.php"  class="nav-tab">Calendario</a>
    <a href="import.php"    class="nav-tab">Importar</a>
  </div>
  <div class="topbar-actions">
    <button class="btn-gold" id="btnImport">↑ Importar CSV</button>
    <form method="POST" action="index.php" style="margin:0">
      <button type="submit" name="logout" class="btn-outline">Salir</button>
    </form>
  </div>
</div>

<div class="main">

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card ok"><div class="num"><?= (int)$cnt_clients ?></div><div class="label">Clientes</div></div>
    <div class="stat-card ok"><div class="num"><?= (int)$cnt_activas ?></div><div class="label">Pólizas activas</div></div>
    <div class="stat-card <?= $cnt_vencidas > 0 ? 'danger' : '' ?>"><div class="num"><?= (int)$cnt_vencidas ?></div><div class="label">Vencidas sin renovar</div></div>
    <div class="stat-card warn"><div class="num"><?= (int)$cnt_vence30 ?></div><div class="label">Vencen en 30 días</div></div>
  </div>

  <!-- Vencimientos próximos -->
  <div class="vence-section">
    <div class="section-title">Pólizas por vencer</div>
    <div class="vence-cards">
      <div class="vence-card v30" data-filter="30"><div class="vnum"><?= (int)$cnt_vence30 ?></div><div class="vlabel">En los próximos 30 días</div></div>
      <div class="vence-card v60" data-filter="60"><div class="vnum"><?= (int)$cnt_vence60 ?></div><div class="vlabel">En los próximos 60 días</div></div>
      <div class="vence-card v90" data-filter="90"><div class="vnum"><?= (int)$cnt_vence90 ?></div><div class="vlabel">En los próximos 90 días</div></div>
    </div>

    <?php if (!empty($vencimientos)): ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Cliente</th><th>Tipo</th><th>No. Póliza</th>
          <th>Vence</th><th>Días</th><th>Estado</th>
        </tr></thead>
        <tbody>
        <?php foreach ($vencimientos as $v):
          $dias = dias_para_vencer($v['fecha_vencimiento']);
          $cls  = $dias <= 30 ? 'dias-30' : ($dias <= 60 ? 'dias-60' : 'dias-90');
        ?>
        <tr class="vence-row" data-client-id="<?= $v['client_id'] ?>" data-dias="<?= $dias ?>">
          <td style="font-weight:600"><?= htmlspecialchars($v['cliente_nombre']) ?></td>
          <td><?= htmlspecialchars($v['tipo'] ?? '—') ?></td>
          <td style="font-family:monospace;font-size:.8rem"><?= htmlspecialchars($v['numero_poliza'] ?? '—') ?></td>
          <td class="fecha"><?= htmlspecialchars($v['fecha_vencimiento']) ?></td>
          <td><span class="badge <?= $cls ?>"><?= $dias ?> días</span></td>
          <td><span class="badge <?= $v['estado'] ?>"><?= $v['estado'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-msg">No hay pólizas por vencer en los próximos 90 días.</div>
    <?php endif; ?>
  </div>

  <!-- Lista de clientes -->
  <div class="section-title">Todos los clientes</div>
  <div class="search-bar">
    <input type="text" class="search-input" id="clientSearch" placeholder="Buscar por nombre, cédula, email o teléfono…">
    <span class="result-count" id="clientCount"><?= count($clients) ?> clientes</span>
    <button class="btn-add" id="btnNewClient">+ Nuevo cliente</button>
  </div>

  <div class="table-wrap">
    <?php if (empty($clients)): ?>
      <div class="empty-msg">No hay clientes. Importa un CSV o crea el primero.</div>
    <?php else: ?>
    <table>
      <thead><tr>
        <th>Nombre</th><th>Cédula</th><th>Teléfono</th>
        <th>Pólizas activas</th><th>Próximo vencimiento</th>
      </tr></thead>
      <tbody id="clientsBody">
      <?php foreach ($clients as $c):
        $dias_prox = $c['proximo_vencimiento'] ? dias_para_vencer($c['proximo_vencimiento']) : null;
        $alerta = $dias_prox !== null && $dias_prox <= 30;
      ?>
      <tr class="client-row"
        data-id="<?= $c['id'] ?>"
        data-search="<?= htmlspecialchars(strtolower($c['nombre'].' '.($c['cedula']??'').' '.($c['email']??'').' '.($c['telefono']??''))) ?>"
      >
        <td style="font-weight:600">
          <?php if ($alerta): ?><span style="color:var(--red);margin-right:.35rem">●</span><?php endif; ?>
          <?= htmlspecialchars($c['nombre']) ?>
        </td>
        <td style="font-family:monospace;font-size:.8rem"><?= htmlspecialchars($c['cedula'] ?? '—') ?></td>
        <td style="font-size:.82rem"><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
        <td><?= (int)$c['polizas_activas'] ?> / <?= (int)$c['num_polizas'] ?></td>
        <td class="fecha">
          <?php if ($c['proximo_vencimiento']): ?>
            <span class="badge <?= $dias_prox <= 30 ? 'dias-30' : ($dias_prox <= 60 ? 'dias-60' : 'activa') ?>">
              <?= $c['proximo_vencimiento'] ?>
            </span>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="empty-msg" id="noClients" style="display:none">Sin resultados.</div>
    <?php endif; ?>
  </div>

</div><!-- /.main -->

<!-- Drawer cliente -->
<div class="drawer-overlay" id="drawerOverlay"></div>
<div class="drawer" id="drawer">
  <div class="drawer-header">
    <div style="flex:1;min-width:0">
      <h2 id="drawerTitle">Cliente</h2>
      <div class="sub" id="drawerSub"></div>
    </div>
    <div style="display:flex;align-items:center;gap:.5rem;flex-shrink:0">
      <button class="drawer-del-btn hidden" id="drawerDelBtn" title="Eliminar cliente">🗑</button>
      <button class="drawer-close" id="drawerClose">✕</button>
    </div>
  </div>
  <div class="drawer-body" id="drawerBody">
    <!-- Se llena dinámicamente -->
  </div>
</div>

<!-- Modal importar CSV -->
<div class="modal-overlay" id="importModal">
  <div class="modal">
    <h3>Importar clientes desde CSV / Excel</h3>
    <p>Exporta tu archivo de Excel como <strong>CSV</strong> (separado por punto y coma o coma). El sistema detecta automáticamente estas columnas:</p>
    <div class="hint">
      nombre · email · telefono · cedula · direccion · notas<br>
      tipo · numero_poliza · aseguradora<br>
      fecha_inicio · fecha_vencimiento · prima_anual
    </div>
    <input type="file" accept=".csv,.txt" class="file-input" id="csvFile">
    <div class="modal-actions">
      <button class="btn-secondary" id="btnCancelImport">Cancelar</button>
      <button class="btn-primary" id="btnDoImport">Importar</button>
    </div>
    <div class="import-result" id="importResult"></div>
  </div>
</div>

<script>
(function(){
/* ── Clientes: buscar ── */
const searchEl  = document.getElementById('clientSearch');
const rows      = Array.from(document.querySelectorAll('.client-row'));
const countEl   = document.getElementById('clientCount');
const noClients = document.getElementById('noClients');

if (searchEl) {
  searchEl.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    let n = 0;
    rows.forEach(function(r) {
      const show = !q || r.dataset.search.includes(q);
      r.classList.toggle('hidden-row', !show);
      if (show) n++;
    });
    if (countEl) countEl.textContent = n + ' clientes';
    if (noClients) noClients.style.display = n === 0 ? 'block' : 'none';
  });
}

/* ── Drawer ── */
const overlay    = document.getElementById('drawerOverlay');
const drawer     = document.getElementById('drawer');
const drawerBody = document.getElementById('drawerBody');
const drawerTitle = document.getElementById('drawerTitle');
const drawerSub  = document.getElementById('drawerSub');
const drawerClose = document.getElementById('drawerClose');

function openDrawer() {
  overlay.classList.add('open');
  drawer.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDrawer() {
  overlay.classList.remove('open');
  drawer.classList.remove('open');
  document.body.style.overflow = '';
}
overlay.addEventListener('click', closeDrawer);
drawerClose.addEventListener('click', closeDrawer);
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeDrawer(); });

/* ── Render form cliente ── */
function clientForm(client) {
  const c = client || {};
  return `
  <form id="clientForm">
    <input type="hidden" name="id" value="${c.id||0}">
    <div class="form-grid">
      <div class="field full"><label>Nombre completo *</label><input type="text" name="nombre" value="${esc(c.nombre||'')}" required></div>
      <div class="field"><label>Cédula / RNC</label><input type="text" name="cedula" value="${esc(c.cedula||'')}"></div>
      <div class="field"><label>Teléfono</label><input type="tel" name="telefono" value="${esc(c.telefono||'')}"></div>
      <div class="field full"><label>Email</label><input type="email" name="email" value="${esc(c.email||'')}"></div>
      <div class="field full"><label>Dirección</label><input type="text" name="direccion" value="${esc(c.direccion||'')}"></div>
      <div class="field full"><label>Notas internas</label><textarea name="notas">${esc(c.notas||'')}</textarea></div>
    </div>
  </form>`;
}

/* ── Render form póliza ── */
function policyForm(policy, clientId) {
  const p = policy || {};
  const tipos = ['vida','salud','salud-persona','viajes','vehiculos','accidentes-personales','internacionales','riesgos-generales','mascotas','otro'];
  const tipoOpts = tipos.map(t => `<option value="${t}"${p.tipo===t?' selected':''}>${t}</option>`).join('');
  const freqOpts = ['mensual','trimestral','semestral','anual'].map(f => `<option value="${f}"${(p.frecuencia_pago||'anual')===f?' selected':''}>${f}</option>`).join('');
  const estOpts  = ['activa','en-renovacion','vencida','cancelada'].map(s => `<option value="${s}"${(p.estado||'activa')===s?' selected':''}>${s}</option>`).join('');
  return `
  <form id="policyForm">
    <input type="hidden" name="id" value="${p.id||0}">
    <input type="hidden" name="client_id" value="${clientId}">
    <div class="form-grid">
      <div class="field full"><label>Tipo de seguro</label><select name="tipo">${tipoOpts}</select></div>
      <div class="field"><label>No. de póliza</label><input type="text" name="numero_poliza" value="${esc(p.numero_poliza||'')}"></div>
      <div class="field"><label>Aseguradora</label><input type="text" name="aseguradora" value="${esc(p.aseguradora||'')}"></div>
      <div class="field"><label>Fecha inicio</label><input type="date" name="fecha_inicio" value="${esc(p.fecha_inicio||'')}"></div>
      <div class="field"><label>Fecha vencimiento</label><input type="date" name="fecha_vencimiento" value="${esc(p.fecha_vencimiento||'')}"></div>
      <div class="field"><label>Prima anual (RD$)</label><input type="number" step="0.01" name="prima_anual" value="${esc(p.prima_anual||'')}"></div>
      <div class="field"><label>Frecuencia de pago</label><select name="frecuencia_pago">${freqOpts}</select></div>
      <div class="field full"><label>Estado</label><select name="estado">${estOpts}</select></div>
      <div class="field full"><label>Notas</label><textarea name="notas">${esc(p.notas||'')}</textarea></div>
    </div>
  </form>`;
}

function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

function formData(form) {
  const fd = new FormData(form);
  return fd;
}

function postAPI(action, fd) {
  return fetch('api.php?action='+action, { method:'POST', body:fd }).then(r=>r.json());
}

/* ── Abrir cliente existente ── */
function openClient(clientId) {
  document.getElementById('drawerDelBtn').classList.add('hidden');
  drawerBody.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--silver-500)">Cargando…</div>';
  drawerBody.scrollTop = 0;
  drawerTitle.textContent = 'Cargando…';
  drawerSub.textContent = '';
  openDrawer();

  fetch('client-data.php?id=' + clientId)
    .then(r=>r.json())
    .then(function(data) {
      if (!data.client) { drawerBody.innerHTML = '<div class="empty-msg">Error cargando cliente.</div>'; return; }
      renderClientDetail(data);
    });
}

function renderClientDetail(data) {
  const c = data.client;
  const policies     = data.policies     || [];
  const applications = data.applications || [];
  drawerTitle.textContent = c.nombre;
  drawerSub.textContent   = (c.cedula ? 'Cédula: ' + c.cedula + '  ·  ' : '') + (c.telefono || '');

  // Activar botón eliminar en el header
  const delBtn = document.getElementById('drawerDelBtn');
  delBtn.classList.remove('hidden');
  delBtn.onclick = function() { deleteClient(c.id, c.nombre); };

  const polHtml = policies.map(function(p) {
    const dias = p.fecha_vencimiento ? Math.ceil((new Date(p.fecha_vencimiento) - new Date()) / 86400000) : null;
    const diasStr = dias !== null ? (dias < 0 ? '<span style="color:var(--red)">Venció hace '+Math.abs(dias)+' días</span>' : '<span>Vence en '+dias+' días</span>') : '';
    return `<div class="policy-card ${p.estado}" onclick="openPolicyForm(${JSON.stringify(p).replace(/"/g,'&quot;')}, ${c.id})">
      <div class="p-tipo">${esc(p.tipo||'Sin tipo')} ${p.numero_poliza ? '· <span style="font-family:monospace;font-size:.75rem">'+esc(p.numero_poliza)+'</span>' : ''}</div>
      <div class="p-meta">
        ${p.aseguradora ? '<span>'+esc(p.aseguradora)+'</span>' : ''}
        ${p.fecha_vencimiento ? '<span class="p-vence">Vence: '+esc(p.fecha_vencimiento)+'</span>' : ''}
        ${diasStr}
        <span class="badge ${p.estado}">${p.estado}</span>
      </div>
    </div>`;
  }).join('');

  drawerBody.innerHTML = `
    <div>
      <div class="d-section-label" style="font-size:.72rem;color:var(--silver-500);text-transform:uppercase;letter-spacing:.05em;font-weight:600;margin-bottom:.75rem">Datos del cliente</div>
      <div style="display:flex;flex-direction:column;gap:.5rem;font-size:.875rem">
        ${c.email ? `<div><strong>Email:</strong> <a href="mailto:${esc(c.email)}" style="color:var(--navy-700)">${esc(c.email)}</a></div>` : ''}
        ${c.telefono ? `<div><strong>Teléfono:</strong> <a href="https://wa.me/1${c.telefono.replace(/\D/g,'')}?text=Hola+${encodeURIComponent(c.nombre)},+somos+Sanalia+%26+Asociados" target="_blank" style="color:#25d366">${esc(c.telefono)} (WhatsApp)</a></div>` : ''}
        ${c.direccion ? `<div><strong>Dirección:</strong> ${esc(c.direccion)}</div>` : ''}
        ${c.notas ? `<div><strong>Notas:</strong> ${esc(c.notas)}</div>` : ''}
      </div>
    </div>
    <hr style="border:none;border-top:1px solid var(--silver-300)">
    <div>
      <div style="font-size:.72rem;color:var(--silver-500);text-transform:uppercase;letter-spacing:.05em;font-weight:600;margin-bottom:.75rem">Pólizas (${policies.length})</div>
      <div class="policies-list" id="policiesList">${polHtml || '<div style="font-size:.875rem;color:var(--silver-500)">Sin pólizas registradas.</div>'}</div>
      <button class="btn-add-policy" style="margin-top:.625rem" onclick="openPolicyForm(null, ${c.id})">+ Nueva póliza</button>
    </div>
    <hr style="border:none;border-top:1px solid var(--silver-300)">
    <div>
      <div style="font-size:.72rem;color:var(--silver-500);text-transform:uppercase;letter-spacing:.05em;font-weight:600;margin-bottom:.75rem">Solicitudes (${applications.length})</div>
      ${applications.length ? applications.map(function(a) {
        const estadoColors = {borrador:'#6b7280',['en-revision']:'#d97706',aprobada:'#1a7f4b',rechazada:'#dc2626'};
        const col = estadoColors[a.estado] || '#6b7280';
        return `<div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem .875rem;background:var(--silver-100);border-radius:8px;margin-bottom:.4rem;font-size:.82rem">
          <span style="font-weight:600">${esc(a.tipo)}</span>
          <span style="color:${col};font-weight:700;font-size:.75rem;text-transform:uppercase">${a.estado}</span>
          <a href="application.php?client=${c.id}&app=${a.id}" style="font-size:.75rem;color:var(--navy-700);text-decoration:none;font-weight:600">Ver →</a>
        </div>`;
      }).join('') : '<div style="font-size:.875rem;color:var(--silver-500)">Sin solicitudes.</div>'}
      <a href="application.php?client=${c.id}" class="btn-add-policy" style="display:block;margin-top:.625rem;text-decoration:none;text-align:center">+ Nueva solicitud</a>
    </div>
    ${(data.documents||[]).length ? `<hr style="border:none;border-top:1px solid var(--silver-300)">
    <div>
      <div style="font-size:.72rem;color:var(--silver-500);text-transform:uppercase;letter-spacing:.05em;font-weight:600;margin-bottom:.75rem">Documentos (${data.documents.length})</div>
      ${data.documents.map(function(d){
        const kb = d.file_size ? (d.file_size/1024).toFixed(0)+' KB' : '';
        return `<div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem .875rem;background:var(--silver-100);border-radius:8px;margin-bottom:.4rem;font-size:.82rem">
          <span>${esc(d.nombre||d.original_name)}</span>
          <span style="color:var(--silver-500);font-size:.72rem">${kb}</span>
          <a href="download.php?id=${d.id}" target="_blank" style="font-size:.75rem;color:var(--navy-700);text-decoration:none;font-weight:600">Ver</a>
        </div>`;
      }).join('')}
    </div>` : ''}
    <hr style="border:none;border-top:1px solid var(--silver-300)">
    <button class="btn-secondary" style="width:100%" onclick="openEditClient(${JSON.stringify(c).replace(/"/g,'&quot;')})">✏ Editar datos del cliente</button>`;
}

/* ── Nuevo cliente ── */
document.getElementById('btnNewClient').addEventListener('click', function() {
  document.getElementById('drawerDelBtn').classList.add('hidden');
  drawerTitle.textContent = 'Nuevo cliente';
  drawerSub.textContent   = '';
  drawerBody.innerHTML    = clientForm(null) + `
    <div style="display:flex;gap:.75rem">
      <button class="btn-primary" id="btnSaveNewClient">Guardar cliente</button>
    </div>`;
  drawerBody.scrollTop = 0;
  document.getElementById('btnSaveNewClient').addEventListener('click', function() {
    const form = document.getElementById('clientForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    this.textContent = 'Guardando…'; this.disabled = true;
    postAPI('client_save', formData(form)).then(function(r) {
      if (r.ok) { location.reload(); }
      else { alert('Error: ' + r.error); }
    });
  });
  openDrawer();
});

/* ── Editar cliente ── */
window.openEditClient = function(client) {
  document.getElementById('drawerDelBtn').classList.add('hidden');
  drawerTitle.textContent = 'Editar cliente';
  drawerSub.textContent   = client.nombre || '';
  drawerBody.innerHTML    = clientForm(client) + `
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <button class="btn-primary" style="flex:1" id="btnSaveEdit">Guardar cambios</button>
      <button class="btn-secondary" onclick="openClient(${client.id})">← Cancelar</button>
      <button class="btn-danger" onclick="deleteClient(${client.id}, '${client.nombre.replace(/'/g,"\\'") }')">🗑 Eliminar cliente</button>
    </div>`;
  drawerBody.scrollTop = 0;
  document.getElementById('btnSaveEdit').addEventListener('click', function() {
    const form = document.getElementById('clientForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    this.textContent = 'Guardando…'; this.disabled = true;
    postAPI('client_save', formData(form)).then(function(r) {
      if (r.ok) { openClient(client.id); }
      else { alert('Error: ' + r.error); }
    });
  });
};

/* ── Póliza form ── */
window.openPolicyForm = function(policy, clientId) {
  const isNew = !policy || !policy.id;
  document.getElementById('drawerDelBtn').classList.add('hidden');
  drawerTitle.textContent = isNew ? 'Nueva póliza' : 'Editar póliza';
  drawerSub.textContent   = isNew ? 'Completa los datos y guarda' : (policy.tipo || '');
  drawerBody.innerHTML    =
    `<button onclick="openClient(${clientId})" style="display:inline-flex;align-items:center;gap:.4rem;background:none;border:none;color:var(--navy-700);font-size:.82rem;font-weight:600;cursor:pointer;padding:0;margin-bottom:.5rem">← Volver al cliente</button>` +
    policyForm(policy, clientId) + `
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <button class="btn-primary" style="flex:1" id="btnSavePolicy">💾 Guardar póliza</button>
      <button class="btn-secondary" onclick="openClient(${clientId})">Cancelar</button>
      ${!isNew ? `<button class="btn-danger" onclick="deletePolicy(${policy.id}, ${clientId})">🗑 Eliminar</button>` : ''}
    </div>`;
  drawerBody.scrollTop = 0;
  document.getElementById('btnSavePolicy').addEventListener('click', function() {
    this.textContent = 'Guardando…'; this.disabled = true;
    postAPI('policy_save', formData(document.getElementById('policyForm'))).then(function(r) {
      if (r.ok) { openClient(clientId); }
      else { alert('Error: ' + r.error); this.textContent = '💾 Guardar póliza'; this.disabled = false; }
    });
  });
};

/* ── Eliminar ── */
window.deleteClient = function(id, nombre) {
  if (!confirm('¿Eliminar a ' + nombre + ' y todas sus pólizas?')) return;
  const fd = new FormData(); fd.append('id', id);
  postAPI('client_delete', fd).then(function(r) { if (r.ok) location.reload(); });
};
window.deletePolicy = function(id, clientId) {
  if (!confirm('¿Eliminar esta póliza?')) return;
  const fd = new FormData(); fd.append('id', id);
  postAPI('policy_delete', fd).then(function(r) { if (r.ok) openClient(clientId); });
};

/* ── Clic en fila de cliente ── */
document.querySelectorAll('.client-row').forEach(function(r) {
  r.addEventListener('click', function() { openClient(parseInt(this.dataset.id)); });
});

/* ── Clic en fila de vencimiento ── */
document.querySelectorAll('.vence-row').forEach(function(r) {
  r.addEventListener('click', function() { openClient(parseInt(this.dataset.clientId)); });
});

/* ── Importar CSV ── */
const importModal   = document.getElementById('importModal');
const btnImport     = document.getElementById('btnImport');
const btnCancelImp  = document.getElementById('btnCancelImport');
const btnDoImport   = document.getElementById('btnDoImport');
const csvFile       = document.getElementById('csvFile');
const importResult  = document.getElementById('importResult');

btnImport.addEventListener('click', function() { importModal.classList.add('open'); });
btnCancelImp.addEventListener('click', function() { importModal.classList.remove('open'); });

btnDoImport.addEventListener('click', function() {
  if (!csvFile.files[0]) { alert('Selecciona un archivo CSV.'); return; }
  this.textContent = 'Importando…'; this.disabled = true;
  const fd = new FormData(); fd.append('csv', csvFile.files[0]);
  fetch('api.php?action=csv_import', { method:'POST', body:fd })
    .then(r=>r.json())
    .then(function(r) {
      importResult.style.display = 'block';
      if (r.ok) {
        importResult.className = 'import-result ok';
        importResult.textContent = '✓ ' + r.imported + ' clientes importados.' + (r.errors ? ' (' + r.errors + ' filas con error)' : '');
        setTimeout(function() { location.reload(); }, 2000);
      } else {
        importResult.className = 'import-result err';
        importResult.textContent = 'Error: ' + r.error;
      }
      btnDoImport.textContent = 'Importar'; btnDoImport.disabled = false;
    });
});

})();
</script>
</body>
</html>
