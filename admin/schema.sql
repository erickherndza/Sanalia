-- ============================================================
-- Sanalia & Asociados — CRM Schema
-- Ejecutar en phpMyAdmin una sola vez
-- ============================================================

CREATE TABLE IF NOT EXISTS clients (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(255) NOT NULL,
    email         VARCHAR(255),
    telefono      VARCHAR(50),
    cedula        VARCHAR(30),
    direccion     TEXT,
    notas         TEXT,
    created_at    DATETIME DEFAULT NOW(),
    updated_at    DATETIME DEFAULT NOW() ON UPDATE NOW(),
    INDEX idx_nombre  (nombre),
    INDEX idx_cedula  (cedula),
    INDEX idx_email   (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policies (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    client_id          INT NOT NULL,
    tipo               VARCHAR(100),
    numero_poliza      VARCHAR(100),
    aseguradora        VARCHAR(150),
    fecha_inicio       DATE,
    fecha_vencimiento  DATE,
    prima_anual        DECIMAL(10,2),
    frecuencia_pago    ENUM('mensual','trimestral','semestral','anual') DEFAULT 'anual',
    estado             ENUM('activa','vencida','cancelada','en-renovacion') DEFAULT 'activa',
    notas              TEXT,
    created_at         DATETIME DEFAULT NOW(),
    updated_at         DATETIME DEFAULT NOW() ON UPDATE NOW(),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_vencimiento (fecha_vencimiento),
    INDEX idx_estado      (estado),
    INDEX idx_client      (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
