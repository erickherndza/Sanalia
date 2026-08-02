<?php
/**
 * Sanalia & Asociados — Endpoint de contacto
 * POST /api/contact.php
 *
 * Notificación interna: mail() nativo → themethoner@gmail.com
 * Auto-respuesta al cliente: mail() nativo → email del solicitante
 */

declare(strict_types=1);

/* ── Helpers ──────────────────────────────────────────────────── */

function json_out(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize_str(string $value): string {
    return trim(strip_tags((string) filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS)));
}

/* ── Método ───────────────────────────────────────────────────── */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Método no permitido'], 405);
}

/* ── Configuración ────────────────────────────────────────────── */

$mail_notif     = 'themethoner@gmail.com';
$mail_reply_to  = 'info@sanaliayasociados.com';
$rate_limit_max = 50;
$rate_limit_ttl = 3600;
$rate_limit_dir = __DIR__ . '/../storage/rate/';
$log_file       = __DIR__ . '/../storage/logs/contact.log';

/* ── Honeypot ─────────────────────────────────────────────────── */

if (!empty($_POST['campo_control'])) {
    json_out(['ok' => true]);
}

/* ── Rate Limiting por IP ─────────────────────────────────────── */

$rate_dir = rtrim($rate_limit_dir, '/');
if (!is_dir($rate_dir)) @mkdir($rate_dir, 0755, true);

$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$client_ip = filter_var(explode(',', $client_ip)[0], FILTER_VALIDATE_IP) ?: '0.0.0.0';
$rate_file = $rate_dir . '/' . md5($client_ip) . '.json';
$now       = time();

$rate_data = ['count' => 0, 'window_start' => $now];
if (file_exists($rate_file)) {
    $raw = @json_decode(file_get_contents($rate_file), true);
    if (is_array($raw)) $rate_data = $raw;
}
if ($now - $rate_data['window_start'] > $rate_limit_ttl) {
    $rate_data = ['count' => 0, 'window_start' => $now];
}
if ($rate_data['count'] >= $rate_limit_max) {
    json_out(['ok' => false, 'message' => 'Demasiados envíos. Inténtalo más tarde.'], 429);
}

/* ── Leer y sanitizar campos ──────────────────────────────────── */

$nombre   = sanitize_str($_POST['nombre']   ?? '');
$email    = trim((string) filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$telefono = sanitize_str($_POST['telefono'] ?? '');
$interes  = sanitize_str($_POST['interes']  ?? '');
$mensaje  = sanitize_str($_POST['mensaje']  ?? '');

/* ── Validación servidor ──────────────────────────────────────── */

$errors = [];

if ($nombre === '') {
    $errors['nombre'] = 'El nombre es requerido';
} elseif (strlen($nombre) < 3) {
    $errors['nombre'] = 'Mínimo 3 caracteres';
} elseif (preg_match('/\d/', $nombre)) {
    $errors['nombre'] = 'El nombre no debe contener números';
}

if ($email === '') {
    $errors['email'] = 'El email es requerido';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Email inválido';
}

if ($telefono === '') {
    $errors['telefono'] = 'El teléfono es requerido';
} else {
    $tel_clean = preg_replace('/[\s\-().+]/', '', $telefono);
    if (!preg_match('/^1?(809|829|849)\d{7}$/', $tel_clean)) {
        $errors['telefono'] = 'Teléfono dominicano inválido (809/829/849)';
    }
}

$opciones_validas = [
    'vida', 'salud-persona', 'viajes', 'vehiculos',
    'salud', 'accidentes-personales', 'internacionales',
    'riesgos-generales', 'mascotas', 'otro',
];
if ($interes === '') {
    $errors['interes'] = 'Selecciona una línea de interés';
} elseif (!in_array($interes, $opciones_validas, true)) {
    $errors['interes'] = 'Opción inválida';
}

if ($mensaje === '') {
    $errors['mensaje'] = 'El mensaje es requerido';
} elseif (mb_strlen($mensaje) < 15) {
    $errors['mensaje'] = 'Mínimo 15 caracteres';
} elseif (mb_strlen($mensaje) > 800) {
    $errors['mensaje'] = 'Máximo 800 caracteres';
}

if (!empty($errors)) {
    json_out(['ok' => false, 'errors' => $errors], 422);
}

/* ── Log y respaldo JSON ──────────────────────────────────────── */

$log_dir = dirname($log_file);
if (!is_dir($log_dir)) @mkdir($log_dir, 0755, true);
$log_entry = implode(' | ', [date('Y-m-d H:i:s'), $client_ip, $interes, mb_substr($nombre, 0, 30)]) . "\n";
@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

$submissions_dir = __DIR__ . '/../storage/submissions/';
if (!is_dir($submissions_dir)) @mkdir($submissions_dir, 0755, true);
@file_put_contents(
    $submissions_dir . date('Y-m') . '.json',
    json_encode([
        'fecha'    => date('Y-m-d H:i:s'),
        'nombre'   => $nombre,
        'email'    => $email,
        'telefono' => $telefono,
        'interes'  => $interes,
        'mensaje'  => $mensaje,
    ], JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX
);

/* ── Rate limit: incrementar ──────────────────────────────────── */

$rate_data['count']++;
@file_put_contents($rate_file, json_encode($rate_data), LOCK_EX);

/* ── Notificación interna → Gmail ─────────────────────────────── */

$notif_subject = '=?UTF-8?B?' . base64_encode("Nuevo contacto web — {$interes}") . '?=';
$notif_body    =
    "Nueva consulta desde sanaliayasociados.com\r\n" .
    str_repeat('-', 48) . "\r\n" .
    "Nombre:   {$nombre}\r\n" .
    "Email:    {$email}\r\n" .
    "Telefono: {$telefono}\r\n" .
    "Interes:  {$interes}\r\n" .
    str_repeat('-', 48) . "\r\n" .
    "Mensaje:\r\n{$mensaje}\r\n";

$notif_headers  = "MIME-Version: 1.0\r\n";
$notif_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$notif_headers .= "From: Sitio Web Sanalia <noreply@sanaliayasociados.com>\r\n";
$notif_headers .= "Reply-To: {$nombre} <{$email}>\r\n";

@mail($mail_notif, $notif_subject, $notif_body, $notif_headers);

/* ── Auto-respuesta al solicitante ────────────────────────────── */

$map_interes = [
    'vida'                  => 'Seguro de Vida',
    'salud-persona'         => 'Seguro de Salud Personal',
    'viajes'                => 'Asistencia en Viaje',
    'vehiculos'             => 'Seguro de Vehículos',
    'salud'                 => 'Seguro de Salud',
    'accidentes-personales' => 'Seguro de Accidentes Personales',
    'internacionales'       => 'Seguro Medico Internacional',
    'riesgos-generales'     => 'Riesgos Generales Empresariales',
    'mascotas'              => 'Seguro de Mascotas',
    'otro'                  => 'Nuestros Servicios',
];
$servicio_nombre = $map_interes[$interes] ?? 'Nuestros Servicios';

$auto_subject = '=?UTF-8?B?' . base64_encode("Sanalia & Asociados — Recibimos tu solicitud") . '?=';
$auto_body    =
    "Estimado/a {$nombre},\r\n\r\n" .
    "Gracias por comunicarte con Sanalia & Asociados y por tu interes en {$servicio_nombre}.\r\n\r\n" .
    "Hemos recibido tu solicitud correctamente. Nuestro equipo de asesores\r\n" .
    "analizara tu consulta y te respondera a la brevedad posible.\r\n\r\n" .
    "Si tienes alguna consulta urgente, puedes contactarnos directamente:\r\n" .
    "  Telefono: (809) 362-4357\r\n" .
    "  WhatsApp: (829) 669-5001 / (829) 616-4585\r\n" .
    "  Horario:  Lunes a Viernes 8:00 AM - 5:00 PM\r\n" .
    "            Sabados 8:30 AM - 12:30 PM\r\n\r\n" .
    "Atentamente,\r\n" .
    "Sanalia & Asociados, S.R.L.\r\n" .
    "Sientete mas que seguro. Somos soluciones.\r\n" .
    "www.sanaliayasociados.com\r\n";

$auto_headers  = "MIME-Version: 1.0\r\n";
$auto_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$auto_headers .= "From: Sanalia & Asociados <noreply@sanaliayasociados.com>\r\n";
$auto_headers .= "Reply-To: Sanalia & Asociados <{$mail_reply_to}>\r\n";

@mail($email, $auto_subject, $auto_body, $auto_headers);

/* ── Respuesta exitosa ────────────────────────────────────────── */

json_out(['ok' => true]);
