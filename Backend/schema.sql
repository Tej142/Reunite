-- Reunite Database Schema
-- Database: lost_connect_db

CREATE DATABASE IF NOT EXISTS `lost_connect_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `lost_connect_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `pin` VARCHAR(50) NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(100) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `trust_score` INT(11) DEFAULT 100,
  `role` ENUM('user', 'admin') DEFAULT 'user',
  `status` ENUM('active', 'blocked') DEFAULT 'active',
  `dob` VARCHAR(10) NULL,
  `college` VARCHAR(50) NULL,
  `branch` VARCHAR(5) NULL,
  `email_verified` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  INDEX `idx_users_pin` (`pin`),
  INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.1 Password Reset Tokens Table (10-minute expiry)
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(128) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_reset_token` (`token`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Lost Reports Table
CREATE TABLE IF NOT EXISTS `lost_reports` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `title` VARCHAR(150) NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(100) NULL,
  `date_lost` DATE NULL,
  `status` ENUM('active', 'matched', 'claimed', 'closed') DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_lost_user` (`user_id`),
  INDEX `idx_lost_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Found Reports Table
CREATE TABLE IF NOT EXISTS `found_reports` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `title` VARCHAR(150) NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(100) NULL,
  `date_found` DATE NULL,
  `image_path` VARCHAR(255) NULL,
  `status` ENUM('active', 'matched', 'claimed', 'closed') DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_found_user` (`user_id`),
  INDEX `idx_found_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Digital DNA Table
CREATE TABLE IF NOT EXISTS `digital_dna` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `report_id` VARCHAR(50) NOT NULL,
  `report_type` ENUM('lost', 'found') NOT NULL,
  `dna_json` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_dna_report` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Matches Table
CREATE TABLE IF NOT EXISTS `matches` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lost_report_id` VARCHAR(50) NOT NULL,
  `found_report_id` VARCHAR(50) NOT NULL,
  `similarity_score` DECIMAL(5,2) NOT NULL,
  `status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_matches_lost` (`lost_report_id`),
  INDEX `idx_matches_found` (`found_report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Recovery Cases Table
CREATE TABLE IF NOT EXISTS `recovery_cases` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `report_id` VARCHAR(50) NOT NULL,
  `claimant_id` INT(11) NOT NULL,
  `proof_details` TEXT NOT NULL,
  `status` ENUM('pending_verification', 'approved', 'rejected', 'handed_off') DEFAULT 'pending_verification',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_cases_claimant` (`claimant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_notif_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Access Logs Table
CREATE TABLE IF NOT EXISTS `access_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL,
  `action` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `device_info` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_logs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
