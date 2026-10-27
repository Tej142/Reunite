<?php
/**
 * Reunite - Campus Admin Automated Migration Runner
 * Safe, idempotent migration to create campuses and scope columns.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Reunite Campus Admin Schema Migration ===\n\n";

if (!$conn) {
    echo "ERROR: Database connection failed: " . mysqli_connect_error() . "\n";
    echo "If running outside InfinityFree web host, this is expected due to InfinityFree IP firewall.\n";
    echo "You can copy and execute Backend/migrations/campus_admin_migration.sql in phpMyAdmin directly.\n";
    exit(1);
}

echo "Database connected successfully to " . DB_NAME . "\n\n";

// Helper to check column existence
function colExists($conn, $table, $column) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

// Helper to check table existence
function tblExists($conn, $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

// 1. Create campuses table
echo "[1/10] Checking 'campuses' table... ";
$conn->query("CREATE TABLE IF NOT EXISTS `campuses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_campus_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

echo "OK.\n";

// 2. Seed Default Campuses
echo "[2/10] Seeding default campuses... ";
$conn->query("INSERT INTO `campuses` (`id`, `name`, `code`, `status`) 
VALUES 
  (1, 'Sri Venkateswara Govt Polytechnic', 'SVGP', 'active'),
  (2, 'Tirupati Central Campus', 'TCC', 'active')
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `status` = VALUES(`status`);");
echo "OK.\n";

// 3. Add campus_id to users
echo "[3/10] Checking 'users.campus_id'... ";
if (!colExists($conn, 'users', 'campus_id')) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `user_id`, ADD INDEX `idx_user_campus` (`campus_id`)");
    echo "Added column.\n";
} else {
    echo "Already exists.\n";
}

// Backfill users
$conn->query("UPDATE `users` SET `campus_id` = 1 WHERE `campus_id` IS NULL");

// 4. Update admins table
echo "[4/10] Checking 'admins' role & campus_id... ";
if (!colExists($conn, 'admins', 'campus_id')) {
    $conn->query("ALTER TABLE `admins` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `role`, ADD INDEX `idx_admin_campus` (`campus_id`)");
    echo "Added campus_id column. ";
}
$conn->query("ALTER TABLE `admins` MODIFY COLUMN `role` ENUM('master_admin', 'campus_admin', 'superadmin', 'admin', 'moderator') NOT NULL DEFAULT 'campus_admin'");
$conn->query("UPDATE `admins` SET `role` = 'master_admin', `campus_id` = NULL WHERE `username` IN ('admin', 'charan_admin') OR `role` IN ('superadmin', 'admin')");
echo "OK.\n";

// 5. Add campus_id to lost_reports
echo "[5/10] Checking 'lost_reports.campus_id'... ";
if (!colExists($conn, 'lost_reports', 'campus_id')) {
    $conn->query("ALTER TABLE `lost_reports` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `user_id`, ADD INDEX `idx_lost_campus` (`campus_id`)");
    echo "Added column. ";
}
$conn->query("UPDATE `lost_reports` l JOIN `users` u ON l.`user_id` = u.`user_id` SET l.`campus_id` = u.`campus_id` WHERE l.`campus_id` IS NULL");
$conn->query("UPDATE `lost_reports` SET `campus_id` = 1 WHERE `campus_id` IS NULL");
echo "OK.\n";

// 6. Add campus_id to found_reports
echo "[6/10] Checking 'found_reports.campus_id'... ";
if (!colExists($conn, 'found_reports', 'campus_id')) {
    $conn->query("ALTER TABLE `found_reports` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `user_id`, ADD INDEX `idx_found_campus` (`campus_id`)");
    echo "Added column. ";
}
$conn->query("UPDATE `found_reports` f JOIN `users` u ON f.`user_id` = u.`user_id` SET f.`campus_id` = u.`campus_id` WHERE f.`campus_id` IS NULL");
$conn->query("UPDATE `found_reports` SET `campus_id` = 1 WHERE `campus_id` IS NULL");
echo "OK.\n";

// 7. Add campus_id to matches
echo "[7/10] Checking 'matches.campus_id'... ";
if (!colExists($conn, 'matches', 'campus_id')) {
    $conn->query("ALTER TABLE `matches` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `found_report_id`, ADD INDEX `idx_match_campus` (`campus_id`)");
    echo "Added column. ";
}
$conn->query("UPDATE `matches` m JOIN `found_reports` f ON (m.`found_report_id` = f.`id` OR m.`found_report_id` = f.`found_id`) SET m.`campus_id` = f.`campus_id` WHERE m.`campus_id` IS NULL");
$conn->query("UPDATE `matches` SET `campus_id` = 1 WHERE `campus_id` IS NULL");
echo "OK.\n";

// 8. Add campus_id to recovery_cases
echo "[8/10] Checking 'recovery_cases.campus_id'... ";
if (!colExists($conn, 'recovery_cases', 'campus_id')) {
    $conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `id`, ADD INDEX `idx_case_campus` (`campus_id`)");
    echo "Added column. ";
}
$conn->query("UPDATE `recovery_cases` r JOIN `users` u ON r.`claimant_id` = u.`user_id` SET r.`campus_id` = u.`campus_id` WHERE r.`campus_id` IS NULL");
$conn->query("UPDATE `recovery_cases` SET `campus_id` = 1 WHERE `campus_id` IS NULL");
echo "OK.\n";

// 9. Add campus_id to notifications
echo "[9/10] Checking 'notifications.campus_id'... ";
if (!colExists($conn, 'notifications', 'campus_id')) {
    $conn->query("ALTER TABLE `notifications` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `user_id`, ADD INDEX `idx_notif_campus` (`campus_id`)");
    echo "Added column. ";
}
$conn->query("UPDATE `notifications` n JOIN `users` u ON n.`user_id` = u.`user_id` SET n.`campus_id` = u.`campus_id` WHERE n.`campus_id` IS NULL AND n.`user_id` > 0");
echo "OK.\n";

// 10. Create admin_audit_logs table
echo "[10/10] Checking 'admin_audit_logs' table... ";
$conn->query("CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
echo "OK.\n\n";

echo "=== Migration Finished Successfully! ===\n";
