-- consent_tokens table
CREATE TABLE IF NOT EXISTS `consent_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `contact_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(128) NOT NULL,
  `token_last4` VARCHAR(8) DEFAULT NULL,
  `purpose` VARCHAR(64) DEFAULT 'WHATSAPP_CONSENT',
  `status` VARCHAR(32) DEFAULT 'ACTIVE',
  `expires_at` DATETIME DEFAULT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  INDEX (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
