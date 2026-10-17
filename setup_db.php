<?php
/**
 * REUNITE PLATFORM — ONE-CLICK PRODUCTION DATABASE MIGRATOR & SETUP
 * Run this by opening: https://reunite.site.je/setup_db.php (or your domain) in your browser.
 */

require_once __DIR__ . '/Backend/config/config.php';
require_once __DIR__ . '/Backend/functions.php';

global $conn;

$isAdmin = !empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true;
$token = trim($_GET['token'] ?? '');
$authorized = $isAdmin || (!empty($token) && hash_equals(defined('ADMIN_MASTER_KEY') ? ADMIN_MASTER_KEY : 'admin@reunite2024', $token));

if (!$authorized) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="background:#12100E;color:#F87171;font-family:sans-serif;padding:40px;text-align:center;">';
    echo '<h1>403 Forbidden</h1><p>Access Denied: Database migration requires administrator authentication or a valid ?token= parameter.</p>';
    echo '<p><a href="Frontend/admin.php" style="color:#C4622D;">Return to Admin Console &rarr;</a></p>';
    echo '</body></html>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reunite Database Auto-Migrator</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #12100E; color: #EDE5DE; padding: 40px 20px; line-height: 1.6; }
    .container { max-width: 780px; margin: 0 auto; background: #1C1917; border: 1px solid #332B25; border-radius: 12px; padding: 32px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    h1 { color: #C4622D; margin-top: 0; font-size: 26px; }
    .status-item { padding: 10px 14px; margin-bottom: 8px; border-radius: 6px; font-size: 14px; display: flex; align-items: center; justify-content: space-between; }
    .status-ok { background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); color: #4ADE80; }
    .status-warn { background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); color: #FACC15; }
    .status-err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #F87171; }
    .btn { display: inline-block; background: #C4622D; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; margin-top: 20px; }
    .btn:hover { background: #E07A44; }
    pre { background: #0A0908; padding: 12px; border-radius: 6px; overflow-x: auto; color: #A89F91; font-size: 13px; }
  </style>
</head>
<body>
<div class="container">
  <h1>⚡ Reunite Database Migration & Setup</h1>

<?php

if (!$conn) {
    echo '<div class="status-item status-err">';
    echo '<span><strong>Connection Failed:</strong> Unable to connect to MySQL database.</span>';
    echo '<span>Error</span>';
    echo '</div>';
    echo '<p>Please check your database credentials in <code>.env</code> or <code>.env.example</code>.</p>';
    echo '<pre>DB_HOST: ' . htmlspecialchars(DB_HOST) . "\nDB_NAME: " . htmlspecialchars(DB_NAME) . "\nDB_USER: " . htmlspecialchars(DB_USER) . '</pre>';
    echo '</div></body></html>';
    exit;
}

echo '<div class="status-item status-ok">';
echo '<span><strong>Database Connected:</strong> Connected to MySQL database `' . htmlspecialchars(DB_NAME) . '`</span>';
echo '<span>SUCCESS</span>';
echo '</div>';

$queries = [
    "Users Table" => "CREATE TABLE IF NOT EXISTS `users` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Digital DNA Table" => "CREATE TABLE IF NOT EXISTS `digital_dna` (
      `dna_id` INT(11) NOT NULL AUTO_INCREMENT,
      `report_type` ENUM('LOST', 'FOUND') NOT NULL,
      `report_id` INT(11) NOT NULL,
      `category` VARCHAR(50) DEFAULT NULL,
      `shape` VARCHAR(50) DEFAULT NULL,
      `encrypted_dna` LONGTEXT DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`dna_id`),
      KEY `idx_dna_report` (`report_type`, `report_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Lost Reports Table" => "CREATE TABLE IF NOT EXISTS `lost_reports` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Found Reports Table" => "CREATE TABLE IF NOT EXISTS `found_reports` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Matches Table" => "CREATE TABLE IF NOT EXISTS `matches` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `lost_report_id` INT(11) NOT NULL,
      `found_report_id` INT(11) NOT NULL,
      `similarity_score` DECIMAL(5,2) NOT NULL,
      `status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_match_lost` (`lost_report_id`),
      KEY `idx_match_found` (`found_report_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Recovery Cases Table" => "CREATE TABLE IF NOT EXISTS `recovery_cases` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Notifications Table" => "CREATE TABLE IF NOT EXISTS `notifications` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Password Resets Table" => "CREATE TABLE IF NOT EXISTS `password_resets` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) NOT NULL,
      `email` VARCHAR(255) NOT NULL,
      `token` VARCHAR(128) NOT NULL,
      `expires_at` DATETIME NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_reset_token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Maintenance Settings Table" => "CREATE TABLE IF NOT EXISTS `maintenance_settings` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `enabled` TINYINT(1) NOT NULL DEFAULT 0,
      `message` VARCHAR(500) DEFAULT NULL,
      `eta` DATETIME DEFAULT NULL,
      `support_email` VARCHAR(150) DEFAULT NULL,
      `updated_by` VARCHAR(100) DEFAULT 'System Administrator',
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Admins Table" => "CREATE TABLE IF NOT EXISTS `admins` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Logs Table" => "CREATE TABLE IF NOT EXISTS `logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "Question Sets Table" => "CREATE TABLE IF NOT EXISTS `question_sets` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `category` VARCHAR(100) NOT NULL,
      `report_type` ENUM('LOST', 'FOUND') NOT NULL,
      `questions_json` LONGTEXT NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_question_set` (`category`, `report_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
];

foreach ($queries as $name => $sql) {
    if ($conn->query($sql)) {
        echo "<div class='status-item status-ok'><span>$name verified/created</span><span>OK</span></div>";
    } else {
        echo "<div class='status-item status-err'><span>$name error: " . htmlspecialchars($conn->error) . "</span><span>ERR</span></div>";
    }
}

// ── Ensure Columns Exist in Users Table ───────────────────
$alterCols = [
    "dob" => "ALTER TABLE `users` ADD COLUMN `dob` VARCHAR(10) DEFAULT NULL AFTER `status`",
    "college" => "ALTER TABLE `users` ADD COLUMN `college` VARCHAR(50) DEFAULT NULL AFTER `dob`",
    "branch" => "ALTER TABLE `users` ADD COLUMN `branch` VARCHAR(5) DEFAULT NULL AFTER `college`",
    "email_verified" => "ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT(1) DEFAULT 1 AFTER `branch`"
];
foreach ($alterCols as $col => $alterSql) {
    $check = $conn->query("SHOW COLUMNS FROM `users` LIKE '$col'");
    if ($check && $check->num_rows === 0) {
        $conn->query($alterSql);
        echo "<div class='status-item status-warn'><span>Added missing column `$col` to `users`</span><span>ADDED</span></div>";
    }
}

// ── Ensure Columns & Modifiers Exist in Recovery Cases Table ─
$rcAlterCols = [
    "claimant_id" => "ALTER TABLE `recovery_cases` ADD COLUMN `claimant_id` INT(11) NOT NULL DEFAULT 0 AFTER `id`",
    "report_id" => "ALTER TABLE `recovery_cases` ADD COLUMN `report_id` INT(11) NOT NULL DEFAULT 0 AFTER `claimant_id`",
    "found_id" => "ALTER TABLE `recovery_cases` ADD COLUMN `found_id` INT(11) NOT NULL DEFAULT 0 AFTER `report_id`",
    "lost_id" => "ALTER TABLE `recovery_cases` ADD COLUMN `lost_id` INT(11) DEFAULT NULL AFTER `found_id`",
    "proof_details" => "ALTER TABLE `recovery_cases` ADD COLUMN `proof_details` TEXT DEFAULT NULL AFTER `lost_id`",
    "meeting_time" => "ALTER TABLE `recovery_cases` ADD COLUMN `meeting_time` VARCHAR(100) DEFAULT NULL AFTER `proof_details`",
    "meeting_location" => "ALTER TABLE `recovery_cases` ADD COLUMN `meeting_location` VARCHAR(255) DEFAULT NULL AFTER `meeting_time`",
    "otp" => "ALTER TABLE `recovery_cases` ADD COLUMN `otp` VARCHAR(10) DEFAULT NULL AFTER `meeting_location`",
    "otp_expires_at" => "ALTER TABLE `recovery_cases` ADD COLUMN `otp_expires_at` DATETIME DEFAULT NULL AFTER `otp`"
];
foreach ($rcAlterCols as $col => $alterSql) {
    $check = $conn->query("SHOW COLUMNS FROM `recovery_cases` LIKE '$col'");
    if ($check && $check->num_rows === 0) {
        $conn->query($alterSql);
        echo "<div class='status-item status-warn'><span>Added missing column `$col` to `recovery_cases`</span><span>ADDED</span></div>";
    }
}
// Ensure status column can accept modern status strings
$conn->query("ALTER TABLE `recovery_cases` MODIFY `status` VARCHAR(50) DEFAULT 'pending_verification'");

// ── Ensure Student Account (24155-cm-002) Exists ─────────
$checkUser = $conn->query("SELECT user_id FROM `users` WHERE pin = '24155-cm-002'");
if ($checkUser && $checkUser->num_rows === 0) {
    $pin = '24155-cm-002';
    $name = 'Charan';
    $email = encryptData('charante153624@gmail.com');
    $passHash = password_hash('charan142009', PASSWORD_DEFAULT);
    $college = 'Sri Venkateswara Govt Polytechnic';
    $branch = 'cme';
    $dob = '2000-01-01';
    
    $insertUser = $conn->prepare("INSERT INTO `users` (full_name, pin, email, password_hash, trust_score, role, status, dob, college, branch, email_verified) VALUES (?, ?, ?, ?, 100, 'user', 'active', ?, ?, ?, 1)");
    if ($insertUser) {
        $insertUser->bind_param("sssssss", $name, $pin, $email, $passHash, $dob, $college, $branch);
        if ($insertUser->execute()) {
            echo "<div class='status-item status-ok'><span>Created student account: <strong>24155-cm-002</strong> (Password: <code>charan142009</code>)</span><span>READY</span></div>";
        }
        $insertUser->close();
    }
} else {
    // Make sure password is known and correct
    $passHash = password_hash('charan142009', PASSWORD_DEFAULT);
    $conn->query("UPDATE `users` SET password_hash = '$passHash', status = 'active' WHERE pin = '24155-cm-002'");
    echo "<div class='status-item status-ok'><span>Student account <strong>24155-cm-002</strong> verified & active!</span><span>READY</span></div>";
}

// ── Optional: Seed Sample Report for Verification ─────────
if (isset($_GET['seed_sample'])) {
    $uQ = $conn->query("SELECT user_id FROM `users` WHERE pin = '24155-cm-002' LIMIT 1");
    if ($uQ && ($uRow = $uQ->fetch_assoc())) {
        $cUserId = (int)$uRow['user_id'];
        
        // 1. Insert sample lost report if none exists
        $checkLost = $conn->query("SELECT id FROM `lost_reports` WHERE user_id = $cUserId LIMIT 1");
        $lostId = null;
        if ($checkLost && $checkLost->num_rows === 0) {
            $lStmt = $conn->prepare("INSERT INTO `lost_reports` (user_id, category, title, description, location, date_lost, status) VALUES (?, 'Electronics', 'HP Pavilion 15 Laptop (Silver)', 'Left on desk in 2nd floor library reading room. Silver finish with black case.', 'Main Campus Library', CURDATE(), 'active')");
            if ($lStmt) {
                $lStmt->bind_param("i", $cUserId);
                $lStmt->execute();
                $lostId = $lStmt->insert_id;
                $lStmt->close();
                echo "<div class='status-item status-ok'><span>Seeded sample lost report (ID: RL-" . str_pad($lostId, 5, '0', STR_PAD_LEFT) . ")</span><span>SEEDED</span></div>";
            }
        } else {
            $lostId = (int)$checkLost->fetch_assoc()['id'];
            echo "<div class='status-item status-warn'><span>Lost report already exists (ID: RL-" . str_pad($lostId, 5, '0', STR_PAD_LEFT) . ")</span><span>EXISTS</span></div>";
        }

        // 2. Insert sample found report if none exists
        $checkFound = $conn->query("SELECT id FROM `found_reports` LIMIT 1");
        $foundId = null;
        if ($checkFound && $checkFound->num_rows === 0) {
            $fStmt = $conn->prepare("INSERT INTO `found_reports` (user_id, category, title, description, location, date_found, status) VALUES (?, 'Electronics', 'Silver HP Laptop in Black Pouch', 'Found unattended laptop on table near reference section in central library.', 'Central Library Hall', CURDATE(), 'active')");
            if ($fStmt) {
                $fStmt->bind_param("i", $cUserId);
                $fStmt->execute();
                $foundId = $fStmt->insert_id;
                $fStmt->close();
                echo "<div class='status-item status-ok'><span>Seeded sample found report (ID: RF-" . str_pad($foundId, 5, '0', STR_PAD_LEFT) . ")</span><span>SEEDED</span></div>";
            }
        } else {
            $foundId = (int)$checkFound->fetch_assoc()['id'];
            echo "<div class='status-item status-warn'><span>Found report already exists (ID: RF-" . str_pad($foundId, 5, '0', STR_PAD_LEFT) . ")</span><span>EXISTS</span></div>";
        }

        // 3. Insert sample AI match if both exist
        if ($lostId && $foundId) {
            $checkMatch = $conn->query("SELECT id FROM `matches` WHERE lost_report_id = $lostId AND found_report_id = $foundId LIMIT 1");
            if ($checkMatch && $checkMatch->num_rows === 0) {
                $mStmt = $conn->prepare("INSERT INTO `matches` (lost_report_id, found_report_id, similarity_score, status, return_type) VALUES (?, ?, 92.50, 'pending', 'hand_on_hand')");
                if ($mStmt) {
                    $mStmt->bind_param("ii", $lostId, $foundId);
                    $mStmt->execute();
                    $mStmt->close();
                    echo "<div class='status-item status-ok'><span>Generated 92.50% AI Match between report #$lostId and #$foundId</span><span>SEEDED</span></div>";
                }
            }
        }
        echo "<div class='status-item status-ok'><span>Profile stats verification data ready!</span><span>DONE</span></div>";
    }
}

echo '<div style="margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap;">';
echo '<a href="Frontend/profile.php" class="btn" style="background:#22C55E;">View Profile &rarr;</a>';
echo '<a href="setup_db.php?seed_sample=1" class="btn" style="background:#3B82F6;">Seed Sample Report & AI Match</a>';
echo '<a href="Frontend/login.php" class="btn">Go to Login Page &rarr;</a>';
echo '</div>';
?>
</div>
</body>
</html>
