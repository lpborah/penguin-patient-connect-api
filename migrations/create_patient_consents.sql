-- Migration: create_patient_consents
-- Stores consent state for patient records

CREATE TABLE patient_consents (
    consent_id BIGINT(20) NOT NULL AUTO_INCREMENT,
    customer_id INT(11) NOT NULL,
    patient_id VARCHAR(100) DEFAULT NULL,
    consent_status ENUM('PENDING', 'SENT', 'CONSENTED', 'DECLINED') DEFAULT 'PENDING',
    consent_source VARCHAR(100) DEFAULT NULL,
    purpose VARCHAR(50) DEFAULT 'WHATSAPP_CONSENT',
    consent_datetime DATETIME DEFAULT NULL,
    consent_granted_at DATETIME DEFAULT NULL,
    consent_revoked_at DATETIME DEFAULT NULL,
    last_message_id VARCHAR(100) DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (consent_id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;