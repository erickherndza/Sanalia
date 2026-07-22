<?php
/**
 * Sanalia & Asociados — Endpoint de contacto
 * POST /api/contact.php
 *
 * Responde siempre JSON: {ok:true} | {ok:false, errors:{campo:"msg"}}
 * Status 200 (incluyendo honeypot trap) / 405 (método no POST) / 422 (validación) / 429 (rate limit)
 *
 * Requiere: config.php (copiado de config.php.example, fuera del repo)
 *           vendor/autoload.php (PHPMailer vía Composer — instalar con: cd api && composer install)
 */

declare(strict_types=1);

// PHPMailer: cargar clases si vendor está disponible
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

/* ── Config ───────────────────────────────────────────────────── */

$config_path = __DIR__ . '/config.php';
if (!file_exists($config_path)) {
    error_log('[contact.php] config.php no encontrado');
    json_out(['ok' => false, 'message' => 'Error de configuración del servidor'], 500);
}
$cfg = require $config_path;

/* ── Honeypot ─────────────────────────────────────────────────── */
// Responder 200 genérico sin enviar correo — no delatar al bot
if (!empty($_POST['campo_control'])) {
    json_out(['ok' => true]);
}

/* ── Rate Limiting (archivo plano por IP) ─────────────────────── */

$rate_dir = rtrim($cfg['rate_limit_dir'] ?? (__DIR__ . '/../storage/rate/'), '/');
if (!is_dir($rate_dir)) {
    @mkdir($rate_dir, 0755, true);
}

$client_ip   = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$client_ip   = filter_var(explode(',', $client_ip)[0], FILTER_VALIDATE_IP) ?: '0.0.0.0';
$rate_file   = $rate_dir . '/' . md5($client_ip) . '.json';
$rate_max    = (int)($cfg['rate_limit_max'] ?? 5);
$rate_ttl    = (int)($cfg['rate_limit_ttl'] ?? 3600);
$now         = time();

$rate_data = ['count' => 0, 'window_start' => $now];
if (file_exists($rate_file)) {
    $raw = @json_decode(file_get_contents($rate_file), true);
    if (is_array($raw)) $rate_data = $raw;
}

// Reiniciar ventana si expiró
if ($now - $rate_data['window_start'] > $rate_ttl) {
    $rate_data = ['count' => 0, 'window_start' => $now];
}

if ($rate_data['count'] >= $rate_max) {
    json_out(['ok' => false, 'message' => 'Demasiados envíos. Inténtalo más tarde.'], 429);
}

/* ── Leer y sanitizar campos ──────────────────────────────────── */

$nombre  = sanitize_str($_POST['nombre']   ?? '');
$email   = trim((string) filter_var($_POST['email']  ?? '', FILTER_SANITIZE_EMAIL));
$telefono= sanitize_str($_POST['telefono'] ?? '');
$interes = sanitize_str($_POST['interes']  ?? '');
$mensaje = sanitize_str($_POST['mensaje']  ?? '');

/* ── Validación servidor ──────────────────────────────────────── */

$errors = [];

// nombre: requerido, mín 3 chars, sin dígitos
if ($nombre === '') {
    $errors['nombre'] = 'El nombre es requerido';
} elseif (strlen($nombre) < 3) {
    $errors['nombre'] = 'Mínimo 3 caracteres';
} elseif (preg_match('/\d/', $nombre)) {
    $errors['nombre'] = 'El nombre no debe contener números';
}

// email: requerido + validación RFC
if ($email === '') {
    $errors['email'] = 'El email es requerido';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Email inválido';
}

// teléfono: formatos dominicanos (809/829/849, local, o internacional +1)
if ($telefono === '') {
    $errors['telefono'] = 'El teléfono es requerido';
} else {
    $tel_clean = preg_replace('/[\s\-().+]/', '', $telefono);
    // acepta: 8090000000 / 18090000000 / formatos con separadores
    if (!preg_match('/^1?(809|829|849)\d{7}$/', $tel_clean)) {
        $errors['telefono'] = 'Teléfono dominicano inválido (809/829/849)';
    }
}

// línea de interés: debe ser uno de los valores permitidos
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

// mensaje: requerido, mín 15, máx 800
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

/* ── Envío de correo ──────────────────────────────────────────── */

if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // PHPMailer vía Composer (producción)
    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host        = $cfg['smtp_host'];
        $mailer->SMTPAuth    = true;
        $mailer->Username    = $cfg['smtp_user'];
        $mailer->Password    = $cfg['smtp_pass'];
        $mailer->SMTPSecure  = $cfg['smtp_secure'] === 'tls'
                                   ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
                                   : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mailer->Port        = (int)$cfg['smtp_port'];
        $mailer->CharSet     = 'UTF-8';

        $mailer->setFrom($cfg['mail_from'], $cfg['mail_from_name']);
        $mailer->addAddress($cfg['mail_to']);
        $mailer->addReplyTo($email, $nombre);

        $mailer->Subject = "Nuevo contacto desde el sitio web — {$interes}";
        $mailer->Body    = nl2br(htmlspecialchars(
            "Nombre:   {$nombre}\n" .
            "Email:    {$email}\n" .
            "Teléfono: {$telefono}\n" .
            "Interés:  {$interes}\n\n" .
            $mensaje
        ));
        $mailer->AltBody =
            "Nombre: {$nombre}\nEmail: {$email}\nTeléfono: {$telefono}\nInterés: {$interes}\n\n{$mensaje}";

        $mailer->send();
    } catch (\Exception $e) {
        error_log('[contact.php] PHPMailer error: ' . $e->getMessage());
        json_out(['ok' => false, 'message' => 'No se pudo enviar el mensaje. Inténtalo de nuevo.'], 500);
    }
} else {
    // Fallback: mail() nativo (útil en desarrollo sin Composer)
    $to      = $cfg['mail_to'];
    $subject = "=?UTF-8?B?" . base64_encode("Nuevo contacto desde el sitio web — {$interes}") . "?=";
    $body    = "Nombre: {$nombre}\nEmail: {$email}\nTeléfono: {$telefono}\nInterés: {$interes}\n\n{$mensaje}";
    $headers = implode("\r\n", [
        "From: {$cfg['mail_from']}",
        "Reply-To: {$email}",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=utf-8",
    ]);
    $sent = @mail($to, $subject, $body, $headers);
    if (!$sent) {
        error_log('[contact.php] mail() nativo falló');
        json_out(['ok' => false, 'message' => 'No se pudo enviar el mensaje. Inténtalo de nuevo.'], 500);
    }
}

/* ── Log (solo metadatos — nunca el mensaje completo) ─────────── */

$log_file = $cfg['log_file'] ?? (__DIR__ . '/../storage/logs/contact.log');
$log_dir  = dirname($log_file);
if (!is_dir($log_dir)) @mkdir($log_dir, 0755, true);

$log_entry = implode(' | ', [
    date('Y-m-d H:i:s'),
    $client_ip,
    $interes,
    mb_substr($nombre, 0, 30),
]) . "\n";
@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

/* ── Incrementar contador de rate limit ───────────────────────── */

$rate_data['count']++;
@file_put_contents($rate_file, json_encode($rate_data), LOCK_EX);

/* ── Respuesta exitosa ────────────────────────────────────────── */

json_out(['ok' => true]);
