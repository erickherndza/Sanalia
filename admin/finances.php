<?php
/**
 * Sanalia CRM — Cuentas por Cobrar y Cuentas por Pagar
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db.php';

$db    = get_db();
$today = date('Y-m-d');
$in30  = date('Y-m-d', strtotime('+30 days'));
$mes   = date('Y-m-01');

/* ── Resumen CxC ── */
$cxc_total = $cxc_vencido = $cxc_cobrado = 0.0;
$cxc_rows = [];
try {
    $cxc_total   = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM receivables WHERE estado='pendiente'")->fetchColumn();
    $cxc_vencido = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM receivables WHERE estado='pendiente' AND fecha_vencimiento<'$today'")->fetchColumn();
    $cxc_cobrado = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM receivables WHERE estado='pagado'")->fetchColumn();
    $cxc_rows    = $db->query(
        "SELECT r.*, c.nombre AS cliente_nombre, c.telefono AS cliente_tel, c.email AS cliente_email
         FROM receivables r JOIN clients c ON c.id=r.client_id
         ORDER BY r.fecha_vencimiento ASC"
    )->fetchAll();
} catch (Exception $e) { /* tabla aún no existe — corre el schema.sql */ }

/* ── Resumen CxP ── */
$cxp_total = $cxp_vencido = $cxp_pagado = 0.0;
$cxp_rows = [];
try {
    $cxp_total  = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM payables WHERE estado='pendiente'")->fetchColumn();
    $cxp_vencido = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM payables WHERE estado='pendiente' AND fecha_vencimiento<'$today'")->fetchColumn();
    $cxp_pagado  = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM payables WHERE estado='pagado'")->fetchColumn();
    $cxp_rows    = $db->query("SELECT * FROM payables ORDER BY fecha_vencimiento ASC")->fetchAll();
} catch (Exception $e) { /* tabla aún no existe */ }

/* ── Clientes para select ── */
$clientes = [];
$polizas  = [];
try {
    $clientes = $db->query("SELECT id,nombre FROM clients ORDER BY nombre ASC")->fetchAll();
    $polizas  = $db->query("SELECT id,tipo,numero_poliza,client_id FROM policies WHERE estado='activa' ORDER BY tipo ASC")->fetchAll();
} catch (Exception $e) { }

function fmt(float $n): string {
    return 'RD$ '.number_format($n, 2, '.', ',');
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Finanzas — Sanalia CRM</title>
<meta name="robots" content="noindex, nofollow">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --navy-950:#071523; --navy-900:#0C2036; --navy-700:#1E4468;
  --gold-500:#C6A15B; --gold-600:#A9843F;
  --silver-100:#F3F5F7; --silver-300:#DCE1E7; --silver-500:#AEB8C4;
  --ink:#0E1620;
  --green:#1a7f4b; --green-bg:#e8f5ee;
  --orange:#d97706; --orange-bg:#fef3c7;
  --red:#dc2626; --red-bg:#fee2e2;
  --blue:#1d4ed8; --blue-bg:#dbeafe;
}
body { font-family:'Inter',system-ui,sans-serif; background:var(--silver-100); color:var(--ink); min-height:100vh; }

/* topbar */
.topbar { background:var(--navy-900); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:.875rem 2rem; position:sticky; top:0; z-index:100; gap:1rem; flex-wrap:wrap; }
.topbar .brand { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1rem; }
.topbar .brand span { color:var(--gold-500); }
.topbar-nav { display:flex; gap:.25rem; }
.nav-tab { padding:.4rem .875rem; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; color:rgba(255,255,255,.6); border:none; background:none; cursor:pointer; }
.nav-tab:hover { color:#fff; background:rgba(255,255,255,.08); }
.nav-tab.active { color:#fff; background:rgba(255,255,255,.15); }
.btn-gold { background:var(--gold-500); color:var(--navy-950); border:none; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; }
.btn-gold:hover { background:var(--gold-600); }
.btn-outline { background:transparent; border:1.5px solid rgba(255,255,255,.3); color:#fff; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; cursor:pointer; }

.main { max-width:1320px; margin:0 auto; padding:2rem 1.5rem; }

/* summaries */
.summary-row { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:2rem; }
.sum-card { background:#fff; border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 1px 6px rgba(7,21,35,.07); border-top:3px solid var(--silver-300); }
.sum-card.cobrar   { border-color:var(--gold-500); }
.sum-card.vencidoc { border-color:var(--red); }
.sum-card.cobrado  { border-color:var(--green); }
.sum-card.pagar    { border-color:var(--orange); }
.sum-card.vencidop { border-color:var(--red); }
.sum-card.pagado   { border-color:var(--green); }
.sum-card .num { font-family:'IBM Plex Mono',monospace; font-size:1.3rem; font-weight:700; color:var(--navy-900); }
.sum-card.vencidoc .num { color:var(--red); }
.sum-card.vencidop .num { color:var(--red); }
.sum-card .lbl { font-size:.7rem; color:var(--silver-500); text-transform:uppercase; letter-spacing:.05em; margin-top:.3rem; }

/* tabs */
.tabs { display:flex; gap:.5rem; margin-bottom:1.25rem; }
.tab-btn { padding:.5rem 1.25rem; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; border:1.5px solid var(--silver-300); background:#fff; color:var(--navy-900); }
.tab-btn.active { background:var(--navy-900); color:#fff; border-color:var(--navy-900); }

.tab-panel { display:none; }
.tab-panel.active { display:block; }

/* table */
.table-wrap { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(7,21,35,.07); overflow:hidden; margin-bottom:1.25rem; }
table { width:100%; border-collapse:collapse; font-size:.82rem; }
thead { background:var(--navy-950); color:#fff; }
thead th { padding:.65rem 1rem; text-align:left; font-family:'IBM Plex Mono',monospace; font-size:.67rem; letter-spacing:.07em; text-transform:uppercase; font-weight:500; white-space:nowrap; }
tbody tr { border-bottom:1px solid var(--silver-100); transition:background .15s; }
tbody tr:hover { background:#f8fafc; cursor:pointer; }
tbody tr:last-child { border-bottom:none; }
td { padding:.65rem 1rem; vertical-align:middle; }
.mono { font-family:'IBM Plex Mono',monospace; font-size:.78rem; }
.empty-msg { text-align:center; padding:3rem; color:var(--silver-500); font-size:.875rem; }

/* badge */
.badge { display:inline-block; padding:.2rem .6rem; border-radius:999px; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
.badge.pendiente { background:var(--orange-bg); color:var(--orange); }
.badge.pagado    { background:var(--green-bg);  color:var(--green); }
.badge.vencido   { background:var(--red-bg);    color:var(--red); }
.badge.anulado   { background:#f3f4f6;           color:#6b7280; }

/* toolbar */
.toolbar { display:flex; gap:.75rem; align-items:center; margin-bottom:1rem; flex-wrap:wrap; }
.filter-select { padding:.5rem .875rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.82rem; background:#fff; outline:none; }
.filter-select:focus { border-color:var(--navy-700); }
.btn-add { background:var(--navy-900); color:#fff; border:none; padding:.5rem 1.1rem; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; }
.btn-add:hover { background:var(--navy-700); }
.spacer { flex:1; }

/* modal / drawer */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(7,21,35,.55); z-index:300; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal { background:#fff; border-radius:14px; padding:2rem; width:100%; max-width:520px; box-shadow:0 8px 40px rgba(7,21,35,.2); max-height:90vh; overflow-y:auto; }
.modal h3 { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.05rem; margin-bottom:1.25rem; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:.875rem; }
.field { display:flex; flex-direction:column; gap:.35rem; }
.field.full { grid-column:1/-1; }
label { font-size:.72rem; font-weight:600; color:var(--navy-900); text-transform:uppercase; letter-spacing:.04em; }
input,select,textarea { padding:.6rem .875rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.875rem; font-family:inherit; outline:none; background:#fff; }
input:focus,select:focus,textarea:focus { border-color:var(--navy-700); }
textarea { resize:vertical; min-height:60px; }
.modal-footer { display:flex; gap:.75rem; margin-top:1.5rem; }
.btn-primary { flex:1; padding:.7rem; background:var(--navy-900); color:#fff; border:none; border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; }
.btn-primary:hover { background:var(--navy-700); }
.btn-secondary { padding:.7rem 1rem; background:#fff; color:var(--navy-900); border:1.5px solid var(--silver-300); border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; }
.btn-danger { padding:.7rem 1rem; background:#fff; color:var(--red); border:1.5px solid var(--red); border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; }
.btn-success { padding:.4rem .875rem; background:var(--green); color:#fff; border:none; border-radius:6px; font-size:.75rem; font-weight:700; cursor:pointer; white-space:nowrap; }
.btn-link { background:none; border:none; color:var(--navy-700); font-size:.75rem; font-weight:600; cursor:pointer; text-decoration:underline; }

@media (max-width:768px) {
  .summary-row { grid-template-columns:1fr 1fr; }
  .topbar { padding:.75rem 1rem; }
  .main { padding:1.25rem 1rem; }
  .form-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">Sanalia &amp; Asociados &mdash; <span>CRM</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="nav-tab">Dashboard</a>
    <a href="index.php"     class="nav-tab">Solicitudes</a>
    <a href="clients.php"   class="nav-tab">Clientes</a>
    <a href="finances.php"  class="nav-tab active">Finanzas</a>
    <a href="calendar.php"  class="nav-tab">Calendario</a>
    <a href="import.php"    class="nav-tab">Importar</a>
  </div>
  <div class="topbar-actions">
    <form method="POST" action="index.php" style="margin:0">
      <button type="submit" name="logout" class="btn-outline">Salir</button>
    </form>
  </div>
</div>

<div class="main">

<!-- ── Resumen financiero ── -->
<div style="font-family:'Manrope',system-ui,sans-serif;font-weight:800;font-size:1.2rem;margin-bottom:1.25rem;color:var(--navy-900)">Cuentas por cobrar &amp; pagar</div>

<div style="font-weight:700;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--silver-500);margin-bottom:.625rem">Por cobrar</div>
<div class="summary-row" style="margin-bottom:1.5rem">
  <div class="sum-card cobrar"><div class="num"><?= fmt($cxc_total) ?></div><div class="lbl">Pendiente de cobro</div></div>
  <div class="sum-card vencidoc"><div class="num"><?= fmt($cxc_vencido) ?></div><div class="lbl">Cobros vencidos</div></div>
  <div class="sum-card cobrado"><div class="num"><?= fmt($cxc_cobrado) ?></div><div class="lbl">Total cobrado histórico</div></div>
</div>

<div style="font-weight:700;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--silver-500);margin-bottom:.625rem">Por pagar</div>
<div class="summary-row">
  <div class="sum-card pagar"><div class="num"><?= fmt($cxp_total) ?></div><div class="lbl">Pendiente de pago</div></div>
  <div class="sum-card vencidop"><div class="num"><?= fmt($cxp_vencido) ?></div><div class="lbl">Pagos vencidos</div></div>
  <div class="sum-card pagado"><div class="num"><?= fmt($cxp_pagado) ?></div><div class="lbl">Total pagado histórico</div></div>
</div>

<!-- ── Tabs ── -->
<div class="tabs">
  <button class="tab-btn active" data-tab="cxc">Por cobrar (<?= count($cxc_rows) ?>)</button>
  <button class="tab-btn" data-tab="cxp">Por pagar (<?= count($cxp_rows) ?>)</button>
</div>

<!-- ══ CxC ══ -->
<div class="tab-panel active" id="tab-cxc">
  <div class="toolbar">
    <select class="filter-select" id="cxcFilter">
      <option value="">Todos los estados</option>
      <option value="pendiente">Pendiente</option>
      <option value="vencido">Vencido</option>
      <option value="pagado">Pagado</option>
      <option value="anulado">Anulado</option>
    </select>
    <div class="spacer"></div>
    <button class="btn-add" id="btnNewCxc">+ Nuevo cobro</button>
  </div>

  <div class="table-wrap">
    <?php if (empty($cxc_rows)): ?>
      <div class="empty-msg">Sin cuentas por cobrar registradas. Usa "+ Nuevo cobro" para agregar.</div>
    <?php else: ?>
    <table id="cxcTable">
      <thead><tr>
        <th>Cliente</th><th>Concepto</th><th>Emisión</th><th>Vencimiento</th><th>Monto</th><th>Estado</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($cxc_rows as $r):
        $dias = (int)ceil((strtotime($r['fecha_vencimiento']) - time()) / 86400);
        $estado_real = ($r['estado'] === 'pendiente' && $dias < 0) ? 'vencido' : $r['estado'];
      ?>
      <tr class="cxc-row" data-estado="<?= $estado_real ?>" data-id="<?= $r['id'] ?>">
        <td style="font-weight:600"><?= htmlspecialchars($r['cliente_nombre']) ?></td>
        <td style="font-size:.8rem"><?= htmlspecialchars($r['concepto']) ?></td>
        <td class="mono"><?= $r['fecha_emision'] ?></td>
        <td class="mono <?= $dias < 0 && $r['estado']==='pendiente' ? 'style="color:var(--red)"' : '' ?>"><?= $r['fecha_vencimiento'] ?></td>
        <td class="mono" style="font-weight:600"><?= fmt((float)$r['monto']) ?></td>
        <td><span class="badge <?= $estado_real ?>"><?= $estado_real ?></span></td>
        <td style="white-space:nowrap">
          <?php if ($r['estado'] === 'pendiente'): ?>
          <button class="btn-success btn-mark-paid" data-type="cxc" data-id="<?= $r['id'] ?>">✓ Marcar pagado</button>
          <?php endif; ?>
          <button class="btn-link btn-edit-cxc" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>'>✏</button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- ══ CxP ══ -->
<div class="tab-panel" id="tab-cxp">
  <div class="toolbar">
    <select class="filter-select" id="cxpFilter">
      <option value="">Todos los estados</option>
      <option value="pendiente">Pendiente</option>
      <option value="vencido">Vencido</option>
      <option value="pagado">Pagado</option>
      <option value="anulado">Anulado</option>
    </select>
    <select class="filter-select" id="cxpCatFilter">
      <option value="">Todas las categorías</option>
      <option value="comision">Comisión</option>
      <option value="prima">Prima</option>
      <option value="proveedor">Proveedor</option>
      <option value="otro">Otro</option>
    </select>
    <div class="spacer"></div>
    <button class="btn-add" id="btnNewCxp">+ Nuevo pago</button>
  </div>

  <div class="table-wrap">
    <?php if (empty($cxp_rows)): ?>
      <div class="empty-msg">Sin cuentas por pagar registradas. Usa "+ Nuevo pago" para agregar.</div>
    <?php else: ?>
    <table id="cxpTable">
      <thead><tr>
        <th>Beneficiario</th><th>Concepto</th><th>Categoría</th><th>Vencimiento</th><th>Monto</th><th>Estado</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($cxp_rows as $p):
        $dias = (int)ceil((strtotime($p['fecha_vencimiento']) - time()) / 86400);
        $estado_real = ($p['estado'] === 'pendiente' && $dias < 0) ? 'vencido' : $p['estado'];
      ?>
      <tr class="cxp-row" data-estado="<?= $estado_real ?>" data-cat="<?= $p['categoria'] ?>" data-id="<?= $p['id'] ?>">
        <td style="font-weight:600"><?= htmlspecialchars($p['beneficiario']) ?></td>
        <td style="font-size:.8rem"><?= htmlspecialchars($p['concepto']) ?></td>
        <td><span style="font-size:.75rem;color:var(--silver-500)"><?= $p['categoria'] ?></span></td>
        <td class="mono"><?= $p['fecha_vencimiento'] ?></td>
        <td class="mono" style="font-weight:600"><?= fmt((float)$p['monto']) ?></td>
        <td><span class="badge <?= $estado_real ?>"><?= $estado_real ?></span></td>
        <td style="white-space:nowrap">
          <?php if ($p['estado'] === 'pendiente'): ?>
          <button class="btn-success btn-mark-paid" data-type="cxp" data-id="<?= $p['id'] ?>">✓ Marcar pagado</button>
          <?php endif; ?>
          <button class="btn-link btn-edit-cxp" data-row='<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>'>✏</button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

</div><!-- /.main -->

<!-- ══ Modal CxC ══ -->
<div class="modal-overlay" id="modalCxc">
  <div class="modal">
    <h3 id="cxcModalTitle">Nuevo cobro</h3>
    <form id="formCxc">
      <input type="hidden" name="id" id="cxcId" value="0">
      <div class="form-grid">
        <div class="field full">
          <label>Cliente *</label>
          <select name="client_id" id="cxcClientId" required>
            <option value="">— Seleccionar —</option>
            <?php foreach ($clientes as $cl): ?>
            <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field full">
          <label>Póliza (opcional)</label>
          <select name="policy_id" id="cxcPolicyId">
            <option value="">— Sin póliza específica —</option>
            <?php foreach ($polizas as $pol): ?>
            <option value="<?= $pol['id'] ?>" data-client="<?= $pol['client_id'] ?>">
              <?= htmlspecialchars(($pol['tipo']??'').' '.($pol['numero_poliza']??'')) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field full">
          <label>Concepto *</label>
          <input type="text" name="concepto" placeholder="Ej: Prima anual Seguro de Vida" required>
        </div>
        <div class="field">
          <label>Monto (RD$) *</label>
          <input type="number" name="monto" step="0.01" min="0.01" required>
        </div>
        <div class="field">
          <label>Fecha de emisión *</label>
          <input type="date" name="fecha_emision" value="<?= $today ?>" required>
        </div>
        <div class="field">
          <label>Fecha de vencimiento *</label>
          <input type="date" name="fecha_vencimiento" required>
        </div>
        <div class="field">
          <label>Estado</label>
          <select name="estado">
            <option value="pendiente">Pendiente</option>
            <option value="pagado">Pagado</option>
            <option value="anulado">Anulado</option>
          </select>
        </div>
        <div class="field">
          <label>Fecha de pago</label>
          <input type="date" name="fecha_pago">
        </div>
        <div class="field full">
          <label>Notas</label>
          <textarea name="notas" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" id="btnCancelCxc">Cancelar</button>
        <button type="submit" class="btn-primary" id="btnSaveCxc">Guardar</button>
        <button type="button" class="btn-danger" id="btnDeleteCxc" style="display:none">Eliminar</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ Modal CxP ══ -->
<div class="modal-overlay" id="modalCxp">
  <div class="modal">
    <h3 id="cxpModalTitle">Nuevo pago</h3>
    <form id="formCxp">
      <input type="hidden" name="id" id="cxpId" value="0">
      <div class="form-grid">
        <div class="field full">
          <label>Beneficiario *</label>
          <input type="text" name="beneficiario" placeholder="Aseguradora, proveedor, etc." required>
        </div>
        <div class="field full">
          <label>Concepto *</label>
          <input type="text" name="concepto" placeholder="Ej: Prima neta Seguro de Vida" required>
        </div>
        <div class="field">
          <label>Categoría</label>
          <select name="categoria">
            <option value="comision">Comisión</option>
            <option value="prima">Prima</option>
            <option value="proveedor">Proveedor</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="field">
          <label>Monto (RD$) *</label>
          <input type="number" name="monto" step="0.01" min="0.01" required>
        </div>
        <div class="field">
          <label>Fecha de emisión *</label>
          <input type="date" name="fecha_emision" value="<?= $today ?>" required>
        </div>
        <div class="field">
          <label>Fecha de vencimiento *</label>
          <input type="date" name="fecha_vencimiento" required>
        </div>
        <div class="field">
          <label>Estado</label>
          <select name="estado">
            <option value="pendiente">Pendiente</option>
            <option value="pagado">Pagado</option>
            <option value="anulado">Anulado</option>
          </select>
        </div>
        <div class="field">
          <label>Fecha de pago</label>
          <input type="date" name="fecha_pago">
        </div>
        <div class="field full">
          <label>Notas</label>
          <textarea name="notas" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" id="btnCancelCxp">Cancelar</button>
        <button type="submit" class="btn-primary" id="btnSaveCxp">Guardar</button>
        <button type="button" class="btn-danger" id="btnDeleteCxp" style="display:none">Eliminar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){

/* ═══ Tabs ═══ */
document.querySelectorAll('.tab-btn').forEach(function(btn){
  btn.addEventListener('click', function(){
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
    document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
    this.classList.add('active');
    document.getElementById('tab-'+this.dataset.tab).classList.add('active');
  });
});

/* ═══ Filtros CxC ═══ */
document.getElementById('cxcFilter').addEventListener('change', function(){
  const v = this.value;
  document.querySelectorAll('.cxc-row').forEach(function(r){
    r.style.display = (!v || r.dataset.estado === v) ? '' : 'none';
  });
});

/* ═══ Filtros CxP ═══ */
document.getElementById('cxpFilter').addEventListener('change', filterCxp);
document.getElementById('cxpCatFilter').addEventListener('change', filterCxp);
function filterCxp(){
  const est = document.getElementById('cxpFilter').value;
  const cat = document.getElementById('cxpCatFilter').value;
  document.querySelectorAll('.cxp-row').forEach(function(r){
    const okEst = !est || r.dataset.estado === est;
    const okCat = !cat || r.dataset.cat === cat;
    r.style.display = (okEst && okCat) ? '' : 'none';
  });
}

/* ═══ Marcar pagado (quick action) ═══ */
document.querySelectorAll('.btn-mark-paid').forEach(function(btn){
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    const type = this.dataset.type;
    const id   = this.dataset.id;
    if (!confirm('¿Marcar este registro como pagado con fecha de hoy?')) return;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('estado', 'pagado');
    fd.append('fecha_pago', new Date().toISOString().substring(0,10));
    fetch('api.php?action='+(type==='cxc'?'receivable_save':'payable_save'), {method:'POST',body:fd})
      .then(function(r){ return r.json(); })
      .then(function(r){ if(r.ok) location.reload(); else alert('Error: '+r.error); });
  });
});

/* ═══ Helpers modal ═══ */
function fillForm(form, data){
  Object.keys(data).forEach(function(k){
    const el = form.elements[k];
    if (el) el.value = data[k] || '';
  });
}
function clearForm(form){
  form.reset();
  form.elements['id'].value = '0';
}
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }

/* ═══ CxC Modal ═══ */
const modalCxc = 'modalCxc';
document.getElementById('btnNewCxc').addEventListener('click', function(){
  clearForm(document.getElementById('formCxc'));
  document.getElementById('cxcModalTitle').textContent = 'Nuevo cobro';
  document.getElementById('btnDeleteCxc').style.display = 'none';
  openModal(modalCxc);
});
document.getElementById('btnCancelCxc').addEventListener('click', function(){ closeModal(modalCxc); });
document.querySelectorAll('.btn-edit-cxc').forEach(function(btn){
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    const row = JSON.parse(this.dataset.row);
    fillForm(document.getElementById('formCxc'), row);
    document.getElementById('cxcModalTitle').textContent = 'Editar cobro';
    document.getElementById('btnDeleteCxc').style.display = '';
    document.getElementById('btnDeleteCxc').dataset.id = row.id;
    openModal(modalCxc);
  });
});
document.getElementById('formCxc').addEventListener('submit', function(e){
  e.preventDefault();
  const btn = document.getElementById('btnSaveCxc');
  btn.textContent = 'Guardando…'; btn.disabled = true;
  fetch('api.php?action=receivable_save', {method:'POST', body: new FormData(this)})
    .then(function(r){ return r.json(); })
    .then(function(r){
      if (r.ok) location.reload();
      else { alert('Error: '+r.error); btn.textContent='Guardar'; btn.disabled=false; }
    });
});
document.getElementById('btnDeleteCxc').addEventListener('click', function(){
  if (!confirm('¿Eliminar este registro?')) return;
  const fd = new FormData(); fd.append('id', this.dataset.id);
  fetch('api.php?action=receivable_delete', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(r){ if (r.ok) location.reload(); });
});

/* ═══ CxP Modal ═══ */
const modalCxp = 'modalCxp';
document.getElementById('btnNewCxp').addEventListener('click', function(){
  clearForm(document.getElementById('formCxp'));
  document.getElementById('cxpModalTitle').textContent = 'Nuevo pago';
  document.getElementById('btnDeleteCxp').style.display = 'none';
  openModal(modalCxp);
});
document.getElementById('btnCancelCxp').addEventListener('click', function(){ closeModal(modalCxp); });
document.querySelectorAll('.btn-edit-cxp').forEach(function(btn){
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    const row = JSON.parse(this.dataset.row);
    fillForm(document.getElementById('formCxp'), row);
    document.getElementById('cxpModalTitle').textContent = 'Editar pago';
    document.getElementById('btnDeleteCxp').style.display = '';
    document.getElementById('btnDeleteCxp').dataset.id = row.id;
    openModal(modalCxp);
  });
});
document.getElementById('formCxp').addEventListener('submit', function(e){
  e.preventDefault();
  const btn = document.getElementById('btnSaveCxp');
  btn.textContent = 'Guardando…'; btn.disabled = true;
  fetch('api.php?action=payable_save', {method:'POST', body: new FormData(this)})
    .then(function(r){ return r.json(); })
    .then(function(r){
      if (r.ok) location.reload();
      else { alert('Error: '+r.error); btn.textContent='Guardar'; btn.disabled=false; }
    });
});
document.getElementById('btnDeleteCxp').addEventListener('click', function(){
  if (!confirm('¿Eliminar este registro?')) return;
  const fd = new FormData(); fd.append('id', this.dataset.id);
  fetch('api.php?action=payable_delete', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(r){ if (r.ok) location.reload(); });
});

/* cerrar modales con Escape */
document.addEventListener('keydown', function(e){
  if (e.key==='Escape'){
    closeModal(modalCxc);
    closeModal(modalCxp);
  }
});

})();
</script>
</body>
</html>
