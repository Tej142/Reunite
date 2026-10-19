<?php
/**
 * Reunite - Matching Engine v2 Schema Migration Runner
 * Idempotently verifies and applies all required columns and indexes for Matching v2.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: text/plain; charset=utf-8');

global $conn;

if (!$conn) {
    http_response_code(500);
    echo "ERROR: Database connection unavailable: " . ($db_connection_error ?? 'Unknown error') . "\n";
    exit(1);
}

echo "====================================================================\n";
echo "REUNITE: MATCHING ENGINE V2 SCHEMA MIGRATION\n";
echo "Database: " . (defined('DB_NAME') ? DB_NAME : 'reunite') . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

$changesMade = 0;

function checkColumnExists($conn, $table, $column) {
    $tableSafe = $conn->real_escape_string($table);
    $colSafe = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$tableSafe` LIKE '$colSafe'");
    return ($res && $res->num_rows > 0);
}

function checkIndexExists($conn, $table, $indexName) {
    $tableSafe = $conn->real_escape_string($table);
    $idxSafe = $conn->real_escape_string($indexName);
    $res = $conn->query("SHOW INDEX FROM `$tableSafe` WHERE Key_name = '$idxSafe'");
    return ($res && $res->num_rows > 0);
}

// 1. matches.engine_version
echo "[1/6] Checking `matches`.`engine_version`...\n";
if (!checkColumnExists($conn, 'matches', 'engine_version')) {
    $sql = "ALTER TABLE `matches` ADD COLUMN `engine_version` VARCHAR(20) NOT NULL DEFAULT '2.0.0' AFTER `match_percentage`";
    if ($conn->query($sql)) {
        echo " -> Added column `engine_version` to `matches`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `engine_version`: " . $conn->error . "\n";
    }
} else {
    echo " -> Column `engine_version` already present.\n";
}

// 2. matches.confidence_tier
echo "\n[2/6] Checking `matches`.`confidence_tier`...\n";
if (!checkColumnExists($conn, 'matches', 'confidence_tier')) {
    $sql = "ALTER TABLE `matches` ADD COLUMN `confidence_tier` ENUM('STRONG', 'POSSIBLE', 'DISCARD') NOT NULL DEFAULT 'STRONG' AFTER `engine_version`";
    if ($conn->query($sql)) {
        echo " -> Added column `confidence_tier` to `matches`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `confidence_tier`: " . $conn->error . "\n";
    }
} else {
    echo " -> Column `confidence_tier` already present.\n";
}

// 3. matches.match_reasons
echo "\n[3/6] Checking `matches`.`match_reasons`...\n";
if (!checkColumnExists($conn, 'matches', 'match_reasons')) {
    $sql = "ALTER TABLE `matches` ADD COLUMN `match_reasons` LONGTEXT NULL DEFAULT NULL AFTER `confidence_tier`";
    if ($conn->query($sql)) {
        echo " -> Added column `match_reasons` to `matches`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `match_reasons`: " . $conn->error . "\n";
    }
} else {
    echo " -> Column `match_reasons` already present.\n";
}

// 4. matches.needs_admin_review
echo "\n[4/6] Checking `matches`.`needs_admin_review`...\n";
if (!checkColumnExists($conn, 'matches', 'needs_admin_review')) {
    $sql = "ALTER TABLE `matches` ADD COLUMN `needs_admin_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `match_reasons`";
    if ($conn->query($sql)) {
        echo " -> Added column `needs_admin_review` to `matches`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `needs_admin_review`: " . $conn->error . "\n";
    }
} else {
    echo " -> Column `needs_admin_review` already present.\n";
}

// 5. matches index idx_tier_status
echo "\n[5/6] Checking index `idx_tier_status` on `matches`...\n";
if (!checkIndexExists($conn, 'matches', 'idx_tier_status')) {
    $sql = "ALTER TABLE `matches` ADD INDEX `idx_tier_status` (`confidence_tier`, `status`)";
    if ($conn->query($sql)) {
        echo " -> Added composite index `idx_tier_status` to `matches`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding index `idx_tier_status`: " . $conn->error . "\n";
    }
} else {
    echo " -> Index `idx_tier_status` already present.\n";
}

// 6. normalized_attributes on lost_reports and found_reports
echo "\n[6/6] Checking `normalized_attributes` on reports...\n";
if (!checkColumnExists($conn, 'lost_reports', 'normalized_attributes')) {
    $sql = "ALTER TABLE `lost_reports` ADD COLUMN `normalized_attributes` LONGTEXT NULL DEFAULT NULL";
    if ($conn->query($sql)) {
        echo " -> Added `normalized_attributes` to `lost_reports`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `normalized_attributes` to `lost_reports`: " . $conn->error . "\n";
    }
} else {
    echo " -> Column `lost_reports`.`normalized_attributes` already present.\n";
}

if (!checkColumnExists($conn, 'found_reports', 'normalized_attributes')) {
    $sql = "ALTER TABLE `found_reports` ADD COLUMN `normalized_attributes` LONGTEXT NULL DEFAULT NULL";
    if ($conn->query($sql)) {
        echo " -> Added `normalized_attributes` to `found_reports`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `normalized_attributes` to `found_reports`: " . $conn->error . "\n";
    }
} else {
    echo " -> Column `found_reports`.`normalized_attributes` already present.\n";
}

echo "\n====================================================================\n";
echo "MIGRATION COMPLETE. Changes applied: {$changesMade}\n";
echo "====================================================================\n";
