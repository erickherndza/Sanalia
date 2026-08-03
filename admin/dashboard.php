<?php
/**
 * Sanalia CRM — Dashboard KPIs
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db.php';

$db    = get_db();
$today = date('Y-m-d');
$mes   = date('Y-m-01');
$in30  = date('Y-m-d', strtotime('+30 days'));
$in90  = date('Y-m-d', strtotime('+90 days'));
$mes_ant = date('Y-m-01', strtotime('-1 month'));
$fin_mes_ant = date('Y-m-t', strtotime('-1 month'));

/* ═══════════════════════════════════════════════════════
   CARTERA
═══════════════════════════════════════════════════════ */
$cartera_total  = (float)$db->query("SELECT COALESCE(SUM(prima_anual),0) FROM policies WHERE estado='activa'")->fetchColumn();
$polizas_activas = (int)$db->query("SELECT COUNT(*) FROM policies WHERE estado='activa'")->fetchColumn();
$clientes_total  = (int)$db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$polizas_vencidas = (int)$db->query("SELECT COUNT(*) FROM policies WHERE estado='activa' AND fecha_vencimiento < '$today'")->fetchColumn();
$polizas_renovacion = (int)$db->query("SELECT COUNT(*) FROM policies WHERE estado='en-renovacion'")->fetchColumn();
$polizas_vence30 = (int)$db->query("SELECT COUNT(*) FROM policies WHERE estado='activa' AND fecha_vencimiento BETWEEN '$today' AND '$in30'")->fetchColumn();

/* Nuevas pólizas este mes vs mes anterior */
$nuevas_mes  = (int)$db->query("SELECT COUNT(*) FROM policies WHERE created_at >= '$mes'")->fetchColumn();
$nuevas_ant  = (int)$db->query("SELECT COUNT(*) FROM policies WHERE created_at BETWEEN '$mes_ant' AND '$fin_mes_ant 23:59:59'")->fetchColumn();

/* ═══════════════════════════════════════════════════════
   SOLICITUDES (applications) — tabla puede no existir aún
═══════════════════════════════════════════════════════ */
$apps_mes = $apps_aprobadas = $apps_total = $apps_pendientes = 0;
$conv_rate = 0.0;
try {
    $apps_mes       = (int)$db->query("SELECT COUNT(*) FROM applications WHERE created_at >= '$mes'")->fetchColumn();
    $apps_aprobadas = (int)$db->query("SELECT COUNT(*) FROM applications WHERE estado='aprobada'")->fetchColumn();
    $apps_total     = (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    $apps_pendientes = (int)$db->query("SELECT COUNT(*) FROM applications WHERE estado IN ('borrador','en-revision')")->fetchColumn();
    $conv_rate = $apps_total > 0 ? round(($apps_aprobadas / $apps_total) * 100, 1) : 0;
} catch (Exception $e) { /* tabla aún no existe */ }

/* ═══════════════════════════════════════════════════════
   CUENTAS POR COBRAR — tabla puede no existir aún
═══════════════════════════════════════════════════════ */
$cxc_pendiente = $cxc_vencido = $cxc_cobrado_mes = $cxc_vence30 = 0.0;
try {
    $cxc_pendiente   = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM receivables WHERE estado='pendiente'")->fetchColumn();
    $cxc_vencido     = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM receivables WHERE estado='pendiente' AND fecha_vencimiento < '$today'")->fetchColumn();
    $cxc_cobrado_mes = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM receivables WHERE estado='pagado' AND fecha_pago >= '$mes'")->fetchColumn();
    $cxc_vence30     = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM receivables WHERE estado='pendiente' AND fecha_vencimiento BETWEEN '$today' AND '$in30'")->fetchColumn();
} catch (Exception $e) { /* tabla aún no existe */ }

/* ═══════════════════════════════════════════════════════
   CUENTAS POR PAGAR — tabla puede no existir aún
═══════════════════════════════════════════════════════ */
$cxp_pendiente = $cxp_vencido = $cxp_pagado_mes = 0.0;
try {
    $cxp_pendiente  = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM payables WHERE estado='pendiente'")->fetchColumn();
    $cxp_vencido    = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM payables WHERE estado='pendiente' AND fecha_vencimiento < '$today'")->fetchColumn();
    $cxp_pagado_mes = (float)$db->query("SELECT COALESCE(SUM(monto),0) FROM payables WHERE estado='pagado' AND fecha_pago >= '$mes'")->fetchColumn();
} catch (Exception $e) { /* tabla aún no existe */ }

/* ═══════════════════════════════════════════════════════
   TOP 5 CLIENTES POR VALOR DE CARTERA
═══════════════════════════════════════════════════════ */
$top_clientes = [];
try {
    $top_clientes = $db->query(
        "SELECT c.nombre, c.telefono,
            COUNT(p.id) AS num_polizas,
            COALESCE(SUM(p.prima_anual),0) AS cartera
         FROM clients c
         JOIN policies p ON p.client_id=c.id AND p.estado='activa'
         GROUP BY c.id
         ORDER BY cartera DESC
         LIMIT 5"
    )->fetchAll();
} catch (Exception $e) { }

/* ═══════════════════════════════════════════════════════
   DISTRIBUCIÓN POR TIPO DE PÓLIZA
═══════════════════════════════════════════════════════ */
$por_tipo = [];
try {
    $por_tipo = $db->query(
        "SELECT tipo, COUNT(*) AS num, COALESCE(SUM(prima_anual),0) AS total
         FROM policies WHERE estado='activa'
         GROUP BY tipo ORDER BY num DESC"
    )->fetchAll();
} catch (Exception $e) { }

/* ═══════════════════════════════════════════════════════
   CXC: próximos cobros urgentes
═══════════════════════════════════════════════════════ */
$cxc_urgentes = [];
try {
    $cxc_urgentes = $db->query(
        "SELECT r.*, c.nombre AS cliente_nombre, c.telefono AS cliente_tel
         FROM receivables r JOIN clients c ON c.id=r.client_id
         WHERE r.estado='pendiente' AND r.fecha_vencimiento <= '$in30'
         ORDER BY r.fecha_vencimiento ASC LIMIT 10"
    )->fetchAll();
} catch (Exception $e) { }

/* ═══════════════════════════════════════════════════════
   CXP: próximos pagos urgentes
═══════════════════════════════════════════════════════ */
$cxp_urgentes = [];
try {
    $cxp_urgentes = $db->query(
        "SELECT * FROM payables
         WHERE estado='pendiente' AND fecha_vencimiento <= '$in30'
         ORDER BY fecha_vencimiento ASC LIMIT 10"
    )->fetchAll();
} catch (Exception $e) { }

/* ═══════════════════════════════════════════════════════
   TENDENCIA: cobros por mes (últimos 6 meses)
═══════════════════════════════════════════════════════ */
$tendencia = [];
try {
    $tendencia = $db->query(
        "SELECT DATE_FORMAT(fecha_pago,'%Y-%m') AS mes,
            COALESCE(SUM(monto),0) AS cobrado
         FROM receivables
         WHERE estado='pagado' AND fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY mes ORDER BY mes ASC"
    )->fetchAll();
} catch (Exception $e) { }

/* ── Detectar si las tablas nuevas existen ── */
$tablas_pendientes = [];
foreach (['applications','receivables','payables'] as $t) {
    try {
        $db->query("SELECT 1 FROM $t LIMIT 1");
    } catch (Exception $e) {
        $tablas_pendientes[] = $t;
    }
}

function fmt(float $n): string {
    return 'RD$ ' . number_format($n, 2, '.', ',');
}
function pct_change(int $now, int $prev): string {
    if ($prev === 0) return $now > 0 ? '+nuevo' : '—';
    $p = round((($now - $prev) / $prev) * 100, 1);
    return ($p >= 0 ? '+' : '') . $p . '%';
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — Sanalia CRM</title>
<meta name="robots" content="noindex, nofollow">
<style>
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
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
}
body { font-family:'Inter',system-ui,sans-serif; background:var(--silver-100); color:var(--ink); min-height:100vh; }
.topbar { background:var(--navy-900); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:.875rem 2rem; position:sticky; top:0; z-index:100; gap:1rem; flex-wrap:wrap; }
.topbar .brand { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1rem; }
.topbar .brand span { color:var(--gold-500); }
.topbar-nav { display:flex; gap:.25rem; }
.nav-tab { padding:.4rem .875rem; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; color:rgba(255,255,255,.6); border:none; background:none; cursor:pointer; }
.nav-tab:hover { color:#fff; background:rgba(255,255,255,.08); }
.nav-tab.active { color:#fff; background:rgba(255,255,255,.15); }
.topbar-actions { display:flex; gap:.75rem; align-items:center; }
.btn-outline { background:transparent; border:1.5px solid rgba(255,255,255,.3); color:#fff; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; cursor:pointer; }

.main { max-width:1320px; margin:0 auto; padding:2rem 1.5rem; }
.page-title { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.35rem; color:var(--navy-900); margin-bottom:1.5rem; }
.section-title { font-family:'Manrope',system-ui,sans-serif; font-weight:700; font-size:.9rem; color:var(--navy-900); margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; }
.section-title::after { content:''; flex:1; height:1px; background:var(--silver-300); }

/* ── KPI grid ── */
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:1rem; margin-bottom:2rem; }
.kpi-card { background:#fff; border-radius:12px; padding:1.25rem; box-shadow:0 1px 6px rgba(7,21,35,.07); position:relative; overflow:hidden; }
.kpi-card .kpi-label { font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--silver-500); margin-bottom:.5rem; }
.kpi-card .kpi-val { font-family:'IBM Plex Mono',monospace; font-size:1.6rem; font-weight:700; line-height:1; color:var(--navy-900); }
.kpi-card .kpi-sub { font-size:.72rem; color:var(--silver-500); margin-top:.4rem; }
.kpi-card .kpi-badge { display:inline-block; margin-top:.4rem; font-size:.68rem; font-weight:700; padding:.15rem .5rem; border-radius:999px; }
.kpi-card.ok   .kpi-val { color:var(--green); }
.kpi-card.warn .kpi-val { color:var(--orange); }
.kpi-card.danger .kpi-val { color:var(--red); }
.kpi-card.gold .kpi-val { color:var(--gold-600); }
.kpi-card.blue .kpi-val { color:var(--blue); }
.kpi-card .kpi-accent { position:absolute; top:0; left:0; width:4px; height:100%; border-radius:12px 0 0 12px; }
.kpi-card.ok .kpi-accent    { background:var(--green); }
.kpi-card.warn .kpi-accent  { background:var(--orange); }
.kpi-card.danger .kpi-accent { background:var(--red); }
.kpi-card.gold .kpi-accent  { background:var(--gold-500); }
.kpi-card.blue .kpi-accent  { background:var(--blue); }

/* ── 2-col layout ── */
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:2rem; }
@media (max-width:900px) { .two-col { grid-template-columns:1fr; } }

/* ── Card box ── */
.card { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(7,21,35,.07); overflow:hidden; }
.card-header { background:var(--navy-950); color:#fff; padding:.875rem 1.25rem; font-family:'Manrope',system-ui,sans-serif; font-weight:700; font-size:.875rem; }
.card-body { padding:1.25rem; }

/* ── Tabla ── */
table { width:100%; border-collapse:collapse; font-size:.82rem; }
thead { background:var(--silver-100); }
thead th { padding:.6rem 1rem; text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.06em; color:var(--silver-500); font-weight:600; }
tbody tr { border-bottom:1px solid var(--silver-100); }
tbody tr:last-child { border-bottom:none; }
td { padding:.65rem 1rem; vertical-align:middle; }
.mono { font-family:'IBM Plex Mono',monospace; font-size:.78rem; }

/* ── Badge ── */
.badge { display:inline-block; padding:.18rem .55rem; border-radius:999px; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.badge.pendiente  { background:var(--orange-bg); color:var(--orange); }
.badge.pagado     { background:var(--green-bg);  color:var(--green); }
.badge.vencido    { background:var(--red-bg);    color:var(--red); }
.badge.anulado    { background:#f3f4f6;           color:#6b7280; }

/* ── Distribución por tipo (barras) ── */
.tipo-list { display:flex; flex-direction:column; gap:.625rem; }
.tipo-item .tipo-label { display:flex; justify-content:space-between; font-size:.78rem; margin-bottom:.25rem; }
.tipo-bar-bg { background:var(--silver-100); border-radius:4px; height:8px; }
.tipo-bar-fill { background:var(--navy-700); border-radius:4px; height:8px; transition:width .6s ease; }

/* ── Tendencia barras ── */
.trend-bars { display:flex; align-items:flex-end; gap:.5rem; height:100px; padding:.5rem 0; }
.trend-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:.3rem; }
.trend-bar { width:100%; background:var(--navy-700); border-radius:4px 4px 0 0; min-height:4px; transition:height .6s; }
.trend-label { font-size:.6rem; color:var(--silver-500); font-family:'IBM Plex Mono',monospace; white-space:nowrap; }
.trend-val { font-size:.6rem; color:var(--navy-900); font-weight:600; }

/* ── Empty ── */
.empty { text-align:center; padding:2.5rem; color:var(--silver-500); font-size:.875rem; }

@media (max-width:768px) {
  .topbar { padding:.75rem 1rem; }
  .main { padding:1.25rem 1rem; }
  .kpi-grid { grid-template-columns:repeat(2,1fr); }
}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">Sanalia &amp; Asociados &mdash; <span>CRM</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="nav-tab active">Dashboard</a>
    <a href="index.php"     class="nav-tab">Solicitudes</a>
    <a href="clients.php"   class="nav-tab">Clientes</a>
    <a href="finances.php"  class="nav-tab">Finanzas</a>
    <a href="calendar.php"  class="nav-tab">Calendario</a>
  </div>
  <div class="topbar-actions">
    <form method="POST" action="index.php" style="margin:0">
      <button type="submit" name="logout" class="btn-outline">Salir</button>
    </form>
  </div>
</div>

<div class="main">

<?php if (!empty($tablas_pendientes)): ?>
<div style="background:#fef3c7;border:1.5px solid #d97706;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.875rem;display:flex;align-items:center;gap:.75rem">
  <span style="font-size:1.25rem">⚠️</span>
  <div>
    <strong>Acción requerida:</strong> Corre el archivo <code>admin/schema.sql</code> completo en phpMyAdmin para habilitar todas las funciones del CRM.
    Tablas pendientes: <strong><?= implode(', ', $tablas_pendientes) ?></strong>.
    Los KPIs de finanzas y solicitudes mostrarán cero hasta entonces.
  </div>
</div>
<?php endif; ?>

<div class="page-title">Dashboard — <?= date('F Y') ?></div>

<!-- ── KPIs: CARTERA ── -->
<div class="section-title">Cartera de pólizas</div>
<div class="kpi-grid">
  <div class="kpi-card gold">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Primas activas</div>
    <div class="kpi-val"><?= 'RD$ '.number_format($cartera_total, 0, '.', ',') ?></div>
    <div class="kpi-sub">Valor total anual de la cartera</div>
  </div>
  <div class="kpi-card ok">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Pólizas activas</div>
    <div class="kpi-val"><?= $polizas_activas ?></div>
    <div class="kpi-sub"><?= $clientes_total ?> clientes en CRM</div>
  </div>
  <div class="kpi-card <?= $polizas_vencidas > 0 ? 'danger' : '' ?>">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Vencidas sin renovar</div>
    <div class="kpi-val"><?= $polizas_vencidas ?></div>
    <div class="kpi-sub">Requieren atención inmediata</div>
  </div>
  <div class="kpi-card <?= $polizas_vence30 > 0 ? 'warn' : '' ?>">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Vencen en 30 días</div>
    <div class="kpi-val"><?= $polizas_vence30 ?></div>
    <div class="kpi-sub"><?= $polizas_renovacion ?> en proceso de renovación</div>
  </div>
  <div class="kpi-card blue">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Nuevas pólizas (mes)</div>
    <div class="kpi-val"><?= $nuevas_mes ?></div>
    <div class="kpi-sub">
      <?php $chg = pct_change($nuevas_mes, $nuevas_ant); ?>
      vs mes anterior:
      <span style="color:<?= str_starts_with($chg,'+') && $chg !== '+nuevo' ? 'var(--green)' : (str_starts_with($chg,'-') ? 'var(--red)' : 'inherit') ?>;font-weight:700"><?= $chg ?></span>
    </div>
  </div>
</div>

<!-- ── KPIs: SOLICITUDES ── -->
<div class="section-title">Solicitudes</div>
<div class="kpi-grid">
  <div class="kpi-card blue">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Solicitudes este mes</div>
    <div class="kpi-val"><?= $apps_mes ?></div>
  </div>
  <div class="kpi-card <?= $apps_pendientes > 0 ? 'warn' : '' ?>">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Pendientes de revisión</div>
    <div class="kpi-val"><?= $apps_pendientes ?></div>
  </div>
  <div class="kpi-card ok">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Aprobadas</div>
    <div class="kpi-val"><?= $apps_aprobadas ?></div>
    <div class="kpi-sub">de <?= $apps_total ?> totales</div>
  </div>
  <div class="kpi-card gold">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Tasa de conversión</div>
    <div class="kpi-val"><?= $conv_rate ?>%</div>
    <div class="kpi-sub">Solicitudes aprobadas / total</div>
  </div>
</div>

<!-- ── KPIs: FINANCIEROS ── -->
<div class="section-title">Cuentas por cobrar &amp; pagar</div>
<div class="kpi-grid">
  <div class="kpi-card gold">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Por cobrar pendiente</div>
    <div class="kpi-val"><?= 'RD$ '.number_format($cxc_pendiente, 0, '.', ',') ?></div>
    <div class="kpi-sub">Cobrado este mes: <?= fmt($cxc_cobrado_mes) ?></div>
  </div>
  <div class="kpi-card <?= $cxc_vencido > 0 ? 'danger' : '' ?>">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Cobros vencidos</div>
    <div class="kpi-val"><?= 'RD$ '.number_format($cxc_vencido, 0, '.', ',') ?></div>
    <div class="kpi-sub">Vencen próx. 30 d: <?= fmt($cxc_vence30) ?></div>
  </div>
  <div class="kpi-card <?= $cxp_pendiente > 0 ? 'warn' : '' ?>">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Por pagar pendiente</div>
    <div class="kpi-val"><?= 'RD$ '.number_format($cxp_pendiente, 0, '.', ',') ?></div>
    <div class="kpi-sub">Pagado este mes: <?= fmt($cxp_pagado_mes) ?></div>
  </div>
  <div class="kpi-card <?= $cxp_vencido > 0 ? 'danger' : '' ?>">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Pagos vencidos</div>
    <div class="kpi-val"><?= 'RD$ '.number_format($cxp_vencido, 0, '.', ',') ?></div>
  </div>
</div>

<!-- ── 2 columnas: Top clientes + Distribución por tipo ── -->
<div class="two-col">

  <div class="card">
    <div class="card-header">Top clientes por valor de cartera</div>
    <div class="card-body">
      <?php if (empty($top_clientes)): ?>
        <div class="empty">Sin datos aún.</div>
      <?php else: ?>
      <table>
        <thead><tr>
          <th>#</th><th>Cliente</th><th>Pólizas</th><th>Cartera anual</th>
        </tr></thead>
        <tbody>
        <?php foreach ($top_clientes as $i => $cl): ?>
        <tr>
          <td style="color:var(--silver-500)"><?= $i+1 ?></td>
          <td style="font-weight:600"><?= htmlspecialchars($cl['nombre']) ?></td>
          <td><?= $cl['num_polizas'] ?></td>
          <td class="mono"><?= fmt((float)$cl['cartera']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Distribución por tipo de póliza</div>
    <div class="card-body">
      <?php if (empty($por_tipo)): ?>
        <div class="empty">Sin datos aún.</div>
      <?php else:
        $max_tipo = max(array_column($por_tipo, 'num'));
      ?>
      <div class="tipo-list">
        <?php foreach ($por_tipo as $t): ?>
        <div class="tipo-item">
          <div class="tipo-label">
            <span><?= htmlspecialchars($t['tipo'] ?: 'Sin tipo') ?></span>
            <span class="mono" style="color:var(--silver-500)"><?= $t['num'] ?> · <?= fmt((float)$t['total']) ?></span>
          </div>
          <div class="tipo-bar-bg">
            <div class="tipo-bar-fill" style="width:<?= $max_tipo > 0 ? round(($t['num']/$max_tipo)*100) : 0 ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ── 2 columnas: Cobros urgentes + Pagos urgentes ── -->
<div class="two-col">

  <div class="card">
    <div class="card-header">Cobros urgentes — próximos 30 días</div>
    <div class="card-body" style="padding:0">
      <?php if (empty($cxc_urgentes)): ?>
        <div class="empty">Sin cobros próximos.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>Cliente</th><th>Concepto</th><th>Vence</th><th>Monto</th></tr></thead>
        <tbody>
        <?php foreach ($cxc_urgentes as $r):
          $dias = (int)ceil((strtotime($r['fecha_vencimiento']) - time()) / 86400);
          $cls  = $dias < 0 ? 'vencido' : 'pendiente';
        ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($r['cliente_nombre']) ?></td>
          <td style="font-size:.78rem"><?= htmlspecialchars($r['concepto']) ?></td>
          <td><span class="badge <?= $cls ?>"><?= $dias < 0 ? abs($dias).'d atrás' : $dias.'d' ?></span></td>
          <td class="mono"><?= fmt((float)$r['monto']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Pagos urgentes — próximos 30 días</div>
    <div class="card-body" style="padding:0">
      <?php if (empty($cxp_urgentes)): ?>
        <div class="empty">Sin pagos próximos.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>Beneficiario</th><th>Concepto</th><th>Vence</th><th>Monto</th></tr></thead>
        <tbody>
        <?php foreach ($cxp_urgentes as $p):
          $dias = (int)ceil((strtotime($p['fecha_vencimiento']) - time()) / 86400);
          $cls  = $dias < 0 ? 'vencido' : 'pendiente';
        ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($p['beneficiario']) ?></td>
          <td style="font-size:.78rem"><?= htmlspecialchars($p['concepto']) ?></td>
          <td><span class="badge <?= $cls ?>"><?= $dias < 0 ? abs($dias).'d atrás' : $dias.'d' ?></span></td>
          <td class="mono"><?= fmt((float)$p['monto']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ── Tendencia de cobros ── -->
<?php if (!empty($tendencia)): ?>
<div class="section-title">Tendencia de cobros (últimos 6 meses)</div>
<div class="card" style="margin-bottom:2rem">
  <div class="card-body">
    <?php
      $max_tend = max(array_column($tendencia, 'cobrado'));
    ?>
    <div class="trend-bars">
      <?php foreach ($tendencia as $t):
        $h = $max_tend > 0 ? max(8, round(($t['cobrado'] / $max_tend) * 90)) : 8;
      ?>
      <div class="trend-col">
        <div class="trend-val"><?= 'RD$ '.number_format((float)$t['cobrado']/1000, 1) ?>K</div>
        <div class="trend-bar" style="height:<?= $h ?>px"></div>
        <div class="trend-label"><?= $t['mes'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

</div><!-- /.main -->
</body>
</html>
