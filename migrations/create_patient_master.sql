-- Migration: create_patient_master
-- Core patient master table used by patient APIs

CREATE TABLE IF NOT EXISTS `patient_master` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `external_patient_id` VARCHAR(255) DEFAULT NULL,
  `first_name` VARCHAR(255) DEFAULT NULL,
  `last_name` VARCHAR(255) DEFAULT NULL,
  `age` VARCHAR(32) DEFAULT NULL,
  `sex` VARCHAR(32) DEFAULT NULL,
  `mobile` VARCHAR(32) DEFAULT NULL,
  `consent_status` VARCHAR(32) DEFAULT 'PENDING',
  `consent_response_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_patient_master_customer_id` (`customer_id`),
  KEY `idx_patient_master_mobile` (`mobile`),
  KEY `idx_patient_master_external_patient_id` (`external_patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
