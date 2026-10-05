<?php
/**
 * Migration: Create maintenance_settings table
 * Run once via browser or PHP CLI
 */
require_once dirname(__DIR__) . '/Backend/config/config.php';

if (!$conn) {
    die("No DB connection\n");
}

$sql = "CREATE TABLE IF NOT EXISTS `maintenance_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `message` VARCHAR(500) DEFAULT NULL,
    `eta` DATETIME DEFAULT NULL,
    `support_email` VARCHAR(150) DEFAULT NULL,
    `updated_by` VARCHAR(100) DEFAULT 'System Administrator',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo "Table 'maintenance_settings' created OK.\n";
    
    // Seed an initial disabled row if empty
    $check = $conn->query("SELECT COUNT(*) as c FROM maintenance_settings");
    $row = $check->fetch_assoc();
    if ((int)$row['c'] === 0) {
        $conn->query("INSERT INTO maintenance_settings (enabled, message, eta, support_email, updated_by) VALUES (0, NULL, NULL, NULL, 'System')");
        echo "Initial row seeded.\n";
    } else {
        echo "Table already has data, skipping seed.\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
echo "Done.\n";
