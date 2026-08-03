<?php
/**
 * Sanalia CRM — Finanzas (módulo desactivado)
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) { header('Location: index.php'); exit; }
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
  --gold-500:#C6A15B; --silver-100:#F3F5F7; --silver-300:#DCE1E7; --silver-500:#AEB8C4;
  --ink:#0E1620;
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
.main { max-width:900px; margin:4rem auto; padding:2rem 1.5rem; text-align:center; }
.disabled-box { background:#fff; border-radius:16px; box-shadow:0 1px 8px rgba(7,21,35,.08); padding:3rem 2rem; }
.disabled-icon { font-size:3rem; margin-bottom:1rem; color:var(--silver-500); }
.disabled-title { font-family:'Manrope',system-ui,sans-serif; font-weight:800; font-size:1.5rem; color:var(--navy-900); margin-bottom:.75rem; }
.disabled-text { color:var(--silver-500); font-size:.95rem; max-width:500px; margin:0 auto 2rem; line-height:1.6; }
.btn-go { background:var(--navy-700); color:#fff; border:none; padding:.65rem 1.5rem; border-radius:10px; font-size:.9rem; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
.btn-go:hover { background:var(--navy-950); }
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
  </div>
  <div class="topbar-actions">
    <form method="POST" action="index.php" style="margin:0">
      <button type="submit" name="logout" class="btn-outline">Salir</button>
    </form>
  </div>
</div>

<div class="main">
  <div class="disabled-box">
    <div class="disabled-icon">🔒</div>
    <div class="disabled-title">Módulo no disponible</div>
    <div class="disabled-text">
      El módulo de Finanzas ha sido desactivado en esta versión del CRM.<br>
      Este sistema está enfocado en la gestión de contactos y pipeline de campañas publicitarias.
    </div>
    <a href="dashboard.php" class="btn-go">Ir al Dashboard</a>
  </div>
</div>

</body>
</html>
