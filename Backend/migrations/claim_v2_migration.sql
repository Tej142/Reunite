-- ============================================================================
-- REUNITE CLAIM & HANDOVER V2 SCHEMA MIGRATION
-- Engine: InnoDB | Idempotent | Zero Data Loss
-- ============================================================================

-- 1. Anti-Spam Claimant Ban Column on users table
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'claim_ban_until');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `users` ADD COLUMN `claim_ban_until` DATETIME NULL DEFAULT NULL AFTER `status`, ADD INDEX `idx_claim_ban` (`claim_ban_until`)', 
    'SELECT "Column users.claim_ban_until already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Proof image storage on matches table
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matches' AND COLUMN_NAME = 'proof_image');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `matches` ADD COLUMN `proof_image` VARCHAR(255) NULL DEFAULT NULL AFTER `claim_details`', 
    'SELECT "Column matches.proof_image already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Ensure recovery_cases has required columns for Claim V2
-- 3a. match_id with unique constraint
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recovery_cases' AND COLUMN_NAME = 'match_id');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `recovery_cases` ADD COLUMN `match_id` INT(11) NULL AFTER `id`, ADD UNIQUE INDEX `uq_match_id` (`match_id`)', 
    'SELECT "Column recovery_cases.match_id already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3b. return_method ('hand_on_hand' or 'admin_office')
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recovery_cases' AND COLUMN_NAME = 'return_method');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `recovery_cases` ADD COLUMN `return_method` ENUM("hand_on_hand", "admin_office") NOT NULL DEFAULT "hand_on_hand" AFTER `match_id`', 
    'SELECT "Column recovery_cases.return_method already exists"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3c. meeting_location and meeting_time
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recovery_cases' AND COLUMN_NAME = 'meeting_location');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `recovery_cases` ADD COLUMN `meeting_location` VARCHAR(255) NULL DEFAULT NULL AFTER `return_method`, ADD COLUMN `meeting_time` DATETIME NULL DEFAULT NULL AFTER `meeting_location`', 
    'SELECT "Columns meeting_location/time already exist"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3d. Secure OTP Hashing & Attempt Lockout columns
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recovery_cases' AND COLUMN_NAME = 'otp_hash');
SET @sql_stmt = IF(@col_exists = 0, 
    'ALTER TABLE `recovery_cases` ADD COLUMN `otp_hash` VARCHAR(64) NULL DEFAULT NULL AFTER `meeting_time`, ADD COLUMN `otp_expires_at` DATETIME NULL DEFAULT NULL AFTER `otp_hash`, ADD COLUMN `otp_attempts` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `otp_expires_at`, ADD COLUMN `otp_last_generated` DATETIME NULL DEFAULT NULL AFTER `otp_attempts`, ADD COLUMN `otp_regen_count` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `otp_last_generated`', 
    'SELECT "OTP columns already exist"');
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Archival of legacy in-flight claims
UPDATE `recovery_cases` SET `status` = 'archived' WHERE `status` = 'pending_verification';
UPDATE `found_reports` SET `status` = 'active' WHERE `found_id` IN (1, 2) AND `status` = 'claimed';
