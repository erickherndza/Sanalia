<?php
/**
 * Sanalia CRM — Descarga autenticada de documentos
 * GET /admin/download.php?id=X
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) { http_response_code(403); exit('Acceso denegado'); }

require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('ID requerido'); }

try {
    $db   = get_db();
    $stmt = $db->prepare('SELECT * FROM documents WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $doc  = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(500); exit('Error de base de datos');
}

if (!$doc) { http_response_code(404); exit('Documento no encontrado'); }

$path = __DIR__ . '/../storage/docs/' . $doc['filename'];
if (!file_exists($path)) { http_response_code(404); exit('Archivo no encontrado en el servidor'); }

$mime = $doc['mime_type'] ?: 'application/octet-stream';
$name = $doc['original_name'] ?: basename($doc['filename']);

// Vista previa para imágenes y PDF, descarga para el resto
$inline = in_array($mime, ['application/pdf','image/jpeg','image/png','image/webp','image/gif']);
$disp   = $inline ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disp . '; filename="' . addslashes($name) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);
