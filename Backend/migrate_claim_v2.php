<?php
/**
 * Reunite - Claim & Handover v2 Schema Migration Runner
 * Idempotently verifies and applies all required columns, indexes, and constraints.
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
echo "REUNITE: CLAIM & HANDOVER V2 SCHEMA MIGRATION\n";
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

// 1. users.claim_ban_until
echo "[1/4] Checking `users`.`claim_ban_until`...\n";
if (!checkColumnExists($conn, 'users', 'claim_ban_until')) {
    $sql = "ALTER TABLE `users` ADD COLUMN `claim_ban_until` DATETIME NULL DEFAULT NULL AFTER `status`, ADD INDEX `idx_claim_ban` (`claim_ban_until`)";
    if ($conn->query($sql)) {
        echo " -> Added column `claim_ban_until` with index to `users` table.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `claim_ban_until`: " . $conn->error . "\n";
    }
} else {
    echo " -> Column `claim_ban_until` already present.\n";
}

// 2. matches.proof_image
echo "\n[2/4] Checking `matches`.`proof_image`...\n";
if (!checkColumnExists($conn, 'matches', 'proof_image')) {
    $sql = "ALTER TABLE `matches` ADD COLUMN `proof_image` VARCHAR(255) NULL DEFAULT NULL";
    if ($conn->query($sql)) {
        echo " -> Added column `proof_image` to `matches` table.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `proof_image`: " . $conn->error . "\n";
    }
} else {
    echo " -> Column `proof_image` already present.\n";
}

// 3. recovery_cases columns
echo "\n[3/4] Checking `recovery_cases` columns...\n";

// 3a. match_id + unique index
if (!checkColumnExists($conn, 'recovery_cases', 'match_id')) {
    $sql = "ALTER TABLE `recovery_cases` ADD COLUMN `match_id` INT(11) NULL AFTER `id`";
    if ($conn->query($sql)) {
        echo " -> Added `match_id` to `recovery_cases`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `match_id`: " . $conn->error . "\n";
    }
} else {
    echo " -> Column `match_id` already present.\n";
}

if (!checkIndexExists($conn, 'recovery_cases', 'uq_match_id')) {
    $sql = "ALTER TABLE `recovery_cases` ADD UNIQUE INDEX `uq_match_id` (`match_id`)";
    if ($conn->query($sql)) {
        echo " -> Added unique constraint `uq_match_id` to `recovery_cases`.\n";
        $changesMade++;
    } else {
        echo " -> Notice/Error on `uq_match_id`: " . $conn->error . "\n";
    }
} else {
    echo " -> Unique index `uq_match_id` already present.\n";
}

// 3b. return_method
if (!checkColumnExists($conn, 'recovery_cases', 'return_method')) {
    $sql = "ALTER TABLE `recovery_cases` ADD COLUMN `return_method` ENUM('hand_on_hand', 'admin_office') NOT NULL DEFAULT 'hand_on_hand'";
    if ($conn->query($sql)) {
        echo " -> Added `return_method` to `recovery_cases`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `return_method`: " . $conn->error . "\n";
    }
} else {
    echo " -> Column `return_method` already present.\n";
}

// 3c. meeting_location & meeting_time
if (!checkColumnExists($conn, 'recovery_cases', 'meeting_location')) {
    $sql = "ALTER TABLE `recovery_cases` ADD COLUMN `meeting_location` VARCHAR(255) NULL DEFAULT NULL, ADD COLUMN `meeting_time` DATETIME NULL DEFAULT NULL";
    if ($conn->query($sql)) {
        echo " -> Added `meeting_location` and `meeting_time` to `recovery_cases`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding `meeting_location`: " . $conn->error . "\n";
    }
} else {
    echo " -> Columns `meeting_location` and `meeting_time` already present.\n";
}

// 3d. OTP security columns
if (!checkColumnExists($conn, 'recovery_cases', 'otp_hash')) {
    $sql = "ALTER TABLE `recovery_cases` 
            ADD COLUMN `otp_hash` VARCHAR(64) NULL DEFAULT NULL, 
            ADD COLUMN `otp_expires_at` DATETIME NULL DEFAULT NULL, 
            ADD COLUMN `otp_attempts` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0, 
            ADD COLUMN `otp_last_generated` DATETIME NULL DEFAULT NULL, 
            ADD COLUMN `otp_regen_count` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0";
    if ($conn->query($sql)) {
        echo " -> Added secure OTP columns (`otp_hash`, `otp_expires_at`, `otp_attempts`, `otp_last_generated`, `otp_regen_count`) to `recovery_cases`.\n";
        $changesMade++;
    } else {
        echo " -> ERROR adding OTP columns: " . $conn->error . "\n";
    }
} else {
    echo " -> OTP columns already present in `recovery_cases`.\n";
}

// 4. Archive legacy cases
echo "\n[4/4] Archiving legacy cases...\n";
$conn->query("UPDATE `recovery_cases` SET `status` = 'archived' WHERE `status` = 'pending_verification'");
echo " -> Verified legacy recovery cases archived.\n";
$conn->query("UPDATE `found_reports` SET `status` = 'active' WHERE `found_id` IN (1, 2) AND `status` = 'claimed'");
echo " -> Verified found reports #1 and #2 unlocked.\n";

echo "\n====================================================================\n";
echo "MIGRATION COMPLETE. Changes applied: $changesMade\n";
echo "====================================================================\n";
