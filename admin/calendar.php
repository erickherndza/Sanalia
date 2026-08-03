<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db.php';

$db = get_db();

/* ── JSON de eventos ── */
if (isset($_GET['events'])) {
    header('Content-Type: application/json; charset=utf-8');
    $today  = date('Y-m-d');
    $events = [];

    /* ── Seguimientos de leads ── */
    try {
        $leads = $db->query(
            "SELECT id, nombre, telefono, interes, estado, fecha_proximo_contacto
             FROM leads
             WHERE fecha_proximo_contacto IS NOT NULL
               AND estado NOT IN ('ganado','perdido')
             ORDER BY fecha_proximo_contacto ASC"
        )->fetchAll();

        $estados_label = [
            'nuevo'       => 'Nuevo',
            'contactado'  => 'Contactado',
            'seguimiento' => 'Seguimiento',
        ];
        $interes_label = [
            'vida'=>'Vida','salud'=>'Salud','viajes'=>'Viajes',
            'vehiculos'=>'Vehículos','accidentes-personales'=>'Accidentes',
            'internacionales'=>'Internacional','riesgos-generales'=>'Riesgos Generales',
            'mascotas'=>'Mascotas','exequial'=>'Exequial','otro'=>'General',
        ];

        foreach ($leads as $l) {
            $vencido = $l['fecha_proximo_contacto'] < $today;
            $hoy     = $l['fecha_proximo_contacto'] === $today;

            if ($vencido) {
                $color = '#dc2626'; // rojo — atrasado
            } elseif ($hoy) {
                $color = '#d97706'; // naranja — hoy
            } else {
                $color = '#1E4468'; // navy — próximo
            }

            $interes_str = $interes_label[$l['interes']] ?? ($l['interes'] ?: 'Seguros');
            $estado_str  = $estados_label[$l['estado']]  ?? $l['estado'];

            $events[] = [
                'id'    => 'lead_' . $l['id'],
                'title' => '📋 ' . $l['nombre'],
                'start' => $l['fecha_proximo_contacto'],
                'color' => $color,
                'extendedProps' => [
                    'tipo'      => 'lead',
                    'leadId'    => $l['id'],
                    'nombre'    => $l['nombre'],
                    'telefono'  => $l['telefono'],
                    'interes'   => $interes_str,
                    'estado'    => $estado_str,
                    'vencido'   => $vencido,
                    'hoy'       => $hoy,
                ],
            ];
        }
    } catch (Exception $e) { /* tabla leads no existe aún */ }

    echo json_encode($events, JSON_UNESCAPED_UNICODE);
    exit;
}

$ics_token = defined('CALENDAR_TOKEN') ? CALENDAR_TOKEN : '';
$ics_url   = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/admin/feed.ics?token=' . urlencode($ics_token);
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Calendario — Sanalia CRM</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --navy-950:#071523; --navy-900:#0C2036; --navy-800:#153350; --navy-700:#1E4468;
  --gold-500:#C6A15B; --gold-600:#A9843F;
  --silver-100:#F3F5F7; --silver-300:#DCE1E7; --silver-500:#AEB8C4;
  --ink:#0E1620; --green:#1a7f4b; --red:#dc2626; --orange:#d97706;
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
.btn-outline:hover { border-color:#fff; }

.main { max-width:1280px; margin:0 auto; padding:2rem 1.5rem; }

/* ── Contadores rápidos ── */
.counters { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
.counter-card { background:#fff; border-radius:12px; padding:1rem 1.25rem; box-shadow:0 1px 4px rgba(7,21,35,.07); border-left:4px solid transparent; }
.counter-card.red    { border-color:var(--red); }
.counter-card.orange { border-color:var(--orange); }
.counter-card.navy   { border-color:var(--navy-700); }
.counter-card .num { font-family:'IBM Plex Mono',monospace; font-size:1.75rem; font-weight:700; line-height:1; }
.counter-card.red    .num { color:var(--red); }
.counter-card.orange .num { color:var(--orange); }
.counter-card.navy   .num { color:var(--navy-700); }
.counter-card .lbl { font-size:.75rem; color:var(--silver-500); margin-top:.25rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }

/* ── Leyenda ── */
.legend { display:flex; gap:1.25rem; flex-wrap:wrap; margin-bottom:1.25rem; align-items:center; }
.legend-item { display:flex; align-items:center; gap:.4rem; font-size:.78rem; color:var(--silver-500); }
.legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

/* ── Calendario ── */
.calendar-wrap { background:#fff; border-radius:14px; box-shadow:0 1px 8px rgba(7,21,35,.08); padding:1.5rem; }
.fc .fc-toolbar-title { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.1rem; color:var(--navy-900); }
.fc .fc-button { background:var(--navy-900) !important; border-color:var(--navy-900) !important; font-size:.8rem !important; }
.fc .fc-button:hover { background:var(--navy-700) !important; }
.fc .fc-button-active { background:var(--navy-800) !important; }
.fc .fc-daygrid-day-number { font-family:'IBM Plex Mono',monospace; font-size:.78rem; color:var(--silver-500); }
.fc .fc-day-today { background:#f0f7ff !important; }
.fc .fc-event { border:none !important; border-radius:5px !important; padding:2px 5px !important; font-size:.72rem !important; cursor:pointer !important; }
.fc .fc-event-title { font-weight:600; }

/* ── Modal evento lead ── */
.event-modal-overlay { display:none; position:fixed; inset:0; background:rgba(7,21,35,.5); z-index:400; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.event-modal-overlay.open { display:flex; }
.event-modal { background:#fff; border-radius:14px; padding:2rem; width:100%; max-width:420px; box-shadow:0 8px 40px rgba(7,21,35,.2); position:relative; }
.event-modal .close { position:absolute; top:1rem; right:1rem; background:var(--silver-100); border:none; width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:.9rem; }
.event-modal h3 { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.05rem; color:var(--navy-900); margin-bottom:.25rem; }
.event-modal .em-sub { font-size:.78rem; color:var(--silver-500); margin-bottom:1.25rem; }
.em-badge { display:inline-block; padding:.25rem .75rem; border-radius:999px; font-size:.72rem; font-weight:700; margin-bottom:1rem; }
.em-badge.vencido { background:#fee2e2; color:var(--red); }
.em-badge.hoy     { background:#fef3c7; color:#92400e; }
.em-badge.proximo { background:#e0f2fe; color:#0369a1; }
.em-row { display:flex; justify-content:space-between; font-size:.875rem; padding:.4rem 0; border-bottom:1px solid var(--silver-100); }
.em-row:last-child { border-bottom:none; }
.em-row .lbl { color:var(--silver-500); font-size:.78rem; }
.em-row .val { font-weight:600; color:var(--navy-900); text-align:right; }
.em-actions { display:flex; gap:.75rem; margin-top:1.25rem; flex-wrap:wrap; }
.btn-wa  { flex:1; padding:.65rem; background:#25d366; color:#fff; border:none; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; text-decoration:none; text-align:center; white-space:nowrap; }
.btn-nav { flex:1; padding:.65rem; background:var(--navy-900); color:#fff; border:none; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; text-decoration:none; text-align:center; white-space:nowrap; }

@media (max-width:768px) {
  .topbar { padding:.75rem 1rem; }
  .main { padding:1rem; }
  .calendar-wrap { padding:1rem; }
  .counters { grid-template-columns:1fr 1fr; }
  .legend { display:none; }
}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">Sanalia &amp; Asociados &mdash; <span>CRM</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="nav-tab">Dashboard</a>
    <a href="leads.php"     class="nav-tab">Contactos</a>
    <a href="calendar.php"  class="nav-tab active">Calendario</a>
    <a href="import.php"    class="nav-tab">Importar</a>
  </div>
  <div class="topbar-actions">
    <form method="POST" action="index.php" style="margin:0">
      <button type="submit" name="logout" class="btn-outline">Salir</button>
    </form>
  </div>
</div>

<div class="main">

  <!-- Contadores rápidos -->
  <div class="counters" id="counters">
    <div class="counter-card red">
      <div class="num" id="cntVencidos">—</div>
      <div class="lbl">Seguimientos atrasados</div>
    </div>
    <div class="counter-card orange">
      <div class="num" id="cntHoy">—</div>
      <div class="lbl">Contactos para hoy</div>
    </div>
    <div class="counter-card navy">
      <div class="num" id="cntProximos">—</div>
      <div class="lbl">Próximos 7 días</div>
    </div>
  </div>

  <!-- Leyenda -->
  <div class="legend">
    <span style="font-size:.78rem;font-weight:600;color:var(--navy-900)">Seguimientos:</span>
    <span class="legend-item"><span class="legend-dot" style="background:var(--red)"></span> Atrasado</span>
    <span class="legend-item"><span class="legend-dot" style="background:var(--orange)"></span> Hoy</span>
    <span class="legend-item"><span class="legend-dot" style="background:var(--navy-700)"></span> Próximo</span>
  </div>

  <div class="calendar-wrap">
    <div id="calendar"></div>
  </div>

</div>

<!-- Modal lead -->
<div class="event-modal-overlay" id="eventModal">
  <div class="event-modal">
    <button class="close" id="closeEventModal">✕</button>
    <h3 id="emNombre"></h3>
    <div class="em-sub" id="emSub"></div>
    <div id="emBadge"></div>
    <div id="emRows"></div>
    <div class="em-actions">
      <a class="btn-wa"  id="emWa"  href="#" target="_blank">💬 WhatsApp</a>
      <a class="btn-nav" id="emNav" href="#">Abrir en CRM</a>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

  const today = new Date().toISOString().slice(0, 10);
  const in7   = new Date(Date.now() + 7*86400000).toISOString().slice(0,10);

  /* ── FullCalendar ── */
  const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
    initialView: 'dayGridMonth',
    locale: 'es',
    headerToolbar: {
      left:   'prev,next today',
      center: 'title',
      right:  'dayGridMonth,listMonth'
    },
    buttonText: { today:'Hoy', month:'Mes', list:'Lista' },
    height: 'auto',
    events: 'calendar.php?events=1',
    noEventsContent: 'No hay seguimientos programados.',

    eventDidMount: function(info) {
      const p = info.event.extendedProps;
      info.el.title = p.nombre + ' — ' + p.interes + ' (' + p.estado + ')';
    },

    eventClick: function(info) {
      openLeadModal(info.event);
    },

    // Actualizar contadores una vez que carguen los eventos
    eventSourceSuccess: function(events) {
      let vencidos = 0, hoy = 0, proximos = 0;
      events.forEach(function(e) {
        const p = e.extendedProps;
        if (!p || p.tipo !== 'lead') return;
        if (p.vencido)      vencidos++;
        else if (p.hoy)     hoy++;
        else if (e.start <= in7) proximos++;
      });
      document.getElementById('cntVencidos').textContent = vencidos;
      document.getElementById('cntHoy').textContent      = hoy;
      document.getElementById('cntProximos').textContent = proximos;
    },
  });
  calendar.render();

  /* ── Modal lead ── */
  const modal       = document.getElementById('eventModal');
  const closeBtn    = document.getElementById('closeEventModal');
  closeBtn.addEventListener('click', function() { modal.classList.remove('open'); });
  modal.addEventListener('click', function(e) { if (e.target === modal) modal.classList.remove('open'); });

  function waPhone(raw) {
    const d = (raw||'').replace(/\D/g,'');
    if (d.length === 11 && d.startsWith('1')) return d;
    if (d.length === 10) return '1' + d;
    return d;
  }

  function openLeadModal(event) {
    const p    = event.extendedProps;
    const date = event.startStr;

    document.getElementById('emNombre').textContent = p.nombre;
    document.getElementById('emSub').textContent    = 'Seguimiento programado: ' + date;

    let badgeClass = 'proximo', badgeText = '📅 Próximo contacto';
    if (p.vencido) { badgeClass = 'vencido'; badgeText = '⚠️ Seguimiento atrasado'; }
    else if (p.hoy) { badgeClass = 'hoy'; badgeText = '🔔 Contactar hoy'; }
    document.getElementById('emBadge').innerHTML =
      '<div class="em-badge ' + badgeClass + '">' + badgeText + '</div>';

    const rows = [
      ['Interés',       p.interes || '—'],
      ['Estado actual', p.estado  || '—'],
      ['Fecha agendada', date],
    ];
    document.getElementById('emRows').innerHTML = rows.map(function(r) {
      return '<div class="em-row"><span class="lbl">'+r[0]+'</span><span class="val">'+r[1]+'</span></div>';
    }).join('');

    const tel = waPhone(p.telefono);
    const waMsg = encodeURIComponent('Hola ' + p.nombre + ', te contacto de Sanalia & Asociados con información sobre ' + (p.interes || 'seguros') + '. ¿Tienes un momento?');
    document.getElementById('emWa').href  = tel ? 'https://wa.me/' + tel + '?text=' + waMsg : '#';
    document.getElementById('emNav').href = 'leads.php';

    modal.classList.add('open');
  }

});
</script>
</body>
</html>
