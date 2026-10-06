-- ==============================================================================
-- REUNITE PLATFORM — COMPLETE PRODUCTION DATABASE SCHEMA
-- Compatible with MySQL 5.7+ / 8.0+ / MariaDB / InfinityFree / Remote Cloud DBs
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `pin` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(100) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `trust_score` INT(11) DEFAULT 100,
  `role` ENUM('user', 'admin') DEFAULT 'user',
  `status` ENUM('active', 'blocked') DEFAULT 'active',
  `dob` VARCHAR(10) DEFAULT NULL,
  `college` VARCHAR(50) DEFAULT NULL,
  `branch` VARCHAR(5) DEFAULT NULL,
  `email_verified` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `idx_users_email` (`email`),
  UNIQUE KEY `idx_users_pin` (`pin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Digital DNA Table
CREATE TABLE IF NOT EXISTS `digital_dna` (
  `dna_id` INT(11) NOT NULL AUTO_INCREMENT,
  `report_type` ENUM('LOST', 'FOUND') NOT NULL,
  `report_id` INT(11) NOT NULL,
  `category` VARCHAR(50) DEFAULT NULL,
  `shape` VARCHAR(50) DEFAULT NULL,
  `encrypted_dna` LONGTEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`dna_id`),
  KEY `idx_dna_report` (`report_type`, `report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Lost Reports Table
CREATE TABLE IF NOT EXISTS `lost_reports` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `title` VARCHAR(150) DEFAULT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `date_lost` DATE DEFAULT NULL,
  `lost_time` TIME DEFAULT NULL,
  `location` VARCHAR(100) DEFAULT NULL,
  `dna_id` INT(11) DEFAULT NULL,
  `status` ENUM('active', 'matched', 'claimed', 'closed') DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lost_user` (`user_id`),
  KEY `idx_lost_dna` (`dna_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Found Reports Table
CREATE TABLE IF NOT EXISTS `found_reports` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `title` VARCHAR(150) DEFAULT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `date_found` DATE DEFAULT NULL,
  `found_time` TIME DEFAULT NULL,
  `location` VARCHAR(100) DEFAULT NULL,
  `dna_id` INT(11) DEFAULT NULL,
  `status` ENUM('active', 'matched', 'claimed', 'closed') DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_found_user` (`user_id`),
  KEY `idx_found_dna` (`dna_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Matches Table
CREATE TABLE IF NOT EXISTS `matches` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lost_report_id` INT(11) NOT NULL,
  `found_report_id` INT(11) NOT NULL,
  `similarity_score` DECIMAL(5,2) NOT NULL,
  `status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_match_lost` (`lost_report_id`),
  KEY `idx_match_found` (`found_report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Recovery Cases / Claims Table
CREATE TABLE IF NOT EXISTS `recovery_cases` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `claimant_id` INT(11) NOT NULL,
  `proof_details` TEXT NOT NULL,
  `report_id` INT(11) NOT NULL,
  `owner_verified` TINYINT(1) DEFAULT 0,
  `finder_confirmed` TINYINT(1) DEFAULT 0,
  `status` ENUM('pending_verification', 'approved', 'rejected', 'handed_off') DEFAULT 'pending_verification',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rec_claimant` (`claimant_id`),
  KEY `idx_rec_report` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `title` VARCHAR(150) DEFAULT NULL,
  `report_type` ENUM('LOST', 'FOUND') NOT NULL DEFAULT 'LOST',
  `report_id` INT(11) NOT NULL DEFAULT 0,
  `type` VARCHAR(50) NOT NULL DEFAULT 'match',
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notif_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Password Reset Tokens Table (10-minute validity)
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(128) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reset_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. Site Maintenance Mode Settings Table
CREATE TABLE IF NOT EXISTS `maintenance_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `message` VARCHAR(500) DEFAULT NULL,
  `eta` DATETIME DEFAULT NULL,
  `support_email` VARCHAR(150) DEFAULT NULL,
  `updated_by` VARCHAR(100) DEFAULT 'System Administrator',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 10. System Administrators Table
CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL DEFAULT 'System Administrator',
  `email` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('superadmin', 'admin', 'moderator') DEFAULT 'admin',
  `status` ENUM('active', 'suspended') DEFAULT 'active',
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `idx_admin_username` (`username`),
  UNIQUE KEY `idx_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 11. Activity & Access Audit Logs Table
CREATE TABLE IF NOT EXISTS `logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_type` ENUM('admin', 'user', 'system', 'anonymous') DEFAULT 'user',
  `user_id` INT(11) DEFAULT NULL,
  `operator_name` VARCHAR(100) DEFAULT NULL,
  `operator_identifier` VARCHAR(100) DEFAULT NULL,
  `category` ENUM('admin_access', 'user_access', 'security', 'password_reset', 'report_activity', 'match_verification', 'claim_activity', 'system_event') NOT NULL DEFAULT 'user_access',
  `action` VARCHAR(150) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `device_info` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_category` (`category`),
  KEY `idx_logs_user` (`user_id`),
  KEY `idx_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 12. Dynamic Question Sets Table
CREATE TABLE IF NOT EXISTS `question_sets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(100) NOT NULL,
  `report_type` ENUM('LOST', 'FOUND') NOT NULL,
  `questions_json` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_question_set` (`category`, `report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
