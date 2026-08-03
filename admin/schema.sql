-- ============================================================
-- Sanalia & Asociados — CRM Schema
-- Ejecutar en phpMyAdmin una sola vez (cada bloque es idempotente)
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

-- ── form_templates: plantillas de campos por tipo de póliza ──────────────────
CREATE TABLE IF NOT EXISTS form_templates (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tipo       VARCHAR(100) NOT NULL UNIQUE,
    nombre     VARCHAR(255),
    fields     JSON NOT NULL,
    created_at DATETIME DEFAULT NOW(),
    updated_at DATETIME DEFAULT NOW() ON UPDATE NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── applications: solicitudes de póliza por cliente ──────────────────────────
CREATE TABLE IF NOT EXISTS applications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    client_id  INT NOT NULL,
    policy_id  INT,
    tipo       VARCHAR(100) NOT NULL,
    form_data  JSON,
    estado     ENUM('borrador','en-revision','aprobada','rechazada') DEFAULT 'borrador',
    notas      TEXT,
    created_at DATETIME DEFAULT NOW(),
    updated_at DATETIME DEFAULT NOW() ON UPDATE NOW(),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE SET NULL,
    INDEX idx_client (client_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── documents: documentos adjuntos de clientes / solicitudes ─────────────────
CREATE TABLE IF NOT EXISTS documents (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    client_id      INT NOT NULL,
    application_id INT,
    nombre         VARCHAR(255),
    filename       VARCHAR(500) NOT NULL,
    original_name  VARCHAR(500),
    file_size      INT,
    mime_type      VARCHAR(100),
    created_at     DATETIME DEFAULT NOW(),
    FOREIGN KEY (client_id)      REFERENCES clients(id)      ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── receivables: cuentas por cobrar (primas adeudadas por clientes) ──────────
CREATE TABLE IF NOT EXISTS receivables (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    client_id         INT NOT NULL,
    policy_id         INT,
    concepto          VARCHAR(255) NOT NULL,
    monto             DECIMAL(12,2) NOT NULL,
    fecha_emision     DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    fecha_pago        DATE,
    estado            ENUM('pendiente','pagado','vencido','anulado') DEFAULT 'pendiente',
    notas             TEXT,
    created_at        DATETIME DEFAULT NOW(),
    updated_at        DATETIME DEFAULT NOW() ON UPDATE NOW(),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE SET NULL,
    INDEX idx_estado_r      (estado),
    INDEX idx_vencimiento_r (fecha_vencimiento),
    INDEX idx_client_r      (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════
-- NUEVO: Tabla de leads / contactos del CRM de marketing
-- Recibe contactos del formulario web y campañas publicitarias
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS leads (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    nombre                 VARCHAR(255) NOT NULL,
    email                  VARCHAR(255),
    telefono               VARCHAR(60),
    interes                VARCHAR(100),           -- línea de seguro de interés
    mensaje                TEXT,                   -- mensaje original del formulario
    fuente                 ENUM('web','facebook','instagram','whatsapp','referido','otro') DEFAULT 'web',
    campana                VARCHAR(150),            -- nombre de la campaña (UTM)
    estado                 ENUM('nuevo','contactado','seguimiento','ganado','perdido') DEFAULT 'nuevo',
    notas                  TEXT,                   -- notas internas del equipo
    fecha_proximo_contacto DATE,                   -- próxima fecha de seguimiento
    fbclid                 VARCHAR(255),            -- Facebook Click ID (atribución exacta de anuncio)
    gclid                  VARCHAR(255),            -- Google Click ID (atribución exacta de anuncio)
    ga_client_id           VARCHAR(100),            -- GA4 Client ID (_ga cookie)
    created_at             DATETIME DEFAULT NOW(),
    updated_at             DATETIME DEFAULT NOW() ON UPDATE NOW(),
    INDEX idx_estado  (estado),
    INDEX idx_fuente  (fuente),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migración para bases de datos existentes (ejecutar si la tabla ya existe):
-- ALTER TABLE leads ADD COLUMN fbclid       VARCHAR(255) DEFAULT NULL AFTER fecha_proximo_contacto;
-- ALTER TABLE leads ADD COLUMN gclid        VARCHAR(255) DEFAULT NULL AFTER fbclid;
-- ALTER TABLE leads ADD COLUMN ga_client_id VARCHAR(100) DEFAULT NULL AFTER gclid;

-- ── payables: cuentas por pagar (comisiones, pagos a aseguradoras) ───────────
CREATE TABLE IF NOT EXISTS payables (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    beneficiario      VARCHAR(255) NOT NULL,
    concepto          VARCHAR(255) NOT NULL,
    monto             DECIMAL(12,2) NOT NULL,
    fecha_emision     DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    fecha_pago        DATE,
    estado            ENUM('pendiente','pagado','vencido','anulado') DEFAULT 'pendiente',
    categoria         ENUM('comision','prima','proveedor','otro') DEFAULT 'otro',
    notas             TEXT,
    created_at        DATETIME DEFAULT NOW(),
    updated_at        DATETIME DEFAULT NOW() ON UPDATE NOW(),
    INDEX idx_estado_p      (estado),
    INDEX idx_vencimiento_p (fecha_vencimiento),
    INDEX idx_categoria     (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
