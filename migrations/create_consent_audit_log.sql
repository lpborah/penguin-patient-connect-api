CREATE TABLE consent_audit_log (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT(11) NOT NULL,
    contact_id BIGINT(20) NOT NULL,
    token_id BIGINT(20) UNSIGNED DEFAULT NULL,
    event_type ENUM(
        'TOKEN_CREATED',
        'MESSAGE_SENT',
        'MESSAGE_FAILED',
        'CLICK_GRANTED',
        'INVALID_TOKEN',
        'EXPIRED_TOKEN',
        'CONSENT_REVOKED'
    ) NOT NULL,
    event_payload LONGTEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;