<?php
/**
 * Sanalia CRM — Importador Universal de Excel / CSV
 * SheetJS procesa el archivo en el browser → JSON → PHP → MySQL
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Importar datos — Sanalia CRM</title>
<meta name="robots" content="noindex, nofollow">
<!-- SheetJS CDN -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
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
.brand { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1rem; }
.brand span { color:var(--gold-500); }
.topbar-nav { display:flex; gap:.25rem; }
.nav-tab { padding:.4rem .875rem; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; color:rgba(255,255,255,.6); border:none; background:none; cursor:pointer; }
.nav-tab:hover { color:#fff; background:rgba(255,255,255,.08); }
.nav-tab.active { color:#fff; background:rgba(255,255,255,.15); }
.btn-outline { background:transparent; border:1.5px solid rgba(255,255,255,.3); color:#fff; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; cursor:pointer; }

.main { max-width:1100px; margin:0 auto; padding:2rem 1.5rem; }
.page-title { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.3rem; color:var(--navy-900); margin-bottom:.4rem; }
.page-sub { font-size:.875rem; color:var(--silver-500); margin-bottom:2rem; }

/* Step indicators */
.steps { display:flex; gap:0; margin-bottom:2rem; }
.step { display:flex; align-items:center; gap:.5rem; font-size:.8rem; font-weight:600; color:var(--silver-500); }
.step.active { color:var(--navy-900); }
.step.done { color:var(--green); }
.step-num { width:24px; height:24px; border-radius:50%; background:var(--silver-300); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; flex-shrink:0; }
.step.active .step-num { background:var(--navy-900); }
.step.done .step-num { background:var(--green); }
.step-sep { flex:1; height:2px; background:var(--silver-300); margin:0 .5rem; min-width:20px; }
.step.done + .step-sep { background:var(--green); }

/* Cards */
.card { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(7,21,35,.07); padding:1.75rem; margin-bottom:1.5rem; }
.card h3 { font-family:'Manrope',system-ui,sans-serif; font-weight:700; font-size:1rem; color:var(--navy-900); margin-bottom:1rem; }

/* Drop zone */
.drop-zone { border:2px dashed var(--silver-300); border-radius:12px; padding:3rem 2rem; text-align:center; cursor:pointer; transition:all .2s; }
.drop-zone:hover, .drop-zone.drag-over { border-color:var(--navy-700); background:var(--blue-bg); }
.drop-zone .icon { font-size:2.5rem; margin-bottom:.75rem; }
.drop-zone h4 { font-family:'Manrope',system-ui,sans-serif; font-weight:700; color:var(--navy-900); margin-bottom:.4rem; }
.drop-zone p { font-size:.82rem; color:var(--silver-500); }
.drop-zone input { display:none; }

/* Import type selector */
.type-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
.type-card { border:2px solid var(--silver-300); border-radius:10px; padding:1.1rem; cursor:pointer; transition:all .18s; text-align:center; }
.type-card:hover { border-color:var(--navy-700); }
.type-card.selected { border-color:var(--navy-900); background:var(--navy-950); color:#fff; }
.type-card .type-icon { font-size:1.5rem; margin-bottom:.5rem; }
.type-card .type-name { font-family:'Manrope',system-ui,sans-serif; font-weight:700; font-size:.875rem; }
.type-card .type-desc { font-size:.72rem; color:var(--silver-500); margin-top:.25rem; }
.type-card.selected .type-desc { color:rgba(255,255,255,.6); }

/* Sheet selector */
.sheet-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem; }
.sheet-tab { padding:.35rem .875rem; border-radius:6px; border:1.5px solid var(--silver-300); font-size:.8rem; font-weight:600; cursor:pointer; background:#fff; color:var(--navy-900); }
.sheet-tab.active { background:var(--navy-900); color:#fff; border-color:var(--navy-900); }

/* Column mapping */
.col-map-grid { display:grid; grid-template-columns:1fr 1fr; gap:.875rem; }
.col-map-row { display:flex; align-items:center; gap:.75rem; padding:.625rem .875rem; background:var(--silver-100); border-radius:8px; }
.col-map-row .col-name { font-size:.8rem; font-weight:600; color:var(--navy-900); min-width:160px; flex-shrink:0; }
.col-map-row select { flex:1; padding:.4rem .75rem; border:1.5px solid var(--silver-300); border-radius:6px; font-size:.8rem; background:#fff; outline:none; }
.col-map-row select:focus { border-color:var(--navy-700); }

/* Preview table */
.preview-wrap { overflow-x:auto; border-radius:8px; border:1px solid var(--silver-300); max-height:280px; overflow-y:auto; }
table.preview { width:100%; border-collapse:collapse; font-size:.75rem; }
table.preview thead { background:var(--navy-950); color:#fff; position:sticky; top:0; }
table.preview th { padding:.5rem .75rem; text-align:left; white-space:nowrap; font-weight:500; }
table.preview td { padding:.45rem .75rem; border-bottom:1px solid var(--silver-100); white-space:nowrap; max-width:200px; overflow:hidden; text-overflow:ellipsis; }
table.preview tr:hover td { background:var(--silver-100); }

/* Progress / results */
.progress-bar-wrap { background:var(--silver-300); border-radius:999px; height:8px; margin:1rem 0; }
.progress-bar-fill { background:var(--navy-700); border-radius:999px; height:8px; width:0%; transition:width .3s; }
.result-box { padding:1rem 1.25rem; border-radius:10px; font-size:.875rem; margin-top:1rem; display:none; }
.result-box.ok  { background:var(--green-bg);  color:var(--green);  border:1px solid var(--green); }
.result-box.err { background:var(--red-bg);    color:var(--red);    border:1px solid var(--red); }
.result-box.warn { background:var(--orange-bg); color:var(--orange); border:1px solid var(--orange); }

/* Buttons */
.btn-primary { background:var(--navy-900); color:#fff; border:none; padding:.7rem 1.5rem; border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; }
.btn-primary:hover { background:var(--navy-700); }
.btn-primary:disabled { opacity:.5; cursor:not-allowed; }
.btn-secondary { background:#fff; color:var(--navy-900); border:1.5px solid var(--silver-300); padding:.7rem 1.25rem; border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; }
.btn-secondary:hover { border-color:var(--navy-700); }
.btn-gold { background:var(--gold-500); color:var(--navy-950); border:none; padding:.7rem 1.5rem; border-radius:8px; font-size:.875rem; font-weight:700; cursor:pointer; }
.btn-gold:hover { background:var(--gold-600); }
.btn-row { display:flex; gap:.75rem; align-items:center; margin-top:1.25rem; }

/* Options */
.option-row { display:flex; align-items:center; gap:.75rem; padding:.875rem; background:var(--silver-100); border-radius:8px; margin-bottom:.625rem; }
.option-row label { font-size:.875rem; font-weight:600; flex:1; }
.option-row small { font-size:.75rem; color:var(--silver-500); }
input[type="checkbox"] { width:18px; height:18px; accent-color:var(--navy-900); flex-shrink:0; }

/* Hidden */
.hidden { display:none !important; }

/* Info box */
.info-box { background:var(--blue-bg); border:1px solid var(--blue); border-radius:8px; padding:.875rem 1rem; font-size:.82rem; color:var(--blue); margin-bottom:1rem; }

/* Errors list */
.errors-list { max-height:200px; overflow-y:auto; font-size:.78rem; }
.errors-list li { padding:.3rem 0; border-bottom:1px solid rgba(220,38,38,.15); }

@media (max-width:768px) {
  .topbar { padding:.75rem 1rem; }
  .main { padding:1.25rem 1rem; }
  .type-grid { grid-template-columns:1fr; }
  .col-map-grid { grid-template-columns:1fr; }
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
    <a href="finances.php"  class="nav-tab">Finanzas</a>
    <a href="calendar.php"  class="nav-tab">Calendario</a>
    <a href="import.php"    class="nav-tab active">Importar</a>
  </div>
  <div class="topbar-actions">
    <form method="POST" action="index.php" style="margin:0">
      <button type="submit" name="logout" class="btn-outline">Salir</button>
    </form>
  </div>
</div>

<div class="main">

<div class="page-title">Importador de datos</div>
<div class="page-sub">Carga archivos Excel (.xlsx) o CSV directamente desde los archivos de Sanalia. El sistema detecta las columnas automáticamente.</div>

<!-- Steps -->
<div class="steps" id="stepsBar">
  <div class="step active" id="step1"><div class="step-num">1</div> Cargar archivo</div>
  <div class="step-sep"></div>
  <div class="step" id="step2"><div class="step-num">2</div> Tipo de datos</div>
  <div class="step-sep"></div>
  <div class="step" id="step3"><div class="step-num">3</div> Columnas</div>
  <div class="step-sep"></div>
  <div class="step" id="step4"><div class="step-num">4</div> Importar</div>
</div>

<!-- ══ PASO 1: Cargar archivo ══ -->
<div class="card" id="panelFile">
  <h3>1. Selecciona el archivo</h3>
  <div class="info-box">
    Soporta: <strong>Excel (.xlsx, .xls)</strong> y <strong>CSV</strong>.
    Compatible con los archivos de CxC de Alberto, CTAS POR COBRAR, hojas de renovaciones y cualquier listado de clientes.
  </div>
  <div class="drop-zone" id="dropZone">
    <div class="icon">📂</div>
    <h4>Arrastra tu archivo aquí</h4>
    <p>o haz clic para seleccionar · Excel .xlsx / .xls / CSV</p>
    <input type="file" id="fileInput" accept=".xlsx,.xls,.csv,.xlsb">
  </div>
  <div id="fileInfo" class="hidden" style="margin-top:1rem;padding:.875rem;background:var(--green-bg);border-radius:8px;font-size:.875rem;color:var(--green)"></div>
</div>

<!-- ══ PASO 2: Tipo de importación ══ -->
<div class="card hidden" id="panelType">
  <h3>2. ¿Qué quieres importar?</h3>

  <!-- Sheet selector (si hay múltiples hojas) -->
  <div id="sheetSelectorWrap" class="hidden" style="margin-bottom:1.25rem">
    <div style="font-size:.8rem;font-weight:600;color:var(--silver-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem">Hoja del archivo</div>
    <div class="sheet-tabs" id="sheetTabs"></div>
  </div>

  <div class="type-grid">
    <div class="type-card" data-type="cxc">
      <div class="type-icon">💰</div>
      <div class="type-name">Cuentas por cobrar</div>
      <div class="type-desc">Pagos de primas, renovaciones, cuotas pendientes</div>
    </div>
    <div class="type-card" data-type="clientes">
      <div class="type-icon">👥</div>
      <div class="type-name">Clientes + Pólizas</div>
      <div class="type-desc">Listado de asegurados con datos de contacto y póliza</div>
    </div>
    <div class="type-card" data-type="renovaciones">
      <div class="type-icon">🔄</div>
      <div class="type-name">Renovaciones / Cuotas</div>
      <div class="type-desc">Planes de pago con cuotas y fechas de vencimiento</div>
    </div>
  </div>

  <div id="previewWrap" class="hidden">
    <div style="font-size:.8rem;font-weight:600;color:var(--silver-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem">Vista previa (primeras 8 filas)</div>
    <div class="preview-wrap">
      <table class="preview" id="previewTable"><thead></thead><tbody></tbody></table>
    </div>
    <div style="font-size:.75rem;color:var(--silver-500);margin-top:.4rem" id="totalRowsInfo"></div>
  </div>

  <div class="btn-row">
    <button class="btn-secondary" onclick="goStep(1)">← Atrás</button>
    <button class="btn-primary" id="btnToStep3" disabled>Siguiente →</button>
  </div>
</div>

<!-- ══ PASO 3: Mapeo de columnas ══ -->
<div class="card hidden" id="panelMap">
  <h3>3. Confirma el mapeo de columnas</h3>
  <p style="font-size:.82rem;color:var(--silver-500);margin-bottom:1.25rem">El sistema detectó las columnas automáticamente. Ajusta si alguna está incorrecta.</p>

  <div id="colMapContainer"></div>

  <!-- Opciones de importación -->
  <div style="margin-top:1.5rem">
    <div style="font-size:.8rem;font-weight:600;color:var(--silver-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.625rem">Opciones</div>
    <div class="option-row">
      <input type="checkbox" id="optSkipDupes" checked>
      <label for="optSkipDupes">Omitir duplicados <small>(detecta por nombre + póliza)</small></label>
    </div>
    <div class="option-row">
      <input type="checkbox" id="optCreateClients" checked>
      <label for="optCreateClients">Crear clientes automáticamente <small>(si no existen en el CRM)</small></label>
    </div>
    <div class="option-row" id="optCuotasRow" style="display:none">
      <input type="checkbox" id="optCuotas" checked>
      <label for="optCuotas">Generar una CxC por cuota <small>(en renovaciones con múltiples cuotas)</small></label>
    </div>
  </div>

  <div class="btn-row">
    <button class="btn-secondary" onclick="goStep(2)">← Atrás</button>
    <button class="btn-gold" id="btnImport">⬆ Importar ahora</button>
  </div>
</div>

<!-- ══ PASO 4: Resultado ══ -->
<div class="card hidden" id="panelResult">
  <h3>4. Resultado de la importación</h3>
  <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progressBar"></div></div>
  <div id="progressMsg" style="font-size:.82rem;color:var(--silver-500);text-align:center;margin-top:.4rem">Procesando…</div>
  <div class="result-box" id="resultBox"></div>
  <div id="errorsWrap" class="hidden" style="margin-top:1rem">
    <div style="font-size:.8rem;font-weight:600;color:var(--red);margin-bottom:.4rem">Filas con error:</div>
    <ul class="errors-list" id="errorsList"></ul>
  </div>
  <div class="btn-row" id="resultActions" style="display:none">
    <a href="clients.php" class="btn-primary">Ver Clientes</a>
    <a href="finances.php" class="btn-primary">Ver Finanzas</a>
    <button class="btn-secondary" onclick="resetAll()">Nueva importación</button>
  </div>
</div>

</div><!-- /.main -->

<script>
(function(){
'use strict';

/* ══ Estado global ══ */
let workbook = null;
let activeSheet = null;
let sheetData = [];      // Array de arrays (todas las filas)
let importType = null;
let colMapping = {};
let totalRows = 0;

/* ══ Campos disponibles por tipo ══ */
const FIELDS = {
  cxc: [
    { key:'nombre',           label:'Nombre del cliente *' },
    { key:'poliza',           label:'No. de póliza' },
    { key:'monto',            label:'Monto (RD$)' },
    { key:'aseguradora',      label:'Aseguradora / ARS' },
    { key:'fecha',            label:'Fecha' },
    { key:'forma_pago',       label:'Forma de pago / Estatus' },
    { key:'telefono',         label:'Teléfono' },
    { key:'correo',           label:'Correo electrónico' },
    { key:'vendedor',         label:'Vendedor / Referencia' },
    { key:'ignore',           label:'— Ignorar columna —' },
  ],
  clientes: [
    { key:'nombre',           label:'Nombre completo *' },
    { key:'cedula',           label:'Cédula / RNC' },
    { key:'telefono',         label:'Teléfono' },
    { key:'correo',           label:'Correo electrónico' },
    { key:'direccion',        label:'Dirección' },
    { key:'tipo_seguro',      label:'Tipo de seguro' },
    { key:'aseguradora',      label:'Aseguradora' },
    { key:'poliza',           label:'No. de póliza' },
    { key:'fecha_inicio',     label:'Fecha inicio póliza' },
    { key:'fecha_vencimiento',label:'Fecha vencimiento' },
    { key:'prima',            label:'Prima anual (RD$)' },
    { key:'ignore',           label:'— Ignorar columna —' },
  ],
  renovaciones: [
    { key:'nombre',           label:'Nombre del cliente *' },
    { key:'poliza',           label:'No. de póliza' },
    { key:'aseguradora',      label:'Aseguradora' },
    { key:'monto_cuota',      label:'Monto por cuota' },
    { key:'cuota_label',      label:'Etiqueta cuota (Cuota 1, Total…)' },
    { key:'fecha_pago',       label:'Fecha de pago / vencimiento' },
    { key:'ignore',           label:'— Ignorar columna —' },
  ],
};

/* ══ Auto-detección de columnas ══ */
const COL_HINTS = {
  nombre:           ['nombre','name','cliente','asegurado','titular'],
  cedula:           ['cedula','cédula','rnc','id','pasaporte'],
  poliza:           ['poliza','póliza','no.poliza','numero','policy'],
  monto:            ['monto','importe','amount','valor','prima'],
  monto_cuota:      ['monto','importe','cuota'],
  aseguradora:      ['ars','aseguradora','compañia','compañía','empresa','insurer'],
  fecha:            ['fecha','date','pago','cobro'],
  fecha_inicio:     ['inicio','desde','vigencia'],
  fecha_vencimiento:['vencimiento','vence','expira','hasta'],
  forma_pago:       ['forma','estatus','status','pago','tipo'],
  telefono:         ['telefono','teléfono','tel','movil','celular','phone'],
  correo:           ['correo','email','mail'],
  vendedor:         ['vendedor','referencia','agente','asesor'],
  tipo_seguro:      ['tipo','seguro','linea'],
  prima:            ['prima','anual'],
  cuota_label:      ['cuota','label','numero_cuota'],
};

function detectCol(headerStr, type) {
  const h = String(headerStr||'').toLowerCase().replace(/[^a-záéíóúüñ0-9]/gi,' ').trim();
  const fields = FIELDS[type];
  for (const hint of Object.entries(COL_HINTS)) {
    const [key, words] = hint;
    if (!fields.find(f => f.key === key)) continue;
    if (words.some(w => h.includes(w))) return key;
  }
  return 'ignore';
}

/* ══ Helpers ══ */
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }

function parseDate(v) {
  if (!v) return null;
  if (v instanceof Date) return v.toISOString().substring(0,10);
  if (typeof v === 'number') {
    // Excel serial date
    const d = new Date(Math.round((v - 25569) * 86400 * 1000));
    return d.toISOString().substring(0,10);
  }
  const s = String(v).trim();
  // dd/mm/yyyy or dd/mm/yy
  const m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
  if (m) {
    const y = m[3].length === 2 ? '20'+m[3] : m[3];
    return `${y}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
  }
  // yyyy-mm-dd
  if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.substring(0,10);
  return null;
}

function parseMonto(v) {
  if (!v) return null;
  if (typeof v === 'number') return v;
  const s = String(v).replace(/[RD$\s,]/g,'').replace(/\./,'.');
  const n = parseFloat(s);
  return isNaN(n) ? null : n;
}

function goStep(n) {
  ['panelFile','panelType','panelMap','panelResult'].forEach((id,i) => {
    document.getElementById(id).classList.toggle('hidden', i !== n-1);
  });
  ['step1','step2','step3','step4'].forEach((id,i) => {
    const el = document.getElementById(id);
    el.classList.toggle('active', i === n-1);
    el.classList.toggle('done', i < n-1);
  });
}

function resetAll() {
  workbook = null; activeSheet = null; sheetData = []; importType = null; colMapping = {};
  document.getElementById('fileInput').value = '';
  document.getElementById('fileInfo').classList.add('hidden');
  document.getElementById('progressBar').style.width = '0%';
  document.getElementById('resultBox').style.display = 'none';
  document.getElementById('resultBox').className = 'result-box';
  document.getElementById('errorsWrap').classList.add('hidden');
  document.getElementById('errorsList').innerHTML = '';
  document.getElementById('resultActions').style.display = 'none';
  document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
  document.getElementById('btnToStep3').disabled = true;
  goStep(1);
}

/* ══ 1. Cargar archivo ══ */
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('drag-over');
  if (e.dataTransfer.files[0]) loadFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => { if (fileInput.files[0]) loadFile(fileInput.files[0]); });

function loadFile(file) {
  const name = file.name;
  const reader = new FileReader();
  reader.onload = function(e) {
    try {
      const data = new Uint8Array(e.target.result);
      workbook = XLSX.read(data, { type:'array', cellDates:false });

      document.getElementById('fileInfo').classList.remove('hidden');
      document.getElementById('fileInfo').innerHTML =
        `✓ <strong>${esc(name)}</strong> — ${workbook.SheetNames.length} hoja(s): <em>${workbook.SheetNames.join(', ')}</em>`;

      // Precarga primera hoja
      loadSheet(workbook.SheetNames[0]);

      // Sheet tabs
      const sheetTabs = document.getElementById('sheetTabs');
      sheetTabs.innerHTML = '';
      if (workbook.SheetNames.length > 1) {
        document.getElementById('sheetSelectorWrap').classList.remove('hidden');
        workbook.SheetNames.forEach(function(sName, idx) {
          const btn = document.createElement('button');
          btn.className = 'sheet-tab' + (idx===0?' active':'');
          btn.textContent = sName;
          btn.dataset.sheet = sName;
          btn.addEventListener('click', function() {
            document.querySelectorAll('.sheet-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadSheet(this.dataset.sheet);
          });
          sheetTabs.appendChild(btn);
        });
      } else {
        document.getElementById('sheetSelectorWrap').classList.add('hidden');
      }

      goStep(2);
    } catch(err) {
      alert('Error leyendo el archivo: ' + err.message);
    }
  };
  reader.readAsArrayBuffer(file);
}

function loadSheet(sheetName) {
  activeSheet = sheetName;
  const ws = workbook.Sheets[sheetName];
  sheetData = XLSX.utils.sheet_to_json(ws, { header:1, defval:null, raw:true });

  // Filtrar filas vacías
  sheetData = sheetData.filter(row => row.some(v => v !== null && v !== ''));
  totalRows = sheetData.length;

  // Detectar la fila de encabezados (buscar la primera que tiene texto en múltiples columnas)
  renderPreview();
  if (importType) buildColMap();
}

function getHeaderRow() {
  for (let i = 0; i < Math.min(5, sheetData.length); i++) {
    const row = sheetData[i];
    const textCols = row.filter(v => v && typeof v === 'string' && v.trim().length > 1);
    if (textCols.length >= 2) return { idx: i, row };
  }
  return { idx: 0, row: sheetData[0] || [] };
}

function renderPreview() {
  const previewWrap = document.getElementById('previewWrap');
  if (!sheetData.length) { previewWrap.classList.add('hidden'); return; }

  previewWrap.classList.remove('hidden');
  const { idx, row: headerRow } = getHeaderRow();
  const headers = headerRow.map((h,i) => h || 'Col '+(i+1));

  const thead = document.querySelector('#previewTable thead');
  const tbody = document.querySelector('#previewTable tbody');
  thead.innerHTML = '<tr>' + headers.map(h => `<th>${esc(String(h))}</th>`).join('') + '</tr>';
  tbody.innerHTML = '';

  const dataRows = sheetData.slice(idx+1, idx+9);
  dataRows.forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = headers.map((_,i) => `<td title="${esc(String(row[i]||''))}">${esc(String(row[i]||''))}</td>`).join('');
    tbody.appendChild(tr);
  });

  document.getElementById('totalRowsInfo').textContent =
    `Total: ${totalRows - idx - 1} filas de datos (${totalRows} con encabezado)`;
}

/* ══ 2. Tipo de importación ══ */
document.querySelectorAll('.type-card').forEach(card => {
  card.addEventListener('click', function() {
    document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
    this.classList.add('selected');
    importType = this.dataset.type;
    document.getElementById('btnToStep3').disabled = false;
    document.getElementById('optCuotasRow').style.display = importType === 'renovaciones' ? '' : 'none';
  });
});

document.getElementById('btnToStep3').addEventListener('click', function() {
  if (!importType) return;
  buildColMap();
  goStep(3);
});

/* ══ 3. Mapeo de columnas ══ */
function buildColMap() {
  const { idx, row: headerRow } = getHeaderRow();
  const fields = FIELDS[importType];
  const container = document.getElementById('colMapContainer');

  const fieldOptions = fields.map(f => `<option value="${f.key}">${f.label}</option>`).join('');

  let html = '<div class="col-map-grid">';
  headerRow.forEach(function(h, colIdx) {
    if (h === null || h === '') return;
    const detected = detectCol(h, importType);
    html += `<div class="col-map-row">
      <div class="col-name">${esc(String(h))}</div>
      <select data-col="${colIdx}">
        ${fields.map(f => `<option value="${f.key}"${f.key===detected?' selected':''}>${f.label}</option>`).join('')}
      </select>
    </div>`;
  });
  html += '</div>';
  container.innerHTML = html;
}

/* ══ 4. Importar ══ */
document.getElementById('btnImport').addEventListener('click', runImport);

async function runImport() {
  const btn = document.getElementById('btnImport');
  btn.disabled = true;
  btn.textContent = 'Importando…';

  // Leer mapeo actual
  colMapping = {};
  document.querySelectorAll('#colMapContainer select').forEach(sel => {
    const col = parseInt(sel.dataset.col);
    const key = sel.value;
    if (key !== 'ignore') colMapping[col] = key;
  });

  const skipDupes   = document.getElementById('optSkipDupes').checked;
  const createClients = document.getElementById('optCreateClients').checked;

  goStep(4);

  const { idx } = getHeaderRow();
  const dataRows = sheetData.slice(idx + 1).filter(r => r.some(v => v !== null && v !== ''));

  const BATCH = 50;
  let imported = 0, skipped = 0, errors = [];
  const errorsList = document.getElementById('errorsList');

  document.getElementById('progressMsg').textContent = `Procesando ${dataRows.length} filas…`;

  for (let i = 0; i < dataRows.length; i += BATCH) {
    const batch = dataRows.slice(i, i + BATCH).map(row => {
      const record = {};
      Object.entries(colMapping).forEach(([col, key]) => {
        record[key] = row[parseInt(col)];
      });
      return record;
    }).filter(r => {
      // Filtrar filas sin nombre o que sean totales/subtotales
      const n = String(r.nombre || r.cliente || '').toLowerCase().trim();
      return n && n !== 'nombre' && n !== 'nombre /asegurado' && n !== 'total' && n !== 'subtotal' && n.length > 1;
    });

    if (!batch.length) continue;

    const fd = new FormData();
    fd.append('rows', JSON.stringify(batch));
    fd.append('type', importType);
    fd.append('skip_dupes', skipDupes ? '1' : '0');
    fd.append('create_clients', createClients ? '1' : '0');

    try {
      const res = await fetch('api.php?action=bulk_import', { method:'POST', body:fd });
      const r = await res.json();
      imported += r.imported || 0;
      skipped  += r.skipped  || 0;
      if (r.errors && r.errors.length) {
        r.errors.forEach(e => {
          errors.push(e);
          const li = document.createElement('li');
          li.textContent = e;
          errorsList.appendChild(li);
        });
      }
    } catch(e) {
      errors.push('Error de red en lote ' + (i/BATCH+1));
    }

    const pct = Math.round(((i + BATCH) / dataRows.length) * 100);
    document.getElementById('progressBar').style.width = Math.min(pct,100) + '%';
    document.getElementById('progressMsg').textContent =
      `Procesadas ${Math.min(i+BATCH, dataRows.length)} de ${dataRows.length} filas…`;

    // Pequeña pausa para no saturar el servidor
    await new Promise(r => setTimeout(r, 80));
  }

  // Resultado final
  document.getElementById('progressBar').style.width = '100%';
  document.getElementById('progressMsg').textContent = 'Completado.';

  const resultBox = document.getElementById('resultBox');
  resultBox.style.display = 'block';

  if (errors.length === 0) {
    resultBox.className = 'result-box ok';
    resultBox.innerHTML = `✓ <strong>${imported} registros importados</strong>${skipped > 0 ? ` · ${skipped} omitidos (duplicados)` : ''}.`;
  } else if (imported > 0) {
    resultBox.className = 'result-box warn';
    resultBox.innerHTML = `⚠ <strong>${imported} importados</strong>, ${skipped} omitidos, <strong>${errors.length} con error</strong>. Revisa la lista de errores.`;
    document.getElementById('errorsWrap').classList.remove('hidden');
  } else {
    resultBox.className = 'result-box err';
    resultBox.innerHTML = `✗ No se importó ningún registro. ${errors.length} errores. Verifica el mapeo de columnas.`;
    document.getElementById('errorsWrap').classList.remove('hidden');
  }

  document.getElementById('resultActions').style.display = 'flex';
  btn.disabled = false;
  btn.textContent = '⬆ Importar ahora';
}

})();
</script>
</body>
</html>
