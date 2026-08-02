<?php
/**
 * Sanalia CRM — Retorna datos de un cliente + sus pólizas (JSON)
 * GET /admin/client-data.php?id=X
 */

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['sanalia_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

require_once __DIR__ . '/db.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'ID requerido']);
    exit;
}

try {
    $db     = get_db();
    $client = $db->prepare('SELECT * FROM clients WHERE id = :id');
    $client->execute(['id' => $id]);
    $client = $client->fetch();

    if (!$client) {
        echo json_encode(['ok' => false, 'error' => 'Cliente no encontrado']);
        exit;
    }

    $policies = $db->prepare('SELECT * FROM policies WHERE client_id = :id ORDER BY fecha_vencimiento ASC');
    $policies->execute(['id' => $id]);
    $policies = $policies->fetchAll();

    $applications = $db->prepare(
        'SELECT id, tipo, estado, notas, created_at FROM applications WHERE client_id = :id ORDER BY created_at DESC'
    );
    $applications->execute(['id' => $id]);
    $applications = $applications->fetchAll();

    $documents = $db->prepare(
        'SELECT id, nombre, original_name, file_size, mime_type, application_id, created_at
         FROM documents WHERE client_id = :id ORDER BY created_at DESC'
    );
    $documents->execute(['id' => $id]);
    $documents = $documents->fetchAll();

    echo json_encode([
        'ok'           => true,
        'client'       => $client,
        'policies'     => $policies,
        'applications' => $applications,
        'documents'    => $documents,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
