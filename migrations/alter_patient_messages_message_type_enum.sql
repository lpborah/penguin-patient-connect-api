-- Alter message_type column from VARCHAR to ENUM in patient_messages
ALTER TABLE `patient_messages`
    MODIFY COLUMN `message_type`
        ENUM('CONSENT','OPD_REGISTRATION','LAB_WELCOME','IPD_WELCOME','REPORT_READY','GENERAL')
        NOT NULL DEFAULT 'CONSENT';
