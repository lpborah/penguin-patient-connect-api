-- contact table
CREATE TABLE IF NOT EXISTS `contact` (
  `contact_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `external_patient_id` VARCHAR(255) DEFAULT NULL,
  `mobile_no` VARCHAR(32) DEFAULT NULL,
  `first_name` VARCHAR(255) DEFAULT NULL,
  `last_name` VARCHAR(255) DEFAULT NULL,
  `source_type` VARCHAR(64) DEFAULT NULL,
  `source_reference` VARCHAR(255) DEFAULT NULL,
  `consent_status` VARCHAR(32) DEFAULT NULL,
  `consent_source` VARCHAR(128) DEFAULT NULL,
  `consent_granted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`contact_id`),
  INDEX (`customer_id`),
  INDEX (`mobile_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
