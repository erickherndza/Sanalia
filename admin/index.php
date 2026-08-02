<?php
/**
 * Sanalia & Asociados — Panel de solicitudes
 * Acceso: /admin/  →  contraseña requerida
 */

declare(strict_types=1);

session_start();

/* ── Configuración ───────────────────────────────────────────── */

// Para cambiar la contraseña, reemplaza este valor y actualiza ADMIN_HASH
// con el resultado de: php -r "echo password_hash('NuevaContraseña', PASSWORD_DEFAULT);"
define('ADMIN_HASH', '$2y$12$QwErTyUiOpAsDfGhJkLzXcVbNmQwErTyUiOpAsDfGhJkLzXcVbNm12'); // placeholder
define('ADMIN_PASSWORD_PLAIN', 'Sanalia2026!'); // contraseña inicial — cambia después del primer acceso

$submissions_dir = __DIR__ . '/../storage/submissions/';
$error           = '';

/* ── Autenticación ───────────────────────────────────────────── */

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD_PLAIN) {
        $_SESSION['sanalia_admin'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $error = 'Contraseña incorrecta.';
}

$auth = !empty($_SESSION['sanalia_admin']);

/* ── Cargar solicitudes ──────────────────────────────────────── */

$submissions = [];
if ($auth && is_dir($submissions_dir)) {
    foreach (glob($submissions_dir . '*.json') as $file) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry)) {
                $submissions[] = $entry;
            }
        }
    }
    usort($submissions, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
}

/* ── Mapa de interés → etiqueta ──────────────────────────────── */

$map_interes = [
    'vida'                  => 'Vida',
    'salud-persona'         => 'Salud Personal',
    'viajes'                => 'Asistencia en Viaje',
    'vehiculos'             => 'Vehículos',
    'salud'                 => 'Salud',
    'accidentes-personales' => 'Accidentes Personales',
    'internacionales'       => 'Médico Internacional',
    'riesgos-generales'     => 'Riesgos Generales',
    'mascotas'              => 'Mascotas',
    'otro'                  => 'Otro',
];

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel de Solicitudes — Sanalia & Asociados</title>
<meta name="robots" content="noindex, nofollow">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy-950: #071523;
    --navy-900: #0C2036;
    --navy-800: #153350;
    --navy-700: #1E4468;
    --gold-500: #C6A15B;
    --gold-600: #A9843F;
    --silver-100: #F3F5F7;
    --silver-300: #DCE1E7;
    --silver-500: #AEB8C4;
    --ink: #0E1620;
    --paper: #F7F7F5;
  }

  body {
    font-family: 'Inter', system-ui, sans-serif;
    background: var(--silver-100);
    color: var(--ink);
    min-height: 100vh;
  }

  /* ── Login ── */
  .login-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 2rem;
  }

  .login-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(7,21,35,.12);
    padding: 2.5rem 2rem;
    width: 100%;
    max-width: 360px;
    text-align: center;
  }

  .login-card .brand {
    font-family: 'Manrope', system-ui, sans-serif;
    font-weight: 800;
    font-size: 1.25rem;
    color: var(--navy-900);
    margin-bottom: .25rem;
  }

  .login-card .sub {
    font-size: .8rem;
    color: var(--silver-500);
    margin-bottom: 2rem;
    letter-spacing: .05em;
    text-transform: uppercase;
  }

  .login-card input[type="password"] {
    width: 100%;
    padding: .75rem 1rem;
    border: 1.5px solid var(--silver-300);
    border-radius: 8px;
    font-size: 1rem;
    outline: none;
    transition: border-color .2s;
    margin-bottom: 1rem;
  }

  .login-card input[type="password"]:focus {
    border-color: var(--navy-700);
  }

  .btn-login {
    width: 100%;
    padding: .75rem;
    background: var(--navy-900);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
  }

  .btn-login:hover { background: var(--navy-700); }

  .login-error {
    margin-top: 1rem;
    color: #c0392b;
    font-size: .875rem;
  }

  /* ── Panel ── */
  .topbar {
    background: var(--navy-900);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .875rem 2rem;
    gap: 1rem;
  }

  .topbar .brand {
    font-family: 'Manrope', system-ui, sans-serif;
    font-weight: 800;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: .75rem;
  }

  .topbar .brand span {
    color: var(--gold-500);
  }

  .btn-logout {
    background: transparent;
    border: 1.5px solid rgba(255,255,255,.3);
    color: #fff;
    padding: .4rem .875rem;
    border-radius: 6px;
    font-size: .8rem;
    cursor: pointer;
    transition: border-color .2s;
  }

  .btn-logout:hover { border-color: #fff; }

  .main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
  }

  .stats {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
  }

  .stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
    flex: 1;
    min-width: 140px;
    box-shadow: 0 1px 6px rgba(7,21,35,.07);
  }

  .stat-card .num {
    font-family: 'IBM Plex Mono', monospace, system-ui;
    font-size: 2rem;
    font-weight: 700;
    color: var(--navy-900);
    line-height: 1;
  }

  .stat-card .label {
    font-size: .78rem;
    color: var(--silver-500);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-top: .35rem;
  }

  .section-title {
    font-family: 'Manrope', system-ui, sans-serif;
    font-weight: 700;
    font-size: 1rem;
    color: var(--navy-900);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
  }

  .section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--silver-300);
  }

  /* ── Tabla ── */
  .table-wrap {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 6px rgba(7,21,35,.07);
    overflow: hidden;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: .875rem;
  }

  thead {
    background: var(--navy-950);
    color: #fff;
  }

  thead th {
    padding: .75rem 1rem;
    text-align: left;
    font-family: 'IBM Plex Mono', monospace, system-ui;
    font-size: .72rem;
    letter-spacing: .07em;
    text-transform: uppercase;
    font-weight: 500;
    white-space: nowrap;
  }

  tbody tr {
    border-bottom: 1px solid var(--silver-100);
    transition: background .15s;
  }

  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: #f0f4f8; }

  td {
    padding: .875rem 1rem;
    vertical-align: top;
    line-height: 1.5;
  }

  td.fecha {
    font-family: 'IBM Plex Mono', monospace, system-ui;
    font-size: .75rem;
    color: var(--silver-500);
    white-space: nowrap;
  }

  td.nombre { font-weight: 600; color: var(--navy-900); }

  td.email a {
    color: var(--navy-700);
    text-decoration: none;
  }

  td.email a:hover { text-decoration: underline; }

  td.tel {
    font-family: 'IBM Plex Mono', monospace, system-ui;
    font-size: .8rem;
    white-space: nowrap;
  }

  .badge {
    display: inline-block;
    padding: .25rem .625rem;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    background: var(--navy-800);
    color: var(--gold-500);
    white-space: nowrap;
  }

  td.mensaje {
    max-width: 280px;
    color: #444;
    font-size: .82rem;
  }

  .empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--silver-500);
    font-size: .9rem;
  }

  /* ── Responsive ── */
  @media (max-width: 768px) {
    .topbar { padding: .75rem 1rem; }
    .main { padding: 1.25rem 1rem; }

    table { font-size: .8rem; }
    thead th, td { padding: .625rem .75rem; }

    td.mensaje { max-width: 160px; }
  }
</style>
</head>
<body>

<?php if (!$auth): ?>

<div class="login-wrap">
  <div class="login-card">
    <div class="brand">Sanalia &amp; Asociados</div>
    <div class="sub">Panel de Solicitudes</div>
    <form method="POST" autocomplete="off">
      <input type="password" name="password" placeholder="Contraseña" autofocus required>
      <button type="submit" class="btn-login">Entrar</button>
    </form>
    <?php if ($error): ?>
      <p class="login-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>

<div class="topbar">
  <div class="brand">
    Sanalia &amp; Asociados &mdash; <span>Solicitudes recibidas</span>
  </div>
  <form method="POST">
    <button type="submit" name="logout" class="btn-logout">Cerrar sesión</button>
  </form>
</div>

<div class="main">

  <?php
    $total    = count($submissions);
    $este_mes = 0;
    $mes_actual = date('Y-m');
    foreach ($submissions as $s) {
      if (str_starts_with($s['fecha'], $mes_actual)) $este_mes++;
    }
    $hoy = 0;
    $hoy_str = date('Y-m-d');
    foreach ($submissions as $s) {
      if (str_starts_with($s['fecha'], $hoy_str)) $hoy++;
    }
  ?>

  <div class="stats">
    <div class="stat-card">
      <div class="num"><?= $total ?></div>
      <div class="label">Total solicitudes</div>
    </div>
    <div class="stat-card">
      <div class="num"><?= $este_mes ?></div>
      <div class="label">Este mes</div>
    </div>
    <div class="stat-card">
      <div class="num"><?= $hoy ?></div>
      <div class="label">Hoy</div>
    </div>
  </div>

  <div class="section-title">Todas las solicitudes</div>

  <div class="table-wrap">
    <?php if (empty($submissions)): ?>
      <div class="empty">No hay solicitudes registradas aún.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Nombre</th>
          <th>Email</th>
          <th>Teléfono</th>
          <th>Interés</th>
          <th>Mensaje</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $s): ?>
        <tr>
          <td class="fecha"><?= htmlspecialchars($s['fecha'] ?? '') ?></td>
          <td class="nombre"><?= htmlspecialchars($s['nombre'] ?? '') ?></td>
          <td class="email">
            <a href="mailto:<?= htmlspecialchars($s['email'] ?? '') ?>">
              <?= htmlspecialchars($s['email'] ?? '') ?>
            </a>
          </td>
          <td class="tel"><?= htmlspecialchars($s['telefono'] ?? '') ?></td>
          <td>
            <span class="badge">
              <?= htmlspecialchars($map_interes[$s['interes'] ?? ''] ?? ($s['interes'] ?? '')) ?>
            </span>
          </td>
          <td class="mensaje"><?= nl2br(htmlspecialchars(mb_substr($s['mensaje'] ?? '', 0, 200))) ?><?= mb_strlen($s['mensaje'] ?? '') > 200 ? '…' : '' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>

<?php endif; ?>

</body>
</html>
