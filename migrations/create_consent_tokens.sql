-- Migration: create_consent_tokens
-- Token store for consent links

CREATE TABLE consent_tokens (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT(11) NOT NULL,
    consent_id BIGINT(20) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_last4 CHAR(4) NOT NULL,
    status ENUM('ACTIVE', 'USED', 'EXPIRED', 'CANCELLED') NOT NULL DEFAULT 'ACTIVE',
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;