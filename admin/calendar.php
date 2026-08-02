<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db.php';

$db = get_db();

// Eventos para FullCalendar (JSON)
if (isset($_GET['events'])) {
    header('Content-Type: application/json; charset=utf-8');
    $policies = $db->query(
        "SELECT p.id, p.tipo, p.numero_poliza, p.aseguradora, p.fecha_vencimiento,
                p.estado, p.prima_anual, p.client_id,
                c.nombre AS cliente_nombre, c.telefono AS cliente_tel
         FROM policies p
         JOIN clients c ON c.id = p.client_id
         WHERE p.fecha_vencimiento IS NOT NULL
         ORDER BY p.fecha_vencimiento ASC"
    )->fetchAll();

    $events = [];
    $today  = date('Y-m-d');
    foreach ($policies as $p) {
        $dias = (int) ceil((strtotime($p['fecha_vencimiento']) - time()) / 86400);
        if ($p['estado'] === 'cancelada') {
            $color = '#9ca3af';
        } elseif ($p['estado'] === 'vencida' || $dias < 0) {
            $color = '#dc2626';
        } elseif ($dias <= 30) {
            $color = '#ef4444';
        } elseif ($dias <= 60) {
            $color = '#d97706';
        } elseif ($dias <= 90) {
            $color = '#7c3aed';
        } else {
            $color = '#1a7f4b';
        }
        $events[] = [
            'id'           => $p['id'],
            'title'        => ($p['tipo'] ? strtoupper($p['tipo']) . ' — ' : '') . $p['cliente_nombre'],
            'start'        => $p['fecha_vencimiento'],
            'color'        => $color,
            'extendedProps' => [
                'clientId'    => $p['client_id'],
                'clientNombre'=> $p['cliente_nombre'],
                'clientTel'   => $p['cliente_tel'],
                'tipo'        => $p['tipo'],
                'numPoliza'   => $p['numero_poliza'],
                'aseguradora' => $p['aseguradora'],
                'prima'       => $p['prima_anual'],
                'estado'      => $p['estado'],
                'dias'        => $dias,
            ],
        ];
    }
    echo json_encode($events, JSON_UNESCAPED_UNICODE);
    exit;
}

// Token para ICS feed
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
  --ink:#0E1620; --green:#1a7f4b; --red:#dc2626; --orange:#d97706; --purple:#7c3aed;
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
.btn-outlook { background:var(--gold-500); color:var(--navy-950); border:none; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; }
.btn-outlook:hover { background:var(--gold-600); }
.btn-outline { background:transparent; border:1.5px solid rgba(255,255,255,.3); color:#fff; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; cursor:pointer; }
.btn-outline:hover { border-color:#fff; }

.main { max-width:1280px; margin:0 auto; padding:2rem 1.5rem; }

/* ── Leyenda ── */
.legend { display:flex; gap:1.25rem; flex-wrap:wrap; margin-bottom:1.5rem; align-items:center; }
.legend-item { display:flex; align-items:center; gap:.4rem; font-size:.78rem; color:var(--silver-500); }
.legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

/* ── Calendario ── */
.calendar-wrap {
  background:#fff;
  border-radius:14px;
  box-shadow:0 1px 8px rgba(7,21,35,.08);
  padding:1.5rem;
}

.fc .fc-toolbar-title { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.1rem; color:var(--navy-900); }
.fc .fc-button { background:var(--navy-900) !important; border-color:var(--navy-900) !important; font-size:.8rem !important; }
.fc .fc-button:hover { background:var(--navy-700) !important; border-color:var(--navy-700) !important; }
.fc .fc-button-active { background:var(--navy-800) !important; }
.fc .fc-daygrid-day-number { font-family:'IBM Plex Mono',monospace; font-size:.78rem; color:var(--silver-500); }
.fc .fc-day-today { background:#f0f7ff !important; }
.fc .fc-event { border:none !important; border-radius:5px !important; padding:2px 5px !important; font-size:.72rem !important; cursor:pointer !important; }
.fc .fc-event-title { font-weight:600; }

/* ── Modal evento ── */
.event-modal-overlay { display:none; position:fixed; inset:0; background:rgba(7,21,35,.5); z-index:400; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.event-modal-overlay.open { display:flex; }
.event-modal { background:#fff; border-radius:14px; padding:2rem; width:100%; max-width:420px; box-shadow:0 8px 40px rgba(7,21,35,.2); position:relative; }
.event-modal .close { position:absolute; top:1rem; right:1rem; background:var(--silver-100); border:none; width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:.9rem; }
.event-modal h3 { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.05rem; color:var(--navy-900); margin-bottom:.25rem; }
.event-modal .em-sub { font-size:.78rem; color:var(--silver-500); margin-bottom:1.25rem; }
.em-row { display:flex; justify-content:space-between; font-size:.875rem; padding:.4rem 0; border-bottom:1px solid var(--silver-100); }
.em-row:last-child { border-bottom:none; }
.em-row .lbl { color:var(--silver-500); font-size:.78rem; }
.em-row .val { font-weight:600; color:var(--navy-900); text-align:right; }
.em-actions { display:flex; gap:.75rem; margin-top:1.25rem; }
.btn-wa { flex:1; padding:.65rem; background:#25d366; color:#fff; border:none; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; text-decoration:none; text-align:center; }
.btn-nav { flex:1; padding:.65rem; background:var(--navy-900); color:#fff; border:none; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; text-decoration:none; text-align:center; }
.dias-badge { display:inline-block; padding:.2rem .6rem; border-radius:999px; font-size:.72rem; font-weight:700; margin-top:.5rem; }

/* ── Outlook modal ── */
.outlook-modal-overlay { display:none; position:fixed; inset:0; background:rgba(7,21,35,.6); z-index:500; align-items:center; justify-content:center; }
.outlook-modal-overlay.open { display:flex; }
.outlook-modal { background:#fff; border-radius:14px; padding:2rem; width:100%; max-width:520px; box-shadow:0 8px 40px rgba(7,21,35,.2); }
.outlook-modal h3 { font-family:'Manrope',system-ui,sans-serif; font-weight:800; margin-bottom:.75rem; }
.outlook-modal ol { padding-left:1.25rem; font-size:.875rem; line-height:2; color:#444; margin-bottom:1rem; }
.ics-url-box { background:var(--silver-100); border-radius:8px; padding:.75rem 1rem; font-family:'IBM Plex Mono',monospace; font-size:.75rem; color:var(--navy-700); word-break:break-all; margin-bottom:1rem; cursor:pointer; border:1.5px solid var(--silver-300); transition:border-color .2s; }
.ics-url-box:hover { border-color:var(--navy-700); }
.copied-msg { font-size:.78rem; color:var(--green); display:none; margin-bottom:.75rem; }
.outlook-actions { display:flex; gap:.75rem; }

@media (max-width:768px) {
  .topbar { padding:.75rem 1rem; }
  .main { padding:1rem; }
  .calendar-wrap { padding:1rem; }
  .legend { display:none; }
}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">Sanalia &amp; Asociados &mdash; <span>CRM</span></div>
  <div class="topbar-nav">
    <a href="index.php" class="nav-tab">Solicitudes</a>
    <a href="clients.php" class="nav-tab">Clientes</a>
    <a href="calendar.php" class="nav-tab active">Calendario</a>
  </div>
  <div class="topbar-actions">
    <button class="btn-outlook" id="btnOutlook">📅 Suscribir en Outlook</button>
    <form method="POST" action="index.php" style="margin:0">
      <button type="submit" name="logout" class="btn-outline">Salir</button>
    </form>
  </div>
</div>

<div class="main">

  <div class="legend">
    <span style="font-size:.78rem;font-weight:600;color:var(--navy-900)">Leyenda:</span>
    <span class="legend-item"><span class="legend-dot" style="background:var(--green)"></span> Activa (+90 días)</span>
    <span class="legend-item"><span class="legend-dot" style="background:var(--purple)"></span> Vence en 90 días</span>
    <span class="legend-item"><span class="legend-dot" style="background:var(--orange)"></span> Vence en 60 días</span>
    <span class="legend-item"><span class="legend-dot" style="background:var(--red)"></span> Vence en 30 días</span>
    <span class="legend-item"><span class="legend-dot" style="background:#9ca3af"></span> Cancelada</span>
  </div>

  <div class="calendar-wrap">
    <div id="calendar"></div>
  </div>

</div>

<!-- Modal evento -->
<div class="event-modal-overlay" id="eventModal">
  <div class="event-modal">
    <button class="close" id="closeEventModal">✕</button>
    <h3 id="emNombre"></h3>
    <div class="em-sub" id="emSub"></div>
    <div id="emRows"></div>
    <div class="em-actions">
      <a class="btn-wa" id="emWa" href="#" target="_blank">💬 WhatsApp</a>
      <a class="btn-nav" id="emNav" href="#">Ver cliente</a>
    </div>
  </div>
</div>

<!-- Modal Outlook -->
<div class="outlook-modal-overlay" id="outlookModal">
  <div class="outlook-modal">
    <h3>Suscribir calendario en Outlook</h3>
    <ol>
      <li>Abre <strong>Outlook</strong> (web o escritorio)</li>
      <li>Ve a <strong>Calendario</strong> → <strong>Agregar calendario</strong></li>
      <li>Selecciona <strong>"Desde internet"</strong></li>
      <li>Pega la URL de abajo y haz clic en <strong>Importar</strong></li>
    </ol>
    <div class="ics-url-box" id="icsUrlBox" title="Clic para copiar"><?= htmlspecialchars($ics_url) ?></div>
    <div class="copied-msg" id="copiedMsg">✓ URL copiada al portapapeles</div>
    <div class="outlook-actions">
      <button class="btn-secondary" id="btnCopyUrl" style="flex:1;padding:.65rem;background:#fff;color:var(--navy-900);border:1.5px solid var(--silver-300);border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;">Copiar URL</button>
      <button class="btn-nav" style="flex:1;padding:.65rem;border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;background:var(--navy-900);color:#fff" id="btnCloseOutlook">Cerrar</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

  /* ── FullCalendar ── */
  const calEl = document.getElementById('calendar');
  const calendar = new FullCalendar.Calendar(calEl, {
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
    eventClick: function(info) {
      openEventModal(info.event);
    },
    eventDidMount: function(info) {
      info.el.title = info.event.title;
    },
    noEventsContent: 'No hay pólizas con fecha de vencimiento registradas.',
  });
  calendar.render();

  /* ── Modal evento ── */
  const eventModal   = document.getElementById('eventModal');
  const closeEvModal = document.getElementById('closeEventModal');
  closeEvModal.addEventListener('click', function() { eventModal.classList.remove('open'); });
  eventModal.addEventListener('click', function(e) { if (e.target===eventModal) eventModal.classList.remove('open'); });

  function openEventModal(event) {
    const p     = event.extendedProps;
    const dias  = p.dias;
    let diasColor = dias < 0 ? '#dc2626' : dias <= 30 ? '#ef4444' : dias <= 60 ? '#d97706' : dias <= 90 ? '#7c3aed' : '#1a7f4b';
    let diasText  = dias < 0 ? 'Venció hace ' + Math.abs(dias) + ' días' : 'Vence en ' + dias + ' días';

    document.getElementById('emNombre').textContent = p.clientNombre;
    document.getElementById('emSub').innerHTML =
      '<span class="dias-badge" style="background:' + diasColor + '20;color:' + diasColor + '">' + diasText + '</span>';

    const rows = [
      ['Tipo de seguro', p.tipo || '—'],
      ['No. de póliza',  p.numPoliza || '—'],
      ['Aseguradora',    p.aseguradora || '—'],
      ['Vencimiento',    event.startStr],
      ['Prima anual',    p.prima ? 'RD$ ' + parseFloat(p.prima).toLocaleString('es-DO', {minimumFractionDigits:2}) : '—'],
      ['Estado',         p.estado],
    ];
    document.getElementById('emRows').innerHTML = rows.map(function(r) {
      return '<div class="em-row"><span class="lbl">'+r[0]+'</span><span class="val">'+r[1]+'</span></div>';
    }).join('');

    const telClean = (p.clientTel || '').replace(/\D/g,'');
    const waMsg    = encodeURIComponent('Hola ' + p.clientNombre + ', somos Sanalia & Asociados. Le contactamos sobre su póliza de ' + (p.tipo||'seguro') + ' que vence el ' + event.startStr + '. ¿Desea renovarla?');
    document.getElementById('emWa').href  = 'https://wa.me/1' + telClean + '?text=' + waMsg;
    document.getElementById('emNav').href = 'clients.php?open=' + p.clientId;

    eventModal.classList.add('open');
  }

  /* ── Modal Outlook / ICS ── */
  const outlookModal  = document.getElementById('outlookModal');
  const btnOutlook    = document.getElementById('btnOutlook');
  const btnCloseOutlook = document.getElementById('btnCloseOutlook');
  const btnCopyUrl    = document.getElementById('btnCopyUrl');
  const icsUrlBox     = document.getElementById('icsUrlBox');
  const copiedMsg     = document.getElementById('copiedMsg');

  btnOutlook.addEventListener('click', function() { outlookModal.classList.add('open'); });
  btnCloseOutlook.addEventListener('click', function() { outlookModal.classList.remove('open'); });
  outlookModal.addEventListener('click', function(e) { if(e.target===outlookModal) outlookModal.classList.remove('open'); });

  function copyUrl() {
    navigator.clipboard.writeText(icsUrlBox.textContent.trim()).then(function() {
      copiedMsg.style.display = 'block';
      setTimeout(function() { copiedMsg.style.display = 'none'; }, 3000);
    });
  }

  btnCopyUrl.addEventListener('click', copyUrl);
  icsUrlBox.addEventListener('click', copyUrl);

});
</script>
</body>
</html>
