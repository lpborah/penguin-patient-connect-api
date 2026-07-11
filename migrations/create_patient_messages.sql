CREATE TABLE IF NOT EXISTS `patient_messages` (
    `message_id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `customer_id`         INT UNSIGNED    NOT NULL,
    `patient_id`          INT UNSIGNED    NOT NULL,
    `visit_id`            INT UNSIGNED    NULL DEFAULT NULL,
    `consent_id`          INT UNSIGNED    NULL DEFAULT NULL,
    `message_type`        ENUM('CONSENT','OPD_REGISTRATION','LAB_WELCOME','IPD_WELCOME','REPORT_READY','GENERAL') NOT NULL DEFAULT 'CONSENT',
    `template_name`       VARCHAR(100)    NULL DEFAULT NULL,
    `mobile_no`           VARCHAR(20)     NOT NULL,
    `provider`            VARCHAR(50)     NOT NULL DEFAULT 'AISENSY',
    `provider_message_id` VARCHAR(100)    NULL DEFAULT NULL,
    `status`              VARCHAR(30)     NOT NULL DEFAULT 'SENT' COMMENT 'SENT | FAILED | ERROR',
    `error_message`       TEXT            NULL DEFAULT NULL,
    `request_payload`     JSON            NULL DEFAULT NULL,
    `provider_response`   JSON            NULL DEFAULT NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`message_id`),
    INDEX `idx_pm_patient_id`  (`patient_id`),
    INDEX `idx_pm_consent_id`  (`consent_id`),
    INDEX `idx_pm_customer_id` (`customer_id`),
    INDEX `idx_pm_status`      (`status`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Log of every outbound message (WhatsApp / AiSensy) per patient';
