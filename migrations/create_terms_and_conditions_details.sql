-- Migration: create_terms_and_conditions_details
-- Run this SQL to create the terms_and_conditions_details table

CREATE TABLE IF NOT EXISTS `terms_and_conditions_details` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `master_id` INT UNSIGNED NOT NULL,
  `term_order` INT NOT NULL DEFAULT 0,
  `term` TEXT NOT NULL,
  `user` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_master` (`master_id`),
  CONSTRAINT `fk_terms_master` FOREIGN KEY (`master_id`) REFERENCES `terms_and_conditions_master`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
