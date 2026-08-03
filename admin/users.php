<?php
/**
 * Sanalia CRM — Gestión de Usuarios
 * Solo accesible para rol 'admin'
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_admin();

$db  = get_db();
ensure_users_table($db);

$msg   = '';
$error = '';

/* ── Acciones POST ──────────────────────────────────────────────── */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── Crear usuario admin ── */
    if ($action === 'create_admin') {
        $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 150);
        $email  = trim($_POST['email'] ?? '');
        $pass   = $_POST['password'] ?? '';

        if ($nombre === '' || $email === '' || $pass === '') {
            $error = 'Todos los campos son requeridos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido.';
        } elseif (mb_strlen($pass) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $st   = $db->prepare("INSERT INTO crm_users (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'admin')");
                $st->execute([$nombre, $email, $hash]);
                $msg = "Usuario admin «{$nombre}» creado correctamente.";
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Ese email ya está registrado.' : 'Error al crear el usuario.';
            }
        }
    }

    /* ── Crear usuario guest ── */
    if ($action === 'create_guest') {
        $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 150);
        $email  = trim($_POST['email'] ?? '');
        $mins   = max(10, min(480, (int)($_POST['minutos'] ?? 90)));

        if ($nombre === '' || $email === '') {
            $error = 'Nombre y email son requeridos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido.';
        } else {
            /* Auto-generar contraseña de 10 chars */
            $chars    = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789@#$';
            $raw_pass = '';
            for ($i = 0; $i < 10; $i++) {
                $raw_pass .= $chars[random_int(0, mb_strlen($chars) - 1)];
            }
            $hash      = password_hash($raw_pass, PASSWORD_BCRYPT);
            $expires   = date('Y-m-d H:i:s', time() + $mins * 60);
            try {
                $st = $db->prepare(
                    "INSERT INTO crm_users (nombre, email, password_hash, rol, expires_at) VALUES (?, ?, ?, 'guest', ?)"
                );
                $st->execute([$nombre, $email, $hash, $expires]);
                /* Pasar la contraseña en sesión para mostrarla UNA vez */
                $_SESSION['guest_created'] = [
                    'nombre'  => $nombre,
                    'email'   => $email,
                    'pass'    => $raw_pass,
                    'expires' => $expires,
                    'minutos' => $mins,
                ];
                header('Location: users.php');
                exit;
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Ese email ya está registrado.' : 'Error al crear el usuario.';
            }
        }
    }

    /* ── Desactivar/Activar usuario ── */
    if ($action === 'toggle_active') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid > 0 && $uid !== ($_SESSION['user_id'] ?? 0)) {
            $db->prepare("UPDATE crm_users SET activo = 1 - activo WHERE id = ?")->execute([$uid]);
        }
        header('Location: users.php');
        exit;
    }

    /* ── Eliminar usuario ── */
    if ($action === 'delete_user') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid > 0 && $uid !== ($_SESSION['user_id'] ?? 0)) {
            $db->prepare("DELETE FROM crm_users WHERE id = ?")->execute([$uid]);
        }
        header('Location: users.php');
        exit;
    }
}

/* ── Recuperar credenciales de guest recién creado ─────────────── */

$guest_created = null;
if (!empty($_SESSION['guest_created'])) {
    $guest_created = $_SESSION['guest_created'];
    unset($_SESSION['guest_created']);
}

/* ── Listar usuarios ────────────────────────────────────────────── */

$users = $db->query("SELECT * FROM crm_users ORDER BY rol ASC, created_at DESC")->fetchAll();

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Usuarios — CRM Sanalia</title>
<meta name="robots" content="noindex, nofollow">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --navy-950:#071523; --navy-900:#0C2036; --navy-800:#153350; --navy-700:#1E4468;
  --gold-500:#C6A15B; --gold-600:#A9843F;
  --silver-100:#F3F5F7; --silver-300:#DCE1E7; --silver-500:#AEB8C4;
  --ink:#0E1620; --paper:#F7F7F5;
  --green:#1a7f4b; --green-bg:#e8f5ee;
  --red:#c0392b; --red-bg:#fdecea;
  --amber:#d97706; --amber-bg:#fef3c7;
}
body { font-family:'Inter',system-ui,sans-serif; background:var(--silver-100); color:var(--ink); min-height:100vh; }

/* Topbar */
.topbar { background:var(--navy-900); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:.875rem 2rem; position:sticky; top:0; z-index:100; gap:1rem; flex-wrap:wrap; }
.topbar .brand { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1rem; }
.topbar .brand span { color:var(--gold-500); }
.topbar-nav { display:flex; gap:.25rem; }
.nav-tab { padding:.4rem .875rem; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; color:rgba(255,255,255,.6); border:none; background:none; cursor:pointer; white-space:nowrap; }
.nav-tab:hover { color:#fff; background:rgba(255,255,255,.08); }
.nav-tab.active { color:#fff; background:rgba(255,255,255,.18); }
.btn-outline { background:transparent; border:1.5px solid rgba(255,255,255,.3); color:#fff; padding:.4rem .875rem; border-radius:6px; font-size:.8rem; cursor:pointer; }
.btn-outline:hover { border-color:#fff; }
.guest-badge { background:var(--amber); color:#fff; font-size:.7rem; font-weight:700; padding:.2rem .6rem; border-radius:999px; }

/* Main */
.main { max-width:1100px; margin:0 auto; padding:2rem 1.5rem; display:flex; flex-direction:column; gap:2rem; }

/* Alert */
.alert { padding:.875rem 1.25rem; border-radius:8px; font-size:.875rem; font-weight:500; }
.alert.ok  { background:var(--green-bg); color:var(--green); border-left:4px solid var(--green); }
.alert.err { background:var(--red-bg); color:var(--red); border-left:4px solid var(--red); }

/* Secret box (guest pass) */
.secret-box { background:var(--navy-950); border-radius:10px; padding:1.5rem; color:#fff; position:relative; }
.secret-box h3 { font-family:'Manrope',system-ui,sans-serif; font-size:1rem; margin-bottom:.75rem; color:var(--gold-500); }
.secret-grid { display:grid; grid-template-columns:1fr 1fr; gap:.75rem 1.5rem; }
.secret-grid .lbl { font-size:.7rem; color:var(--silver-500); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.2rem; }
.secret-grid .val { font-family:'IBM Plex Mono',monospace,system-ui; font-size:.95rem; color:#fff; font-weight:700; background:rgba(255,255,255,.07); padding:.4rem .75rem; border-radius:6px; word-break:break-all; }
.warning-note { margin-top:1rem; font-size:.78rem; color:#f59e0b; display:flex; align-items:center; gap:.5rem; }

/* Cards grid */
.section-title { font-family:'Manrope',system-ui,sans-serif; font-size:1.1rem; font-weight:800; color:var(--navy-900); margin-bottom:1rem; }

/* User table */
.table-wrap { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(7,21,35,.07); overflow:hidden; }
table { width:100%; border-collapse:collapse; font-size:.875rem; }
thead { background:var(--navy-950); color:#fff; }
thead th { padding:.75rem 1rem; text-align:left; font-size:.7rem; letter-spacing:.07em; text-transform:uppercase; font-weight:500; font-family:'IBM Plex Mono',monospace,system-ui; }
tbody tr { border-bottom:1px solid var(--silver-100); }
tbody tr:last-child { border-bottom:none; }
td { padding:.85rem 1rem; vertical-align:middle; }
.badge-rol { display:inline-block; padding:.2rem .6rem; border-radius:999px; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.badge-rol.admin { background:var(--navy-800); color:var(--gold-500); }
.badge-rol.guest { background:var(--amber-bg); color:var(--amber); }
.badge-activo { display:inline-block; padding:.2rem .6rem; border-radius:999px; font-size:.68rem; font-weight:700; }
.badge-activo.si { background:var(--green-bg); color:var(--green); }
.badge-activo.no { background:var(--red-bg); color:var(--red); }
.expires-label { font-size:.78rem; color:var(--silver-500); }
.expires-label.vencido { color:var(--red); }
.action-btns { display:flex; gap:.5rem; }
.btn-sm { padding:.3rem .7rem; border:none; border-radius:6px; font-size:.75rem; font-weight:600; cursor:pointer; }
.btn-toggle { background:var(--silver-100); color:var(--ink); }
.btn-toggle:hover { background:var(--silver-300); }
.btn-delete { background:var(--red-bg); color:var(--red); }
.btn-delete:hover { background:#fca5a5; }
.me-badge { font-size:.68rem; background:var(--navy-800); color:#fff; padding:.15rem .5rem; border-radius:999px; margin-left:.4rem; }

/* Forms */
.forms-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
@media (max-width:700px) { .forms-grid { grid-template-columns:1fr; } }
.form-card { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(7,21,35,.07); padding:1.5rem; }
.form-card h3 { font-family:'Manrope',system-ui,sans-serif; font-size:.95rem; font-weight:800; margin-bottom:1rem; color:var(--navy-900); display:flex; align-items:center; gap:.5rem; }
.form-group { margin-bottom:.875rem; }
.form-group label { display:block; font-size:.75rem; font-weight:600; color:var(--silver-500); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.35rem; }
.form-group input, .form-group select { width:100%; padding:.65rem .875rem; border:1.5px solid var(--silver-300); border-radius:8px; font-size:.875rem; outline:none; transition:border-color .2s; }
.form-group input:focus, .form-group select:focus { border-color:var(--navy-700); }
.btn-primary { width:100%; padding:.7rem; background:var(--navy-900); color:#fff; border:none; border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; transition:background .2s; margin-top:.25rem; }
.btn-primary:hover { background:var(--navy-700); }
.btn-guest { background:var(--amber); }
.btn-guest:hover { background:var(--amber-bg); color:var(--amber); }
.help-text { font-size:.75rem; color:var(--silver-500); margin-top:.35rem; }

@media (max-width:768px) { .topbar { padding:.75rem 1rem; } .main { padding:1.25rem 1rem; } }
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">Sanalia &amp; Asociados &mdash; <span>CRM</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="nav-tab">Dashboard</a>
    <a href="leads.php"     class="nav-tab">Contactos</a>
    <a href="calendar.php"  class="nav-tab">Calendario</a>
    <a href="import.php"    class="nav-tab">Importar</a>
    <a href="users.php"     class="nav-tab active">Usuarios</a>
  </div>
  <div style="display:flex;align-items:center;gap:.75rem">
    <?php if (!empty($_SESSION['user_nombre'])): ?>
    <span style="font-size:.8rem;color:rgba(255,255,255,.6)"><?= htmlspecialchars($_SESSION['user_nombre']) ?></span>
    <?php endif; ?>
    <form method="POST" action="index.php" style="margin:0">
      <button type="submit" name="logout" class="btn-outline">Salir</button>
    </form>
  </div>
</div>

<div class="main">

<?php if ($guest_created): ?>
<!-- Credenciales de guest recién creado — mostrar solo UNA vez -->
<div class="secret-box">
  <h3>⚠ Credenciales del usuario temporal — guardar antes de cerrar</h3>
  <div class="secret-grid">
    <div>
      <div class="lbl">Nombre</div>
      <div class="val"><?= htmlspecialchars($guest_created['nombre']) ?></div>
    </div>
    <div>
      <div class="lbl">Email</div>
      <div class="val"><?= htmlspecialchars($guest_created['email']) ?></div>
    </div>
    <div>
      <div class="lbl">Contraseña temporal</div>
      <div class="val" id="guestPass"><?= htmlspecialchars($guest_created['pass']) ?></div>
    </div>
    <div>
      <div class="lbl">Expira en</div>
      <div class="val"><?= (int)$guest_created['minutos'] ?> min &mdash; <?= htmlspecialchars($guest_created['expires']) ?></div>
    </div>
  </div>
  <p class="warning-note">⚡ Esta contraseña NO se puede recuperar luego. Compártela ahora con el usuario.</p>
  <button onclick="navigator.clipboard.writeText(document.getElementById('guestPass').textContent).then(()=>this.textContent='✓ Copiado')"
          style="margin-top:.75rem;padding:.4rem 1rem;border:none;border-radius:6px;background:var(--gold-500);color:var(--navy-950);font-weight:700;cursor:pointer;font-size:.8rem">
    Copiar contraseña
  </button>
</div>
<?php endif; ?>

<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Lista de usuarios -->
<div>
  <div class="section-title">Usuarios registrados</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Email</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Expira</th>
          <th>Creado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--silver-500)">No hay usuarios registrados aún.</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u):
          $is_me     = (int)$u['id'] === ($_SESSION['user_id'] ?? -1);
          $is_exp    = $u['expires_at'] && strtotime($u['expires_at']) < time();
          $exp_label = $u['expires_at']
            ? ($is_exp ? '⛔ Vencido' : '⏱ ' . $u['expires_at'])
            : '—';
        ?>
        <tr>
          <td style="font-weight:600">
            <?= htmlspecialchars($u['nombre']) ?>
            <?php if ($is_me): ?><span class="me-badge">Tú</span><?php endif; ?>
          </td>
          <td style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge-rol <?= $u['rol'] ?>"><?= $u['rol'] === 'admin' ? 'Admin' : 'Guest' ?></span></td>
          <td><span class="badge-activo <?= $u['activo'] ? 'si' : 'no' ?>"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
          <td><span class="expires-label<?= $is_exp ? ' vencido' : '' ?>"><?= htmlspecialchars($exp_label) ?></span></td>
          <td style="font-size:.78rem;color:var(--silver-500)"><?= substr($u['created_at'], 0, 16) ?></td>
          <td>
            <?php if (!$is_me): ?>
            <div class="action-btns">
              <form method="POST" style="margin:0">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn-sm btn-toggle">
                  <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                </button>
              </form>
              <form method="POST" style="margin:0" onsubmit="return confirm('¿Eliminar a <?= htmlspecialchars(addslashes($u['nombre'])) ?>?')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn-sm btn-delete">Eliminar</button>
              </form>
            </div>
            <?php else: ?>
            <span style="font-size:.75rem;color:var(--silver-500)">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Formularios de creación -->
<div>
  <div class="section-title">Crear nuevo usuario</div>
  <div class="forms-grid">

    <!-- Admin user -->
    <div class="form-card">
      <h3>🔑 Usuario Administrador</h3>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="create_admin">
        <div class="form-group">
          <label>Nombre completo</label>
          <input type="text" name="nombre" required maxlength="150" placeholder="Ej: María González">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required placeholder="usuario@empresa.com">
        </div>
        <div class="form-group">
          <label>Contraseña</label>
          <input type="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres">
          <p class="help-text">El usuario podrá acceder a todo el CRM sin restricciones de tiempo.</p>
        </div>
        <button type="submit" class="btn-primary">Crear administrador</button>
      </form>
    </div>

    <!-- Guest user -->
    <div class="form-card">
      <h3>⏳ Usuario Temporal (Guest)</h3>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="create_guest">
        <div class="form-group">
          <label>Nombre completo</label>
          <input type="text" name="nombre" required maxlength="150" placeholder="Ej: Auditor Externo">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required placeholder="auditor@empresa.com">
        </div>
        <div class="form-group">
          <label>Duración del acceso (minutos)</label>
          <select name="minutos">
            <option value="30">30 minutos</option>
            <option value="60">60 minutos</option>
            <option value="90" selected>90 minutos</option>
            <option value="120">2 horas</option>
            <option value="240">4 horas</option>
            <option value="480">8 horas (jornada)</option>
          </select>
          <p class="help-text">La contraseña se genera automáticamente y se muestra una sola vez.</p>
        </div>
        <button type="submit" class="btn-primary btn-guest">Generar acceso temporal</button>
      </form>
    </div>

  </div>
</div>

</div><!-- /.main -->
</body>
</html>
