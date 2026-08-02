<?php
/**
 * Sanalia — Admin: guardar etapa y notas de un lead
 * POST /admin/save.php
 */

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['sanalia_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'no autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$status_file   = __DIR__ . '/../storage/status.json';
$valid_etapas  = ['nuevo', 'contactado', 'cotizacion', 'emitido', 'perdido'];

$key   = trim($_POST['key']   ?? '');
$etapa = trim($_POST['etapa'] ?? '');
$notas = mb_substr(trim($_POST['notas'] ?? ''), 0, 1000);

if (!$key || !in_array($etapa, $valid_etapas, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'datos inválidos']);
    exit;
}

$data = [];
if (file_exists($status_file)) {
    $raw = @file_get_contents($status_file);
    if ($raw) $data = json_decode($raw, true) ?: [];
}

$data[$key] = [
    'etapa'      => $etapa,
    'notas'      => $notas,
    'updated_at' => date('Y-m-d H:i:s'),
];

@file_put_contents(
    $status_file,
    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

echo json_encode(['ok' => true]);
