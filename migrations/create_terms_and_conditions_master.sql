-- Migration: create_terms_and_conditions_master
-- Run this SQL to create the terms_and_conditions_master table

CREATE TABLE IF NOT EXISTS `terms_and_conditions_master` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `heading` VARCHAR(255) NOT NULL,
  `user` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100) DEFAULT NULL,
  `updated_by` VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
