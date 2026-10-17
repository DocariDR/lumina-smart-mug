-- Lumina : schéma de la newsletter (V2)
-- Exécution : mysql -u USER -p DATABASE < db/schema.sql

CREATE TABLE IF NOT EXISTS subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    status ENUM('pending', 'confirmed', 'unsubscribed') NOT NULL DEFAULT 'pending',
    confirm_token_hash CHAR(64) NULL,
    confirm_token_expires_at DATETIME NULL,
    consent_at DATETIME NOT NULL,
    consent_version VARCHAR(32) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    unsubscribed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscribers_email (email),
    KEY idx_subscribers_token_hash (confirm_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;