<?php
/**
 * Sanalia CRM — Importador de Prospectos / Leads
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Importar prospectos — Sanalia CRM</title>
<meta name="robots" content="noindex, nofollow">
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
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
  --blue:#1d4ed8; --blue-bg:#dbeafe;
}
body { font-family:'Inter',system-ui,sans-serif; background:var(--silver-100); color:var(--ink); min-height:100vh; }

.topbar { background:var(--navy-900); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:.875rem 2rem; position:sticky; top:0; z-index:100; gap:1rem; flex-wrap:wrap; }
.brand { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1rem; }
.brand span { color:var(--gold-500); }
.topbar-nav { display:flex; gap:.25rem; }
.nav-tab { padding:.4rem .875rem; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; color:rgba(255,255,255,.6); border:none; background:none; cursor:pointer; white-space:nowrap; }
.nav-tab:hover { color:#fff; background:rgba(255,255,255,.08); }
.nav-tab.active { color:#fff; background:rgba(255,255,255,.15); }
.btn-outline { background:transparent; border:1.5px solid rgba(255,255,255,.3); color:#fff; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; cursor:pointer; }

.main { max-width:960px; margin:0 auto; padding:2rem 1.5rem; }

/* ── Page header ── */
.page-header { margin-bottom:2rem; }
.page-title { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.35rem; color:var(--navy-900); margin-bottom:.35rem; }
.page-sub { font-size:.875rem; color:var(--silver-500); }

/* ── Steps ── */
.steps { display:flex; align-items:center; gap:0; margin-bottom:2rem; }
.step { display:flex; align-items:center; gap:.5rem; font-size:.8rem; font-weight:600; color:var(--silver-500); white-space:nowrap; }
.step.active { color:var(--navy-900); }
.step.done   { color:var(--green); }
.step-num { width:26px; height:26px; border-radius:50%; background:var(--silver-300); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; flex-shrink:0; }
.step.active .step-num { background:var(--navy-900); }
.step.done   .step-num { background:var(--green); }
.step-sep { flex:1; height:2px; background:var(--silver-300); min-width:24px; }
.step.done + .step-sep { background:var(--green); }

/* ── Card ── */
.card { background:#fff; border-radius:14px; box-shadow:0 1px 8px rgba(7,21,35,.07); padding:2rem; margin-bottom:1.5rem; }
.card-title { font-family:'Manrope',system-ui,sans-serif; font-weight:700; font-size:1.05rem; color:var(--navy-900); margin-bottom:1.25rem; display:flex; align-items:center; gap:.5rem; }

/* ── Template download ── */
.template-strip { background:var(--navy-950); border-radius:10px; padding:1rem 1.25rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
.template-strip p { font-size:.84rem; color:rgba(255,255,255,.75); }
.template-strip p strong { color:#fff; }
.btn-template { background:var(--gold-500); color:var(--navy-950); border:none; padding:.5rem 1.1rem; border-radius:8px; font-size:.82rem; font-weight:700; cursor:pointer; white-space:nowrap; }
.btn-template:hover { background:var(--gold-600); }

/* ── Drop zone ── */
.drop-zone { border:2.5px dashed var(--silver-300); border-radius:14px; padding:3rem 2rem; text-align:center; cursor:pointer; transition:all .2s; position:relative; }
.drop-zone:hover, .drop-zone.drag-over { border-color:var(--navy-700); background:#f0f4ff; }
.drop-zone .dz-icon { font-size:2.75rem; margin-bottom:.875rem; display:block; }
.drop-zone h4 { font-family:'Manrope',system-ui,sans-serif; font-weight:700; font-size:1.05rem; color:var(--navy-900); margin-bottom:.4rem; }
.drop-zone p { font-size:.82rem; color:var(--silver-500); }
.drop-zone input[type="file"] { display:none; }
.file-loaded { background:var(--green-bg); border-radius:10px; padding:.875rem 1.1rem; margin-top:1rem; font-size:.875rem; color:var(--green); display:none; align-items:center; gap:.625rem; }
.file-loaded.show { display:flex; }

/* ── Sheet selector ── */
.sheet-row { margin-bottom:1.25rem; display:none; }
.sheet-row label { font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--silver-500); display:block; margin-bottom:.4rem; }
.sheet-tabs { display:flex; gap:.5rem; flex-wrap:wrap; }
.sheet-tab { padding:.35rem .875rem; border-radius:6px; border:1.5px solid var(--silver-300); font-size:.8rem; font-weight:600; cursor:pointer; background:#fff; color:var(--navy-900); transition:all .15s; }
.sheet-tab.active { background:var(--navy-900); color:#fff; border-color:var(--navy-900); }

/* ── Column mapping ── */
.col-map-intro { font-size:.84rem; color:var(--silver-500); margin-bottom:1.25rem; line-height:1.6; }
.col-map-grid { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:1.5rem; }
.col-map-row { background:var(--silver-100); border-radius:10px; padding:.875rem 1rem; display:flex; align-items:center; gap:.875rem; }
.col-map-row .col-from { font-size:.78rem; font-weight:600; color:var(--navy-900); min-width:140px; flex-shrink:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.col-map-row .col-arrow { color:var(--silver-500); font-size:.9rem; flex-shrink:0; }
.col-map-row select { flex:1; padding:.4rem .625rem; border:1.5px solid var(--silver-300); border-radius:7px; font-size:.8rem; background:#fff; outline:none; cursor:pointer; min-width:0; }
.col-map-row select:focus { border-color:var(--navy-700); }
.col-map-row select.mapped { border-color:var(--green); background:var(--green-bg); }

/* ── Preview table ── */
.preview-section { margin-top:1.5rem; }
.preview-label { font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--silver-500); margin-bottom:.5rem; display:flex; justify-content:space-between; align-items:center; }
.preview-wrap { overflow-x:auto; border:1px solid var(--silver-300); border-radius:10px; max-height:300px; overflow-y:auto; }
table.prev { width:100%; border-collapse:collapse; font-size:.775rem; }
table.prev thead { background:var(--navy-950); color:#fff; position:sticky; top:0; }
table.prev th { padding:.55rem .875rem; text-align:left; font-weight:500; white-space:nowrap; }
table.prev td { padding:.5rem .875rem; border-bottom:1px solid var(--silver-100); white-space:nowrap; max-width:180px; overflow:hidden; text-overflow:ellipsis; }
table.prev tbody tr:hover td { background:var(--silver-100); }
.preview-meta { font-size:.72rem; color:var(--silver-500); margin-top:.4rem; }

/* ── Import options ── */
.options-grid { display:grid; gap:.625rem; margin-bottom:1.75rem; }
.opt-row { display:flex; align-items:flex-start; gap:.875rem; background:var(--silver-100); border-radius:10px; padding:.875rem 1rem; }
.opt-row input[type="checkbox"] { width:18px; height:18px; accent-color:var(--navy-900); flex-shrink:0; margin-top:.1rem; }
.opt-row .opt-text { flex:1; }
.opt-row .opt-label { font-size:.875rem; font-weight:600; color:var(--navy-900); display:block; margin-bottom:.15rem; }
.opt-row .opt-desc  { font-size:.75rem; color:var(--silver-500); }

/* ── fuente selector ── */
.fuente-row { background:var(--silver-100); border-radius:10px; padding:.875rem 1rem; display:flex; align-items:center; gap:.875rem; margin-bottom:.625rem; }
.fuente-row label { font-size:.875rem; font-weight:600; flex-shrink:0; }
.fuente-row select { flex:1; padding:.4rem .625rem; border:1.5px solid var(--silver-300); border-radius:7px; font-size:.84rem; background:#fff; outline:none; max-width:220px; }

/* ── Progress ── */
.progress-wrap { margin:1.25rem 0; }
.progress-bar-bg { background:var(--silver-300); border-radius:999px; height:10px; margin-bottom:.5rem; overflow:hidden; }
.progress-bar-fill { background:var(--navy-700); border-radius:999px; height:10px; width:0%; transition:width .3s; }
.progress-msg { font-size:.82rem; color:var(--silver-500); text-align:center; }

/* ── Result box ── */
.result-box { border-radius:10px; padding:1rem 1.25rem; font-size:.875rem; display:none; margin-bottom:1rem; }
.result-box.ok   { background:var(--green-bg);  color:var(--green);  border:1.5px solid var(--green); }
.result-box.warn { background:var(--orange-bg); color:var(--orange); border:1.5px solid var(--orange); }
.result-box.err  { background:var(--red-bg);    color:var(--red);    border:1.5px solid var(--red); }
.errors-list { max-height:160px; overflow-y:auto; font-size:.78rem; margin-top:.75rem; padding-left:1.1rem; }
.errors-list li { padding:.25rem 0; }

/* ── Buttons ── */
.btn-primary { background:var(--navy-900); color:#fff; border:none; padding:.7rem 1.5rem; border-radius:9px; font-size:.875rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; transition:background .15s; }
.btn-primary:hover { background:var(--navy-700); }
.btn-primary:disabled { opacity:.5; cursor:not-allowed; }
.btn-secondary { background:#fff; color:var(--navy-900); border:1.5px solid var(--silver-300); padding:.7rem 1.25rem; border-radius:9px; font-size:.875rem; font-weight:600; cursor:pointer; transition:border-color .15s; }
.btn-secondary:hover { border-color:var(--navy-700); }
.btn-gold { background:var(--gold-500); color:var(--navy-950); border:none; padding:.7rem 1.5rem; border-radius:9px; font-size:.875rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; transition:background .15s; }
.btn-gold:hover { background:var(--gold-600); }
.btn-gold:disabled { opacity:.5; cursor:not-allowed; }
.btn-row { display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; }

.hidden { display:none !important; }

@media (max-width:768px) {
  .topbar { padding:.75rem 1rem; }
  .main { padding:1.25rem 1rem; }
  .col-map-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">Sanalia &amp; Asociados &mdash; <span>CRM</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="nav-tab">Dashboard</a>
    <a href="leads.php"     class="nav-tab">Contactos</a>
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

<div class="page-header">
  <div class="page-title">Importar prospectos</div>
  <div class="page-sub">Carga una lista de contactos desde Excel o CSV. Los registros entran en el pipeline como leads nuevos.</div>
</div>

<!-- Steps -->
<div class="steps" id="stepsBar">
  <div class="step active" id="step1"><div class="step-num">1</div> Cargar archivo</div>
  <div class="step-sep"></div>
  <div class="step" id="step2"><div class="step-num">2</div> Mapear columnas</div>
  <div class="step-sep"></div>
  <div class="step" id="step3"><div class="step-num">3</div> Importar</div>
</div>

<!-- ══ PASO 1: Archivo ══ -->
<div id="panelFile">

  <!-- Template download -->
  <div class="template-strip">
    <p><strong>¿No tienes el formato?</strong> Descarga la plantilla Excel con las columnas correctas y rellena tus datos.</p>
    <button class="btn-template" onclick="downloadTemplate()">⬇ Descargar plantilla</button>
  </div>

  <div class="card">
    <div class="card-title">📂 Selecciona tu archivo</div>
    <div class="drop-zone" id="dropZone">
      <span class="dz-icon">📊</span>
      <h4>Arrastra el archivo aquí</h4>
      <p>Excel (.xlsx / .xls) o CSV — cualquier formato con nombres y teléfonos</p>
      <input type="file" id="fileInput" accept=".xlsx,.xls,.csv">
    </div>
    <div class="file-loaded" id="fileInfo">
      <span style="font-size:1.25rem">✅</span>
      <span id="fileInfoText"></span>
    </div>
  </div>

</div><!-- /#panelFile -->

<!-- ══ PASO 2: Mapeo ══ -->
<div id="panelMap" class="hidden">
  <div class="card">
    <div class="card-title">🔗 Mapea las columnas</div>
    <p class="col-map-intro">El sistema detectó las columnas de tu archivo automáticamente. Asigna cada una al campo correspondiente del CRM. Las columnas en verde ya están detectadas.</p>

    <!-- Sheet selector -->
    <div class="sheet-row" id="sheetRow">
      <label>Hoja del archivo</label>
      <div class="sheet-tabs" id="sheetTabs"></div>
    </div>

    <!-- Column mapping -->
    <div class="col-map-grid" id="colMapGrid"></div>

    <!-- Preview -->
    <div class="preview-section">
      <div class="preview-label">
        <span>Vista previa — primeras 8 filas</span>
        <span id="totalRowsInfo" style="color:var(--navy-700);font-weight:700"></span>
      </div>
      <div class="preview-wrap">
        <table class="prev" id="previewTable">
          <thead id="previewHead"></thead>
          <tbody id="previewBody"></tbody>
        </table>
      </div>
    </div>

    <div class="btn-row" style="margin-top:1.5rem">
      <button class="btn-secondary" onclick="goStep(1)">← Atrás</button>
      <button class="btn-primary" onclick="goStep(3)">Ver opciones →</button>
    </div>
  </div>
</div><!-- /#panelMap -->

<!-- ══ PASO 3: Opciones + resultado ══ -->
<div id="panelImport" class="hidden">
  <div class="card">
    <div class="card-title">⚙️ Opciones de importación</div>

    <!-- Fuente por defecto -->
    <div class="fuente-row">
      <label>Fuente de este lote:</label>
      <select id="batchFuente">
        <option value="web">Web</option>
        <option value="facebook">Facebook</option>
        <option value="instagram">Instagram</option>
        <option value="whatsapp">WhatsApp</option>
        <option value="referido">Referido</option>
        <option value="otro">Otro</option>
      </select>
    </div>

    <div class="options-grid">
      <div class="opt-row">
        <input type="checkbox" id="optSkipDupes" checked>
        <div class="opt-text">
          <span class="opt-label">Omitir duplicados</span>
          <span class="opt-desc">Si ya existe un lead con el mismo nombre y teléfono, se saltea.</span>
        </div>
      </div>
      <div class="opt-row">
        <input type="checkbox" id="optOverwrite">
        <div class="opt-text">
          <span class="opt-label">Actualizar si ya existe</span>
          <span class="opt-desc">Si el lead ya existe, actualiza su fuente y campaña. Requiere desactivar "Omitir duplicados".</span>
        </div>
      </div>
    </div>

    <!-- Progress -->
    <div class="progress-wrap" id="progressWrap" style="display:none">
      <div class="progress-bar-bg"><div class="progress-bar-fill" id="progressBar"></div></div>
      <div class="progress-msg" id="progressMsg">Procesando…</div>
    </div>

    <!-- Result -->
    <div class="result-box" id="resultBox"></div>

    <div class="btn-row" id="btnRowImport">
      <button class="btn-secondary" onclick="goStep(2)">← Atrás</button>
      <button class="btn-gold" id="btnImport" onclick="runImport()">⬆ Importar ahora</button>
    </div>

    <div class="btn-row hidden" id="btnRowDone">
      <a href="leads.php" class="btn-primary">Ver contactos importados →</a>
      <button class="btn-secondary" onclick="resetAll()">Nueva importación</button>
    </div>
  </div>
</div><!-- /#panelImport -->

</div><!-- /.main -->

<script>
(function(){
'use strict';

/* ═══ Campos del CRM de leads ═══ */
const CRM_FIELDS = [
  { key: 'nombre',   label: 'Nombre *',             hint: ['nombre','name','cliente','prospecto','contacto','titular'] },
  { key: 'telefono', label: 'Teléfono',              hint: ['telefono','teléfono','tel','phone','movil','celular','mobile','whatsapp'] },
  { key: 'email',    label: 'Email',                 hint: ['email','correo','mail','e-mail'] },
  { key: 'interes',  label: 'Línea de interés',      hint: ['interes','interés','seguro','linea','línea','producto','servicio'] },
  { key: 'campana',  label: 'Campaña',               hint: ['campaña','campana','campaign','utm','origen','source'] },
  { key: 'notas',    label: 'Notas / Observaciones', hint: ['nota','notas','observacion','observaciones','comentario','remarks'] },
  { key: 'ignore',   label: '— Ignorar —',           hint: [] },
];

/* ═══ Estado global ═══ */
let workbook   = null;
let sheetData  = [];   // [[row], [row], ...]
let headerIdx  = 0;    // índice de la fila de headers
let imported   = 0;

/* ════════════════════════════════
   PLANTILLA EXCEL
════════════════════════════════ */
function downloadTemplate() {
  const ws = XLSX.utils.aoa_to_sheet([
    ['Nombre','Teléfono','Email','Interés / Seguro','Campaña','Notas'],
    ['Juan Pérez','8091234567','juan@email.com','Salud','facebook-agosto','Interesado en familiar'],
    ['María López','8299876543','','Vida','','Referida por cliente'],
  ]);
  ws['!cols'] = [{wch:24},{wch:16},{wch:28},{wch:22},{wch:18},{wch:30}];
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Prospectos');
  XLSX.writeFile(wb, 'plantilla-prospectos-sanalia.xlsx');
}
window.downloadTemplate = downloadTemplate;

/* ════════════════════════════════
   PASO 1 — CARGAR ARCHIVO
════════════════════════════════ */
const dropZone  = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('drag-over');
  if (e.dataTransfer.files[0]) loadFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => { if (fileInput.files[0]) loadFile(fileInput.files[0]); });

function loadFile(file) {
  const reader = new FileReader();
  reader.onload = e => {
    try {
      const data = new Uint8Array(e.target.result);
      workbook = XLSX.read(data, { type:'array', cellDates:false });

      const fi = document.getElementById('fileInfo');
      fi.classList.add('show');
      document.getElementById('fileInfoText').textContent =
        `${file.name} · ${workbook.SheetNames.length} hoja(s): ${workbook.SheetNames.join(', ')}`;

      // Sheet tabs
      const sheetRow  = document.getElementById('sheetRow');
      const sheetTabs = document.getElementById('sheetTabs');
      sheetTabs.innerHTML = '';
      if (workbook.SheetNames.length > 1) {
        sheetRow.style.display = 'block';
        workbook.SheetNames.forEach((name, i) => {
          const btn = document.createElement('button');
          btn.className = 'sheet-tab' + (i===0?' active':'');
          btn.textContent = name;
          btn.dataset.sheet = name;
          btn.addEventListener('click', function() {
            document.querySelectorAll('.sheet-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadSheet(this.dataset.sheet);
          });
          sheetTabs.appendChild(btn);
        });
      } else {
        sheetRow.style.display = 'none';
      }

      loadSheet(workbook.SheetNames[0]);
      goStep(2);
    } catch(err) {
      alert('Error leyendo el archivo: ' + err.message);
    }
  };
  reader.readAsArrayBuffer(file);
}

function loadSheet(name) {
  const ws = workbook.Sheets[name];
  sheetData = XLSX.utils.sheet_to_json(ws, { header:1, defval:'', raw:true })
    .filter(r => r.some(v => String(v).trim() !== ''));

  headerIdx = detectHeaderRow();
  buildColMap();
  buildPreview();
}

function detectHeaderRow() {
  // Primera fila con al menos 2 celdas de texto
  for (let i = 0; i < Math.min(6, sheetData.length); i++) {
    const textCells = sheetData[i].filter(v => v && typeof v === 'string' && v.trim().length > 1);
    if (textCells.length >= 2) return i;
  }
  return 0;
}

/* ════════════════════════════════
   PASO 2 — MAPEO DE COLUMNAS
════════════════════════════════ */
function autoDetect(headerStr) {
  const h = String(headerStr||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
  for (const f of CRM_FIELDS) {
    if (f.key === 'ignore') continue;
    if (f.hint.some(w => h.includes(w))) return f.key;
  }
  return 'ignore';
}

function buildColMap() {
  const grid = document.getElementById('colMapGrid');
  const headerRow = sheetData[headerIdx] || [];

  const selHtml = CRM_FIELDS.map(f => `<option value="${f.key}">${f.label}</option>`).join('');

  grid.innerHTML = headerRow.map((h, ci) => {
    if (String(h).trim() === '') return '';
    const detected = autoDetect(h);
    const isMapped = detected !== 'ignore';
    return `<div class="col-map-row">
      <div class="col-from" title="${esc(String(h))}">${esc(String(h))}</div>
      <span class="col-arrow">→</span>
      <select data-col="${ci}" class="${isMapped?'mapped':''}" onchange="onSelChange(this)">
        ${CRM_FIELDS.map(f => `<option value="${f.key}"${f.key===detected?' selected':''}>${f.label}</option>`).join('')}
      </select>
    </div>`;
  }).join('');
}

function onSelChange(sel) {
  sel.className = sel.value !== 'ignore' ? 'mapped' : '';
}

function buildPreview() {
  const headerRow = sheetData[headerIdx] || [];
  const dataRows  = sheetData.slice(headerIdx + 1, headerIdx + 9);
  const dataTotal = sheetData.length - headerIdx - 1;

  document.getElementById('totalRowsInfo').textContent =
    `${dataTotal} prospecto${dataTotal !== 1 ? 's' : ''} encontrado${dataTotal !== 1 ? 's' : ''}`;

  const head = document.getElementById('previewHead');
  const body = document.getElementById('previewBody');
  head.innerHTML = '<tr>' + headerRow.map(h => `<th>${esc(String(h||''))}</th>`).join('') + '</tr>';
  body.innerHTML = dataRows.map(row =>
    '<tr>' + headerRow.map((_,i) => `<td title="${esc(String(row[i]||''))}">${esc(String(row[i]||''))}</td>`).join('') + '</tr>'
  ).join('');
}

/* ════════════════════════════════
   PASO 3 — IMPORTAR
════════════════════════════════ */
async function runImport() {
  const btn = document.getElementById('btnImport');
  btn.disabled = true;
  btn.textContent = 'Importando…';

  const skipDupes  = document.getElementById('optSkipDupes').checked;
  const overwrite  = document.getElementById('optOverwrite').checked;
  const fuente     = document.getElementById('batchFuente').value;

  // Leer mapeo
  const mapping = {};
  document.querySelectorAll('#colMapGrid select').forEach(sel => {
    if (sel.value !== 'ignore') mapping[parseInt(sel.dataset.col)] = sel.value;
  });

  // Construir registros
  const dataRows = sheetData.slice(headerIdx + 1)
    .filter(r => r.some(v => String(v).trim() !== ''));

  const records = dataRows.map(row => {
    const rec = { fuente };
    Object.entries(mapping).forEach(([ci, key]) => {
      rec[key] = String(row[parseInt(ci)] ?? '').trim();
    });
    return rec;
  }).filter(r => r.nombre && r.nombre.length > 1);

  if (records.length === 0) {
    alert('No se encontraron registros con nombre válido. Verifica el mapeo de columnas.');
    btn.disabled = false; btn.textContent = '⬆ Importar ahora';
    return;
  }

  // UI
  document.getElementById('progressWrap').style.display = 'block';
  document.getElementById('progressMsg').textContent = `Procesando ${records.length} registros…`;

  const BATCH = 100;
  let ok = 0, sk = 0, errs = [];

  for (let i = 0; i < records.length; i += BATCH) {
    const batch = records.slice(i, i + BATCH);
    const fd = new FormData();
    fd.append('rows',       JSON.stringify(batch));
    fd.append('skip_dupes', skipDupes ? '1' : '0');
    fd.append('overwrite',  overwrite  ? '1' : '0');
    try {
      const res = await fetch('api.php?action=leads_import', { method:'POST', body:fd });
      const d   = await res.json();
      ok += d.imported || 0;
      sk += d.skipped  || 0;
      if (d.errors) errs.push(...d.errors);
    } catch(e) {
      errs.push('Error de red en lote ' + (Math.floor(i/BATCH)+1));
    }
    const pct = Math.min(Math.round(((i+BATCH)/records.length)*100), 100);
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressMsg').textContent =
      `Procesados ${Math.min(i+BATCH, records.length)} de ${records.length}…`;
    await new Promise(r => setTimeout(r, 60));
  }

  // Resultado
  document.getElementById('progressBar').style.width = '100%';
  document.getElementById('progressMsg').textContent = 'Completado.';

  const rb = document.getElementById('resultBox');
  rb.style.display = 'block';
  imported = ok;

  if (errs.length === 0) {
    rb.className = 'result-box ok';
    rb.innerHTML = `✅ <strong>${ok} prospectos importados</strong>${sk > 0 ? ` · ${sk} omitidos (duplicados)` : ''}.`;
  } else if (ok > 0) {
    rb.className = 'result-box warn';
    rb.innerHTML = `⚠️ <strong>${ok} importados</strong>, ${sk} omitidos, <strong>${errs.length} con error</strong>.<ul class="errors-list">${errs.map(e=>`<li>${esc(e)}</li>`).join('')}</ul>`;
  } else {
    rb.className = 'result-box err';
    rb.innerHTML = `❌ No se importó ningún registro. Revisa el mapeo de columnas o el formato del archivo.<ul class="errors-list">${errs.map(e=>`<li>${esc(e)}</li>`).join('')}</ul>`;
  }

  document.getElementById('btnRowImport').classList.add('hidden');
  document.getElementById('btnRowDone').classList.remove('hidden');
}
window.runImport = runImport;

/* ════════════════════════════════
   STEPS NAV
════════════════════════════════ */
function goStep(n) {
  document.getElementById('panelFile').classList.toggle('hidden',   n !== 1);
  document.getElementById('panelMap').classList.toggle('hidden',    n !== 2);
  document.getElementById('panelImport').classList.toggle('hidden', n !== 3);

  ['step1','step2','step3'].forEach((id,i) => {
    const el = document.getElementById(id);
    el.classList.toggle('active', i+1 === n);
    el.classList.toggle('done',   i+1 < n);
  });
}
window.goStep = goStep;

function resetAll() {
  workbook = null; sheetData = []; headerIdx = 0; imported = 0;
  fileInput.value = '';
  document.getElementById('fileInfo').classList.remove('show');
  document.getElementById('progressBar').style.width = '0%';
  document.getElementById('progressWrap').style.display = 'none';
  document.getElementById('resultBox').style.display = 'none';
  document.getElementById('resultBox').className = 'result-box';
  document.getElementById('btnRowImport').classList.remove('hidden');
  document.getElementById('btnRowDone').classList.add('hidden');
  goStep(1);
}
window.resetAll = resetAll;

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

})();
</script>
</body>
</html>
