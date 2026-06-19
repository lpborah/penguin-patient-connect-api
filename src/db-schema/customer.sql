-- customer table
CREATE TABLE IF NOT EXISTS `customer` (
  `customer_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(255) NOT NULL,
  `customer_code` VARCHAR(64) DEFAULT NULL,
  `aisensy_campaign_name` VARCHAR(255) DEFAULT NULL,
  `aisensy_template_name` VARCHAR(255) DEFAULT NULL,
  `aisensy_api_endpoint` VARCHAR(512) DEFAULT NULL,
  `aisensy_api_key` VARCHAR(255) DEFAULT NULL,
  `contact_person` VARCHAR(255) DEFAULT NULL,
  `mobile_no` VARCHAR(32) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(128) DEFAULT NULL,
  `state` VARCHAR(128) DEFAULT NULL,
  `country` VARCHAR(128) DEFAULT NULL,
  `pin` VARCHAR(32) DEFAULT NULL,
  `gst_no` VARCHAR(64) DEFAULT NULL,
  `status` VARCHAR(32) DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`),
  INDEX (`mobile_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
