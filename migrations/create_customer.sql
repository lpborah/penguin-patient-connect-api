CREATE TABLE customer (
    customer_id INT(11) NOT NULL AUTO_INCREMENT,
    customer_name VARCHAR(255) NOT NULL,
    customer_code VARCHAR(50) NOT NULL,
    aisensy_campaign_name VARCHAR(150) NOT NULL DEFAULT 'HM Consent V1',
    aisensy_template_name VARCHAR(150) NOT NULL DEFAULT 'hm_whatsapp_consent_v1',
    aisensy_api_endpoint TEXT DEFAULT NULL,
    aisensy_api_key TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    contact_person VARCHAR(255) DEFAULT NULL,
    mobile_no VARCHAR(20) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) DEFAULT NULL,
    pin VARCHAR(7) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    gst_no VARCHAR(50) DEFAULT NULL,
    status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id),
    UNIQUE KEY uk_customer_code (customer_code)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;