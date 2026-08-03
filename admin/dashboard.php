<?php
/**
 * Sanalia CRM — Dashboard de Leads / Pipeline
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_auth();
$db = get_db();
$today = date('Y-m-d');
$mes   = date('Y-m-01');
$mes_ant     = date('Y-m-01', strtotime('-1 month'));
$fin_mes_ant = date('Y-m-t',  strtotime('-1 month'));

/* ── Verificar tabla leads ── */
$leads_ok = true;
try { $db->query("SELECT 1 FROM leads LIMIT 1"); }
catch (Exception $e) { $leads_ok = false; }

/* ── KPIs ── */
$total_mes = $total_mes_ant = 0;
$por_estado = ['nuevo'=>0,'contactado'=>0,'seguimiento'=>0,'ganado'=>0,'perdido'=>0];
$por_fuente = [];
$recientes  = [];
$conv_rate  = 0.0;
$total_all  = 0;

if ($leads_ok) {
    $total_mes     = (int)$db->query("SELECT COUNT(*) FROM leads WHERE created_at >= '$mes'")->fetchColumn();
    $total_mes_ant = (int)$db->query("SELECT COUNT(*) FROM leads WHERE created_at BETWEEN '$mes_ant' AND '$fin_mes_ant 23:59:59'")->fetchColumn();
    $total_all     = (int)$db->query("SELECT COUNT(*) FROM leads")->fetchColumn();

    foreach (array_keys($por_estado) as $e) {
        $por_estado[$e] = (int)$db->query("SELECT COUNT(*) FROM leads WHERE estado='$e'")->fetchColumn();
    }

    $fuente_rows = $db->query("SELECT fuente, COUNT(*) AS n FROM leads GROUP BY fuente ORDER BY n DESC")->fetchAll();
    foreach ($fuente_rows as $f) { $por_fuente[$f['fuente']] = (int)$f['n']; }

    $ganados   = $por_estado['ganado'];
    $conv_rate = $total_all > 0 ? round(($ganados / $total_all) * 100, 1) : 0.0;

    $recientes = $db->query(
        "SELECT * FROM leads ORDER BY created_at DESC LIMIT 10"
    )->fetchAll();
}

$fuentes_label = ['web'=>'Web','facebook'=>'Facebook','instagram'=>'Instagram','whatsapp'=>'WhatsApp','referido'=>'Referido','otro'=>'Otro'];

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
}
body { font-family:'Inter',system-ui,sans-serif; background:var(--silver-100); color:var(--ink); min-height:100vh; }
.topbar { background:var(--navy-900); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:.875rem 2rem; position:sticky; top:0; z-index:100; gap:1rem; flex-wrap:wrap; }
.topbar .brand { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1rem; }
.topbar .brand span { color:var(--gold-500); }
.topbar-nav { display:flex; gap:.25rem; }
.nav-tab { padding:.4rem .875rem; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; color:rgba(255,255,255,.6); border:none; background:none; cursor:pointer; white-space:nowrap; }
.nav-tab:hover { color:#fff; background:rgba(255,255,255,.08); }
.nav-tab.active { color:#fff; background:rgba(255,255,255,.15); }
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
.kpi-card.ok .kpi-val     { color:var(--green); }
.kpi-card.warn .kpi-val   { color:var(--orange); }
.kpi-card.danger .kpi-val { color:var(--red); }
.kpi-card.gold .kpi-val   { color:var(--gold-600); }
.kpi-card.blue .kpi-val   { color:var(--blue); }
.kpi-card.purple .kpi-val { color:var(--purple); }
.kpi-card .kpi-accent { position:absolute; top:0; left:0; width:4px; height:100%; border-radius:12px 0 0 12px; }
.kpi-card.ok .kpi-accent     { background:var(--green); }
.kpi-card.warn .kpi-accent   { background:var(--orange); }
.kpi-card.danger .kpi-accent { background:var(--red); }
.kpi-card.gold .kpi-accent   { background:var(--gold-500); }
.kpi-card.blue .kpi-accent   { background:var(--blue); }
.kpi-card.purple .kpi-accent { background:var(--purple); }

/* ── 2-col layout ── */
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:2rem; }
@media (max-width:900px) { .two-col { grid-template-columns:1fr; } }

/* ── Card box ── */
.card { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(7,21,35,.07); overflow:hidden; }
.card-header { background:var(--navy-950); color:#fff; padding:.875rem 1.25rem; font-family:'Manrope',system-ui,sans-serif; font-weight:700; font-size:.875rem; }
.card-body { padding:1.25rem; }

/* ── Funnel ── */
.funnel { display:flex; flex-direction:column; gap:.625rem; }
.funnel-row { display:flex; align-items:center; gap:.75rem; }
.funnel-label { width:110px; font-size:.78rem; font-weight:600; flex-shrink:0; }
.funnel-bar-bg { flex:1; background:var(--silver-100); border-radius:4px; height:20px; overflow:hidden; }
.funnel-bar-fill { height:100%; border-radius:4px; transition:width .6s ease; display:flex; align-items:center; justify-content:flex-end; padding-right:.4rem; }
.funnel-bar-fill span { font-size:.65rem; font-weight:700; color:#fff; }
.funnel-count { width:40px; text-align:right; font-family:'IBM Plex Mono',monospace; font-size:.8rem; color:var(--navy-900); }

/* ── Tabla ── */
table { width:100%; border-collapse:collapse; font-size:.82rem; }
thead { background:var(--silver-100); }
thead th { padding:.6rem 1rem; text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.06em; color:var(--silver-500); font-weight:600; }
tbody tr { border-bottom:1px solid var(--silver-100); }
tbody tr:last-child { border-bottom:none; }
td { padding:.65rem 1rem; vertical-align:middle; }

/* ── Badges ── */
.badge { display:inline-block; padding:.18rem .55rem; border-radius:999px; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.badge-nuevo       { background:var(--blue-bg);   color:var(--blue); }
.badge-contactado  { background:var(--orange-bg); color:var(--orange); }
.badge-seguimiento { background:var(--purple-bg); color:var(--purple); }
.badge-ganado      { background:var(--green-bg);  color:var(--green); }
.badge-perdido     { background:var(--red-bg);    color:var(--red); }

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
    <a href="leads.php"     class="nav-tab">Contactos</a>
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
<?php endif; ?>

<div class="page-title">Dashboard — <?= date('F Y') ?></div>

<!-- ── KPIs: RESUMEN GENERAL ── -->
<div class="section-title">Pipeline de contactos</div>
<div class="kpi-grid">
  <div class="kpi-card blue">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Leads este mes</div>
    <div class="kpi-val"><?= $total_mes ?></div>
    <div class="kpi-sub">
      <?php $chg = pct_change($total_mes, $total_mes_ant); ?>
      vs mes anterior:
      <span style="color:<?= str_starts_with($chg,'+') && $chg !== '+nuevo' ? 'var(--green)' : (str_starts_with($chg,'-') ? 'var(--red)' : 'inherit') ?>;font-weight:700"><?= $chg ?></span>
    </div>
  </div>
  <div class="kpi-card <?= $por_estado['nuevo'] > 0 ? 'warn' : '' ?>">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Sin contactar</div>
    <div class="kpi-val"><?= $por_estado['nuevo'] ?></div>
    <div class="kpi-sub">Requieren primer contacto</div>
  </div>
  <div class="kpi-card purple">
    <div class="kpi-accent"></div>
    <div class="kpi-label">En seguimiento</div>
    <div class="kpi-val"><?= $por_estado['seguimiento'] ?></div>
    <div class="kpi-sub"><?= $por_estado['contactado'] ?> contactados</div>
  </div>
  <div class="kpi-card ok">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Ganados</div>
    <div class="kpi-val"><?= $por_estado['ganado'] ?></div>
    <div class="kpi-sub"><?= $por_estado['perdido'] ?> perdidos</div>
  </div>
  <div class="kpi-card gold">
    <div class="kpi-accent"></div>
    <div class="kpi-label">Tasa de conversión</div>
    <div class="kpi-val"><?= $conv_rate ?>%</div>
    <div class="kpi-sub">Ganados / <?= $total_all ?> totales</div>
  </div>
</div>

<!-- ── 2 columnas: Funnel + Por fuente ── -->
<div class="two-col">

  <div class="card">
    <div class="card-header">Funnel del pipeline</div>
    <div class="card-body">
      <?php
      $funnel_items = [
        'nuevo'       => ['Nuevo',       '#1d4ed8'],
        'contactado'  => ['Contactado',  '#d97706'],
        'seguimiento' => ['Seguimiento', '#7c3aed'],
        'ganado'      => ['Ganado',      '#1a7f4b'],
        'perdido'     => ['Perdido',     '#dc2626'],
      ];
      $max_f = max(array_values($por_estado)) ?: 1;
      ?>
      <div class="funnel">
        <?php foreach ($funnel_items as $k => [$label, $color]):
          $n = $por_estado[$k];
          $w = $max_f > 0 ? round(($n / $max_f) * 100) : 0;
        ?>
        <div class="funnel-row">
          <span class="funnel-label"><?= $label ?></span>
          <div class="funnel-bar-bg">
            <div class="funnel-bar-fill" style="width:<?= max($w,0) ?>%;background:<?= $color ?>">
              <?php if ($n > 0): ?><span><?= $n ?></span><?php endif; ?>
            </div>
          </div>
          <span class="funnel-count"><?= $n ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Leads por canal de origen</div>
    <div class="card-body">
      <?php if (empty($por_fuente)): ?>
        <div class="empty">Sin datos aún.</div>
      <?php else:
        $max_fuen = max(array_values($por_fuente)) ?: 1;
      ?>
      <div class="funnel">
        <?php foreach ($por_fuente as $f => $n):
          $label = $fuentes_label[$f] ?? $f;
          $w     = round(($n / $max_fuen) * 100);
        ?>
        <div class="funnel-row">
          <span class="funnel-label"><?= $label ?></span>
          <div class="funnel-bar-bg">
            <div class="funnel-bar-fill" style="width:<?= max($w,2) ?>%;background:var(--navy-700)">
              <?php if ($n > 0): ?><span><?= $n ?></span><?php endif; ?>
            </div>
          </div>
          <span class="funnel-count"><?= $n ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ── Leads recientes ── -->
<?php if (!empty($recientes)): ?>
<div class="section-title">Contactos recientes</div>
<div class="card" style="margin-bottom:2rem">
  <div class="card-body" style="padding:0">
    <table>
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Interés</th>
          <th>Fuente</th>
          <th>Campaña</th>
          <th>Estado</th>
          <th>Fecha</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $interes_opts = [
          'vida'=>'Vida','salud'=>'Salud','viajes'=>'Viajes',
          'vehiculos'=>'Vehículos','accidentes-personales'=>'Accidentes',
          'internacionales'=>'Internacional','riesgos-generales'=>'Riesgos Grales.',
          'mascotas'=>'Mascotas','exequial'=>'Exequial','otro'=>'Otro',
      ];
      foreach ($recientes as $r):
        $interes_label = $interes_opts[$r['interes']] ?? ($r['interes'] ?: '—');
        $fuente_label  = $fuentes_label[$r['fuente']] ?? $r['fuente'];
        $fecha_corta   = $r['created_at'] ? date('d/m/y H:i', strtotime($r['created_at'])) : '—';
      ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($r['nombre']) ?></td>
        <td style="font-size:.78rem"><?= htmlspecialchars($interes_label) ?></td>
        <td><span style="font-size:.72rem"><?= htmlspecialchars($fuente_label) ?></span></td>
        <td style="font-size:.72rem;color:var(--silver-500)"><?= htmlspecialchars($r['campana'] ?: '—') ?></td>
        <td><span class="badge badge-<?= $r['estado'] ?>"><?= ucfirst($r['estado']) ?></span></td>
        <td style="font-size:.72rem;color:var(--silver-500)"><?= $fecha_corta ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div style="text-align:center;margin-bottom:2rem">
  <a href="leads.php" style="color:var(--navy-700);font-size:.84rem;font-weight:600;text-decoration:none">Ver todos los contactos →</a>
</div>
<?php endif; ?>

</div><!-- /.main -->

<?php if (guest_minutes_left() >= 0): ?>
<script>
(function() {
  var el = document.getElementById('guestTimer');
  if (!el) return;
  var secs = parseInt(el.dataset.mins, 10) * 60;
  var iv = setInterval(function() {
    secs--;
    if (secs <= 0) { clearInterval(iv); location.href = 'index.php?expired=1'; return; }
    var m = Math.floor(secs / 60), s = secs % 60;
    el.textContent = '⏱ ' + m + 'm ' + (s < 10 ? '0' : '') + s + 's restantes';
    if (secs <= 300) el.style.background = '#c0392b';
  }, 1000);
})();
</script>
<?php endif; ?>
</body>
</html>
