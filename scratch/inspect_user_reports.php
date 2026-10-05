<?php
require_once dirname(__DIR__) . '/Backend/config/config.php';
global $conn;

echo "=== USERS ===\n";
$resU = $conn->query("SELECT user_id, full_name, email FROM users");
while($u = $resU->fetch_assoc()) echo "User #{$u['user_id']}: {$u['full_name']} ({$u['email']})\n";

echo "\n=== LOST REPORTS ===\n";
$resL = $conn->query("SELECT * FROM lost_reports ORDER BY id DESC LIMIT 5");
while($l = $resL->fetch_assoc()) print_r($l);

echo "\n=== FOUND REPORTS ===\n";
$resF = $conn->query("SELECT * FROM found_reports ORDER BY id DESC LIMIT 5");
while($f = $resF->fetch_assoc()) print_r($f);

echo "\n=== MATCHES ===\n";
$resM = $conn->query("SELECT * FROM matches ORDER BY id DESC LIMIT 5");
while($m = $resM->fetch_assoc()) print_r($m);

echo "\n=== NOTIFICATIONS ===\n";
$resN = $conn->query("SELECT * FROM notifications ORDER BY notification_id DESC LIMIT 5");
while($n = $resN->fetch_assoc()) print_r($n);

echo "\n=== ACCESS LOGS (LATEST 10) ===\n";
$resA = $conn->query("SELECT * FROM access_logs ORDER BY log_id DESC LIMIT 10");
if ($resA) while($a = $resA->fetch_assoc()) echo "[{$a['created_at']}] User {$a['user_id']}: {$a['action']} - {$a['device_info']}\n";
