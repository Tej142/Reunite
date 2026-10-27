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
  `address` VARCHAR(255) NULL DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_campus_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

if (!colExists($conn, 'campuses', 'address')) {
    $conn->query("ALTER TABLE `campuses` ADD COLUMN `address` VARCHAR(255) NULL DEFAULT NULL AFTER `code`");
}
echo "OK.\n";

// 2. Seed Default Campuses (including all registration page campuses)
echo "[2/10] Seeding all registration campuses... ";
$campusesToSeed = [
    [1, 'Sri Venkateswara Govt Polytechnic', 'SVGP', 'KT Road, Tirupati, AP', 'active'],
    [2, 'Tirupati Central Campus', 'TCC', 'Tirupati Central, AP', 'active'],
    [3, 'Government polytechinc pillaripattu, Puttur.', 'PLPT', 'Pillaripattu, Puttur, AP', 'active'],
    [4, 'Venkata Perumal', 'VKPT', 'Tirupati, AP', 'active'],
    [5, 'University of California, Berkeley', 'UCB', 'Berkeley, CA, USA', 'active'],
    [6, 'Columbia University', 'COLUMBIA', 'New York, NY, USA', 'active'],
    [7, 'Georgia Institute of Technology (Georgia Tech)', 'GATECH', 'Atlanta, GA, USA', 'active'],
    [8, 'Carnegie Mellon University (CMU)', 'CMU', 'Pittsburgh, PA, USA', 'active'],
    [9, 'Other Academic Institution', 'OTHER', 'Global / Other', 'active'],
];

foreach ($campusesToSeed as $c) {
    $stmtC = $conn->prepare("INSERT INTO `campuses` (`id`, `name`, `code`, `address`, `status`) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
          `name` = VALUES(`name`),
          `code` = VALUES(`code`),
          `address` = COALESCE(VALUES(`address`), `address`),
          `status` = VALUES(`status`)");
    $stmtC->bind_param("issss", $c[0], $c[1], $c[2], $c[3], $c[4]);
    $stmtC->execute();
    $stmtC->close();
}
echo "OK.\n";

// 3. Add campus_id to users and remap accurately
echo "[3/10] Checking 'users.campus_id' and mapping students... ";
if (!colExists($conn, 'users', 'campus_id')) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL AFTER `user_id`, ADD INDEX `idx_user_campus` (`campus_id`)");
    echo "Added column. ";
}

// Remap students by college name
$conn->query("UPDATE `users` SET `campus_id` = 3 WHERE `college` LIKE '%pillaripattu%' OR `college` LIKE '%Puttur%'");
$conn->query("UPDATE `users` SET `campus_id` = 9 WHERE `college` LIKE '%other%' OR `college` LIKE '%Other%'");
$conn->query("UPDATE `users` SET `campus_id` = 1 WHERE (`college` LIKE '%Sri Venkateswara%' OR `college` = 'svgp') AND `campus_id` != 3");
$conn->query("UPDATE `users` SET `campus_id` = 1 WHERE `campus_id` IS NULL OR `campus_id` = 0");
echo "OK.\n";

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
    $conn->query("ALTER TABLE `lost_reports` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL, ADD INDEX `idx_lost_campus` (`campus_id`)");
    echo "Added column. ";
}
$conn->query("UPDATE `lost_reports` SET `campus_id` = 1 WHERE `campus_id` IS NULL OR `campus_id` = 0");
echo "OK.\n";

// 6. Add campus_id to found_reports
echo "[6/10] Checking 'found_reports.campus_id'... ";
if (!colExists($conn, 'found_reports', 'campus_id')) {
    $conn->query("ALTER TABLE `found_reports` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL, ADD INDEX `idx_found_campus` (`campus_id`)");
    echo "Added column. ";
}
$conn->query("UPDATE `found_reports` SET `campus_id` = 1 WHERE `campus_id` IS NULL OR `campus_id` = 0");
echo "OK.\n";

// 7. Add campus_id to matches
echo "[7/10] Checking 'matches.campus_id'... ";
if (!colExists($conn, 'matches', 'campus_id')) {
    $conn->query("ALTER TABLE `matches` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL, ADD INDEX `idx_match_campus` (`campus_id`)");
    echo "Added column. ";
}
$conn->query("UPDATE `matches` SET `campus_id` = 1 WHERE `campus_id` IS NULL OR `campus_id` = 0");
echo "OK.\n";

// 8. Add campus_id to recovery_cases
echo "[8/10] Checking 'recovery_cases.campus_id'... ";
if (!colExists($conn, 'recovery_cases', 'campus_id')) {
    $conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL, ADD INDEX `idx_case_campus` (`campus_id`)");
    echo "Added column. ";
}
$conn->query("UPDATE `recovery_cases` SET `campus_id` = 1 WHERE `campus_id` IS NULL OR `campus_id` = 0");
echo "OK.\n";

// 9. Add campus_id to notifications
echo "[9/10] Checking 'notifications.campus_id'... ";
if (!colExists($conn, 'notifications', 'campus_id')) {
    $conn->query("ALTER TABLE `notifications` ADD COLUMN `campus_id` INT(11) NULL DEFAULT NULL, ADD INDEX `idx_notif_campus` (`campus_id`)");
    echo "Added column. ";
}
$conn->query("UPDATE `notifications` SET `campus_id` = 1 WHERE `campus_id` IS NULL OR `campus_id` = 0");
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

echo "=== Migration Finished Successfully! ===\n\n";

echo "--- Live Counts Across All Campuses ---\n";
$allC = $conn->query("SELECT id, name, code FROM campuses ORDER BY id ASC");
if ($allC) {
    while ($c = $allC->fetch_assoc()) {
        $uCount = $conn->query("SELECT COUNT(*) as c FROM users WHERE campus_id = " . (int)$c['id'])->fetch_assoc()['c'];
        $lCount = $conn->query("SELECT COUNT(*) as c FROM lost_reports WHERE campus_id = " . (int)$c['id'])->fetch_assoc()['c'];
        $fCount = $conn->query("SELECT COUNT(*) as c FROM found_reports WHERE campus_id = " . (int)$c['id'])->fetch_assoc()['c'];
        echo "Campus #{$c['id']} [{$c['code']}] {$c['name']}: {$uCount} Students | {$lCount} Lost | {$fCount} Found\n";
    }
}
