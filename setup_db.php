<?php
/**
 * REUNITE PLATFORM — PRODUCTION DATABASE SETUP & MIGRATOR
 * Access via: https://reunite.site.je/setup_db.php
 */

require_once __DIR__ . '/Backend/config/config.php';
require_once __DIR__ . '/Backend/functions.php';

global $conn;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reunite Database Auto-Migrator & Configuration</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #12100E; color: #EDE5DE; padding: 40px 20px; line-height: 1.6; }
    .container { max-width: 780px; margin: 0 auto; background: #1C1917; border: 1px solid #332B25; border-radius: 12px; padding: 32px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    h1 { color: #C4622D; margin-top: 0; font-size: 26px; }
    .status-item { padding: 10px 14px; margin-bottom: 8px; border-radius: 6px; font-size: 14px; display: flex; align-items: center; justify-content: space-between; }
    .status-ok { background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); color: #4ADE80; }
    .status-warn { background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); color: #FACC15; }
    .status-err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #F87171; }
    .btn { display: inline-block; background: #C4622D; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; font-size: 15px; }
    .btn:hover { background: #E07A44; }
    .config-card { background: #161412; border: 1px solid #2B2520; border-radius: 8px; padding: 20px; margin-top: 20px; }
    pre { background: #0A0908; padding: 12px; border-radius: 6px; overflow-x: auto; color: #A89F91; font-size: 13px; }
  </style>
</head>
<body>
<div class="container">
  <h1>⚡ Reunite Database Migration & Setup</h1>

<?php
if (!$conn) {
    echo '<div class="status-item status-err">';
    echo '<span><strong>Connection Failed:</strong> Cannot connect to MySQL with current settings.</span>';
    echo '<span>NOT CONNECTED</span>';
    echo '</div>';
    echo '<p style="color:#A89F91;">Current target credentials from <code>.env.example</code> / <code>.env</code>:</p>';
    echo '<pre>DB_HOST: ' . htmlspecialchars(DB_HOST) . "\nDB_NAME: " . htmlspecialchars(DB_NAME) . "\nDB_USER: " . htmlspecialchars(DB_USER) . '</pre>';
    echo '</div></body></html>';
    exit;
}

echo '<div class="status-item status-ok">';
echo '<span><strong>Database Connected:</strong> Connected to MySQL database `' . htmlspecialchars(DB_NAME) . '` on `' . htmlspecialchars(DB_HOST) . '`</span>';
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
    $passHash = password_hash('charan142009', PASSWORD_DEFAULT);
    $conn->query("UPDATE `users` SET password_hash = '$passHash', status = 'active' WHERE pin = '24155-cm-002'");
    echo "<div class='status-item status-ok'><span>Student account <strong>24155-cm-002</strong> verified & active!</span><span>READY</span></div>";
}

echo '<br><a href="Frontend/login.php" class="btn">Go to Login Page &rarr;</a>';
?>
</div>
</body>
</html>
