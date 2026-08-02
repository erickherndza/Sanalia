<?php
/**
 * Sanalia & Asociados — Endpoint de contacto
 * POST /api/contact.php
 *
 * Notificación interna: PHPMailer via smtp.office365.com (Microsoft 365)
 * Auto-respuesta al cliente: mail() nativo de PHP
 */

declare(strict_types=1);

$vendor_path = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendor_path)) {
    require $vendor_path;
}

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

$config_path = __DIR__ . '/config.php';
if (!file_exists($config_path)) {
    error_log('[contact.php] config.php no encontrado');
    json_out(['ok' => false, 'message' => 'Error de configuración del servidor'], 500);
}
$cfg = require $config_path;

/* ── Honeypot ─────────────────────────────────────────────────── */

if (!empty($_POST['campo_control'])) {
    json_out(['ok' => true]);
}

/* ── Rate Limiting por IP ─────────────────────────────────────── */

$rate_dir = rtrim($cfg['rate_limit_dir'] ?? (__DIR__ . '/../storage/rate/'), '/');
if (!is_dir($rate_dir)) {
    @mkdir($rate_dir, 0755, true);
}

$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$client_ip = filter_var(explode(',', $client_ip)[0], FILTER_VALIDATE_IP) ?: '0.0.0.0';
$rate_file = $rate_dir . '/' . md5($client_ip) . '.json';
$now       = time();

$rate_data = ['count' => 0, 'window_start' => $now];
if (file_exists($rate_file)) {
    $raw = @json_decode(file_get_contents($rate_file), true);
    if (is_array($raw)) $rate_data = $raw;
}
if ($now - $rate_data['window_start'] > (int)($cfg['rate_limit_ttl'] ?? 3600)) {
    $rate_data = ['count' => 0, 'window_start' => $now];
}
if ($rate_data['count'] >= (int)($cfg['rate_limit_max'] ?? 5)) {
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

/* ── Log del envío ────────────────────────────────────────────── */

$log_file = $cfg['log_file'] ?? (__DIR__ . '/../storage/logs/contact.log');
$log_dir  = dirname($log_file);
if (!is_dir($log_dir)) @mkdir($log_dir, 0755, true);
$log_entry = implode(' | ', [date('Y-m-d H:i:s'), $client_ip, $interes, mb_substr($nombre, 0, 30)]) . "\n";
@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

/* ── Rate limit: incrementar ──────────────────────────────────── */

$rate_data['count']++;
@file_put_contents($rate_file, json_encode($rate_data), LOCK_EX);

/* ── Notificación interna via PHPMailer + Microsoft 365 ───────── */

$mail_to = $cfg['mail_to'] ?? 'info@sanaliayasociados.com';

if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    try {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host       = $cfg['smtp_host'];
        $mailer->SMTPAuth   = true;
        $mailer->Username   = $cfg['smtp_user'];
        $mailer->Password   = $cfg['smtp_pass'];
        $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port       = (int)$cfg['smtp_port'];
        $mailer->CharSet    = 'UTF-8';
        $mailer->Timeout    = 15;

        $mailer->setFrom($cfg['smtp_user'], 'Sitio Web — Sanalia & Asociados');
        $mailer->addAddress($mail_to);
        $mailer->addReplyTo($email, $nombre);

        $mailer->Subject = "Nuevo contacto desde el sitio web — {$interes}";
        $mailer->isHTML(false);
        $mailer->Body =
            "Nueva consulta recibida desde sanaliayasociados.com\r\n" .
            str_repeat('-', 50) . "\r\n" .
            "Nombre:   {$nombre}\r\n" .
            "Email:    {$email}\r\n" .
            "Telefono: {$telefono}\r\n" .
            "Interes:  {$interes}\r\n" .
            str_repeat('-', 50) . "\r\n" .
            "Mensaje:\r\n{$mensaje}\r\n";

        $mailer->send();
    } catch (\Exception $e) {
        error_log('[contact.php] PHPMailer error: ' . $e->getMessage());
        // Fallback a mail() nativo si PHPMailer falla
        $subj  = '=?UTF-8?B?' . base64_encode("Nuevo contacto — {$interes}") . '?=';
        $body  = "Nombre: {$nombre}\nEmail: {$email}\nTelefono: {$telefono}\nInteres: {$interes}\n\n{$mensaje}";
        $hdrs  = "From: noreply@sanaliayasociados.com\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($mail_to, $subj, $body, $hdrs, '-fnoreply@sanaliayasociados.com');
    }
} else {
    // Sin PHPMailer: mail() nativo
    $subj = '=?UTF-8?B?' . base64_encode("Nuevo contacto — {$interes}") . '?=';
    $body = "Nombre: {$nombre}\nEmail: {$email}\nTelefono: {$telefono}\nInteres: {$interes}\n\n{$mensaje}";
    $hdrs = "From: noreply@sanaliayasociados.com\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($mail_to, $subj, $body, $hdrs, '-fnoreply@sanaliayasociados.com');
}

/* ── Auto-respuesta al solicitante via mail() ─────────────────── */

$map_interes = [
    'vida'                  => 'Seguro de Vida',
    'salud-persona'         => 'Seguro de Salud Personal',
    'viajes'                => 'Asistencia en Viaje',
    'vehiculos'             => 'Seguro de Vehículos',
    'salud'                 => 'Seguro de Salud',
    'accidentes-personales' => 'Seguro de Accidentes Personales',
    'internacionales'       => 'Seguro Médico Internacional',
    'riesgos-generales'     => 'Riesgos Generales Empresariales',
    'mascotas'              => 'Seguro de Mascotas',
    'otro'                  => 'Nuestros Servicios',
];
$servicio_nombre = $map_interes[$interes] ?? 'Nuestros Servicios';

$auto_subject = '=?UTF-8?B?' . base64_encode("Sanalia & Asociados — Recibimos tu solicitud") . '?=';

$auto_body  = "Estimado/a {$nombre},\r\n\r\n";
$auto_body .= "Gracias por comunicarte con Sanalia & Asociados y por tu interes en {$servicio_nombre}.\r\n\r\n";
$auto_body .= "Hemos recibido tu solicitud correctamente. Nuestro equipo de asesores\r\n";
$auto_body .= "analizara tu consulta y te respondera a la brevedad posible.\r\n\r\n";
$auto_body .= "Si tienes alguna consulta urgente, puedes contactarnos directamente:\r\n";
$auto_body .= "  Telefono: (809) 362-4357\r\n";
$auto_body .= "  WhatsApp: (829) 669-5001 / (829) 616-4585\r\n";
$auto_body .= "  Horario:  Lunes a Viernes 8:00 AM - 5:00 PM\r\n";
$auto_body .= "            Sabados 8:30 AM - 12:30 PM\r\n\r\n";
$auto_body .= "Atentamente,\r\n";
$auto_body .= "Sanalia & Asociados, S.R.L.\r\n";
$auto_body .= "Sientete mas que seguro. Somos soluciones.\r\n";
$auto_body .= "www.sanaliayasociados.com\r\n";

$auto_headers  = "MIME-Version: 1.0\r\n";
$auto_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$auto_headers .= "From: Sanalia & Asociados <noreply@sanaliayasociados.com>\r\n";
$auto_headers .= "Reply-To: Sanalia & Asociados <{$mail_to}>\r\n";
$auto_headers .= "X-Mailer: PHP/" . phpversion();

@mail($email, $auto_subject, $auto_body, $auto_headers);

/* ── Respuesta exitosa ────────────────────────────────────────── */

json_out(['ok' => true]);
