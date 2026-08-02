<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/form-defaults.php';

$db        = get_db();
$client_id = (int)($_GET['client'] ?? 0);
$tipo      = $_GET['tipo'] ?? '';
$app_id    = (int)($_GET['app'] ?? 0);

if (!$client_id) { header('Location: clients.php'); exit; }

// Cargar cliente
$client = $db->prepare('SELECT * FROM clients WHERE id=:id');
$client->execute(['id'=>$client_id]);
$client = $client->fetch();
if (!$client) { header('Location: clients.php'); exit; }

// Cargar aplicación existente si app_id
$app = null;
if ($app_id) {
    $s = $db->prepare('SELECT * FROM applications WHERE id=:id AND client_id=:cid');
    $s->execute(['id'=>$app_id,'cid'=>$client_id]);
    $app = $s->fetch();
    if ($app) $tipo = $app['tipo'];
}

// Cargar template
$template = null;
if ($tipo) {
    $s = $db->prepare('SELECT * FROM form_templates WHERE tipo=:tipo');
    $s->execute(['tipo'=>$tipo]);
    $template = $s->fetch();
}
$fields = $template ? json_decode($template['fields'], true) : ($tipo ? default_fields($tipo) : []);
$form_data = $app ? json_decode($app['form_data'] ?? '{}', true) : [];

// Documentos del cliente
$docs = $db->prepare(
    'SELECT * FROM documents WHERE client_id=:cid ORDER BY created_at DESC'
);
$docs->execute(['cid'=>$client_id]);
$docs = $docs->fetchAll();

$tipos_list = [
    'vida'=>'Vida','salud'=>'Salud','salud-persona'=>'Salud Personal',
    'viajes'=>'Asistencia en Viaje','vehiculos'=>'Vehículos',
    'accidentes-personales'=>'Accidentes Personales',
    'internacionales'=>'Médico Internacional',
    'riesgos-generales'=>'Riesgos Generales',
    'mascotas'=>'Mascotas',
];

function fmt_size(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes/1024,1) . ' KB';
    return round($bytes/1048576,1) . ' MB';
}
function mime_icon(string $mime): string {
    if (str_contains($mime,'pdf'))   return '📄';
    if (str_contains($mime,'image')) return '🖼';
    if (str_contains($mime,'word') || str_contains($mime,'document')) return '📝';
    if (str_contains($mime,'excel') || str_contains($mime,'sheet'))   return '📊';
    return '📎';
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Solicitud — <?= htmlspecialchars($client['nombre']) ?></title>
<meta name="robots" content="noindex, nofollow">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy-950:#071523;--navy-900:#0C2036;--navy-800:#153350;--navy-700:#1E4468;
  --gold-500:#C6A15B;--gold-600:#A9843F;
  --silver-100:#F3F5F7;--silver-300:#DCE1E7;--silver-500:#AEB8C4;
  --ink:#0E1620;--green:#1a7f4b;--green-bg:#e8f5ee;
  --red:#dc2626;--red-bg:#fee2e2;--orange:#d97706;
}
body{font-family:'Inter',system-ui,sans-serif;background:var(--silver-100);color:var(--ink);min-height:100vh}
.topbar{background:var(--navy-900);color:#fff;display:flex;align-items:center;justify-content:space-between;padding:.875rem 2rem;position:sticky;top:0;z-index:100;gap:1rem;flex-wrap:wrap}
.topbar .brand{font-family:'Manrope',system-ui,sans-serif;font-weight:800;font-size:1rem}
.topbar .brand span{color:var(--gold-500)}
.topbar-nav{display:flex;gap:.25rem}
.nav-tab{padding:.4rem .875rem;border-radius:6px;font-size:.82rem;font-weight:600;text-decoration:none;color:rgba(255,255,255,.6);border:none;background:none;cursor:pointer}
.nav-tab:hover{color:#fff;background:rgba(255,255,255,.08)}
.btn-back{background:rgba(255,255,255,.12);border:none;color:#fff;padding:.4rem .875rem;border-radius:6px;font-size:.8rem;cursor:pointer;text-decoration:none}
.btn-back:hover{background:rgba(255,255,255,.2)}
.main{max-width:900px;margin:0 auto;padding:2rem 1.5rem;display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start}
@media(max-width:768px){.main{grid-template-columns:1fr;padding:1rem}}

/* ── Card ── */
.card{background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(7,21,35,.08);overflow:hidden}
.card-header{background:var(--navy-900);color:#fff;padding:1.25rem 1.5rem}
.card-header h2{font-family:'Manrope',system-ui,sans-serif;font-weight:800;font-size:1rem}
.card-header .sub{font-size:.75rem;color:var(--silver-500);margin-top:.2rem}
.card-body{padding:1.5rem;display:flex;flex-direction:column;gap:1.1rem}

/* ── Selector de tipo ── */
.tipo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.625rem}
@media(max-width:500px){.tipo-grid{grid-template-columns:1fr 1fr}}
.tipo-btn{padding:.65rem .5rem;border:1.5px solid var(--silver-300);border-radius:8px;background:#fff;font-size:.78rem;font-weight:600;cursor:pointer;text-align:center;color:var(--navy-900);transition:all .18s;text-decoration:none;display:block}
.tipo-btn:hover,.tipo-btn.active{background:var(--navy-900);color:#fff;border-color:var(--navy-900)}

/* ── Formulario ── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.875rem}
@media(max-width:600px){.form-grid{grid-template-columns:1fr}}
.field{display:flex;flex-direction:column;gap:.35rem}
.field.full{grid-column:1/-1}
label{font-size:.72rem;font-weight:700;color:var(--navy-900);text-transform:uppercase;letter-spacing:.04em}
label .req{color:var(--red)}
input[type=text],input[type=number],input[type=date],select,textarea{
  padding:.625rem .875rem;border:1.5px solid var(--silver-300);border-radius:8px;
  font-size:.875rem;font-family:inherit;outline:none;transition:border-color .2s;background:#fff;color:var(--ink)}
input:focus,select:focus,textarea:focus{border-color:var(--navy-700)}
textarea{resize:vertical;min-height:72px}

/* ── Estado badge ── */
.estado-bar{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.5rem}
.estado-opt{padding:.3rem .75rem;border-radius:999px;border:2px solid var(--silver-300);font-size:.75rem;font-weight:700;cursor:pointer;background:#fff;color:var(--silver-500);transition:all .18s}
.estado-opt.sel-borrador{background:#f3f4f6;color:#6b7280;border-color:#9ca3af}
.estado-opt.sel-en-revision{background:#fef3c7;color:var(--orange);border-color:var(--orange)}
.estado-opt.sel-aprobada{background:var(--green-bg);color:var(--green);border-color:var(--green)}
.estado-opt.sel-rechazada{background:var(--red-bg);color:var(--red);border-color:var(--red)}

/* ── Botones ── */
.btn-primary{width:100%;padding:.75rem;background:var(--navy-900);color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;transition:background .2s}
.btn-primary:hover{background:var(--navy-700)}
.btn-primary.saved{background:var(--green)}
.notas-field textarea{min-height:80px}

/* ── Documentos ── */
.doc-list{display:flex;flex-direction:column;gap:.5rem}
.doc-item{display:flex;align-items:center;gap:.75rem;background:var(--silver-100);border-radius:8px;padding:.625rem .875rem}
.doc-icon{font-size:1.25rem;flex-shrink:0}
.doc-info{flex:1;min-width:0}
.doc-name{font-size:.82rem;font-weight:600;color:var(--navy-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.doc-meta{font-size:.72rem;color:var(--silver-500)}
.doc-actions{display:flex;gap:.4rem;flex-shrink:0}
.btn-doc{padding:.25rem .5rem;border-radius:5px;font-size:.72rem;font-weight:600;cursor:pointer;border:none;text-decoration:none}
.btn-view{background:var(--navy-800);color:#fff}
.btn-del{background:var(--red-bg);color:var(--red)}

/* ── Upload zone ── */
.upload-zone{border:2px dashed var(--silver-300);border-radius:10px;padding:1.5rem;text-align:center;cursor:pointer;transition:all .2s;position:relative}
.upload-zone:hover,.upload-zone.drag{border-color:var(--navy-700);background:var(--silver-100)}
.upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-zone .uz-icon{font-size:2rem;margin-bottom:.5rem}
.upload-zone p{font-size:.82rem;color:var(--silver-500)}
.upload-zone strong{color:var(--navy-900)}
.upload-progress{margin-top:.75rem;display:none}
.progress-bar{height:6px;background:var(--silver-300);border-radius:3px;overflow:hidden;margin-top:.4rem}
.progress-fill{height:100%;background:var(--navy-700);border-radius:3px;transition:width .3s}
.upload-msg{font-size:.78rem;margin-top:.4rem}
.upload-msg.ok{color:var(--green)}
.upload-msg.err{color:var(--red)}
</style>
</head>
<body>
<div class="topbar">
  <div class="brand">Sanalia &amp; Asociados &mdash; <span>CRM</span></div>
  <div class="topbar-nav">
    <a href="index.php" class="nav-tab">Solicitudes</a>
    <a href="clients.php" class="nav-tab">Clientes</a>
    <a href="calendar.php" class="nav-tab">Calendario</a>
  </div>
  <a href="clients.php" class="btn-back">← Volver a Clientes</a>
</div>

<div class="main">

  <!-- Columna izquierda: formulario -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Selector de tipo -->
    <?php if (!$tipo): ?>
    <div class="card">
      <div class="card-header">
        <h2>Nueva Solicitud — <?= htmlspecialchars($client['nombre']) ?></h2>
        <div class="sub">Selecciona el tipo de póliza</div>
      </div>
      <div class="card-body">
        <div class="tipo-grid">
          <?php foreach ($tipos_list as $k=>$v): ?>
          <a href="application.php?client=<?= $client_id ?>&tipo=<?= $k ?>" class="tipo-btn"><?= $v ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php else: ?>

    <!-- Formulario de solicitud -->
    <div class="card">
      <div class="card-header">
        <h2><?= htmlspecialchars(tipo_nombre($tipo)) ?></h2>
        <div class="sub">Cliente: <?= htmlspecialchars($client['nombre']) ?><?= $app ? ' · Solicitud #'.$app['id'] : '' ?></div>
      </div>
      <div class="card-body">

        <!-- Estado -->
        <div>
          <label style="display:block;margin-bottom:.5rem">Estado</label>
          <div class="estado-bar" id="estadoBar">
            <?php foreach (['borrador'=>'Borrador','en-revision'=>'En revisión','aprobada'=>'Aprobada','rechazada'=>'Rechazada'] as $est=>$lbl): ?>
            <button type="button" class="estado-opt <?= (($app['estado']??'borrador')===$est)?'sel-'.$est:'' ?>" data-est="<?= $est ?>"><?= $lbl ?></button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" id="estadoVal" value="<?= htmlspecialchars($app['estado']??'borrador') ?>">
        </div>

        <!-- Campos del formulario -->
        <form id="appForm">
          <input type="hidden" name="app_id"    value="<?= $app_id ?>">
          <input type="hidden" name="client_id" value="<?= $client_id ?>">
          <input type="hidden" name="tipo"      value="<?= htmlspecialchars($tipo) ?>">
          <div class="form-grid" id="fieldsGrid">
          <?php foreach ($fields as $f):
            $val = $form_data[$f['name']] ?? '';
            $full = in_array($f['type'], ['textarea','text']) && strlen($f['label']) > 20 ? 'full' : '';
            $full = $f['type'] === 'textarea' ? 'full' : $full;
          ?>
          <div class="field <?= $full ?>" data-field-id="<?= htmlspecialchars($f['id']) ?>">
            <label><?= htmlspecialchars($f['label']) ?><?= $f['required'] ? ' <span class="req">*</span>' : '' ?></label>
            <?php if ($f['type'] === 'select'): ?>
            <select name="field_<?= htmlspecialchars($f['name']) ?>" <?= $f['required']?'required':'' ?>>
              <option value="">— Seleccionar —</option>
              <?php foreach ($f['options']??[] as $opt): ?>
              <option value="<?= htmlspecialchars($opt) ?>" <?= $val===$opt?'selected':'' ?>><?= htmlspecialchars($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <?php elseif ($f['type'] === 'textarea'): ?>
            <textarea name="field_<?= htmlspecialchars($f['name']) ?>" <?= $f['required']?'required':'' ?> rows="3"><?= htmlspecialchars($val) ?></textarea>
            <?php else: ?>
            <input type="<?= htmlspecialchars($f['type']) ?>"
                   name="field_<?= htmlspecialchars($f['name']) ?>"
                   value="<?= htmlspecialchars($val) ?>"
                   placeholder="<?= htmlspecialchars($f['placeholder']??'') ?>"
                   <?= $f['required']?'required':'' ?>>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          </div>

          <!-- Notas internas -->
          <div class="field full notas-field" style="margin-top:.5rem">
            <label>Notas Internas</label>
            <textarea name="notas" rows="3" placeholder="Observaciones del asesor…"><?= htmlspecialchars($app['notas']??'') ?></textarea>
          </div>
        </form>

        <button class="btn-primary" id="btnSaveApp">Guardar Solicitud</button>
      </div>
    </div>

    <!-- Constructor de campos personalizados -->
    <div class="card">
      <div class="card-header">
        <h2>Personalizar Campos</h2>
        <div class="sub">Agrega o elimina campos para este tipo de póliza</div>
      </div>
      <div class="card-body">
        <div id="fieldsList" style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem">
          <?php foreach ($fields as $f): ?>
          <div class="field-row" data-id="<?= htmlspecialchars($f['id']) ?>" style="display:flex;align-items:center;gap:.5rem;background:var(--silver-100);padding:.5rem .75rem;border-radius:8px">
            <span style="flex:1;font-size:.82rem;font-weight:600"><?= htmlspecialchars($f['label']) ?></span>
            <span style="font-size:.72rem;color:var(--silver-500);font-family:monospace"><?= $f['type'] ?></span>
            <?php if ($f['required']??false): ?><span style="font-size:.65rem;background:var(--red-bg);color:var(--red);padding:.15rem .4rem;border-radius:4px;font-weight:700">REQ</span><?php endif; ?>
            <button type="button" class="btn-del-field btn-doc btn-del" data-id="<?= htmlspecialchars($f['id']) ?>">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <!-- Nuevo campo -->
        <div style="background:var(--silver-100);border-radius:10px;padding:1rem;display:flex;flex-direction:column;gap:.75rem">
          <div style="font-size:.78rem;font-weight:700;color:var(--navy-900);text-transform:uppercase;letter-spacing:.04em">+ Agregar campo</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.625rem">
            <div class="field"><label>Etiqueta</label><input type="text" id="newLabel" placeholder="Ej: No. de empleado"></div>
            <div class="field"><label>Tipo</label>
              <select id="newType">
                <option value="text">Texto</option>
                <option value="number">Número</option>
                <option value="date">Fecha</option>
                <option value="textarea">Texto largo</option>
                <option value="select">Opciones (selección)</option>
              </select>
            </div>
            <div class="field" id="optionsWrap" style="display:none;grid-column:1/-1">
              <label>Opciones (una por línea)</label>
              <textarea id="newOptions" rows="3" placeholder="Opción 1&#10;Opción 2&#10;Opción 3"></textarea>
            </div>
            <div class="field" style="grid-column:1/-1;flex-direction:row;align-items:center;gap:.5rem">
              <input type="checkbox" id="newRequired" style="width:16px;height:16px">
              <label for="newRequired" style="text-transform:none;font-size:.82rem;font-weight:400;letter-spacing:0">Campo requerido</label>
            </div>
          </div>
          <button type="button" class="btn-primary" id="btnAddField" style="padding:.6rem">Agregar campo</button>
        </div>
        <button type="button" class="btn-primary" id="btnSaveTemplate" style="margin-top:.5rem;background:var(--gold-600)">Guardar plantilla de campos</button>
      </div>
    </div>

    <?php endif; ?>
  </div>

  <!-- Columna derecha: documentos -->
  <?php if ($tipo): ?>
  <div style="display:flex;flex-direction:column;gap:1.25rem">
    <div class="card">
      <div class="card-header">
        <h2>Documentos del Cliente</h2>
        <div class="sub"><?= count($docs) ?> archivo(s)</div>
      </div>
      <div class="card-body">

        <!-- Upload zone -->
        <div class="upload-zone" id="uploadZone">
          <input type="file" id="fileInput" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.docx,.doc">
          <div class="uz-icon">📎</div>
          <p><strong>Arrastra archivos aquí</strong><br>o haz clic para seleccionar</p>
          <p style="font-size:.72rem;margin-top:.35rem">PDF, imagen, Excel, Word — máx. 10 MB</p>
        </div>
        <div class="upload-progress" id="uploadProgress">
          <div style="font-size:.78rem;color:var(--navy-900)" id="uploadFileName"></div>
          <div class="progress-bar"><div class="progress-fill" id="progressFill" style="width:0%"></div></div>
          <div class="upload-msg" id="uploadMsg"></div>
        </div>

        <!-- Lista de documentos -->
        <div class="doc-list" id="docList">
          <?php foreach ($docs as $d): ?>
          <div class="doc-item" id="doc-<?= $d['id'] ?>">
            <span class="doc-icon"><?= mime_icon($d['mime_type']??'') ?></span>
            <div class="doc-info">
              <div class="doc-name"><?= htmlspecialchars($d['nombre']) ?></div>
              <div class="doc-meta"><?= htmlspecialchars($d['original_name']??'') ?> · <?= fmt_size($d['file_size']??0) ?></div>
            </div>
            <div class="doc-actions">
              <a class="btn-doc btn-view" href="download.php?id=<?= $d['id'] ?>" target="_blank">Ver</a>
              <button class="btn-doc btn-del" onclick="deleteDoc(<?= $d['id'] ?>)">✕</button>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($docs)): ?>
          <div style="text-align:center;padding:1.5rem;color:var(--silver-500);font-size:.82rem" id="emptyDocs">Sin documentos adjuntos.</div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
(function(){
const CLIENT_ID = <?= $client_id ?>;
const TIPO      = '<?= addslashes($tipo) ?>';
let fields      = <?= json_encode($fields, JSON_UNESCAPED_UNICODE) ?>;

/* ── Estado ── */
let estadoActual = document.getElementById('estadoVal')?.value || 'borrador';
document.querySelectorAll('.estado-opt').forEach(function(b) {
  b.addEventListener('click', function() {
    estadoActual = this.dataset.est;
    document.querySelectorAll('.estado-opt').forEach(x => x.className = 'estado-opt');
    this.className = 'estado-opt sel-' + estadoActual;
    document.getElementById('estadoVal').value = estadoActual;
  });
});

/* ── Guardar solicitud ── */
const btnSave = document.getElementById('btnSaveApp');
if (btnSave) {
  btnSave.addEventListener('click', function() {
    const form = document.getElementById('appForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    btnSave.textContent = 'Guardando…'; btnSave.disabled = true;

    const fd = new FormData(form);
    fd.append('estado', estadoActual);

    fetch('api.php?action=application_save', { method:'POST', body:fd })
      .then(r=>r.json())
      .then(function(r) {
        if (r.ok) {
          btnSave.textContent = '✓ Guardado';
          btnSave.classList.add('saved');
          // Actualizar URL con app_id
          if (r.id && !window.location.search.includes('app=')) {
            history.replaceState(null,'','?client='+CLIENT_ID+'&tipo='+TIPO+'&app='+r.id);
          }
          setTimeout(function(){ btnSave.textContent='Guardar Solicitud'; btnSave.classList.remove('saved'); btnSave.disabled=false; }, 2500);
        } else {
          alert('Error: ' + r.error);
          btnSave.textContent='Guardar Solicitud'; btnSave.disabled=false;
        }
      });
  });
}

/* ── Constructor de campos ── */
const newType = document.getElementById('newType');
const optionsWrap = document.getElementById('optionsWrap');
if (newType) {
  newType.addEventListener('change', function() {
    optionsWrap.style.display = this.value === 'select' ? 'block' : 'none';
  });
}

document.getElementById('btnAddField')?.addEventListener('click', function() {
  const label = document.getElementById('newLabel').value.trim();
  const type  = document.getElementById('newType').value;
  const req   = document.getElementById('newRequired').checked;
  const optsRaw = document.getElementById('newOptions').value;
  if (!label) { alert('Escribe una etiqueta para el campo.'); return; }

  const name  = label.toLowerCase().replace(/[^a-z0-9]/g,'_').replace(/_+/g,'_');
  const id    = name + '_' + Date.now();
  const field = { id, label, name, type, required:req, placeholder:'', options: type==='select' ? optsRaw.split('\n').map(s=>s.trim()).filter(Boolean) : undefined };
  fields.push(field);

  // Añadir al formulario
  addFieldToForm(field);
  // Añadir a la lista de campos
  addFieldToList(field);

  document.getElementById('newLabel').value = '';
  document.getElementById('newOptions').value = '';
  document.getElementById('newRequired').checked = false;
});

function addFieldToForm(f) {
  const grid = document.getElementById('fieldsGrid');
  if (!grid) return;
  const div  = document.createElement('div');
  const full = f.type==='textarea' ? 'full' : '';
  div.className = 'field ' + full;
  div.dataset.fieldId = f.id;
  let input = '';
  if (f.type === 'select') {
    input = '<select name="field_'+f.name+'">' + (f.options||[]).map(o=>'<option>'+o+'</option>').join('') + '</select>';
  } else if (f.type === 'textarea') {
    input = '<textarea name="field_'+f.name+'" rows="3"></textarea>';
  } else {
    input = '<input type="'+f.type+'" name="field_'+f.name+'" placeholder="'+( f.placeholder||'')+'">';
  }
  div.innerHTML = '<label>'+f.label+(f.required?' <span class="req">*</span>':'')+'</label>'+input;
  grid.appendChild(div);
}

function addFieldToList(f) {
  const list = document.getElementById('fieldsList');
  if (!list) return;
  const div = document.createElement('div');
  div.className = 'field-row';
  div.dataset.id = f.id;
  div.style.cssText='display:flex;align-items:center;gap:.5rem;background:var(--silver-100);padding:.5rem .75rem;border-radius:8px';
  div.innerHTML = '<span style="flex:1;font-size:.82rem;font-weight:600">'+f.label+'</span>'
    + '<span style="font-size:.72rem;color:var(--silver-500);font-family:monospace">'+f.type+'</span>'
    + (f.required?'<span style="font-size:.65rem;background:var(--red-bg);color:var(--red);padding:.15rem .4rem;border-radius:4px;font-weight:700">REQ</span>':'')
    + '<button type="button" class="btn-del-field btn-doc btn-del" data-id="'+f.id+'">✕</button>';
  list.appendChild(div);
  bindDelField(div.querySelector('.btn-del-field'));
}

function bindDelField(btn) {
  btn.addEventListener('click', function() {
    const id = this.dataset.id;
    fields = fields.filter(function(f){ return f.id !== id; });
    // Quitar de la lista
    document.querySelector('.field-row[data-id="'+id+'"]')?.remove();
    // Quitar del formulario
    document.querySelector('[data-field-id="'+id+'"]')?.remove();
  });
}
document.querySelectorAll('.btn-del-field').forEach(bindDelField);

/* ── Guardar plantilla ── */
document.getElementById('btnSaveTemplate')?.addEventListener('click', function() {
  this.textContent = 'Guardando…'; this.disabled = true;
  const btn = this;
  const fd = new FormData();
  fd.append('tipo', TIPO);
  fd.append('fields', JSON.stringify(fields));
  fetch('api.php?action=template_save', { method:'POST', body:fd })
    .then(r=>r.json())
    .then(function(r) {
      if (r.ok) { btn.textContent = '✓ Plantilla guardada'; setTimeout(()=>{ btn.textContent='Guardar plantilla de campos'; btn.disabled=false; }, 2000); }
      else { alert('Error: ' + r.error); btn.textContent='Guardar plantilla'; btn.disabled=false; }
    });
});

/* ── Upload documentos ── */
const uploadZone  = document.getElementById('uploadZone');
const fileInput   = document.getElementById('fileInput');
const uploadProg  = document.getElementById('uploadProgress');
const progressFill= document.getElementById('progressFill');
const uploadMsg   = document.getElementById('uploadMsg');
const uploadFName = document.getElementById('uploadFileName');
const docList     = document.getElementById('docList');

if (uploadZone) {
  ['dragenter','dragover'].forEach(function(ev){
    uploadZone.addEventListener(ev, function(e){ e.preventDefault(); uploadZone.classList.add('drag'); });
  });
  ['dragleave','drop'].forEach(function(ev){
    uploadZone.addEventListener(ev, function(e){ e.preventDefault(); uploadZone.classList.remove('drag'); });
  });
  uploadZone.addEventListener('drop', function(e) {
    uploadFiles(e.dataTransfer.files);
  });
  fileInput.addEventListener('change', function() {
    uploadFiles(this.files);
  });
}

function uploadFiles(fileList) {
  Array.from(fileList).forEach(uploadOne);
}

function uploadOne(file) {
  uploadProg.style.display = 'block';
  uploadFName.textContent  = file.name;
  progressFill.style.width = '0%';
  uploadMsg.className = 'upload-msg';
  uploadMsg.textContent = '';

  const fd = new FormData();
  fd.append('file', file);
  fd.append('client_id', CLIENT_ID);
  fd.append('nombre', file.name.replace(/\.[^.]+$/, ''));

  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'upload.php');
  xhr.upload.addEventListener('progress', function(e) {
    if (e.lengthComputable) progressFill.style.width = Math.round(e.loaded/e.total*100) + '%';
  });
  xhr.addEventListener('load', function() {
    let r;
    try { r = JSON.parse(xhr.responseText); } catch(e) { r = {ok:false,error:'Respuesta inválida'}; }
    if (r.ok) {
      uploadMsg.className = 'upload-msg ok';
      uploadMsg.textContent = '✓ ' + file.name + ' subido correctamente';
      addDocToList(r);
      document.getElementById('emptyDocs')?.remove();
    } else {
      uploadMsg.className = 'upload-msg err';
      uploadMsg.textContent = '✗ ' + (r.error || 'Error desconocido');
    }
    progressFill.style.width = '100%';
    setTimeout(function(){ uploadProg.style.display='none'; }, 3000);
    fileInput.value = '';
  });
  xhr.send(fd);
}

function addDocToList(doc) {
  const icons = {'application/pdf':'📄','image/jpeg':'🖼','image/png':'🖼','image/webp':'🖼'};
  const icon  = icons[doc.mime_type] || (doc.mime_type?.includes('word')?'📝': doc.mime_type?.includes('sheet')?'📊':'📎');
  const size  = doc.file_size < 1048576 ? Math.round(doc.file_size/1024)+'KB' : Math.round(doc.file_size/1048576*10)/10+'MB';
  const div   = document.createElement('div');
  div.className = 'doc-item';
  div.id = 'doc-' + doc.id;
  div.innerHTML = '<span class="doc-icon">'+icon+'</span>'
    + '<div class="doc-info"><div class="doc-name">'+doc.nombre+'</div><div class="doc-meta">'+doc.original_name+' · '+size+'</div></div>'
    + '<div class="doc-actions">'
    + '<a class="btn-doc btn-view" href="download.php?id='+doc.id+'" target="_blank">Ver</a>'
    + '<button class="btn-doc btn-del" onclick="deleteDoc('+doc.id+')">✕</button>'
    + '</div>';
  docList.insertBefore(div, docList.firstChild);
}

/* ── Eliminar documento ── */
window.deleteDoc = function(id) {
  if (!confirm('¿Eliminar este documento?')) return;
  const fd = new FormData(); fd.append('id', id);
  fetch('api.php?action=doc_delete', { method:'POST', body:fd })
    .then(r=>r.json())
    .then(function(r) {
      if (r.ok) {
        document.getElementById('doc-'+id)?.remove();
        if (!docList.querySelector('.doc-item')) {
          docList.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--silver-500);font-size:.82rem">Sin documentos adjuntos.</div>';
        }
      }
    });
};

})();
</script>
</body>
</html>
