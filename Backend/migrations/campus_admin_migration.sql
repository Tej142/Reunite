-- ============================================================================
-- REUNITE CAMPUS ADMIN SCHEMA MIGRATION
-- Safe, Idempotent, Zero Data Loss, Re-runnable
-- Compatible with MySQL 5.7+ / 8.0+ / MariaDB / InfinityFree / phpMyAdmin
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Create campuses Table
CREATE TABLE IF NOT EXISTS `campuses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_campus_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Seed Default Production Campuses (if not already seeded)
INSERT INTO `campuses` (`id`, `name`, `code`, `status`) 
VALUES 
  (1, 'Sri Venkateswara Govt Polytechnic', 'SVGP', 'active'),
  (2, 'Tirupati Central Campus', 'TCC', 'active')
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `status` = VALUES(`status`);

-- 3. Add campus_id to users Table
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'campus_id');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `users` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `user_id`, ADD INDEX `idx_user_campus` (`campus_id`)', 
    'SELECT "Column users.campus_id already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Normalize & Backfill Existing Users to campus_id = 1 (SVGP)
UPDATE `users` 
SET `campus_id` = 1, `college` = 'Sri Venkateswara Govt Polytechnic'
WHERE `campus_id` IS NULL AND (`college` LIKE '%SVGP%' OR `college` LIKE '%Venkateswara%' OR `college` LIKE '%Polytechnic%' OR `college` IS NULL OR `college` = '');

-- Fallback for any remaining users without campus_id
UPDATE `users` SET `campus_id` = 1 WHERE `campus_id` IS NULL;

-- 5. Update admins Table: Add campus_id and Support master_admin / campus_admin Roles
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'campus_id');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `admins` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `role`, ADD INDEX `idx_admin_campus` (`campus_id`)', 
    'SELECT "Column admins.campus_id already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Modify role column on admins table to support new roles while preserving legacy roles
ALTER TABLE `admins` 
MODIFY COLUMN `role` ENUM('master_admin', 'campus_admin', 'superadmin', 'admin', 'moderator') NOT NULL DEFAULT 'campus_admin';

-- Backfill existing admins: set master accounts to master_admin with NULL campus_id
UPDATE `admins` 
SET `role` = 'master_admin', `campus_id` = NULL 
WHERE `username` IN ('admin', 'charan_admin') OR `role` IN ('superadmin', 'admin');

-- 6. Add campus_id to lost_reports Table
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lost_reports' AND COLUMN_NAME = 'campus_id');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `lost_reports` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `user_id`, ADD INDEX `idx_lost_campus` (`campus_id`)', 
    'SELECT "Column lost_reports.campus_id already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill lost_reports.campus_id from owner user
UPDATE `lost_reports` l 
JOIN `users` u ON l.`user_id` = u.`user_id` 
SET l.`campus_id` = u.`campus_id` 
WHERE l.`campus_id` IS NULL;
UPDATE `lost_reports` SET `campus_id` = 1 WHERE `campus_id` IS NULL;

-- 7. Add campus_id to found_reports Table
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'found_reports' AND COLUMN_NAME = 'campus_id');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `found_reports` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `user_id`, ADD INDEX `idx_found_campus` (`campus_id`)', 
    'SELECT "Column found_reports.campus_id already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill found_reports.campus_id from finder user
UPDATE `found_reports` f 
JOIN `users` u ON f.`user_id` = u.`user_id` 
SET f.`campus_id` = u.`campus_id` 
WHERE f.`campus_id` IS NULL;
UPDATE `found_reports` SET `campus_id` = 1 WHERE `campus_id` IS NULL;

-- 8. Add campus_id to matches Table
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matches' AND COLUMN_NAME = 'campus_id');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `matches` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `found_report_id`, ADD INDEX `idx_match_campus` (`campus_id`)', 
    'SELECT "Column matches.campus_id already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill matches.campus_id from found_reports
UPDATE `matches` m 
JOIN `found_reports` f ON (m.`found_report_id` = f.`id` OR m.`found_report_id` = f.`found_id`)
SET m.`campus_id` = f.`campus_id` 
WHERE m.`campus_id` IS NULL;
UPDATE `matches` SET `campus_id` = 1 WHERE `campus_id` IS NULL;

-- 9. Add campus_id to recovery_cases Table
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recovery_cases' AND COLUMN_NAME = 'campus_id');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `recovery_cases` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `id`, ADD INDEX `idx_case_campus` (`campus_id`)', 
    'SELECT "Column recovery_cases.campus_id already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill recovery_cases.campus_id from claimant user or found_reports
UPDATE `recovery_cases` r 
JOIN `users` u ON r.`claimant_id` = u.`user_id` 
SET r.`campus_id` = u.`campus_id` 
WHERE r.`campus_id` IS NULL;
UPDATE `recovery_cases` SET `campus_id` = 1 WHERE `campus_id` IS NULL;

-- 10. Add campus_id to notifications Table
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'campus_id');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `notifications` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `user_id`, ADD INDEX `idx_notif_campus` (`campus_id`)', 
    'SELECT "Column notifications.campus_id already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill notifications.campus_id from user
UPDATE `notifications` n 
JOIN `users` u ON n.`user_id` = u.`user_id` 
SET n.`campus_id` = u.`campus_id` 
WHERE n.`campus_id` IS NULL AND n.`user_id` > 0;

-- 11. Create admin_audit_logs Table for Master Admin Tracking
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) NOT NULL,
  `admin_name` VARCHAR(100) NOT NULL,
  `role` VARCHAR(50) NOT NULL,
  `campus_id` INT(11) NULL DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50) NULL DEFAULT NULL,
  `target_id` INT(11) NULL DEFAULT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_admin` (`admin_id`),
  KEY `idx_audit_campus` (`campus_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
