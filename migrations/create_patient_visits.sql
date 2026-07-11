CREATE TABLE IF NOT EXISTS `patient_visits` (
    `visit_id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `customer_id`       INT UNSIGNED    NOT NULL,
    `patient_id`        INT UNSIGNED    NOT NULL,
    `external_visit_id` VARCHAR(100)    NULL DEFAULT NULL COMMENT 'Visit ID from external / HIS system',
    `source_type`       VARCHAR(50)     NULL DEFAULT NULL COMMENT 'e.g. HIS, MANUAL, CSV',
    `source_reference`  VARCHAR(100)    NULL DEFAULT NULL,
    `department`        VARCHAR(100)    NULL DEFAULT NULL,
    `doctor_name`       VARCHAR(150)    NULL DEFAULT NULL,
    `visit_date`        DATE            NULL DEFAULT NULL,
    `laboratory_id`     VARCHAR(100)    NULL DEFAULT NULL,
    `bill_number`       VARCHAR(100)    NULL DEFAULT NULL,
    `bill_amount`       DECIMAL(12, 2)  NULL DEFAULT NULL,
    `admission_number`  VARCHAR(100)    NULL DEFAULT NULL,
    `ward`              VARCHAR(100)    NULL DEFAULT NULL,
    `bed`               VARCHAR(50)     NULL DEFAULT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`visit_id`),
    INDEX `idx_pv_patient_id`   (`patient_id`),
    INDEX `idx_pv_customer_id`  (`customer_id`),
    INDEX `idx_pv_visit_date`   (`visit_date`),
    UNIQUE INDEX `uq_pv_customer_external` (`customer_id`, `external_visit_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Patient visit records linked to patient_master';
