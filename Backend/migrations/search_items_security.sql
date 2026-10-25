-- Search Items and claim review safeguards. Apply after the existing baseline and claim_v2 migration.
CREATE TABLE IF NOT EXISTS `search_lookup_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `ip_hash` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lookup_account_time` (`user_id`, `created_at`),
  KEY `idx_lookup_ip_time` (`ip_hash`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `claim_review_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_id` INT(11) NOT NULL,
  `claimant_id` INT(11) NOT NULL,
  `actor_user_id` INT(11) DEFAULT NULL,
  `decision` VARCHAR(40) NOT NULL,
  `reason` VARCHAR(1000) NOT NULL,
  `fraud_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
  `sanction_hours` INT(11) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_review_match` (`match_id`),
  KEY `idx_review_claimant` (`claimant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_privacy_thumb = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'found_reports' AND COLUMN_NAME = 'privacy_thumbnail_path'
);
SET @thumb_sql = IF(@has_privacy_thumb = 0,
  'ALTER TABLE `found_reports` ADD COLUMN `privacy_thumbnail_path` VARCHAR(255) NULL DEFAULT NULL',
  'SELECT "found_reports.privacy_thumbnail_path already exists"');
PREPARE stmt FROM @thumb_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Runtime claim v2 writes these states although the original schema used a narrower ENUM.
ALTER TABLE `matches` MODIFY COLUMN `status` VARCHAR(40) NOT NULL DEFAULT 'pending';

SET @has_claim_submitted_at = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matches' AND COLUMN_NAME = 'claim_submitted_at'
);
SET @claim_time_sql = IF(@has_claim_submitted_at = 0,
  'ALTER TABLE `matches` ADD COLUMN `claim_submitted_at` DATETIME NULL DEFAULT NULL',
  'SELECT "matches.claim_submitted_at already exists"');
PREPARE stmt FROM @claim_time_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
