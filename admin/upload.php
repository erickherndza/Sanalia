<?php
/**
 * Sanalia CRM — Subida segura de documentos
 * POST /admin/upload.php
 */
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['sanalia_admin'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'no autorizado']); exit; }

require_once __DIR__ . '/db.php';

$client_id      = (int)($_POST['client_id'] ?? 0);
$application_id = (int)($_POST['application_id'] ?? 0) ?: null;
$nombre         = trim($_POST['nombre'] ?? '');

if (!$client_id) { echo json_encode(['ok'=>false,'error'=>'client_id requerido']); exit; }
if (empty($_FILES['file'])) { echo json_encode(['ok'=>false,'error'=>'Archivo no recibido']); exit; }

$allowed_mime = [
    'application/pdf',
    'image/jpeg','image/jpg','image/png','image/webp','image/gif',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$max_size = 10 * 1024 * 1024; // 10 MB

$file     = $_FILES['file'];
$tmp      = $file['tmp_name'];
$original = basename($file['name']);
$size     = $file['size'];
$mime     = mime_content_type($tmp);

if ($size > $max_size) { echo json_encode(['ok'=>false,'error'=>'Archivo demasiado grande (máx. 10 MB)']); exit; }
if (!in_array($mime, $allowed_mime, true)) { echo json_encode(['ok'=>false,'error'=>'Tipo de archivo no permitido']); exit; }

// Extensión segura desde el nombre original
$ext_map = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/jpg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif',
    'application/vnd.ms-excel'=>'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
    'application/msword'=>'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx'];
$ext = $ext_map[$mime] ?? 'bin';

// Directorio de almacenamiento
$dir = __DIR__ . '/../storage/docs/' . $client_id . '/';
if (!is_dir($dir)) { mkdir($dir, 0755, true); }

$filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$dest     = $dir . $filename;

if (!move_uploaded_file($tmp, $dest)) { echo json_encode(['ok'=>false,'error'=>'Error al guardar el archivo']); exit; }

// Nombre para mostrar
if (!$nombre) {
    $nombre = pathinfo($original, PATHINFO_FILENAME);
}

try {
    $db = get_db();
    $stmt = $db->prepare(
        'INSERT INTO documents (client_id, application_id, nombre, filename, original_name, file_size, mime_type)
         VALUES (:client_id, :application_id, :nombre, :filename, :original_name, :file_size, :mime_type)'
    );
    $stmt->execute([
        'client_id'      => $client_id,
        'application_id' => $application_id,
        'nombre'         => $nombre,
        'filename'       => $client_id . '/' . $filename,
        'original_name'  => $original,
        'file_size'      => $size,
        'mime_type'      => $mime,
    ]);
    $doc_id = (int)$db->lastInsertId();
    echo json_encode(['ok'=>true,'id'=>$doc_id,'nombre'=>$nombre,'original_name'=>$original,'file_size'=>$size,'mime_type'=>$mime]);
} catch (Exception $e) {
    @unlink($dest);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
