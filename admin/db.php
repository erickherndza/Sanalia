<?php
/**
 * Sanalia CRM — Conexión PDO
 * Requiere que api/config.php defina DB_HOST, DB_NAME, DB_USER, DB_PASS
 */

declare(strict_types=1);

$config_path = __DIR__ . '/../api/config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (!defined('DB_HOST')) {
        throw new RuntimeException('DB_HOST no definido. Revisa api/config.php');
    }

    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $pdo;
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
