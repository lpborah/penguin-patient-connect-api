-- patients table
CREATE TABLE IF NOT EXISTS `patients` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `patient_name` VARCHAR(255) NOT NULL,
  `mobile` VARCHAR(32) DEFAULT NULL,
  `consent_status` VARCHAR(32) DEFAULT 'pending',
  `consent_source` VARCHAR(128) DEFAULT NULL,
  `consent_sent_at` DATETIME DEFAULT NULL,
  `consent_response_at` DATETIME DEFAULT NULL,
  `last_message_id` VARCHAR(128) DEFAULT NULL,
  `last_error` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`customer_id`),
  INDEX (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
