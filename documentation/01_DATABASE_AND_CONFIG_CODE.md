# Database Schema & Configuration Code

## 1. Database Connection & Environment Loader
**Source File:** `Backend/config/config.php`

```php
<?php
/**
 * Reunite Database & Global Configuration
 * Database: lost_connect_db
 */

// Ensure consistent campus timezone (Tirupati, AP, GMT+5:30)
date_default_timezone_set('Asia/Kolkata');

// Start volatile session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Automatically load environment variables: .env is the primary source of truth
(function() {
    $rootDir = dirname(__DIR__, 2);
    $examplePath = $rootDir . '/.env.example';
    $envPath = $rootDir . '/.env';

    if (file_exists($envPath) && is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0 || strpos($line, ';') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
})();

// Primary Database Connection
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'lost_connect_db';
$dbPort = (int)(getenv('DB_PORT') ?: 3306);

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
}
```

---

## 2. Core Relational Database Tables DDL
**Database:** `lost_connect_db`

```sql
-- 1. Campuses (Multi-Tenant Institutions)
CREATE TABLE IF NOT EXISTS `campuses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `city` VARCHAR(100) DEFAULT '',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Administrators (Master and Campus Admins)
CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('master_admin', 'campus_admin') DEFAULT 'campus_admin',
  `campus_id` INT DEFAULT NULL,
  `status` ENUM('active', 'suspended') DEFAULT 'active',
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`campus_id`) REFERENCES `campuses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Students / Users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(150) NOT NULL,
  `pin` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(255) DEFAULT '',
  `password_hash` VARCHAR(255) NOT NULL,
  `campus_id` INT DEFAULT NULL,
  `college` VARCHAR(200) DEFAULT '',
  `branch` VARCHAR(100) DEFAULT '',
  `trust_score` INT DEFAULT 100,
  `status` ENUM('active', 'blocked') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`campus_id`) REFERENCES `campuses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Lost Items
CREATE TABLE IF NOT EXISTS `lost_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `campus_id` INT DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `location_lost` VARCHAR(255) DEFAULT '',
  `date_lost` DATE DEFAULT NULL,
  `image_path` VARCHAR(255) DEFAULT '',
  `status` ENUM('active', 'matched', 'restored', 'cancelled') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`),
  FOREIGN KEY (`campus_id`) REFERENCES `campuses`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Found Items
CREATE TABLE IF NOT EXISTS `found_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `finder_id` INT NOT NULL,
  `campus_id` INT DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `location_found` VARCHAR(255) DEFAULT '',
  `date_found` DATE DEFAULT NULL,
  `image_path` VARCHAR(255) DEFAULT '',
  `status` ENUM('available', 'claim_pending', 'handed_over', 'archived') DEFAULT 'available',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`finder_id`) REFERENCES `users`(`user_id`),
  FOREIGN KEY (`campus_id`) REFERENCES `campuses`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Claims & Verification Handshakes
CREATE TABLE IF NOT EXISTS `claims` (
  `claim_id` INT AUTO_INCREMENT PRIMARY KEY,
  `lost_id` INT NOT NULL,
  `found_id` INT NOT NULL,
  `claimant_id` INT NOT NULL,
  `campus_id` INT DEFAULT NULL,
  `status` ENUM('pending_founder_approval', 'approved', 'ready_for_handover', 'rejected', 'completed') DEFAULT 'pending_founder_approval',
  `confidence_score` DECIMAL(5,2) DEFAULT 0.00,
  `handover_otp_hash` VARCHAR(255) DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`claimant_id`) REFERENCES `users`(`user_id`),
  FOREIGN KEY (`campus_id`) REFERENCES `campuses`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Immutable Audit Access Logs
CREATE TABLE IF NOT EXISTS `access_logs` (
  `log_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT 0,
  `action` VARCHAR(150) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45) DEFAULT '',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
