<?php
/**
 * Sanalia CRM — Middleware de autenticación
 * Incluir al inicio de cada página del admin.
 *
 * Uso básico:        require_once __DIR__ . '/auth.php'; require_auth();
 * Solo para admins:  require_once __DIR__ . '/auth.php'; require_admin();
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';

/* ── Asegurar tabla crm_users ── */
function ensure_users_table(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS crm_users (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre        VARCHAR(150) NOT NULL,
        email         VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        rol           ENUM('admin','guest') DEFAULT 'admin',
        activo        TINYINT(1)  DEFAULT 1,
        expires_at    DATETIME    DEFAULT NULL,
        created_at    DATETIME    DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ── Verificar si un usuario de sesión sigue válido ── */
function session_still_valid(): bool {
    if (empty($_SESSION['sanalia_admin'])) return false;
    // Guest: verificar expiración
    if (!empty($_SESSION['user_expires_at'])) {
        if (strtotime($_SESSION['user_expires_at']) < time()) {
            session_destroy();
            return false;
        }
    }
    return true;
}

/* ── Requerir autenticación (cualquier rol) ── */
function require_auth(): void {
    if (!session_still_valid()) {
        $expired = !empty($_SESSION) ? '?expired=1' : '';
        header('Location: index.php' . $expired);
        exit;
    }
}

/* ── Requerir rol admin ── */
function require_admin(): void {
    require_auth();
    if (($_SESSION['user_rol'] ?? 'admin') !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

/* ── Helper: minutos restantes para guest ── */
function guest_minutes_left(): int {
    if (empty($_SESSION['user_expires_at'])) return -1;
    return max(0, (int) ceil((strtotime($_SESSION['user_expires_at']) - time()) / 60));
}
